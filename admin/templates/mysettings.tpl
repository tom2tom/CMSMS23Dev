<form action="{$selfurl}{$urlext}" method="post">
  <input type="hidden" name="old_default_cms_lang" value="{$old_default_cms_lang}" />
  <div class="pageinput postgap">
    <button type="submit" name="submit" class="adminsubmit icon check">{lang('submit')}</button>
    <button type="submit" name="cancel" class="adminsubmit icon cancel">{lang('cancel')}</button>
  </div>

  <fieldset>
    <legend>{lang('lang_settings_legend')}:</legend>
    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('language')}<label for="language">{$t}:</label>
        {cms_help key2='help_myaccount_language' title=$t}
      </p>
      <p class="pageinput">
        <select id="language" name="default_cms_language">
          {html_options options=$language_opts selected=$default_cms_language}
        </select>
      </p>
    </div>
    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('date_format_string')}<label for="dateformat">{$t}:</label>
        {cms_help key2='help_myaccount_dateformat' title=$t}
      </p>
      <p class="pageinput">
        <input class="pagenb" size="20" maxlength="255" type="text" name="date_format_string" value="{$date_format_string}" />
      </p>
    </div>
  </fieldset>

  <fieldset class="pregap">
    <legend>{lang('content_editor_legend')}:</legend>
    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('wysiwygtouse')}<label for="wysiwyg">{$t}:</label>
        {cms_help key2='help_myaccount_wysiwyg' title=$t}
      </p>
      <p class="pageinput">
        <select id="wysiwyg" name="wysiwyg">
          {html_options options=$wysiwyg_opts selected=$wysiwyg}
        </select>
      </p>
    </div>

    <div class="pageoverflow">
      <p class="pagetext">
         {$t=lang('syntaxhighlightertouse')}<label for="syntaxh">{$t}:</label>
         {cms_help key2='help_myaccount_syntax' title=$t}
      </p>
      <p class="pageinput">
        <select id="syntaxh" name="syntaxhighlighter">
         {html_options options=$syntax_opts selected=$syntaxhighlighter}
        </select>
      </p>
    </div>

    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('ce_navdisplay')}<label for="ce_navdisplay">{$t}:</label>
        {cms_help key2='help_myaccount_ce_navdisplay' title=$t}
      </p>
      <p class="pageinput">
        {$opts['']=lang('none')} {$opts['menutext']=lang('menutext')} {$opts['title']=lang('title')}
        <select id="ce_navdisplay" name="ce_navdisplay">
          {html_options options=$opts selected=$ce_navdisplay}
        </select>
      </p>
    </div>

    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('adminindent')}<label for="indent">{$t}:</label>
        {cms_help key2='help_myaccount_indent' title=$t}
      </p>
      <p class="pageinput">
        <input class="pagenb" type="checkbox" id="indent" name="indent"{if $indent} checked="checked"{/if} />
      </p>
    </div>

    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('defaultparentpage')}<label for="parent_id">{$t}:</label>
        {cms_help key2='help_myaccount_dfltparent' title=$t}
      </p>
      <p class="pageinput">{$default_parent}</p>
    </div>
    <!-- content display //-->
  </fieldset>

  <fieldset class="pregap">
    <legend>{lang('general_operation_settings')}:</legend>
    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('admintheme')}<label for="admintheme">{$t}:</label>
        {cms_help key2='help_myaccount_admintheme' title=$t}
      </p>
      <p class="pageinput">
        <select id="admintheme" name="admintheme">
         {html_options options=$themes_opts selected=$admintheme}
        </select>
      </p>
    </div>
    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('text_editor_theme')}<label for="editortheme">{$t}:</label>
        {cms_help key2='settings_acetheme' title=$t}
      </p>
      <p class="pageinput">
        <input id="editortheme" type="text" name="ace_theme" size="30" value="{$acetheme}" maxlength="40" />
      </p>
    </div>
    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('homepage')}<label for="homepage">{$t}:</label>
        {cms_help key2='help_myaccount_homepage' title=$t}
      </p>
      <p class="pageinput">
        {$homepage}
      </p>
    </div>
    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('admincallout')}<label for="admincallout">{$t}:</label>
        {cms_help key2='help_myaccount_admincallout' title=$t}
      </p>
      <p class="pageinput">
        <input class="pagenb" id="admincallout" type="checkbox" name="bookmarks"{if $bookmarks} checked="checked"{/if} />
      </p>
    </div>
    <div class="pageoverflow">
      <p class="pagetext">
        {$t=lang('hide_help_links')}<label for="hidehelp">{$t}:</label>
        {cms_help key2='help_myaccount_hidehelp' title=$t}
      </p>
      <p class="pageinput">
        <input class="pagenb" id="hidehelp" type="checkbox" name="hide_help_links"{if $hide_help_links} checked="checked"{/if} />
      </p>
    </div>
  </fieldset>

  <div class="pageinput pregap">
    <button type="submit" name="submit" class="adminsubmit icon check">{lang('submit')}</button>
    <button type="submit" name="cancel" class="adminsubmit icon cancel">{lang('cancel')}</button>
  </div>
</form>
