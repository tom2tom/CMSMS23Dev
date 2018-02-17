<script type="text/javascript">
{literal}//<![CDATA[
$(document).ready(function() {
  $('a.delusertag').click(function(ev) {
    ev.preventDefault();
    var _hr = $(this).attr('href');
    cms_confirm({/literal}'{lang("confirm_deleteusertag")|cms_escape:"javascript"}'{literal}).done(function() {
      window.location.href = _hr;
    });
  });
});
{/literal}//]]>
</script>

<div class="pagecontainer">
  {if $padd}
  <div class="pageoptions">
    <a href="{$addurl}{$urlext}" title="{lang('addusertag')}">$iconadd</a>
    <a href="{$addurl}{$urlext}">{lang('addusertag')}</a>
  </div>
  <br />
  {/if}
  {$maintitle}
  <table class="pagetable">
    <thead>
      <tr>
        <th>{lang('name')}</th>
        <th>{lang('description')}</th>
        {if $access}
        <th class="pageicon">&nbsp;</th>
        <th class="pageicon">&nbsp;</th>
        {/if}
      </tr>
    </thead>
    <tbody>
      {foreach $tags as $tag_id => $tag}
      <tr class="{cycle values='row1,row2'}">
        {strip}
        <td>
         {if $access}
          <a href="{$editurl}{$urlext}&amp;plugin_id={$tag_id}" title="{lang('editusertag')}">{$tag.name}</a>
         {else}
          {$tag.name}
         {/if}
        </td>
        <td>{$tag.description}</td>
        {if $access}
        <td>
          <a href="{$editurl}{$urlext}&amp;plugin_id={$tag_id}">{$iconedit}</a>
        </td>
        <td>
          <a href="{$deleteurl}{$urlext}&amp;plugin_id={$tag_id}" class="delusertag">{$icondel}</a>
        </td>
        {/if}
{/strip}
      </tr>
      {/foreach}
    </tbody>
  </table>
  {if $padd && count($tags) > 20}
  <br />
  <div class="pageoptions">
    <a href="{$addurl}{$urlext}" title="{lang('addusertag')}">$iconadd</a>
    <a href="{$addurl}{$urlext}">{lang('addusertag')}</a>
  </div>
  {/if}
</div>
