<div class="five-col search noprint" role="search">
{form_start action=dosearch method=$form_method returnid=$destpage inline=$inline}
  {if isset($hidden)}{$hidden}{/if}
  <label for="{$search_actionid}searchinput" class="visuallyhidden">{$searchprompt}:</label>
  <input type="search" class="search-input" id="{$search_actionid}searchinput" name="{$search_actionid}searchinput" size="20" maxlength="50" value="" placeholder="{$searchtext}" /><i class="icon-search" aria-hidden="true"></i>
</form>
</div>
