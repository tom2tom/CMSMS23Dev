<?php
# CMSModuleManager module function: populate installed-modules tab
# Copyright (C) 2008-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
# This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.

use ModuleManager\module_info;

if( !isset($gCms) ) exit;
if( !$this->CheckPermission('Modify Modules') ) return;

try {
    $allmoduleinfo = module_info::get_all_module_info($connection_ok);
    uksort($allmoduleinfo,'strnatcasecmp');
}
catch( Exception $e ) {
    $this->SetError($e->GetMessage());
    return;
}

$yes = $this->Lang('yes');
$s1 = json_encode($this->Lang('confirm_upgrade'));
$s2 = json_encode($this->Lang('confirm_remove'));
$s3 = json_encode($this->Lang('confirm_chmod'));
$s4 = json_encode($this->Lang('error_nofileuploaded'));
$s5 = json_encode($this->Lang('error_invaliduploadtype'));

$js = <<<EOS
<script type="text/javascript">
//<![CDATA[
$(document).ready(function() {
  $('a.mod_upgrade').on('click', function(ev) {
    ev.preventDefault();
    cms_confirm_linkclick(this,$s1,'$yes');
    return false;
  });
  $('a.mod_remove').on('click', function(ev) {
    ev.preventDefault();
    cms_confirm_linkclick(this,$s2,'$yes');
    return false;
  });
  $('a.mod_chmod').on('click', function(ev) {
    ev.preventDefault();
    cms_confirm_linkclick(this,$s3,'$yes');
    return false;
  });
  $('#importbtn').on('click', function() {
    cms_dialog($('#importdlg'), {
      modal: true,
      buttons: {
        {$this->Lang('submit')}: function() {
          var file = $('#xml_upload').val();
          if(file.length == 0) {
            cms_alert($s4);
          } else {
            var ext = file.split('.').pop().toLowerCase();
            if($.inArray(ext, ['xml','cmsmod']) == -1) {
              cms_alert($s5);
            } else {
              $(this).dialog('close');
              $('#local_import').submit();
            }
          }
        },
        {$this->Lang('cancel')}: function() {
          $(this).dialog('close');
        }
      }
    });
  });
});
//]]>
</script>
EOS;
$this->AdminBottomContent($js);

$tpl = $smarty->createTemplate($this->GetTemplateResource('admin_installed.tpl'),null,null,$smarty);

$tpl->assign('module_info',$allmoduleinfo);
$devmode = !empty($config['developer_mode']);
$tpl->assign('allow_export',($devmode)?1:0);
if ($devmode) {
    $tpl->assign('iconsurl',$this->GetModuleURLPath().'/images');
}
$tpl->assign('allow_modman_uninstall',$this->GetPreference('allowuninstall',0));

$tpl->display();

