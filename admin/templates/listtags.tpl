{if isset($subheader)}
  <div class="pageheader">{$subheader}
   {if isset($wiki_url) && isset($image_help_external)}
    <span class="helptext">
      <a href="{$wiki_url}" target="_blank" class="helpicon">{$image_help_external}</a>
      <a href="{$wiki_url}" target="_blank">{lang('help')}</a> ({lang('new_window')})
    </span>
   {/if}
  </div>
 {else}
  <div class="information">
   <p>{lang_by_realm('tags','tag_info')}<br />{lang_by_realm('tags','tag_info3')}</p>
  </div>
{/if}
{if !empty($pdev)}
<div class="pageoverflow pregap">
  <p class="pagetext">{lang('upload_plugin_file')}</p>
  <form action="{$selfurl}{$urlext}" method="post" enctype="multipart/form-data">
  <p class="pageinput"><input type="file" name="pluginfile" size="30" maxlength="255" accept="application/x-php" /></p>
  <div class="pageinput pregap">
   <button type="submit" name="upload" class="adminsubmit icon do">{lang('submit')}</button>
  </div>
  </form>
</div>
<br />
{/if}
{if isset($content)}
  {$content}
{elseif isset($plugins)}
  <table class="pagetable">
    <thead>
     <tr>
       <th title="{lang_by_realm('tags','tag_name')}">{lang('name')}</th>
       <th title="{lang_by_realm('tags','tag_type')}">{lang('type')}</th>
{*       <th title="{lang_by_realm('tags','tag_cachable')}">{lang('cachable')}</th> *}
       <th title="{lang_by_realm('tags','tag_adminplugin')}">{lang('adminplugin')}</th>
       <th title="{lang_by_realm('tags','tag_help')}">{lang('help')}</th>
       <th title="{lang_by_realm('tags','tag_about')}">{lang('about')}</th>
     </tr>
    </thead>
    <tbody>
      {foreach $plugins as $one}
      <tr class="{cycle values='row1,row2'}">
       {strip}
       <td>
        {$one.name}
       </td>
       <td>
          <span title="{lang_by_realm('tags',$one.type)}">{$one.type}</span>
       </td>
{*       <td style="text-align:center;">
         {if empty($one.cachable)}{$iconcno}{else}{$iconcyes}{/if}
       </td> *}
       <td style="text-align:center;">
         {if empty($one.admin)}{$iconno}{else}{$iconyes}{/if}
       </td>
       <td style="text-align:center;">
         {if isset($one.help_url)}
           <a href="{$one.help_url}" title="{lang_by_realm('tags','viewhelp')}">{$iconhelp}</a>
         {/if}
       </td>
       <td style="text-align:center;">
         {if isset($one.about_url)}
           <a href="{$one.about_url}" title="{lang_by_realm('tags','viewabout')}">{$iconabout}</a>
         {/if}
       </td>
{/strip}
     </tr>
    {/foreach}
    </tbody>
  </table>
{/if}
