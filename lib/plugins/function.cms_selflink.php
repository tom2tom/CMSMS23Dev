<?php
# Plugin to generate an url or link to a page of the current website.
# Copyright (C) 2013-2020 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
# Thanks to Robert Campbell and all other contributors from the CMSMS Development Team.
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

use CMSMS\ContentOperations;
use CMSMS\LangOperations;

function smarty_function_cms_selflink($params, $template)
{
	$gCms = CmsApp::get_instance();
	$hm = $gCms->GetHierarchyManager();
	$url = '';
	$urlparam = '';
	$label_side = 'left';
	$label = '';
	$urlonly = 0;
	$node = null;
	$dir = null;
	$pageid = null;

	$rellink = isset($params['rellink']) && $params['rellink'] == '1';
	if( isset($params['urlparam']) && strlen($params['urlparam']) > 0 ) $urlparam = trim($params['urlparam']);

	if( isset($params['page']) || isset($params['href']) ) {
		$page = null;
		if (isset($params['href'])) {
			$page = trim($params['href']);
			$urlonly = 1;
		}
		else {
			$page = trim($params['page']);
		}

		if( $page ) {
			if( (int)$page > 0 && is_numeric($page) ) {
				$pageid = (int)$page;
			}
			else {
				$page = cms_html_entity_decode($page); // decode entities (alias may be encoded if entered in WYSIWYG)
				$node = $hm->find_by_tag('alias',$page);
				if( $node ) $pageid = $node->get_tag('id');
			}
		}
	}

	else if( isset($params['dir']) ) {
		$startpage = null;
		if( $pageid ) $startpage = $pageid;
		if( !$startpage ) $startpage = $gCms->get_content_id();
		$dir = strtolower(trim($params['dir']));

		switch( $dir ) {
		case 'next':
			// next visible page
			$flatcontent = $hm->getFlatList();
			$indexes = array_keys($flatcontent);
			$i = array_search($startpage,$indexes);
			if( $i < ($n = count($flatcontent)) ) {
				for( $j = $i + 1; $j < $n; $j++ ) {
					$k = $indexes[$j];
					$content = $flatcontent[$k]->getContent();
					if( !is_object($content) ) continue;
					if( !$content->Active() || !$content->HasUsableLink() || !$content->ShowInMenu() ) continue;
					$pageid = $content->Id();
					$label = LangOperations::lang_from_realm('cms_selflink','next_label');
					break;
				}
			}
			break;

		case 'nextpeer':
		case 'nextsibling':
			// next valid peer page.
			$node = $hm->find_by_tag('id',$startpage);
			if( !$node ) return;
			$parent = $node->get_parent();
			if( !$parent ) $parent = $hm; //root node, cloned
			$children = $parent->get_children();
			for( $i = 0, $n = count($children); $i < $n; $i++ ) {
				$id = $children[$i]->get_tag('id');
				if( $id == $startpage ) break;
			}
			if( $i < $n ) {
				for( $j = $i + 1; $j < $n; $j++ ) {
					$content = $children[$j]->getContent();
					if( !is_object($content) ) continue;
					if( !$content->Active() || !$content->HasUsableLink() || !$content->ShowInMenu() ) continue;
					$pageid = $content->Id();
					$label = LangOperations::lang_from_realm('cms_selflink','next_label');
					break;
				}
			}
			break;

		case 'prev':
		case 'previous':
			// previous visible page.
			$flatcontent = $hm->getFlatList();
			$indexes = array_keys($flatcontent);
			$i = array_search($startpage,$indexes);
			if( $i !== FALSE ) {
				for( $j = $i - 1; $j >= 0; $j-- ) {
					$k = $indexes[$j];
					$content = $flatcontent[$k]->getContent();
					if( !is_object($content) || !$content->Active() || !$content->HasUsableLink() || !$content->ShowInMenu() ) continue;
					$pageid = $content->Id();
					$label = LangOperations::lang_from_realm('cms_selflink','prev_label');
					break;
				}
			}
			break;

		case 'prevpeer':
		case 'prevsibling':
			// previous valid peer page.
			$node = $hm->find_by_tag('id',$startpage);
			if( !$node ) return;
			$parent = $node->get_parent();
			if( !$parent ) $parent = $hm;
			$children = $parent->get_children();
			for( $i = 0, $n = count($children); $i < $n; $i++ ) {
				$id = $children[$i]->get_tag('id');
				if( $id == $startpage ) break;
			}
			if( $i < $n ) {
				for( $j = $i - 1; $j >= 0; $j-- ) {
					$content = $children[$j]->getContent();
					if( !is_object($content) || !$content->Active() || !$content->HasUsableLink() || !$content->ShowInMenu() ) continue;
					$pageid = $content->Id();
					$label = LangOperations::lang_from_realm('cms_selflink','prev_label');
					break;
				}
			}
			break;

		case 'start':
			// default home page
			$contentops = ContentOperations::get_instance();
			$pageid = $contentops->GetDefaultPageId();
			break;

		case 'up':
			// parent page.
			$node = $hm->find_by_tag('id',$startpage);
			if( !$node ) return;
			$node = $node->get_parent();
			if( !$node ) return;
			$content = $node->getContent();
			if( !$content ) return;
			$pageid = $content->Id();
			break;

		default:
			// unknown direction... prolly should do something here.
			return;
		}
	}

	if( $pageid == '' ) return;

	// a final check to see if this page exists.
	$node = $hm->find_by_tag('id',$pageid);
	if( !$node ) return;

	// get the content object.
	$content = $node->getContent();
	if( !$content || !is_object($content) || !$content->Active() || !$content->HasUsableLink() ) return;

	// get our raw display data
	$alias = $content->Alias();
	$name = $content->Name();
	$url = $content->GetUrl();
	$menu_text = $content->MenuText();
	$titleattr = $content->TitleAttribute();
	if (isset($params['anchorlink'])) $url .= '#' . ltrim($params['anchorlink'], '#');
	if( $urlparam != '' ) $url .= $urlparam;

	if( empty($url) ) return; // no url to link to, therefore nothing to do.

	if( isset($params['urlonly']) ) $urlonly = cms_to_bool($params['urlonly']);

	if( $urlonly ) {
		if( isset($params['assign']) ) {
			$template->assign(trim($params['assign']),$url);
			return;
		}
		return $url;
	}

	// Now we build the output.
	$result = '';
	if (isset($params['label'])) {
		$label = $params['label'];
		$label = cms_htmlentities($label);
	}

	$title = $name ?? '';
	if( isset($params['title']) ) {
		$title = $params['title'];
	}
	else if( !empty($titleattr) ) {
		$title = $titleattr;
	}
	$title = cms_htmlentities(strip_tags($title));

	if ($rellink && $dir != '' ) {
		// output a relative link.
		$result .= '<link rel="';
		switch($dir) {
		case 'prev':
		case 'previous':
			$result .= 'prev';
			break;

		case 'start':
		case 'anchor':
		case 'next':
		case 'up':
			$result .= $dir;
			break;
		}

		$result .= '" title="'.$title.'" ';
		$result .= 'href="'.$url.'" />';
	}
	else {
		if( isset($params['label_side']) ) $label_side = strtolower(trim($params['label_side']));
		if( $label_side == 'left' ) $result .= $label.' ';
		$result .= '<a href="'.$url.'"';
		$result .= ' title="'.$title.'" ';
		if (isset($params['target'])) $result .= ' target="'.$params['target'].'"';
		if (isset($params['id'])) $result .= ' id="'.$params['id'].'"';
		if (isset($params['class'])) $result .= ' class="'.$params['class'].'"';
		if (isset($params['tabindex'])) $result .= ' tabindex="'.$params['tabindex'].'"';
		if (isset($params['more'])) $result .= ' '.$params['more'];
		$result .= '>';

		$linktext = $name;
		if (isset($params['text'])) {
			$linktext = $params['text'];
		} elseif (isset($params['menu']) && $params['menu'] == '1') {
			$linktext = $menu_text;
		}

		if( !empty($params['image']) ) {
			$width = (!empty($params['width'])) ? (int)$params['width'] : '';
			$height = (!empty($params['height'])) ? (int)$params['height'] : '';
			$alt = (!empty($params['alt'])) ? $params['alt'] : '';
			$result .= "<img src=\"{$params['image']}\" alt=\"$alt\"";
			if( $width ) $width = max(1,$width);
			if( $width ) $result .= " width=\"$width\"";
			if( $height ) $height = max(1,$height);
			if( $height ) $result .= " height=\"$height\"";
			$result .= ' />';
			if( empty($params['imageonly']) ) $result .= " $linktext";
		} else {
			$result .= $linktext;
		}

		$result .= '</a>';
		if( $label_side == 'right' ) $result .= ' '.$label;
	}

	$result = trim($result);
	if( isset($params['assign']) ){
		$template->assign(trim($params['assign']),$result);
		return;
	}
	return $result;
}

function smarty_cms_help_function_cms_selflink()
{
	echo lang_by_realm('tags','help_function_cms_selflink');
}
