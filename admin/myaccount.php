<?php
#procedure to display and modify the current user's account data
#Copyright (C) 2004-2018 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
#Thanks to Ted Kulp and all other contributors from the CMSMS Development Team.
#This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
#This program is free software; you can redistribute it and/or modify
#it under the terms of the GNU General Public License as published by
#the Free Software Foundation; either version 2 of the License, or
#(at your option) any later version.
#
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#You should have received a copy of the GNU General Public License
#along with this program. If not, see <https://www.gnu.org/licenses/>.

use \CMSMS\internal\module_meta;

$CMS_ADMIN_PAGE = 1;
$CMS_TOP_MENU = 'admin';
$CMS_ADMIN_TITLE = 'myaccount';

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';

check_login();

$urlext = '?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
if (isset($_POST['cancel'])) {
    redirect('index.php'.$urlext);
}

$userid = get_userid(); // Also checks login

$themeObject = cms_utils::get_theme_object();

if (!check_permission($userid,'Manage My Account')) {
    //TODO some immediate popup    lang('needpermissionto','"Manage My Account"')
    return;
}

$userobj = UserOperations::get_instance()->LoadUserByID($userid); // <- Safe to do, cause if $userid fails, it redirects automatically to login.

if (isset($_POST['submit'])) {
    // validate submitted data
    $valid = true;
    $username = cleanValue($_POST['user']);
    if ($username === '') {
        $valid = false;
        $themeObject->RecordNotice('error', lang('nofieldgiven', lang('username')));
    } elseif (!preg_match('/^[a-zA-Z0-9\._ ]+$/', $username)) {
        $valid = false;
        $themeObject->RecordNotice('error', lang('illegalcharacters', lang('username')));
    }
    $password = $_POST['password']; //no cleanup: any char is valid, & hashed before storage
    $passwordagain = $_POST['passwordagain'];
    if ($password != $passwordagain) {
        $valid = false;
        $themeObject->RecordNotice('error', lang('nopasswordmatch'));
    }
    $email = filter_var($_POST['email'],FILTER_SANITIZE_EMAIL);
    if (!empty($email) && !is_email($email)) {
        $valid = false;
        $themeObject->RecordNotice('error', lang('invalidemail').': '.$email);
    }

    if ($valid) {
        $userobj->username = $username;
        $userobj->firstname = cleanValue($_POST['firstname']);
        $userobj->lastname = cleanValue($_POST['lastname']);
        $userobj->email = $email;
        if ($password) $userobj->SetPassword($password);

        \CMSMS\Events::SendEvent('Core', 'EditUserPre', [ 'user'=>&$userobj ]);

        $result = $userobj->Save();

        if ($result) {
            // put mention into the admin log
            audit($userid, 'Admin Username: '.$userobj->username, 'Edited');
            \CMSMS\Events::SendEvent('Core', 'EditUserPost', [ 'user'=>&$userobj ]);
            $themeObject->RecordNotice('success', lang('accountupdated'));
        } else {
            $themeObject->RecordNotice('error', lang('error_internal'));
        }
    }
}

/**
 * Build page
 */
$userobj->password = '';
$selfurl = basename(__FILE__);
$smarty = CMSMS\internal\Smarty::get_instance();

$smarty->assign([
    'selfurl' => $selfurl,
    'urlext' => $urlext,
    'userobj'=>$userobj,
]);

include_once 'header.php';
$smarty->display('myaccount.tpl');
include_once 'footer.php';
