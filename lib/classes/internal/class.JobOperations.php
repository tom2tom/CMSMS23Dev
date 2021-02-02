<?php
/*
Class of async-job methods
Copyright (C) 2021 CMS Made Simple Foundation <foundation@cmsmadesimple.org>

This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>

CMS Made Simple is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of that license, or
(at your option) any later version.

CMS Made Simple is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of that license along with CMS Made Simple.
If not, see <https://www.gnu.org/licenses/>.
*/
namespace CMSMS\internal;

use BadMethodCallException;
use CMSMS\AppParams;
use CMSMS\AppSingle;
use CMSMS\Async\CronJob;
use CMSMS\Async\Job;
use CMSMS\Async\RecurType;
use CMSMS\Async\RegularJob;
use CMSMS\Events;
use CMSMS\IRegularTask;
use CMSMS\ModuleOperations;
use CMSMS\RequestParameters;
use CMSMS\SysDataCacheDriver;
use CMSMS\Utils;
use CmsRegularTask;
use InvalidArgumentException;
use LogicException;
use Throwable;
use const ASYNCLOG;
use const CMS_ADMIN_PATH;
use const CMS_DB_PREFIX;
use const CMS_ROOT_PATH;
use const CMS_SECURE_PARAM_NAME;
use const TMP_CACHE_LOCATION;
use function audit;
use function cms_join_path;
use function cms_path_to_url;
use function debug_to_log;
use function error_log;

/**
 * @since 2.99
 */
final class JobOperations
{
//    private const TABLE_NAME = CMS_DB_PREFIX.'mod_cmsjobmgr'; // TODO 'asyncjobs'
    private const TABLE_NAME = CMS_DB_PREFIX.'asyncjobs';
    private const EVT_FAILEDJOB = 'JobFailed'; // TODO also process events e.g. module [un]install
    private const LOCKPREF = 'joblock';
    private const MANAGE_JOBS = 'Manage Jobs';

    /**
     * Maximum no. of jobs per batch
     * Should never be > 100 pending jobs for a site
     */
    private const MAXJOBS = 50;
    /**
     * Interval (seconds) between bad-job cleanups
     */
    private const MINGAP = 3600;
    /**
     * Minimum no. of errors which signals a 'bad' job
     */
    private const MINERRORS = 10;

    private const LOGFILE = TMP_CACHE_LOCATION.DIRECTORY_SEPARATOR.'joberrs.log';
    private $ASYNCLOG = TMP_CACHE_LOCATION.DIRECTORY_SEPARATOR.'debug.log'; // TODO $log = defined('ASYNCLOG');
    private $_current_job;

    private $_lock = 0;

    /**
     * Get the interval between job-runs
     * @return int seconds
     */
    public function get_async_freq() : int
    {
        return AppParams::get('jobinterval', 180); //seconds
    }

    /**
     * Check whether the specified Job recurs
     * @param Job $job
     * @return bool
     */
    public function job_recurs(Job $job) : bool
    {
        if ($job instanceof CronJob) {
            return $job->frequency != RecurType::RECUR_NONE;
        }
        return false;
    }

    /**
     * Get a timestamp representing the earliest time when the specified
     * job will next be processed. Or 0 if it's not to be processed.
     * @param Job $job
     * @return int
     */
    public function calculate_next_start_time(Job $job) : int
    {
        if (!$this->job_recurs($job)) {
            return 0;
        }
        $now = $job->start;
        if ($now == 0) {
            return 0;
        }
        switch ($job->frequency) {
        case RecurType::RECUR_15M:
            $out = $now + 900;
            break;
        case RecurType::RECUR_30M:
            $out = $now + 1800;
            break;
        case RecurType::RECUR_HOURLY:
            $out = $now + 3600;
            break;
        case RecurType::RECUR_120M:
            $out = $now + 7200;
            break;
        case RecurType::RECUR_180M:
            $out = $now + 10800;
            break;
        case RecurType::RECUR_12H:
            $out = $now + 43200;
            break;
        case RecurType::RECUR_DAILY:
            $out = strtotime('+1 day', $now);
            break;
        case RecurType::RECUR_WEEKLY:
            $out = strtotime('+1 week', $now);
            break;
        case RecurType::RECUR_MONTHLY:
            $out = strtotime('+1 month', $now);
            break;
        case RecurType::RECUR_ALWAYS:
            $out = $now;
            break;
//      case RecurType::RECUR_ONCE:
        default:
            $out = 0;
            break;
        }
        if ($out) {
            $out = max($out, time()+1); // next start cannot be < time()
            if (!$job->until || $out <= $job->until) {
                debug_to_log("adjusted to $out -- $now // {$job->until}");
//                $d = $out - $now;
//                error_log($job->name." next start @ last-start + $d)"."\n", 3, ASYNCLOG);
                return $out;
            }
        }
        return 0;
    }

    public function clear_errors()
    {
        //TODO
    }

    /**
     * Transfer the file-stored job-errors log data to the database
     */
    public function process_errors()
    {
        $fn = self::LOGFILE;
        if (!is_file($fn)) {
            return;
        }

        $data = file_get_contents($fn);
        @unlink($fn);
        if (!$data) {
            return;
        }

        $tmp = explode("\n", $data);
        if (!is_array($tmp) || !count($tmp)) {
            return;
        }

        $job_ids = [];
        foreach ($tmp as $one) {
            $one = (int) $one;
            if ($one < 1) {
                continue;
            }
            if (!in_array($one, $job_ids)) {
                $job_ids[] = $one;
            }
        }

        // we have job(s) whose error count needs to be increased
        $db = AppSingle::Db();
        $sql = 'UPDATE '.self::TABLE_NAME.' SET errors = errors + 1 WHERE id IN ('.implode(',', $job_ids).')';
        $db->Execute($sql);
        debug_to_log('Increased error count on '.count($job_ids).' jobs ');
    }

    /**
     * Record (in a file) the id of a job where an error occurred, for later processing
     * @param int $job_id
     */
    public function put_error(int $job_id)
    {
        $fh = fopen(self::LOGFILE, 'a');
        fwrite($fh, $job_id."\n");
        fclose($fh);
    }

    /**
     * @param mixed $job
     * @param string $errmsg
     * @param string $errfile
     * @param string $errline
     */
    public function joberrorhandler($job, string $errmsg, string $errfile, string $errline)
    {
        debug_to_log('Fatal error occurred processing async jobs at: '.$errfile.':'.$errline);
        debug_to_log('Msg: '.$errmsg);

        if (is_object($job)) {
            $this->put_error($job->id);
        }
    }

    /**
     * Populate or refresh the database tasks-store for each discovered
     * CmsRegularTask object and Job object
     *
     * @param bool $force optional flag whether to clear the store before polling. Default false
     * @return int count of job(s) processed
     */
    public function refresh_jobs(bool $force = false) : int
    {
        $res = 0;

        if ($force) {
            $db = AppSingle::Db();
            $db->Execute('DELETE FROM '.self::TABLE_NAME); // TRUNCATE ?
            $db->Execute('ALTER TABLE '.self::TABLE_NAME.' AUTO_INCREMENT=1');
        }

        // Get job objects from files
        $patn = cms_join_path(CMS_ROOT_PATH, 'lib', 'classes', 'jobs', 'class.*.php');
        $files = glob($patn);
        foreach ($files as $p) {
            $classname = 'CMSMS\\jobs\\';
            $tmp = explode('.', basename($p));
            if (count($tmp) == 4 && $tmp[2] == 'task') {
                $classname .= $tmp[1].'Task';
            } else {
                $classname .= $tmp[1];
            }
            require_once $p;
            try {
                $obj = new $classname();
                if ($obj instanceof CmsRegularTask || $obj instanceof IRegularTask) {
//                  if (!$obj->test($now)) continue; ALWAYS RECORD TASK
                    try {
                        $job = new RegularJob($obj);
                    } catch (Throwable $t) {
                        continue;
                    }
                } elseif ($obj instanceof Job) {
                    $job = $obj;
                } else {
                    continue;
                }
                if ($this->load_job($job) > 0) {
                    ++$res;
                }
            } catch (Throwable $t) {
                continue;
            }
        }

        // Get job objects from modules
        $cache = AppSingle::SysDataCache();
        if (!$cache->get('modules')) {
            $obj = new SysDataCacheDriver('modules',
                function() {
                    $db = AppSingle::Db();
                    $query = 'SELECT * FROM '.CMS_DB_PREFIX.'modules';
                    return $db->GetArray($query);
                });
            $cache->add_cachable($obj);
        }

        $modules = ModuleOperations::get_modules_with_capability('tasks');
        if (!$modules) {
            if (defined('ASYNCLOG')) {
                error_log('async action No task-capable modules present'."\n", 3, ASYNCLOG);
            }
            return $res;
        }
        foreach ($modules as $one) {
            if (!is_object($one)) {
                $one = Utils::get_module($one);
            }
            if (!method_exists($one, 'get_tasks')) {
                continue;
            }

            $tasks = $one->get_tasks();
            if (!$tasks) {
                continue;
            }
            if (!is_array($tasks)) {
                $tasks = [$tasks];
            }

            foreach ($tasks as $obj) {
                if (!is_object($obj)) {
                    continue;
                }
                if ($obj instanceof CmsRegularTask || $obj instanceof IRegularTask) {
//                    if (! $obj->test()) continue;  ALWAYS RECORD TASK
                    try {
                        $job = new RegularJob($obj);
                    } catch (Throwable $t) {
                        continue;
                    }
                } elseif ($obj instanceof Job) {
                    $job = $obj;
                } else {
                    continue;
                }
                $job->module = $one->GetName();
                if ($this->load_job($job) > 0) {
                    ++$res;
                }
            }
        }
        return $res;
    }

    /**
     * Update or initialize the recorded data for the supplied Job, and if
     * relevant, update the Job's id-property
     * @param Job $job
     * @return int id of updated|inserted Job | 0 upon error
     */
    public function load_job(Job $job) : int
    {
        $db = AppSingle::Db();
        if ($job->id == 0) {
            $sql = 'SELECT id,start FROM '.self::TABLE_NAME.' WHERE name = ? AND module = ?';
            $dbr = $db->GetRow($sql, [$job->name, $job->module]);
            if ($dbr) {
                if ($dbr['start'] > 0) {
                    $job->set_id((int)$dbr['id']);
                    $sql = 'UPDATE '.self::TABLE_NAME.' SET start = ? WHERE id = ?'; //update next-start
                    $db->Execute($sql, [$job->start, $job->id]);
                }
                return $job->id; // maybe still 0
            }
            $now = time();
            if ($this->job_recurs($job)) {
                $start = min($job->start, $now);
                $recurs = $job->frequency;
                $until = $job->until;
            } else {
                $start = 0; //$job->start;
                $recurs = RecurType::RECUR_NONE;
                $until = 0;
            }
            $sql = 'INSERT INTO '.self::TABLE_NAME.' (name,created,module,errors,start,recurs,until,data) VALUES (?,?,?,?,?,?,?,?)';
            $dbr = $db->Execute($sql, [$job->name, $job->created, $job->module, $job->errors, $start, $recurs, $until, serialize($job)]);
            if ($dbr) {
                $new_id = $db->Insert_ID();
                try {
                    $job->set_id($new_id);
                    return $new_id;
                } catch (LogicException $e) {
                    // nothing here
                }
            }
            return 0;
        } else {
            // note... we don't play with the module, the data, or recurs/until stuff for existing jobs.
            $sql = 'UPDATE '.self::TABLE_NAME.' SET start = ? WHERE id = ?';
            $dbr = $db->Execute($sql, [$job->start, $job->id]);
            return ($db->affected_rows() > 0) ? $job->id : 0;
        }
    }

    /**
     * @throws InvalidArgumentException if $job_id is invalid
     * @throws ? if Job-unserialize() fails
     */
    public function load_job_by_id($job_id)
    {
        $job_id = (int) $job_id;
        if ($job_id > 0) {
            $db = AppSingle::Db();
            $sql = 'SELECT * FROM '.self::TABLE_NAME.' WHERE id = ?';
            $row = $db->GetRow($sql, [$job_id]);
            if (!is_array($row) || !count($row)) {
                return;
            }

            $obj = unserialize($row['data']);
            $obj->set_id($row['id']);
            return $obj;
        }
        throw new InvalidArgumentException('Invalid job_id passed to '.__METHOD__);
    }

    /**
     *
     * @param mixed $module module object | string module name
     */
    public function load_jobs_by_module($module)
    {
        if (!is_object($module)) {
            $module = Utils::get_module($module);
        }

        if (!method_exists($module, 'get_tasks')) {
            return;
        }
        $tasks = $module->get_tasks();
        if (!$tasks) {
            return;
        }

        if (!is_array($tasks)) {
            $tasks = [$tasks];
        }

        foreach ($tasks as $obj) {
            if (!is_object($obj)) {
                continue;
            }
            if ($obj instanceof CmsRegularTask) {
                $job = new RegularTask($obj);
            } elseif ($obj instanceof Job) {
                $job = $obj;
            } else {
                continue;
            }
            $job->module = $module->GetName();
            $this->load_job($job);
        }
    }

    /**
     * Remove a specific job from the current-jobs table
     *
     * @param Job $job
     * @throws BadMethodCallException
     */
    public function unload_job(Job $job)
    {
        if ($job->id > 0) {
            $db = AppSingle::Db();
            $sql = 'DELETE FROM '.self::TABLE_NAME.' WHERE id = ?';
            if ($db->Execute($sql, [$job->id])) {
                return;
            }
        }
        throw new BadMethodCallException('Cannot delete a job that has no id');
    }

    /**
     * Remove an enumerated job from the current-jobs table
     *
     * @param mixed $job_id int | null
     * @return type
     * @throws InvalidArgumentException
     */
    public function unload_job_by_id($job_id)
    {
        $job_id = (int) $job_id;
        if ($job_id > 0) {
            $db = AppSingle::Db();
            $sql = 'DELETE FROM '.self::TABLE_NAME.' WHERE id = ?';
            if ($db->Execute($sql, [$job_id])) {
                return;
            }
        }
        throw new InvalidArgumentException('Invalid job_id passed to '.__METHOD__);
    }

    /**
     * Remove a named job of a named module from the current-jobs table
     *
     * @param string $module_name
     * @param string $job_name
     * @throws InvalidArgumentException
     */
    public function unload_job_by_name($module_name, $job_name)
    {
        if ($module_name) {
            $db = AppSingle::Db();
            $sql = 'DELETE FROM '.self::TABLE_NAME.' WHERE module = ? AND name = ?';
            if ($db->Execute($sql, [$module_name, $job_name])) {
                return;
            }
        }
        throw new InvalidArgumentException('Invalid identifier(s) passed to '.__METHOD__);
    }

    /**
     * Remove all jobs of a named module from the current-jobs table
     *
     * @param string $module_name
     * @throws InvalidArgumentException
     */
    public function unload_jobs_by_module($module_name)
    {
        if ($module_name) {
            $db = AppSingle::Db();
            $sql = 'DELETE FROM '.self::TABLE_NAME.' WHERE module = ?';
            $db->Execute($sql, [$module_name]); // don't care if this fails i.e. no jobs
            return;
        }
        throw new InvalidArgumentException('Invalid module name passed to '.__METHOD__);
    }

    /**
     * Retrieve the cached current job
     *
     * @return mixed Job object | null
     */
    public function get_current_job()
    {
        return $this->_current_job;
    }

    /**
     * Set or clear the cached current job
     *
     * @param mixed $job optional Job|null Default null
     * @throws InvalidArgumentException
     */
    public function set_current_job($job = null)
    {
        if (!(is_null($job) || $job instanceof Job)) {
            throw new InvalidArgumentException('Invalid data passed to '.__METHOD__);
        }
        $this->_current_job = $job;
    }

    /**
     * Retrieve (up to 50) jobs from the current-jobs table
     * @return array up to 50 members, mebbe empty
     */
    public function get_all_jobs() : array
    {
        $now = time();
        $sql = 'SELECT * FROM '.self::TABLE_NAME." WHERE created < $now ORDER BY created";
        $db = AppSingle::Db();
        $rs = $db->SelectLimit($sql, self::MAXJOBS);
        if (!$rs) {
            return [];
        }

        $out = [];
        while (!$rs->EOF()) {
            $row = $rs->fields();
            if (!empty($row['module'])) {
                $mod = Utils::get_module($row['module']);
                if (!is_object($mod)) {
                    debug_to_log(sprintf('Could not load module %s required by job %s', $row['module'], $row['name']));
                    audit('', 'TODO', sprintf('Could not load module %s required by job %s', $row['module'], $row['name']));
//                    throw new RuntimeException('Job '.$row['name'].' requires module '.$row['module'].' That could not be loaded');
                }
            }
            try {
                $obj = unserialize($row['data']/*, ['allowed_classes' => ['allowed_classes' => Job-descentants, interface*-implmentors]]*/);
            } catch (Throwable $t) {
                $obj = null;
            }
            if (is_object($obj)) {
                $obj->set_id($row['id']);
                $obj->force_start = $row['start']; // in case this job was modified
                $out[] = $obj;
            } else {
                debug_to_log(__METHOD__);
                debug_to_log('Problem deserializing row');
                debug_to_log($row);
            }
            if (!$rs->MoveNext()) {
                break;
            }
        }
        $rs->Close();
        return $out;
    }

    /**
     * Get pending jobs, up to 50 of them, or just one if checking
     *
     * @param bool $check_only Optional flag whether to merely check for existence
     *  of relevant job(s). Default false.
     * @return mixed array | bool
     */
    public function get_jobs(bool $check_only = false)
    {
        $db = AppSingle::Db();
        $now = time();

        if ($check_only) {
            $sql = 'SELECT id FROM '.self::TABLE_NAME." WHERE start > 0 AND start <= $now AND (until = 0 OR until >= $now)";
            $rs = $db->SelectLimit($sql, 1);
            if ($rs) {
                $res = !$rs->EOF();
                $rs->Close();
                return $res;
            }
            return false;
        }

        $sql = 'SELECT * FROM '.self::TABLE_NAME." WHERE start > 0 AND start <= $now AND (until = 0 OR until >= $now) ORDER BY errors,created";
        $rs = $db->SelectLimit($sql, self::MAXJOBS);

        if (!$rs) {
            return false;
        }

        $out = [];
        while (!$rs->EOF()) {
            $row = $rs->fields();
            if (!empty($row['module'])) {
                $mod = Utils::get_module($row['module']);
                if (!is_object($mod)) {
                    debug_to_log(sprintf('Could not load module %s required by job %s', $row['module'], $row['name']));
                    audit('', 'JobOperations', sprintf('Could not load module %s required by job %s', $row['module'], $row['name']));
                }
            }
            try {
                $obj = unserialize($row['data']/*, ['allowed_classes' => Job-descentants, interface*-implmentors]*/);
            } catch (Throwable $t) {
                $obj = null;
            }
            if (is_object($obj)) {
                $obj->set_id($row['id']);
                $obj->force_start = $row['start']; // in case this job was modified
                $out[] = $obj;
            } else {
                debug_to_log(__METHOD__);
                debug_to_log('Problem deserializing row');
                debug_to_log($row);
            }
            if (!$rs->MoveNext()) {
                break;
            }
        }
        $rs->Close();
        return $out;
    }

    /**
     * Report whether there is any pending job
     *
     * @return bool
     */
    public function have_jobs() : bool
    {
        return $this->get_jobs(true);
    }

    /**
     * At defined intervals, remove from the jobs table those which
     * have recorded more errors than the defined threshold
     */
    public function clear_bad_jobs()
    {
        $now = time();
        $lastrun = (int)AppParams::get('joblastbadrun');
        if ($lastrun + self::MINGAP >= $now) {
            return;
        }

        $db = AppSingle::Db();
        $sql = 'SELECT * FROM '.self::TABLE_NAME.' WHERE errors >= ?';
        $list = $db->GetArray($sql, [self::MINERRORS]);
        if ($list) {
            $idlist = [];
            foreach ($list as &$row) {
                try {
                    $obj = unserialize($row['data']/*, ['allowed_classes' => ['allowed_classes' => Job-descentants, interface*-implmentors]]*/);
                } catch (Throwable $t) {
                    $obj = null;
                }
                if (is_object($obj)) {
                    $obj->set_id($row['id']);
                    $idlist[] = (int) $row['id'];
                    Events::SendEvent('Core', self::EVT_FAILEDJOB, ['job' => &$obj]);
                } else {
                    debug_to_log(__METHOD__);
                    debug_to_log('Problem deserializing row');
                    debug_to_log($row);
                }
            }
            unset($row);
            $sql = 'DELETE FROM '.self::TABLE_NAME.' WHERE id IN ('.implode(',', $idlist).')';
            $db->Execute($sql);
            audit('', $mod->GetName(), 'Cleared '.count($idlist).' bad jobs');
        }
        AppParams::set('joblastbadrun', $now);
    }

    /**
     * Set (locally and in parameters-table) the timestamp for a lock
     */
    public function lock()
    {
        $this->_lock = time();
        AppParams::set(self::LOCKPREF, $this->_lock);
    }

    /**
     * Clear (locally and in parameters-table) the current lock-timestamp
     */
    public function unlock()
    {
        $this->_lock = 0;
        AppParams::remove(self::LOCKPREF);
    }

    /**
     * Report whether a lock is current
     */
    public function is_locked() : bool
    {
        $this->_lock = (int)AppParams::get(self::LOCKPREF);
        return ($this->_lock > 0);
    }

    public function lock_expired()
    {
        $this->_lock = (int)AppParams::get(self::LOCKPREF);
        return ($this->_lock && $this->_lock < time() - $this->get_async_freq());
    }

    /**
     * Initiate job-processing, after checking whether it's appropriate to do so
     */
    public function begin_async_processing()
    {
        static $_returnid = -1;

//        error_log('trigger_async_processing @start'."\n", 3, $this->ASYNCLOG);
        // if we're processing a prior job-manager request - do nothing
        if (isset($_GET[CMS_SECURE_PARAM_NAME.'job'])) {
            error_log('JobOperations abort: re-entry'."\n", 3, $this->ASYNCLOG);
            return;
        }

        // ensure this method only operates once-per-request
        if ($_returnid !== -1) {
            error_log('JobOperations abort: no repeat during request'."\n", 3, $this->ASYNCLOG);
            return;
        }

        $t = AppParams::get('joblastrun', 0) + AppParams::get('jobinterval', 180);
        if ($t > time()) {
            error_log('JobOperations: too soon to run jobs'."\n", 3, $this->ASYNCLOG);
            return;
        }

/* DEBUG sync operation
        $_GET['_jo_'] = 'ERFGerftg4'; //any rubbish 
        $_GET[CMS_SECURE_PARAM_NAME.'job'] = '';
        $_GET[CMS_JOB_KEY] = 2;
        include_once CMS_ADMIN_PATH.DIRECTORY_SEPARATOR.'processjobs.php';
        return;
*/
//        $joburl = AppParams::get('joburl');
//        if (!$joburl) {
            $joburl = cms_path_to_url(CMS_ADMIN_PATH).'/processjobs.php?'.RequestParameters::create_job_params([CMS_SECURE_PARAM_NAME.'job'=>''], true);
//        }
//        error_log('JobOperations: processor URL '.$joburl."\n", 3, $this->ASYNCLOG);

/* DEBUG sync operation
        error_log('begin_async_processing redirect to '.$joburl."\n", 3, $this->ASYNCLOG);
        redirect($joburl);
*/
        [$host, $path, $transport, $port] = $this->get_url_params($joburl);
        error_log('begin_async_processing path '.$path."\n", 3, $this->ASYNCLOG);

        $remote = $transport.'://'.$host.':'.$port;
        if ($transport == 'tcp') {
            $context = stream_context_create();
        } else {
            //internal-use only, skip verification
            $opts = [
            'ssl' => [
//              'allow_self_signed' => true,
                'verify_host' => false,
                'verify_peer' => false,
             ],
            'tls' => [
//              'allow_self_signed' => true,
                'verify_host' => false,
                'verify_peer' => false,
             ]
            ];
            $context = stream_context_create($opts); //, $params);
        }

        $res = stream_socket_client($remote, $errno, $errstr, 1, STREAM_CLIENT_ASYNC_CONNECT, $context);
        if ($res) {
//            error_log('JobOperations: open stream '.$remote."\n", 3, $this->ASYNCLOG);
            $req = "GET $path HTTP/1.1\r\nHost: {$host}\r\nContent-type: text/plain\r\nContent-length: 0\r\nConnection: Close\r\n\r\n";
            fputs($res, $req);
//            error_log('stream-socket sent: '.$req."\n", 3, $this->ASYNCLOG);
            stream_socket_shutdown($res, STREAM_SHUT_RDWR);
            if ($errno == 0) {
                return;
            } else {
                error_log('stream-socket client failure: '.$remote."\n", 3, $this->ASYNCLOG);
            }
        }

        if ($errno > 0) {
            error_log('stream-socket error: '.$errstr."\n", 3, $this->ASYNCLOG);
            debug_to_log($this->GetName().': stream-socket error: '.$errstr);
        } else {
            error_log('stream-socket error: connection failure'."\n", 3, $this->ASYNCLOG);
            debug_to_log($this->GetName().': stream-socket error: connection failure');
        }
    }

    private function get_url_params(string $url) : array
    {
        $urlparts = parse_url($url);
        if (!$urlparts || empty($urlparts['host'])) {
            return [null, null, null, null];
        }
        $host = $urlparts['host'];
        $path = $urlparts['path'] ?? '';
        if (!empty($urlparts['query'])) {
            $path .= '?'.$urlparts['query'];
        }
        $scheme = $urlparts['scheme'] ?? 'http';
        $secure = (strcasecmp($scheme, 'https') == 0);
        if ($secure) {
            $opts = stream_get_transports();
            if (in_array('tls', $opts)) {
                $transport = 'tls';
            } elseif (in_array('ssl', $opts)) { //deprecated PHP7
                $transport = 'ssl';
            } else {
                $transport = 'tcp';
                $secure = false;
            }
        } else {
            $transport = 'tcp';
        }
        $port = $urlparts['port'] ?? (($secure) ? 443 : 80);
        return [$host, $path, $transport, $port];
    }

    /**
     * Job-related events handler
     * @param string $eventname event name
     * @param array $params optional event parameters
     */
    public static function event_handler(string $eventname, array $params = [])
    {
        $obj = new self();
        switch ($eventname) {
            case 'ModuleUninstalled':
                $module_name = trim($params['name']);
                if ($module_name) {
                    $obj->delete_jobs_by_module($module_name);
                    break;
                }
            // no break here
            case 'ModuleInstalled':
            case 'ModuleUpgraded':
                $obj->refresh_jobs(true);
        }
    }

    /**
     * Event handler to initiate job-processing
     */
    public static function begin_async_work()
    {
        (new self())->begin_async_processing();
    }

    /**
     * Shutdown function
     */
    public static function errorhandler()
    {
        $err = error_get_last();
        if (is_null($err)) {
            return;
        }
        if ($err['type'] != E_ERROR) {
            return;
        }
        $obj = new self();
        $job = $obj->get_current_job();
        if ($job) {
            $obj->joberrorhandler($job, $err['message'], $err['file'], $err['line']);
        }
    }
}