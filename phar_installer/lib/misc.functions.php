<?php

namespace cms_installer {

use cms_installer\cms_smarty;
use cms_installer\installer_base;
use cms_installer\langtools;
use cms_installer\nlstools;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

static $_writable_error = [];

/**
 *
 * @param string $to URL
 */
function redirect(string $to)
{
	$_SERVER['PHP_SELF'] = null;
	//TODO generally support the websocket protocol
	$schema = $_SERVER['SERVER_PORT'] == '443' ? 'https' : 'http';
	$host = strlen($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:$_SERVER['SERVER_NAME'];

	$components = parse_url($to);
	if (count($components) > 0) {
		$to =  (isset($components['scheme']) && startswith($components['scheme'], 'http') ? $components['scheme'] : $schema) . '://';
		$to .= isset($components['host']) ? $components['host'] : $host;
		$to .= isset($components['port']) ? ':' . $components['port'] : '';
		if(isset($components['path'])) {
			if(in_array(substr($components['path'],0,1),['\\','/'])) { //Path is absolute, just append.
				$to .= $components['path'];
			}
			//Path is relative, append current directory first.
			else if (isset($_SERVER['PHP_SELF']) && !is_null($_SERVER['PHP_SELF'])) { //Apache
				$to .= (strlen(dirname($_SERVER['PHP_SELF'])) > 1 ?  dirname($_SERVER['PHP_SELF']).'/' : '/') . $components['path'];
			}
			else if (isset($_SERVER['REQUEST_URI']) && !is_null($_SERVER['REQUEST_URI'])) { //Lighttpd
				if (endswith($_SERVER['REQUEST_URI'], '/'))
					$to .= (strlen($_SERVER['REQUEST_URI']) > 1 ? $_SERVER['REQUEST_URI'] : '/') . $components['path'];
				else
					$to .= (strlen(dirname($_SERVER['REQUEST_URI'])) > 1 ? dirname($_SERVER['REQUEST_URI']).'/' : '/') . $components['path'];
			}
		}
		else {
			$to .= $_SERVER['REQUEST_URI'];
		}
		$to .= isset($components['query']) ? '?' . $components['query'] : '';
		$to .= isset($components['fragment']) ? '#' . $components['fragment'] : '';
	}
	else {
		$to = $schema.'://'.$host.'/'.$to;
	}

	session_write_close();

	if( headers_sent() ) {
		// use javascript instead
		echo '<script type="text/javascript"><!-- location.replace("'.$to.'"); // --></script><noscript><meta http-equiv="Refresh" content="0;URL='.$to.'"></noscript>';
		exit;
	}
	else {
		header("Location: $to");
		exit;
	}
}

/**
 * @return installer_base object
 */
function get_app()
{
	return installer_base::get_instance();
}

/**
 * @return cms_smarty object, a Smarty subclass
 */
function smarty()
{
	return cms_smarty::get_instance();
}

/**
 * @return nlstools object
 */
function nls()
{
	return new nlstools();
}

/**
 * @return langtools object
 */
function translator()
{
	return langtools::get_instance();
}

function startswith(string $haystack, string $needle) : bool
{
	return (strncmp($haystack,$needle,strlen($needle)) == 0);
}

function endswith(string $haystack, string $needle) : bool
{
	$o = strlen( $needle );
	if( $o > 0 && $o <= strlen($haystack) ) {
		return strpos($haystack, $needle, -$o) !== false;
	}
	return false;
}

function joinpath(string ...$args) : string
{
	if (is_array($args[0])) {
		$args = $args[0];
	}
	$path = implode(DIRECTORY_SEPARATOR, $args);
	return str_replace(['\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR],
		 [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $path);
}

function lang(...$args)
{
	try {
		return langtools::get_instance()->translate(...$args);
	}
	catch( Throwable $t ) {
		return '';
	}
}

/**
 *
 * @param mixed $in
 * @param bool $strict Default false
 * @return mixed bool or null
 */
function to_bool($in, bool $strict = false)
{
	$in = strtolower((string) $in);
	if( in_array($in,['1','y','yes','true','t','on']) ) return true;
	if( in_array($in,['0','n','no','false','f','off']) ) return false;
	return ( $strict ) ? null : ($in != false);
}

/**
 *
 * @param string $str
 * @return bool
 */
function is_email(string $str) : bool
{
	return filter_var($str,FILTER_VALIDATE_EMAIL) !== false;
}

/**
 *
 * @param mixed $val
 * @return mixed
 */
function clean_string($val)
{
	if( !$val ) return $val;
	$val = filter_var($val.'', FILTER_SANITIZE_STRING,
		FILTER_FLAG_NO_ENCODE_QUOTES |
		FILTER_FLAG_STRIP_LOW |
		FILTER_FLAG_STRIP_BACKTICK);
	return strip_tags($val);
}

/**
 *
 * @return string
 * @throws Exception
 */
function get_sys_tmpdir() : string
{
	if( function_exists('sys_get_temp_dir') ) {
		$tmp = rtrim(sys_get_temp_dir(),'\\/');
		if( $tmp && @is_dir($tmp) && @is_writable($tmp) ) return $tmp;
	}

	$vars = ['TMP','TMPDIR','TEMP'];
	foreach( $vars as $var ) {
		if( isset($_ENV[$var]) && $_ENV[$var] ) {
			$tmp = realpath($_ENV[$var]);
			if( $tmp && @is_dir($tmp) && @is_writable($tmp) ) return $tmp;
		}
	}

	$tmpdir = ini_get('upload_tmp_dir');
	if( $tmpdir && @is_dir($tmpdir) && @is_writable($tmpdir) ) return $tmpdir;

	if( ini_get('safe_mode') != '1' ) {
		// last ditch effort to find a place to write to.
		$tmp = @tempnam('','xxx');
		if( $tmp && is_file($tmp) ) {
			@unlink($tmp);
			return realpath(dirname($tmp));
		}
	}

	throw new Exception('Could not find a writable location for temporary files');
}

/**
 * Recursively check whether a directory and all its contents are modifiable.
 *
 * @param  string  $path Start directory.
 * @param  bool    $ignore_specialfiles  Optionally ignore special system
 *  files in the scan. Such files include:
 *    files beginning with '.'
 *    php.ini files
 * @return bool
 */
function is_directory_writable( string $path, bool $ignore_specialfiles = true ) : bool
{
	if( substr ( $path, strlen ( $path ) - 1 ) != '/' ) $path .= '/' ;

	$result = true;
	if( $handle = @opendir( $path ) ) {
		while( false !== ( $file = readdir( $handle ) ) ) {
			if( $file == '.' || $file == '..' ) continue;

			// ignore dotfiles, except .htaccess.
			if( $ignore_specialfiles ) {
				if( $file[0] == '.' && $file != '.htaccess' ) continue;
				if( $file == 'php.ini' ) continue;
			}

			$p = $path.$file;
			if( !@is_writable( $p ) ) {
				$_writable_error[] = $p;
				@closedir( $handle );
				return false;
			}

			if( @is_dir( $p ) ) {
				$result = is_directory_writable( $p, $ignore_specialfiles );
				if( !$result ) {
					$_writable_error[] = $p;
					@closedir( $handle );
					return false;
				}
			}
		}
		@closedir( $handle );
	}
	else {
		$_writable_error[] = $p;
		return false;
	}

	return true;
}

/**
 * Recursive delete directory
 *
 * @param string $path filepath
 * @param bool $withtop Since 2.9 Optional flag whether to remove $path
 *  itself, as well as all its contents. Default true.
 */
function rrmdir($path, $withtop = true)
{
	if( is_dir($path) ) {
		$items = scandir($path);
		foreach ($items as $name) {
			if( !($name == '.' || $name == '..') ) {
				if( filetype($path.DIRECTORY_SEPARATOR.$name) == 'dir' ) {
					rrmdir($path.DIRECTORY_SEPARATOR.$name); //recurse
				}
				else {
					@unlink($path.DIRECTORY_SEPARATOR.$name);
				}
			}
		}
		if( $withtop ) {
			if( is_link($path) ) {
				return @unlink($path);
			}
			elseif( is_dir($path) ) {
				return @rmdir($path);
			}
			else {
				return false;
			}
		}
		return true;
	}
}

/**
 * Recursive copy directory and all contents
 *
 * @param string $frompath filepath
 * @param string $topath filepath
 * @param bool $dummy whether to create empty 'index.html' file in each folder Default false
 */
function rcopy(string $frompath, string $topath, bool $dummy = false)
{
	$frompath = rtrim($frompath, '/\\');
	if (!is_dir($frompath) || !is_readable($frompath)) return;
	$topath = rtrim($topath, '/\\');
	if (!is_dir($topath) || !is_writable($topath)) return;

	$len = strlen($frompath);
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator(
			$frompath,
			FilesystemIterator::CURRENT_AS_PATHNAME |
			FilesystemIterator::SKIP_DOTS //|
//              FilesystemIterator::UNIX_PATHS //|
//              FilesystemIterator::FOLLOW_SYMLINKS too bad if links not relative !!
		),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iter as $fp) {
		$relpath = substr($fp, $len);
		$tp = $topath . DIRECTORY_SEPARATOR . $relpath;
		if (!is_link($fp)) {
			if (!is_dir($fp)) {
				copy($fp, $tp);
				chmod($tp, 0640);
			} else {
				mkdir($tp, 0770, true);
				if ($dummy) { touch($tp . DIRECTORY_SEPARATOR . 'index.html'); }
			}
		} else {
			copy($fp, $tp);
			//TODO re-target the link
			if (!is_dir($fp)) {
				chmod($tp, 0640);
			} else {
				chmod($tp, 0770);
			}
		}
	}
}

/**
 *
 * @return array, maybe empty
 */
function get_writable_error() : array
{
	return $_writable_error;
}

/**
 * Get list of versions we can upgrade from.
 *
 * @return array
 * @throws Exception
 */
function get_upgrade_versions() : array
{
	$app = get_app();
	$app_config = $app->get_config();
	$min_upgrade_version = $app_config['min_upgrade_version'];
	if( !$min_upgrade_version ) throw new Exception(lang('error_invalidconfig'));

	$dir = dirname(__DIR__).DIRECTORY_SEPARATOR.'upgrade';
	if( !is_dir($dir) ) throw new Exception(lang('error_internal','u100'));

	$dh = opendir($dir);
	if( !$dh ) throw new Exception(lang('error_internal','u102'));
	$versions = [];
	while( ($file = readdir($dh)) !== false ) {
		if( $file == '.' || $file == '..' ) continue;
		if( is_dir($dir.DIRECTORY_SEPARATOR.$file) &&
			(is_file("$dir/$file/MANIFEST.DAT.gz") || is_file("$dir/$file/MANIFEST.DAT") || is_file("$dir/$file/upgrade.php")) ) {
			if( version_compare($min_upgrade_version, $file) <= 0 ) $versions[] = $file;
		}
	}
	closedir($dh);
	if( $versions ) {
		usort($versions,'version_compare');
		return $versions;
	}
	return [];
}

/**
 * It is not an error to not have a changelog file
 * @param string $version
 * @return string
 * @throws Exception
 */
function get_upgrade_changelog(string $version) : string
{
	$dir = dirname(__DIR__).'/upgrade/'.$version;
	if( !is_dir($dir) ) throw new Exception(lang('error_internal','u103'));
	$files = ['CHANGELOG.txt','CHANGELOG.TXT','changelog.txt'];
	foreach( $files as $fn ) {
		if( is_file("$dir/$fn") ) {
			// convert text into some sort of html
			$tmp = @file_get_contents("$dir/$fn");
			$tmp = nl2br(wordwrap(htmlspecialchars($tmp),80));
			return $tmp;
		}
	}
	return '';
}

/**
 * It is not an error to not have a readme file
 * @param type $version
 * @return string
 * @throws Exception
 */
function get_upgrade_readme(string $version) : string
{
	$dir = dirname(__DIR__).'/upgrade/'.$version;
	if( !is_dir($dir) ) throw new Exception(lang('error_internal','u104'));
	$files = ['README.HTML.INC','readme.html.inc','README.HTML','readme.html'];
	foreach( $files as $fn ) {
		if( is_file("$dir/$fn") ) return @file_get_contents("$dir/$fn");
	}
	if( is_file("$dir/readme.txt") ) {
		// convert text into some sort of html.
		$tmp = @file_get_contents("$dir/readme.txt");
		$tmp = nl2br(wordwrap(htmlspecialchars($tmp),80));
		return $tmp;
	}
	return '';
}

} //cms_installer namespace

namespace {

use cms_installer\wizard\wizard;

// functions to generate GUI-installer messages

function verbose_msg(string $str)
{
	$obj = wizard::get_instance()->get_step();
	if( method_exists($obj,'verbose') ) $obj->verbose($str);
}

function status_msg(string $str)
{
	$obj = wizard::get_instance()->get_step();
	if( method_exists($obj,'message') ) $obj->message($str);
}

function error_msg(string $str)
{
	$obj = wizard::get_instance()->get_step();
	if( method_exists($obj,'error') ) $obj->error($str);
}

} //global namespace
