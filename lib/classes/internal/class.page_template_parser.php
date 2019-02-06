<?php
#smarty template sub-class
#Copyright (C) 2016-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

namespace CMSMS\internal;

use CmsEditContentException;
use Smarty_Internal_Template;
use SmartyException;
use function startswith;

class page_template_parser extends Smarty_Internal_Template
{
    /**
     * @ignore
     * @var int
     */
    protected static $_priority = 100;

    /**
     * @ignore
     * @var array, each member like 'blockname' => [blockparms]
     */
    protected static $_contentBlocks = [];

    /**
     * @ignore
     * @var strings array
     */
//    private static $_allowed_static_plugins = ['global_content'];

    /**
     * Class constructor
     * @param string $template_resource template identifier
     * @param mixed $smarty
     * @param type $_parent
     * @param type $_cache_id
     * @param mixed $_compile_id string|null - always overridden
     * @param boolean $_caching
     * @param int $_cache_lifetime
     */
    public function __construct(
        $template_resource,
        $smarty,
        $_parent = null,
        $_cache_id = null,
        $_compile_id = null,
        $_caching = false,
        $_cache_lifetime = 0
    ) {
        $_compile_id = 'cmsms_parser_'.microtime();
        parent::__construct($template_resource, $smarty, $_parent, $_cache_id, $_compile_id, $_caching, $_cache_lifetime);

        $this->registerDefaultPluginHandler([$this,'defaultPluginHandler']);
        $this->merge_compiled_includes = true;

        try {
            $this->registerPlugin('compiler', 'content', [$this,'compile_contentblock'], false)
                 ->registerPlugin('compiler', 'content_image', [$this,'compile_imageblock'], false)
                 ->registerPlugin('compiler', 'content_module', [$this,'compile_moduleblock'], false)
                 ->registerPlugin('compiler', 'content_text', [$this,'compile_contenttext'], false);
        } catch (SmartyException $e) {
            // ignore these... throws an error in Smarty 3.1.16 if plugin is already registered
            // because plugin registration is global.
        }
    }

    /**
     * Callable for the default plugin-handler
     * @param array $params
     * @param mixed $template
     * @return string (empty)
     */
    public static function _dflt_plugin($params, $template)
    {
        return '';
    }

    /**
     * Setup a default smarty-plugin handler
     * @param type $name
     * @param string $type
     * @param mixed $template
     * @param callable $callback
     * @param type $script
     * @param boolean $cachable
     * @return boolean
     */
    public function defaultPluginHandler($name, $type, $template, &$callback, &$script, &$cachable)
    {
        if ($type == 'compiler') {
            $callback = [__CLASS__,'_dflt_plugin'];
            $cachable = false;
            return true;
        }

        return false;
    }

    /**
     * Default fetcher - should never be called
     * @param type $template
     * @param type $cache_id
     * @param type $compile_id
     * @param type $parent
     * @param type $display
     * @param type $merge_tpl_vars
     * @param type $no_output_filter
     */
    public function fetch(
        $template = null,
        $cache_id = null,
        $compile_id = null,
        $parent = null,
        $display = false,
        $merge_tpl_vars = true,
        $no_output_filter = false
    ) {
        die(__FILE__.'::'.__LINE__.' CRITICAL: This method should never be called');
    }

    /**
     * Set object properties back to respective defaults
     */
    public static function reset()
    {
        self::$_priority = 100;
        self::$_contentBlocks = [];
    }

    /**
     * Get recorded content blocks
     * @return strings array
     */
    public static function get_content_blocks() : array
    {
        return self::$_contentBlocks;
    }

    /**
     * Compile a content block tag into PHP code
     *
     * @param array $params
     * @param Smarty_Internal_SmartyTemplateCompiler $template
     * @return string
     */
    public static function compile_fecontentblock(array $params, $template) : string
    {
        $tmp = [];
        foreach ($params as $k => $v) {
            //CHECKME if $v is a string, quote it?
            $tmp[] .= '\''.$k.'\'=>'.$v;
        }
        $ptext = implode(',', $tmp);
        return '<?php \\CMSMS\\internal\\content_plugins::fetch_contentblock(['.$ptext.'],$_smarty_tpl); ?>';
    }

    /**
     * Process a {content} tag
     *
     * @param array $params
     * @param mixed $template
     */
    public static function compile_contentblock(array $params, $template)
    {
        $rec = [
            'adminonly'=>0,
            'cssname'=>'',
            'default'=>'',
            'id'=>'',
            'label'=>'',
            'maxlength'=>'255',
            'name'=>'',
            'noedit'=>false,
            'oneline'=>false, //CHECKME was string 'false'
            'placeholder'=>'',
            'priority'=>'',
            'required'=>0,
            'size'=>'50',
            'tab'=>'',
            'type'=>'text',
            'usewysiwyg'=>true, //CHECKME was string 'true'
        ];
        foreach ($params as $key => $value) {
            $value = trim($value, '"\'');
            if (startswith($key, 'data-')) {
                $rec[$key] = $value;
            } else {
                if ($key == 'type') {
                    continue;
                }
                if ($key == 'block') {
                    $key = 'name';
                }
                if ($key == 'wysiwyg') {
                    $key = 'usewysiwyg';
                }
                if (isset($rec[$key])) {
                    $rec[$key] = $value;
                }
            }
        }

        if (!$rec['name']) {
            $rec['name'] = $rec['id'] = 'content_en';
        }
        if (strpos($rec['name'], ' ') !== false) {
            if (!$rec['label']) {
                $rec['label'] = $rec['name'];
            }
            $rec['name'] = str_replace(' ', '_', $rec['name']);
        }
        if (!$rec['id']) {
            $rec['id'] = str_replace(' ', '_', $rec['name']);
        }
/*
        // check for duplicate
        if( isset(self::$_contentBlocks[$rec['name']]) ) throw new CmsEditContentException('Duplicate content block: '.$rec['name']);
*/
        // set priority
        if (empty($rec['priority'])) {
            $rec['priority'] = self::$_priority++;
        }

        self::$_contentBlocks[$rec['name']] = $rec;
    }

    /**
     * Process a {content_image} tag
     * @param type $params
     * @param type $template
     * @throws CmsEditContentException
     */
    public static function compile_imageblock(array $params, $template)
    {
        if (!isset($params['block']) || empty($params['block'])) {
            throw new CmsEditContentException('{content_image} tag requires block parameter');
        }

        $rec = [
            'type'=>'image',
            'name'=>'',
            'label'=>'',
            'upload'=>true,
            'dir'=>'',
            'default'=>'',
            'tab'=>'',
            'priority'=>'',
            'exclude'=>'',
            'sort'=>0,
            'profile'=>'',
        ];
        foreach ($params as $key => $value) {
            if ($key == 'type') {
                continue;
            }
            if ($key == 'block') {
                $key = 'name';
            }
            if (isset($rec[$key])) {
                $rec[$key] = trim($value, "'\"");
            }
        }

        if (!$rec['name']) {
            $n = count(self::$_contentBlocks)+1;
            $rec['name'] = 'image_'.$n;
        }
        if (strpos($rec['name'], ' ') !== false) {
            if (!$rec['label']) {
                $rec['label'] = $rec['name'];
            }
            $rec['name'] = str_replace(' ', '_', $rec['name']);
        }
        if (empty($rec['id'])) {
            $rec['id'] = str_replace(' ', '_', $rec['name']);
        }

        // set priority
        if (empty($rec['priority'])) {
            $rec['priority'] = self::$_priority++;
        }

        self::$_contentBlocks[$rec['name']] = $rec;
    }

    /**
     * Process {content_module} tag
     * @param type $params
     * @param type $template
     * @throws CmsEditContentException
     */
    public static function compile_moduleblock(array $params, $template)
    {
        if (!isset($params['block']) || empty($params['block'])) {
            throw new CmsEditContentException('{content_module} tag requires block parameter');
        }

        $rec = [
            'type'=>'module',
            'id'=>'',
            'name'=>'',
            'module'=>'',
            'label'=>'',
            'blocktype'=>'',
            'tab'=>'',
            'priority'=>'',
        ];
        $parms = [];
        foreach ($params as $key => $value) {
            if ($key == 'block') {
                $key = 'name';
            }

            $value = trim(trim($value, '"\''));
            if (isset($rec[$key])) {
                $rec[$key] = $value;
            } else {
                $parms[$key] = $value;
            }
        }

        if (!$rec['name']) {
            $n = count(self::$_contentBlocks)+1;
            $rec['id'] = $rec['name'] = 'module_'.$n;
        }
        if (strpos($rec['name'], ' ') !== false) {
            if (!$rec['label']) {
                $rec['label'] = $rec['name'];
            }
            $rec['name'] = str_replace(' ', '_', $rec['name']);
        }
        if (!$rec['id']) {
            $rec['id'] = str_replace(' ', '_', $rec['name']);
        }
        $rec['params'] = $parms;
        if ($rec['module'] == '') {
            throw new CmsEditContentException('Missing module param for content_module tag');
        }

        // set priority
        if (empty($rec['priority'])) {
            $rec['priority'] = self::$_priority++;
        }

        self::$_contentBlocks[$rec['name']] = $rec;
    }

    /**
     * Process {content_text} tag
     *
     * @param array $params
     * @param mixed $template
     */
    public static function compile_contenttext(array $params, $template)
    {
        //if( !isset($params['block']) || empty($params['block']) ) throw new \CmsEditContentException('{content_text} smarty block tag requires block parameter');

        $rec = [
            'type'=>'static',
            'name'=>'',
            'label'=>'',
            'upload'=>true,
            'dir'=>'',
            'default'=>'',
            'tab'=>'',
            'priority'=>'',
            'exclude'=>'',
            'sort'=>0,
            'profile'=>'',
            'text'=>'',
        ];
        foreach ($params as $key => $value) {
            if ($key == 'type') {
                continue;
            }
            if ($key == 'block') {
                $key = 'name';
            }
            if (isset($rec[$key])) {
                $rec[$key] = trim($value, "'\"");
            }
        }

        if (!$rec['name']) {
            $n = count(self::$_contentBlocks)+1;
            $rec['name'] = 'static_'.$n;
        }
        if (strpos($rec['name'], ' ') !== false) {
            if (!$rec['label']) {
                $rec['label'] = $rec['name'];
            }
            $rec['name'] = str_replace(' ', '_', $rec['name']);
        }
        if (empty($rec['id'])) {
            $rec['id'] = str_replace(' ', '_', $rec['name']);
        }

        // set priority
        if (empty($rec['priority'])) {
            $rec['priority'] = self::$_priority++;
        }

        if (!$rec['text']) {
            return; // do nothing.
        }
        $rec['static_content'] = trim(strip_tags($rec['text']));

        self::$_contentBlocks[$rec['name']] = $rec;
    }
}
