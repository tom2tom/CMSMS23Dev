<?php

use CMSMS\ContentOperations;
use CMSMS\Events;
use CMSMS\FormUtils;
use News\Adminops;

if (!isset($gCms))  exit ;

if (!$this->CheckPermission('Modify News'))  return;
if (isset($params['cancel'])) $this->Redirect($id, 'defaultadmin', $returnid);
// default status
$status = ($this->CheckPermission('Approve News')) ? 'published' : 'draft';

$cz = $config['timezone'];
$tz = new DateTimeZone($cz);
$dt = new DateTime(null, $tz);
$toffs = $tz->getOffset($dt);

$useexp = $params['inputexp'] ?? 1;

if (isset($params['submit']) || isset($params['apply'])) {

    $articleid    = $params['articleid'];
    $title        = $params['title'];
    $summary      = $params['summary'];
    $content      = $params['content'];
    $status       = $params['status'] ?? $status;
    $searchable   = $params['searchable'] ?? 0;
    $news_url     = $params['news_url'];
    $usedcategory = $params['category'];
    $author_id    = $params['author_id'] ?? '-1';
    $extra        = trim($params['extra']);

    $st = strtotime($params['fromdate']);
    if ($st !== false) {
        if (isset($params['fromtime'])) {
            $stt = strtotime($params['fromtime'], 0);
            if ($stt !== false) {
                $st += $stt + $toffs;
            }
        }
        $startdate = $st;
    } else {
        //TODO process non-date input or bad-date error
        $startdate = NULL;
    }

    if ($useexp == 1) {
        $enddate = 0;
    } else {
        $st = strtotime($params['todate']);
        if ($st !== false) {
            if (isset($params['totime'])) {
                $stt = strtotime($params['totime'], 0);
                if ($stt !== false) {
                    $st += $stt + $toffs;
                }
            }
            $enddate = $st;
        } else {
            //TODO process non-date input or bad-date error
            $enddate = NULL;
        }
    }

    // Validation
    $error = false;
    if (empty($title)) {
        $this->ShowErrors($this->Lang('notitlegiven'));
        $error = true;
    } elseif (empty($content)) {
        $$this->ShowErrors($this->Lang('nocontentgiven'));
        $error = true;
    }

    if ($useexp == 1 && $startdate <= $enddate) {
        $this->ShowErrors($this->Lang('error_invaliddates'));
        $error = true;
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

        // check this url isn't a duplicate.
        cms_route_manager::load_routes();
        $route = cms_route_manager::find_match($news_url, true);
        if ($route) {
            $dflts = $route->get_defaults();
            if ($route['key1'] != $this->GetName() || !isset($dflts['articleid']) || $dflts['articleid'] != $articleid) {
                $this->ShowErrors($this->Lang('error_invalidurl'));
                $error = true;
            }
        }
    }

    if (!$error) {
        //
        // database work
        //
        $now = time();
        $query = 'UPDATE ' . CMS_DB_PREFIX . 'module_news SET
news_title=?,
news_data=?,
summary=?,
news_category_id=?,
status=?,
searchable=?,
start_time=?,
end_time=?,
modified_date=?,
news_extra=?,
news_url= ?
WHERE news_id=?';
        $args = [
         $title,
         $content,
         $summary,
         $usedcategory,
         $status,
         $searchable,
         $startdate,
         (($useexp == 1)?$enddate:NULL),
         $now,
         $extra,
         $news_url,
         $articleid
        ];
        $db->Execute($query, $args);

        //
        //Update custom fields
        //

        // get the field types
        $query = 'SELECT id,name,type FROM ' . CMS_DB_PREFIX . "module_news_fielddefs WHERE type='file'";
        $types = $db->GetArray($query);
        if (is_array($types)) {
            foreach ($types as $onetype) {
                $elem = $id . 'customfield_' . $onetype['id'];
                if (isset($_FILES[$elem]) && $_FILES[$elem]['name'] != '') {
                    if ($_FILES[$elem]['error'] != 0 || $_FILES[$elem]['tmp_name'] == '') {
                        $this->ShowErrors($this->Lang('error_upload'));
                        $error = true;
                    } else {
                        $value = Adminops::handle_upload($articleid, $elem, $error);
		                if ($value === false) {
		                    $this->ShowErrors($error);
		                    $error = true;
		                } else {
                            $params['customfield'][$onetype['id']] = $value;
		                }
                    }
                }
            }
        }

        if (!$error && isset($params['customfield'])) {
            foreach ($params['customfield'] as $fldid => $value) {
                // first check if it's available
                $query = 'SELECT value FROM ' . CMS_DB_PREFIX . 'module_news_fieldvals WHERE news_id = ? AND fielddef_id = ?';
                $tmp = $db->GetOne($query, [
                    $articleid,
                    $fldid
                ]);
                $dbr = true;
                if ($tmp === false) {
                    if (!empty($value)) {
                        $query = 'INSERT INTO ' . CMS_DB_PREFIX . "module_news_fieldvals (news_id,fielddef_id,value,create_date) VALUES (?,?,?,$now)";
                        $dbr = $db->Execute($query, [
                            $articleid,
                            $fldid,
                            $value
                        ]);
                    }
                } else {
                    if (empty($value)) {
                        $query = 'DELETE FROM ' . CMS_DB_PREFIX . 'module_news_fieldvals WHERE news_id = ? AND fielddef_id = ?';
                        $dbr = $db->Execute($query, [
                            $articleid,
                            $fldid
                        ]);
                    } else {
                        $query = 'UPDATE ' . CMS_DB_PREFIX . "module_news_fieldvals
                      SET value = ?, modified_date = $now WHERE news_id = ? AND fielddef_id = ?";
                        $dbr = $db->Execute($query, [
                            $value,
                            $articleid,
                            $fldid
                        ]);
                    }
                }
                if (!$dbr)
                    die('FATAL SQL ERROR: ' . $db->ErrorMsg() . '<br />QUERY: ' . $db->sql);
            }
        }

        if (!$error && isset($params['delete_customfield']) && is_array($params['delete_customfield'])) {
            foreach ($params['delete_customfield'] as $k => $v) {
                if ($v != 'delete')
                    continue;
                $query = 'DELETE FROM ' . CMS_DB_PREFIX . 'module_news_fieldvals WHERE news_id = ? AND fielddef_id = ?';
                $db->Execute($query, [
                    $articleid,
                    $k
                ]);
            }
        }

        if (!$error && $status == 'published' && $news_url != '') {
            Adminops::delete_static_route($articleid);
            Adminops::register_static_route($news_url, $articleid);
        }

        //Update search index
        if (!$error) {
            $module = cms_utils::get_search_module();
            if (is_object($module)) {
                if ($status == 'draft' || !$searchable) {
                    $module->DeleteWords($this->GetName(), $articleid, 'article');
                } else {
                    if (!$useexp || ($enddate > time()) || $this->GetPreference('expired_searchable', 1) == 1) {
                        $text = '';
                    }

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

            Events::SendEvent('News', 'NewsArticleEdited', [
                'news_id' => $articleid,
                'category_id' => $usedcategory,
                'title' => $title,
                'content' => $content,
                'summary' => $summary,
                'status' => $status,
                'start_time' => $startdate,
                'end_time' => $enddate,
                'post_time' => $startdate,
                'extra' => $extra,
                'useexp' => $useexp,
                'news_url' => $news_url
            ]);
            // put mention into the admin log
            audit($articleid, 'News: ' . $title, 'Article edited');
        }// if no error

        if (isset($params['apply']) && isset($params['ajax'])) {
            $response = '<EditArticle>';
            if ($error) {
                $response .= '<Response>Error</Response>';
                $response .= '<Details><![CDATA[' . $error . ']]></Details>';
            } else {
                $response .= '<Response>Success</Response>';
                $response .= '<Details><![CDATA[' . $this->Lang('articleupdated') . ']]></Details>';
            }
            $response .= '</EditArticle>';
            echo $response;
            return;
        }

        if (!$error && !isset($params['apply'])) {
            // redirect out of here.
            $this->SetMessage($this->Lang('articlesubmitted'));
            $this->Redirect($id, 'defaultadmin', $returnid);
            return;
        }

    }

    $row = [
    'modified_date' => 0, //TODO
    'create_date' => 0, //TODO
    'start_time' => $startdate,
    'end_time' => $enddate,
    ];
} elseif (!isset($params['preview'])) {
    //
    // Load data from database
    //
    $query = 'SELECT * FROM ' . CMS_DB_PREFIX . 'module_news WHERE news_id = ?';
    $row = $db->GetRow($query, [$params['articleid']]);

    if ($row) {
        $articleid    = $row['news_id'];
        $title        = $row['news_title'];
        $content      = $row['news_data'];
        $summary      = $row['summary'];
        $status       = $row['status'];
        $searchable   = $row['searchable'];
        $startdate    = $row['start_time'];
        $enddate      = $row['end_time'];
        $usedcategory = $row['news_category_id'];
        $author_id    = $row['author_id'];
        $extra        = $row['news_extra'];
        $news_url     = $row['news_url'];
    } else {
        //TODO handle error
    }
} else {
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
    for ($cnt = 0; $cnt < sizeof($handlers); $cnt++) {
        ob_end_clean();
    }
    header('Content-Type: text/xml');
    echo $response;
    exit;
}

$choices = [
    $this->Lang('draft')=>'draft',
    $this->Lang('final')=>'final',
    $this->Lang('archived')=>'archived',
];
$statusradio = $this->CreateInputRadioGroup($id,'status',$choices,$status,'','  ');

$created = date('Y-n-j G:i', $row['create_date']);
if ($row['modified_date'] > $row['create_date']) {
    $modified = date('Y-n-j G:i', $row['modified_date']);
} else {
    $modified = NULL;
}
if ($status == 'published') {
    if ($row['start_time']) {
        $published = date('Y-n-j G:i', $row['start_time']);
    } else {
        $published = '?';
    }
} else {
    $published = NULL;
}
if ($status == 'archived') {
    if ($row['end_time']) {
        $archived = date('Y-n-j G:i', $row['end_time']);
    } else {
        $archived = '?';
    }
} else {
    $archived = NULL;
}

$block = $this->GetPreference('timeblock', News::HOURBLOCK);
switch ($block) {
    case News::DAYBLOCK:
        $rounder = 3600*24;
        break;
    case News::HALFDAYBLOCK:
        $rounder = 3600*12;
        break;
    default:
        $rounder = 3600;
        break;
}
$withtime = ($block == News::DAYBLOCK) ? 0:1;

if ($startdate > 0) {
    $st = strtotime('midnight', $startdate);
    $fromdate = date('Y-m-d', $st);
    if ($withtime) {
        $stt = $startdate - $st - $toffs;
        $stt = (int)($stt / $rounder) * $rounder;
        $fromtime = date('h:ia', $stt);
    } else {
        $fromtime = null;
    }
} else {
    $fromdate = '';
    $fromtime = null;
}

if ($enddate > 0) {
    $st = strtotime('midnight', $enddate);
    $todate = date('Y-m-d', $st);
    if ($withtime) {
        $stt = $enddate - $st - $toffs;
        $stt = (int)($stt / $rounder) * $rounder;
        $totime = date('h:ia', $stt);
    } else {
        $totime = null;
    }
} else {
    $todate = '';
    $totime = null;
}

$categorylist = [];
$query = 'SELECT * FROM ' . CMS_DB_PREFIX . 'module_news_categories ORDER BY hierarchy';
$dbr = $db->Execute($query);
while ($dbr && $row = $dbr->FetchRow()) {
    $categorylist[$row['long_name']] = $row['news_category_id'];
}

/*--------------------
 * Custom fields logic
 ---------------------*/

// Get the field values
$fieldvals = [];
$query = 'SELECT * FROM ' . CMS_DB_PREFIX . 'module_news_fieldvals WHERE news_id = ?';
$tmp = $db->GetArray($query, [$articleid]);
if (is_array($tmp)) {
    foreach ($tmp as $one) {
        $fieldvals[$one['fielddef_id']] = $one;
    }
}

$query = 'SELECT * FROM ' . CMS_DB_PREFIX . 'module_news_fielddefs ORDER BY item_order';
$dbr = $db->Execute($query);
$custom_flds = [];
while ($dbr && ($row = $dbr->FetchRow())) {
    if (!empty($row['extra']))
		$row['extra'] = unserialize($row['extra']);

    if (isset($row['extra']['options'])) $options = $row['extra']['options'];
    else $options = null;

    if (isset($fieldvals[$row['id']])) $value = $fieldvals[$row['id']]['value'];
    else $value = '';
    $value = isset($params['customfield'][$row['id']]) && in_array($params['customfield'][$row['id']], $params['customfield']) ? $params['customfield'][$row['id']] : $value;

    if ($row['type'] == 'file') {
        $name = 'customfield_' . $row['id'];
    } else {
        $name = 'customfield[' . $row['id'] . ']';
    }

    $obj = new StdClass();

    $obj->value    = $value;
    $obj->nameattr = $id . $name;
    $obj->type     = $row['type'];
    $obj->idattr   = 'customfield_' . $row['id'];
    $obj->prompt   = $row['name'];
    $obj->size     = min(80, (int)$row['max_length']);
    $obj->max_len  = max(1, (int)$row['max_length']);
    $obj->delete   = $id . 'delete_customfield[' . $row['id'] . ']';
    $obj->options  = $options;
/*
    FIXME - If we create inputs with hmtl markup in smarty template, whats the use of switch and form API here?
    switch( $row['type'] ) {
        case 'textbox' :
            $size = min(50, $row['max_length']);
            $obj->field = $this->CreateInputText($id, $name, $value, $size, $row['max_length']);  DEPRECATED API
            break;
        case 'checkbox' :
            $obj->field = $this->CreateInputHidden($id, $name, 0) . $this->CreateInputCheckbox($id, $name, 1, (int)$value); DEPRECATED API
            break;
        case 'textarea' :
            $obj->field = FormUtils::create_textarea(['enablewysiwyg'=>1, 'modid'=>$id, 'name'=>$name, 'value'=>$value]);
            break;
        case 'file' :
            $del = '';
            if ($value != '') {
                $deln = 'delete_customfield[' . $row['id'] . ']';
                $del = '&nbsp;' . $this->Lang('delete') . $this->CreateInputCheckbox($id, $deln, 'delete'); DEPRECATED API
            }
            $obj->field = $value . '&nbsp;' . $this->CreateFileUploadInput($id, $name) . $del;
            break;
        case 'dropdown' :
            $obj->field = $this->CreateInputDropdown($id, $name, array_flip($options), -1, $value); DEPRECATED API
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
 ->assign('startform', $this->CreateFormStart($id, 'editarticle', $returnid))
 ->assign('hidden', $this->CreateInputHidden($id, 'articleid', $articleid) . $this->CreateInputHidden($id, 'author_id', $author_id))
 ->assign('title', $title);

if ($author_id > 0) {
    $userops = $gCms->GetUserOperations();
    $theuser = $userops->LoadUserById($author_id);
    if ($theuser) {
        $tpl->assign('inputauthor', $theuser->username);
    } else {
        $tpl->assign('inputauthor', $this->Lang('anonymous'));
    }
} else if ($author_id == 0) {
    $tpl->assign('inputauthor', $this->Lang('anonymous'));
} else {
    $feu = $this->GetModuleInstance('FrontEndUsers');
    if ($feu) {
        $uinfo = $feu->GetUserInfo($author_id * -1);
        if ($uinfo[0])
            $tpl->assign('inputauthor', $uinfo[1]['username']);
    }
}

if ($this->GetPreference('allow_summary_wysiwyg', 1)) {
    $tpl->assign('hide_summary_field', false)
	 ->assign('inputsummary', FormUtils::create_textarea([
        'enablewysiwyg' => 1,
	    'modid' => $id,
	    'name' => 'summary',
	    'class' => 'pageextrasmalltextarea',
	    'value' => $summary,
	    'addtext' => 'style="height:3em;"',
    ]));
} else {
     $tpl->assign('hide_summary_field', true);
}
$tpl->assign('inputcontent', FormUtils::create_textarea([
    'enablewysiwyg' => 1,
    'modid' => $id,
    'name' => 'content',
    'value' => $content,
]));

$tpl->assign('useexp', $useexp)
 ->assign('inputexp', $this->CreateInputCheckbox($id, 'useexp', '1', $useexp, 'class="pagecheckbox"'))
 ->assign('createat', $created)
 ->assign('modat', $modified)
 ->assign('pubat', $published)
 ->assign('archat', $archived)
 ->assign('fromdate', $fromdate)
 ->assign('todate', $todate)
 ->assign('fromtime', $fromtime)
 ->assign('totime', $totime)
 ->assign('withtime', $withtime)
 ->assign('status', $status)
 ->assign('categorylist', array_flip($categorylist))
 ->assign('category', $usedcategory)
 ->assign('searchable', $searchable)
 ->assign('extra', $extra)
 ->assign('news_url', $news_url)
 ->assign('delete_field_val', $this->Lang('delete'))
 ->assign('warning_preview', $this->Lang('warning_preview'))
 ->assign('select_option', $this->Lang('select_option'))
// tab stuff
 ->assign('start_tab_headers', $this->StartTabHeaders())
 ->assign('tabheader_article', $this->SetTabHeader('article', $this->Lang('article')))
 ->assign('tabheader_preview', $this->SetTabHeader('preview', $this->Lang('preview')))
 ->assign('end_tab_headers', $this->EndTabHeaders())
 ->assign('start_tab_content', $this->StartTabContent())
 ->assign('start_tab_article', $this->StartTab('article', $params))
 ->assign('end_tab_article', $this->EndTab())
 ->assign('end_tab_content', $this->EndTabContent());

if ($this->CheckPermission('Approve News')) {
    $tpl->assign('statuses',$statusradio);
    //->assign('statustext', lang('status'));
}

if ($custom_flds) {
    $tpl->assign('custom_fields', $custom_flds);
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
} catch( Exception $e ) {
    audit('', $this->GetName(), 'No detail templates available for preview');
}

// page resources
$baseurl = $this->GetModuleURLPath();
$css = <<<EOS
 <link rel="stylesheet" href="{$baseurl}/css/jquery.datepicker.css">
 <link rel="stylesheet" href="{$baseurl}/css/jquery.timepicker.css">

EOS;
$this->AdminHeaderContent($css);
include __DIR__.DIRECTORY_SEPARATOR.'method.articlescript.php';

$tpl->display();
