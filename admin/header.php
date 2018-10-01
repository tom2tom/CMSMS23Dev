<?php
#Shared stage of admin-page-top display (used after action is run)
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

use CMSMS\FormUtils;
use CMSMS\HookManager;
use CMSMS\internal\Smarty;

// variables for general use
if (empty($CMS_LOGIN_PAGE)) {
	$userid = get_userid(); //also checks login status
}
if (!isset($themeObject)) {
	$themeObject = cms_utils::get_theme_object();
}
if (!isset($smarty)) {
	$smarty = Smarty::get_instance();
}
$config = cms_config::get_instance();

$aout = HookManager::do_hook_accumulate('AdminHeaderSetup');
if ($aout) {
	$out = '';
	foreach($aout as $bundle) {
		if ($bundle[0]) {
			//NOTE downstream must ensure var keys and values are formatted for js
			foreach($bundle[0] as $key => $value) {
				$out .= "cms_data.{$key} = {$value};\n";
			}
		}

		if ($bundle[1]) {
			foreach($bundle[1] as $list) {
				$one = is_array($list) ? implode("\n",$list) : $list;
				$themeObject->add_headtext($one."\n");
			}
		}
	}

	if ($out) {
		$themeObject->add_headtext(<<<EOT
<script type="text/javascript">
//<![CDATA[

EOT
		);
		$themeObject->add_headtext($out);
		$themeObject->add_headtext(<<<EOT
//]]>
</script>

EOT
		);
	}
}

if (isset($modinst)) {
	if ($modinst->HasAdmin()) {
		$txt = $modinst->AdminStyle();
		if ($txt) {
			$themeObject->add_headtext($txt);
		}
	}
	$txt = $modinst->GetHeaderHTML($action);
	if ($txt) {
		$themeObject->add_headtext($txt);
	}
}

// initialize required WYSIWYG modules
// (must be after action/content generation, which might create textarea(s))
$list = FormUtils::get_requested_wysiwyg_modules();
if ($list) {
	foreach ($list as $module_name => $info) {
		$obj = cms_utils::get_module($module_name);
		if (!is_object($obj)) {
			audit('','Core','WYSIWYG module '.$module_name.' requested, but could not be instantiated');
			continue;
		}

		$cssnames = [];
		foreach ($info as $rec) {
			if (!($rec['stylesheet'] == '' || $rec['stylesheet'] == FormUtils::NONE)) {
				$cssnames[] = $rec['stylesheet'];
			}
		}
		$cssnames = array_unique($cssnames);
		if ($cssnames) {
			$css = CmsLayoutStylesheet::load_bulk($cssnames);
			// adjust the cssnames array to only contain the list of the stylesheets we actually found.
			if ($css) {
				$tmpnames = [];
				foreach ($css as $stylesheet) {
					$name = $stylesheet->get_name();
					if (!in_array($name,$tmpnames)) $tmpnames[] = $name;
				}
				$cssnames = $tmpnames;
			} else {
				$cssnames = [];
			}
		}

		// initialize each 'specialized' textarea
		$need_generic = false;
		foreach ($info as $rec) {
			$selector = $rec['id'];
			$cssname = $rec['stylesheet'];

			if ($cssname == FormUtils::NONE) $cssname = null;
			if (!$cssname || !is_array($cssnames) || !in_array($cssname,$cssnames) || $selector == FormUtils::NONE) {
				$need_generic = true;
				continue;
			}

			$selector = 'textarea#'.$selector;
			try {
				$out = $obj->WYSIWYGGenerateHeader($selector,$cssname);
				$themeObject->add_headtext($out);
			} catch (Exception $e) {}
		}
		// do we need a generic textarea ?
		if ($need_generic) {
			try {
				$out = $obj->WYSIWYGGenerateHeader();
				$themeObject->add_headtext($out);
			} catch (Exception $e) {}
		}
	}
}

// initialize required syntax hilighter modules
$list = FormUtils::get_requested_syntax_modules();
if ($list) {
	foreach ($list as $one) {
		$obj = cms_utils::get_module($one);
		if (is_object($obj)) {
			try {
				$out = $obj->SyntaxGenerateHeader();
				$themeObject->add_headtext($out);
			} catch (Exception $e) {}
		}
	}
}

cms_admin_sendheaders(); //TODO is this $CMS_JOB_TYPE-related ?

if (isset($config['show_performance_info'])) {
	$starttime = microtime();
}
if (!isset($USE_OUTPUT_BUFFERING) || $USE_OUTPUT_BUFFERING) {
	@ob_start();
}

if (!isset($USE_THEME) || $USE_THEME) {
	if (empty($CMS_LOGIN_PAGE)) {
		$smarty->assign('secureparam', CMS_SECURE_PARAM_NAME . '=' . $_SESSION[CMS_USER_KEY]);

		// Display notification stuff from modules
		// should be controlled by preferences or something
		$ignoredmodules = explode(',',cms_userprefs::get_for_user($userid,'ignoredmodules'));
		if( cms_siteprefs::get('enablenotifications',1) && cms_userprefs::get_for_user($userid,'enablenotifications',1) ) {
			// Display a warning sitedownwarning
			$sitedown_file = TMP_CACHE_LOCATION . DIRECTORY_SEPARATOR. 'SITEDOWN';
			$sitedown_message = lang('sitedownwarning', $sitedown_file);
			if (file_exists($sitedown_file)) $themeObject->AddNotification(1,'Core',$sitedown_message);
		}
	}

	$themeObject->do_header();
//} else {
//    echo '<!-- admin theme disabled -->';
}
