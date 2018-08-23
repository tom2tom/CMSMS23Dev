<?php

if( !isset($gCms) ) exit;
if( !$this->CheckPermission('Modify Site Preferences') ) return;

$prompt = json_encode($this->Lang('areyousure'));
$yes = $this->Lang('yes');

$js = <<<EOS
<script type="text/javascript">
//<![CDATA[
$(document).ready(function() {
 $('a.del_cat').on('click', function(ev) {
  ev.preventDefault();
  cms_confirm_linkclick(this,$prompt,'$yes');
  return false;
 });
 $('a.del_fielddef').on('click', function(ev) {
  ev.preventDefault();
  cms_confirm_linkclick(this,$prompt,'$yes');
  return false;
 });
});
{/literal}//]]>
</script>

EOS;
$this->AdminBottomContent($js);

$tpl = $smarty->createTemplate($this->GetTemplateResource('settings.tpl'),null,null,$smarty);

include __DIR__.DIRECTORY_SEPARATOR.'function.admin_categoriestab.php';
include __DIR__.DIRECTORY_SEPARATOR.'function.admin_customfieldstab.php';
include __DIR__.DIRECTORY_SEPARATOR.'function.admin_optionstab.php';

$tpl->display();
