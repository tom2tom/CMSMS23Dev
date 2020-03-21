<?php

use CMSMS\AdminUtils;

class ClearCacheTask implements CmsRegularTask
{
    const  LASTRUN_SITEPREF = self::class.'\\\\lastexecute'; //sep was ::, now cms_siteprefs::NAMESPACER
    const  LIFETIME_SITEPREF = self::class.'\\\\auto_clear_cache_age';

    private $_age_days;

    public function get_name()
    {
        return self::class;
    }

    public function get_description()
    {
        return lang_by_realm('tasks','clearcache_taskdescription');
    }

    public function test($time = 0)
    {
        $this->_age_days = (int)cms_siteprefs::get(self::LIFETIME_SITEPREF,0);
        if( $this->_age_days == 0 ) return FALSE;

        // do we need to do this task now? (daily intervals)
        if( !$time ) $time = time();
        $last_execute = (int)cms_siteprefs::get(self::LASTRUN_SITEPREF,0);
        if( ($time - 24*3600) >= $last_execute ) {
            // set this preference here... prevents multiple requests at or about the same time from getting here.
            cms_siteprefs::set(self::LASTRUN_SITEPREF,$time);
            return TRUE;
        }
        return FALSE;
    }

    public function execute($time = 0)
    {
        // do the task.
        AdminUtils::clear_cached_files($this->_age_days);
        return TRUE;
    }

    public function on_success($time = 0)
    {
        if( !$time ) $time = time();
        cms_siteprefs::set(self::LASTRUN_SITEPREF,$time);
    }

    public function on_failure($time = 0)
    {
        // we failed, try again at the next request
        cms_siteprefs::remove(self::LASTRUN_SITEPREF);
    }
} // class
