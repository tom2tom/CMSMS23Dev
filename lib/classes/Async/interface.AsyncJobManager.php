<?php
# Interface AsyncJobManager: API definition for modules which process asynchronous tasks.
# Copyright (C) 2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

/**
 * This interface defines the minimum API for processing asynchronous background jobs
 *
 * @package CMS
 *
 * @since 2.3
 */
interface AsyncJobManager
{
	public function load_jobs_by_module($module);

	public function load_job_by_id(int $job_id);

	public function load_job(Job $job) : int;

	public function unload_jobs_by_module(string $module_name);

	public function unload_job_by_id(int $job_id);

	public function unload_job_by_name(string $module_name, string $job_name);

	public function unload_job(Job $job);
	 /**
	  * Initiate job-processing, if it's currently appropriate to do so
	 */
	public function begin_async_processing();
	// static variant of begin_async_processing()
	public static function begin_async_work();
}