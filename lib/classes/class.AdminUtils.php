<?php
#A class of convenience functions for admin console requests
#Copyright (C) 2010-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
#Thanks to Robert Campbell and all other contributors from the CMSMS Development Team.
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

namespace CMSMS;

use cms_http_request;
use cms_siteprefs;
use cms_utils;
use CmsApp;
use CMSMS\internal\Smarty;
use FilesystemIterator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use const CMS_DEFAULT_VERSIONCHECK_URL;
use const CMS_ROOT_URL;
use const CMS_SECURE_PARAM_NAME;
use const CMS_USER_KEY;
use const CMS_VERSION;
use const PUBLIC_CACHE_LOCATION;
use const TMP_CACHE_LOCATION;
use const TMP_TEMPLATES_C_LOCATION;
use function cms_join_path;
use function cms_module_places;
use function cms_path_to_url;
use function endswith;
use function startswith;

//this is also used during content installation i.e. STATE_INSTALL_PAGE, or nothing
//if( !CmsApp::get_instance()->test_state(CmsApp::STATE_ADMIN_PAGE) )
//    throw new CmsLogicException('Attempt to use CMSMS\AdminUtils class from an invalid request');

/**
 * A class of static utility methods for admin requests.
 *
 * @final
 * @package CMS
 * @license GPL
 * @author  Robert Campbell
 *
 * @since 2.0
 */
final class AdminUtils
{
	/**
	 * A regular expression to use when testing if an item has a valid name.
	 */
	const ITEMNAME_REGEX = '<^[a-zA-Z0-9_\x7f-\xff][a-zA-Z0-9_\ \/\+\-\,\.\x7f-\xff]*$>';

	/**
	 * @ignore
	 */
	private function __construct() {}
	/**
	 * @ignore
	 */
	private function __clone() {}

	/**
	 * Test if a string is suitable for use as a name of an item in CMSMS.
	 * For use by various modules and the core.
	 * The name must begin with an alphanumeric character (but some extended characters are allowed).  And must be followed by the same alphanumeric characters
	 * note the name is not necessarily guaranteed to be usable in smarty without backticks.
	 *
	 * @param string $str The string to test
	 * @return bool|string FALSE on error or the validated string.
	 */
	public static function is_valid_itemname(string $str)
	{
		if( !is_string($str) ) return FALSE;
		$t_str = trim($str);
		if( !$t_str ) return FALSE;
		if( !preg_match(self::ITEMNAME_REGEX,$t_str) ) return FALSE;
		return $str;
	}

	/**
	 * Convert an admin request URL to a generic form that is suitable for saving to a database.
	 * This is useful for things like bookmarks and homepages.
	 *
	 * @param string $in_url The input URL that has the session key in it.
	 * @return string A URL that is converted to a generic form.
	 */
	public static function get_generic_url(string $in_url) : string
	{
		if( !defined('CMS_USER_KEY') ) throw new LogicException('This method can only be called for admin requests');
		if( !isset($_SESSION[CMS_USER_KEY]) || !$_SESSION[CMS_USER_KEY] ) throw new LogicException('This method can only be called for admin requests');

		$len = strlen($_SESSION[CMS_USER_KEY]);
		$in_p = '+'.CMS_SECURE_PARAM_NAME.'\=[A-Za-z0-9]{'.$len.'}&?(amp;)?+';
//		$out_p = '_CMSKEY_='.str_repeat('X',$len); totally unused during login !!
//		$out = preg_replace($in_p,$out_p,$in_url);
		$out = preg_replace($in_p,'',$in_url);
		if( endswith($out,'&amp;') ) $out = substr($out, 0, -5);
		if( startswith($out,CMS_ROOT_URL) ) $out = str_replace(CMS_ROOT_URL,'',$out);
		return $out;
	}

	/**
	 * Convert a generic URL into something that is suitable for this user's session.
	 *
	 * @param string $in_url The generic url. Usually retrieved from a preference or from the database
	 * @return string A URL that has a session key in it.
	 */
	public static function get_session_url(string $in_url) : string
	{
		if( !defined('CMS_USER_KEY') ) throw new LogicException('This method can only be called for admin requests');
		IF( !isset($_SESSION[CMS_USER_KEY]) || !$_SESSION[CMS_USER_KEY] ) throw new LogicException('This method can only be called for admin requests');

		$len = strlen($_SESSION[CMS_USER_KEY]);
		$in_p = '+_CMSKEY_=[X]{'.$len.'}+';
		$out_p = CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
		return preg_replace($in_p,$out_p,$in_url);
	}

	/**
	 * Get the latest available CMSMS version.
	 * This method does a remote request to the version check URL at most once per day.
	 *
	 * @return string
	 */
	public static function fetch_latest_cmsms_ver() : string
	{
		$last_fetch = (int) cms_siteprefs::get('last_remotever_check');
		$remote_ver = cms_siteprefs::get('last_remotever');
		if( $last_fetch < (time() - 24 * 3600) ) {
			$req = new cms_http_request();
			$req->setTimeout(3);
			$req->execute(CMS_DEFAULT_VERSIONCHECK_URL);
			if( $req->getStatus() == 200 ) {
				$remote_ver = trim($req->getResult());
				if( strpos($remote_ver,':') !== FALSE ) {
					list($tmp,$remote_ver) = explode(':',$remote_ver,2);
					$remote_ver = trim($remote_ver);
				}
				cms_siteprefs::set('last_remotever',$remote_ver);
				cms_siteprefs::set('last_remotever_check',time());
			}
		}
		return $remote_ver;
	}

	/**
	 * Report whether a newer version of CMSMS than presently running is available
	 *
	 * @return bool
	 */
	public static function site_needs_updating() : bool
	{
		$remote_ver = self::fetch_latest_cmsms_ver();
		return version_compare(CMS_VERSION,$remote_ver) < 0;
	}

	/**
	 * Get a tag representing a module icon
	 *
	 * @since 2.3
	 * @param string $module Name of the module
	 * @param array $attrs Optional assoc array of attributes for the created img tag
	 * @return string
	 */
	public static function get_module_icon(string $module, array $attrs = []) : string
	{
		$dirs = cms_module_places($module);
		if ($dirs) {
			$appends = [
				['images','icon.svg'],
				['icons','icon.svg'],
				['images','icon.png'],
				['icons','icon.png'],
				['images','icon.gif'],
				['icons','icon.gif'],
				['images','icon.i'],
				['icons','icon.i'],
			];
			foreach ($dirs as $base) {
				foreach ($appends as $one) {
					$path = cms_join_path($base, ...$one);
					if (is_file($path)) {
						$path = cms_path_to_url($path);
						if (endswith($path, '.svg')) {
							// see https://css-tricks.com/using-svg
							$alt = str_replace('svg','png',$path);
							$out = '<img src="'.$path.'" onerror="this.onerror=null;this.src=\''.$alt.'\';"';
						} elseif (endswith($path, '.i')) {
							$props = parse_ini_file($path, false, INI_SCANNER_TYPED);
							if ($props) {
								foreach ($props as $key => $value) {
									if (isset($attrs[$key]) ) {
										if (is_numeric($value) || is_bool($value)) {
											continue; //supplied attrib prevails
										} elseif (is_string($value)) {
											$attrs[$key] = $value.' '.$attrs[$key];
										}
									} else {
										$attrs[$key] = $value;
									}
								}
							}
							$out = '<i';
						} else {
							$out = '<img src="'.$path.'"';
						}
						$extras = array_merge(['alt'=>$module, 'title'=>$module], $attrs);
						foreach( $extras as $key => $value ) {
							if ($value !== '' || $key == 'title') {
								$out .= " $key=\"$value\"";
							}
						}
						if (!endswith($path, '.i')) {
							$out .= ' />';
						} else {
							$out .= '></i>';
						}
						return $out;
					}
				}
			}
		}
		return '';
	}

	/**
	 * Get a tag representing a themed icon or module icon
	 *
	 * @param string $icon the basename of the desired icon file, may include theme-dir-relative path,
	 *  may omit file type/suffix, ignored if smarty variable $actionmodule is currently set
	 * @param array $attrs Since 2.3 Optional assoc array of attributes for the created img tag
	 * @return string
	 */
	public static function get_icon(string $icon, array $attrs = []) : string
	{
		$smarty = Smarty::get_instance();
		$module = $smarty->getTemplateVars('actionmodule');

		if ($module) {
			return self::get_module_icon($module, attrs);
		} else {
			$theme = cms_utils::get_theme_object();
			if( is_object($theme) ) {
				if( basename($icon) == $icon ) $icon = 'icons'.DIRECTORY_SEPARATOR.'system'.DIRECTORY_SEPARATOR.$icon;
				return $theme->DisplayImage($icon,'','','',null,$attrs);
			}
		}
	}

	/**
	 * Get a help tag for displaying inline popup help.
	 *
	 * This method accepts variable arguments. Recognized keys are
	 " 'key1'/'realm','key'/'key2','title'/'titlekey'
	 * If neither 'key1'/'realm' is provided, the fallback will be action-module
	 * name, or else 'help'
	 * If neither 'title'/'titlekey' is provided, the fallback will be 'key'/'key2'
	 *
	 * @param $args string(s) varargs
	 * @return mixed string HTML content of the help tag, or null
	 */
	public static function get_help_tag(...$args)
	{
		if( !CmsApp::get_instance()->test_state(CmsApp::STATE_ADMIN_PAGE) ) return;

		$theme = cms_utils::get_theme_object();
		if( !is_object($theme) ) return;

		$icon = self::get_icon('info', ['class'=>'cms_helpicon']);
		if( !$icon ) return;

		$params = [];
		if( count($args) >= 2 && is_string($args[0]) && is_string($args[1]) ) {
			$params['key1'] = $args[0];
			$params['key2'] = $args[1];
			if( isset($args[2]) ) $params['title'] = $args[2];
		}
		else if( count($args) == 1 && is_string($args[0]) ) {
			$params['key2'] = $args[0];
		}
		else {
			$params = $args[0];
		}

		$key1 = '';
		$key2 = '';
		$title = '';
		foreach( $params as $key => $value ) {
			switch( $key ) {
			case 'key1':
			case 'realm':
				$key1 = trim($value);
				break;
			case 'key':
			case 'key2':
				$key2 = trim($value);
				break;
			case 'title':
			case 'titlekey':
				$title = trim($value); //TODO ensure $value including e.g. &quot; works
			}
		}

		if( !$key1 ) {
			$smarty = Smarty::get_instance();
			$module = $smarty->getTemplateVars('actionmodule');
			if( $module ) {
				$key1 = $module;
			}
			else {
				$key1 = 'help'; //default realm for lang
			}
		}

		if( !$key1 ) return;

		if( $key2 !== '' ) { $key1 .= '__'.$key2; }
		if( $title === '' ) { $title = ($key2) ? $key2 : 'for this'; } //TODO lang

		return '<span class="cms_help" data-cmshelp-key="'.$key1.'" data-cmshelp-title="'.$title.'">'.$icon.'</span>';
	}

	/**
	 * Remove files from the website directories defined as
	 * TMP_CACHE_LOCATION, TMP_TEMPLATES_C_LOCATION, PUBLIC_CACHE_LOCATION
	 * @since 2.3
	 *
	 * @param $age_days Optional file-modification threshold (days), 0 to whatever. Default 0 hence 'now'.
	 */
	public static function clear_cache(int $age_days = 0)
	{
		//TODO also clear non-file caches, if used
		global $CMS_ADMIN_PAGE, $CMS_INSTALL_PAGE;

		if( !(isset($CMS_ADMIN_PAGE) || isset($CMS_INSTALL_PAGE))) return;
		if( !defined('TMP_CACHE_LOCATION') ) return;
		$age_days = max(0,(int)$age_days);
		HookManager::do_hook_simple('clear_cached_files', [ 'older_than' => $age_days ]);
		$the_time = time() - $age_days * 24 * 3600;

		$dirs = array_unique([TMP_CACHE_LOCATION, TMP_TEMPLATES_C_LOCATION, PUBLIC_CACHE_LOCATION]);
		foreach( $dirs as $start_dir ) {
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($start_dir,
					FilesystemIterator::KEY_AS_FILENAME |
					FilesystemIterator::SKIP_DOTS
				),
				RecursiveIteratorIterator::LEAVES_ONLY |
				RecursiveIteratorIterator::SELF_FIRST);
			foreach( $iter as $fn => $inf ) {
				if( $inf->isFile() && $inf->getMTime() <= $the_time ) {
					if( !fnmatch('index.htm?', $fn) ) {
						unlink($inf->getPathname());
					}
					else {
						touch($inf->getPathname());
					}
				}
			}
		}
	}
} // class
