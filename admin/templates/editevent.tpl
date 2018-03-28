<form action="{$selfurl}{$urlext}" method="post">
  <div class="hidden">
    <input type="hidden" name="module" value="{$module}" />
    <input type="hidden" name="event" value="{$event}" />
  </div>
  <div class="pageoverflow">
    <p class="pagetext">{lang('module_name')}:</p>
    <p class="pageinput">{$modulename}</p>
  </div>
  <div class="pageoverflow">
    <p class="pagetext">{lang('event_name')}:</p>
    <p class="pageinput">{$event}</p>
  </div>
  <div class="pageoverflow">
    <p class="pagetext">{lang('event_description')}</p>
    <p class="pageinput">{$description}</p>
  </div>
  <br />
  <table class="pagetable">
  <thead>
    <tr>
      <th>{lang('order')}</th>
      <th>{lang('user_tag')}</th>
      <th>{lang('module')}</th>
      <th class="pageicon">&nbsp;</th>
      <th class="pageicon">&nbsp;</th>
      <th class="pageicon">&nbsp;</th>
    </tr>
  </thead>
{if $handlers}
  <tbody>{foreach $handlers as $one}
    <tr class="{cycle values='row1,row2'}">
      {strip}
      <td>{$one->handler_order}</td>
      <td>{$one->tag_name}</td>
      <td>{$one->module_name}</td>
      <td>
      {if !$one@first}
      <a href="{$selfurl}{$urlext}&amp;action=up&amp;order={$one->handler_order}&amp;handler={$one->handler_id}">{$iconup}</a>
      {/if}
      </td>
      <td>
      {if !$one@last}
      <a href="{$selfurl}{$urlext}&amp;action=down&amp;order={$one->handler_order}&amp;handler={$one->handler_id}">{$icondown}</a>
      {/if}
      </td>
      <td>
      {if $one->removable}
      <a href="{$selfurl}{$urlext}&amp;action=delete&amp;handler={$one->handler_id}" onclick="return confirm('{cms_html_entity_decode(lang('deleteconfirm', $one->name))}');">{$icondel}</a>
      {/if}
      </td>
{/strip}
    </tr>
  {/foreach}</tbody>
{/if}
  </table>
{if $allhandlers}
  <br />
  <select name="handler">
  {foreach $allhandlers as $key => $value}
  <option value="{$value}">{$key}</option>
  {/foreach}
  </select>
{/if}
  <div class="pageinput pregap">
    TODO why add when editing
    <button type="submit" name="create" class="adminsubmit icon add">{lang('add')}</button>
  </div>
</form>
