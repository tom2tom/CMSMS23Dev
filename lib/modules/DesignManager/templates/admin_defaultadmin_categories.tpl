{if isset($list_categories) && count($list_categories) > 1}
  <div class="pagewarn">{$mod->Lang('warning_category_dragdrop')}</div>
{/if}{* list_categories *}

<div class="pageinfo">{$mod->Lang('info_about_categories')}</div>
<div class="pageoptions">
  {cms_action_url action='admin_edit_category' assign='url'}
  <a href="{$url}" title="{$mod->Lang('create_category')}">{admin_icon icon='newobject.gif'}</a>
  <a href="{$url}">{$mod->Lang('create_category')}</a>
</div>

{if isset($list_categories)}
<table id="categorylist" class="pagetable">
  <thead>
    <tr>
      <th style="width:5%;" title="{$mod->Lang('title_cat_id')}">{$mod->Lang('prompt_id')}</th>
      <th title="{$mod->Lang('title_cat_name')}">{$mod->Lang('prompt_name')}</th>
      <th class="pageicon"></th>
      <th class="pageicon"></th>
    </tr>
  </thead>
  <tbody>
    {foreach $list_categories as $category}
    {cms_action_url action='admin_edit_category' cat=$category->get_id() assign='edit_url'}
    <tr class="{cycle values='row1,row2'} sortable-table" id="cat_{$category->get_id()}">
      <td><a href="{$edit_url}" title="{$mod->Lang('prompt_edit')}">{$category->get_id()}</a></td>
      <td><a href="{$edit_url}" title="{$mod->Lang('prompt_edit')}">{$category->get_name()}</a></td>
      <td><a href="{$edit_url}" title="{$mod->Lang('prompt_edit')}">{admin_icon icon='edit.gif'}</a></td>
      <td> {cms_action_url action='admin_delete_category' cat=$category->get_id() assign='delete_url'}
        <a href="{$delete_url}" class="del_cat" title="{$mod->Lang('prompt_delete')}">{admin_icon icon='delete.gif'}</a>
      </td>
    </tr>
    {/foreach}
  </tbody>
</table>
{/if}
