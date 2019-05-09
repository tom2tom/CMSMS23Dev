<p class="pageinfo postgap">{$mod->Lang('info_fields')}</p>
{if !empty($fieldcount)}
<table class="pagetable">
  <thead>
    <tr>
      <th>{$fielddeftext}</th>
      <th>{$typetext}</th>
      <th class="pageicon">&nbsp;</th>
      <th class="pageicon">&nbsp;</th>
      <th class="pageicon">&nbsp;</th>
      <th class="pageicon">&nbsp;</th>
    </tr>
  </thead>
  <tbody>
    {foreach $fields as $entry}
    <tr class="{cycle name='fields' values='row1,row2'}">
      <td>{$entry->name}</td>
      <td>{$entry->type}</td>
      <td>{$entry->uplink}</td>
      <td>{$entry->downlink}</td>
      <td>{$entry->editlink}</td>
      <td><a href="{$entry->delete_url}" class="del_fielddef">{admin_icon icon='delete.gif' alt=$mod->Lang('delete')}</a></td>
    </tr>
    {/foreach}
  </tbody>
</table>
{/if}
<div class="pageoptions">
  <a href="{$addurl}" title="{$mod->Lang('addfielddef')}">{admin_icon icon='newobject.gif'} {$mod->Lang('addfielddef')}</a>
</div>
