<?php
# Base class for data-cache drivers.
# Copyright (C) 2013-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

namespace CMSMS;

/**
 * Base class for data-cache drivers
 *
 * @since 2.0
 * @author Robert Campbell
 * @package CMS
 * @license GPL
 */
abstract class CacheDriver
{
    /**
     * @ignore
     */
    const SERIALIZED = '_|SE6ED|_';

    /**
     * @ignore
     */
    protected $_auto_cleaning = false;

    /**
     * @ignore
     * NULL for unlimited
     */
    protected $_lifetime = 3600; //1 hour

    /**
     * @ignore
     */
    protected $_group = 'default';

    /**
     * Get a cached value
     * If the $group parameter is not specified the current group will be used
     * @see CacheDriver::set_group()
     *
     * @param string $key
     * @param string $group Optional name, default ''
     */
    abstract public function get($key, $group = '');

    /**
     * Test if a cached value exists.
     * If the $group parameter is not specified the current group will be used
     * @see CacheDriver::set_group()
     *
     * @param string $key
     * @param string $group Optional name, default ''
     * @return bool
     */
    abstract public function exists($key, $group = '');

    /**
     * Set a cached value
     * If the $group parameter is not specified the current group will be used
     * @see CacheDriver::set_group()
     *
     * @param string $key
     * @param mixed $value
     * @param string $group Optional name, default ''
     */
    abstract public function set($key, $value, $group = '');

    /**
     * Erase a cached value
     * If the $group parameter is not specified the current group will be used
     *
     * @see CacheDriver::set_group()
     * @param string $key
     * @param string $group Optional name, default ''
     */
    abstract public function erase($key, $group = '');

    /**
     * Erase all cached values in a group
     * If the $group parameter is not specified the current group will be used
     * @see CacheDriver::set_group()
     *
     * @param string $group Optional name, default ''
     */
    abstract public function clear($group = '');

    /**
     * Set the current group
     *
     * @param string $group Ignored if empty
     */
    public function set_group($group)
    {
        if( $group ) $this->_group = trim($group);
    }

    /**
     * Hash $key using the (shortish, fastish, low collision) djb2a algorithm
     * @param string $key
     * @return string (13 alphanum bytes)
     */
    private function hash(string $key) : string
    {
        // actual byte-length (i.e. no mb interference);
        $key = array_values(unpack('C*',(string) $key));
        $klen = count($key);
        $h1 = 5381;
        for ($i = 0; $i < $klen; ++$i) {
            $h1 = ($h1 + ($h1 << 5)) ^ (int)$key[$i]; //aka $h1 = $h1*33 ^ $key[$i]
        }
        return base_convert((string)$h1, 10, 30);
    }

    /**
     * Construct a cache-key with identifiable group-prefix
     * @param string $key cache-item key
     * @param string $class initiator class
     * @param string $group cache-group key
     * @return string
     */
    protected function get_cachekey(string $key, string $class, string $group) : string
    {
        return $this->hash(__DIR__.$class.$group).':'.$this->hash($key.$class.__DIR__);
    }

    /**
     * Construct a cache-key group-prefix
     * @param string $class initiator class
     * @param string $group cache-group key
     * @return string
     */
    protected function get_cacheprefix(string $class, string $group) : string
    {
        return $this->hash(__DIR__.$class.$group).':';
    }
} // class
