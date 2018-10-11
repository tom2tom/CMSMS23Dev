<?php
/*
AdminLog module action: display the log
Copyright (C) 2017-2018 CMS Made Simple Foundation <foundationcmsmadesimple.org>
This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
*/

use AdminLog\filter;
use AdminLog\resultset;

if( !isset($gCms) ) exit;
if( !$this->VisibleToAdminUser() ) exit;

$page = 1;
$filter_applied = false;
$filter = new filter();
if( isset($_SESSION['adminlog_filter']) && $_SESSION['adminlog_filter'] instanceof filter ) {
    $filter = $_SESSION['adminlog_filter'];
    $filter_applied = true;
}

if( isset($params['filter']) ) {
    $filter = new filter();
    unset($_SESSION['adminlog_filter']);
    $filter_applied = false;
}
if( isset($params['filter']) ) {
    $filter->severity = (int) get_parameter_value($params,'f_sev');
    $filter->username = trim(get_parameter_value($params,'f_user'));
    $filter->subject = trim(get_parameter_value($params,'f_subj'));
    $filter->msg = trim(get_parameter_value($params,'f_msg'));
    $_SESSION['adminlog_filter'] = $filter;
    $filter_applied = true;
}
if( ($page = (int) get_parameter_value($params,'page',1)) > 1 ) {
    $filter->offset = ($page - 1) * $filter->limit;
}

$resultset = new resultset( $db, $filter );

$pagelist = [];
if( $resultset->numpages > 0 ) {
    if( $resultset->numpages < 25 ) {
        for( $i = 1; $i <= $resultset->numpages; $i++ ) {
            $pagelist[$i] = $i;
        }
    }
    else {
        // first 5
        for( $i = 0; $i <= 5; $i++ ) {
            $pagelist[$i] = $i;
        }
        $tpage = $page;
        if( $tpage <= 5 || $tpage >= ($resultset->numpages - 5) ) $tpage = $resultset->numpages / 2;
        $x1 = max(1,(int)($tpage - 5 / 2));
        $x2 = min($resultset->numpages,(int)($tpage + 5 / 2));
        for( $i = $x1; $i <= $x2; $i++ ) {
            $pagelist[] = $i;
        }
        for( $i = max(1,$resultset->numpages - 5); $i <= $resultset->numpages; $i++ ) {
            $pagelist[] = $i;
        }
        $pagelist = array_unique($pagelist);
        sort($pagelist);
        $pagelist = array_combine($pagelist,$pagelist);
    }
}
$results = $resultset->GetMatches();

$url = $this->create_url($id,'defaultadmin',$returnid,['page'=>'xxx']);
$url = str_replace('&amp;','&',$url);

$js = <<<EOS
<script type="text/javascript">
//<![CDATA[
$(document).ready(function() {
  $('#pagenum').on('change', function() {
    var v = $(this).val();
    var t_url = '$url'.replace('xxx', v);
    window.location = t_url;
  });
  $('#filterbtn').on('click', function() {
    cms_dialog($('#filter_dlg'), {
      open: function(ev, ui) {
        cms_equalWidth($('#filter_dlg label.boxchild'));
      },
      modal: true,
      width: 'auto',
      height: 'auto'
    });
  });
});
//]]>
</script>
EOS;
$this->AdminBottomContent($js);

$severity_list = [
 $this->Lang('sev_msg'),
 $this->Lang('sev_notice'),
 $this->Lang('sev_warning'),
 $this->Lang('sev_error'),
];

$tpl = $smarty->createTemplate( $this->GetTemplateResource('admin_log_tab.tpl'),null,null,$smarty);
$tpl->assign('filter',$filter)
 ->assign('filterimage',cms_join_path(__DIR__,'images','filter'))
 ->assign('results',$results)
 ->assign('pagelist',$pagelist)
 ->assign('severity_list',$severity_list)
 ->assign('page',$page)
 ->assign('filter_applied',$filter_applied);

$tpl->display();
