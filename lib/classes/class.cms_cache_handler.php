<?php
#generic cache handler wrapper class
#Copyright (C) 2010-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
#Thanks to Robert Campbell and all other contributors from the CMSMS Development Team.
#This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
#This program is free software; you can redistribute it and/or modify
#it under the terms of the GNU General Public License as published by
#the Free Software Foundation; either version 2 of the License, or
#(at your option) any later version.
#
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#You should have received a copy of the GNU General Public License
#along with this program. If not, see <https://www.gnu.org/licenses/>.

//TODO admin developer-mode UI for related site-preferences
//namespace CMSMS;

use CMSMS\CacheDriver;

/**
 * Wrapper class providing caching capability.
 * NOTE After instantiation and before use, connect() or set_driver() must be called
 *
 * Site-preferences which affect cache operation (unless overridden by supplied params):
 * 'cache_driver' string 'predis','memcached','apcu','yac','file' or 'auto'
 * 'cache_autocleaning' bool
 * 'cache_lifetime' int seconds (maybe 0)
 * 'cache_file_blocking' bool
 * 'cache_file_locking' bool
 *
 * @final
 * @package CMS
 * @license GPL
*/

//class CacheHandler
final class cms_cache_handler
{
  /**
   * @ignore
   * Populated for the global cache
   */
  private static $_instance = null;

  /**
   * @ignore
   */
  private $_driver = null;

  /* *
   * @ignore
   */
//  private function __construct() {}

  /* *
   * @ignore
   */
//  private function __clone() {}

  /**
   * Return the 'global' instance of this class.
   *
   * @throws CmsException
   * @return static cms_cache_handler object | not at all
   */
  public static function get_instance() : self
  {
    if( !is_object(self::$_instance) ) {
        $ob = new self();
        $ob->connect();
        self::$_instance = $ob; //no we're connected (unless excepted already)
    }
    return self::$_instance;
  }

  /**
   * Connect to and record a driver class
   *
   * @since 2.3
   * @param $opts Optional connection-parameters. Default []
   * @throws CmsException
   * @return CacheDriver object | not at all
   */
  public function connect(array $opts = []) : CacheDriver
  {
    global $CMS_INSTALL_PAGE;

    if ( $this->_driver instanceof CMSMS\CacheDriver ) {
      return $this->_driver; //just one connection per instance
    }

    // ordered by preference during an auto-selection
    $drivers = [
     'predis' => 'CMSMS\\CachePredis',
     'apcu' => 'CMSMS\\CacheApcu',
     'memcached' => 'CMSMS\\CacheMemcached', //slowest !?
     'yac' => 'CMSMS\\CacheYac',  // bit intersection risky
     'file' => 'CMSMS\\CacheFile',
    ];

    $parms = $opts;
    // preferences cache maybe N/A now, so get pref data directly
    $driver_name = $opts['driver'] ?? cms_siteprefs::getraw('cache_driver', 'auto');
    unset($parms['driver']);
    $ttl = $opts['lifetime'] ?? cms_siteprefs::getraw('cache_lifetime', 3600);
    $ttl = (int)$ttl;
    if( $ttl < 1 ) $ttl = 0;
    if( $ttl < 1 ) {
      $parms['lifetime'] = null; // unlimited
      $parms['auto_cleaning'] = false;
    }
    else {
      $parms['lifetime'] = $ttl;
      if( !isset($parms['auto_cleaning']) ) {
        $parms['auto_cleaning'] = cms_siteprefs::getraw('cache_autocleaning', true);
      }
    }

    if( !empty($CMS_INSTALL_PAGE)) {
      $parms['lifetime'] = 120;
      $parms['auto_cleaning'] = true;
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $tmp = cms_siteprefs::getraw(['cache_file_blocking', 'cache_file_locking'],[0, 1]);
    $settings = [
       'predis' => [
       'host' => $host,
       'port' => 6379,
       'read_write_timeout' => 10.0, // connection timeout, not data lifetime
       'password' => '',
       'database' => 0,
       ],
       'memcached' => [
       'host' => $host,
       'port' => 11211,
       ],
       'file' => [
       'blocking' => reset($tmp),
       'locking' => end($tmp),
       ],
    ];

    $type = strtolower($driver_name);
    switch( $type ) {
      case 'predis':
      case 'apcu':
      case 'memcached':
      case 'yac':
      case 'file':
        $classname = $drivers[$type];
        if( isset($settings[$type]) ) $parms += $settings[$type];
        try {
          $this->_driver = new $classname($parms);
          debug_buffer('Cache initiated, using driver: ' . $type);
          return $this->_driver;
        } catch (Exception $e) {}
        break;
      case 'auto':
//        debug_buffer('Start cache-driver polling');
        foreach( $drivers as $type => $classname ) {
        $tmp = $parms;
        if( isset($settings[$type]) ) $tmp += $settings[$type];
        try {
          $this->_driver = new $classname($tmp);
          debug_buffer('Cache initiated, using driver: ' . $type);
          return $this->_driver;
        }
        catch (Exception $e) {}
        }
        break;
      default:
        break;
    }
    $this->_driver = null;
    throw new CmsException('Cache ('.$driver_name.') setup failed');
  }

  /**
   * Set the driver to use for caching.
   * This allows use of a custom driver other than supported by connect()
   *
   * @see cms_cache_handler::connect()
   * @param CacheDriver $driver
   */
  public function set_driver(CacheDriver $driver)
  {
    $this->_driver = $driver;
  }

  /**
   * Get the driver used for caching
   * This may be used e.g. to adjust the driver parameters
   *
   * @return mixed CacheDriver object | null
   */
  public function get_driver()
  {
    return $this->_driver;
  }

  /**
   * Clear the cache
   * If the group is not specified, the current set group will be used.
   * If the group is empty, all cached values will be cleared.
   * Use with caution, especially in shared public caches like Memcached.
   *
   * @see cms_cache_handler::set_group()
   * @param string $group
   * @return bool
   */
  public function clear(string $group = '') : bool
  {
//    if( $this->can_cache() ) {
    if( $this->_driver instanceof CMSMS\CacheDriver) {
//    if( is_object($this->_driver) ) {
      return $this->_driver->clear($group);
    }
    return FALSE;
  }

  /**
   * Get a cached value
   *
   * @see cms_cache_handler::set_group()
   * @param string $key The primary key for the cached value
   * @param string $group An optional cache group name.
   * @return mixed null if there is no cache-data for $key
   */
  public function get(string $key, string $group = '')
  {
//    if( $this->can_cache() ) {
    if( $this->_driver instanceof CMSMS\CacheDriver) {
//    if( is_object($this->_driver) ) {
      return $this->_driver->get($key, $group);
    }
    return NULL;
  }

  /**
   * Test if a cached value exist
   *
   * @see cms_cache_handler::set_group()
   * @param string $key The primary key for the cached value
   * @param string $group An optional cache group name.
   * @return bool
   */
  public function exists(string $key, string $group = '') : bool
  {
//    if( $this->can_cache() ) {
    if( $this->_driver instanceof CMSMS\CacheDriver) {
//    if( is_object($this->_driver) ) {
      return $this->_driver->exists($key, $group);
    }
    return FALSE;
  }

  /**
   * Erase a cached value
   *
   * @see cms_cache_handler::set_group()
   * @param string $key The primary key for the cached value
   * @param string $group An optional cache group name.
   * @return bool
   */
  public function erase(string $key, string $group = '') : bool
  {
//    if( $this->can_cache() ) {
    if( $this->_driver instanceof CMSMS\CacheDriver) {
//    if( is_object($this->_driver) ) {
      return $this->_driver->erase($key, $group);
    }
    return FALSE;
  }

  /**
   * Add or replace a value in the cache
   * NOTE that to ensure detectability, a $value === null will be
   * converted to 0, and in some cache-drivers, a $value === false
   * will be converted to 0
   *
   * @see cms_cache_handler::set_group()
   * @param string $key The primary key for the cached value
   * @param mixed  $value the value to save
   * @param string $group An optional cache group name.
   * @return bool
   */
  public function set(string $key, $value, string $group = '') : bool
  {
//    if( $this->can_cache() ) {
    if( $this->_driver instanceof CMSMS\CacheDriver) {
//    if( is_object($this->_driver) ) {
      if ($value === null ) { $value = 0; }
      return $this->_driver->set($key, $value, $group);
    }
    return FALSE;
  }

  /**
   * Set the cache group
   * Specifies the scope for all methods in this cache
   *
   * @param string $group
   * @return bool
   */
  public function set_group(string $group) : bool
  {
    if( $this->_driver instanceof CMSMS\CacheDriver) {
//    if( is_object($this->_driver) ) {
      return $this->_driver->set_group($group);
    }
    return FALSE;
  }

  /* *
   * Test if caching is possible
   * Caching is not possible if there is no driver(, CHECKME or during an install-request?).
   *
   * @return bool
   */
/*  public function can_cache() : bool
  {
/ *    global $CMS_INSTALL_PAGE;

    if( !is_object($this->_driver) ) return FALSE;
    return empty($CMS_INSTALL_PAGE);
* /
    return $this->_driver instanceof CMSMS\CacheDriver;
  }
*/
}

//\class_alias(CacheHandler::class, 'cms_cache_handler', false);
