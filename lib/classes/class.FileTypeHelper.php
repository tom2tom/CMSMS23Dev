<?php
#File identification class
#Copyright (C) 2016-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
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

use cms_config;
use function mime_content_type;
use function startswith;

/**
 * A class to identify file type.
 *
 * @package CMS
 * @license GPL
 * @author Robert Campbell <calguy1000@cmsmadesimple.org>
 * @since  2.2
 */
class FileTypeHelper
{
    /**
     * @ignore
     */
    private $_finfo;

    /**
     * @ignore
     */
    private $_use_mimetype;
    /**
     * @ignore
     */
    private $_config;
    /**
     * @ignore
     */
    private $_image_extensions = [
        'ai',
        'bmp',
        'eps',
        'fla',
        'gif',
        'ico',
        'jp2',
        'jpc',
        'jpeg',
        'jpg',
        'jpx',
        'png',
        'psd',
        'psd',
        'svg',
        'swf',
        'tif',
        'tiff',
        'wbmp',
        'webp',
        'xbm',
    ];
    /**
     * @ignore
     */
    private $_archive_extensions = [
        '7z',
        'gz',
        'rar',
        's7z',
        'tar',
        'xz',
        'z',
        'zip',
    ];
    /**
     * @ignore
     */
    private $_audio_extensions = [
        'aac',
        'ac3',
        'flac',
        'm4a',
        'mka',
        'mp2',
        'mp3',
        'oga',
        'ogg',
        'ra',
        'ram',
        'tds',
        'wav',
        'wm',
        'wma',
    ];
    /**
     * @ignore
     */
    private $_video_extensions = [
        '3gp',
        'asf',
        'avi',
        'f4v',
        'flv',
        'm4v',
        'mkv',
        'mov',
        'mp4',
        'mpeg',
        'mpg',
        'ogm',
        'ogv',
        'rm',
        'swf',
        'webm',
        'wmv',
    ];
    /**
     * @ignore
     */
    private $_xml_extensions = ['xml','rss'];
    /**
     * @ignore
     */
    private $_document_extensions = [
        'doc',
        'docx',
        'odf',
        'odg',
        'odp',
        'ods',
        'odt',
        'pdf',
        'ppt',
        'pptx',
        'text',
        'txt',
        'xls',
        'xlsx',
    ];
    /**
     * @ignore
	 * xml'ish excluded cuz too hard to process in html context
     */
    private $_text_extensions = [
        'txt', 'css', 'ini', 'conf', 'log', 'htaccess', 'passwd', 'ftpquota', 'sql', 'js', 'json', 'sh', 'config',
        'php', 'php4', 'php5', 'phps', 'phtml', 'htm', 'html', 'shtml', 'xhtml',/* 'xml', 'xsl',*/ 'm3u', 'm3u8', 'pls', 'cue',
        'eml', 'msg', 'csv', 'bat', 'twig', 'tpl', 'md', 'gitignore', 'less', 'sass', 'scss', 'c', 'cpp', 'cs', 'py', 'rb', 'pl',
        'map', 'lock', 'dtd',
    ];
    /**
     * These are essentially for editable-file checking, rather than text per se
      * @ignore
     */
    private $_text_mimes = [
//too hard to edit in html context       'application/xml',
        'application/javascript',
        'application/x-javascript',
//ditto       'image/svg+xml',
        'message/rfc822',
    ];

    /**
     * Constructor
     *
     * @param cms_config $config Optional since 2.3
     */
    public function __construct( cms_config $config = null )
    {
        if ($config) {
            $this->update_config_extensions('_image_extensions', $config['FileTypeHelper_image_extensions']);
            $this->update_config_extensions('_audio_extensions', $config['FileTypeHelper_audio_extensions']);
            $this->update_config_extensions('_video_extensions', $config['FileTypeHelper_video_extensions']);
            $this->update_config_extensions('_xml_extensions', $config['FileTypeHelper_xml_extensions']);
            $this->update_config_extensions('_document_extensions', $config['FileTypeHelper_document_extensions']);
            $this->update_config_extensions('_text_extensions', $config['FileTypeHelper_text_extensions']);
        }
    }

    /**
     * @ignore
     */
    public function __destruct()
    {
        if( !empty($this->_finfo) ) {
            finfo_close($this->_finfo);
        }
    }

    /**
     * A utility method to allow overriding the extensions used to identify files of a specific type
     *
     * @param string $member One of (_archive_extensions, _audio_extensions, _video_extensions, _xml_extensions, _document_extensions)
     * @param string $str A comma separated string of extensions for that file type
     */
    protected function update_config_extensions( $member, $str )
    {
        $str = trim($str);
        if( !$str ) return;

        $out = $this->$member;
        $list = explode(',',$str);
        foreach( $list as $one ) {
            $one = strtolower(trim($one));
            if( !$one || in_array($one,$out) ) continue;
            $out[] = $one;
        }
        $this->$member = $out;
    }

    /**
     * Test whether the specified file is readable, and not a directory
     *
     * @param string $filename Fileystem absolute path or include-path-resolvable path
     * @return string real path of the file
     */
    public function is_readable( $filename )
    {
        $real = stream_resolve_include_path($filename); //faster file_exists()
        if( $real && is_readable($real) && is_file($real) ) {
            return $real;
        }
        return FALSE;
    }

    /**
     * Get the extension of the specified file
     *
     * @param string $filename Basename (at least) of a file. Assumes strtolower() works on the extension (ASCII)
     * @return string, lowercase
     */
    public function get_extension( $filename )
    {
        return strtolower(substr($filename,strrpos($filename,'.')+1));
    }

    /**
     * Get the mime type of the specified file
     *
     * @param string $filename Fileystem absolute path or include-path-resolvable path
     * @return string
     */
    public function get_mime_type( $filename )
    {
        if( !isset($this->_finfo) ) {
            if( function_exists('finfo_open') ) {
                $this->_finfo = finfo_open(FILEINFO_MIME_TYPE);
            } else {
                $this->_finfo = null;
            }
        }
        if( $this->_finfo ) {
             return finfo_file($this->_finfo, $filename);
        } elseif( function_exists('mime_content_type') ) {
             return mime_content_type($filename);
        } elseif( !stristr(ini_get('disable_functions'), 'shell_exec')) {
             $file = escapeshellarg($filename);
             $type = shell_exec('file -bi ' . $file);
             if ($type) return $type;
        }
        return '--';
    }

    /**
     * Test whether the specified file is an image.
     * This method will use the mime type if possible, otherwise an extension is used to determine if the file is an image.
     *
     * @param string $filename
     * @return bool
     */
    public function is_image( $filename )
    {
        if( ($filename = $this->is_readable( $filename )) ) {
            $type = $this->get_mime_type( $filename );
            if($type && $type != '--') {
                return startswith( $type, 'image/' );
            }
            // fall back to extension-check
            $ext = $this->get_extension( $filename );
            return in_array( $ext, $this->_image_extensions );
        }
        return FALSE;
    }

    /**
     * Test whether the specified file is a thumbnail
     * This method first tests if the file is an image, and then if it is also a thumbnail.
     *
     * @param string $filename
     * @return bool
     */
    public function is_thumb( $filename )
    {
        $bn = basename( $filename );
        return $this->is_image( $filename ) && startswith($bn,'thumb_');
    }

    /**
     * Using the file extension, test whether the specified file is a known archive.
     *
     * @param string $filename
     * @return bool
     */
    public function is_archive( $filename )
    {
        // extensions only.
        $ext = $this->get_extension( $filename );
        return in_array( $ext, $this->_archive_extensions );
    }

    /**
     * Using mime types if possible, or extensions, test whether the specified file is a known audio file.
     *
     * @param string $filename Fileystem absolute path or include-path-resolvable path
     * @return bool
     */
    public function is_audio( $filename )
    {
        if( ($filename = $this->is_readable( $filename )) ) {
            $type = $this->get_mime_type( $filename );
            if($type && $type != '--') {
                return startswith( $type, 'audio/' );
            }
            $ext = $this->get_extension( $filename );
            return in_array( $ext, $this->_audio_extensions );
        }
        return FALSE;
    }

    /**
     * Using mime types if possible, or extensions, test whether the specified file is a known audio file.
     *
     * @param string $filename Fileystem absolute path or include-path-resolvable path
     * @return bool
     */
    public function is_video( $filename )
    {
        if( ($filename = $this->is_readable( $filename )) ) {
            $type = $this->get_mime_type( $filename );
            if($type && $type != '--') {
                return startswith( $type, 'video/' );
            }
            $ext = $this->get_extension( $filename );
            return in_array( $ext, $this->_video_extensions );
        }
        return FALSE;
    }

    /**
     * Test whether the file name specified is a known media (image, audio, video) file.
     *
     * @param string $filename Fileystem absolute path or include-path-resolvable path
     * @return bool
     */
    public function is_media( $filename )
    {
        if( $this->is_image( $filename ) ) return TRUE;
        if( $this->is_audio( $filename ) ) return TRUE;
        if( $this->is_video( $filename ) ) return TRUE;
        return FALSE;
    }

    /**
     * Test whether the file name specified is a known XML file.
     *
     * @param string $filename Fileystem absolute path or include-path-resolvable path
     * @return bool
     */
    public function is_xml( $filename )
    {
        if( ($filename = $this->is_readable( $filename )) ) {
            $type = $this->get_mime_type( $filename );
			if( $type && ($p = strpos($type, ';')) !== FALSE) {
				$type = trim(substr($type, 0, $p));
			}
            switch( $type ) {
                case 'text/xml';
                case 'application/xml':
                case 'application/rss+xml':
                    return TRUE;
            }
            $ext = $this->get_extension( $filename );
            return in_array( $ext, $this->_video_extensions ); //???
        }
        return FALSE;
    }

    /**
     * Using the file extension, test whether the file name specified is a known text file.
     *
     * @param string $filename At least the basename of a file
     * @return bool
     */
    public function is_document( $filename )
    {
        // extensions only
        $ext = $this->get_extension( $filename );
        return in_array( $ext, $this->_document_extensions );
    }

    /**
     * Using mime type if possible, or extension, test whether the specified fule is (potentially-editable) text.
     *
     * @since 2.3
     * @param string $filename Fileystem absolute path or include-path-resolvable path
     * @return bool
     */
    public function is_text( $filename )
    {
        if( ($filename = $this->is_readable( $filename )) ) {
            $type = $this->get_mime_type( $filename );
            if( startswith($type, 'text/') ) {
                return TRUE;
            }
			if( $type && ($p = strpos($type, ';')) !== FALSE) {
				$type = trim(substr($type, 0, $p));
			}
			if( in_array($type, $this->_text_mimes) ) {
                return TRUE;
			}

            $ext = $this->get_extension( $filename );
             // on a website, pretty much everything without an extension will be some form of editable text
            if( $ext === '' && in_array($ext, $this->_text_extensions) ) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Attempt to find a file type for the given filename.
     *
     * @param string $filename Fileystem absolute path or include-path-resolvable path
     * @return mixed A FileType type constant string describing the file type, if found. Otherwise null
     */
    public function get_file_type( $filename )
    {
        if( $this->is_image( $filename ) ) return FileType::TYPE_IMAGE;
        if( $this->is_audio( $filename ) ) return FileType::TYPE_AUDIO;
        if( $this->is_video( $filename ) ) return FileType::TYPE_VIDEO;
        if( $this->is_xml( $filename ) ) return FileType::TYPE_XML;
        if( $this->is_document( $filename ) ) return FileType::TYPE_DOCUMENT;
        if( $this->is_archive( $filename ) ) return FileType::TYPE_ARCHIVE;
    }
} // class
