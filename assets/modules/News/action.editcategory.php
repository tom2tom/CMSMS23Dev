<?php

use CMSMS\Events;
use News\news_admin_ops;
use News\news_ops;

if (!isset($gCms)) exit;
if (!$this->CheckPermission('Modify Site Preferences')) return;

$this->SetCurrentTab('categories');
if (isset($params['cancel'])) $this->RedirectToAdminTab('','','admin_settings');

$catid = '';
$row = null;
$name = '';
$parentid = -1;
if( isset($params['catid']) ) {
  $catid = (int)$params['catid'];
  $query = 'SELECT * FROM '.CMS_DB_PREFIX.'module_news_categories WHERE news_category_id = ?';
  $row = $db->GetRow($query, array($catid));
  if( !$row ) {
    $this->SetError($this->Lang('error_categorynotfound'));
    $this->RedirectToAdminTab();
  }
  $name = $row['news_category_name'];
  $parentid = (int)$row['parent_id'];
}

//$parentid = '-1'; // why reset again?

if( isset($params['submit']) ) {
  $parentid = (int)$params['parent'];
  $name = trim($params['name']);

  if( $name == '' ) {
    $this->ShowErrors($this->Lang('nonamegiven'));
  }
  else {
    // its an update.
    $query = 'SELECT news_category_id FROM '.CMS_DB_PREFIX.'module_news_categories
              WHERE parent_id = ? AND news_category_name = ? AND news_category_id != ?';
    $tmp = $db->GetOne($query,array($parentid,$name,$catid));
    if( $tmp ) {
      $this->ShowErrors($this->Lang('error_duplicatename'));
    }
    else {
      if( $parentid == $catid ) {
	$this->ShowErrors($this->Lang('error_categoryparent'));
      }
      else if( $parentid != $row['parent_id'] ) {
	// parent changed

	// gotta figure out a new item order.
	$query = 'SELECT max(item_order) FROM '.CMS_DB_PREFIX.'module_news_categories
                  WHERE parent_id = ?';
	$maxn = (int)$db->GetOne($query,array($parentid));
	$maxn++;

	$query = 'UPDATE '.CMS_DB_PREFIX.'module_news_categories SET item_order = item_order - 1
                  WHERE parent_id = ? AND item_order > ?';
	$db->Execute($query,array($row['parent_id'],$row['item_order']));

	$row['item_order'] = $maxn;
      }

      $query = 'UPDATE '.CMS_DB_PREFIX.'module_news_categories
                SET news_category_name = ?, item_order = ?, parent_id = ?, modified_date = NOW()
                WHERE news_category_id = ?';
      $parms = array($name,$row['item_order'],$parentid);
      $parms[] = $catid;
      $db->Execute($query, $parms);

      news_admin_ops::UpdateHierarchyPositions();

      Events::SendEvent('News', 'NewsCategoryEdited', [ 'category_id'=>$catid, 'name'=>$name, 'origname'=>$origname ] );
      // put mention into the admin log
      audit($catid, 'News category: '.$name, ' Category edited');

      $this->SetMessage($this->Lang('categoryupdated'));
      $this->RedirectToAdminTab('categories','','admin_settings');
    }
  }
}

$tmp = news_ops::get_category_list();
$tmp2 = array_flip($tmp);
$categories = array(-1=>$this->Lang('none'));
foreach( $tmp2 as $k => $v ) {
  if( $k == $catid ) continue;
  $categories[$k] = $v;
}
$parms = ['catid'=>$catid];

//Display template
$tpl = $smarty->createTemplate($this->GetTemplateResource('editcategory.tpl'),null,null,$smarty);

$tpl->assign('catid',$catid)
 ->assign('parent',$parentid)
 ->assign('name',$name)
 ->assign('categories',$categories)
 ->assign('startform', $this->CreateFormStart($id, 'editcategory',
						    $returnid, 'post', '', false, '', $parms))
 ->assign('endform', $this->CreateFormEnd())
 ->assign('nametext', $this->Lang('name'))
 ->assign('inputname', $this->CreateInputText($id, 'name', $name, 20, 255));
//see template ->assign('submit', $this->CreateInputSubmit($id, 'submit', lang('submit')))
//->assign('cancel', $this->CreateInputSubmit($id, 'cancel', lang('cancel')))
//see DoActionBase() ->assign('mod',$this);

$tpl->display();

