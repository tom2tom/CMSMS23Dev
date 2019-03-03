<?php
# asynchronous jobs class
# Copyright (C) 2017-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
# Thanks to Robert Campbell and all other contributors from the CMSMS Development Team.
# This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.

namespace CMSMS\Async;

use CmsApp;

/**
 * A singleton class for asynchronous jobs.
 * This is a wrapper for methods in the relevant async-jobs module.
 *
 * @package CMS
 * @author Robert Campbell
 *
 * @since 2.2
 */
final class JobManager
{
    /**
     * @ignore
     */
    private $_mod;

    /**
     * @ignore
     */
    private static $_instance = null;

    /**
     * @ignore
     */
    private function __construct() {}

    /**
     * @ignore
     */
    private function __clone() {}

    /**
     * Get the sole permitted instance of this object
     *
     * @return CMSMS\Async\JobManager
     */
    final public static function get_instance() : self
    {
        if( !self::$_instance ) self::$_instance = new self();
        return self::$_instance;
    }

    /**
     * Get the module that handles job requests.
     *
     * @internal
     * @return mixed CMSModule | null
     */
    protected function get_mod()
    {
        if( !isset($this->_mod) ) $this->_mod = CmsApp::get_instance()->GetJobManager();
        return $this->_mod;
    }

    /**
     * Trigger asynchronous processing.
     *
     * @internal
     */
    public function trigger_async_processing()
    {
        $mod = $this->get_mod();
        if( $mod ) return $mod->trigger_async_processing();
    }

    /**
     * Given an integer job id, load the job.
     *
     * @param int $job_id
     * @return mixed Job | null
     */
    public function load_job( $job_id )
    {
        $mod = $this->get_mod();
        if( $mod ) return $mod->load_job_by_id( $job_id );
    }

    /**
     * Save a job to the queue.
     *
     * @param Job $job
     * @return mixed int | null The id of the job.
     */
    public function save_job( Job &$job )
    {
        $mod = $this->get_mod();
        if( $mod ) return $mod->save_job($job);
    }

    /**
     * Remove a job from the queue
     *
     * Note: After calling this method, the job object itself is invalid and cannot be saved.
     *
     * @param Job $job
	 * @return mixed
     */
    public function delete_job( Job &$job )
    {
        $mod = $this->get_mod();
        if( $mod ) return $mod->delete_job($job);
    }

    /**
     * Remove all jobs originating from a specific module
     *
     * @param mixed string $module_name | null
	 * @return mixed
     */
    public function delete_jobs_by_module( $module_name )
    {
        $mod = $this->get_mod();
        if( $mod ) return $mod->delete_job($module_name);
    }
} // class
