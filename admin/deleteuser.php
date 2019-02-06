<?php
#procedure to delete an admin user
#Copyright (C) 2004-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

use CMSMS\Events;

if (!isset($_GET['user_id'])) {
    return;
}

$CMS_ADMIN_PAGE=1;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';

check_login();

$urlext='?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
$cur_userid = get_userid();
if( !check_permission($cur_userid, 'Manage Users') ) {
    cms_utils::get_theme_object()->ParkNotice('error', lang('needpermissionto', '"Manage Users"'));
    redirect('listusers.php'.$urlext);
}

$key = '';
$user_id = (int)$_GET['user_id'];
if ($user_id != $cur_userid) {
    $userops = cmsms()->GetUserOperations();
    $ownercount = $userops->CountPageOwnershipByID($user_id);
    if ($ownercount <= 0) {
        $oneuser = $userops->LoadUserByID($user_id);
        $user_name = $oneuser->username;

        Events::SendEvent( 'Core', 'DeleteUserPre', ['user'=>&$oneuser] );

		if ($oneuser->Delete()) {
	        cms_userprefs::remove_for_user($user_id);

	        Events::SendEvent( 'Core', 'DeleteUserPost', ['user'=>&$oneuser] );

	        // put mention into the admin log
	        audit($user_id, 'Admin User: '.$user_name, 'Deleted');
		} else {
	        $key = 'failure';
		}
    } else {
        $key = 'erroruserinuse';
    }
} else {
    $key = 'cantremove';
}

if ($key) {
    cms_utils::get_theme_object()->ParkNotice('error', lang($key));
}
redirect('listusers.php'.$urlext);

