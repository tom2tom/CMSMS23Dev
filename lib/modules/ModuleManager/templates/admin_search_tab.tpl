<script type="text/javascript">
{literal}//<![CDATA[
$(document).ready(function() {
  {/literal}{if !$advanced}$('#advhelp').hide();{/if}{literal}
  $('#advanced').click(function() {
    $('#advhelp').toggle();
  });
});
{/literal}//]]>
</script>

{function get_module_status_icon}
{strip}
{if $status == 'stale'}
 {$stale_img}
{elseif $status == 'warn'}
 {$warn_img}
{elseif $status == 'new'}
 {$new_img}
{/if}
{/strip}
{/function}

{$formstart}
<fieldset>
<legend>{$mod->Lang('search_input')}:</legend>
<div class="pageoverflow">
  <p class="pagetext">
      <label for="searchterm">{$mod->Lang('searchterm')}:</label>
  </p>
  <p class="pageinput">
    <input id="searchterm" type="text" name="{$actionid}term" size="50" value="{$term}" title="{$mod->Lang('title_searchterm')}" placeholder="{$mod->Lang('entersearchterm')}" />&nbsp;
    <input type="hidden" name="{$actionid}advanced" value="0" />
    <input type="checkbox" id="advanced" name="{$actionid}advanced" value="1"{if $advanced} checked="checked"{/if} title="{$mod->Lang('title_advancedsearch')}" />&nbsp;<label for="advanced">{$mod->Lang('prompt_advancedsearch')}</label>
    <span id="advhelp" style="display: none;"><br />{$mod->Lang('advancedsearch_help')}</span>
  </p>
</div>

<div class="pageoverflow">
  <p class="pagetext"></p>
  <p class="pageinput">
    <button type="submit" name="{$actionid}submit" class="adminsubmit iconcheck">{$mod->Lang('submit')}</button>
  </p>
</div>
</fieldset>
{$formend}

{if isset($search_data)}
<fieldset>
  <legend>{$mod->Lang('search_results')}:</legend>
  <table class="pagetable scrollable">
    <thead>
      <tr>
        <th></th>
        <th>{$mod->Lang('nametext')}</th>
        <th><span title="{$mod->Lang('title_modulelastversion')}">{$mod->Lang('vertext')}</span></th>
        <th><span title="{$mod->Lang('title_modulelastreleasedate')}">{$mod->Lang('releasedate')}</span></th>
        <th><span title="{$mod->Lang('title_moduletotaldownloads')}">{$mod->Lang('downloads')}</span></th>
        <th><span title="{$mod->Lang('title_modulestatus')}">{$mod->Lang('statustext')}</span></th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
        <th>&nbsp;</th>
      </tr>
    </thead>
    <tbody>
      {foreach $search_data as $entry}
      <tr class="{cycle values='row1,row2'}"{if $entry->age=='new'} style="font-weight:bold;"{/if}>
        <td>{get_module_status_icon status=$entry->age}</td>
        <td><span title="{$entry->description|strip_tags|cms_escape}">{$entry->name}</span></td>
        <td>{$entry->version}</td>
        <td>{$entry->date|date_format:'%x'}</td>
        <td>{$entry->downloads}</td>
        <td>
         {if $entry->candownload}
          <span title="{$mod->Lang('title_moduleinstallupgrade')}">{$entry->status}</span>
         {else}
          {$entry->status}
         {/if}
        </td>
        <td><a href="{$entry->depends_url}" title="{$mod->Lang('title_moduledepends')}">{$mod->Lang('dependstxt')}</a></td>
        <td><a href="{$entry->help_url}" title="{$mod->Lang('title_modulehelp')}">{$mod->Lang('helptxt')}</a></td>
        <td><a href="{$entry->about_url}" title="{$mod->Lang('title_moduleabout')}">{$mod->Lang('abouttxt')}</a></td>
      </tr>
      {/foreach}
    </tbody>
  </table>
</fieldset>
{/if}
