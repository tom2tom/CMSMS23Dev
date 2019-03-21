<?php

namespace AdminSearch; //OR DesignManager ?

use cms_utils;
use CmsLayoutTemplate;
use CMSMS\TemplateOperations;
use const CMS_DB_PREFIX;
use const CMS_ROOT_PATH;
use function check_permission;
use function cms_relative_path;
use function cmsms;
use function get_userid;

final class template_slave extends slave
{
    public function get_name()
    {
        $mod = cms_utils::get_module('AdminSearch');
        return $mod->Lang('lbl_template_search');
    }

    public function get_description()
    {
        $mod = cms_utils::get_module('AdminSearch');
        return $mod->Lang('desc_template_search');
    }

    public function check_permission()
    {
        $userid = get_userid();
        return check_permission($userid,'Modify Templates');
    }

    private function check_tpl_match(CmsLayoutTemplate $tpl)
    {
        if( strpos($tpl->get_name(),$this->get_text()) !== FALSE ) return TRUE;
        if( strpos($tpl->get_content(),$this->get_text()) !== FALSE ) return TRUE;
        if( $this->search_descriptions() && strpos($tpl->get_description(),$this->get_text()) !== FALSE ) return TRUE;
        return FALSE;
    }

    private function get_mod()
    {
        static $_mod;
        if( !$_mod ) $_mod = cms_utils::get_module('DesignManager');
        return $_mod;
    }

    private function get_tpl_match_info(CmsLayoutTemplate $tpl)
    {
        $one = $tpl->get_id();
        $intext = $this->get_text();
        $text = '';
        $content = $tpl->get_content();
        $pos = strpos($content,$intext);
        if( $pos !== FALSE ) {
            $start = max(0,$pos - 50);
            $end = min(strlen($content),$pos+50);
            $text = substr($content,$start,$end-$start);
            $text = htmlentities($text);
            $text = str_replace($intext,'<span class="search_oneresult">'.$intext.'</span>',$text);
            $text = str_replace(["\r\n","\r","\n"],[' ',' ',' '],$text);
        }
        $url = $this->get_mod()->create_url( 'm1_','admin_edit_template','', [ 'tpl'=>$one ] );
        $url = str_replace('&amp;','&',$url);
        $title = $tpl->get_name();
        if( $tpl->has_content_file() ) {
            $file = $tpl->get_content_filename();
            $title = $tpl->get_name().' ('.cms_relative_path($file,CMS_ROOT_PATH).')';
        }
        $tmp = [
		 'title'=>$title,
         'description'=>tools::summarize($tpl->get_description()),
         'edit_url'=>$url,
		 'text'=>$text
		];
        return $tmp;
    }

    public function get_matches()
    {
        $db = cmsms()->GetDb();
        $mod = $this->get_mod();
        // get all of the template ids
        $sql = 'SELECT id FROM '.CMS_DB_PREFIX.TemplateOperations::TABLENAME.' ORDER BY name ASC';
        $all_ids = $db->GetCol($sql);
        $output = [];
        if( $all_ids ) {
            $chunks = array_chunk($all_ids,15);
            foreach( $chunks as $chunk ) {
                $tpl_list = TemplateOperations::load_bulk_templates($chunk);
                foreach( $tpl_list as $tpl ) {
                    if( $this->check_tpl_match($tpl) ) $output[] = $this->get_tpl_match_info($tpl);
                }
            }
        }
        return $output;
    }
} // class
