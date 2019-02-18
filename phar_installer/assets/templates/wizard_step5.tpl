{* wizard step 5 *}
{extends file='wizard_step.tpl'}

{block name='logic'}
  {$subtitle = 'title_step5'|tr}
  {$current_step = '5'}
{/block}

{block name='contents'}

<div class="installer-form">
{wizard_form_start}
  {if $action != 'freshen'}
    <h3>{'prompt_sitename'|tr}</h3>
    <p>{'info_sitename'|tr}</p>
    <div class="row form-row">
      <div class="twelve-col">
        <input class="form-field required full-width" type="text" name="sitename" value="{$sitename}" placeholder="{'ph_sitename'|tr}" required="required" />
        <div class="corner red">
          <i class="icon-asterisk"></i>
        </div>
      </div>
    </div>

    <h3{if !$verbose} class="disabled"{/if}>{'prompt_helpurl'|tr}</h3>
    <p{if !$verbose} class="disabled"{/if}>{'info_helpurl'|tr}</p>
    <div class="row form-row">
      <div class="twelve-col">
        <input class="form-field full-width{if !$verbose} disabled{/if}" type="text" name="helpurl" value="{$helpurl}"{if $verbose} placeholder="{'ph_helpurl'|tr}"{else} disabled="disabled"{/if} />
      </div>
    </div>
  {/if}

  <h3>{'prompt_addlanguages'|tr}</h3>
  <p>{'info_addlanguages'|tr}</p>
  <div class="row form-row">
    <select class="form-field" name="languages[]" multiple="multiple" size="8">
      {html_options options=$language_list selected=$languages}
    </select>
  </div>

  {if !empty($modules_list)}
  <h3>{'prompt_addmodules'|tr}</h3>
  <p>{'info_addmodules'|tr}</p>
  <div class="row form-row">
    <select class="form-field" name="xmodules[]" multiple="multiple" size="3">
      {html_options options=$modules_list selected=$modules_sel}
    </select>
  </div>
  {/if}

  {if $action == 'install'}
  <h3>{'prompt_installcontent'|tr}</h3>
  <p>{'info_installcontent'|tr}</p>
  <div class="row form-row">
    <label for="demo">{'prompt_installcontent'|tr}</label>
    <select id="demo" class="form-field" name="samplecontent">
      {html_options options=$yesno selected=$config.samplecontent}
    </select>
  </div>
  {/if}

  <div id="bottom_nav">
   <button class="action-button positive" type="submit" name="next"><i class='icon-cog'></i> {'next'|tr}</button>
  </div>

{wizard_form_end}
</div>

{/block}
