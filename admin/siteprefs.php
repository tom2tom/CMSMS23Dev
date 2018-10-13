<?php
#procedure to display and modify website preferences/settings
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

use CMSMS\contenttypes\Content;
use CMSMS\FormUtils;
use CMSMS\internal\module_meta;
use CMSMS\internal\Smarty;
use CMSMS\Mailer;
use CMSMS\ModuleOperations;
use CMSMS\SyntaxEditor;

$CMS_ADMIN_PAGE=1;
$CMS_TOP_MENU='admin';
$CMS_ADMIN_TITLE='preferences';

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';

$userid = get_userid(); // <- Also checks login

$urlext='?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
if (isset($_POST['cancel'])) {
    redirect('index.php'.$urlext);
    return;
}

$access = check_permission($userid, 'Modify Site Preferences');

$themeObject = cms_utils::get_theme_object();

if (!$access) {
    //TODO some immediate popup    $themeObject->RecordNotice('error', lang('needpermissionto', '"Modify Site Preferences"'));
    return;
}

/**
 * Interpret octal permissions $perms into human-readable strings
 *
 * @param int $perms The permissions to process
 * @return array strings for owner,group,other
 */
function siteprefs_interpret_permissions(int $perms) : array
{
    $owner = [];
    $group = [];
    $other = [];

    if ($perms & 0400) {
        $owner[] = lang('read');
    }
    if ($perms & 0200) {
        $owner[] = lang('write');
    }
    if ($perms & 0100) {
        $owner[] = lang('execute');
    }
    if ($perms & 0040) {
        $group[] = lang('read');
    }
    if ($perms & 0020) {
        $group[] = lang('write');
    }
    if ($perms & 0010) {
        $group[] = lang('execute');
    }
    if ($perms & 0004) {
        $other[] = lang('read');
    }
    if ($perms & 0002) {
        $other[] = lang('write');
    }
    if ($perms & 0001) {
        $other[] = lang('execute');
    }

    return [$owner,$group,$other];
}

/**
 * Interpret permissions $permsarr into a human-readable string
 *
 * @param array $permsarr 3-members
 * @return string
 */
function siteprefs_display_permissions(array $permsarr) : string
{
    $tmparr = [lang('owner'),lang('group'),lang('other')];
    if (count($permsarr) != 3) {
        return lang('permissions_parse_error');
    }

    $result = [];
    for ($i = 0; $i < 3; $i++) {
        $str = $tmparr[$i].': ';
        $str .= implode(',', $permsarr[$i]);
        $result[] = $str;
    }
    $str = implode('<br />&nbsp;&nbsp;', $result);
    return $str;
}

$errors = [];
$messages = [];

$config = cms_config::get_instance();
$pretty_urls = $config['url_rewriting'] == 'none' ? 0 : 1;

cleanArray($_POST);

$tab = (isset($_POST['active_tab'])) ? trim($_POST['active_tab']) : '';

if (!empty($_POST['testmail'])) {
    if (!cms_siteprefs::get('mail_is_set', 0)) {
        $errors[] = lang('error_mailnotset_notest');
    } elseif ($_POST['mailtest_testaddress'] == '') {
        $errors[] = lang('error_mailtest_noaddress');
    } else {
        $addr = filter_var($_POST['mailtest_testaddress'], FILTER_SANITIZE_EMAIL);
        if (!is_email($addr)) {
            $errors[] = lang('error_mailtest_notemail');
        } else {
            // we got an email, and we have settings.
            try {
                $mailer = new Mailer();
                $mailer->AddAddress($addr);
                $mailer->IsHTML(true);
                $mailer->SetBody(lang('mail_testbody', 'siteprefs'));
                $mailer->SetSubject(lang('mail_testsubject', 'siteprefs'));
                $mailer->Send();
                if ($mailer->IsError()) {
                    $errors[] = $mailer->GetErrorInfo();
                }
                $message .= lang('testmsg_success');
            } catch (Exception $e) {
                $errors[] = $e->GetMessage();
            }
        }
    }
}

if (!empty($_POST['testumask'])) {
    $testdir = TMP_CACHE_LOCATION;
    $testfile = $testdir.DIRECTORY_SEPARATOR.'dummy.tst';
    if (!is_writable($testdir)) {
        $errors[] = lang('errordirectorynotwritable');
    } else {
        @umask(octdec($global_umask));

        $fh = @fopen($testfile, 'w');
        if (!$fh) {
            $errors[] = lang('errorcantcreatefile').' ('.$testfile.')';
        } else {
            @fclose($fh);
            $filestat = stat($testfile);
            if ($filestat == false) {
                $errors[] = lang('errorcantcreatefile');
            }

            if (function_exists('posix_getpwuid')) {
                //function posix_getpwuid not available on WAMP systems
                $userinfo = @posix_getpwuid($filestat[4]);
                $username = $userinfo['name'] ?? lang('unknown');
                $permsstr = siteprefs_display_permissions(siteprefs_interpret_permissions($filestat[2]));
                $messages[] = sprintf('%s: %s<br />%s:<br />&nbsp;&nbsp;%s', lang('owner'), $username, lang('permissions'), $permsstr);
            } else {
                $errors[] = sprintf('%s: %s<br />%s:<br />&nbsp;&nbsp;%s', lang('owner'), 'N/A', lang('permissions'), 'N/A');
            }
            @unlink($testfile);
        }
    }
}

if (isset($_POST['submit'])) {
    if ($access) {
        switch ($tab) {
            case 'general':
                cms_siteprefs::set('sitename', trim($_POST['sitename']));
                cms_siteprefs::set('sitelogo', trim(filter_var($_POST['sitelogo'], FILTER_SANITIZE_URL)));
                $val = (!empty($_POST['frontendlang'])) ? trim($_POST['frontendlang']) : '';
                cms_siteprefs::set('frontendlang', $val);
                cms_siteprefs::set('metadata', $_POST['metadata']);
                $val = (!empty($_POST['logintheme'])) ? trim($_POST['logintheme']) : '';
                cms_siteprefs::set('logintheme', $val);
/*              $val = (!empty($_POST['backendwysiwyg'])) ? trim($_POST['backendwysiwyg']) : '';
                cms_siteprefs::set('backendwysiwyg', $val);
*/
                // undo some cleaning ?
                $val = str_replace('&#37;', '%', $_POST['defaultdateformat']);
                cms_siteprefs::set('defaultdateformat', $val);
                cms_siteprefs::set('thumbnail_width', filter_var($_POST['thumbnail_width'], FILTER_SANITIZE_NUMBER_INT));
                cms_siteprefs::set('thumbnail_height', filter_var($_POST['thumbnail_height'], FILTER_SANITIZE_NUMBER_INT));
                $val = (!empty($_POST['frontendwysiwyg'])) ? trim($_POST['frontendwysiwyg']) : '';
                cms_siteprefs::set('frontendwysiwyg', $val);
                $val = (!empty($_POST['search_module'])) ? trim($_POST['search_module']) : '';
                cms_siteprefs::set('searchmodule', $val);
                break;
            case 'editcontent':
                if ($pretty_urls) {
                    cms_siteprefs::set('content_autocreate_urls', (int) $_POST['content_autocreate_urls']);
                    cms_siteprefs::set('content_autocreate_flaturls', (int) $_POST['content_autocreate_flaturls']);
                    cms_siteprefs::set('content_mandatory_urls', (int) $_POST['content_mandatory_urls']);
                }
                cms_siteprefs::set('content_imagefield_path', trim($_POST['content_imagefield_path']));
                cms_siteprefs::set('content_thumbnailfield_path', $content_thumbnailfield_path = trim($_POST['content_thumbnailfield_path']));
                cms_siteprefs::set('contentimage_path', trim($_POST['contentimage_path']));
                cms_siteprefs::set('content_cssnameisblockname', (int) $_POST['content_cssnameisblockname']);
                $val = (!empty($_POST['basic_attributes'])) ?
                    implode(',', ($_POST['basic_attributes'])) : '';
                cms_siteprefs::set('basic_attributes', $val);
                $val = (!empty($_POST['disallowed_contenttypes'])) ?
                    implode(',', $_POST['disallowed_contenttypes']) : '';
                cms_siteprefs::set('disallowed_contenttypes', $val);
                break;
            case 'sitedown':
                $val = (!empty($_POST['sitedownexcludes'])) ? trim($_POST['sitedownexcludes']) : '';
                cms_siteprefs::set('sitedownexcludes', $val);
                $val= (!empty($_POST['sitedownexcludeadmins'])) ? 1:0;
                cms_siteprefs::set('sitedownexcludeadmins', $val);

                $enablesitedownmessage = !empty($_POST['enablesitedownmessage']);
                $val = (!empty($_POST['sitedownmessage'])) ? trim(strip_tags($_POST['sitedownmessage'])) : '';
                if ($val || !$enablesitedownmessage) {
                    $prevsitedown = cms_siteprefs::get('enablesitedownmessage');
                    if (!$prevsitedown && $enablesitedownmessage) {
                        audit('', 'Global Settings', 'Sitedown enabled');
                    } elseif ($prevsitedown && !$enablesitedownmessage) {
                       audit('', 'Global Settings', 'Sitedown disabled');
                    }
                    cms_siteprefs::set('enablesitedownmessage', $enablesitedownmessage);
                    cms_siteprefs::set('sitedownmessage', $val);
                } else {
                    $errors[] = lang('error_sitedownmessage');
                }
                break;
            case 'mail':
                // gather mailprefs
                $prefix = 'mailprefs_';
                foreach ($_POST as $key => $val) {
                    if (!startswith($key, $prefix)) {
                        continue;
                    }
                    $key = substr($key, strlen($prefix));
                    $mailprefs[$key] = trim($val);
                }
                // validate
                if ($mailprefs['from'] == '') {
                    $errors[] = lang('error_fromrequired');
                } elseif (!is_email($mailprefs['from'])) {
                    $errors[] = lang('error_frominvalid');
                }
                if ($mailprefs['mailer'] == 'smtp') {
                    if ($mailprefs['host'] == '') {
                        $errors[] = lang('error_hostrequired');
                    }
                    if ($mailprefs['port'] == '') {
                        $mailprefs['port'] = 25;
                    } // convenience.
                    if ($mailprefs['port'] < 1 || $mailprefs['port'] > 10240) {
                        $errors[] = lang('error_portinvalid');
                    }
                    if ($mailprefs['timeout'] == '') {
                        $mailprefs['timeout'] = 180;
                    }
                    if ($mailprefs['timeout'] < 1 || $mailprefs['timeout'] > 3600) {
                        $errors[] = lang('error_timeoutinvalid');
                    }
                    if ($mailprefs['smtpauth']) {
                        if ($mailprefs['username'] == '') {
                            $errors[] = lang('error_usernamerequired');
                        }
                        if ($mailprefs['password'] == '') {
                            $errors[] = lang('error_passwordrequired');
                        }
                    }
                }
                // save.
                if (!$errors) {
                    cms_siteprefs::set('mail_is_set', 1);
                    cms_siteprefs::set('mailprefs', serialize($mailprefs));
                }
                break;
            case 'advanced':
                cms_siteprefs::set('loginmodule', trim($_POST['login_module']));
                cms_siteprefs::set('lock_timeout', (int) $_POST['lock_timeout']);
                $val = trim($_POST['editortype']); //TODO process this
                cms_siteprefs::set('syntax_editor', $val);
                $val = trim($_POST['editortheme']);
                if ($val) {
                    $val = strtolower(strtr($val, ' ', '_'));
                }
                cms_siteprefs::set('editor_theme', $val);
//              cms_siteprefs::set('xmlmodulerepository', $_POST['xmlmodulerepository']);
                cms_siteprefs::set('checkversion', !empty($_POST['checkversion']));
                cms_siteprefs::set('global_umask', $_POST['global_umask']);
                cms_siteprefs::set('allow_browser_cache', (int) $_POST['allow_browser_cache']);
                cms_siteprefs::set('browser_cache_expiry', (int) $_POST['browser_cache_expiry']);
                cms_siteprefs::set('auto_clear_cache_age', (int) $_POST['auto_clear_cache_age']);
                cms_siteprefs::set('adminlog_lifetime', (int) $_POST['adminlog_lifetime']);
                break;
            case 'smarty':
                $val = (!empty($_POST['use_smartycompilecheck'])) ? 1:0;
                cms_siteprefs::set('use_smartycompilecheck', $val);
                $gCms->clear_cached_files();
                break;
        } //switch tab

        if (!$errors) {
            // put mention into the admin log
            audit('', 'Global Settings', 'Edited');
            $messages[] = lang('siteprefsupdated');
        }
    } else {
        $errors[] = lang('noaccessto', 'Modify Site Permissions');
    }
}

//$contentimage_useimagepath = 0;
//$sitedownmessagetemplate = '-1';

/**
 * Get old/new preferences
 */
$adminlog_lifetime = cms_siteprefs::get('adminlog_lifetime', 2592000); //3600*24*30
$allow_browser_cache = cms_siteprefs::get('allow_browser_cache', 0);
$auto_clear_cache_age = cms_siteprefs::get('auto_clear_cache_age', 0);
$backendwysiwyg = cms_siteprefs::get('backendwysiwyg', '');
$basic_attributes = cms_siteprefs::get('basic_attributes', null);
$browser_cache_expiry = cms_siteprefs::get('browser_cache_expiry', 60);
$checkversion = cms_siteprefs::get('checkversion', 1);
$content_autocreate_flaturls = cms_siteprefs::get('content_autocreate_flaturls', 0);
$content_autocreate_urls = cms_siteprefs::get('content_autocreate_urls', 0);
$content_cssnameisblockname = cms_siteprefs::get('content_cssnameisblockname', 1);
$content_imagefield_path = cms_siteprefs::get('content_imagefield_path', '');
$content_mandatory_urls = cms_siteprefs::get('content_mandatory_urls', 0);
$content_thumbnailfield_path = cms_siteprefs::get('content_thumbnailfield_path', '');
$contentimage_path = cms_siteprefs::get('contentimage_path', '');
$defaultdateformat = cms_siteprefs::get('defaultdateformat', '');
$disallowed_contenttypes = cms_siteprefs::get('disallowed_contenttypes', '');
$editortheme = cms_siteprefs::get('editor_theme', '');
$editortype = cms_siteprefs::get('syntax_editor', '');
$enablesitedownmessage = cms_siteprefs::get('enablesitedownmessage', 0);
$frontendlang = cms_siteprefs::get('frontendlang', '');
$frontendwysiwyg = cms_siteprefs::get('frontendwysiwyg', '');
$global_umask = cms_siteprefs::get('global_umask', '022');
$lock_timeout = (int)cms_siteprefs::get('lock_timeout', 60);
$login_module = cms_siteprefs::get('loginmodule', '');
$logintheme = cms_siteprefs::get('logintheme', 'default');
$mail_is_set = cms_siteprefs::get('mail_is_set', 0);
$metadata = cms_siteprefs::get('metadata', '');
$search_module = cms_siteprefs::get('searchmodule', 'Search');
$sitedownexcludeadmins = cms_siteprefs::get('sitedownexcludeadmins', '');
$sitedownexcludes = cms_siteprefs::get('sitedownexcludes', '');
$sitedownmessage = cms_siteprefs::get('sitedownmessage', '<p>Site is currently down.  Check back later.</p>');
$sitelogo = cms_siteprefs::get('sitelogo', '');
$sitename = cms_html_entity_decode(cms_siteprefs::get('sitename', 'CMSMS Website'));
$thumbnail_height = cms_siteprefs::get('thumbnail_height', 96);
$thumbnail_width = cms_siteprefs::get('thumbnail_width', 96);
$use_smartycompilecheck = cms_siteprefs::get('use_smartycompilecheck', 1);
//$xmlmodulerepository = cms_siteprefs::get('xmlmodulerepository', '');
$tmp = cms_siteprefs::get('mailprefs');
if ($tmp) {
    $mailprefs = unserialize($tmp);
} else {
    $mailprefs = [
     'mailer'=>'mail',
     'host'=>'localhost',
     'port'=>25,
     'from'=>'root@localhost.localdomain',
     'fromuser'=>'CMS Administrator',
     'sendmail'=>'/usr/sbin/sendmail',
     'smtpauth'=>0,
     'username'=>'',
     'password'=>'',
     'secure'=>'',
     'timeout'=>60,
     'charset'=>'utf-8',
    ];
}

/**
 * Build page
 */

// Error if cache folders are not writable
if (!is_writable(TMP_CACHE_LOCATION) || !is_writable(TMP_TEMPLATES_C_LOCATION)) {
    $errors[] = lang('cachenotwritable');
}

if ($errors) {
    $themeObject->RecordNotice('error', $errors);
}
if ($messages) {
    $themeObject->RecordNotice('success', $messages);
}

$submit = lang('submit');
$cancel = lang('cancel');
$editortitle = lang('text_editor_deftheme');
$nofile = json_encode(lang('nofiles'));
$badfile = json_encode(lang('errorwrongfile'));
$confirm = json_encode(lang('siteprefs_confirm'));

$out = <<<EOS
<script type="text/javascript">
//<![CDATA[
function on_mailer() {
 var v = $('#mailer').val();
 if( v == 'mail' ) {
  $('#set_smtp').find('input,select').attr('disabled','disabled');
  $('#set_sendmail').find('input,select').attr('disabled','disabled');
 } else if(v == 'smtp') {
  $('#set_smtp').find('input,select').removeAttr('disabled');
  $('#set_sendmail').find('input,select').attr('disabled','disabled');
 } else if(v == 'sendmail') {
  $('#set_smtp').find('input,select').attr('disabled','disabled');
  $('#set_sendmail').find('input,select').removeAttr('disabled');
 }
}
$(document).ready(function() {
 $('#mailertest').on('click', function(e) {
  cms_dialog($('#testpopup'),{
   modal: true,
   width: 'auto'
  });
  return false;
 });
 $('#testcancel').on('click', function(e) {
  cms_dialog($('#testpopup'),'close');
  return false;
 });
 $('#testsend').on('click', function(e) {
  cms_dialog($('#testpopup'),'close');
  $(this).closest('form').submit();
 });
 var b = $('#importbtn');
 if(b.length > 0) {
  b.on('click', function() {
   cms_dialog($('#importdlg'), {
    modal: true,
    buttons: {
     {$submit}: function() {
      var file = $('#xml_upload').val();
      if(file.length === 0) {
       cms_alert($nofile);
      } else {
       var ext = file.split('.').pop().toLowerCase();
       if(ext !== 'xml') {
        cms_alert($badfile);
       } else {
        $(this).dialog('close');
        $('#importform').submit();
       }
      }
     },
     {$cancel}: function() {
      $(this).dialog('close');
     }
    },
    width: 'auto'
   });
  });
  $('#deletebtn').on('click', function() {
   cms_dialog($('#deletedlg'), {
    modal: true,
    buttons: {
     {$submit}: function() {
      $(this).dialog('close');
      $('#deleteform').submit();
     },
     {$cancel}: function() {
      $(this).dialog('close');
     }
    },
    width: 'auto'
   });
  });
 }
 b = $('#exportbtn');
 if(b.length > 0) {
  b.on('click', function() {
   cms_dialog($('#exportdlg'), {
    modal: true,
    width: 'auto',
    buttons: {
     {$submit}: function() {
      $(this).dialog('close');
      $('#exportform').submit();
     },
     {$cancel}: function() {
      $(this).dialog('close');
     }
    }
   });
  });
 }
 $('#theme_help .cms_helpicon').on('click', function() {
  var key = $('input[name=editortype]:checked').attr('data-themehelp-key');
  if (key) {
   var self = this;
   $.get(cms_data.ajax_help_url, {
    key: key
   }, function(text) {
    var data = {
     cmshelpTitle: '$editortitle'
    };
    cms_help(self, data, text);
   });
  }
 });
 $('#mailer').change(function() {
  on_mailer();
 });
 on_mailer();
 $('[name=submit]').on('click', function(ev) {
  ev.preventDefault();
  cms_confirm_btnclick(this, $confirm);
  return false;
 });
});
//]]>
</script>

EOS;
$themeObject->add_footertext($out);

$modops = ModuleOperations::get_instance();
$smarty = Smarty::get_instance();

$tmp = [-1 => lang('none')];
$modules = $modops->get_modules_with_capability('search');
if ($modules) {
    for ($i = 0, $n = count($modules); $i < $n; $i++) {
        $tmp[$modules[$i]] = $modules[$i];
    }
    $smarty->assign('search_module', $search_module);
} else {
    $smarty->assign('search_module', lang('none'));
}
$smarty->assign('search_modules', $tmp);

$tmp = ['' => lang('theme')];
$modules = $modops->get_modules_with_capability('adminlogin');
if ($modules) {
    for ($i = 0, $n = count($modules); $i < $n; $i++) {
        if ($modules[$i] == 'CoreAdminLogin') {
            $tmp[$modules[$i]] = lang('default');
        } else {
            $tmp[$modules[$i]] = $modules[$i];
        }
    }
}
$smarty->assign('login_module', $login_module)
  ->assign('login_modules', $tmp);

$maileritems = [];
$maileritems['mail'] = 'mail';
$maileritems['sendmail'] = 'sendmail';
$maileritems['smtp'] = 'smtp';
$smarty->assign('maileritems', $maileritems);
$opts = [];
$opts[''] = lang('none');
$opts['ssl'] = 'SSL';
$opts['tls'] = 'TLS';
$smarty->assign('secure_opts', $opts)
  ->assign('mail_is_set', $mail_is_set)
  ->assign('mailprefs', $mailprefs)

  ->assign('languages', get_language_list())
  ->assign('tab', $tab)
  ->assign('pretty_urls', $pretty_urls);

// need a list of wysiwyg modules.

$tmp = module_meta::get_instance()->module_list_by_capability('wysiwyg');
$n = count($tmp);
$tmp2 = [-1 => lang('none')];
for ($i = 0; $i < $n; $i++) {
    $tmp2[$tmp[$i]] = $tmp[$i];
}
$smarty->assign('wysiwyg', $tmp2);

$tmp = CmsAdminThemeBase::GetAvailableThemes();
if ($tmp) {
    $smarty->assign('themes', $tmp)
      ->assign('logintheme', cms_siteprefs::get('logintheme', reset($tmp)))
      ->assign('exptheme', !empty($config['developer_mode']));
} else {
    $smarty->assign('themes', null)
      ->assign('logintheme', null);
}
$smarty->assign('modtheme', check_permission($userid, 'Modify Site Preferences'));

# advanced/WYSIWYG editors
$editors = [];
$tmp = module_meta::get_instance()->module_list_by_capability(CmsCoreCapabilities::SYNTAX_MODULE);
if( $tmp) {
    for ($i = 0, $n = count($tmp); $i < $n; ++$i) {
		$ob = cms_utils::get_module($tmp[$i]);
		if ($ob instanceof SyntaxEditor) {
			$all = $ob->ListEditors(true);
			foreach ($all as $label=>$val) {
				$one = new stdClass();
				$one->value = $val;
				$one->label = $label;
				list($modname, $edname) = explode('::', $val);
				list($realm, $key) = $ob->GetMainHelpKey($edname);
				if (!$realm) $realm = $modname;
				$one->mainkey = $realm.'__'.$key;
				list($realm, $key) = $ob->GetThemeHelpKey($edname);
				if (!$realm) $realm = $modname;
				$one->themekey = $realm.'__'.$key;
				if ($one->value == $editortype) $one->checked = true;
				$editors[] = $one;
			}
		} elseif ($tmp[$i] != 'MicroTiny') { //that's only for html :(
			$one = new stdClass();
			$one->value = $tmp[$i].'::'.$tmp[$i];
			$one->label = $ob->GetName();
			$one->mainkey = '';
			$one->themekey = '';
			if ($tmp[$i] == $editortype || $one->value == $editortype) $one->checked = true;
			$editors[] = $one;
		}
	}
	usort($editors, function ($a,$b) { return strcmp($a->label, $b->label); });

	$one = new stdClass();
	$one->value = '';
	$one->label = lang('none');
	$one->mainkey = '';
	$one->themekey = '';
	if (!$editortype) $one->checked = true;
	$editors[] = $one;
}
$smarty->assign('editors', $editors);

$theme = cms_utils::get_theme_object();
$smarty->assign('helpicon', $theme->DisplayImage('icons/system/help.png', 'help','','','cms_helpicon'))
  ->assign('editortheme', $editortheme)

  ->assign('adminlog_lifetime', $adminlog_lifetime)
  ->assign('allow_browser_cache', $allow_browser_cache)
  ->assign('auto_clear_cache_age', $auto_clear_cache_age)
  ->assign('backendwysiwyg', $backendwysiwyg)
  ->assign('basic_attributes', explode(',', $basic_attributes))
  ->assign('browser_cache_expiry', $browser_cache_expiry)
  ->assign('checkversion', $checkversion)
  ->assign('content_autocreate_flaturls', $content_autocreate_flaturls)
  ->assign('content_autocreate_urls', $content_autocreate_urls)
  ->assign('content_cssnameisblockname', $content_cssnameisblockname)
  ->assign('content_imagefield_path', $content_imagefield_path)
  ->assign('content_mandatory_urls', $content_mandatory_urls)
  ->assign('content_thumbnailfield_path', $content_thumbnailfield_path)
  ->assign('contentimage_path', $contentimage_path)
  ->assign('defaultdateformat', $defaultdateformat)
  ->assign('disallowed_contenttypes', explode(',', $disallowed_contenttypes))
  ->assign('enablesitedownmessage', $enablesitedownmessage)
  ->assign('frontendlang', $frontendlang)
  ->assign('frontendwysiwyg', $frontendwysiwyg)
  ->assign('global_umask', $global_umask)
  ->assign('lock_timeout', $lock_timeout)
  ->assign('login_module', $login_module)
  ->assign('metadata', $metadata)
  ->assign('search_module', $search_module)
  ->assign('sitedownexcludeadmins', $sitedownexcludeadmins)
  ->assign('sitedownexcludes', $sitedownexcludes)
  ->assign('sitelogo', $sitelogo)
  ->assign('sitename', $sitename)
  ->assign('testresults', lang('untested'))

  ->assign('textarea_sitedownmessage', $obj = FormUtils::create_textarea([
  'enablewysiwyg' => 1,
  'htmlid' => 'sitedownmessage',
  'name' => 'sitedownmessage',
  'class' => 'pagesmalltextarea',
  'value' => $sitedownmessage,
]))

  ->assign('thumbnail_height', $thumbnail_height)
  ->assign('thumbnail_width', $thumbnail_width)
  ->assign('use_smartycompilecheck', $use_smartycompilecheck);

$tmp = [
  60*60*24=>lang('adminlog_1day'),
  60*60*24*7=>lang('adminlog_1week'),
  60*60*24*14=>lang('adminlog_2weeks'),
  60*60*24*31=>lang('adminlog_1month'),
  60*60*24*31*3=>lang('adminlog_3months'),
  60*60*24*30*6=>lang('adminlog_6months'),
  -1=>lang('adminlog_manual'),
];
$smarty->assign('adminlog_options', $tmp);

$all_attributes = null;

$content_obj = new Content(); // should this be the default type?
$list = $content_obj->GetProperties();
if ($list) {
    // pre-remove some items.
    $all_attributes = [];
    for ($i = 0, $n = count($list); $i < $n; $i++) {
        $obj = $list[$i];
        if ($obj->tab == $content_obj::TAB_PERMS) {
            continue;
        }
        if (!isset($all_attributes[$obj->tab])) {
            $all_attributes[$obj->tab] = ['label'=>lang($obj->tab),'value'=>[]];
        }
        $all_attributes[$obj->tab]['value'][] = ['value'=>$obj->name,'label'=>lang($obj->name)];
    }
}
$txt = FormUtils::create_option($all_attributes);

$smarty->assign('all_attributes', $all_attributes)
  ->assign('smarty_cacheoptions', ['always'=>lang('always'),'never'=>lang('never'),'moduledecides'=>lang('moduledecides')])
  ->assign('smarty_cacheoptions2', ['always'=>lang('always'),'never'=>lang('never')]);

$contentops = cmsms()->GetContentOperations();
$all_contenttypes = $contentops->ListContentTypes(false, false);
$smarty->assign('all_contenttypes', $all_contenttypes)

  ->assign('yesno', [0=>lang('no'),1=>lang('yes')])
  ->assign('titlemenu', [lang('menutext'),lang('title')])

  ->assign('backurl', $themeObject->backUrl());
$selfurl = basename(__FILE__);
$smarty->assign('selfurl', $selfurl)
  ->assign('urlext', $urlext);

include_once 'header.php';
$smarty->display('siteprefs.tpl');
include_once 'footer.php';
