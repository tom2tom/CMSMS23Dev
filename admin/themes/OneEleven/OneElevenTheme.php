<?php
# OneEleven- an Admin Console theme for CMS Made Simple
# Copyright (C) 2012 Goran Ilic <ja@ich-mach-das.at>
# Copyright (C) 2012-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
# This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.

use CMSMS\AdminAlerts\Alert;
use CMSMS\AdminUtils;
use CMSMS\ModuleOperations;
use CMSMS\ScriptManager;
use CMSMS\UserOperations;

class OneElevenTheme extends CmsAdminThemeBase
{
	/**
	 * For theme exporting/importing
	 * @ignore
	 */
	const THEME_VERSION = '1.1';

	/**
	 * @ignore
	 */
	private $_havetree = null;

	// 2.3+ will access these via parent-class
	protected $_errors = array();
	protected $_messages = array();

	/**
	 * Determine whether this is running on CMSMS 2.3+
	 */
	protected function currentversion() : bool
	{
		static $flag = null;
		if ($flag === null) {
			$flag = method_exists($this, 'RecordNotice');
		}
		return $flag;
	}

	/**
	 * Hook function to nominate runtime resources, which will be included
	 * in the header of each displayed admin page
	 *
	 * NOTE this must be replicated somehow for pre 2.3 TODO
	 * @since 2.3
	 * @param array $vars assoc. array of js-variable names and their values
	 * @param array $add_list array of strings representing includables
	 * @return array 2-members, which are the supplied params after any updates
	 */
	public function AdminHeaderSetup(array $vars, array $add_list) : array
	{
		list($vars, $add_list) = parent::AdminHeaderSetup($vars, $add_list);

		$config = cmsms()->GetConfig();
		$admin_path = $config['admin_path'];
//		$admin_url = $config['admin_url'];
		$rel = substr(__DIR__, strlen($admin_path) + 1);
		$rel_url = strtr($rel,DIRECTORY_SEPARATOR,'/');

		$lang = CmsNlsOperations::get_current_language();
		$info = CmsNlsOperations::get_language_info($lang);
		$fn = 'style';
		if ($info->direction() == 'rtl') {
			if (file_exists(__DIR__.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.$fn.'-rtl.css')) {
				$fn .= '-rtl';
			}
		}
		$incs = cms_installed_jquery(true, true, true, true);
		$url = AdminUtils::path_to_url($incs['jquicss']);
		$out = <<<EOS
<link rel="stylesheet" type="text/css" href="{$url}" />
<link rel="stylesheet" type="text/css" href="{$rel_url}/css/{$fn}.css" />

EOS;
		if (file_exists(__DIR__.DIRECTORY_SEPARATOR.'extcss'.DIRECTORY_SEPARATOR.$fn.'.css')) {
			$out .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$rel_url}/extcss/{$fn}.css" />

EOS;
		}
		$tpl = '<script type="text/javascript" src="%s"></script>'."\n";

		$sm = new ScriptManager();
		$sm->queue_file($incs['jqcore'], 1);
		$sm->queue_file($incs['jqmigrate'], 1); //in due course, omit this ?
		$sm->queue_file($incs['jqui'], 1);
		$p = cms_join_path($config['root_path'],'lib','js','');
		$sm->queue_file($p.'jquery.cms_admin.js', 2); //OR .min for production
		$fn = $sm->render_scripts('', false, false);
		$url = AdminUtils::path_to_url(TMP_CACHE_LOCATION).'/'.$fn;
		$out .= sprintf($tpl,$url);

		global $CMS_LOGIN_PAGE;
		if (isset($_SESSION[CMS_USER_KEY]) && !isset($CMS_LOGIN_PAGE)) {
			$sm->reset();
			require_once $admin_path.DIRECTORY_SEPARATOR.'jsruntime.php';
			$sm->queue_string($_out_);
			$fn = $sm->render_scripts('', false, false);
			$url = AdminUtils::path_to_url(TMP_CACHE_LOCATION).'/'.$fn;
			$out .= sprintf($tpl,$url);
		}

		$sm->reset();
		$sm->queue_file($p.'jquery.ui.touch-punch.min.js', 1);
		$sm->queue_file($p.'jquery.toast.js', 1); //OR .min for production
		$p = __DIR__.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR;
		$sm->queue_file($p.'standard.js', 3); //OR .min for production
		$fn = $sm->render_scripts();
		$url = AdminUtils::path_to_url(TMP_CACHE_LOCATION).'/'.$fn;
		$out .= sprintf($tpl,$url);

		$add_list[] = $out;
//		$vars[] = anything needed ?;

		return [$vars, $add_list];
	}

	public function ShowErrors($errors, $get_var = '')
	{
		if ($this->currentversion()) {
			$this->RecordNotice('error', $errors, '', false, $get_var);
		} else {

		// cache errors for use in the template.
		if ($get_var != '' && isset($_GET[$get_var]) && !empty($_GET[$get_var])) {
			if (is_array($_GET[$get_var])) {
				foreach ($_GET[$get_var] as $one) {
					$this->_errors[] = lang(cleanValue($one));
				}
			} else {
				$this->_errors[] = lang(cleanValue($_GET[$get_var]));
			}
		} elseif (is_array($errors)) {
			foreach ($errors as $one) {
				$this->_errors[] = $one;
			}
		} elseif (is_string($errors)) {
			$this->_errors[] = $errors;
		}
		return '<!-- OneEleven::ShowErrors() called -->';

		} //pre 2.3
	}

	public function ShowMessage($message, $get_var = '')
	{
		if ($this->currentversion()) {
			$this->RecordNotice('success', $message, '', false, $get_var);
		} else {

		// cache message for use in the template.
		if ($get_var != '' && isset($_GET[$get_var]) && !empty($_GET[$get_var])) {
			if (is_array($_GET[$get_var])) {
				foreach ($_GET[$get_var] as $one) {
					$this->_messages[] = lang(cleanValue($one));
				}
			} else {
				$this->_messages[] = lang(cleanValue($_GET[$get_var]));
			}
		} elseif (is_array($message)) {
			foreach ($message as $one) {
				$this->_messages[] = $one;
			}
		} elseif (is_string($message)) {
			$this->_messages[] = $message;
		}

		} // pre 2.3
	}

	public function ShowHeader($title_name, $extra_lang_params = array(), $link_text = '', $module_help_type = FALSE)
	{
		if ($this->currentversion()) {
			parent::ShowHeader($title_name, $extra_lang_params, $link_text, $module_help_type);
		} else {

		if ($title_name) $this->set_value('pagetitle', $title_name);
		if (is_array($extra_lang_params) && count($extra_lang_params)) $this->set_value('extra_lang_params', $extra_lang_params);
		$this->set_value('module_help_type', $module_help_type);

		$config = cms_config::get_instance();
		if ($module_help_type) {
			// help for a module.
			$module = '';
			if (isset($_REQUEST['module'])) {
				$module = $_REQUEST['module'];
			} elseif (isset($_REQUEST['mact'])) {
				$tmp = explode(',', $_REQUEST['mact']);
				$module = $tmp[0];
			}

			// get the image url.
			$icon = "modules/{$module}/images/icon.gif";
			$path = cms_join_path($config['root_path'], $icon);
			if (file_exists($path)) {
				$url = $config->smart_root_url() . '/' . $icon;
				$this->set_value('module_icon_url', $url);
			}

			// set the module help url (this should be supplied TO the theme)
			$module_help_url = $this->get_module_help_url();
			$this->set_value('module_help_url', $module_help_url);
		}

		$bc = $this->get_breadcrumbs();
		if ($bc) {
			for ($i = 0; $i < count($bc); $i++) {
				$rec = $bc[$i];
				$title = $rec['title'];
				if ($module_help_type && $i + 1 == count($bc)) {
					$module_name = '';
					if (!empty($_GET['module'])) {
						$module_name = trim($_GET['module']);
					} else {
						$tmp = explode(',', $_REQUEST['mact']);
						$module_name = $tmp[0];
					}
					$orig_module_name = $module_name;
					$module_name = preg_replace('/([A-Z])/', "_$1", $module_name);
					$module_name = preg_replace('/_([A-Z])_/', "$1", $module_name);
					if ($module_name[0] == '_')
						$module_name = substr($module_name, 1);
				} else {
					if (($p = strrchr($title, ':')) !== FALSE) {
						$title = substr($title, 0, $p);
					}
					// find the key of the item with this title.
					$title_key = $this->find_menuitem_by_title($title);
				}
			} // for-loop
		}

		} // pre-2.3
	}

	public function do_header()
	{
	}

	public function do_footer()
	{
	}

	public function do_toppage($section_name)
	{
		$flag = $this->currentversion();

		$smarty = cmsms()->GetSmarty();
		if ($section_name) {
			$smarty->assign('section_name', $section_name);
			if ($flag) {
				$nodes = $this->get_navigation_tree($section_name, 0);
				$smarty->assign('pagetitle', $this->title);
			} else {
				$nodes = $this->get_navigation_tree($section_name, -1, FALSE);
				$smarty->assign('pagetitle', lang($section_name)); //CHECKME
			}
		} else {
			if ($flag) {
				$nodes = $this->get_navigation_tree(null, 3, 'root:view:dashboard');
			} else {
				$nodes = $this->get_navigation_tree(-1, 2, FALSE);
			}
		}
//		$this->_havetree = $nodes; //block further tree-data changes
		$smarty->assign('nodes', $nodes);

		$config = cmsms()->GetConfig();
		$smarty->assign('admin_url', $config['admin_url']);
		$smarty->assign('theme', $this);

		// is the website set down for maintenance?
		if (cms_siteprefs::get('enablesitedownmessage')) {
			$smarty->assign('is_sitedown', 1);
		}

		$otd = $smarty->template_dir;
		$smarty->template_dir = __DIR__.DIRECTORY_SEPARATOR.'templates';
		$smarty->display('topcontent.tpl');
		$smarty->template_dir = $otd;
	}

	/**
	 * Get URL's for installed jquery, jquery-ui & related css
	 * Only for pre-2.3 operation
	 * @return 3-member array
     */
	protected function find_installed_jq()
	{
		$config = cmsms()->GetConfig();

		$fp = cms_join_path(CMS_ROOT_PATH,'lib','jquery','css','*','jquery-ui*.css');
		$m = glob($fp, GLOB_NOSORT|GLOB_NOESCAPE);
		//find highest version
		$best = '0';
		$use = false;
		foreach ($m as $fn) {
			$file = basename($fn);
			preg_match('~(\d[\d\.]+\d)~', $file, $matches);
			if (version_compare($best, $matches[1]) < 0) {
				$best = $matches[1];
				$use = $file;
			}
		}
		$p = basename(dirname($m[0]));
		$jqcss = $config['root_url']. '/lib/jquery/ccs/'.$p.'/'.$use;

		$fp = cms_join_path(CMS_ROOT_PATH,'lib','jquery','js');
		$allfiles = scandir($fp);
		$m = preg_grep('~^jquery\-ui\-\d[\d\.]+\d([\.\-]custom)?(\.min)?\.js$~', $allfiles);
		//find highest version
		$best = '0';
		$use = reset($m);
		foreach ($m as $file) {
			preg_match('~(\d[\d\.]+\d)~', $file, $matches);
			if (version_compare($best, $matches[1]) < 0) {
				$best = $matches[1];
				$use = $file;
			}
		}
		$jqui = $config['root_url']. '/lib/jquery/js/'.$use;

		$m = preg_grep('~^jquery\-\d[\d\.]+\d(\.min)?\.js$~', $allfiles);
		//find highest version
		$best = '0';
		$use = reset($m);
		foreach ($m as $file) {
			preg_match('~(\d[\d\.]+\d)~', $file, $matches);
			if (version_compare($best, $matches[1]) < 0) {
				$best = $matches[1];
				$use = $file;
			}
		}
		$jqcore = $config['root_url']. '/lib/jquery/js/'.$use;

		return array($jqcss, $jqui, $jqcore);
	}

	/**
	 * @param  $params Array of variables for smarty (CMSMS pre-2.3 only)
	 */
	public function do_login($params = null)
	{
		$gCms = cmsms();

		if ($this->currentversion()) {
			$auth_module = cms_siteprefs::get('loginmodule', 'CoreAdminLogin');
			$modinst = ModuleOperations::get_instance()->get_module_instance($auth_module, '', true);
			if ($modinst) {
				$data = $modinst->StageLogin(); //returns only if further processing is needed
			} else {
				die('System error');
			}

			$smarty = $gCms->GetSmarty();
			$smarty->assign($data);

			//extra shared parameters for the form
			$config = $gCms->GetConfig(); //also need by the inclusion
			$fp = cms_join_path($config['admin_path'], 'themes', 'assets', 'function.extraparms.php');
			require_once $fp;
			$smarty->assign($tplvars);

			//extra theme-specific setup
			$fp = cms_join_path(__DIR__, 'function.extraparms.php');
			if (is_file($fp)) {
				require_once $fp;
				if (!empty($tplvars)) {
					$smarty->assign($tplvars);
				}
			}

//TODO	ensure $smarty->assign('lang_code', cms_siteprefs::get('frontendlang'));

			// scripts: jquery, jquery-ui
			$incs = cms_installed_jquery();
			$url = AdminUtils::path_to_url($incs['jquicss']);
			$dir = ''; //TODO or '-rtl'
			$out = <<<EOS
<link rel="stylesheet" href="$url" />
<link rel="stylesheet" href="themes/OneEleven/css/style{$dir}.css" />

EOS;
			$tpl = '<script type="text/javascript" src="%s"></script>'."\n";
			$url = AdminUtils::path_to_url($incs['jqcore']);
			$out .= sprintf($tpl,$url);
			$url = AdminUtils::path_to_url($incs['jqui']);
			$out .= sprintf($tpl,$url);
		} else {
			$smarty = $gCms->GetSmarty();
			if (!empty($params)) {
				$smarty->assign($params);
			}

			$config = $gCms->GetConfig();
			//extra setup/parameters for the form
			$fp = cms_join_path(__DIR__, 'function.login.php');
			require $fp;
			if (!empty($tplvars)) {
				$smarty->assign($tplvars);
			}

			list($jqcss, $jqui, $jqcore) = $this->find_installed_jq();
			$out = <<<EOS
<link rel="stylesheet" href="$jqcss" />
<link rel="stylesheet" href="themes/OneEleven/css/style{if $lang_dir=='rtl'}-rtl{/if}.css" />
<link rel="stylesheet" href="loginstyle.php" />
<script type="text/javascript" src="$jqcore"></script>
<script type="text/javascript" src="$jqui"></script>

EOS;
		} // pre 2.3

		$smarty->assign('header_includes', $out); //NOT into bottom (to avoid UI-flash)
		$smarty->template_dir = __DIR__ . DIRECTORY_SEPARATOR . 'templates';
		$smarty->display('login.tpl');
	}

	public function postprocess($html)
	{
		$flag = $this->currentversion();

		$smarty = cmsms()->GetSmarty();
		$uid = get_userid(false);

		// prefer cached parameters, if any
		// module name
		$module_name = $this->get_value('module_name');
		if (!$module_name && isset($_REQUEST['mact'])) {
			$module_name = explode(',', $_REQUEST['mact'])[0];
		}
		$smarty->assign('module_name', $module_name);

		$module_help_type = $this->get_value('module_help_type');
		// module_help_url
		if ($module_name && ($module_help_type || $module_help_type === null) &&
			!cms_userprefs::get_for_user($uid,'hide_help_links', 0)) {
			if (($module_help_url = $this->get_value('module_help_url'))) {
				$smarty->assign('module_help_url', $module_help_url);
			}
		}

		// page title
		$alias = $title = $this->get_value('pagetitle');
		$subtitle = '';
		if ($title) {
			if (!$module_help_type) {
				// if not doing module help, maybe translate the string
				if (CmsLangOperations::lang_key_exists('admin', $title)) {
					$extra = $this->get_value('extra_lang_params');
					if (!$extra) {
						$extra = array();
					}
					$title = lang($title, $extra);
				}
			}
		} elseif ($this->title) {
			$title = $this->title; // active-menu-item title
			$subtitle = $this->subtitle;
		} elseif ($module_name) {
			$modinst = cms_utils::get_module($module_name);
			$title = $modinst->GetFriendlyName();
			$subtitle = $modinst->GetAdminDescription();
		} else {
			// no title, get one from the breadcrumbs
			$bc = $this->get_breadcrumbs();
			if (is_array($bc) && count($bc)) {
				$title = $bc[count($bc) - 1]['title'];
			}
		}
		if (!$title) $title = '';

		// page title and alias
		$smarty->assign('pagetitle', $title);
		$smarty->assign('subtitle', $subtitle);
		$smarty->assign('pagealias', munge_string_to_url($alias));

		// icon
		if ($module_name && ($icon_url = $this->get_value('module_icon_url'))) {
			$tag = '<img src="'.$icon_url.'" alt="'.$module_name.'" class="module-icon" />';
		} elseif ($module_name && $title) {
			$tag = AdminUtils::get_module_icon($module_name, ['alt'=>$module_name, 'class'=>'module-icon']);
		} elseif (($icon_url = $this->get_value('page_icon_url'))) {
			$tag = '<img src="'.$icon_url.'" alt="'.basename($icon_url).'" />';
		} else {
			$name = $this->get_active('name');
			$tag = ($name) ? $this->DisplayImage("icons/topfiles/$name.png", $name) : '';
		}
		$smarty->assign('pageicon', $tag);

		// site logo
		$sitelogo = cms_siteprefs::get('sitelogo');
		if ($sitelogo) {
			if (!preg_match('~^\w*:?//~', $sitelogo)) {
				$sitelogo = CMS_ROOT_URL.'/'.$sitelogo;
			}
			$smarty->assign('sitelogo', $sitelogo);
		}

		// preferences UI
		if (check_permission($uid,'Manage My Settings')) {
		  $smarty->assign('myaccount',1);
		}

		// bookmarks UI
		if (cms_userprefs::get_for_user($uid, 'bookmarks') && check_permission($uid, 'Manage My Bookmarks')) {
			$marks = $this->get_bookmarks();
			$smarty->assign('marks', $marks);
		}

		// other variables
		$config = cmsms()->GetConfig();
		$smarty->assign('admin_url', $config['admin_url']);
		$smarty->assign('content', str_replace('</body></html>', '', $html));
		$smarty->assign('theme', $this);
		$smarty->assign('secureparam', CMS_SECURE_PARAM_NAME . '=' . $_SESSION[CMS_USER_KEY]);
		$userops = UserOperations::get_instance();
		$user = $userops->LoadUserByID($uid);
		$smarty->assign('username', $user->username);
		// user-selected language
		$lang = cms_userprefs::get_for_user($uid, 'default_cms_language');
		if (!$lang) $lang = cms_siteprefs::get('frontendlang');
		$smarty->assign('lang_code', $lang);
		// language direction
		$lang = CmsNlsOperations::get_current_language();
		$info = CmsNlsOperations::get_language_info($lang);
		$smarty->assign('lang_dir', $info->direction());

		if ($flag) {
			$smarty->assign('header_includes', $this->get_headtext());
			$smarty->assign('bottom_includes', $this->get_footertext());
		} else {
			if (is_array($this->_errors) && count($this->_errors))
				$smarty->assign('errors', $this->_errors);
			if (is_array($this->_messages) && count($this->_messages))
				$smarty->assign('messages', $this->_messages);
		}

		// is the website set down for maintenance?
		if (cms_siteprefs::get('enablesitedownmessage')) {
			$smarty->assign('is_sitedown', 1);
		}

		$otd = $smarty->template_dir;
		$smarty->template_dir = __DIR__ . '/templates';
		$_contents = $smarty->fetch('pagetemplate.tpl');
		$smarty->template_dir = $otd;
		return $_contents;
	}

	public function get_my_alerts()
	{
		//TODO namespace for pre-2.3
		return Alert::load_my_alerts();
	}
}