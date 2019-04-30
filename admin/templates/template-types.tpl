{if $list_all_types}
<table id="typelist" class="pagetable" style="width:auto;">
  <thead>
  <tr>
   <th>{lang_by_realm('layout','prompt_id')}</th>
   <th>{lang_by_realm('layout','prompt_name')}</th>
   <th class="pageicon nosort"></th>
  </tr>
  </thead>
  <tbody>
{strip}
  {foreach $list_all_types as $type} {$tid=$type->get_id()}
   {$url="edittpltype.php`$urlext`&amp;type=`$tid`"}
   {cycle values="row1,row2" assign='rowclass'}
{/strip}
   <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
    <td>{$tid}</td>{strip}
    <td>{$tmp=$type->get_description()}
     <a href="{$url}"{if $tmp} class="action tooltip" data-cms-description="{$tmp|summarize}"{else} class="action"{/if} title="{lang_by_realm('layout','title_edit_type')}">{$type->get_langified_display_value()}</a>
    </td>
    <td>
    {$ul=!$type->locked()}
    {$t=lang_by_realm('layout','prompt_locked')}
     <span class="locked" data-type-id="{$tid}" title="{$t}"{if $ul} style="display:none;"{/if}>{admin_icon icon='icons/extra/block.gif' title=$t}</span>
    {$t=lang_by_realm('layout','prompt_steal_lock')}
    <a class="steal_lock" href="{$url}&amp;steal=1" data-type-id="{$tid}" title="{$t}" accesskey="e"{if $ul} style="display:none;"{/if}>{admin_icon icon='permissions.gif' title=$t}</a>
    {$t=lang_by_realm('layout','title_edit_type')}
    <a class="action" href="{$url}" title="{$t}"{if !$ul} style="display:none;"{/if}>{admin_icon icon='edit.gif' title=$t}</a>
    {if $type->get_dflt_flag()}
    {$t=lang_by_realm('layout','title_reset_factory')}
     <a class="action" href="templateoperations.php{$urlext}&amp;op=reset&amp;type={$tid}" title="{$t}"{if !$ul} style="display:none;"{/if}>{admin_icon icon='icons/extra/reset.gif' title=$t}</a>
    {/if}
    </td>{/strip}
   </tr>
  {/foreach}
  </tbody>
</table>
{else}
<p class="information">{lang_by_realm('layout','info_no_types')}</p>
{/if}