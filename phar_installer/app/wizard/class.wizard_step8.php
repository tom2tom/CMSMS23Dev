<?php

namespace cms_autoinstaller;

use cms_config;
use cms_siteprefs;
use CmsApp;
use CMSMS\Database\mysqli\Connection;
use Exception;
use const CMS_DB_PREFIX;
use function cmsms;


class wizard_step8 extends wizard_step
{
    protected function process()
    {
        // nothing here
    }

    private function &db_connect($destconfig)
    {
        if( !defined('CMS_DB_PREFIX') ) define('CMS_DB_PREFIX',$destconfig['db_prefix']);
        $db = new Connection($destconfig);
        $db->Execute("SET NAMES 'utf8'");
        CmsApp::get_instance()->_setDb($db);
        return $db;
    }

    private function connect_to_cmsms($destdir)
    {
        global $CMS_INSTALL_PAGE, $DONT_LOAD_DB, $DONT_LOAD_SMARTY, $CMS_VERSION, $CMS_PHAR_INSTALLER;
        $CMS_INSTALL_PAGE = 1;
        $DONT_LOAD_DB = 1;
        $DONT_LOAD_SMARTY = 1;
        $CMS_PHAR_INSTALLER = 1;
        $CMS_VERSION = $this->get_wizard()->get_data('destversion');

        // setup and initialize the CMSMS API's
        // note DONT_LOAD_DB and DONT_LOAD_SMARTY are true
        if( is_file("$destdir/include.php") ) {
            include_once $destdir.'/include.php';
        }
        else {
            include_once $destdir.'/lib/include.php';
        }

    }

    private function do_install()
    {
        $destdir = \__appbase\get_app()->get_destdir();
        if( !$destdir ) throw new Exception(\__appbase\lang('error_internal',700));

        $adminaccount = $this->get_wizard()->get_data('adminaccount');
        if( !$adminaccount ) throw new Exception(\__appbase\lang('error_internal',701));

        $destconfig = $this->get_wizard()->get_data('config');
        if( !$destconfig ) throw new Exception(\__appbase\lang('error_internal',703));

        $siteinfo = $this->get_wizard()->get_data('siteinfo');
        if( !$siteinfo ) throw new Exception(\__appbase\lang('error_internal',704));

        $this->connect_to_cmsms($destdir);

        // connect to the database
        $db = $this->db_connect($destconfig);

        $dir = \__appbase\get_app()->get_appdir().'/install';

        include_once __DIR__.'/msg_functions.php';

        try {
            // create some variables that the sub functions need.
            if( !defined('CMS_ADODB_DT') ) define('CMS_ADODB_DT','DT');
            $admin_user = null;
            $db_prefix = CMS_DB_PREFIX;

            // install the schema
            $this->message(\__appbase\lang('install_schema'));
            $fn = $dir.'/schema.php';
            if( !file_exists($fn) ) throw new Exception(\__appbase\lang('error_internal',705));

            global $CMS_INSTALL_DROP_TABLES, $CMS_INSTALL_CREATE_TABLES;
            $CMS_INSTALL_DROP_TABLES=1;
            $CMS_INSTALL_CREATE_TABLES=1;
            include_once $fn;

            $this->verbose(\__appbase\lang('install_setsequence'));
            include_once $dir.'/createseq.php';

            // create tmp directories
            $this->verbose(\__appbase\lang('install_createtmpdirs'));
            @mkdir($destdir.'/tmp/cache',0771,TRUE);
            @mkdir($destdir.'/tmp/templates_c',0771,TRUE);

            include_once $dir.'/base.php';

            $this->message(\__appbase\lang('install_defaultcontent'));
            if( $destconfig['samplecontent'] ) {
                $xmlfile = $dir . DIRECTORY_SEPARATOR . \cms_autoinstaller\cms_install::CONTENTXML;
                if( is_file($xmlfile) ) {
                    global $CMS_INSTALL_PAGE;
                    $CMS_INSTALL_PAGE = 1;
                    $fp = CMS_ADMIN_PATH . DIRECTORY_SEPARATOR . 'function.contentoperation.php';
                    require_once $fp;
                    import_content($xmlfile);
                }
            }
            else {
                 include_once $dir.'/initial.php';
            }

            $this->verbose(\__appbase\lang('install_setsitename'));
            cms_siteprefs::set('sitename',$siteinfo['sitename']);

            $this->write_config();

            // update all hierarchy positions
            $this->message(\__appbase\lang('install_updatehierarchy'));
            $contentops = cmsms()->GetContentOperations();
            $contentops->SetAllHierarchyPositions();

            // todo: install default preferences
            cms_siteprefs::set('global_umask','022');
        }
        catch( Exception $e ) {
            die('got exception');
            $this->error($e->GetMessage());
        }
    }

    private function do_upgrade($version_info)
    {
        global $CMS_INSTALL_PAGE, $DONT_LOAD_DB, $DONT_LOAD_SMARTY, $CMS_VERSION, $CMS_PHAR_INSTALLER;
        $CMS_INSTALL_PAGE = 1;
        $CMS_PHAR_INSTALLER = 1;
        $DONT_LOAD_DB = 1;
        $DONT_LOAD_SMARTY = 1;
        $CMS_VERSION = $this->get_wizard()->get_data('destversion');

        // get the list of all available versions that this upgrader knows about
        $app = \__appbase\get_app();
        $dir =  $app->get_appdir().'/upgrade';
        if( !is_dir($dir) ) throw new Exception(\__appbase\lang('error_internal',710));
        $destdir = $app->get_destdir();
        if( !$destdir ) throw new Exception(\__appbase\lang('error_internal',711));

        $dh = opendir($dir);
        $versions = array();
        if( !$dh ) throw new Exception(\__appbase\lang('error_internal',712));
        while( ($file = readdir($dh)) !== false ) {
            if( $file == '.' || $file == '..' ) continue;
            if( is_dir($dir.'/'.$file) && (is_file("$dir/$file/MANIFEST.DAT") || is_file("$dir/$file/MANIFEST.DAT.gz")) ) $versions[] = $file;
        }
        closedir($dh);
        if( count($versions) ) usort($versions,'version_compare');

        $destconfig = $this->get_wizard()->get_data('config');
        if( !$destconfig ) throw new Exception(\__appbase\lang('error_internal',703));

        // setup and initialize the cmsms API's
        if( is_file("$destdir/include.php") ) {
            include_once $destdir.'/include.php';
        }
        else {
            include_once $destdir.'/lib/include.php';
        }

        // setup database connection
        $db = $this->db_connect($destconfig);

        include_once __DIR__.'/msg_functions.php';

        try {
            // ready to do the upgrading now (in a loop)
            // only perform upgrades for the versions known by the installer that are greater than what is instaled.
            $current_version = $version_info['version'];
            foreach( $versions as $ver ) {
                $fn = "$dir/$ver/upgrade.php";
                if( version_compare($current_version,$ver) < 0 && is_file($fn) ) {
                    include_once $fn;
                }
            }

            $this->write_config();

            $this->message(\__appbase\lang('done'));
        }
        catch( Exception $e ) {
            $this->error($e->GetMessage());
        }
    }

    private function do_freshen()
    {
        try {
            $this->write_config();
        }
        catch( Exception $e ) {
            $this->error($e->GetMessage());
        }
    }

    private function write_config()
    {
        $destconfig = $this->get_wizard()->get_data('config');
        if( !$destconfig ) throw new Exception(\__appbase\lang('error_internal',703));

        $destdir = \__appbase\get_app()->get_destdir();
        if( !$destdir ) throw new Exception(\__appbase\lang('error_internal',700));

        // create new config file.
        // this step has to go here.... as config file has to exist in step9
        // so that CMSMS can connect to the database.
        $fn = $destdir."/config.php";
        if( is_file($fn) ) {
            $this->verbose(\__appbase\lang('install_backupconfig'));
            $destfn = $destdir.'/bak.config.php';
            if( !copy($fn,$destfn) ) throw new Exception(\__appbase\lang('error_backupconfig'));
        }

        $this->connect_to_cmsms($destdir);

        $this->message(\__appbase\lang('install_createconfig'));
        $newconfig = cms_config::get_instance();
        $newconfig['dbms'] = 'mysqli'; //trim($destconfig['db_type']);
        $newconfig['db_hostname'] = trim($destconfig['db_hostname']);
        $newconfig['db_username'] = trim($destconfig['db_username']);
        $newconfig['db_password'] = trim($destconfig['db_password']);
        $newconfig['db_name'] = trim($destconfig['db_name']);
        $newconfig['db_prefix'] = trim($destconfig['db_prefix']);
        $newconfig['timezone'] = trim($destconfig['timezone']);
        if( $destconfig['query_var'] ) $newconfig['query_var'] = trim($destconfig['query_var']);
        if( isset($destconfig['db_port']) ) {
            $num = (int)$destconfig['db_port'];
            if( $num > 0 ) $newconfig['db_port'] = $num;
        }
        $newconfig->save();
    }

    protected function display()
    {
        parent::display();
        \__appbase\smarty()->assign('next_url',$this->get_wizard()->next_url());
        echo \__appbase\smarty()->display('wizard_step8.tpl');

        // here, we do either the upgrade, or the install stuff.
        try {
            $action = $this->get_wizard()->get_data('action');
            $tmp = $this->get_wizard()->get_data('version_info');
            if( $action == 'upgrade' && is_array($tmp) && count($tmp) ) {
                $this->do_upgrade($tmp);
            }
            elseif( $action == 'freshen' ) {
                $this->do_freshen();
            }
            elseif( $action == 'install' ) {
                $this->do_install();
            }
            else {
                throw new Exception(\__appbase\lang('error_internal',705));
            }
        }
        catch( Exception $e ) {
            $this->error($e->GetMessage());
        }

        $this->finish();
    }
} // class

