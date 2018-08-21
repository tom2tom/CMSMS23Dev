<?php
#CMSContentManager-module action: edit page content
#Copyright (C) 2013-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
#This file is a component of CMS Made Simple <http://dev.cmsmadesimple.org>
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

use CMSContentManager\Utils;
use CMSMS\ContentException;
use CMSMS\ContentOperations;

global $CMS_JOB_TYPE;

if( !isset($gCms) ) exit;

$this->SetCurrentTab('pages');

if( isset($params['cancel']) ) {
    unset($_SESSION['__cms_copy_obj__']);
    $this->SetInfo($this->Lang('msg_cancelled'));
    $this->RedirectToAdminTab();
}

//
// init
//
try {
    $user_id = get_userid();
    $content_id = $parent_id = $content_obj = $error = $active_tab = null;
    $pagedefaults = Utils::get_pagedefaults();
    $content_type = $pagedefaults['contenttype'];
    $error = null;

    if( isset($params['content_id']) ) $content_id = (int)$params['content_id'];

    if( $content_id < 1 ) {
        // adding.
        if( !$this->CheckPermission('Add Pages') ) {
            // no permission to add pages.
            $this->SetError($this->Lang('error_editpage_permission'));
            $this->RedirectToAdminTab();
        }
    }
    elseif( !$this->CanEditContent($content_id) ) {
        // nope, can't edit this page anyways.
        $this->SetError($this->Lang('error_editpage_permission'));
        $this->RedirectToAdminTab();
    }

    // get a list of content types and pick a default if necessary
    $contentops = ContentOperations::get_instance();
    $existingtypes = $contentops->ListContentTypes(false,true);

    //
    // load or create the initial content object
    //
    if( $content_id == 'copy' && isset($_SESSION['__cms_copy_obj__']) ) {
        // we're copying a content object.
        $content_obj = unserialize($_SESSION['__cms_copy_obj__']);
        $content_type = $content_obj->Type();
    }
    elseif( $content_id < 1 ) {
        // creating a new content object
        if( isset($params['parent_id']) ) $parent_id = (int) $params['parent_id'];
        if( isset($params['content_type']) ) $content_type = trim($params['content_type']);
        $content_obj = $contentops->CreateNewContent($content_type);
        $content_obj->SetOwner($user_id);
        $content_obj->SetLastModifiedBy($user_id);
        $content_obj->SetActive($pagedefaults['active']);
        $content_obj->SetCachable($pagedefaults['cachable']);
        $content_obj->SetShowInMenu($pagedefaults['showinmenu']);
        $content_obj->SetPropertyValue('design_id',$pagedefaults['design_id']);
        $content_obj->SetTemplateId($pagedefaults['template_id']);
        $content_obj->SetPropertyValue('searchable',$pagedefaults['searchable']);
        $content_obj->SetPropertyValue('content_en',$pagedefaults['content']);
        $content_obj->SetMetaData($pagedefaults['metadata']);
        $content_obj->SetPropertyValue('extra1',$pagedefaults['extra1']);
        $content_obj->SetPropertyValue('extra2',$pagedefaults['extra2']);
        $content_obj->SetPropertyValue('extra3',$pagedefaults['extra3']);
        $content_obj->SetAdditionalEditors($pagedefaults['addteditors']);
        $dflt_parent = (int) cms_userprefs::get('default_parent');
        if( $dflt_parent < 1 ) $dflt_parent = -1;
        if( !$this->CheckPermission('Modify Any Page') || !$this->CheckPermission('Manage All Content') ) {
            // we get the list of pages that this user has access to.
            // if he is not an editor of the default page, then we use the first page the user has access to, or -1
            $list = $contentops->GetPageAccessForUser($user_id);
            if( count($list) && !in_array($dflt_parent,$list) ) $dflt_parent = $list[0];
        }
        // double check if this parent is valid... if it is not, we use -1
        if( $dflt_parent > 0 ) {
            $node = $contentops->quickfind_node_by_id( $dflt_parent );
            if( !$node ) $dflt_parent = -1;
        }
        if( $parent_id < 1 ) $parent_id = $dflt_parent;
        $content_obj->SetParentId($parent_id);
    }
    else {
        // editing an existing content object
        $content_obj = $contentops->LoadContentFromId($content_id);
        $content_type = $content_obj->Type();
        if( isset($params['content_type']) ) {
            $content_type = trim($params['content_type']);
        }
    }

    // validate the content type.
    if( is_array($existingtypes) && count($existingtypes) && !in_array($content_type,array_keys($existingtypes)) ) {
        $this->SetError($this->Lang('error_editpage_contenttype'));
        $this->RedirectToAdminTab();
    }
}
catch( Exception $e ) {
    // An error here means we can't display anything
    $this->SetError($e->getMessage());
    $this->RedirectToAdminTab();
}

//
// handle changing content types
// or a POST
//
try {
    if( $content_id != -1 && $content_type != $content_obj->Type() ) {
        // content type changed. create a new content object, but preserve the id.
        $tmpobj = $contentops->CreateNewContent($content_type);
        $tmpobj->SetId($content_obj->Id());
        $tmpobj->SetName($content_obj->Name());
        $tmpobj->SetMenuText($content_obj->MenuText());
        $tmpobj->SetTemplateId($content_obj->TemplateId());
        if( $tmpobj->TemplateId() < 1 ) $tmpobj->SetTemplateId($pagedefaults['template_id']);
        if( $tmpobj->GetPropertyValue('design_id') < 1 ) $tmpobj->SetPropertyValue('design_id',$pagedefaults['design_id']);

        $tmpobj->SetParentId($content_obj->ParentId());
        $tmpobj->SetAlias($content_obj->Alias());
        $tmpobj->SetOwner($content_obj->Owner());
        $tmpobj->SetActive($content_obj->Active());
        $tmpobj->SetItemOrder($content_obj->ItemOrder());
        $tmpobj->SetShowInMenu($content_obj->ShowInMenu());
        $tmpobj->SetCachable($content_obj->Cachable());
        $tmpobj->SetHierarchy($content_obj->Hierarchy());
        $tmpobj->SetLastModifiedBy($content_obj->LastModifiedBy());
        $tmpobj->SetAdditionalEditors($content_obj->GetAdditionalEditors());
        $tmpobj->Properties();
        $content_obj = $tmpobj;
    }

    $was_defaultcontent = $content_obj->DefaultContent();
    if( strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' ) {
        // if we're in a POST action, another item may have changed that requires reloading the page
        // filling the params will make sure that no edited content was lost.
        $content_obj->FillParams($_POST,($content_id > 0));
    }

    $active_tab = isset($params['active_tab']) ? trim($params['active_tab']) : null;
    if( isset($params['submit']) || isset($params['apply']) || isset($params['preview']) ) {
        $error = $content_obj->ValidateData();
        if( $error ) {
            if( isset($params['ajax']) ) {
                $tmp = ['response'=>'Error','details'=>$error];
                echo json_encode($tmp);
                exit;
            }
            // error, but no ajax... fall through
        }
        elseif( isset($params['submit']) || isset($params['apply']) ) {
            $content_obj->SetLastModifiedBy(get_userid());
            $content_obj->Save();
            if( ! $was_defaultcontent && $content_obj->DefaultContent() ) {
                $contentops->SetDefaultContent( $content_obj->Id() );
            }
            unset($_SESSION['__cms_copy_obj__']);
            audit($content_obj->Id(),'Content','Edited content item '.$content_obj->Name());
            if( isset($params['submit']) ) {
                $this->SetMessage($this->Lang('msg_editpage_success'));
                $this->RedirectToAdminTab();
            }

            if( isset($params['ajax']) ) {
                $tmp = ['response'=>'Success','details'=>$this->Lang('msg_editpage_success'),'url'=>$content_obj->GetURL()];
                echo json_encode($tmp);
                exit;
            }
        }
        elseif( isset($params['preview']) && $content_obj->HasPreview() ) {
            $_SESSION['__cms_preview__'] = serialize($content_obj);
            $_SESSION['__cms_preview_type__'] = $content_type;
            debug_to_log($_SESSION,'before preview');
            exit;
        }
    }
}
catch( CmsEditContentException $e ) {
/*
    if( isset($params['submit']) ) {
        $this->SetError($e->getMessage());
        $this->RedirectToAdminTab();
    };
*/
    $error = $e->GetMessage();
    if( isset($params['ajax']) ) {
        $tmp = ['response'=>'Error','details'=>$error];
        echo json_encode($tmp);
        exit;
    }
}
catch( CmsContentException $e ) {
    $error = $e->GetMessage();
    if( isset($params['ajax']) ) {
        $tmp = ['response'=>'Error','details'=>$error];
        echo json_encode($tmp);
        exit;
    }
}
catch( ContentException $e ) {
    $error = $e->GetMessage();
    if( isset($params['ajax']) ) {
        $tmp = ['response'=>'Error','details'=>$error];
        echo json_encode($tmp);
        exit;
    }
}

//
// BUILD THE DISPLAY
//
if( $content_id && Utils::locking_enabled() ) {
    try {
        $lock_id = null;
        for( $i = 0; $i < 3; $i++ ) {
            // check if this thing is already locked.
            $lock_id = CmsLockOperations::is_locked('content',$content_id);
            if( $lock_id == 0 ) break;
            usleep(500);
        }
        if( $lock_id > 0 ) {
            // it's locked... by somebody, make sure it's expired before we allow stealing it.
            $lock = CmsLock::load('content',$content_id);
            if( !$lock->expired() ) throw new CmsLockException('CMSEX_L010');
            // lock is expired, we can just remove it.
            CmsLockOperations::unlock($lock_id,'content',$content_id);
        }
    }
    catch( CmsException $e ) {
        $this->SetError($e->getMessage());
        $this->RedirectToAdminTab();
    }
}

$tab_contents_array = [];
$tab_message_array = [];

try {
    $tab_names = $content_obj->GetTabNames();

    // the content object may not have a main tab, but we require one
    if( !in_array($content_obj::TAB_MAIN,$tab_names) ) {
        $tmp = [$content_obj::TAB_MAIN=>lang($content_obj::TAB_MAIN)];
        $tab_names = array_merge($tmp,$tab_names);
    }

    foreach( $tab_names as $currenttab => $label ) {
        $tmp = $content_obj->GetTabMessage($currenttab);
        if( $tmp ) $tab_message_array[$currenttab] = $tmp;

        $contentarray = $content_obj->GetTabElements($currenttab, $content_obj->Id() == 0 );
        if( $currenttab == $content_obj::TAB_MAIN ) {
            // first tab... add the content type selector.
            if( $this->CheckPermission('Manage All Content') || $content_obj->Owner() == $user_id )  {
                // if you're only an additional editor on this page... you don't get to change this.
                $help = '&nbsp;'.cms_admin_utils::get_help_tag(['key'=>'help_content_type','title'=>$this->Lang('help_title_content_type')]);
                $tmp = ['<label for="content_type">*'.$this->Lang('prompt_editpage_contenttype').':</label>'.$help];
                $tmp2 = "<select id=\"content_type\" name=\"{$id}content_type\">";
                foreach( $existingtypes as $type => $label ) {
                    $tmp2 .= CmsFormUtils::create_option(['value'=>$type,'label'=>$label],$content_type);
                }
                $tmp2 .= '</select>';
                $tmp[] = $tmp2;
                $contentarray = array_merge([$tmp],$contentarray);
            }
        }
        $tab_contents_array[$currenttab] = $contentarray;
    }
}
catch( Exception $e ) {
    $tab_names = null;
    $error = $e->GetMessage();
}

if( $error ) {
    $this->ShowErrors($error);
}

$tpl = $smarty->createTemplate($this->GetTemplateResource('admin_editcontent.tpl'),null,null,$smarty);

if( $content_obj->HasPreview() ) {
    $tpl->assign('has_preview',1);
    $preview_url = $config['root_url'].'/index.php?'.$config['query_var'].'='.__CMS_PREVIEW_PAGE__;
    $tmp = $this->create_url($id,'admin_editcontent',$returnid,['preview'=>1]);
    $preview_ajax_url = rawurldecode(str_replace('&amp;','&',$tmp)).'&cmsjobtype=1';
}
else {
    $preview_url = '';
    $preview_ajax_url = '';
}

if( $this->GetPreference('template_list_mode','designpage') != 'all')  {
    $tmp = $this->create_url($id,'admin_ajax_gettemplates',$returnid);
    $designchanged_ajax_url = rawurldecode(str_replace('&amp;','&',$tmp)).'&cmsjobtype=1';
}
else {
    $designchanged_ajax_url = '';
}

$parms = [];
if( $content_id > 0 ) $parms['content_id'] = $content_id;
$tmp = $this->create_url($id,'admin_editcontent',$returnid,$parms);
$apply_ajax_url = rawurldecode(str_replace('&amp;','&',$tmp)).'&cmsjobtype=1';
$lock_timeout = $this->GetPreference('locktimeout');
$lock_refresh = $this->GetPreference('lockrefresh');
$do_locking = ($content_id > 0 && $lock_timeout > 0) ? 1:0;
$options_tab_name = $content_obj::TAB_OPTIONS;
$msg = json_encode($this->Lang('msg_lostlock'));
$script_url = CMS_SCRIPTS_URL;

$js = <<<EOS
<script type="text/javascript" src="{$script_url}/jquery.cmsms_dirtyform.js"></script>
<script type="text/javascript" src="{$script_url}/jquery.cmsms_lock.js"></script>
<script type="text/javascript">
//<![CDATA[
$(document).ready(function() {
  var do_locking = $do_locking;
  // initialize the dirtyform stuff
  $('#Edit_Content').dirtyForm({
    beforeUnload: function(is_dirty) {
      if (do_locking) $('#Edit_Content').lockManager('unlock').done(function() {
        console.log('after dirtyform unlock');
      });
    },
    unloadCancel: function() {
      if (do_locking) $('#Edit_Content').lockManager('relock');
    }
  });
  // initialize lock manager
  if (do_locking) {
    $('#Edit_Content').lockManager({
      type: 'content',
      oid: $content_id,
      uid: $user_id,
      lock_timeout: $lock_timeout,
      lock_refresh: $lock_refresh,
      error_handler: function(err) {
        cms_alert('{$this->Lang('lockerror')}: ' + err.type + ' -- ' + err.msg);
      },
      lostlock_handler: function(err) {
      // we lost the lock on this content... make sure we can't save anything.
      // and display a nice message.
        $('[name$=cancel]').fadeOut().attr('value', '{$this->Lang('close')}').fadeIn();
        $('#Edit_Content').dirtyForm('option', 'dirty', false);
        cms_alert($msg);
      }
    });
  }

EOS;

if ($preview_url) {
    $js .= <<<EOS
  $('#_preview_').on('click', function() {
    if (typeof tinyMCE !== 'undefined') tinyMCE.triggerSave();
    // serialize the form data
    var data = $('#Edit_Content').find('input:not([type=submit]), select, textarea').serializeArray();
    data.push({
      'name': '{$id}preview',
      'value': 1
    });
    data.push({
      'name': '{$id}ajax',
      'value': 1
    });
    $.post('$preview_ajax_url', data, function(resultdata, textStatus, jqXHR) {
      if (resultdata !== null && resultdata.response == 'Error') {
        $('#previewframe').attr('src', '').hide();
        $('#preview_errors').html('<ul></ul>');
        for (var i = 0; i < resultdata.details.length; i++) {
          $('#preview_errors').append('<li>' + resultdata.details[i] + '</li>');
        }
        $('#previewerror').show();
      } else {
        var x = new Date().getTime();
        var url = '{$preview_url}&junk=' + x;
        $('#previewerror').hide();
        $('#previewframe').attr('src', url).show();
      }
    }, 'json');
  });

EOS;
}
    $js .= <<<EOS
  // submit the form if disable wysiwyg, template id, and/or content-type fields are changed.
  $('#id_disablewysiwyg, #template_id, #content_type').on('change', function() {
    // disable the dirty form stuff, and unlock because we're gonna relockit on reload.
    var self = this;
    var this_id = $(this).attr('id');
    $('#Edit_Content').dirtyForm('disable');
    if (this_id != 'content_type') $('#active_tab').val('{$options_tab_name}');
    if (do_locking) {
      if (do_locking) $('#Edit_Content').lockManager('unlock', 1).done(function() {
        $(self).closest('form').submit();
      });
    } else {
      $(self).closest('form').submit();
    }
  });

  // handle cancel/close ... and unlock
  $('[name$=cancel]').on('click', function(ev) {
    // turn off all required elements, we're cancelling
    $('#Edit_Content :hidden').removeAttr('required');
    // do not touch the dirty flag, so that theunload handler stuff can warn us.
    if (do_locking) {
      // unlock the item, and submit the form.
      var self = this;
      var form = $(this).closest('form');
      ev.preventDefault();
      $('#Edit_Content').lockManager('unlock', 1).done(function() {
        var el = $('<input type="hidden"/>');
        el.attr('name', $(self).attr('name')).val($(self).val()).appendTo(form);
        form.submit();
      });
    }
  });

  $('[name$=submit]').on('click', function(ev) {
    // set the form to not dirty.
    $('#Edit_Content').dirtyForm('option', 'dirty', false);
    if (do_locking) {
      // unlock the item, and submit the form
      var self = this;
      ev.preventDefault();
      var form = $(this).closest('form');
      $('#Edit_Content').lockManager('unlock', 1).done(function() {
        var el = $('<input type="hidden"/>');
        el.attr('name', $(self).attr('name')).val($(self).val()).appendTo(form);
        form.submit();
      });
    }
  });

  // handle apply (ajax submit)
  $('[name$=apply]').on('click', function() {
    // apply does not do an unlock.
    if (typeof tinyMCE !== 'undefined') tinyMCE.triggerSave(); // TODO this needs better approach, create a common "ajax save" function that can be reused
    var data = $('#Edit_Content').find('input:not([type=submit]), select, textarea').serializeArray();
    data.push({
      'name': '{$id}ajax',
      'value': 1
    });
    data.push({
      'name': '{$id}apply',
      'value': 1
    });
    $.ajax({
      type: 'POST',
      url: '{$apply_ajax_url}',
      data: data,
      dataType: 'json',
    }).done(function(data, text) {
      var event = $.Event('cms_ajax_apply');
      event.response = data.response;
      event.details = data.details;
      event.close = '{$this->Lang('close')}';
      if (typeof data.url !== 'undefined' && data.url !== '') event.url = data.url;
      $('body').trigger(event);
    });
    return false;
  });

  $(document).on('cms_ajax_apply', function(e) {
    $('#Edit_Content').dirtyForm('option', 'dirty', false);
    if (typeof e.url !== 'undefined' && e.url !== '') {
      $('a#viewpage').attr('href', e.url);
    }
  });

EOS;
if ($designchanged_ajax_url) {
    $msg = json_encode($this->Lang('warn_notemplates_for_design'));
    $js .= <<<EOS
  $('#design_id').change(function(e, edata) {
    var v = $(this).val();
    var lastValue = $(this).data('lastValue');
    var data = {'{$id}design_id': v};
    $.get('$designchanged_ajax_url', data, function(data, text) {
      if (typeof data == 'object') {
        var sel = $('#template_id').val();
        var fnd = false;
        var first = null;
        for (var key in data) {
          if (!data.hasOwnProperty(key)) continue;
          if (first === null) first = key;
          if (key == sel) fnd = true;
        }
        if (!first) {
          $('#design_id').val(lastValue);
          cms_alert($msg);
        } else {
          $('#template_id').val('');
          $('#template_id').empty();
          for (key in data) {
            if (!data.hasOwnProperty(key)) continue;
            $('#template_id').append('<option value="' + key + '">' + data[key] + '</option>');
          }
          if (fnd) {
            $('#template_id').val(sel);
          } else if (first) {
            $('#template_id').val(first);
          }
          if (typeof edata === 'undefined' || typeof edata.skip_fallthru === 'undefined') {
            $('#template_id').trigger('change');
          }
        }
      }
    }, 'json');
  });

  $('#design_id').trigger('change', [{ skip_fallthru: 1 }]);
  $('#design_id').data('lastValue', $('#design_id').val());
  $('#template_id').data('lastValue', $('#template_id').val());
  $('#Edit_Content').dirtyForm('option', 'dirty', false);

EOS;
}
    $js .= <<<EOS
});
//]]>
</script>
EOS;
$this->AdminBottomContent($js);

$tpl->assign('active_tab',$active_tab)
 ->assign('content_id',$content_id)
 ->assign('content_obj',$content_obj)
 ->assign('tab_names',$tab_names)
 ->assign('tab_contents_array',$tab_contents_array)
 ->assign('tab_message_array',$tab_message_array);
/*$factory = new ContentAssistantFactory($content_obj);
  $assistant = $factory->getEditContentAssistant(); */
/* if( is_object($assistant) ) $tpl->assign('extra_content',$assistant->getExtraCode()); */

$tpl->display();
