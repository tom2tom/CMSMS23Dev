<?php
#Page related functions.
#Copyright (C) 2004-2018 Ted Kulp <ted@cmsmadesimple.org>
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

/**
 * Page related functions.  Generally these are functions not necessarily
 * related to content, but more to the underlying mechanisms of the system.
 *
 * @package CMS
 * @license GPL
 */

use CMSMS\ContentOperations;
use CMSMS\internal\LoginOperations;
use CMSMS\internal\Smarty;
use CMSMS\SyntaxEditor;
use CMSMS\UserOperations;

/**
 * Gets the userid of the currently logged in user.
 *
 * If an effective uid has been set in the session, AND the primary user is a member of the admin group
 * then allow emulating that effective uid.
 *
 * @since 0.1
 * @param  boolean $redirect Redirect to the admin login page if the user is not logged in.
 * @return integer The UID of the logged in administrator, or NULL
 */
function get_userid(bool $redirect = true)
{
    $login_ops = LoginOperations::get_instance();
    $uid = $login_ops->get_effective_uid();
    if( !$uid && $redirect ) {
        $config = cms_config::get_instance();
        redirect($config['admin_url'].'/login.php');
    }
    return $uid;
}


/**
 * Gets the username of the currently logged in user.
 *
 * If an effective username has been set in the session, AND the primary user is a member of the admin group
 * then return the effective username.
 *
 * @since 2.0
 * @param  boolean $check Redirect to the admin login page if the user is not logged in.
 * @return string the username of the logged in user, or NULL.
 */
function get_username(bool $check = true)
{
    $login_ops = LoginOperations::get_instance();
    $uname = $login_ops->get_effective_username();
    if( !$uname && $check ) {
        $config = cms_config::get_instance();
        redirect($config['admin_url'].'/login.php');
    }
    return $uname;
}


/**
 * Checks to see if the user is logged in and the request has the proper key.  If not, redirects the browser
 * to the admin login.
 *
 * Note: Because this method validates that the secret key is in the URL and matches the one that is in the session
 * this method should only be called from admin actions.
 *
 * @since 0.1
 * @param string $no_redirect If true, then don't redirect if not logged in
 * @return boolean or NULL
 */
function check_login(bool $no_redirect = false)
{
    $do_redirect = !$no_redirect;
    $uid = get_userid(!$no_redirect);
    $res = false;
    if( $uid > 0 ) {
        $res = true;
        $login_ops = LoginOperations::get_instance();
        $res = $login_ops->validate_requestkey();
    }
    if( !$res ) {
        // logged in, but no url key on the request
        if( $do_redirect ) {
            // redirect to the admin login.php
            // use SCRIPT_FILENAME and make sure it validates with the root_path
            if( startswith($_SERVER['SCRIPT_FILENAME'],CMS_ROOT_PATH) ) {
                $_SESSION['login_redirect_to'] = $_SERVER['REQUEST_URI'];
            }
            $login_ops->deauthenticate();
            $config = cms_config::get_instance();
            redirect($config['admin_url'].'/login.php');
        }
    }
    return TRUE;
}



/**
 * Checks to see that the given userid has access to the given permission.
 * Members of the admin group have all permissions.
 *
 * @since 0.1
 * @param int $userid The user id
 * @param string $permname The permission name
 * @return boolean
 */
function check_permission(int $userid, string $permname)
{
    return UserOperations::get_instance()->CheckPermission($userid,$permname);
}


/**
 * Checks that the given userid has access to modify the given
 * pageid.  This would mean that they were set as additional
 * authors/editors by the owner.
 *
 * @internal
 * @since 0.2
 * @param  integer The admin user id
 * @param  integer A valid content id.
 * @return boolean
 */
function check_authorship(int $userid, int $contentid = null)
{
    return ContentOperations::get_instance()->CheckPageAuthorship($userid,$contentid);
}


/**
 * Prepares an array with the list of the pages $userid is an author of
 *
 * @internal
 * @since 0.11
 * @param  integer The user id.
 * @return array   An array of pages this user is an author of.
 */
function author_pages(int $userid)
{
    return ContentOperations::get_instance()->GetPageAccessForUser($userid);
}


/**
 * Gets the given site preference
 *
 * @deprecated
 * @since 0.6
 * @see cms_siteprefs::get
 * @param string $prefname The preference name
 * @param mixed  $defaultvalue The default value if the preference does not exist
 * @return mixed
 */
function get_site_preference(string $prefname, $defaultvalue = null)
{
    return cms_siteprefs::get($prefname,$defaultvalue);
}

/**
 * A method to create a text area control
 *
 * @internal
 * @access private
 * @deprecated since 2.3 instead use CmsFormUtils::create_textarea()
 * @param boolean $enablewysiwyg Whether or not we are enabling a wysiwyg.  If false, and forcewysiwyg is not empty then a syntax area is used.
 * @param string  $value The contents of the text area
 * @param string  $name The name of the text area
 * @param string  $class An optional class name
 * @param string  $id An optional ID (HTML ID) value
 * @param string  $encoding The optional encoding
 * @param string  $stylesheet Optional style information
 * @param integer $width Width (the number of columns) (CSS can and will override this)
 * @param integer $height Height (the number of rows) (CSS can and will override this)
 * @param string  $forcewysiwyg Optional name of the syntax hilighter or wysiwyg to use.  If empty, preferences indicate which a syntax editor or wysiwyg should be used.
 * @param string  $wantedsyntax Optional name of the language used.  If non empty it indicates that a syntax highlihter will be used.
 * @param string  $addtext Optional additional text to include in the textarea tag
 * @return string
 */
function create_textarea(
    bool $enablewysiwyg,
    string $value,
    string $name,
    string $class = '',
    string $id = '',
    string $encoding = '',
    string $stylesheet = '',
    int $width = 80,
    int $height = 15,
    string $forcewysiwyg = '',
    string $wantedsyntax = '',
    string $addtext = ''
) {
    $parms = func_get_args() + [
        'height' => 15,
	    'width' => 80,
	];
    return CmsFormUtils::create_textarea($parms);
}

/**
 * A convenience function to test if the site is marked as down according to the config panel.
 * This method includes handling the preference that indicates that site-down behavior should
 * be disabled for certain IP address ranges.
 *
 * @return boolean
 */
function is_sitedown() : bool
{
    global $CMS_INSTALL_PAGE;
    if( isset($CMS_INSTALL_PAGE) ) return TRUE;

    if( cms_siteprefs::get('enablesitedownmessage') !== '1' ) return FALSE;

    $uid = get_userid(FALSE);
    if( $uid && cms_siteprefs::get('sitedownexcludeadmins') ) return FALSE;

    if( !isset($_SERVER['REMOTE_ADDR']) ) return TRUE;
    $excludes = cms_siteprefs::get('sitedownexcludes','');
    if( empty($excludes) ) return TRUE;

    $tmp = explode(',',$excludes);
    $ret = cms_ipmatches($_SERVER['REMOTE_ADDR'],$excludes);
    if( $ret ) return FALSE;
    return TRUE;
}

/**
 * Create a dropdown form element containing a list of files that match certain conditions
 *
 * @internal
 * @param string The name for the select element.
 * @param string The directory name to search for files.
 * @param string The name of the file that should be selected
 * @param string A comma separated list of extensions that should be displayed in the list
 * @param string An optional string with which to prefix each value in the output by
 * @param boolean Whether 'none' should be an allowed option
 * @param string Text containing additional parameters for the dropdown element
 * @param string A prefix to use when filtering files
 * @param boolean A flag indicating whether the files matching the extension and the prefix should be included or excluded from the result set
 * @param boolean A flag indicating whether the output should be sorted.
 * @return string
 */
function create_file_dropdown(string $name,string $dir,string $value,string $allowed_extensions,string $optprefix='',
                              bool $allownone=false,string $extratext='',
                              string $fileprefix='',bool $excludefiles=true,bool $sortresults = false)
{
  $files = [];
  $files = get_matching_files($dir,$allowed_extensions,true,true,$fileprefix,$excludefiles);
  if( $files === false ) return false;
  $out = "<select name=\"{$name}\" id=\"{$name}\" {$extratext}>\n";
  if( $allownone ) {
    $txt = '';
    if( empty($value) ) $txt = 'selected="selected"';
    $out .= "  <option value=\"-1\" $txt>--- ".lang('none')." ---</option>\n";
  }

  if( $sortresults ) natcasesort($files);
  foreach( $files as $file ) {
    $txt = '';
    $opt = $file;
    if( !empty($optprefix) ) $opt = $optprefix.'/'.$file;
    if( $opt == $value ) $txt = 'selected="selected"';
    $out .= "  <option value=\"{$opt}\" {$txt}>{$file}</option>\n";
  }
  $out .= '</select>';
  return $out;
}


/**
 * A function that, given the current request information will return
 * a pageid or an alias that should be used for the display
 * This method also handles matching routes and specifying which module
 * should be called with what parameters
 *
 * This is the main routine to do route dispatching
 *
 * @internal
 * @ignore
 * @access private
 * @return string
 */
function get_pageid_or_alias_from_url()
{
    $gCms = CmsApp::get_instance();
    $config = cms_config::get_instance();
    $contentops = ContentOperations::get_instance();
    $smarty = Smarty::get_instance();

    $page = '';
    $query_var = $config['query_var'];
    if( isset($_GET[$query_var]) ) {
        // using non friendly urls... get the page alias/id from the query var.
        $page = @trim((string) $_REQUEST[$query_var]);
    }
    else {
        // either we're using internal pretty urls or this is the default page.
        if (isset($_SERVER['REQUEST_URI']) && !endswith($_SERVER['REQUEST_URI'], 'index.php')) {
            $matches = [];
            if (preg_match('/.*index\.php\/(.*?)$/', $_SERVER['REQUEST_URI'], $matches)) {
                // pretty urls... grab all the stuff after the index.php
                $page = trim($matches[1]);
            }
        }
    }
    unset($_GET['query_var']);

    $dflt_content = $contentops->GetDefaultContent();
    if( !$page ) {
        // by here, if page is empty, use the default page id
        return $dflt_content;
    }

    // by here, if we're not assuming pretty urls of any sort
    // and we have a value... we're done.
    if( $config['url_rewriting'] == 'none' ) return $page;

    // some kind of a pretty url.
    // strip off GET params.
    if( ($tmp = strpos($page,'?')) !== FALSE ) $page = substr($page,0,$tmp);

    // strip off page extension
    if ($config['page_extension'] != '' && endswith($page, $config['page_extension'])) {
        $page = substr($page, 0, strlen($page) - strlen($config['page_extension']));
    }

    // trim trailing and leading /
    // it appears that some servers leave in the first / of a request some times which will stop rout matching.
    $page = trim($page, '/');

    // see if there's a route that matches.
    // note: we handle content routing separately at this time.
    // it'd be cool if contents were just another mact.
    $route = cms_route_manager::find_match($page);
    if( ! $route ) {
        // if no route matched... assume it is an alias and that the alias begins after the last /
        if( ($pos = strrpos($page,'/')) !== FALSE ) $page = substr($page, $pos + 1);
    }
    else {
        if( $route['key1'] == '__CONTENT__' ) {
            // a route to a page.
            $page = (int)$route['key2'];
        }
        else {
            $matches = $route->get_results();

            // it's a module route... setup some default parameters.
            $arr = ['id'=>'cntnt01', 'action'=>'defaulturl', 'inline'=>false, 'module'=>$route->get_dest() ];
            $matches = array_merge( $arr, $matches );
            $tmp = $route->get_defaults();
            if( $tmp ) $matches = array_merge( $dflts, $matches );

            // Get rid of numeric matches, and put the data into the _REQUEST for later processing.
            foreach( $matches as $key=>$val ) {
                if( is_int($key) ) {
                    // do nothing
                }
                else if( $key != 'id' && $key != 'returnid' && $key != 'action' ) {
                    $_REQUEST[$matches['id'] . $key] = $val;
                }
            }

            // Put the resulting mact into the request for later processing.
            // this is essentially our translation from pretty URLs to non-pretty URLS.
            $_REQUEST['mact'] = $matches['module'] . ',' . $matches['id'] . ',' . $matches['action'] . ',' . $matches['inline'];

            // Get a decent returnid
            $page = $dflt_content;
            if( $matches['returnid'] ) {
                $page = (int) $matches['returnid'];
                unset( $matches['returnid'] );
            }
        }
    }

    return $page;
}

/**
 * Get javascript for initialization of the configured 'advanced'
 *  (a.k.a. wysiwyg) text-editor
 * @since 2.3
 * @param array $params  Configuration details. Recognized members are:
 *  bool   'edit'   whether the content is editable. Default false (i.e. just for display)
 *  string 'handle' name of the js variable to be used for the created editor. Default 'editor'
 *  string 'htmlid' id of the page-element whose content is to be edited. Mandatory.
 *  string 'style'  override for the normal editor theme/style.  Default ''
 *  string 'typer'  content-type identifier, an absolute filepath or at least
 *    an extension or pseudo (like 'smarty'). Default ''
 *  string 'workid' id of a div to be created (by some editors) to process
 *    the content of the htmlid-element. Default 'Editor'
 *
 * @return array up to 2 members, being 'head' and/or 'foot'
 */
function get_editor_script(array $params) : array
{
	$handler = cms_siteprefs::get('syntax_editor');
	if( $handler ) {
		list($modname, $edname) = explode('::', $handler);
		if( !$edname ) {
			$edname = $modname;
		}
		$modinst = cms_utils::get_module($modname);
		if( $modinst && ($modinst instanceof SyntaxEditor) ) {
			return $modinst->GetEditorScript($edname, $params);
		}
	}
	return [];
}
