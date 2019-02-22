<?php
# A class to work with data cached using the PHP Memcached extension.
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

namespace CMSMS;

use Exception;
use Memcached;
use function startswith;

/**
 * A driver to cache data using PHP's Memcached extension
 *
 * Supports settable cache lifetime, automatic cleaning.
 *
 * @package CMS
 * @license GPL
 * @since 2.3
 */
class CacheMemcached extends CacheDriver
{
    /**
     * @ignore
     */
    private $instance;

    /**
     * Constructor
     *
     * @param array $opts
     * Associative array of some/all options as follows:
     *  lifetime  => seconds (default 3600, min 600)
     *  group => string (default 'default')
     *  myspace => string cache differentiator (default cms_)
     *  host => string
     *  port => int
     */
    public function __construct($opts)
    {
        if ($this->use_driver()) {
            if ($this->connectServer($opts)) {
                if (is_array($opts)) {
                    $_keys = ['lifetime', 'group', 'myspace'];
                    foreach ($opts as $key => $value) {
                        if (in_array($key,$_keys)) {
                            $tmp = '_'.$key;
                            $this->$tmp = $value;
                        }
                    }
                }
                $this->_lifetime = max($this->_lifetime, 600);
                return;
            }
        }
        throw new Exception('no Memcached storage');
    }

    /**
     * @ignore
     */
    private function use_driver()
    {
        return class_exists('Memcached');
    }

    /**
     * @ignore
     */
    private function connectServer($opts)
    {
        $params = array_merge([
         'host' => '127.0.0.1',
         'port' => 11211,
        ], $opts);
        $host = $params['host'];
        $port = (int)$params['port'];

        $this->instance = new Memcached();

        $servers = $this->instance->getServerList();
        if (is_array($servers)) {
            foreach ($servers as $server) {
                if ($server['host'] == $host && $server['port'] == $port) {
                    return true;
                }
            }
        }

        try {
            if ($this->instance->addServer($host, $port)) //may throw Exception
            { return true; }
        } catch (Exception $e) {}
        unset($this->instance);
        return false;
    }

    public function get($key, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        $res = $this->instance->get($key);
        if (!$res && ($dbg = $this->instance->getResultCode()) != Memcached::RES_SUCCESS) {
            return null;
        }
        return $res;
    }

    public function exists($key, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        return ($this->instance->get($key) != false ||
                $this->instance->getResultCode() == Memcached::RES_SUCCESS);
    }

    public function set($key, $value, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        return $this->_write_cache($key, $value);
    }

    public function erase($key, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        return $this->instance->delete($key);
    }

    public function clear($group = '')
    {
        return $this->_clean($group);
    }

    /**
     * @ignore
     */
    private function _write_cache(string $key, $data) : bool
    {
        $ttl = ($this->_auto_cleaning) ? 0 : $this->_lifetime;
        if ($ttl > 0) {
            $expire = time() + $ttl;
            return $this->instance->set($key, $data, $expire);
        } else {
            return $this->instance->set($key, $data);
        }
    }

    /**
     * @ignore
     */
    private function _clean(string $group, bool $aged = true) : int
    {
        if (!$group) return 0; //no global interrogation in shared key-space with aged data

        $nremoved = 0;
        $info = $this->instance->getAllKeys(); //NOT RELIABLE
        if ($info) {
//          $prefix = ($group) ? $this->get_cacheprefix(__CLASS__, $group) : parent::MYSPACE;
            $prefix = $this->get_cacheprefix(__CLASS__, $group);
            $len = strlen($prefix);
            if ($aged) {
                $ttl = ($this->_auto_cleaning) ? 0 : $this->_lifetime;
                $limit = time() - $ttl;
            }

            foreach ($info as $key) {
                if (strncmp($key, $prefix, $len) == 0) {
                    if ($aged) {
                        //TODO ageing is bad
                        if (1 && $this->instance->delete($key)) {
                            ++$nremoved;
                        }
                    } elseif ($this->instance->delete($key)) {
                        ++$nremoved;
                    }
                }
            }
        }
        return $nremoved;
    }
} // class
