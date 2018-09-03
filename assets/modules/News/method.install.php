<?php

use News\news_admin_ops;

if (!isset($gCms)) exit;

//best to avoid module-specific class autoloading during installation
if( !class_exists('news_admin_ops') ) {
  $fn = cms_join_path(__DIR__,'lib','class.news_admin_ops.php');
  require_once($fn);
}

$uid = null;
if( cmsms()->test_state(CmsApp::STATE_INSTALL) ) {
  $uid = 1; // hardcode to first user
} else {
  $uid = get_userid();
}

$db = $this->GetDb();
$dict = NewDataDictionary($db);
$taboptarray = ['mysqli' => 'ENGINE=MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci'];

// icon is no longer used
$flds = '
news_id I KEY,
news_category_id I,
news_title C(255),
news_data X(16384),
news_date DT,
summary X(1024),
start_time DT,
end_time DT,
status C(25),
icon C(255),
create_date DT,
modified_date DT,
author_id I,
news_extra C(255),
news_url C(255),
searchable I(1)
';

$sqlarray = $dict->CreateTableSQL(CMS_DB_PREFIX.'module_news', $flds, $taboptarray);
$dict->ExecuteSQLArray($sqlarray);
$db->CreateSequence(CMS_DB_PREFIX.'module_news_seq');

$flds = '
news_category_id I KEY,
news_category_name C(255) NOTNULL,
parent_id I,
hierarchy C(255),
item_order I,
long_name X(1024),
create_date T,
modified_date T
';

$sqlarray = $dict->CreateTableSQL(CMS_DB_PREFIX.'module_news_categories', $flds, $taboptarray);
$dict->ExecuteSQLArray($sqlarray);
$db->CreateSequence(CMS_DB_PREFIX.'module_news_categories_seq');

$flds = '
id I KEY AUTO,
name C(255),
type C(50),
max_length I,
create_date DT,
modified_date DT,
item_order I,
public I,
extra  X
';

$sqlarray = $dict->CreateTableSQL(CMS_DB_PREFIX.'module_news_fielddefs', $flds, $taboptarray);
$dict->ExecuteSQLArray($sqlarray);

$flds = '
news_id I KEY NOT NULL,
fielddef_id I KEY NOT NULL,
value X(16384),
create_date DT,
modified_date DT
';

$sqlarray = $dict->CreateTableSQL(CMS_DB_PREFIX.'module_news_fieldvals', $flds, $taboptarray);
$dict->ExecuteSQLArray($sqlarray);

#Set Permissions
$this->CreatePermission('Modify News', 'Modify News');
$this->CreatePermission('Approve News', 'Approve News For Frontend Display');
$this->CreatePermission('Delete News', 'Delete News Articles');
$this->CreatePermission('Modify News Preferences', 'Modify News Module Settings');

$me = $this->GetName();
# Setup summary template
try {
  $summary_template_type = new CmsLayoutTemplateType();
  $summary_template_type->set_originator($me);
  $summary_template_type->set_name('summary');
  $summary_template_type->set_dflt_flag(TRUE);
  $summary_template_type->set_lang_callback('News::page_type_lang_callback');
  $summary_template_type->set_content_callback('News::reset_page_type_defaults');
  $summary_template_type->set_help_callback('News::template_help_callback');
  $summary_template_type->reset_content_to_factory();
  $summary_template_type->save();
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  $fn = __DIR__.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'orig_summary_template.tpl';
  if( file_exists( $fn ) ) {
    $content = @file_get_contents($fn);
    $tpl = new CmsLayoutTemplate();
    $tpl->set_originator($me);
    $tpl->set_name('News Summary Sample');
    $tpl->set_owner($uid);
    $tpl->set_content($content);
    $tpl->set_type($summary_template_type);
    $tpl->set_type_dflt(TRUE);
    $tpl->save();
  }
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  // Setup Simplex Theme HTML5 sample summary template
  $fn = __DIR__.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'Summary_Simplex_template.tpl';
  if( file_exists( $fn ) ) {
    $content = @file_get_contents($fn);
    $tpl = new CmsLayoutTemplate();
    $tpl->set_originator($me);
    $tpl->set_name('Simplex News Summary');
    $tpl->set_owner($uid);
    $tpl->set_content($content);
    $tpl->set_type($summary_template_type);
    $tpl->add_design('Simplex');
    $tpl->save();
  }
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  // Setup detail template
  $detail_template_type = new CmsLayoutTemplateType();
  $detail_template_type->set_originator($me);
  $detail_template_type->set_name('detail');
  $detail_template_type->set_dflt_flag(TRUE);
  $detail_template_type->set_lang_callback('News::page_type_lang_callback');
  $detail_template_type->set_content_callback('News::reset_page_type_defaults');
  $detail_template_type->reset_content_to_factory();
  $detail_template_type->set_help_callback('News::template_help_callback');
  $detail_template_type->save();
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  $fn = __DIR__.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'orig_detail_template.tpl';
  if( file_exists( $fn ) ) {
    $content = @file_get_contents($fn);
    $tpl = new CmsLayoutTemplate();
    $tpl->set_originator($me);
    $tpl->set_name('News Detail Sample');
    $tpl->set_owner($uid);
    $tpl->set_content($content);
    $tpl->set_type($detail_template_type);
    $tpl->set_type_dflt(TRUE);
    $tpl->save();
  }
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  // Setup Simplex Theme HTML5 sample detail template
  $fn = __DIR__.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'Simplex_Detail_template.tpl';
  if( file_exists( $fn ) ) {
    $content = @file_get_contents($fn);
    $tpl = new CmsLayoutTemplate();
    $tpl->set_originator($me);
    $tpl->set_name('Simplex News Detail');
    $tpl->set_owner($uid);
    $tpl->set_content($content);
    $tpl->set_type($detail_template_type);
    $tpl->add_design('Simplex');
    $tpl->save();
  }
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  // Setup form template
  $form_template_type = new CmsLayoutTemplateType();
  $form_template_type->set_originator($me);
  $form_template_type->set_name('form');
  $form_template_type->set_dflt_flag(TRUE);
  $form_template_type->set_lang_callback('News::page_type_lang_callback');
  $form_template_type->set_content_callback('News::reset_page_type_defaults');
  $form_template_type->reset_content_to_factory();
  $form_template_type->set_help_callback('News::template_help_callback');
  $form_template_type->save();
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  $fn = __DIR__.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'orig_form_template.tpl';
  if( file_exists( $fn ) ) {
    $content = @file_get_contents($fn);
    $tpl = new CmsLayoutTemplate();
    $tpl->set_originator($me);
    $tpl->set_name('News Fesubmit Form Sample');
    $tpl->set_owner($uid);
    $tpl->set_content($content);
    $tpl->set_type($form_template_type);
    $tpl->set_type_dflt(TRUE);
    $tpl->save();
  }
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  // Setup browsecat template
  $browsecat_template_type = new CmsLayoutTemplateType();
  $browsecat_template_type->set_originator($me);
  $browsecat_template_type->set_name('browsecat');
  $browsecat_template_type->set_dflt_flag(TRUE);
  $browsecat_template_type->set_lang_callback('News::page_type_lang_callback');
  $browsecat_template_type->set_content_callback('News::reset_page_type_defaults');
  $browsecat_template_type->reset_content_to_factory();
  $browsecat_template_type->set_help_callback('News::template_help_callback');
  $browsecat_template_type->save();
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

try {
  $fn = __DIR__.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'browsecat.tpl';
  if( file_exists( $fn ) ) {
    $content = @file_get_contents($fn);
    $tpl = new CmsLayoutTemplate();
    $tpl->set_originator($me);
    $tpl->set_name('News Browse Category Sample');
    $tpl->set_owner($uid);
    $tpl->set_content($content);
    $tpl->set_type($browsecat_template_type);
    $tpl->set_type_dflt(TRUE);
    $tpl->save();
  }
} catch( CmsException $e ) {
  // log it
  debug_to_log(__FILE__.':'.__LINE__.' '.$e->GetMessage());
  audit('',$me,'Installation Error: '.$e->GetMessage());
}

// Default email template and email preferences
$this->SetPreference('email_subject',$this->Lang('subject_newnews'));
$this->SetTemplate('email_template',$this->GetDfltEmailTemplate());

// Other preferences
$this->SetPreference('allowed_upload_types','gif,png,jpeg,jpg');
$this->SetPreference('auto_create_thumbnails','gif,png,jpeg,jpg');

// General category
$catid = $db->GenID(CMS_DB_PREFIX.'module_news_categories_seq');
$query = 'INSERT INTO '.CMS_DB_PREFIX.'module_news_categories (news_category_id, news_category_name, parent_id, create_date, modified_date) VALUES (?,?,?,'.$db->DbTimeStamp(time()).','.$db->DbTimeStamp(time()).')';
$db->Execute($query, [$catid, 'General', -1]);

// Initial news article
$articleid = $db->GenID(CMS_DB_PREFIX.'module_news_seq');
$query = 'INSERT INTO '.CMS_DB_PREFIX.'module_news ( NEWS_ID, NEWS_CATEGORY_ID, AUTHOR_ID, NEWS_TITLE, NEWS_DATA, NEWS_DATE, SUMMARY, START_TIME, END_TIME, STATUS, ICON, SEARCHABLE, CREATE_DATE, MODIFIED_DATE ) VALUES (?,?,?,?,?,'.$db->DbTimeStamp(time()).',?,?,?,?,?,?,'.$db->DbTimeStamp(time()).','.$db->DbTimeStamp(time()).')';
$db->Execute($query, [$articleid, $catid, 1, 'News Module Installed', 'The news module was installed.  Exciting. This news article is not using the Summary field and therefore there is no link to read more. But you can click on the news heading to read only this article.', null, null, null, 'published', null, 1]);
news_admin_ops::UpdateHierarchyPositions();

// Permissions
$perm_id = $db->GetOne('SELECT permission_id FROM '.CMS_DB_PREFIX."permissions WHERE permission_name = 'Modify News'");
$group_id = $db->GetOne('SELECT group_id FROM '.CMS_DB_PREFIX."groups WHERE group_name = 'Admin'");

$count = $db->GetOne('SELECT COUNT(*) FROM ' . CMS_DB_PREFIX . 'group_perms WHERE group_id = ? AND permission_id = ?', [$group_id, $perm_id]);
if (isset($count) && (int)$count == 0) {
  $new_id = $db->GenID(CMS_DB_PREFIX.'group_perms_seq');
  $query = 'INSERT INTO ' . CMS_DB_PREFIX . 'group_perms (group_perm_id, group_id, permission_id, create_date, modified_date) VALUES ('.$new_id.', '.$group_id.', '.$perm_id.', '. $db->DbTimeStamp(time()) . ', ' . $db->DbTimeStamp(time()) . ')';
  $db->Execute($query);
}

$group_id = $db->GetOne('SELECT group_id FROM '.CMS_DB_PREFIX."groups WHERE group_name = 'Editor'");

$count = $db->GetOne('SELECT COUNT(*) FROM ' . CMS_DB_PREFIX . 'group_perms WHERE group_id = ? AND permission_id = ?', [$group_id, $perm_id]);
if (isset($count) && (int)$count == 0) {
  $new_id = $db->GenID(CMS_DB_PREFIX.'group_perms_seq');
  $query = 'INSERT INTO ' . CMS_DB_PREFIX . 'group_perms (group_perm_id, group_id, permission_id, create_date, modified_date) VALUES ('.$new_id.', '.$group_id.', '.$perm_id.', '. $db->DbTimeStamp(time()) . ', ' . $db->DbTimeStamp(time()) . ')';
  $db->Execute($query);
}

// Indices
$sqlarray = $dict->CreateIndexSQL('news_postdate',
          CMS_DB_PREFIX.'module_news', 'news_date');
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->CreateIndexSQL('news_daterange',
          CMS_DB_PREFIX.'module_news', 'start_time,end_time');
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->CreateIndexSQL('news_author',
          CMS_DB_PREFIX.'module_news', 'author_id');
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->CreateIndexSQL('news_hier',
          CMS_DB_PREFIX.'module_news', 'news_category_id');
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->CreateIndexSQL('news_url',
          CMS_DB_PREFIX.'module_news', 'news_url');
$dict->ExecuteSQLArray($sqlarray);
/* useless replication of news_daterange index
$sqlarray = $dict->CreateIndexSQL('news_startenddate',
          CMS_DB_PREFIX.'module_news', 'start_time,end_time');
$dict->ExecuteSQLArray($sqlarray);
*/
// Events
$this->CreateEvent('NewsArticleAdded');
$this->CreateEvent('NewsArticleEdited');
$this->CreateEvent('NewsArticleDeleted');
$this->CreateEvent('NewsCategoryAdded');
$this->CreateEvent('NewsCategoryEdited');
$this->CreateEvent('NewsCategoryDeleted');

$this->RegisterModulePlugin(TRUE); //CHECKME in module i.e. each session?
$this->RegisterSmartyPlugin('news', 'function', 'function_plugin');

// and routes...
$this->CreateStaticRoutes();
