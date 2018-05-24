<?php
#FileManager module utilities class
#Copyright (C) 2006-2018 Morten Poulsen <morten@poulsen.org>
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

namespace FileManager;

use cms_config;
use cms_siteprefs;
use cms_userprefs;
use cms_utils;
use CmsApp;
use Exception;
use finfo;
use const CMS_ROOT_PATH;
use function cms_relative_path;
use function cmsms;
use function endswith;
use function startswith;

final class filemanager_utils
{
    static private $_can_do_advanced = -1;

    protected function __construct() {}

    public static function is_valid_filename($name)
    {
        if( $name == '' ) return FALSE;
        if( strpos($name,'/') !== false ) return FALSE;
        if( strpos($name,'\\') !== false ) return FALSE;
        if( strpos($name,'..') !== false ) return FALSE;
        if( $name[0] == '.' || $name[0] == ' ' ) return FALSE;
        $ext = strtolower(substr(strrchr($name, '.'), 1));
        if( startswith($ext,'php') || endswith($ext,'php') ) return FALSE;
        if( preg_match('/[\n\r\t\[\]\&\?\<\>\!\@\#\$\%\*\(\)\{\}\|\"\'\:\;\+]/',$name) ) {
            return FALSE;
        }
        return TRUE;
    }

    public static function can_do_advanced()
    {
        if( self::$_can_do_advanced < 0 ) {
            $filemod = cms_utils::get_module('FileManager');
            $config = cms_config::get_instance();
            if( startswith($config['uploads_path'],$config['root_path']) && $filemod->AdvancedAccessAllowed() ) {
                self::$_can_do_advanced = 1;
            }
            else {
                self::$_can_do_advanced = 0;
            }
        }
        return self::$_can_do_advanced;
    }

    public static function check_advanced_mode()
    {
        $filemod = cms_utils::get_module('FileManager');
        $a = self::can_do_advanced();
        $b = $filemod->GetPreference('advancedmode',0);
        return ( $a && $b );
    }

    public static function get_default_cwd()
    {
        $advancedmode = self::check_advanced_mode();
        $dir = cms_config::get_instance()['uploads_path'];
        if( !startswith($dir,CMS_ROOT_PATH) ) $dir = self::join_path(CMS_ROOT_PATH, 'uploads');
        if( $advancedmode ) $dir = CMS_ROOT_PATH;

        $dir = cms_relative_path( $dir, CMS_ROOT_PATH );
        return $dir;
    }

    public static function test_valid_path($path)
    {
        // returns false if invalid.
        $config = cms_config::get_instance();
        $advancedmode = self::check_advanced_mode();

        $prefix = CMS_ROOT_PATH;
        if( $path === '/' ) $path = null;
        $path = self::join_path($prefix,$path);
        $rpath = realpath($path);
        if( $rpath === FALSE ) return false;

        if (!$advancedmode) {
            // uploading in 'non advanced mode', path has to start with the upload dir.
            $uprp = realpath($config['uploads_path']);
            if (startswith($rpath,$uprp)) return true;
        }
        else {
            // advanced mode, path has to start with the root path.
            $rprp = realpath($config['root_path']);
            if (startswith($path,$rprp)) return true;
        }
        return false;
    }

    /**
     * @return string A relative path BUT with leading DIRECTORY_SEPARATOR!
     */
    public static function get_cwd()
    {
        // check the path
        $path = cms_userprefs::get('filemanager_cwd',self::get_default_cwd());
        if( !self::test_valid_path($path) ) {
            $path = self::get_default_cwd();
        }
        if( $path == '' ) $path = '/';
        return $path;
    }

    public static function set_cwd($path)
    {
        if( startswith($path,CMS_ROOT_PATH) ) $path = cms_relative_path($path,CMS_ROOT_PATH);
        $advancedmode = self::check_advanced_mode();

        // validate the path.
        $tmp = self::join_path(CMS_ROOT_PATH,$path);
        $tmp = realpath($tmp);
        if( !$tmp || !is_dir($tmp) ) throw new Exception('Cannot set current working directory to an invalid path');
        $newpath = cms_relative_path($tmp,CMS_ROOT_PATH);
        if( !self::test_valid_path($newpath) ) throw new Exception('Cannot set current working directory to an invalid path');

        $newpath = str_replace('\\','/',$newpath);
        cms_userprefs::set('filemanager_cwd',$newpath);
    }


    public static function join_path(...$args)
    {
        if( count($args) < 1 ) return;
        if( count($args) < 2 ) return $args[0];

        $args2 = [];
        for( $i = 0; $i < count($args); $i++ ) {
            if( $args[$i] == '' ) continue;
            if( $i != 0 && (startswith($args[$i],'/') || startswith($args[$i],'\\')) ) $args[$i] = substr($args[$i],1);
            if( endswith($args[$i],'/') || endswith($args[$i],'\\') ) $args[$i] = substr($args[$i],0,-1);
            $args2[] = $args[$i];
        }

        return implode(DIRECTORY_SEPARATOR,$args2);
    }

    public static function get_full_cwd()
    {
        $path = self::get_cwd();
        if( !self::test_valid_path($path) ) $path = self::get_default_cwd();
        $base = cms_config::get_instance()['root_path'];
        $realpath = self::join_path($base,$path);
        return $realpath;
    }

    public static function get_cwd_url()
    {
        $path = self::get_cwd();
        if( !self::test_valid_path($path) ) $path = self::get_default_cwd();
        $url = cms_config::get_instance()['root_url'].'/'. str_replace('\\','/',$path);
        return $url;
    }

    public static function is_image_file($file)
    {
        // it'd be nice to check mime type here.
        $ext = substr(strrchr($file, '.'), 1);
        if( !$ext ) return FALSE;

        $tmp = array('gif','jpg','jpeg','png');
        if( in_array(strtolower($ext),$tmp) ) return TRUE;
        return FALSE;
    }

    public static function is_archive_file($file)
    {
        $tmp = array('.tar.gz','.tar.bz2','.zip','.tgz');
        foreach( $tmp as $t2 ) {
            if( endswith(strtolower($file),$t2) ) return TRUE;
        }
        return FALSE;
    }

    /**
     * @deprecated since 1.7 use FilePicker\Utils::get_file_list() instead
     */
    public static function get_file_list($path = '')
    {
        return FilePicker\Utils::get_file_list($path);
    }

    /**
     * @since 1.7
     */
    public static function get_file_details($data)
    {
        if( !empty($data['image']) ) {
            $imginfo = @getimagesize($data['fullpath']);
            if( $imginfo ) {
                $t = $imginfo[0].' x '.$imginfo[1];
                if( isset($imginfo['bits']) ) {
                    $t .= ' x '.$imginfo['bits'];
                }
                return $t;
            }
        }
        return '';
    }

    public static function mime_content_type($filename)
    {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME);
            if ($finfo) {
                $mime_type = finfo_file($finfo,$filename);
                return $mime_type;
            }
        }
        // Revert to check some file-extensions
        $mime_types = [
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        ];

        $ext = explode('.',$filename);
        $ext = strtolower(array_pop($ext));
        if (array_key_exists($ext, $mime_types)) {
             return $mime_types[$ext];
        }
        //Nothing instead of "application/octet-stream"
        return '';
    }

    // get post max size and give a portion of it to smarty for max chunk size.
    public static function str_to_bytes($val)
    {
        if(is_string($val) && $val != '') {
            $val = trim($val);
            $last = strtolower($val[strlen($val)-1]);
            if( $last < '<' || $last > 9 ) $val = substr($val,0,-1);
            $val = (int) $val;
            switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
            }
        }

        return (int) $val;
    }

    private static function get_dirs($startdir,$prefix = DIRECTORY_SEPARATOR)
    {
        if( !is_dir($startdir) ) return;

        global $showhiddenfiles;
        $res = [];
        $dh = @opendir($startdir);
        if ($dh) {
            while( ($entry = readdir($dh)) !== false ) {
                if( $entry == '.' ) continue;
                $full = self::join_path($startdir,$entry);
                if( !is_dir($full) ) continue;
                if( !$showhiddenfiles && ($entry[0] == '.' || $entry[0] == '_') ) continue;

                if( $entry == '.svn' || $entry == '.git' ) continue;
                $res[$prefix.$entry] = $prefix.$entry;
                $tmp = self::get_dirs($full,$prefix.$entry.DIRECTORY_SEPARATOR);
                if( is_array($tmp) && count($tmp) ) $res = array_merge($res,$tmp);
            }
            closedir($dh);
        }
        return $res;
    }

    public static function get_dirlist()
    {
        $config = cms_config::get_instance();
        $mod = cms_utils::get_module('FileManager');
        $showhiddenfiles = $mod->GetPreference('showhiddenfiles');
        $startdir = $config['uploads_path'];
        $advancedmode = self::check_advanced_mode();
        if( $advancedmode ) $startdir = $config['root_path'];

        // now get a simple list of all of the directories we have 'write' access to.
        $output = self::get_dirs($startdir, DIRECTORY_SEPARATOR);
        if( is_array($output) && count($output) ) {
            ksort($output);
            $tmp = [];
            if( $advancedmode ) {
                $tmp[DIRECTORY_SEPARATOR] = DIRECTORY_SEPARATOR.basename($startdir).' ('.$mod->Lang('site_root').')';
            }
            else {
                $tmp[DIRECTORY_SEPARATOR] = DIRECTORY_SEPARATOR.basename($startdir).' ('.$mod->Lang('top').')';
            }
            $output = array_merge($tmp,$output);
        }
        return $output;
    }

    public static function create_thumbnail($src,$dest = null)
    {
        if( !file_exists($src) ) return FALSE;
        if( !$dest ) {
            $bn = basename($src);
            $dn = dirname($src);
            $dest = $dn.DIRECTORY_SEPARATOR.'thumb_'.$bn;
        }
        if( file_exists($dest) && !is_writable($dest) ) return FALSE;

        $info = getimagesize($src);
        if( !$info || !isset($info['mime']) ) return FALSE;

        $i_src = imagecreatefromstring(file_get_contents($src));
        $width = cms_siteprefs::get('thumbnail_width',96);
        $height = cms_siteprefs::get('thumbnail_height',96);

        $i_dest = imagecreatetruecolor($width,$height);
        imagealphablending($i_dest,FALSE);
        $color = imageColorAllocateAlpha($i_src, 255, 255, 255, 127);
        imagecolortransparent($i_dest,$color);
        imagefill($i_dest,0,0,$color);
        imagesavealpha($i_dest,TRUE);
        imagecopyresampled($i_dest,$i_src,0,0,0,0,$width,$height,imagesx($i_src),imagesy($i_src));

        $res = null;
        switch( $info['mime'] ) {
        case 'image/gif':
            $res = imagegif($i_dest,$dest);
            break;
        case 'image/png':
            $res = imagepng($i_dest,$dest,9);
            break;
        case 'image/jpeg':
            $res = imagejpeg($i_dest,$dest,100);
            break;
        }

        if( !$res ) return FALSE;
        return TRUE;
    }

    public static function format_filesize($_size) {
        $mod = cms_utils::get_module('FileManager');
        $unit = $mod->Lang("bytes");
        $size = $_size;

        if ($size>10000 && $size<(1024*1024)) {
            $size = round($size/1024);
            $unit = $mod->Lang("kb");
        }

        if ($size>(1024*1024)) {
            $size = round($size/(1024*1024),1);
            $unit = $mod->Lang("mb");
        }

        $lcc = localeconv();
        $size = number_format($size,0,$lcc['decimal_point'],$lcc['thousands_sep']);

        $result = [];
        $result["size"] = $size;
        $result["unit"] = $unit;
        return $result;
    }

    public static function format_permissions($mode,$style = 'xxx') {
        switch ($style) {
        case 'xxx':
            $owner = 0;
            if ($mode & 0400) $owner += 4;
            if ($mode & 0200) $owner += 2;
            if ($mode & 0100) $owner++;
            $group = 0;
            if ($mode & 0040) $group +=4;
            if ($mode & 0020) $group +=2;
            if ($mode & 0010) $group++;
            $others = 0;
            if ($mode & 0004) $others +=4;
            if ($mode & 0002) $others +=2;
            if ($mode & 0001) $others++;
            return $owner.$group.$others;

        case 'xxxxxxxxx':
            $owner = "";
            if ($mode & 0400) $owner.="r"; else $owner.="-";
            if ($mode & 0200) $owner.="w"; else $owner.="-";
            if ($mode & 0100) $owner.="x"; else $owner.="-";
            $group = "";
            if ($mode & 0040) $group.="r"; else $group.="-";
            if ($mode & 0020) $group.="w"; else $group.="-";
            if ($mode & 0010) $group.="x"; else $group.="-";
            $others = "";
            if ($mode & 0004) $others.="r"; else $others.="-";
            if ($mode & 0002) $others.="w"; else $others.="-";
            if ($mode & 0001) $others.="x"; else $others.="-";
            return $owner.$group.$others;
        }
    }
} // class

