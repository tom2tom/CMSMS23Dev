<!doctype html>
<html lang="{$lang_code}" dir="{$lang_dir}">
<head>
  <meta charset="{$encoding}" />
  <title>{$mod->Lang('title_login_named',{sitename})}</title>
  <base href="{$admin_url}/" />
  <meta name="generator" content="CMS Made Simple" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="viewport" content="initial-scale=1.0 maximum-scale=1.0" />
  <meta name="HandheldFriendly" content="true" />
  <link rel="shortcut icon" href="{$admin_url}/themes/assets/images/cmsms-favicon.ico" />
  <link rel="stylesheet" type="text/css" href="{$admin_url}/themes/Ghostgum/css/style{if $lang_dir == 'rtl'}-rtl{/if}.css" />
  {$header_includes|default:''}
</head>
<body>
 <div id="login">
  <div id="login-container">
    <div id="login-box">
      <a id="toggle-info" href="#" title="{$mod->Lang('open')}/{$mod->Lang('close')}">&nbsp;</a>
      {if empty($sitelogo)}
       <a id="goto" href="{root_url}" title="{$mod->Lang('goto',{sitename})}">&nbsp;</a>
      {else}
       <a href="{root_url}">
        <img id="sitelogo" src="{$sitelogo}" title="{$mod->Lang('goto',{sitename})}" alt="{sitename}" />
       </a>
      {/if}
      <h1>{if isset($smarty.get.forgotpw)}
       {$mod->Lang('title_recover',{sitename})}
      {elseif !empty($sitelogo)}
       {$mod->Lang('title_login')}
      {else}{$mod->Lang('title_login_named',{sitename})}{/if}</h1>
      {$form}
      {if !empty($smarty.get.forgotpw)}
       <div class="pageinfo">{$mod->Lang('title_forgotpw')}</div>
      {/if}
      {if !empty($error)}<div class="pageerror">{$error}</div>{/if}
      {if !empty($warning)}<div class="pagewarn">{$warning}</div>{/if}
      {if !empty($message)}<div class="pagesuccess">{$message}</div>{/if}
      {if !empty($changepwhash)}<div class="pageinfo">{$mod->Lang('passwordchange')}</div>{/if}
      <div id="info-wrapper">
       {$mod->Lang('login_info')}
       {$mod->Lang('login_info_params')}
       <p>{$smarty.server.HTTP_HOST}</p>
       <div class="pagewarn">{$mod->Lang('warn_ipandcookies')}</div>
      </div>
    </div>
    <div id="cmslogo">
      <span id="logotext">{$mod->Lang('power_by')}</span>
      <img src="{$admin_url}/themes/assets/images/CMSMS-logotext-dark.svg" onerror="this.onerror=null;this.src='{$admin_url}/themes/assets/images/CMSMS-logotext-dark.png';" alt="CMS Made Simple" height="30" />
    </div>
  </div>
 </div>
</body>
{$bottom_includes|default:''}
</html>
