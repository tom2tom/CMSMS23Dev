<?php

namespace cms_installer\wizard;

use cms_installer\utils;
use Exception;
use LogicException;
use function cms_installer\CMSMS\lang;
use function cms_installer\CMSMS\smarty;
use function cms_installer\get_app;
use function GetDb;

class wizard_step4 extends wizard_step
{
    private $_config;
    private $_dbms_options;

    public function __construct()
    {
        if( !extension_loaded('mysqli') ) throw new Exception(lang('error_nodatabases'));

        parent::__construct();

        $tz = date_default_timezone_get();
        if( !$tz ) {
            $tz = 'UTC';
            @date_default_timezone_set('UTC');
        }
        $this->_config = [
            'db_type'=>'mysqli',
            'db_hostname'=>'localhost',
            'db_name'=>'',
            'db_username'=>'',
            'db_password'=>'',
            'db_prefix'=>'cms_',
            'db_port'=>'',
            'query_var'=>'',
            'timezone'=>$tz,
            'samplecontent'=>FALSE,
        ];

        // get saved data
        $tmp = $this->get_wizard()->get_data('config');
        if( $tmp ) $this->_config = array_merge($this->_config,$tmp);

        $action = $this->get_wizard()->get_data('action');
        if( $action == 'freshen' || $action == 'upgrade' ) {
            // read config data from config.php for these actions
            $app = get_app();
            $destdir = $app->get_destdir();
            $config_file = $destdir.DIRECTORY_SEPARATOR.'config.php';
            include_once $config_file;
//            $this->_config['db_type'] = /*$config['db_type'] ?? $config['dbms'] ??*/ 'mysqli';
            $this->_config['db_hostname'] = $config['db_hostname'];
            $this->_config['db_username'] = $config['db_username'];
            $this->_config['db_password'] = $config['db_password'];
            $this->_config['db_name'] = $config['db_name'];
            $this->_config['db_prefix'] = $config['db_prefix'];
            if( isset($config['db_port']) ) $this->_config['db_port'] = $config['db_port'];
            if( isset($config['timezone']) ) $this->_config['timezone'] = $config['timezone'];
            if( isset($config['query_var']) ) $this->_config['query_var'] = $config['query_var'];
        }
    }

    private function validate($config)
    {
        $action = $this->get_wizard()->get_data('action');
//        if( empty($config['db_type']) ) throw new Exception(lang('error_nodbtype'));
        if( empty($config['db_hostname']) ) throw new Exception(lang('error_nodbhost'));
        if( empty($config['db_name']) ) throw new Exception(lang('error_nodbname'));
        if( empty($config['db_username']) ) throw new Exception(lang('error_nodbuser'));
        if( empty($config['db_password']) ) throw new Exception(lang('error_nodbpass'));
        if( empty($config['db_prefix']) && $action == 'install' ) throw new Exception(lang('error_nodbprefix'));
        if( empty($config['timezone']) ) throw new Exception(lang('error_notimezone'));

        $re = '/^[a-zA-Z0-9_\.]*$/';
        if( !empty($config['query_var']) && !preg_match($re,$config['query_var']) ) {
            throw new Exception(lang('error_invalidqueryvar'));
        }

        $all_timezones = timezone_identifiers_list();
        if( !in_array($config['timezone'],$all_timezones) ) throw new Exception(lang('error_invalidtimezone'));

        $config['db_password'] = trim($config['db_password']);
        if( $config['db_password'] ) {
            $tmp = filter_var($config['db_password'], FILTER_SANITIZE_STRING,
                FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK | FILTER_FLAG_NO_ENCODE_QUOTES);
            if( $tmp != $config['db_password'] ) {
                throw new Exception(lang('error_invaliddbpassword'));
            }
        }

        // try a test connection
        require_once dirname(__DIR__,2).DIRECTORY_SEPARATOR.'CMSMS'.DIRECTORY_SEPARATOR.'dbaccessor.functions.php';
        try {
            $db = GetDb($config);
        }
        catch( Exception $e ) {
            throw new Exception($e->getMessage().' : '.lang('error_createtable'));
        }
        // see if we can create and drop a table.
        try {
            $db->Execute('CREATE TABLE '.$config['db_prefix'].'_dummyinstall (i INT)');
        }
        catch( Exception $e ) {
            throw new Exception(lang('error_createtable'));
        }

        try {
            $db->Execute('DROP TABLE '.$config['db_prefix'].'_dummyinstall');
        }
        catch( Exception $e ) {
            throw new Exception(lang('error_droptable'));
        }

        // see if a smattering of core tables exist
        if( $action == 'install' ) {
            try {
                $res = $db->GetOne('SELECT content_id FROM '.$config['db_prefix'].'content');
                if( $res > 0 ) throw new Exception(lang('error_cmstablesexist'));
            }
            catch( LogicException $e ) {
                // if this fails it's not a problem
            }

            try {
                $db->GetOne('SELECT module_name FROM '.$config['db_prefix'].'modules');
                if( $res > 0 ) throw new Exception(lang('error_cmstablesexist'));
            }
            catch( LogicException $e ) {
                // if this fails it's not a problem.
            }
        }
    }

    protected function process()
    {
        $this->_config['db_type'] = 'mysqli';
//        if( isset($_POST['db_type']) ) $this->_config['db_type'] = utils::clean_string($_POST['db_type']);
        $this->_config['db_hostname'] = utils::clean_string($_POST['db_hostname']);
        $this->_config['db_name'] = utils::clean_string($_POST['db_name']);
        $this->_config['db_username'] = utils::clean_string($_POST['db_username']);
        $this->_config['db_password'] = trim(filter_var($_POST['db_password'], FILTER_SANITIZE_STRING,
            FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK | FILTER_FLAG_NO_ENCODE_QUOTES));
        if( isset($_POST['db_port']) ) $this->_config['db_port'] = filter_var($_POST['db_port'],FILTER_SANITIZE_NUMBER_INT);
        if( isset($_POST['db_prefix']) ) $this->_config['db_prefix'] = utils::clean_string($_POST['db_prefix']);
        $this->_config['timezone'] = utils::clean_string($_POST['timezone']);
        if( isset($_POST['query_var']) ) $this->_config['query_var'] = utils::clean_string($_POST['query_var']);
        $this->get_wizard()->set_data('config',$this->_config);

        try {
            $app = get_app();
            $config = $app->get_config();
            $this->validate($this->_config);
            $url = $this->get_wizard()->next_url();
            utils::redirect($url);
        }
        catch( Exception $e ) {
            $smarty = smarty();
            $smarty->assign('error',$e->GetMessage());
        }
    }

    protected function display()
    {
        parent::display();
        $smarty = smarty();

        $tmp = timezone_identifiers_list();
        if( !is_array($tmp) ) throw new Exception(lang('error_tzlist'));
        $tmp2 = array_combine(array_values($tmp),array_values($tmp));
        $smarty->assign('timezones',array_merge([''=>lang('none')],$tmp2));
//        $smarty->assign('db_types',$this->_dbms_options);
        $smarty->assign('action',$this->get_wizard()->get_data('action'));
        $raw = $this->_config['verbose'] ?? 0;
//        $v = ($raw === null) ? $this->get_wizard()->get_data('verbose',0) : (int)$raw;
        $smarty->assign('verbose',(int)$raw);
        $smarty->assign('config',$this->_config);
        $smarty->display('wizard_step4.tpl');

        $this->finish();
    }

} // class
