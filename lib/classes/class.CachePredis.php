<?php
# A class to work with data cached using the PHP Predis (aka phpredis) extension
# https://github.com/phpredis/phpredis
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
use Redis;
use function startswith;

/**
 * A driver to cache data using the PHP Predis (aka phpredis) extension
 *
 * Supports settable cache lifetime, automatic cleaning.
 *
 * @package CMS
 * @license GPL
 * @since 2.3
 */
class CachePredis extends CacheDriver
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
     *  host => string
     *  port  => int
     *  read_write_timeout => float
     *  password => string
     *  database => int
     */
    public function __construct($opts)
    {
        if ($this->use_driver()) {
            if ($this->connectServer($opts)) {
                if (is_array($opts)) {
                    $_keys = ['lifetime', 'group'];
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
        throw new Exception('no Predis storage');
    }

    /**
     * @ignore
     */
    private function use_driver()
    {
        return class_exists('Redis');
    }

    /**
     * @ignore
     * $opts[] may include
     *  'host' => string
     *  'port'  => int
     *  'password' => string
     *  'database' => int
     */
    private function connectServer($opts)
    {
        $params = array_merge([
         'host' => '127.0.0.1',
         'port' => 6379,
         'read_write_timeout' => 10.0,
         'password' => '',
         'database' => 0,
        ], $opts);

        $this->instance = new Redis();
        try {
            //trap any connection-failure warning
            $res = @$this->instance->connect($params['host'], (int)$params['port'], (float)$params['read_write_timeout']);
        } catch (Exception $e) {
            unset($this->instance);
            return false;
        }
        if (!$res) {
            unset($this->instance);
            return false;
        } elseif ($params['password'] && !$this->instance->auth($params['password'])) {
            $this->instance->close();
            unset($this->instance);
            return false;
        }
        if ($params['auto_cleaning']) {
//TODO
            if ($params['lifetime']) {
            }
        }
        if ($params['database']) {
            return $this->instance->select((int)$params['database']);
        }
        return true;
    }

    public function get($key, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        return $this->_read_cache($key);
    }

    public function exists($key, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        return $this->instance->exists($key) > 0;
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
    private function _read_cache(string $key)
    {
        $data = $this->instance->get($key);
        if ($data !== false) {
            if (startswith($data, parent::SERIALIZED)) {
                $data = unserialize(substr($data, strlen(parent::SERIALIZED)));
            } elseif (is_numeric($data)) {
                return $data + 0;
            }
            return $data;
        }
        return null;
    }

    /**
     * @ignore
     */
    private function _write_cache(string $key, $data) : bool
    {
        if (is_scalar($data)) {
            $data = (string)$data;
        } else {
            $data = parent::SERIALIZED.serialize($data);
        }
        $ttl = ($this->_auto_cleaning) ? 0 : $this->_lifetime;
        if ($ttl > 0) {
            return $this->instance->setEx($key, $ttl, $data);
        } else {
            return $this->instance->set($key, $data);
        }
    }

    /**
     * @ignore
     */
    private function _clean(string $group) : int
    {
        if (!$group) return 0; //no global interrogation in shared key-space with aged data
//        $prefix = ($group) ? $this->get_cacheprefix(__CLASS__, $group) : parent::MYSPACE;
        $prefix = $this->get_cacheprefix(__CLASS__, $group);

        $nremoved = 0;
        $keys = $this->instance->keys($prefix.'*');
        foreach ($keys as $key) {
            if ($this->instance->delete($key)) {
                ++$nremoved;
            }
        }
        return $nremoved;
    }
} // class
