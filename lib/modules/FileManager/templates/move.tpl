<h3>{$mod->Lang('prompt_move')}</h3>
<p class="pageoverflow">{$mod->Lang('info_move')}:</p>

{$startform}
<div class="pageoverflow">
  <p class="pagetext">{$mod->Lang('itemstomove')}:</p>
  <p class="pageinput">
    <ul>
    {foreach $selall as $one}
      <li>{$one}</li>
    {/foreach}
    </ul>
  </p>
</div>
<div class="pageoverflow">
  <p class="pagetext">
    <label for="destdir">{$mod->Lang('move_destdir')}:</label>
  </p>
  <p class="pageinput">
    <select id="destdir" name="{$actionid}destdir">
    {html_options options=$dirlist selected=$cwd}
    </select>
  </p>
</div>
<div class="pageoverflow">
  <p class="pagetext"></p>
  <p class="pageinput">
    <button type="submit" name="{$actionid}submit" class="adminsubmit icondo">{$mod->Lang('move')}</button>
    <button type="submit" name="{$actionid}cancel" class="adminsubmit iconcancel">{$mod->Lang('cancel')}</button>
  </p>
</div>
{$endform}
