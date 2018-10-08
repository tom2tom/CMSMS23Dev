<?php

use CMSMS\AdminAlerts\TranslatableAlert;

class CmsSecurityCheckTask implements CmsRegularTask
{
    const  LASTEXECUTE_SITEPREF   = __CLASS__;

    public function get_name()
    {
        return __CLASS__; //assume no namespace
    }

    public function get_description()
    {
        return __CLASS__; //lazy
    }

    public function test($time = '')
    {
        // do we need to do this task now? (daily intervals)
        if( !$time ) $time = time();
        $last_execute = cms_siteprefs::get(self::LASTEXECUTE_SITEPREF,0);
        return ($time - 24*3600) >= $last_execute;
    }

    public function execute($time = '')
    {
        if( !$time ) $time = time();

        // check if config is writable
        if( is_writable(CONFIG_FILE_LOCATION) ) {
            $alert = new TranslatableAlert('Modify Site Preferences');
            $alert->name = __CLASS__.'config'; // so that there can only ever be one alert of this type at a time.
            $alert->msgkey = 'config_writable';
            $alert->priority = $alert::PRIORITY_HIGH;
            $alert->titlekey = 'security_issue';
            $alert->save();
        }

        // check if install file exists
        $pattern = cms_join_path(CMS_ROOT_PATH,'cmsms-*-install.php');
        $files = glob($pattern);
        if( $files ) {
            $fn = basename($files[0]);
            $alert = new TranslatableAlert('Modify Site Preferences');
            $alert->name = __CLASS__.'install';
            $alert->msgkey = 'installfileexists';
            $alert->msgargs = $fn;
            $alert->priority = $alert::PRIORITY_HIGH;
            $alert->titlekey = 'security_issue';
            $alert->save();
        }

        // check if mail is configured
        // not really a security issue... but meh, it saves another class.
        if(  !cms_siteprefs::get('mail_is_set',0) ) {
            $alert = new TranslatableAlert('Modify Site Preferences');
            $alert->name = __CLASS__.'mail';
            $alert->msgkey = 'info_mail_notset';
            $alert->priority = $alert::PRIORITY_HIGH;
            $alert->titlekey = 'config_issue';
            $alert->save();
        }
        return TRUE;
    }

    public function on_success($time = '')
    {
        if( !$time ) $time = time();
        cms_siteprefs::set(self::LASTEXECUTE_SITEPREF,$time);
    }

    public function on_failure($time = '')
    {
        // nothing here.
    }
}
