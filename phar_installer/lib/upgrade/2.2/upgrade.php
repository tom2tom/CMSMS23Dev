<?php

use function cms_installer\get_app;

status_msg('Performing structure changes for CMSMS 2.2');

$app = get_app();
$destdir = $app->get_destdir();

$create_private_dir = function($relative_dir) use ($destdir) {
    $relative_dir = trim($relative_dir);
    if( !$relative_dir ) return;

    $dir = $destdir.DIRECTORY_SEPARATOR.$relative_dir;
    if( !is_dir($dir) ) {
        @mkdir($dir,0771,true);
    }
    @touch($dir.DIRECTORY_SEPARATOR.'index.html');
};

$move_directory_files = function($srcdir, $destdir) {
    $srcdir = trim($srcdir);
    $destdir = trim($destdir);
    if( !is_dir($srcdir) ) return;

    $files = glob($srcdir.DIRECTORY_SEPARATOR.'*');
    if( !$files ) return;

    foreach( $files as $src ) {
        $bn = basename($src);
        $dest = $destdir.DIRECTORY_SEPARATOR.$bn;
        rename($src,$dest);
    }
    @touch($dir.DIRECTORY_SEPARATOR.'index.html');
};

//$gCms = cmsms();
$dbdict = $db->NewDataDictionary();
$taboptarray = array('mysql' => 'TYPE=MyISAM');

$sqlarray = $dbdict->AddColumnSQL(CMS_DB_PREFIX.CmsLayoutTemplateType::TABLENAME,'help_content_cb C(255), one_only I1');
$dbdict->ExecuteSQLArray($sqlarray);

verbose_msg(ilang('upgrading_schema',202));
$query = 'UPDATE '.CMS_DB_PREFIX.'version SET version = 202';
$db->Execute($query);

$type = CmsLayoutTemplateType::load('__CORE__::page');
$type->set_help_callback('CmsTemplateResource::template_help_callback');
$type->save();

$type = CmsLayoutTemplateType::load('__CORE__::generic');
$type->set_help_callback('CmsTemplateResource::template_help_callback');
$type->save();

// create the assets (however named) directory structure
verbose_msg('Creating assets structure');
$config = $app->get_config();
$aname = (!empty($config['assetsdir'])) ? $config['assetsdir'] : 'assets';
$create_private_dir($aname.DIRECTORY_SEPARATOR.'templates');
$create_private_dir($aname.DIRECTORY_SEPARATOR.'configs');
$create_private_dir($aname.DIRECTORY_SEPARATOR.'module_custom');
$create_private_dir($aname.DIRECTORY_SEPARATOR.'admin_custom');
$create_private_dir($aname.DIRECTORY_SEPARATOR.'plugins');
$create_private_dir($aname.DIRECTORY_SEPARATOR.'images');
$create_private_dir($aname.DIRECTORY_SEPARATOR.'css');
$srcdir = $destdir.DIRECTORY_SEPARATOR.'module_custom';
if( is_dir($srcdir) ) {
    $move_directory_files($srcdir,$destdir.DIRECTORY_SEPARATOR.$aname.'/module_custom');
}
$srcdir = $destdir.'/admin/custom';
if( is_dir($srcdir) ) {
    $move_directory_files($srcdir,$destdir.DIRECTORY_SEPARATOR.$aname.'/admin_custom');
}
$srcdir = $destdir.'/tmp/configs';
if( is_dir($srcdir) ) {
    $move_directory_files($srcdir,$destdir.DIRECTORY_SEPARATOR.$aname.'/configs');
}
$srcdir = $destdir.'/tmp/templates';
if( is_dir($srcdir) ) {
    $move_directory_files($srcdir,$destdir.DIRECTORY_SEPARATOR.$aname.'/templates');
}