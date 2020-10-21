<?php
/*
defaultadmin action for CMS Made Simple module: CmsJobManager
Copyright (C) 2016-2020 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
Thanks to Robert Campbell and all other contributors from the CMSMS Development Team.
See license details at the top of file CmsJobManager.module.php
*/

use CmsJobManager\JobStore;
use CmsJobManager\Utils;
use CMSMS\App;
use CMSMS\AppParams;
use CMSMS\Async\RecurType;

//$this->refresh_jobs(true); //DEBUG

if (!isset($gCms) || !($gCms instanceof App)) {
    exit;
}
if (!$this->VisibleToAdminUser()) {
    return '';
}

if (isset($params['apply']) && $this->CheckPermission('Modify Site Preferences')) {
    $this->SetPreference('enabled', !empty($params['enabled']));
    $t = max(2, min(120, (int)$params['jobtimeout']));
    AppParams::set('jobtimeout', $t);
    $t = max(1, min(10, (int)$params['jobinterval']));
    AppParams::set('jobinterval', $t * 60); // recorded as seconds
    $t = trim($params['joburl']);
    if ($t) {
        $t2 = filter_var($t, FILTER_SANITIZE_URL);
        if (filter_var($t2, FILTER_VALIDATE_URL)) {
            $this->SetPreference('joburl', $t2);
        } else {
            $this->ShowErrors($this - Lang('err_url'));
        }
    } else {
        $this->SetPreference('joburl', '');
    }
}

//DEBUG - DISABLE FOR PRODUCTION
if (1) {
    $u1 = $this->create_url($id, 'test1', $returnid, [], false, false, '', 1);
    $u2 = $this->create_url($id, 'test2', $returnid, [], false, false, '', 1);
    $js = <<<EOS
<script type="text/javascript">
//<![CDATA[
$(function() {
 $('body').append(
'<a id="simple1" href="$u1" class="link_button icon do">Simple Derived Class Test</a>' +
'<a href="$u2" class="link_button icon do">Simple Derived Cron Test</a>'
);
 $('#simple1').on('click', function(ev) {
  ev.preventDefault();
  cms_confirm('woot it works'); //TODO linkclick ...
  return false;
 });
});
//]]>
</script>
EOS;
    add_page_headtext($js);
} //DEBUG - END

$jobs = [];
$job_objs = JobStore::get_all_jobs();
if ($job_objs) {
    $list = [
        RecurType::RECUR_15M => $this->Lang('recur_15m'),
        RecurType::RECUR_30M => $this->Lang('recur_30m'),
        RecurType::RECUR_HOURLY => $this->Lang('recur_hourly'),
        RecurType::RECUR_120M => $this->Lang('recur_120m'),
        RecurType::RECUR_180M => $this->Lang('recur_180m'),
        RecurType::RECUR_DAILY => $this->Lang('recur_daily'),
        RecurType::RECUR_WEEKLY => $this->Lang('recur_weekly'),
        RecurType::RECUR_MONTHLY => $this->Lang('recur_monthly'),
        RecurType::RECUR_NONE => '',
    ];
    $custom = $this->Lang('pollgap', '%s');

    foreach ($job_objs as $job) {
        $obj = new stdClass();
        $name = $job->name;
        if (($t = strrpos($name, '\\')) !== false) {
            $name = substr($name, $t + 1);
        }
        $obj->name = $name;
        $obj->module = $job->module;
        if (Utils::job_recurs($job)) {
            if (isset($list[$job->frequency])) {
                $obj->frequency = $list[$job->frequency];
            } elseif ($job->frequency == RecurType::RECUR_SELF) {
                $t = floor($job->interval / 3600) . gmdate(':i', $job->interval % 3600);
                $obj->frequency = sprintf($custom, $t);
            } else {
                $obj->frequency = ''; //unknown parameter
            }
            $obj->until = $job->until;
        } else {
            $obj->frequency = null;
            $obj->until = null;
        }
        $obj->created = $job->created;
        $obj->start = ($obj->frequency || $obj->until) ? $job->start : 0;
        $obj->errors = $job->errors;
        $jobs[] = $obj;
    }
}

if (1) { //TODO normal module e.g. method_exists($this,'GetTemplateResource')
    $tpl = $smarty->createTemplate($this->GetTemplateResource('defaultadmin.tpl'));
} else {
    $tpl = $this->GetTemplateObject('defaultadmin.tpl');
}
$tpl->assign('jobs', $jobs);

if ($this->CheckPermission('Modify Site Preferences')) {
    $tpl->assign('tabbed', 1);
    $tpl->assign('enabled', (int)$this->GetPreference('enabled'));
    $tpl->assign('jobinterval', (int)(AppParams::get('jobinterval', 180) / 60)); // show as minutes
    $tpl->assign('jobtimeout', (int)AppParams::get('jobtimeout', 5));
    $tpl->assign('joburl', $this->GetPreference('joburl'));
}

$tpl->display();
return '';
