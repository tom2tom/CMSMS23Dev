<?php
#Entry point for all non-admin pages
#Copyright (C) 2004-2011 by Ted Kulp <ted@cmsmadesimple.org>
#Copyright (C) 2011-2018 by The CMSMS Dev Team <coreteam@cmsmadesimple.org>
#This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
#This program is free software; you can redistribute it and/or modify
#it under the terms of the GNU General Public License as published by
#the Free Software Foundation; either version 2 of the License, or
#(at your option) any later version.
#
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#You should have received a copy of the GNU General Public License
#along with this program; if not, write to the Free Software
#Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

/**
 * Entry point for all non-admin pages
 *
 * @package CMS
 */

use CMSMS\internal\content_plugins;

$starttime = microtime();
$orig_memory = (function_exists('memory_get_usage')?memory_get_usage():0);

if (isset($_REQUEST['cmsjobtype'])) {
    $type = (int)$_REQUEST['cmsjobtype'];
    $CMS_JOB_TYPE = min(max($type, 0), 2);
} elseif (isset($_REQUEST['showtemplate']) && $_REQUEST['showtemplate'] == 'false') {
    // undocumented, deprecated, output-suppressor
    $CMS_JOB_TYPE = 1;
} else {
    //normal output
    $CMS_JOB_TYPE = 0;
}

clearstatcache();

if (!isset($_SERVER['REQUEST_URI']) && isset($_SERVER['QUERY_STRING'])) $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];

require_once __DIR__.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';

if (!is_writable(TMP_TEMPLATES_C_LOCATION) || !is_writable(TMP_CACHE_LOCATION)) {
    echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head><title>Error</title></head><body>
<p>The following directories must be writable by the web server:<br />
tmp/cache<br />
tmp/templates_c<br /></p>
<p>Please correct by executing:<br /><em>chmod 777 tmp/cache<br />chmod 777 tmp/templates_c</em><br />or the equivalent for your platform before continuing.</p>
</body></html>';
    exit;
}

@ob_start();

// initial setup
$_app = CmsApp::get_instance(); // internal use only, subject to change.
$config = cms_config::get_instance();
$params = array_merge($_GET, $_POST);
if ($CMS_JOB_TYPE > 0) {
    $showtemplate = false;
    $_app->disable_template_processing();
} else {
    $showtemplate = true;
}
//TODO which of the following should be $CMS_JOB_TYPE-dependant ?
$smarty = $_app->GetSmarty();
$page = get_pageid_or_alias_from_url();
$contentops = ContentOperations::get_instance();
$contentobj = null;
$trycount = 0;

CMSMS\internal\content_cache::get_instance();
$_tpl_cache = new CMSMS\internal\TemplateCache();

while ($trycount < 2) {
    $trycount++;
    try {
        if ($trycount < 2 && is_file(TMP_CACHE_LOCATION.DIRECTORY_SEPARATOR.'SITEDOWN')) throw new \CmsError503Exception('Site down for maintenance');
        if ($trycount < 2 && is_sitedown()) throw new \CmsError503Exception('Site down for maintenance');

        // preview
        if ($page == -100) {
            if (!isset($_SESSION['__cms_preview__'])) throw new CmsException('preview selected, but temp data not found');
            setup_session(false);

            // todo: get the content type, and load it.
            $contentops->LoadContentType($_SESSION['__cms_preview_type__']);
            $contentobj = unserialize($_SESSION['__cms_preview__'], ['allowed_classes'=>false]);
            $contentobj->SetCachable(FALSE);
            $contentobj->SetId(__CMS_PREVIEW_PAGE__);
        } else {
            // $page could be an integer ID or a string alias
            $contentobj = $contentops->LoadContentFromAlias($page,true);
            if (!is_object($contentobj)) throw new CmsError404Exception('Page '.$page.' not found');
        }

        // session stuff is needed from here on.
        $cachable = $contentobj->Cachable();
        $uid = get_userid(FALSE);
        if ($page == __CMS_PREVIEW_PAGE__ || $uid || $_SERVER['REQUEST_METHOD'] != 'GET') $cachable = false;
        setup_session($cachable);

        // from here in, we're assured to have a content object of some sort

        if (!$contentobj->IsViewable()) {
            $url = $contentobj->GetURL();
            if ($url != '' && $url != '#') redirect($url);
            // not viewable, throw a 404.
            throw new CmsError404Exception('Cannot view an unviewable page');
        }

        if (!$contentobj->IsPermitted()) throw new CmsError403Exception('Permission denied');

        $_app->set_content_object($contentobj);
        $smarty->assignGlobal('content_obj',$contentobj);
        $smarty->assignGlobal('content_id', $contentobj->Id());
        $smarty->assignGlobal('page_id', $page);
        $smarty->assignGlobal('page_alias', $contentobj->Alias());

        CmsNlsOperations::set_language(); // <- NLS detection for frontend
        $smarty->assignGlobal('lang',CmsNlsOperations::get_current_language());
        $smarty->assignGlobal('encoding',CmsNlsOperations::get_encoding());

        CMSMS\HookManager::do_hook('Core::ContentPreRender', [ 'content' => &$contentobj ]);

        if ($config['content_processing_mode'] == 2) {
            debug_buffer('preprocess module action');
            content_plugins::get_default_content_block_content($contentobj->Id(), $smarty);
        }

        $html = null;
//        $showtemplate = $_app->template_processing_allowed();
        if ($showtemplate) {
            $tpl_id = $contentobj->TemplateId();
            $top = $body = $head = null;

            debug_buffer('process template top');
            CMSMS\HookManager::do_hook('Core::PageTopPreRender', [ 'content'=>&$contentobj, 'html'=>&$top ]);
            $tpl = $smarty->createTemplate('tpl_top:'.$tpl_id);
            $top .= $tpl->fetch();
            unset($tpl);
            CMSMS\HookManager::do_hook('Core::PageTopPostRender', [ 'content'=>&$contentobj, 'html'=>&$top ]);

            if ($config['content_processing_mode'] == 1) {
                debug_buffer('preprocess module action');
                content_plugins::get_default_content_block_content($contentobj->Id(), $smarty);
            }

            // if the request has a mact in it, process and cache the output.
            debug_buffer('process template body');
            CMSMS\HookManager::do_hook('Core::PageBodyPreRender', [ 'content'=>&$contentobj, 'html'=>&$body ]);
            $tpl = $smarty->createTemplate('tpl_body:'.$tpl_id);
            $body .= $tpl->fetch();
            unset($tpl);
            CMSMS\HookManager::do_hook('Core::PageBodyPostRender', [ 'content'=>&$contentobj, 'html'=>&$body ]);

            debug_buffer('process template head');
            CMSMS\HookManager::do_hook('Core::PageHeadPreRender', [ 'content'=>&$contentobj, 'html'=>&$head ]);
            $tpl = $smarty->createTemplate('tpl_head:'.$tpl_id);
            $head .= $tpl->fetch();
            unset($tpl);
            CMSMS\HookManager::do_hook('Core::PageHeadPostRender', [ 'content'=>&$contentobj, 'html'=>&$head ]);

            $html = $top.$head.$body;
        } else {
            $html = content_plugins::get_default_content_block_content($contentobj->Id(), $smarty);
        }
        $trycount = 99; // no more iterations
    }

    catch (CmsError404Exception $e) {
        // Catch CMSMS 404 error
        // 404 error thrown... gotta do this process all over again
        $page = 'error404';
        unset($_REQUEST['mact']);
        unset($_REQUEST['module']);
        unset($_REQUEST['action']);
        $handlers = ob_list_handlers();
        for ($cnt = 0; $cnt < sizeof($handlers); $cnt++) { ob_end_clean(); }

        // specified page not found, load the 404 error page
        $contentobj = $contentops->LoadContentFromAlias('error404',true);
        if ($showtemplate && is_object($contentobj)) {
            // we have a 404 error page
            header('HTTP/1.0 404 Not Found');
            header('Status: 404 Not Found');
        } else {
            // no 404 error page
            header('HTTP/1.0 404 Not Found');
            header('Status: 404 Not Found');
            echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head><title>404 Not Found</title></head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
</body></html>';
            exit;
        }
    }

    catch (CmsError403Exception $e) // <- Catch CMSMS 403 error
    {
        $page = 'error403';
        unset($_REQUEST['mact']);
        unset($_REQUEST['module']);
        unset($_REQUEST['action']);
        $handlers = ob_list_handlers();
        for ($cnt = 0; $cnt < sizeof($handlers); $cnt++) { ob_end_clean(); }

        // specified page not found, load the 404 error page.
        $contentobj = $contentops->LoadContentFromAlias('error403',true);
        $msg = $e->GetMessage();
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('HTTP/1.0 403 Forbidden');
        header('Status: 403 Forbidden');
        if ($showtemplate && is_object($contentobj)) {
            // we have a 403 error page.
        } else {
            if (!$msg) $msg = '<p>Sorry, you do not have the appropriate permission to view this item.</p>';
            echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head><title>403 Forbidden</title></head><body>
<h1>Forbidden</h1>'.$msg.'
</body></html>';
            exit;
        }
    }

    catch (CmsError503Exception $e) { // <- Catch CMSMS 503 error
        $page = 'error503';
        unset($_REQUEST['mact']);
        unset($_REQUEST['module']);
        unset($_REQUEST['action']);
        $handlers = ob_list_handlers();
        for ($cnt = 0; $cnt < sizeof($handlers); $cnt++) { ob_end_clean(); }

        // specified page not found, load the 404 error page.
        $contentobj = $contentops->LoadContentFromAlias('error503',true);
        $msg = $e->GetMessage();
        if (!$msg) $msg = '<p>We are sorry, but you do not have the appropriate permission to view this item.</p>';
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('HTTP/1.0 503 Temporarily unavailable');
        header('Status: 503 Temporarily unavailable');
        if ($showtemplate && is_object($contentobj)) {
            // we have a 503 error page.
        } else {
            @ob_end_clean();
            echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head><title>503 Site down for maintenance</title></head><body>
<h1>Sorry, down for maintenance.  Check back shortly.</h1>'.$msg.'
</body></html>';
            exit;
        }
    }

    catch (Exception $e) {
        // Catch rest of exceptions
        $handlers = ob_list_handlers();
        for ($cnt = 0; $cnt < sizeof($handlers); $cnt++) { ob_end_clean(); }
        $code = $e->GetCode();
        if (!$showtemplate && $code >= 400) {
            @ob_end_clean();
            header('HTTP/1.0 '.$code.' '.$e->GetMessage());
            header('Status '.$code.' '.$e->GetMessage());
        } else {
            echo $smarty->errorConsole($e);
        }
        exit();
    }
} // end while trycount

CMSMS\HookManager::do_hook('Core::ContentPostRender', [ 'content' => &$html ]);
if (!headers_sent()) {
    $ct = $_app->get_content_type();
    header("Content-Type: $ct; charset=" . CmsNlsOperations::get_encoding());
}
echo $html;

@ob_flush();

if ($page == __CMS_PREVIEW_PAGE__ && isset($_SESSION['__cms_preview__'])) unset($_SESSION['__cms_preview__']);

$debug = defined('CMS_DEBUG') && CMS_DEBUG;
if ($debug || isset($config['log_performance_info']) || (isset($config['show_performance_info']) && ($showtemplate))) {
    $endtime = microtime();
    $memory = (function_exists('memory_get_usage')?memory_get_usage():0);
    $memory = $memory - $orig_memory;
    $db = $_app->GetDb();
    $sql_time = round($db->query_time_total,5);
    $sql_queries = $db->query_count;
    $memory_peak = (function_exists('memory_get_peak_usage')?memory_get_peak_usage():'n/a');
    $time = microtime_diff($starttime,$endtime);

    if (isset($config['log_performance_info'])) {
        $out = [ time(), $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], $time, $sql_time, $queries, $memory, $memory_peak ];
        $filename = TMP_CACHE_LOCATION.DIRECTORY_SEPARATOR.'performance.log';
        error_log(implode('|',$out)."\n", 3, $filename);
    } else {
        $txt = "Time: $time / SQL: {$sql_time}s for $sql_queries queries / Net Memory: {$memory} / Peak: {$memory_peak}";
        echo '<div style="clear:both;"><pre><code>'.$txt.'</code></pre></div>';
    }
}

if ($debug || is_sitedown()) $smarty->clear_compiled_tpl();
if ($debug && !is_sitedown()) {
    $arr = $_app->get_errors();
    foreach ($arr as $error) {
        echo $error;
    }
}

CMSMS\HookManager::do_hook('PostRequest');

exit;
