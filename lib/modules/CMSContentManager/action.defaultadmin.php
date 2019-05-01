<?php
# CMSContentManger module action: defaultadmin
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

use CMSContentManager\ContentListBuilder;
use CMSContentManager\ContentListFilter;
use CMSContentManager\Utils;
use CMSMS\FormUtils;
use CMSMS\ScriptOperations;
use CMSMS\TemplateOperations;
use CMSMS\UserOperations;
use CMSMS\internal\bulkcontentoperations;

global $CMS_JOB_TYPE;

if( !isset($gCms) ) exit;
// no permissions checks here.

echo '<noscript><h3 style="color:red;text-align:center;">'.$this->Lang('info_javascript_required').'</h3></noscript>'."\n";

$builder = new ContentListBuilder($this);
$pagelimit = cms_userprefs::get($this->GetName().'_pagelimit',500);
$filter = cms_userprefs::get($this->GetName().'_userfilter');
if( $filter ) $filter = unserialize($filter);

//if( isset($params['ajax']) ) { $ajax = 1; } else { $ajax = 0; }

if( isset($params['curpage']) ) {
    $curpage = max(1,min(500,(int)$params['curpage']));
}

if( isset($params['expandall']) || isset($_GET['expandall']) ) {
    $builder->expand_all();
    $curpage = 1;
}
else if( isset($params['collapseall']) || isset($_GET['collapseall']) ) {
    $builder->collapse_all();
    $curpage = 1;
}

$filter = null;
if( isset($params['setoptions']) ) {
    $pagelimit = max(1,min(500,(int)$params['pagelimit']));
    cms_userprefs::set($this->GetName().'_pagelimit',$pagelimit);

    $filter_type = $params['filter_type'] ?? null;
    switch( $filter_type ) {
    case ContentListFilter::EXPR_DESIGN:
        $filter = new ContentListFilter();
        $filter->type = ContentListFilter::EXPR_DESIGN;
        $filter->expr = $params['filter_design'];
        break;
    case ContentListFilter::EXPR_TEMPLATE:
        $filter = new ContentListFilter();
        $filter->type = ContentListFilter::EXPR_TEMPLATE;
        $filter->expr = $params['filter_template'];
        break;
    case ContentListFilter::EXPR_OWNER:
        $filter = new ContentListFilter();
        $filter->type = ContentListFilter::EXPR_OWNER;
        $filter->expr = $params['filter_owner'];
        break;
    case ContentListFilter::EXPR_EDITOR:
        $filter = new ContentListFilter();
        $filter->type = ContentListFilter::EXPR_EDITOR;
        $filter->expr = $params['filter_editor'];
        break;
    default:
        cms_userprefs::remove($this->GetName().'_userfilter');
    }
    if( $filter ) {
		//record for use by ajax processor
		cms_userprefs::set($this->GetName().'_userfilter',serialize($filter));
	}
    $curpage = 1;
}
if( isset($params['expand']) ) {
    $builder->expand_section($params['expand']);
}

if( isset($params['collapse']) ) {
    $builder->collapse_section($params['collapse']);
    $curpage = 1;
}

if( isset($params['setinactive']) ) {
    $builder->set_active($params['setinactive'],FALSE);
    if( !$res ) $this->ShowErrors($this->Lang('error_setinactive'));
}

if( isset($params['setactive']) ) {
    $res = $builder->set_active($params['setactive'],TRUE);
    if( !$res ) $this->ShowErrors($this->Lang('error_setactive'));
}

if( isset($params['setdefault']) ) {
    $res = $builder->set_default($params['setdefault'],TRUE);
    if( !$res ) $this->ShowErrors($this->Lang('error_setdefault'));
}

if( isset($params['moveup']) ) {
    $res = $builder->move_content($params['moveup'],-1);
    if( !$res ) $this->ShowErrors($this->Lang('error_movecontent'));
}

if( isset($params['movedown']) ) {
    $res = $builder->move_content($params['movedown'],1);
    if( !$res ) $this->ShowErrors($this->Lang('error_movecontent'));
}

if( isset($params['delete']) ) {
    $res = $builder->delete_content($params['delete']);
    if( $res ) $this->ShowErrors($res);
}

if( isset($params['multisubmit']) && isset($params['multiaction']) &&
    isset($params['multicontent']) && $params['multicontent'] ) {
    list($module,$bulkaction) = explode('::',$params['multiaction'],2);
    if( $module == '' || $module == '-1' || $bulkaction == '' || $bulkaction == '-1' ) {
        $this->SetMessage($this->Lang('error_nobulkaction'));
        $this->RedirectToAdminTab();
    }
    // redirect to special action to handle bulk content stuff.
    $this->Redirect($id,'admin_multicontent',$returnid,[
        'multicontent'=>base64_encode(serialize($params['multicontent'])),
        'multiaction'=>$params['multiaction']
    ]);
}

$pmanage = $this->CheckPermission('Manage All Content');

$tpl = $smarty->createTemplate($this->GetTemplateResource('defaultadmin.tpl'),null,null,$smarty);

require_once __DIR__.DIRECTORY_SEPARATOR.'action.ajax_get_content.php';

$modname = $this->GetName();
if( isset($curpage) ) {
    $_SESSION[$modname.'_curpage'] = $curpage;
}

$url = $this->create_url($id,'admin_ajax_pagelookup','');
$lookup_url = str_replace('&amp;','&',rawurldecode($url)) . '&'.CMS_JOB_KEY.'=1';
$url = $this->create_url($id,'ajax_get_content','');
$content_url = str_replace('&amp;','&',rawurldecode($url)) . '&'.CMS_JOB_KEY.'=1';
$url = $this->create_url($id,'ajax_check_locks','');
$checklocks_url = str_replace('&amp;','&',rawurldecode($url)) . '&'.CMS_JOB_KEY.'=1';
$locks_url = $config['admin_url'].'/ajax_lock.php?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];

//TODO any other action-specific js
//TODO flexbox css for multi-row .colbox, .rowbox.flow, .boxchild

$s1 = json_encode($this->Lang('confirm_setinactive'));
$s2 = json_encode($this->Lang('confirm_setdefault'));
$s3 = json_encode($this->Lang('confirm_delete_page'));
$s4 = json_encode($this->Lang('confirm_steal_lock'));
$s8 = json_encode($this->Lang('confirm_clearlocks'));
$s5 = json_encode($this->Lang('error_contentlocked'));
$s9 = json_encode($this->Lang('error_action_contentlocked'));
$s6 = lang('submit');
$s7 = lang('cancel');
$secs = cms_siteprefs::get('lock_refresh', 120);
$secs = max(30,min(600,$secs));

$sm = new ScriptOperations();
$sm->queue_matchedfile('jquery.cmsms_autorefresh.js', 1);
$sm->queue_matchedfile('jquery.ContextMenu.js', 2);

$js = <<<EOS
function cms_CMloadUrl(link, lang) {
 $(link).on('click', function(e) {
  var url = $(this).attr('href') + '&{$id}ajax=1&' + cms_data.job_key + '=1';
  var _do_ajax = function() {
   $.ajax(url).done(function() {
    $('#content_area').autoRefresh('start').always(function() {
     $('#content_area').autoRefresh('stop');
    });
   });
  };
  $('#ajax_find').val('');
  e.preventDefault();
  if(typeof lang === 'string' && lang.length > 0) {
    cms_confirm(lang).done(_do_ajax);
  } else {
    _do_ajax();
  }
  return false;
 });
}
function cms_CMtoggleState(el) {
 $(el).attr('disabled', 'disabled').prop('disabled', true);
 $('input:checkbox').on('click', function() {
  if($('input:checkbox').is(':checked')) {
   $(el).removeAttr('disabled').prop('disabled', false);
  } else {
   $(el).attr('disabled', 'disabled').prop('disabled', true);
  }
 });
}
function optsclicker() {
 $('#myoptions').on('click', function() {
  cms_dialog($('#filterdialog'), {
   minWidth: '400',
   minHeight: 225,
   resizable: false,
   buttons: {
    $s6: function() {
     $(this).dialog('close');
     $('#myoptions_form').submit();
    },
    $s7: function() {
     $(this).dialog('close');
    }
   }
  });
 });
}
function gethelp(tgt) {
  var p = tgt.parentNode,
    s = $(p).attr('data-cmshelp-key');
  if (s) {
   $.get(cms_data.ajax_help_url, {
    key: s
   }, function(text) {
    s = $(p).attr('data-cmshelp-title');
    var data = {
     cmshelpTitle: s
    };
    cms_help(tgt, data, text);
   });
  }
}
$(function() {
 $('[context-menu]').ContextMenu();
 $('.cms_help .cms_helpicon').on('click', function() {
  gethelp(this);
 });
 $('#selectall').cmsms_checkall({
  target: '#contenttable'
 });
/*
 $('<div></div').autoRefresh({
  url: '$checklocks_url',
  interval: $secs,
  done_handler: function(json) {
   //var lockdata = JSON.parse(json);
   //TODO refresh locking elements
   var TODO = 1;
  }
 });
*/
 var pageurl = '$content_url';
 $('#ajax_find').autocomplete({
  source: '$lookup_url',
  minLength: 2,
  position: {
   my: 'right top',
   at: 'right bottom'
  },
  change: function(e, ui) {
   // goes back to the full list, no options
   $('#ajax_find').val('');
   $('#content_area').autoRefresh('option', 'url', pageurl).always(function() {
    $('#content_area').autoRefresh('stop');
   });
  },
  select: function(e, ui) {
   e.preventDefault();
   $(this).val(ui.item.label);
   var url = pageurl + '&{$id}seek=' + ui.item.value;
   $('#content_area').autoRefresh('option', 'url', url).always(function() {
    $('html,body').animate({
     scrollTop: $('#row_' + ui.item.value).offset().top
    });
    $('#content_area').autoRefresh('stop');
   });
  }
 });
 cms_CMtoggleState('#multiaction');
 cms_CMtoggleState('#multisubmit');
// * these links can't use ajax as they affect pagination.
 cms_CMloadUrl('a.expandall');
 cms_CMloadUrl('a.collapseall');
 cms_CMloadUrl('a.page_collapse');
 cms_CMloadUrl('a.page_expand');
//*/
 cms_CMloadUrl('a.page_sortup');
 cms_CMloadUrl('a.page_sortdown');
 cms_CMloadUrl('a.page_setinactive', $s1);
 cms_CMloadUrl('a.page_setactive');
 cms_CMloadUrl('a.page_setdefault', $s2);
 cms_CMloadUrl('a.page_delete', $s3);

 $('a.steal_lock').on('click', function(e) {
  // we're gonna confirm stealing this lock
  e.preventDefault();
  var url = $(this).attr('href');
  cms_confirm($s4).done(function() {
   window.location.href = url + '&{$id}steal=1';
  });
  return false;
 });

 var lockurl = '$locks_url';
 $('a.page_edit').on('click', function(e) {
  var v = $(this).data('steal_lock');
  $(this).removeData('steal_lock');
  if(typeof(v) !== 'undefined' && v !== null && !v) return false;
  if(typeof(v) === 'undefined' || v !== null) return true;
  e.preventDefault();
  url = $(this).attr('href');
  // double-check whether this page is locked
  var content_id = $(this).attr('data-cms-content');
  $.ajax(lockurl, {
   data: {
    opt: 'check',
    type: 'content',
    oid: content_id
   }
  }).done(data, function() {
   if(data.status == 'success') {
    if(data.locked) {
     // gotta display a message.
     cms_alert($s5);
    } else {
     window.location.href = url;
    }
   } else {
     cms_alert('AJAX ERROR');
   }
  });
  return false;
 });
 // filter dialog
 $('#filter_type').on('change', function() {
  var map = {
   'TEMPLATE_ID': '#filter_template',
   'OWNER_UID': '#filter_owner',
   'EDITOR_UID': '#filter_editor'
  };
  var v = $(this).val();
  $('.filter_fld').hide();
  $(map[v]).show();
 });
 $('#filter_type').trigger('change');
 optsclicker();
 // other events
 $('#selectall,input.multicontent').on('change', function() {
  $('#content_area').autoRefresh('start').always(function() {
   $('#content_area').autoRefresh('stop');
  });
 });
 $('#ajax_find').on('keypress', function(e) {
  $('#content_area').autoRefresh('start').always(function() {
   $('#content_area').autoRefresh('stop');
  });
  if(e.which == 13) e.preventDefault();
 });
 // go to page on option change
 $('#{$id}curpage').on('change', function() {
  $(this).closest('form').submit();
 });
 $(document).ajaxComplete(function() {
  $('#selectall').cmsms_checkall();
  $('tr.selected').css('background', 'yellow');
 });
 $('a#clearlocks').on('click', function(e) {
  e.preventDefault();
  cms_confirm_linkclick(this, $s8);
  return false;
 });
 $('a#ordercontent').on('click', function(e) {
  var have_locks = $have_locks;
  if(!have_locks) {
   e.preventDefault();
   var url = $(this).attr('href');
   // double-check whether the page is locked
   $.ajax(lockurl,{
    async: false,
    data: {
     opt: 'check',
     type: 'content'
    }
   }).done(data, function() {
    if(data.status == 'success') {
     if(data.locked) {
       have_locks = true;
       cms_alert($s9);
     } else {
       window.location.href = url;
     }
    } else {
       cms_alert('AJAX ERROR');
    }
   });
   return false;
  }
 });
});
EOS;

$sm->queue_string($js, 3);
$out = $sm->render_inclusion('', false, false);
if ($out) {
    $this->AdminBottomContent($out);
}

$opts = ($pmanage) ?
    ['' => lang('none'),
//    'DESIGN_ID' => $this->Lang('prompt_design'),
    'TEMPLATE_ID' => $this->Lang('prompt_template'),
    'OWNER_UID' => $this->Lang('prompt_owner'),
    'EDITOR_UID' => $this->Lang('prompt_editor')] : null;

//$tpl->assign('ajax',$ajax)
$tpl->assign('can_manage_content',$pmanage)
 ->assign('can_reorder_content',$pmanage)
 ->assign('can_add_content', $pmanage || $this->CheckPermission('Add Pages'))
// ->assign('settingsicon',cms_join_path(__DIR__,'images','settings'))
 ->assign('filter',$filter)
 ->assign('filterimage',cms_join_path(__DIR__,'images','filter')) //TODO use new admin icon
// filter selector items
 ->assign('opts',$opts)
// list of admin users for filtering
 ->assign('user_list',(new UserOperations())->GetList())
// list of designs for filtering
// ->assign('design_list',CmsLayoutCollection::get_list())  TODO replacement :: stylesheets and/or groups
// list of templates for filtering
 ->assign('template_list',TemplateOperations::template_query(['as_list'=>1]));

$tpl->display();
