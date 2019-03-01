<?php
# A class to work with data cached using the PHP YAC extension.
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
use Yac;

/**
 * A driver to cache data using PHP's YAC extension
 *
 * Supports settable cache lifetime, automatic cleaning.
 *
 * @package CMS
 * @license GPL
 * @since 2.3
 */
class CacheYac extends CacheDriver
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
     */
    public function __construct($opts)
    {
        if ($this->use_driver()) {
            if ($this->connectServer()) {
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
        throw new Exception('no YAC storage');
    }

    /**
     * @ignore
     */
    private function use_driver()
    {
        return extension_loaded('yac') && ini_get('yac.enable');
    }

    /**
     * @ignore
     */
    private function connectServer()
    {
        $this->instance = new Yac();
        return $this->instance != null;
    }

    public function get($key, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        $res = $this->instance->get($key);
        return ($res || !is_bool($res)) ? $res : null;
    }

    public function exists($key, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        $res = $this->instance->get($key);
        return $res || !is_bool($res);
    }

    public function set($key, $value, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        if ($value === false) $value = 0; //ensure actual false isn't ignored
        return $this->_write_cache($key, $value);
    }

    public function erase($key, $group = '')
    {
        if (!$group) $group = $this->_group;

        $key = $this->get_cachekey($key, __CLASS__, $group);
        return  $this->instance->delete($key);
    }

    public function clear($group = '')
    {
        return $this->_clean($group, false);
    }

    /**
     * @ignore
     */
    private function _write_cache(string $key, $data) : bool
    {
        $ttl = ($this->_auto_cleaning) ? 0 : $this->_lifetime;
        if ($ttl > 0) {
            return $this->instance->set($key, $data, $ttl);
        } else {
            return $this->instance->set($key, $data);
        }
    }

    /**
     * @ignore
     */
    private function _clean(string $group) : int
    {
        $prefix = $this->get_cacheprefix(__CLASS__, $group);
        if ($prefix === '') return 0; //no global interrogation in shared key-space

        $nremoved = 0;
        $info = $this->instance->info();
        $c = (int)$info['slots_used'];
        if ($c) {
            $info = $this->instance->dump($c);
            if ($info) {
                $len = strlen($prefix);

                foreach ($info as $item) {
                    $key = $item['key'];
                    if (strncmp($key, $prefix, $len) == 0) {
                        if ($this->instance->delete($key)) {
                            ++$nremoved;
                        }
                    }
                }
            }
        }
        return $nremoved;
    }
} // class
