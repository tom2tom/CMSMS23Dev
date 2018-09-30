<?php

use CMSMS\ContentOperations;
use CMSMS\Events;
use News\news_admin_ops;

if (!isset($gCms))
    exit ;

if (!$this->CheckPermission('Modify News'))
    return;

if (isset($params['cancel']))
    $this->Redirect($id, 'defaultadmin', $returnid);

/*--------------------
 * Variables
 ---------------------*/

$status       = 'draft';
if ($this->CheckPermission('Approve News'))  $status = 'published';
$userid       = get_userid();
$postdate     = time();
$startdate    = time();
$content      = $params['content'] ?? '';
$summary      = $params['summary'] ?? '';
$status       = $params['status'] ?? $status;
$usedcategory = $params['category'] ?? $this->GetPreference('default_category', '');
$useexp       = isset($params['useexp']) ? 1: 0;
$searchable   = isset($params['searchable']) ? (int)$params['searchable'] : 1;
$news_url     = $params['news_url'] ?? '';
$extra        = isset($params['extra']) ? trim($params['extra']) : '';
$title        = $params['title'] ?? '';
$ndays        = (int)$this->GetPreference('expiry_interval', 180);

if ($ndays == 0)
    $ndays = 180;

$enddate      = strtotime(sprintf('+%d days', $ndays), time());


if (isset($params['postdate_Month'])) {
    $postdate = mktime($params['postdate_Hour'], $params['postdate_Minute'], $params['postdate_Second'], $params['postdate_Month'], $params['postdate_Day'], $params['postdate_Year']);
}

if (isset($params['startdate_Month'])) {
    $startdate = mktime($params['startdate_Hour'], $params['startdate_Minute'], $params['startdate_Second'], $params['startdate_Month'], $params['startdate_Day'], $params['startdate_Year']);
}

if (isset($params['enddate_Month'])) {
    $enddate = mktime($params['enddate_Hour'], $params['enddate_Minute'], $params['enddate_Second'], $params['enddate_Month'], $params['enddate_Day'], $params['enddate_Year']);
}


/*--------------------
 * Logic
 ---------------------*/

if (isset($params['submit'])) {
    $error = false;
    if (empty($title)) {
        $this->ShowErrors($this->Lang('notitlegiven'));
        $error = true;
    }
    if (empty($content)) {
        $this->ShowErrors($this->Lang('nocontentgiven'));
        $error = true;
    }
    if ($useexp == 1) {
        if ($startdate >= $enddate) {
            $this->ShowErrors($this->Lang('error_invaliddates'));
            $error = true;
        }
    }

    if ($news_url) {
        // check for starting or ending slashes
        if (startswith($news_url, '/') || endswith($news_url, '/')) {
            $this->ShowErrors($this->Lang('error_invalidurl'));
            $error = true;
        }

        // check for invalid chars.
        $translated = munge_string_to_url($news_url, false, true);
        if (strtolower($translated) != strtolower($news_url)) {
            $this->ShowErrors($this->Lang('error_invalidurl'));
            $error = true;
        }

        // make sure this url isn't taken.
        cms_route_manager::load_routes();
        $route = cms_route_manager::find_match($news_url);
        if ($route) {
            $this->ShowErrors($this->Lang('error_invalidurl'));
            $error = true;
            // we're adding an article, not editing... any matching route is bad.
        }
    }

    //
    // database work
    //
    if (!$error) {
        $articleid = $db->GenID(CMS_DB_PREFIX . 'module_news_seq');
        $query = 'INSERT INTO ' . CMS_DB_PREFIX . 'module_news (news_id, news_category_id, news_title, news_data, summary, status, news_date, start_time, end_time, create_date, modified_date,author_id,news_extra,news_url,searchable) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        if ($useexp == 1) {
            $dbr = $db->Execute($query, [
                $articleid,
                $usedcategory,
                $title,
                $content,
                $summary,
                $status,
                trim($db->DbTimeStamp($postdate), "'"),
                trim($db->DbTimeStamp($startdate), "'"),
                trim($db->DbTimeStamp($enddate), "'"),
                trim($db->DbTimeStamp(time()), "'"),
                trim($db->DbTimeStamp(time()), "'"),
                $userid,
                $extra,
                $news_url,
                $searchable
            ]);
        } else {
            $dbr = $db->Execute($query, [
                $articleid,
                $usedcategory,
                $title,
                $content,
                $summary,
                $status,
                trim($db->DbTimeStamp($postdate), "'"),
                NULL,
                NULL,
                trim($db->DbTimeStamp(time()), "'"),
                trim($db->DbTimeStamp(time()), "'"),
                $userid,
                $extra,
                $news_url,
                $searchable
            ]);
        }

        if (!$dbr) {
            echo 'DEBUG: SQL = ' . $db->sql . '<br />';
            die($db->ErrorMsg());
        }

        //
        //Handle submitting the 'custom' fields
        //
        // get the field types
        $qu = 'SELECT id,name,type FROM ' . CMS_DB_PREFIX . "module_news_fielddefs WHERE type='file'";
        $types = $db->GetArray($qu);

        foreach ($types as $onetype) {
            $elem = $id . 'customfield_' . $onetype['id'];
            if (isset($_FILES[$elem]) && $_FILES[$elem]['name'] != '') {
                if ($_FILES[$elem]['error'] != 0 || $_FILES[$elem]['tmp_name'] == '') {
                    $this->ShowErrors($this->Lang('error_upload'));
                    $error = true;
                } else {
                    $value = news_admin_ops::handle_upload($articleid, $elem, $error);
                    if ($value === false) {
                        $this->ShowErrors($error);
                        $error = true;
                    } else {
                        $params['customfield'][$onetype['id']] = $value;
                        $error = false;
                    }
                }
            }
        }

        if (isset($params['customfield']) && !$error) {
            $now = trim($db->DbTimeStamp(time()), "'");
            foreach ($params['customfield'] as $fldid => $value) {
                if ($value == '')
                    continue;

                $query = 'INSERT INTO ' . CMS_DB_PREFIX . 'module_news_fieldvals (news_id,fielddef_id,value,create_date,modified_date) VALUES (?,?,?,?,?)';
                $dbr = $db->Execute($query, [
                    $articleid,
                    $fldid,
                    $value,
                    $now,
                    $now
                ]);
                if (!$dbr)
                    die('FATAL SQL ERROR: ' . $db->ErrorMsg() . '<br />QUERY: ' . $db->sql);
            }
        }// if

        if (!$error && $status == 'published' && $news_url != '') {
            // todo: if not expired
            // register the route.
            news_admin_ops::delete_static_route($articleid);
            news_admin_ops::register_static_route($news_url, $articleid);
        }

        if (!$error && $status == 'published' && $searchable) {
            //Update search index
            $module = cms_utils::get_search_module();
            if (is_object($module)) {
                $text = '';
                if (isset($params['customfield'])) {
                    foreach ($params['customfield'] as $fldid => $value) {
                        if (strlen($value) > 1)
                            $text .= $value . ' ';
                    }
                }

                $text .= $content . ' ' . $summary . ' ' . $title . ' ' . $title;
                $module->AddWords($this->GetName(), $articleid, 'article', $text, ($useexp == 1 && $this->GetPreference('expired_searchable', 0) == 0) ? $enddate : NULL);
            }
        }

        if (!$error) {
            Events::SendEvent('News', 'NewsArticleAdded',
                                        ['news_id' => $articleid,
                                              'category_id' => $usedcategory,
                                              'title' => $title,
                                              'content' => $content,
                                              'summary' => $summary,
                                              'status' => $status,
                                              'start_time' => $startdate,
                                              'end_time' => $enddate,
                                              'postdate' => $postdate,
                                              'useexp' => $useexp,
                                              'extra' => $extra ]);
            // put mention into the admin log
            audit($articleid, 'News: ' . $title, 'Article added');
            $this->SetMessage($this->Lang('articleadded'));
            $this->Redirect($id, 'defaultadmin', $returnid);
        } // if !$error
    } // outer if !$error
// end submit
} elseif (isset($params['preview'])) {
    // save data for preview.
    unset($params['apply']);
    unset($params['preview']);
    unset($params['submit']);
    unset($params['cancel']);
    unset($params['ajax']);

    $tmpfname = tempnam(TMP_CACHE_LOCATION, $this->GetName() . '_preview');
    file_put_contents($tmpfname, serialize($params));

    $detail_returnid = $this->GetPreference('detail_returnid', -1);
    if ($detail_returnid <= 0) {
        // now get the default content id.
        $detail_returnid = ContentOperations::get_instance()->GetDefaultContent();
    }
    if (isset($params['previewpage']) && (int)$params['previewpage'] > 0)
        $detail_returnid = (int)$params['previewpage'];

    $_SESSION['news_preview'] = [
        'fname' => basename($tmpfname),
        'checksum' => md5_file($tmpfname)
    ];
    $tparms = ['preview' => md5(serialize($_SESSION['news_preview']))];
    if (isset($params['detailtemplate']))
        $tparms['detailtemplate'] = trim($params['detailtemplate']);
    $url = $this->create_url('_preview_', 'detail', $detail_returnid, $tparms, true);

    $response = '<?xml version="1.0"?>';
    $response .= '<EditArticle>';
    if (!empty($error)) {
        $response .= '<Response>Error</Response>';
        $response .= '<Details><![CDATA[' . $error . ']]></Details>';
    } else {
        $response .= '<Response>Success</Response>';
        $response .= '<Details><![CDATA[' . $url . ']]></Details>';
    }
    $response .= '</EditArticle>';

    $handlers = ob_list_handlers();
    for ($cnt = 0; $cnt < sizeof($handlers); $cnt++) { ob_end_clean();
    }
    header('Content-Type: text/xml');
    echo $response;
    exit ;
}

//
// build the form
//
$statusdropdown = [];
$statusdropdown[$this->Lang('draft')] = 'draft';
$statusdropdown[$this->Lang('published')] = 'published';

$categorylist = [];
$query = 'SELECT * FROM ' . CMS_DB_PREFIX . 'module_news_categories ORDER BY hierarchy';
$dbresult = $db->Execute($query);

while ($dbresult && $row = $dbresult->FetchRow()) {
    $categorylist[$row['long_name']] = $row['news_category_id'];
}


// Display custom fields
$query = 'SELECT * FROM ' . CMS_DB_PREFIX . 'module_news_fielddefs ORDER BY item_order';
$dbr = $db->Execute($query);
$custom_flds = [];

while ($dbr && ($row = $dbr->FetchRow())) {
    if (!empty($row['extra']))
        $row['extra'] = unserialize($row['extra']);

    $options = null;
    if (isset($row['extra']['options']))
        $options = $row['extra']['options'];

    $value = isset($params['customfield'][$row['id']]) && in_array($params['customfield'][$row['id']], $params['customfield']) ? $params['customfield'][$row['id']] : '';

    if ($row['type'] == 'file') {
        $name = 'customfield_' . $row['id'];
    } else {
        $name = 'customfield[' . $row['id'] . ']';
    }

    $obj = new StdClass();

    $obj->value    = $value;
    $obj->type     = $row['type'];
    $obj->nameattr = $id . $name;
    $obj->idattr   = 'customfield_' . $row['id'];
    $obj->prompt   = $row['name'];
    $obj->size     = min(80, (int)$row['max_length']);
    $obj->max_len  = max(1, (int)$row['max_length']);
    $obj->options  = $options;
    // FIXME - If we create inputs with hmtl markup in smarty template, whats the use of switch and form API here?
    /*
    switch( $row['type'] ) {
        case 'textbox' :
            $size = min(50, $row['max_length']);
            $obj->field = $this->CreateInputText($id, $name, $value, $size, $row['max_length']);
            break;
        case 'checkbox' :
            $obj->field = $this->CreateInputHidden($id, $name, $value != '' ? $value : '0') . $this->CreateInputCheckbox($id, $name, '1', $value != '' ? $value : '0');
            break;
        case 'textarea' :
            $obj->field = CmsFormUtils::create_textarea(['enablewysiwyg'=>1, 'modid'=>$id, 'name'=>$name, 'value'=>$value]);
            break;
        case 'file' :
            $name = "customfield_" . $row['id'];
            $obj->field = $this->CreateFileUploadInput($id, $name);
            break;
        case 'dropdown' :
            $obj->field = $this->CreateInputDropdown($id, $name, array_flip($options));
            break;
    }
    */

    $custom_flds[$row['name']] = $obj;
}

/*--------------------
 * Pass everything to smarty
 ---------------------*/
$tpl = $smarty->createTemplate($this->GetTemplateResource('editarticle.tpl'),null,null,$smarty);

$tpl->assign('formid', $id)
 ->assign('hide_summary_field', $this->GetPreference('hide_summary_field', '0'))
 ->assign('authortext', '')
 ->assign('inputauthor', '')
 ->assign('startform', $this->CreateFormStart($id, 'addarticle', $returnid, 'post', 'multipart/form-data'))
 ->assign('endform', $this->CreateFormEnd())
 ->assign('titletext', $this->Lang('title'))
 ->assign('title', $title)
 ->assign('allow_summary_wysiwyg', $this->GetPreference('allow_summary_wysiwyg'))
 ->assign('extratext', $this->Lang('extra'))
 ->assign('extra', $extra)
 ->assign('urltext', $this->Lang('url'))
 ->assign('news_url', $news_url)
 ->assign('postdate', $postdate)
 ->assign('postdateprefix', $id . 'postdate_')
 ->assign('useexp', $useexp)
 ->assign('actionid', $id)
 ->assign('inputexp', $this->CreateInputCheckbox($id, 'useexp', '1', $useexp, 'class="pagecheckbox"'))
 ->assign('startdate', $startdate)
 ->assign('startdateprefix', $id . 'startdate_')
 ->assign('enddate', $enddate)
 ->assign('enddateprefix', $id . 'enddate_')
 ->assign('status', $status)
 ->assign('categorylist', array_flip($categorylist))
 ->assign('category', $usedcategory)
//see template   ->assign('submit', $this->CreateInputSubmit($id, 'submit', lang('submit')))
// ->assign('cancel', $this->CreateInputSubmit($id, 'cancel', lang('cancel')))
 ->assign('delete_field_val', $this->Lang('delete'))
 ->assign('titletext', $this->Lang('title'))
 ->assign('categorytext', $this->Lang('category'))
 ->assign('summarytext', $this->Lang('summary'))
 ->assign('contenttext', $this->Lang('content'))
 ->assign('postdatetext', $this->Lang('postdate'))
 ->assign('useexpirationtext', $this->Lang('useexpiration'))
 ->assign('startdatetext', $this->Lang('startdate'))
 ->assign('enddatetext', $this->Lang('enddate'))
 ->assign('searchable', $searchable)
 ->assign('select_option', $this->Lang('select_option'))
// tab stuff.
 ->assign('start_tab_headers', $this->StartTabHeaders())
 ->assign('tabheader_article', $this->SetTabHeader('article', $this->Lang('article')))
 ->assign('tabheader_preview', $this->SetTabHeader('preview', $this->Lang('preview')))
 ->assign('end_tab_headers', $this->EndTabHeaders())
 ->assign('start_tab_content', $this->StartTabContent())
 ->assign('start_tab_article', $this->StartTab('article', $params))
 ->assign('end_tab_article', $this->EndTab())
 ->assign('end_tab_content', $this->EndTabContent())
 ->assign('warning_preview', $this->Lang('warning_preview'));

$parms = [
    'modid' => $id,
    'name' => 'summary',
    'class' => 'pageextrasmalltextarea',
    'value' => $summary,
];
if ($this->GetPreference('allow_summary_wysiwyg',1)) {
    $parms += [
        'enablewysiwyg' => 1,
        'addtext' => 'style="height:5em;"', //smaller again ...
    ];
}
$tpl->assign('inputsummary', CmsFormutils::create_textarea($parms))
 ->assign('inputcontent', CmsFormUtils::create_textarea([
    'enablewysiwyg' => 1,
    'modid' => $id,
    'name' => 'content',
    'class' => 'pagesmalltextarea',
    'value' => $content,
]));

if (count($custom_flds) > 0) {
    $tpl->assign('custom_fields', $custom_flds);
}
if ($this->CheckPermission('Approve News')) {
    $tpl->assign('statustext', lang('status'))
      ->assign('statuses', array_flip($statusdropdown));
}

$contentops = cmsms()->GetContentOperations();
$tpl->assign('preview_returnid', $contentops->CreateHierarchyDropdown('', $this->GetPreference('detail_returnid', -1), 'preview_returnid'));

// get the list of detail templates.
try {
    $type = CmsLayoutTemplateType::load($this->GetName() . '::detail');
    $templates = $type->get_template_list();
    $list = [];
    if ($templates) {
        foreach ($templates as $template) {
            $list[$template->get_id()] = $template->get_name();
        }
    }
    if ($list) {
        $tpl->assign('prompt_detail_template', $this->Lang('detail_template'))
          ->assign('prompt_detail_page', $this->Lang('detail_page'))
          ->assign('detail_templates', $list)
          ->assign('cur_detail_template', $this->GetPreference('current_detail_template'))
          ->assign('start_tab_preview', $this->StartTab('preview', $params))
          ->assign('end_tab_preview', $this->EndTab());
    }
    include __DIR__.DIRECTORY_SEPARATOR.'method.articlescript.php';
} catch( Exception $e ) {
    audit('', $this->GetName(), 'No detail templates available for preview');
}

$tpl->display();

