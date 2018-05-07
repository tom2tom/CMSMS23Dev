{if isset($tabbed)}
{tab_header name='jobs' label=$mod->Lang('jobs')}
{tab_header name='settings' label=$mod->Lang('settings')}
{tab_start name='jobs'}
{/if}
<div class="pageinfo">{$mod->Lang('info_background_jobs')}</div>

{if !count($jobs)}
  <div style="text-align: center;">
    <div class="pageinfo">{$mod->Lang('info_no_jobs')}</div>
  </div>
{else}
  <table class="pagetable">
    <thead>
      <tr>
        <th>{$mod->Lang('name')}</th>
        <th>{$mod->Lang('module')}</th>
        <th>{$mod->Lang('created')}</th>
        <th>{$mod->Lang('start')}</th>
        <th>{$mod->Lang('frequency')}</th>
        <th>{$mod->Lang('until')}</th>
        <th>{$mod->Lang('errors')}</th>
        <th class="pageicon"></th>
      </tr>
    </thead>
    <tbody>
    {foreach $jobs as $job}
      <tr class="{cycle values='row1,row2'}">
        <td>{$job->name}</td>
        <td>{$job->module|default:''}</td>
        <td>{$job->created|relative_time}</td>
        <td>
           {if $job->start < $smarty.now - $jobinterval}<span style="color: red;">
           {elseif $job->start < $smarty.now + $jobinterval}<span style="color: green;">
           {else}<span>
           {/if}
           {$job->start|relative_time}</span>
        </td>
        <td>{$recur_list[$job->frequency]}</td>
        <td>{if $job->until}{$job->until|date_format:'%x %X'}{/if}</td>
        <td>{if $job->errors > 0}<span style="color: red;">{$job->errors}</span>{/if}</td>
        <td></td>
      </tr>
    {/foreach}
    </tbody>
  </table>
{/if}

{if isset($tabbed)}
{tab_start name='settings'}
{form_start action='defaultadmin'}
  <p class="pagetext">
    {$t=$mod->Lang('prompt_enabled')}<label for="enabled">{$t}:</label>
    {cms_help realm=$_module key2='help_enabled' title={$t}}
  </p>
  <p class="pageinput">
    <input type="checkbox" id="enabled" name="{$actionid}enabled" value="1"{if $enabled} checked="checked"{/if} />
  </p>
  <p class="pagetext">
    {$t=$mod->Lang('prompt_frequency')}<label for="interval">{$t}:</label>
    {cms_help realm=$_module key2='help_frequency' title={$t}}
  </p>
  <p class="pageinput">
    <input type="text" id="interval" name="{$actionid}jobinterval" value="{$jobinterval}" size="4" maxlength="2" />
  </p>
  <p class="pagetext">
    {$t=$mod->Lang('prompt_timelimit')}<label for="timeout">{$t}:</label>
    {cms_help realm=$_module key2='help_timelimit' title={$t}}
  </p>
  <p class="pageinput">
    <input type="text" id="timeout" name="{$actionid}jobtimeout" value="{$jobtimeout}" size="4" maxlength="4" />
  </p>
  <p class="pagetext">
    {$t=$mod->Lang('prompt_joburl')}<label for="url">{$t}:</label>
    {cms_help realm=$_module key2='help_joburl' title={$t}}
  </p>
  <p class="pageinput">
    <input type="text" id="url" name="{$actionid}joburl" value="{$joburl}" size="40" />
  </p>
  <div class="pageinput pregap">
    <button type="submit" name="{$actionid}apply" class="adminsubmit icon apply">{$mod->Lang('apply')}</button>
    <button type="submit" name="{$actionid}cancel" class="adminsubmit icon cancel">{$mod->Lang('cancel')}</button>
  </div>
</form>
{tab_end}
{/if}
