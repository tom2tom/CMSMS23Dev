<?php
# FilePicker - a CMSMS module which provides file-related services for other modules
# Copyright (C) 2016 Fernando Morgado <jomorg@cmsmadesimple.org>
# Copyright (C) 2016-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
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

use CMSMS\ContentBase;
use CMSMS\FilePickerIFace;
use CMSMS\FilePickerProfile;
use CMSMS\FileType;
use CMSMS\FileTypeHelper;
use FilePicker\ProfileDAO;
use FilePicker\TemporaryProfileStorage;
use FilePicker\Utils;

require_once(__DIR__.'/lib/class.ProfileDAO.php');

final class FilePicker extends CMSModule implements FilePickerIFace
{
    protected $_dao;
    protected $_typehelper;

    public function __construct()
    {
        parent::__construct();
        $this->_dao = new ProfileDAO( $this );
        $this->_typehelper = new FileTypeHelper( cms_config::get_instance() );
    }

    private function _encodefilename($filename)
    {
        return str_replace('==', '', base64_encode($filename));
    }

    private function _decodefilename($encodedfilename)
    {
        return base64_decode($encodedfilename . '==');
    }

    private function _GetTemplateObject()
    {
        $ret = $this->GetActionTemplateObject();
        if( is_object($ret) ) return $ret;
        return CmsApp::get_instance()->GetSmarty();
    }
    /*
     * end of private methods
     */

    public function GetAdminSection() { return 'extensions'; }
    public function GetFriendlyName() { return $this->Lang('friendlyname');  }
    public function GetHelp() { return $this->Lang('help'); }
    public function GetVersion() { return '2.0'; }
    public function HasAdmin() { return TRUE; }
    public function VisibleToAdminUser() { return $this->CheckPermission('Modify Site Preferences'); }

    public function HasCapability( $capability, $params = [] )
    {
        switch( $capability ) {
        case 'contentblocks':
        case 'filepicker':
        case 'upload':
            return TRUE;
        default:
            return FALSE;
        }
    }

    /**
     * Generate page-header js. For use by relevant module actions.
     * Include after jQuery and core js.
     * @since 2.3
     * @return string
     */
    protected function HeaderJsContent() : string
    {
        $url = str_replace('&amp;','&',$this->get_browser_url());
        $url2 = $this->GetModuleURLPath();
        $msg = $this->Lang('select_file');
        $out = <<<EOS
<script type="text/javascript">
//<![CDATA[
 cms_data.lang_select_file = '$msg';
 cms_data.filepicker_url = '{$url}&cmsjobtype=1';
//]]>
</script>
<script type="text/javascript" src="{$url2}/lib/js/jquery.cmsms_filepicker.js"></script>
EOS;
        return $out;
    }

    public function GetContentBlockFieldInput($blockName, $value, $params, $adding, ContentBase $content_obj)
    {
        if( empty($blockName) ) return FALSE;
        $uid = get_userid(FALSE);
        //$adding = (bool)( $adding || ($content_obj->Id() < 1) ); // hack for the core. Have to ask why though (JM)

        $profile_name = get_parameter_value($params,'profile');
        $profile = $this->get_profile_or_default($profile_name);

        // todo: optionally allow further overriding the profile
        $out = $this->get_html($blockName, $value, $profile);
        return $out;
    }
/*
    function ValidateContentBlockFieldValue($blockName,$value,$blockparams,ContentBase $content_obj)
    {
        echo('<br />:::::::::::::::::::::<br />');
        debug_display($blockName, '$blockName');
        debug_display($value, '$value');
        debug_display($blockparams, '$blockparams');
        //debug_display($adding, '$adding');
        echo('<br />' . __FILE__ . ' : (' . __CLASS__ . ' :: ' . __FUNCTION__ . ') : ' . __LINE__ . '<br />');
        //die('<br />RIP!<br />');
    }
*/
    public function GetFileList($path = '')
    {
        return Utils::get_file_list($path);
    }

    public function get_profile_or_default( $profile_name, $dir = null, $uid = null )
    {
        $profile_name = trim($profile_name);
        if( $profile_name ) $profile = $this->_dao->loadByName( $profile_name );
		else $profile = null;
        if( !$profile ) {
			$profile = $this->get_default_profile( $dir, $uid );
		}
        return $profile;
    }

    public function get_default_profile( $dir = null, $uid = null )
    {
        /* $dir is absolute */
        $profile = $this->_dao->loadDefault();
        if( !$profile ) {
			$profile = new FilePickerProfile();
		}
        return $profile;
    }

    public function get_browser_url()
    {
        return $this->create_url('m1_','filepicker');
    }

    public function get_html( $name, $value, $profile, $required = false )
    {
		static $first_time = true;

        $_instance = 'i'.uniqid();
        if( $value === '-1' ) $value = null;

        // store the profile as a 'useonce' and add its signature to the params on the url
        $sig = TemporaryProfileStorage::set( $profile );

        switch( $profile->type ) {
        case FileType::IMAGE:
            $key = 'select_an_image';
            break;
        case FileType::AUDIO:
            $key = 'select_an_audio_file';
            break;
        case FileType::VIDEO:
            $key = 'select_a_video_file';
            break;
        case FileType::MEDIA:
            $key = 'select_a_media_file';
            break;
        case FileType::XML:
            $key = 'select_an_xml_file';
            break;
        case FileType::DOCUMENT:
            $key = 'select_a_document';
            break;
        case FileType::ARCHIVE:
            $key = 'select_an_archive_file';
            break;
//        case FileType::ANY:
        default:
            $key = 'select_a_file';
            break;
        }
		$title = $this->Lang($key);
		$req = ( $required ) ? 'true':'false';
		$s1 = $this->Lang('clear');

		if( $first_time ) {
			$first_time = false;
			$js = '<script type="text/javascript" src="'.$this->GetModuleURLPath().'/lib/js/jquery.cmsms_filepicker.js"></script>'."\n";
		}
		else {
			$js = '';
		}

		$js .= <<<EOS
<script type="text/javascript">
//<![CDATA[
$(document).ready(function() {
 $('input[data-cmsfp-instance="$_instance"]').filepicker({
  title: '$title',
  param_sig: '$sig',
  required: $req,
  remove_label: '$s1',
  remove_title: '$s1'
 });
});
//]]>
</script>

EOS;
		$this->AdminBottomContent($js); //CHECKME always admin?

        $smarty = CmsApp::get_instance()->GetSmarty();
        $tpl_ob = $smarty->CreateTemplate($this->GetTemplateResource('contentblock.tpl'),null,null,$smarty);
        $tpl_ob->assign('blockName',$name)
         ->assign('value',$value)
         ->assign('instance',$_instance);
        $out = $tpl_ob->fetch();
        return $out;
    }

    // INTERNAL UTILITY FUNCTION
    public function is_image( $filespec )
    {
        $filespec = trim($filespec);
        if( !$filespec ) return;

        return $this->_typehelper->is_image( $filespec );
    }

    // INTERNAL UTILITY FUNCTION
    public function is_acceptable_filename( $profile, $filename )
    {
        $filename = trim($filename);
        $filename = basename($filename);  // in case it's a path
        if( !$filename ) return FALSE;
        if( !$profile->show_hidden && (startswith($filename,'.') || startswith($filename,'_') || $filename == 'index.html') ) return FALSE;
        if( $profile->match_prefix && !startswith( $filename, $profile->match_prefix) ) return FALSE;
        if( $profile->exclude_prefix && startswith( $filename, $profile->exclude_prefix) ) return FALSE;

        switch( $profile->type ) {
        case FileType::IMAGE:
            return $this->_typehelper->is_image( $filename );

        case FileType::AUDIO:
            return $this->_typehelper->is_audio( $filename );

        case FileType::VIDEO:
            return $this->_typehelper->is_video( $filename );

        case FileType::MEDIA:
            return $this->_typehelper->is_media( $filename);

        case FileType::XML:
            return $this->_typehelper->is_xml( $filename);

        case FileType::DOCUMENT:
            return $this->_typehelper->is_document( $filename);

        case FileType::ARCHIVE:
            return $this->_typehelper->is_archive( $filename );
        }

        // passed
        return TRUE;
    }
} // class
