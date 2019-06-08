<?php
# Methods for fetching content blocks
# Copyright (C) 2013-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
# Thanks to Ted Kulp, Robert Campbell and all other contributors from the CMSMS Development Team.
# This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# BUT withOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.

namespace CMSMS\internal;

use cms_config;
use cms_siteprefs;
use cms_utils;
use CmsApp;
use CmsCoreCapabilities;
use CmsError403Exception;
use CmsError404Exception;
use CMSMS\ModuleOperations;
use Smarty_Internal_SmartyTemplateCompiler;
use const CMS_UPLOADS_URL;
use function cms_join_path;
use function cms_to_bool;
use function get_parameter_value;
use function startswith;

/**
 * Helper class to deal with fetching content blocks.
 *
 * @author      Robert Campbell <calguy1000@cmsmadesimple.org>
 * @since       1.11
 * @ignore
 * @internal
 * @package     CMS
 */
final class content_plugins
{
    private static $_primary_content;    // generated by get_default_content_block_content()

    private function __construct() {}

    /**
     * @ignore
     * @param strihg $content the content
     * @param array $params
     * @param mixed $smarty Smarty_Internal_SmartyTemplateCompiler or CMSMS\internal\template_wrapper
     */
    private static function echo_content(string $content, array &$params, $smarty)
    {
        if( !empty($params['assign']) ) {
            $smarty->assign(trim($params['assign']), $content);
            echo '';
        }
        else {
            echo $content;
        }
    }

    /**
     * This handles {content} blocks.
     * After determining which content block to render, $smarty->fetch()
     * generates a 'content:' resource to retrieve the value of the block.
     *
     * @since 1.11
     * @author calguy1000
     * @param array $params
     * @param Smarty_Internal_SmartyTemplateCompiler $smarty
     * @throws CmsError403Exception
     */
    public static function fetch_contentblock(array $params, $smarty)
    {
        $contentobj = CmsApp::get_instance()->get_content_object();
        $result = null;
        if (is_object($contentobj)) {
            if( !$contentobj->IsPermitted() ) throw new CmsError403Exception();
            $block = $params['block'] ?? 'content_en';
            // if content_en
            //    get primary content
            // otherwise other block
            if( $block == 'content_en' ) {
                // was the data prefetched ?
                $result = self::get_default_content_block_content( $contentobj->Id(), $smarty );
            }
            if( !$result ) {
/*
                if( isset($_SESSION[CMS_PREVIEW]) && $contentobj->Id() == CMS_PREVIEW_PAGEID ) {
                    // note: content precompile/postcompile events will not be triggererd in preview.
//                  $val = $contentobj->Show($block);
//                  $result = $smarty->fetch('eval:'.$val);
                    $result = $smarty->fetch('content:'.strtr($block,' ','_'), '|'.$block, $contentobj->Id().$block);
                }
                else {
*/
                    $result = $smarty->fetch('content:'.strtr($block,' ','_'), '|'.$block, $contentobj->Id().$block);
//                }
            }
        }
        self::echo_content($result, $params, $smarty);
    }

    /**
     * @param array $params
     * @param template_wrapper $template
     * @return mixed string or null
     */
    public static function fetch_pagedata(array $params, $template)
    {
        $contentobj = CmsApp::get_instance()->get_content_object();
        if( !is_object($contentobj) || $contentobj->Id() <= 0 ) {
            self::echo_content('', $params, $template);
            return;
        }

        $result = $template->fetch('content:pagedata','',$contentobj->Id());
        if( isset($params['assign']) ){
            $template->assign(trim($params['assign']),$result);
            return;
        }
        return $result;
    }

    /**
     * @param array $params
     * @param mixed $template
     * @return mixed string or null
     */
    public static function fetch_imageblock(array $params, $template)
    {
        $ignored = [ 'block','type','name','label','upload','dir','default','tab','priority','exclude','sort','profile','urlonly','assign' ];
        $gCms = CmsApp::get_instance();
        $contentobj = $gCms->get_content_object();
        if( !is_object($contentobj) || $contentobj->Id() <= 0 ) {
            self::echo_content('', $params, $template);
            return;
        }

        $config = cms_config::get_instance();
        $adddir = cms_siteprefs::get('contentimage_path');
        if( isset($params['dir']) && $params['dir'] != '' ) $adddir = $params['dir'];
        $dir = cms_join_path($config['uploads_path'],$adddir);
        $basename = basename($config['uploads_path']);

        $result = '';
        if( isset($params['block']) ) {
            $result = $template->fetch('content:'.strtr($params['block'],' ', '_'), '|'.$params['block'], $contentobj->Id().$params['block']);
        }
        $img = $result;

        $out = null;
        if( startswith(realpath($dir),realpath($basename)) ) {
            if( ($img == -1 || empty($img)) && isset($params['default']) && $params['default'] ) $img = $params['default'];

            if( $img != -1 && !empty($img) ) {
                // create the absolute url.
                $orig_val = $img;
                $img = CMS_UPLOADS_URL.'/';
                if( $adddir ) $img .= $adddir.'/';
                $img .= $orig_val;

                $urlonly = cms_to_bool(get_parameter_value($params,'urlonly'));
                if( $urlonly ) {
                    $out = $img;
                }
                else {
                    $tagparms = [];
                    foreach( $params as $key => $val ) {
                        $key = trim($key);
                        if( !$key ) continue;
                        $val = trim($val);
                        if( !$val ) continue;
                        if( in_array($key,$ignored) ) continue;
                        $tagparms[$key] = $val;
                    }

                    $out = "<img src=\"$img\"";
                    foreach( $tagparms as $key => $val ) {
                        $out .= " $key=\"$val\"";
                    }
                    $out .= ' />';
                }
            }
        }
        if( isset($params['assign']) ){
            $template->assign(trim($params['assign']),$out);
            return;
        }
        return $out;
    }

    /**
     *
     * @param array $params
     * @param mixed $template
     * @return mixed string or null
     */
    public static function fetch_moduleblock(array $params, $template)
    {
        if( !isset($params['block']) ) return;

        $block = $params['block'];
        $result = '';

        $gCms = CmsApp::get_instance();
        $content_obj = $gCms->get_content_object();
        if( is_object($content_obj) ) {
            $result = $content_obj->GetPropertyValue($block);
            if( $result == -1 ) $result = '';
            $module = isset($params['module']) ? trim($params['module']) : null;
            if( $module ) {
                $mod = cms_utils::get_module($module);
                if( is_object($mod) ) $result = $mod->RenderContentBlockField($block,$result,$params,$content_obj);
            }
        }

        if( isset($params['assign']) ) {
            $template->assign($params['assign'],$result);
            return;
        }
        return $result;
    }

    /**
     *
     * never returns content on frontend requests
     *
     * @param array $params
     * @param mixed $template
     * @return null
     */
    public static function fetch_textblock(array $params, $template)
    {
        return;
    }

    /**
     * @param mixed $page_id int or ''|null
     * @param mixed $smarty CMSMS\internal\Smarty or CMSMS\internal\template_wrapper
     * @return mixed string or null
     * @throws CmsError404Exception
     */
    public static function get_default_content_block_content($page_id, &$smarty)
    {
        $result = null;
        if( self::$_primary_content ) return self::$_primary_content;

        $do_mact = $module = $id = $action = $inline = null;
        if( isset($_REQUEST['mact']) ) {
            list($module,$id,$action,$inline) = explode(',',$_REQUEST['mact'],4);
            // do not process inline content.
            if( $module && !$inline && $id == 'cntnt01' ) $do_mact = true;
        }

        if( $do_mact ) {
            $modops = ModuleOperations::get_instance();
            $module_obj = $modops->get_module_instance($module);
            if( !$module_obj ) {
                // module not found... couldn't even autoload it.
                @trigger_error('Attempt to access module '.$module.' which could not be found (is it properly installed and configured?');
                throw new CmsError404Exception('Attempt to access module '.$module.' which could not be found (is it properly installed and configured?');
            }
            if( !($module_obj->HasCapability(CmsCoreCapabilities::PLUGIN_MODULE) || $module_obj->IsPluginModule()) ) {
                @trigger_error('Attempt to access module '.$module.' on a frontend request, which is not a plugin module');
                throw new CmsError404Exception('Attempt to access module '.$module.' which could not be found (is it properly installed and configured?');
            }

            $params = $modops->GetModuleParameters($id);
            ob_start();
            $result = $module_obj->DoActionBase($action, $id, $params, $page_id, $smarty);

            if( $result || is_numeric($result) ) echo $result;
            $result = ob_get_contents();
            ob_end_clean();
        }
        else {
            $result = $smarty->fetch('content:content_en', '|content_en', $page_id.'content_en');
        }
        self::$_primary_content = $result;
        return $result;
    }
} // class
