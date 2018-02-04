<?php
#code for CMSMS
#Copyright (C) 2004-2016 Ted Kulp <ted@cmsmadesimple.org>
#Copyright (C) 2016-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
#This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>#
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
#
#$Id$

$CMS_ADMIN_PAGE=1;
$CMS_LOGIN_PAGE=1;

require_once '..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';
$gCms = CmsApp::get_instance();
$db = $gCms->GetDb();
$login_ops = CMSMS\internal\LoginOperations::get_instance();

$error = '';
$forgotmessage = ''; //needed ?
$changepwhash = '';

/**
 * A function to send lost password recovery email to a specified admin user (by name)
 *
 * @internal
 * @access private
 * @param string the username
 * @return results from the attempt to send a message.
 */
function send_recovery_email(User $user)
{
    $gCms = CmsApp::get_instance();
    $config = $gCms->GetConfig();
    $userops = $gCms->GetUserOperations();

    $obj = new cms_mailer();
    $obj->IsHTML(TRUE);
    $obj->AddAddress($user->email,cms_html_entity_decode($user->firstname.' '.$user->lastname));
    $obj->SetSubject(lang('lostpwemailsubject',html_entity_decode(get_site_preference('sitename','CMSMS Site'))));

    $url = $config['admin_url'] . '/login.php?recoverme=' . md5(md5($config['root_path'] . '--' . $user->username . md5($user->password)));
    $body = lang('lostpwemail',cms_html_entity_decode(get_site_preference('sitename','CMSMS Site')), $user->username, $url, $url);

    $obj->SetBody($body);

    audit('','Core','Sent Lost Password Email for '.$user->username);
    return $obj->Send();
}

/**
 * A function find a matching user id given an identity hash
 *
 * @internal
 * @access private
 * @param string the hash
 * @return object The matching user object if found, or null otherwise.
 */
function find_recovery_user($hash)
{
    $gCms = CmsApp::get_instance();
    $config = $gCms->GetConfig();
    $userops = $gCms->GetUserOperations();

    foreach ($userops->LoadUsers() as $user) {
        if ($hash == md5(md5($config['root_path'] . '--' . $user->username . md5($user->password)))) return $user;
    }

    return null;
}



//Redirect to the normal login screen if we hit cancel on the forgot pw one
//Otherwise, see if we have a forgotpw hit
if ((isset($_REQUEST['forgotpwform']) || isset($_REQUEST['forgotpwchangeform'])) && isset($_REQUEST['logincancel'])) {
    redirect('login.php');
}
else if (isset($_REQUEST['forgotpwform']) && isset($_REQUEST['forgottenusername'])) {
    $userops = $gCms->GetUserOperations();
    $forgot_username = filter_var($_REQUEST['forgottenusername'], FILTER_SANITIZE_SRING);
    unset($_REQUEST['forgottenusername'],$_POST['forgottenusername']);
    CMSMS\HookManager::do_hook('Core::LostPassword', [ 'username'=>$forgot_username] );
    $oneuser = $userops->LoadUserByUsername($forgot_username);
    unset($_REQUEST['loginsubmit'],$_POST['loginsubmit']);

    if ($oneuser != null) {
        if ($oneuser->email == '') {
            $error = lang('nopasswordforrecovery');
        }
        else if (send_recovery_email($oneuser)) {
            $warningLogin = lang('recoveryemailsent');
        }
        else {
            $error = lang('errorsendingemail');
        }
    }
    else {
        unset($_POST['username'],$_POST['password'],$_REQUEST['username'],$_REQUEST['password']);
        CMSMS\HookManager::do_hook('Core::LoginFailed', [ 'user'=>$forgot_username ] );
        $error = lang('usernotfound');
    }
}
else if (isset($_REQUEST['recoverme']) && $_REQUEST['recoverme']) {
    $user = find_recovery_user($_REQUEST['recoverme']);
    if ($user == null) {
        $error = lang('usernotfound');
    }
    else {
        $changepwhash = $_REQUEST['recoverme'];
    }
}
else if (isset($_REQUEST['forgotpwchangeform']) && $_REQUEST['forgotpwchangeform']) {
    $user = find_recovery_user($_REQUEST['changepwhash']);
    if ($user == null) {
        $error = lang('usernotfound');
    }
    else {
        if ($_REQUEST['password'] != '') {
            if ($_REQUEST['password'] == $_REQUEST['passwordagain']) {
                $user->SetPassword($_REQUEST['password']);
                $user->Save();
                // put mention into the admin log
                $ip_passw_recovery = cms_utils::get_real_ip();
                audit('','Core','Completed lost password recovery for: '.$user->username.' (IP: '.$ip_passw_recovery.')');
                CMSMS\HookManager::do_hook('Core::LostPasswordReset', [ 'uid'=>$user->id, 'username'=>$user->username, 'ip'=>$ip_passw_recovery ] );
                $acceptLogin = lang('passwordchangedlogin');
                $changepwhash = '';
            }
            else {
                $error = lang('nopasswordmatch');
                $changepwhash = $_REQUEST['changepwhash'];
            }
        }
        else {
            $error = lang('nofieldgiven', array(lang('password')));
            $changepwhash = $_REQUEST['changepwhash'];
        }
    }
}

if (isset($_SESSION['logout_user_now'])) {
    // this does the actual logout stuff.
    unset($_SESSION['logout_user_now']);
    debug_buffer("Logging out.  Cleaning cookies and session variables.");
    $userid = $login_ops->get_loggedin_uid();
    $username = $login_ops->get_loggedin_username();
    CMSMS\HookManager::do_hook('Core::LogoutPre', [ 'uid'=>$userid, 'username'=>$username ] );
    $login_ops->deauthenticate(); // unset all the cruft needed to make sure we're logged in.
    CMSMS\HookManager::do_hook('Core::LogoutPost', [ 'uid'=>$userid, 'username'=>$username ] );
    audit($userid, "Admin Username: ".$username, 'Logged Out');
}

if( isset($_POST['logincancel']) ) {
    debug_buffer("Login cancelled.  Returning to content.");
    $login_ops->deauthenticate(); // just in case
    redirect($config['root_url'].'/index.php', true);
}
else if( isset($_POST['loginsubmit']) ) {
    // login form submitted
    $login_ops->deauthenticate();
    $username = $password = null;
    if (isset($_POST["username"])) $username = cleanValue($_POST["username"]);
    if (isset($_POST["password"])) $password = $_POST["password"];

    $userops = $gCms->GetUserOperations();

    class CmsLoginError extends CmsException {}

    try {
        if( !$password ) throw new CmsLoginError(lang('usernameincorrect'));
        $oneuser = $userops->LoadUserByUsername($username, $password, TRUE, TRUE);
        if( !$oneuser ) throw new CmsLoginError(lang('usernameincorrect'));
        if( ! $oneuser->Authenticate( $password ) ) {
            throw new CmsLoginError( lang('usernameincorrect') );
        }
        $login_ops->save_authentication($oneuser);

        // put mention into the admin log
        audit($oneuser->id, "Admin Username: ".$oneuser->username, 'Logged In');

        // send the post login event
        unset($_POST['username'],$_POST['password'],$_REQUEST['username'],$_REQUEST['password']);
        CMSMS\HookManager::do_hook('Core::LoginPost', [ 'user'=>&$oneuser ] );

        // redirect outa hre somewhere
        if( isset($_SESSION['login_redirect_to']) ) {
            // we previously attempted a URL but didn't have the user key in the request.
            $url_ob = new cms_url($_SESSION['login_redirect_to']);
            unset($_SESSION['login_redirect_to']);
            $url_ob->erase_queryvar('_s_');
            $url_ob->erase_queryvar('sp_');
            $url_ob->set_queryvar(CMS_SECURE_PARAM_NAME,$_SESSION[CMS_USER_KEY]);
            $url = (string) $url_ob;
            redirect($url);
        } else {
            // find the users homepage, if any, and redirect there.
            $homepage = cms_userprefs::get_for_user($oneuser->id,'homepage');
            if( !$homepage ) $homepage = $config['admin_url'];

            // quick hacks to remove old secure param name from homepage url
            // and replace with the correct one.
            $homepage = str_replace('&amp;','&',$homepage);
            $tmp = explode('?',$homepage);
            @parse_str($tmp[1],$tmp2);
            if( in_array('_s_',array_keys($tmp2)) ) unset($tmp2['_s_']);
            if( in_array('sp_',array_keys($tmp2)) ) unset($tmp2['sp_']);
            $tmp2[CMS_SECURE_PARAM_NAME] = $_SESSION[CMS_USER_KEY];
            foreach( $tmp2 as $k => $v ) {
                $tmp3[] = $k.'='.$v;
            }
            $homepage = $tmp[0].'?'.implode('&amp;',$tmp3);

            // and redirect.
            $homepage = cms_html_entity_decode($homepage);
            if( !startswith($homepage,'http') && !startswith($homepage,'//') && startswith($homepage,'/') ) $homepage = CMS_ROOT_URL.$homepage;
            redirect($homepage);
        }
    }
    catch( Exception $e ) {
        $error = $e->GetMessage();
        debug_buffer("Login failed.  Error is: " . $error);
        unset($_POST['password'],$_REQUEST['password']);
        CMSMS\HookManager::do_hook('Core::LoginFailed', [ 'user'=>$_POST['username'] ] );
        // put mention into the admin log
        $ip_login_failed = cms_utils::get_real_ip();
        audit('', '(IP: ' . $ip_login_failed . ') ' . "Admin Username: " . $username, 'Login Failed');
    }
}

//
// display the login form
//

// Language shizzle
cms_admin_sendheaders();
header("Content-Language: " . CmsNlsOperations::get_current_language());

$themeObject = cms_utils::get_theme_object();
$vars = ['error'=>$error];
if( isset($warningLogin) ) $vars['warningLogin'] = $warningLogin;
if( isset($acceptLogin) ) $vars['acceptLogin'] = $acceptLogin;
if( isset($changepwhash) ) $vars['changepwhash'] = $changepwhash;
$themeObject->do_login($vars);
