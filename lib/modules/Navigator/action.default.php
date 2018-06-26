<?php
# Navigator module action: default
# Copyright (C) 2013-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
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

use Navigator\Nav_utils;

if( !defined('CMS_VERSION') ) exit;

debug_buffer('Start Navigator default action');
$items = null;
$nlevels = -1;
$show_all = FALSE;
$show_root_siblings = FALSE;
$start_element = null;
$start_page = null;
$start_level = null;
$childrenof = null;
$deep = TRUE;
$collapse = FALSE;

$template = null;
if( isset($params['template']) ) {
    $template = trim($params['template']);
}
else {
    $tpl = CmsLayoutTemplate::load_dflt_by_type('Navigator::navigation');
    if( !is_object($tpl) ) {
        audit('',$this->GetName(),'No default template found');
        return;
    }
    $template = $tpl->get_name();
}

$tpl = $smarty->CreateTemplate($this->GetTemplateResource($template),null,null,$smarty);
$hm = $gCms->GetHierarchyManager();
foreach( $params as $key => $value ) {
    switch( $key ) {
    case 'loadprops':
        $deep = cms_to_bool($value);
        break;

    case 'items':
        // hardcoded list of items (and their children)
        Nav_utils::clear_excludes();
        $items = trim($value);
        $nlevels = 1;
        $start_element = null;
        $start_page = null;
        $start_level = null;
        $childrenof = null;
        break;

    case 'includeprefix':
        Nav_utils::clear_excludes();
        $list = explode(',',$value);
        if( is_array($list) && count($list) ) {
            foreach( $list as &$one ) {
                $one = trim($one);
            }
            $list = array_unique($list);
            if( count($list) ) {
                $flatlist = $hm->getFlatList();
                if( is_array($flatlist) && count($flatlist) ) {
                    $tmp = [];
                    foreach( $flatlist as $id => &$node ) {
                        $alias = $node->get_tag('alias');
                        foreach( $list as $t1 ) {
                            if( startswith( $alias, $t1 ) ) $tmp[] = $alias;
                        }
                    }
                    if( is_array($tmp) && count($tmp) ) $items = implode(',',$tmp);
                }
            }
        }
        $nlevels = 1;
        $start_element = null;
        $start_page = null;
        $start_level = null;
        $childrenof = null;
        break;

    case 'excludeprefix':
        Nav_utils::set_excludes($value);
        $items = null;
        break;

    case 'nlevels':
    case 'number_of_levels':
        // a maximum number of levels;
        if( (int)$value > 0 ) $nlevels = (int)$value;
        break;

    case 'show_all':
        // show all items, even if marked as 'not shown in menu'
        $show_all = cms_to_bool($value);
        break;

    case 'show_root_siblings':
        // given a start element or start page ... show it's siblings too
        $show_root_siblings = cms_to_bool($value);
        break;

    case 'start_element':
        $start_element = trim($value);
        $start_page = null;
        $start_level = null;
        $childrenof = null;
        $items = null;
        break;

    case 'start_page':
        $start_element = null;
        $start_page = trim($value);
        $start_level = null;
        $childrenof = null;
        $items = null;
        break;

    case 'start_level':
        $value = (int)$value;
        if( $value > 1 ) {
            $start_element = null;
            $start_page = null;
            $items = null;
            $start_level = $value;
        }
        break;

    case 'childrenof':
        $start_page = null;
        $start_element = null;
        $start_level = null;
        $childrenof = trim($value);
        $items = null;
        break;

    case 'collapse':
        $collapse = (int)$value;
        break;
    }
} // params

if( $items ) $collapse = FALSE;

$rootnodes = [];
if( $start_element ) {
    // get an alias... from a hierarchy level.
    $tmp = $hm->getNodeByHierarchy($start_element);
    if( is_object($tmp) ) {
        if( !$show_root_siblings ) {
            $rootnodes[] = $tmp;
        }
        else {
            $tmp = $tmp->getParent();
            if( is_object($tmp) && $tmp->has_children() ) {
                $rootnodes = $tmp->get_children();
            }
        }
    }
}
else if( $start_page ) {
    $tmp = $hm->sureGetNodeByAlias($start_page);
    if( is_object($tmp) ) {
        if( !$show_root_siblings ) {
            $rootnodes[] = $tmp;
        }
        else {
            $tmp = $tmp->getParent();
            if( is_object($tmp) && $tmp->has_children() ) {
                $rootnodes = $tmp->get_children();
            }
        }
    }
}
else if( $start_level > 1 ) {
    $tmp = $hm->sureGetNodeById($gCms->get_content_id());
    if( $tmp ) {
        $arr = $arr2 = [];
        while( $tmp ) {
            $id = $tmp->get_tag('id');
            if( !$id ) break;
            $arr[$id] = $tmp;
            $arr2[] = $id;
            $tmp = $tmp->get_parent();
        }
        if( ($start_level - 2) < count($arr2) ) {
            $arr2 = array_reverse($arr2);
            $id = $arr2[$start_level-2];
            $tmp = $arr[$id];
            if( $tmp->has_children() ) {
                // do childrenof this element
                $rootnodes = $tmp->get_children();
            }
        }
    }
}
else if( $childrenof ) {
    $tmp = $hm->sureGetNodeByAlias(trim($childrenof));
    if( is_object($tmp) ) {
        if( $tmp->has_children() ) $rootnodes = $tmp->get_children();
    }
}
else if( $items ) {
    if( $nlevels < 1 ) $nlevels = 1;
    $items = explode(',',$items);
    $items = array_unique($items);
    foreach( $items as $item ) {
        $item = trim($item);
        $tmp = $hm->sureGetNodeByAlias(trim($item));
        if( $tmp ) $rootnodes[] = $tmp;
    }
}
else {
    // start at the top
    if( $hm->has_children() ) $rootnodes = $hm->get_children();
}

if( count($rootnodes) == 0 ) return; // nothing to do.

// ready to fill the nodes
$outtree = [];
foreach( $rootnodes as $node ) {
    if( Nav_utils::is_excluded($node->get_tag('alias')) ) continue;
    $tmp = Nav_utils::fill_node($node,$deep,$nlevels,$show_all,$collapse);
    if( $tmp ) $outtree[] = $tmp;
}

Nav_utils::clear_excludes();
$tpl->assign('nodes',$outtree);

$tpl->display();
debug_buffer('End Navigator default action');
unset($tpl);
