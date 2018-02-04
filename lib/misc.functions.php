<?php
#utility-methods for CMSMS
#Copyright (C) 2004-2012 Ted Kulp <ted@cmsmadesimple.org>
#Copyright (C) 2012-2018 The CMSMS Dev Team <coreteam@cmsmadesimple.org>
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
#
#$Id$

/**
 * Miscellaneous support functions
 *
 * @package CMS
 * @license GPL
 */

/**
 * Redirects to relative URL on the current site.
 *
 * If headers have not been sent this method will use header based redirection.
 * Otherwise javascript redirection will be used.
 *
 * @author http://www.edoceo.com/
 * @since 0.1
 * @package CMS
 * @param string $to The url to redirect to
 */
function redirect(string $to)
{
    $_SERVER['PHP_SELF'] = null;

    $schema = 'http';
    if( CmsApp::get_instance()->is_https_request() ) $schema = 'https';

    $host = $_SERVER['HTTP_HOST'];
    $components = parse_url($to);
    if(count($components) > 0) {
        $to =  (isset($components['scheme']) && startswith($components['scheme'], 'http') ? $components['scheme'] : $schema) . '://';
        $to .= $components['host'] ?? $host;
        $to .= isset($components['port']) ? ':' . $components['port'] : '';
        if(isset($components['path'])) {
            if(in_array(substr($components['path'],0,1),array('\\','/'))) {
                //Path is absolute, just append.
                $to .= $components['path'];
            }
            //Path is relative, append current directory first.
            else if (isset($_SERVER['PHP_SELF']) && !is_null($_SERVER['PHP_SELF'])) { //Apache
                $to .= (strlen(dirname($_SERVER['PHP_SELF'])) > 1 ?  dirname($_SERVER['PHP_SELF']).'/' : '/') . $components['path'];
            }
            else if (isset($_SERVER['REQUEST_URI']) && !is_null($_SERVER['REQUEST_URI'])) { //Lighttpd
                if (endswith($_SERVER['REQUEST_URI'], '/')) {
                    $to .= (strlen($_SERVER['REQUEST_URI']) > 1 ? $_SERVER['REQUEST_URI'] : '/') . $components['path'];
                }
                else {
                    $dn = dirname($_SERVER['REQUEST_URI']);
                    if( !endswith($dn,'/') ) $dn .= '/';
                    $to .= $dn . $components['path'];
                }
            }
        }
        $to .= isset($components['query']) ? '?' . $components['query'] : '';
        $to .= isset($components['fragment']) ? '#' . $components['fragment'] : '';
    }
    else {
        $to = $schema."://".$host."/".$to;
    }

    session_write_close();

    // this could be used in install/upgrade routines where config is not set yet
    // so cannot use constants.
    $debug = false;
    if( class_exists('CmsApp') ) {
        $config = CmsApp::get_instance()->GetConfig();
        $debug = $config['debug'];
    }

    if (headers_sent() && !$debug) {
        // use javascript instead
        echo '<script type="text/javascript">
<!-- location.replace("'.$to.'"); // -->
</script>
<noscript>
<meta http-equiv="Refresh" content="0;URL='.$to.'">
</noscript>';
        exit;
    }
    else {
        if ( $debug ) {
            echo 'Debug is on. Redirection is disabled... Please click this link to continue.<br />
<a accesskey="r" href="'.$to.'">'.$to.'</a><br />
<div id="DebugFooter">';
            foreach (CmsApp::get_instance()->get_errors() as $error) {
                echo $error;
            }
            echo '</div> <!-- end DebugFooter -->';
            exit;
        }
        else {
            header("Location: $to");
            exit;
        }
    }
}

/**
 * Given a page ID or an alias, redirect to it.
 * Retrieves the URL of the specified page, and performs a redirect
 *
 * @param mixed $alias An integer page id or a string page alias.
 */
function redirect_to_alias(string $alias)
{
  $manager = CmsApp::get_instance()->GetHierarchyManager();
  $node = $manager->sureGetNodeByAlias($alias);
  if( !$node ) {
    // put mention into the admin log
    cms_warning('Core: Attempt to redirect to invalid alias: '.$alias);
    return;
  }
  $content = $node->GetContent();
  if (!is_object($content)) {
    cms_warning('Core: Attempt to redirect to invalid alias: '.$alias);
    return;
  }
  if ($content->GetURL() != '') redirect($content->GetURL());
}

/**
 * Calculate the difference in seconds between two microtime() values.
 *
 * @since 0.3
 * @param string $a Earlier microtime value
 * @param string $b Later microtime value
 * @return int The difference.
 */
function microtime_diff(string $a, string $b) : int
{
    list($a_dec, $a_sec) = explode(' ', $a);
    list($b_dec, $b_sec) = explode(' ', $b);
    return $b_sec - $a_sec + $b_dec - $a_dec;
}

/**
 * Joins path-segments together using the platform-specific directory separator.
 *
 * This method should NOT be used for building URLS.
 *
 * This method accepts a variable number of string arguments
 * e.g. $out = cms_join_path($dir1,$dir2,$dir3,$filename)
 * or $out = cms_join_path($dir1,$dir2,$filename)
 *
 * @since 0.14
 * @return string
 */
function cms_join_path(...$args) : string
{
    $path = implode(DIRECTORY_SEPARATOR, $args);
    return str_replace(['\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR],
        [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $path);
}

/**
 * Return the relative portion of a path
 *
 * @since 2.2
 * @author Robert Campbell
 * @param string $in The input path or file specification
 * @param string $relative_to The optional path to compute relative to.  If not supplied the cmsms root path will be used.
 * @return string The relative portion of the input string.
 */
function cms_relative_path(string $in,string $relative_to = null) : string
{
    $in = realpath(trim($in));
    if( !$relative_to ) $relative_to = CMS_ROOT_PATH;
    $to = realpath(trim($relative_to));

    if ($in && $to && startswith($in, $to)) {
        return substr($in, strlen($to));
    }
    return '';
}

/**
 * Get PHP flag corresponding to the configured 'content_language' i.e. the
 * preferred language/syntax for page-content
 *
 * @return PHP flag
 */
function cms_preferred_lang() : int
{
    $config = CmsApp::get_instance()->GetConfig();
    $val = str_toupper($config['content_language']);
    switch ($val) {
        case 'HTML5';
            return ENT_HTML5;
        case 'HTML':
            return ENT_HTML401; //a.k.a. 0
        case 'NONE':
            return 0;
        default:
            return ENT_XHTML;
    }
}

static $deflang = 0;
static $defenc = '';

/**
 * Perform HTML entity conversion on a string.
 *
 * @see htmlentities
 *
 * @param mixed  $val     The input string, or maybe null
 * @param int    $param   Optional flag(s) indicating how htmlentities() should handle quotes etc. Default 0, hence ENT_QUOTES | cms_preferred_lang().
 * @param string $charset Optional character set of $val. Default 'UTF-8'. If empty the system setting will be used.
 * @param bool   $convert_single_quotes Optional flag indicating whether single quotes should be converted to entities. Default false.
 *
 * @return string the converted string
 */
function cms_htmlentities($val, int $param = 0, string $charset = 'UTF-8', bool $convert_single_quotes = false) : string
{
	if ($val === '' || $val === null) {
        return '';
    }

    global $deflang, $defenc;

    if ($param === 0) {
        $param = ($convert_single_quotes) ? ENT_QUOTES : ENT_COMPAT;
    }
    if ($param & (ENT_HTML5 | ENT_XHTML | ENT_HTML401) == 0) {
        if ($deflang === 0) {
            $deflang = cms_preferred_lang();
        }
        $param |= $deflang;
    }
    if ($convert_single_quotes) {
        $param &= ~(ENT_COMPAT | ENT_NOQUOTES);
    }

    if (!$charset) {
        if ($defenc === '') {
            $defenc = CmsNlsOperations::get_encoding();
        }
        $charset = $defenc;
    }
    return htmlentities($val, $param, $charset, false);
}

/**
 * Perform HTML entity conversion on a string.
 *
 * @see html_entity_decode
 *
 * @param string $val     The input string
 * @param int    $param   Optional flag(s) indicating how html_entity_decode() should handle quotes etc. Default 0, hence ENT_QUOTES | cms_preferred_lang().
 * @param string $charset Optional character set of $val. Default 'UTF-8'. If empty the system setting will be used.
 *
 * @return string the converted string
 */
function cms_html_entity_decode(string $val, int $param = 0, string $charset = 'UTF-8') : string
{
    if ($val === '') {
        return '';
    }

    global $deflang, $defenc;

    if ($param === 0) {
        $param = ENT_QUOTES;
    }
    if ($param & (ENT_HTML5 | ENT_XHTML | ENT_HTML401) == 0) {
        if ($deflang === 0) {
            $deflang = cms_preferred_lang();
        }
        $param |= $deflang;
    }

    if (!$charset) {
        if ($defenc === '') {
            $defenc = CmsNlsOperations::get_encoding();
        }
        $charset = $defenc;
    }

    return html_entity_decode($val, $param, $charset);
}

/**
 * Output a backtrace into the generated log file.
 *
 * @see debug_to_log, debug_bt
 * Rolf: Looks like not used
 */
function debug_bt_to_log()
{
    if( CmsApp::get_instance()->config['debug_to_log'] || (function_exists('get_userid') && get_userid(false)) ) {
        $bt=debug_backtrace();
        $file = $bt[0]['file'];
        $line = $bt[0]['line'];

        $out = ["Backtrace in $file on line $line"];

        $bt = array_reverse($bt);
        foreach($bt as $trace) {
            if( $trace['function'] == 'debug_bt_to_log' ) continue;

            $file = '';
            $line = '';
            if( isset($trace['file']) ) {
                $file = $trace['file'];
                $line = $trace['line'];
            }
            $function = $trace['function'];
            $str = "$function";
            if( $file ) $str .= " at $file:$line";
            $out[] = $str;
        }

        $filename = TMP_CACHE_LOCATION . '/debug.log';
        foreach ($out as $txt) {
            error_log($txt . "\n", 3, $filename);
        }
    }
}

/**
 * A function to generate a backtrace in a readable format.
 *
 * This function does not return but echoes output.
 */
function debug_bt()
{
    $bt=debug_backtrace();
    $file = $bt[0]['file'];
    $line = $bt[0]['line'];

    echo "\n\n<p><b>Backtrace in $file on line $line</b></p>\n";

    $bt = array_reverse($bt);
    echo "<pre><dl>\n";
    foreach($bt as $trace) {
        $file = $trace['file'];
        $line = $trace['line'];
        $function = $trace['function'];
        $args = implode(',', $trace['args']);
        echo "
        <dt><b>$function</b>($args) </dt>
        <dd>$file on line $line</dd>
        ";
    }
    echo "</dl></pre>\n";
}

/**
* Debug function to display $var nicely in html.
*
* @param mixed $var The data to display
* @param string $title (optional) title for the output.  If null memory information is output.
* @param bool $echo_to_screen (optional) Flag indicating wether the output should be echoed to the screen or returned.
* @param bool $use_html (optional) flag indicating wether html or text should be used in the output.
* @param bool $showtitle (optional) flag indicating wether the title field should be displayed in the output.
* @return string
*/
function debug_display($var, string $title='', bool $echo_to_screen = true, bool $use_html = true, bool $showtitle = true) : string
{
    global $starttime, $orig_memory;
    if( !$starttime ) $starttime = microtime();

    ob_start();

    if( $showtitle ) {
        $titleText = "Debug: ";
        if($title) $titleText = "Debug display of '$title':";
        $titleText .= '(' . microtime_diff($starttime,microtime()) . ')';
        if (function_exists('memory_get_usage')) {
            $net = memory_get_usage() - $orig_memory;
            $titleText .= ' - (net usage: '.$net.')';
        }

        $memory_peak = (function_exists('memory_get_peak_usage')?memory_get_peak_usage():'');
        if( $memory_peak ) $titleText .= ' - (peak: '.$memory_peak.')';

        if ($use_html) {
            echo "<div><b>$titleText</b>\n";
        }
        else {
            echo "$titleText\n";
        }
    }

    if(!empty($var)) {
        if ($use_html) echo '<pre>';
        if(is_array($var)) {
            echo "Number of elements: " . count($var) . "\n";
            print_r($var);
        }
        elseif(is_object($var)) {
            print_r($var);
        }
        elseif(is_string($var)) {
            if( $use_html ) {
                print_r(htmlentities(str_replace("\t", '  ', $var)));
            }
            else {
                print_r($var);
            }
        }
        elseif(is_bool($var)) {
            echo $var === true ? 'true' : 'false';
        }
        else {
            print_r($var);
        }
        if ($use_html) echo '</pre>';
    }
    if ($use_html) echo "</div>\n";

    $output = ob_get_contents();
    ob_end_clean();

    if($echo_to_screen) echo $output;
    return $output;
}

/**
 * Display $var nicely only if $config["debug"] is set.
 *
 * @param mixed $var
 * @param string $title
 */
function debug_output($var, string $title="")
{
    $config = \cms_config::get_instance();
    if( $config['debug'] ) debug_display($var, $title, true);
}

/**
 * Debug function to output debug information about a variable in a formatted matter
 * to a debug file.
 *
 * @param mixed $var    data to display
 * @param string $title optional title.
 * @param string $filename optional output filename
 */
function debug_to_log($var, string $title='',string $filename = '')
{
    $config = \cms_config::get_instance();
    if( $config['debug_to_log'] || (function_exists('get_userid') && get_userid(false)) ) {
        if( $filename == '' ) {
            $filename = TMP_CACHE_LOCATION . '/debug.log';
            $x = (is_file($filename)) ? @filemtime($filename) : time();
            if( $x !== false && $x < (time() - 24 * 3600) ) unlink($filename);
        }
        $errlines = explode("\n",debug_display($var, $title, false, false, true));
        foreach ($errlines as $txt) {
            error_log($txt . "\n", 3, $filename);
        }
    }
}

/**
 * Display $var nicely to the CmsApp::get_instance()->errors array if $config['debug'] is set.
 *
 * @param mixed $var
 * @param string $title
 */
function debug_buffer($var, string $title="")
{
    if( !defined('CMS_DEBUG') || CMS_DEBUG == 0 ) return;
    CmsApp::get_instance()->add_error(debug_display($var, $title, false, true));
}

/**
* Return $value if it's set and same basic type as $default_value,
* Otherwise return $default_value. Note. Also will trim($value) if $value is not numeric.
*
* @ignore
* @param mixed $value
* @param mixed $default_value
* @param mixed $session_key
* @deprecated
* @return mixed
*/
function _get_value_with_default($value, $default_value = '', $session_key = '')
{
    if($session_key != '') {
        if(isset($_SESSION['default_values'][$session_key])) $default_value = $_SESSION['default_values'][$session_key];
    }

    // set our return value to the default initially and overwrite with $value if we like it.
    $return_value = $default_value;

    if(isset($value)) {
        if(is_array($value)) {
            // $value is an array - validate each element.
            $return_value = [];
            foreach($value as $element) {
                $return_value[] = _get_value_with_default($element, $default_value);
            }
        }
        else {
            if(is_numeric($default_value)) {
                if(is_numeric($value)) {
                    $return_value = $value;
                }
            }
            else {
                $return_value = trim($value);
            }
        }
    }

    if($session_key != '') $_SESSION['default_values'][$session_key] = $return_value;
    return $return_value;
}

/**
 * Retrieve the $value from the $parameters array checking for $parameters[$value] and
 * $params[$id.$value].
 * Returns $default if $value is not in $params array.
 * Note: This function will also trim() string values.
 *
 * @param array $parameters
 * @param string $value
 * @param mixed $default_value
 * @param string $session_key
 * @return mixed
 */
function get_parameter_value(array $parameters, string $value, $default_value = '', string $session_key = '')
{
    if($session_key != '') {
        if(isset($_SESSION['parameter_values'][$session_key])) $default_value = $_SESSION['parameter_values'][$session_key];
    }

    // set our return value to the default initially and overwrite with $value if we like it.
    $return_value = $default_value;
    if(isset($parameters[$value])) {
        if(is_bool($default_value)) {
            // want a bool return_value
            if(isset($parameters[$value])) $return_value = (bool)$parameters[$value];
        }
        else {
            // is $default_value a number?
            $is_number = false;
            if(is_numeric($default_value)) $is_number = true;

            if(is_array($parameters[$value])) {
                // $parameters[$value] is an array - validate each element.
                $return_value = [];
                foreach($parameters[$value] as $element) {
                    $return_value[] = _get_value_with_default($element, $default_value);
                }
            }
            else {
                if(is_numeric($default_value)) {
                    // default value is a number, we only like $parameters[$value] if it's a number too.
                    if(is_numeric($parameters[$value])) $return_value = $parameters[$value];
                }
                elseif(is_string($default_value)) {
                    $return_value = trim($parameters[$value]);
                }
                else {
                    $return_value = $parameters[$value];
                }
            }
        }
    }

    if($session_key != '') $_SESSION['parameter_values'][$session_key] = $return_value;
    return $return_value;
}

/**
 * Check the permissions of a directory recursively to make sure that
 * we have write permission to all files.
 *
 * @param  string  $path Start directory.
 * @return bool
 */
function is_directory_writable( string $path )
{
    if ( substr ( $path , strlen ( $path ) - 1 ) != '/' ) $path .= '/' ;

    if( !is_dir($path) ) return false;
    $result = true;
    if( $handle = opendir( $path ) ) {
        while( false !== ( $file = readdir( $handle ) ) ) {
            if( $file == '.' || $file == '..' ) continue;

            $p = $path.$file;
            if( !@is_writable( $p ) ) return false;

            if( @is_dir( $p ) ) {
                $result = is_directory_writable( $p );
                if( !$result ) return false;
            }
        }
        closedir( $handle );
        return true;
    }
    return false;
}

/**
 * Return an array containing a list of files in a directory
 * performs a non recursive search.
 *
 * @internal
 * @param string $dir path to search
 * @param string $extensions include only files matching these extensions. case insensitive, comma delimited
 * @param bool   $excludedot
 * @param bool   $excludedir
 * @param string $fileprefix
 * @param bool   $excludefiles
 * @return mixed bool or array
 * Rolf: only used in this file
 */
function get_matching_files(string $dir,string $extensions = '',bool $excludedot = true,bool $excludedir = true, string $fileprefix='',bool $excludefiles = true)
{
    if( !is_dir($dir) ) return false;
    $dh = opendir($dir);
    if( !$dh ) return false;

    if( !empty($extensions) ) $extensions = explode(',',strtolower($extensions));
    $results = [];
    while( false !== ($file = readdir($dh)) ) {
        if( $file == '.' || $file == '..' ) continue;
        if( startswith($file,'.') && $excludedot ) continue;
        if( is_dir(cms_join_path($dir,$file)) && $excludedir ) continue;
        if( !empty($fileprefix) ) {
            if( $excludefiles && startswith($file,$fileprefix) ) continue;
            if( !$excludefiles && !startswith($file,$fileprefix) ) continue;
        }

        $ext = strtolower(substr($file,strrpos($file,'.')+1));
        if( is_array($extensions) && count($extensions) && !in_array($ext,$extensions) ) continue;

        $results[] = $file;
    }
    closedir($dh);
    if( !count($results) ) return false;
    return $results;
}

/**
 * Return an array containing a list of files in a directory performs a recursive search.
 *
 * @param  string  $path     Start Path.
 * @param  array   $excludes Array of regular expressions indicating files to exclude.
 * @param  int     $maxdepth How deep to browse (-1=unlimited)
 * @param  string  $mode     "FULL"|"DIRS"|"FILES"
 * @param  int     $d        for internal use only
 * @return mixed bool or array
**/
function get_recursive_file_list ( string $path ,array $excludes, int $maxdepth = -1, string $mode = "FULL" , int $d = 0 )
{
    $fn = function( $file, $excludes ) {
        // strip the path from the file
        if( empty($excludes) ) return false;
        foreach( $excludes as $excl ) {
            if( @preg_match( "/".$excl."/i", basename($file) ) ) return true;
        }
        return false;
    };

    if ( substr ( $path , strlen ( $path ) - 1 ) != '/' ) { $path .= '/' ; }
    $dirlist = array () ;
    if ( $mode != "FILES" ) { $dirlist[] = $path ; }
    if ( $handle = opendir ( $path ) ) {
        while ( false !== ( $file = readdir ( $handle ) ) ) {
            if( $file == '.' || $file == '..' ) continue;
            if( $fn( $file, $excludes ) ) continue;

            $file = $path . $file ;
            if ( ! @is_dir ( $file ) ) { if ( $mode != "DIRS" ) { $dirlist[] = $file ; } }
            elseif ( $d >=0 && ($d < $maxdepth || $maxdepth < 0) ) {
                $result = get_recursive_file_list ( $file . '/' , $excludes, $maxdepth , $mode , $d + 1 ) ;
                $dirlist = array_merge ( $dirlist , $result ) ;
            }
        }
        closedir ( $handle ) ;
    }
    if ( $d == 0 ) { natcasesort ( $dirlist ) ; }
    return ( $dirlist ) ;
}

/**
 * Delete directory $path, and all files and folders in it; synonymous with rm -r.
 *
 * @param string $path The directory filepath
 * @return bool indicating complete success
 */
function recursive_delete($path)
{
    if (is_dir($path)) {
        $res = true;
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path,
		        FilesystemIterator::CURRENT_AS_PATHNAME |
		        FilesystemIterator::SKIP_DOTS
			), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iter as $p) {
            if (is_dir($p)) {
                if (!@rmdir($p)) {
                    $res = false;
                }
            } elseif (!@unlink($p)) {
                $res = false;
            }
        }
        if ($res) {
            $res = @rmdir($path);
        }
        return $res;
    }
    return false;
}

/**
 * Recursively chmod $path, and if it's a directory, all files and folders in it.
 * Links are not changed.
 *  Rolf: only used in admin/listmodules.php
 *
 * @see chmod
 *
 * @param string $path The start location
 * @param int   $mode The octal mode
 * @return bool indicating complete success
 */
function chmod_r($path, $mode)
{
    $res = true;
    if (is_dir($path)) {
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path,
		        FilesystemIterator::CURRENT_AS_PATHNAME |
		        FilesystemIterator::SKIP_DOTS
			), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iter as $p) {
            if (!(is_link($p) || @chmod($p, $mode))) {
                $res = false;
            }
        }
    }
    if (!is_link($path)) {
        return @chmod($path, $mode) && $res;
    }
    return $res;
}

/**
 * A convenience function to test whether one string starts with another.
 *
 * e.g. startswith('The Quick Brown Fox','The');
 *
 * @param string $str The string to test against
 * @param string $sub The search string
 * @return bool
 */
function startswith( string $str, string $sub ) : bool
{
    return strncmp( $str, $sub, strlen($sub) ) === 0;
}

/**
 * Similar to the startswith method, this function tests whether string A ends with string B.
 *
 * e.g. endswith('The Quick Brown Fox','Fox');
 *
 * @param string $str The string to test against
 * @param string $sub The search string
 * @return bool
 */
function endswith( string $str, string $sub ) : bool
{
    $o = strlen( $sub );
	if( $o > 0 && $o <= strlen($str) ) {
        return strpos($str, $sub, -$o) !== false;
    }
    return false;
}

/**
 * Convert a human readable string into something that is suitable for use in URLS.
 *
 * @param string $alias String to convert
 * @param bool   $tolower Indicates whether output string should be converted to lower case
 * @param bool   $withslash Indicates wether slashes should be allowed in the input.
 * @return string
 */
function munge_string_to_url(string $alias, bool $tolower = false, bool $withslash = false) : string
{
    if ($tolower) $alias = mb_strtolower($alias); //TODO if mb_string N/A?

    // remove invalid chars
    $expr = '/[^\p{L}_\-\.\ \d]/u';
    if( $withslash ) $expr = '/[^\p{L}_\.\-\ \d\/]/u';
    $tmp = trim( preg_replace($expr,'',$alias) );

    // remove extra dashes and spaces.
    $tmp = str_replace(' ','-',$tmp);
    $tmp = str_replace('---','-',$tmp);
    $tmp = str_replace('--','-',$tmp);

    return trim($tmp);
}

/**
 * Get $str scrubbed of any (or most?) non-alphanumeric chars CHECKME allow "_-." etc ?
 * @param string $str String to clean
 * @return string
 */
function sanitize(string $str) : string
{
    return preg_replace('/[^[:alnum:]_\-\.]/u', '', $str);
}

/**
 * Sanitize input to prevent against XSS and other nasty stuff.
 * Used (almost entirely) on member(s) of $_POST[] or $_GET[]
 *
 * @internal
 * @param mixed $val input value
 * @return string
 */
function cleanValue($val) : string
{
/*
// Taken from cakephp (http://cakephp.org)
// Licensed under the MIT License
  if ($val == "") return $val;
  //Replace odd spaces with safe ones
  $val = str_replace(" ", " ", $val);
  $val = str_replace(chr(0xCA), "", $val);
  //Encode any HTML to entities (including \n --> <br />)
  $_cleanHtml = function($string,$remove = false) {
    if ($remove) {
      $string = strip_tags($string);
    } else {
      $patterns = array("/\&/", "/%/", "/</", "/>/", '/"/', "/'/", "/\(/", "/\)/", "/\+/", "/-/");
      $replacements = array("&amp;", "&#37;", "&lt;", "&gt;", "&quot;", "&#39;", "&#40;", "&#41;", "&#43;", "&#45;");
      $string = preg_replace($patterns, $replacements, $string);
    }
    return $string;
  };
  $val = $_cleanHtml($val);
  //Double-check special chars and remove carriage returns
  //For increased SQL security
  $val = preg_replace("/\\\$/", "$", $val);
  $val = preg_replace("/\r/", "", $val);
  $val = str_replace("!", "!", $val);
  $val = str_replace("'", "'", $val);
  //Allow unicode (?)
  $val = preg_replace("/&amp;#([0-9]+);/s", "&#\\1;", $val);
  //Add slashes for SQL
  //$val = $this->sql($val);
  //Swap user-inputted backslashes (?)
  $val = preg_replace("/\\\(?!&amp;#|\?#)/", "\\", $val);
*/
    if (!is_string($val) || $val === '') {
        return $val;
    }
    return filter_var($val, FILTER_SANITIZE_SPECIAL_CHARS, 0);
}

/**
 * Sanitize input to prevent against XSS and other nasty stuff.
 * Used (almost entirely) on $_SERVER[] and/or $_GET[]
 *
 * @internal
 * @param array $array reference to array of input values
 */
function cleanArray(array &$array)
{
    foreach ($array as &$val) {
        if (is_string($val) && $val !== '') {
            $val = filter_var($val, FILTER_SANITIZE_STRING,
                    FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK);
        }
    }
    unset($val);
}

/**
 * A convenience function to return a bool variable given a php ini key that represents a bool.
 *
 * @param string $str The php ini key
 * @return bool
 */
function ini_get_boolean(string $str) : bool
{
    return cms_to_bool(ini_get($str));
}

/**
 * Another convenience function to output a human readable function stack trace.
 *
 * This method uses echo.
 */
function stack_trace()
{
    $stack = debug_backtrace();
    foreach( $stack as $elem ) {
        if( $elem['function'] == 'stack_trace' ) continue;
        if( isset($elem['file'])  ) {
            echo $elem['file'].':'.$elem['line'].' - '.$elem['function'].'<br/>';
        }
        else {
            echo ' - '.$elem['function'].'<br/>';
        }
    }
}

/**
 * A wrapper around move_uploaded_file that attempts to ensure permissions on uploaded
 * files are set correctly.
 *
 * @param string $tmpfile The temporary file specification
 * @param string $destination The destination file specification
 * @return bool.
 */
function cms_move_uploaded_file( string $tmpfile, string $destination ) : bool
{
    $config = CmsApp::get_instance()->GetConfig();

    if( !@move_uploaded_file( $tmpfile, $destination ) ) return false;
    @chmod($destination,octdec($config['default_upload_permission']));
    return true;
}

/**
 * A function to test wether an IP address matches a list of expressions.
 * Credits to J.Adams <jna@retins.net>
 *
 * Matches:
 * xxx.xxx.xxx.xxx        (exact)
 * xxx.xxx.xxx.[yyy-zzz]  (range)
 * xxx.xxx.xxx.xxx/nn    (nn = # bits, cisco style -- i.e. /24 = class C)
 *
 * Does not match:
 * xxx.xxx.xxx.xx[yyy-zzz]  (range, partial octets nnnnnot supported)
 *
 * @param string $ip IP address to test
 * @param array  $checklist Array of match expressions
 * @return bool
 * Rolf: only used in lib/content.functions.php
 */
function cms_ipmatches(string $ip,array $checklist) : bool
{
    $_testip = function($range,$ip) {
    $result = 1;

    $regs = [];
    if (preg_match("/([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)\/([0-9]+)/",$range,$regs)) {
      // perform a mask match
      $ipl = ip2long($ip);
      $rangel = ip2long($regs[1] . "." . $regs[2] . "." . $regs[3] . "." . $regs[4]);

      $maskl = 0;

      for ($i = 0; $i< 31; $i++) {
         if ($i < $regs[5]-1) $maskl = $maskl + pow(2,(30-$i));
      }

      return ($maskl & $rangel) == ($maskl & $ipl);
    } else {
      // range based
      $maskocts = explode('.',$range);
      $ipocts = explode('.',$ip);

      if( count($maskocts) != count($ipocts) && count($maskocts) != 4 ) return 0;

      // perform a range match
      for ($i=0; $i<4; $i++) {
    if (preg_match("/\[([0-9]+)\-([0-9]+)\]/",$maskocts[$i],$regs)) {
      if ( ($ipocts[$i] > $regs[2]) || ($ipocts[$i] < $regs[1])) $result = 0;
    }
    else {
      if ($maskocts[$i] <> $ipocts[$i]) $result = 0;
    }
      }
    }
    return $result;
  }; // _testip

  if( !is_array($checklist) ) $checklist = explode(',',$checklist);
  foreach( $checklist as $one ) {
    if( $_testip(trim($one),$ip) ) return true;
  }
  return false;
}

/**
 * Test if the string provided is a valid email address.
 *
 * @param string  $email
 * @param bool $checkDNS
 * @return bool
*/
function is_email( string $email, bool $checkDNS=false )
{
    if( !filter_var($email,FILTER_VALIDATE_EMAIL) ) return false;
    if ($checkDNS && function_exists('checkdnsrr')) {
        list($user,$domain) = explode('@',$email,2);
        if( !$domain ) return false;
        if ( !(checkdnsrr($domain, 'A') || checkdnsrr($domain, 'MX'))) return false; // Domain doesn't actually exist
    }

   return true;
}

/**
 * A convenience method to output the secure param tag that is used on all admin links.
 *
 * @internal
 * @access private
 * @return string
 * Rolf: only used in admin/imagefiles.php
 */
function get_secure_param() : string
{
    $urlext = '?';
    $str = strtolower(ini_get('session.use_cookies'));
    if( $str == '0' || $str == 'off' ) $urlext .= htmlspecialchars(SID).'&';
    $urlext .= CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
    return $urlext;
}

/**
 * A simple function to convert a string to a bool.
 * Accepts number != 0, 'y','yes','true','on' as true (case insensitive) all other values represent false.
 *
 * @param string $str Input to test.
 */
function cms_to_bool(string $str) : bool
{
    if( is_numeric($str) ) return (int)$str !== 0;

    switch (strtolower($str)) {
        case 'y':
        case 'yes':
        case 'true':
        case 'on':
            return true;
        default:
            return false;
    }
}

/**
 * A function to return the appropriate HTML tags to include the CMSMS included jquery in a web page.
 *
 * CMSMS is distributed with a recent version of jQuery, jQueryUI and various other jquery based
 * libraries.  This function generates the HTML code that will include these scripts.
 *
 * See the {cms_jquery} smarty plugin for a convenient way of including the CMSMS provided jquery
 * libraries from within a smarty template.
 *
 * Known libraries:
 *   jquery
 *   jquery-ui
 *   nestedSortable
 *   json
 *   migrate
 *
 * @since 1.10
 * @deprecated
 * @param string $exclude A comma separated list of script names or aliases to exclude.
 * @param bool $ssl Force use of the ssl_url for the root url to necessary scripts.
 * @param bool $cdn Force the use of a CDN url for the libraries if one is known
 * @param string  $append A comma separated list of library URLS to the output
 * @param string  $custom_root A custom root URL for all scripts (when using local mode).  If this is spefied the $ssl param will be ignored.
 * @param bool $include_css Optionally output stylesheet tags for the included javascript libraries.
 */
function cms_get_jquery(string $exclude = '',bool $ssl = false,bool $cdn = false,string $append = '',string $custom_root='',bool $include_css = true)
{
    $config = cms_config::get_instance();
    $scripts = [];
    $base_url = CMS_ROOT_URL;
    if( $ssl === true || $ssl === true ) $base_url = $config['ssl_url'];
    $basePath=$custom_root!=''?trim($custom_root,'/'):$base_url;

    // Scripts to include
    $scripts['jquery'] = array('cdn'=>'https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js',
                 'local'=>$basePath.'/lib/jquery/js/jquery-1.12.4.min.js',
                 'aliases'=>array('jquery.min.js','jquery',));
    $scripts['jquery-ui'] = array('cdn'=>'https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js',
                'local'=>$basePath.'/lib/jquery/js/jquery-ui-1.11.4.custom.min.js',
                'aliases'=>array('jquery-ui.min.js','ui'),
                'css'=>$basePath.'/lib/jquery/css/smoothness/jquery-ui-1.11.4.custom.min.css');
    $scripts['nestedSortable'] = array('local'=>$basePath.'/lib/jquery/js/jquery.mjs.nestedSortable.min.js');
//  $scripts['json'] = array('local'=>$basePath.'/lib/jquery/js/jquery.json-2.4.min.js');
    $scripts['migrate'] = array('local'=>$basePath.'/lib/jquery/js/jquery-migrate-1.3.0.min.js');

  if( CmsApp::get_instance()->test_state(CmsApp::STATE_ADMIN_PAGE) ) {
      global $CMS_LOGIN_PAGE;
      if( isset($_SESSION[CMS_USER_KEY]) && !isset($CMS_LOGIN_PAGE) ) {
          $url = $config['admin_url'];
          $scripts['cms_js_setup'] = array('local'=>$url.'/cms_js_setup.php?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY]);
      }
      $scripts['cms_admin'] = array('local'=>$basePath.'/lib/jquery/js/jquery.cms_admin.min.js');
      $scripts['cms_dirtyform'] = array('local'=>$basePath.'/lib/jquery/js/jquery.cmsms_dirtyform.js');
      $scripts['cms_lock'] = array('local'=>$basePath.'/lib/jquery/js/jquery.cmsms_lock.js');
      $scripts['cms_hiersel'] = array('local'=>$basePath.'/lib/jquery/js/jquery.cmsms_hierselector.js');
      $scripts['cms_autorefresh'] = array('local'=>$basePath.'/lib/jquery/js/jquery.cmsms_autorefresh.js');
      $scripts['ui_touch_punch'] = array('local'=>$basePath.'/lib/jquery/js/jquery.ui.touch-punch.min.js');
      $scripts['cms_filepicker'] = [ 'local'=>$basePath.'/lib/jquery/js/jquery.cmsms_filepicker.js' ];
  }

  // Check if we need to exclude some script
  if(!empty($exclude)) {
      $exclude_list = explode(",", trim(str_replace(' ','',$exclude)));
      foreach($exclude_list as $one) {
          $one = trim(strtolower($one));

          // find a match
          $found = null;
          foreach( $scripts as $key => $rec ) {
              if( strtolower($one) == strtolower($key) ) {
                  $found = $key;
                  break;
              }
              if( isset($rec['aliases']) && is_array($rec['aliases']) ) {
                  foreach( $rec['aliases'] as $alias ) {
                      if( strtolower($one) == strtolower($alias) ) {
                          $found = $key;
                          break;
                      }
                  }
                  if( $found ) break;
              }
          }

          if( $found ) unset($scripts[$found]);
      }
  }

  // let them add scripts to the end ie: a jQuery plugin
  if(!empty($append)) {
      $append_list = explode(",", trim(str_replace(' ','',$append)));
      foreach($append_list as $key => $item) {
          $scripts['user_'.$key] = array('local'=>$item);
      }
  }

  // Output
  $output = '';
  $fmt_js = '<script type="text/javascript" src="%s"></script>';
  $fmt_css = '<link rel="stylesheet" type="text/css" href="%s"/>';
  foreach($scripts as $script) {
      $url_js = $script['local'];
      if( $cdn && isset($script['cdn']) ) $url_js = $script['cdn'];
      $output .= sprintf($fmt_js,$url_js)."\n";
      if( isset($script['css']) && $script['css'] != '' ) {
          $url_css = $script['css'];
          if( $cdn && isset($script['css_cdn']) ) $url_css = $script['css_cdn'];
          if( $include_css ) $output .= sprintf($fmt_css,$url_css)."\n";
      }
  }
  return $output;
}

/**
 * @ignore
 * @since 2.0.2
 */
function setup_session(bool $cachable = false)
{
    global $CMS_INSTALL_PAGE, $CMS_ADMIN_PAGE;
    static $_setup_already = false;
    if( $_setup_already ) return;

    $_f = $_l = null;
    if( headers_sent( $_f, $_l) ) throw new \LogicException("Attempt to set headers, but headers were already sent at: $_f::$_l");

    if( $cachable ) {
        if( $_SERVER['REQUEST_METHOD'] != 'GET' || isset($CMS_ADMIN_PAGE) || isset($CMS_INSTALL_PAGE) ) $cachable = false;
    }
    if( $cachable ) $cachable = (int) cms_siteprefs::get('allow_browser_cache',0);
    if( !$cachable ) {
        // admin pages can't be cached... period, at all.. never.
        @session_cache_limiter('nocache');
    }
    else {
        // frontend request
        $expiry = (int)max(0,cms_siteprefs::get('browser_cache_expiry',60));
        session_cache_expire($expiry);
        session_cache_limiter('public');
        @header_remove('Last-Modified');
    }

    #Setup session with different id and start it
    $session_name = 'CMSSESSID'.substr(md5(__DIR__.CMS_VERSION), 0, 12);
    if( !isset($CMS_INSTALL_PAGE) ) {
        @session_name($session_name);
        @ini_set('url_rewriter.tags', '');
        @ini_set('session.use_trans_sid', 0);
    }

    if( isset($_COOKIE[$session_name]) ) {
        // validate the contents of the cookie.
        if (!preg_match('/^[a-zA-Z0-9,\-]{22,40}$/', $_COOKIE[$session_name]) ) {
            session_id( uniqid() );
            session_start();
            session_regenerate_id();
        }
    }
    if(!@session_id()) session_start();
    $_setup_already = true;
}

/**
 * Test if a string is a base64 encoded string
 *
 * @since 2.2
 * @param string $s The input string
 * @return bool
 */
function is_base64(string $s) : bool
{
    return (bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s);
}

/**
 * Create a unique GUID.
 *
 * @since 2.3
 * @return string
 */
function cms_create_guid() : string
{
    if (function_exists('com_create_guid') === true) return trim(com_create_guid(), '{}');
    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}
