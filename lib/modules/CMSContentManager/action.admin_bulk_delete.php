<?php
# CMSContentManager module action: bulk delete
# Copyright (C) 2013-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

if( !isset($gCms) ) exit;
$this->SetCurrentTab('pages');

if( !isset($params['multicontent']) || !isset($params['action']) || $params['action'] != 'admin_bulk_delete' ) {
  $this->SetError($this->Lang('error_missingparam'));
  $this->RedirectToAdminTab();
}

function cmscm_admin_bulk_delete_can_delete($node)
{
  // test if can delete this node (not its children)
  $mod = cms_utils::get_module('CMSContentManager');
  if( $mod->CheckPermission('Manage All Content') ) return TRUE;
  if( $mod->CheckPermission('Modify Any Page') && $mod->CheckPermission('Remove Pages') ) return true;
  if( !$mod->CheckPermission('Remove Pages') ) return FALSE;

  $id = (int)$node->get_tag('id');
  if( $id < 1 ) return FALSE;
  if( $id == ContentOperations::get_instance()->GetDefaultContent() ) return FALSE;

  return ContentOperations::get_instance()->CheckPageAuthorship(get_userid(),$id);
}

function cmscm_get_deletable_pages($node)
{
    $out = [];
    if( cmscm_admin_bulk_delete_can_delete($node) ) {
        // we can delete the parent node.
        $out[] = $node->get_tag('id');
        if( $node->has_children() ) {
            // it has children.
            $children = $node->get_children();
            foreach( $children as $child_node ) {
                $tmp = cmscm_get_deletable_pages($child_node);
                $out = array_merge($out,$tmp);
            }
        }
    }
  return $out;
}

if( isset($params['cancel']) ) {
    $this->SetInfo($this->Lang('msg_cancelled'));
    $this->RedirectToAdminTab();
}
if( isset($params['submit']) ) {

  if( isset($params['confirm1']) && isset($params['confirm2']) && $params['confirm1'] == 1  && $params['confirm2'] == 1 ) {
    //
    // do the real work
    //
    $pagelist = unserialize(base64_decode($params['multicontent']));

    try {
        $contentops = ContentOperations::get_instance();
        $hm = cmsms()->GetHierarchyManager();
        $i = 0;
        foreach( $pagelist as $pid ) {
            $node = $contentops->quickfind_node_by_id($pid);
            if( !$node ) continue;
            $content = $node->getContent(FALSE,FALSE,TRUE);
            if( !is_object($content) ) continue;
            if( $content->DefaultContent() ) continue;
            $content->Delete();
            $i++;
        }
        if( $i > 0 ) {
            $contentops->SetAllHierarchyPositions();
            $contentops->SetContentModified();
            audit('','Content','Deleted '.$i.' pages');
            $this->SetMessage($this->Lang('msg_bulk_successful'));
        }
    }
    catch( Exception $e ) {
        $this->SetError($e->GetMessage());
    }
    $this->RedirectToAdminTab();
  }
  else {
      $this->SetError($this->Lang('error_notconfirmed'));
      $this->RedirectToAdminTab();
  }
}


//
// expand $params['multicontent'] to also include children, place it in $pagelist
//
$multicontent = unserialize(base64_decode($params['multicontent']));
if( !$multicontent ) {
    $this->SetError($this->Lang('error_missingparam'));
    $this->RedirectToAdminTab();
}

$contentops = ContentOperations::get_instance();
$pagelist = [];
foreach( $multicontent as $pid ) {
    $node = $contentops->quickfind_node_by_id($pid);
    if( !$node ) continue;
    $tmp = cmscm_get_deletable_pages($node);
    $pagelist = array_merge($pagelist,$tmp);
}
$pagelist = array_unique($pagelist);

//
// build the confirmation display
//
$contentops->LoadChildren(-1,FALSE,FALSE,$pagelist);
$displaydata =  [];
foreach( $pagelist as $pid ) {
  $node = $contentops->quickfind_node_by_id($pid);
  if( !$node ) continue;  // this should not happen, but hey.
  $content = $node->getContent(FALSE,FALSE,FALSE);
  if( !is_object($content) ) continue; // this should never happen either

  if( $content->DefaultContent() ) {
    $this->ShowErrors($this->Lang('error_delete_defaultcontent'));
    continue;
  }

  $rec = [];
  $rec['id'] = $content->Id();
  $rec['name'] = $content->Name();
  $rec['menutext'] = $content->MenuText();
  $rec['owner'] = $content->Owner();
  $rec['alias'] = $content->Alias();
  $displaydata[] = $rec;
}

if( !$displaydata ) {
  $this->SetError($this->Lang('error_delete_novalidpages'));
  $this->RedirectToAdminTab();
}

$tpl = $smarty->createTemplate($this->GetTemplateResource('admin_bulk_delete.tpl'),null,null,$smarty);
$tpl->assign('multicontent',base64_encode(serialize($pagelist)))
 ->assign('displaydata',$displaydata);

$tpl->display();
