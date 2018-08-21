<?php
# Class of utilities for interacting with locks
# Copyright (C) 2014-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
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

//namespace CMSMS;

/**
 * A class of utilities for interacting with locks.
 *
 * @package CMS
 * @since 2.0
 */
final class CmsLockOperations
{
	/**
	 * @ignore
	 */
	private function __construct($type,$id) {}

	/**
	 * Touch any lock of the specified type, and id that matches the currently logged in UID
	 *
	 * @param int $lock_id The lock identifier
	 * @param string $type The type of object being locked
	 * @param int $oid The object identifier
	 * @return int The expiry timestamp of the lock.
	 */
	public static function touch($lock_id,$type,$oid)
	{
		$uid = get_userid(FALSE);
		$lock = CmsLock::load_by_id($lock_id,$type,$oid,$uid);
		$lock->save();
		return $lock['expires'];
	}

	/**
	 * Delete any lock of the specified type, and id that matches the currently logged in UID
	 *
	 * @param int $lock_id The lock identifier
	 * @param string $type The type of object being locked
	 * @param int $oid The object identifier
	 */
	public static function delete($lock_id,$type,$oid)
	{
		self::unlock($lock_id,$type,$oid);
	}

	/**
	 * Delete any lock of the specified type, and id that matches the currently logged in UID
	 *
	 * @param int $lock_id The lock identifier
	 * @param string $type The type of object being locked
	 * @param int $oid The object identifier
	 */
	public static function unlock($lock_id,$type,$oid)
	{
		if( $lock_id ) {
			$uid = get_userid(FALSE);
			$lock = CmsLock::load_by_id($lock_id,$type,$oid);
			$lock->delete();
		}
	}

	/**
	 * test for any lock of the specified type, and id
	 *
	 * @param string $type The type of object being locked
	 * @param int $oid The object identifier
	 * @return bool
	 */
	public static function is_locked($type,$oid)
	{
		try {
			$lock = CmsLock::load($type,$oid);
			sleep(1); // wait for potential asynhronous requests to complete.
			$lock = CmsLock::load($type,$oid);
			return $lock['id'];
		}
		catch( CmsNoLockException $e ) {
			return FALSE;
		}
	}

	/**
	 * Delete any locks that have expired.
	 *
	 * @param int $expires Delete locks older than this date (if not specified current time will be used).
	 * @param string $type The type of locks to delete.  If not specified any locks can be deleted.
	 */
	private static function delete_expired($expires = null,$type = null)
	{
		if( $expires == null ) $expires == time();
		$db = CmsApp::get_instance()->GetDb();
		$query = 'DELETE FROM '.CMS_DB_PREFIX.CmsLock::LOCK_TABLE.' WHERE expires < ?';
		$parms = [$expires];
		if( $type ) {
			$query .= ' AND type = ?';
			$parms[] = $type;
		}
		$dbr = $db->Execute($query,$parms);
	}

	/**
	 * Get all locks of a specific type
	 *
	 * @param string $type The lock type
	 */
	public static function get_locks($type)
	{
		$db = CmsApp::get_instance()->GetDb();
		$query = 'SELECT * FROM '.CMS_DB_PREFIX.CmsLock::LOCK_TABLE.' WHERE type = ?';
		$tmp = $db->GetArray($query,[$type]);
		if( !is_array($tmp) || count($tmp) == 0 ) return;

		$locks = [];
		foreach( $tmp as $row ) {
			$obj = CmsLock::from_row($row);
			$locks[] = $obj;
		}
		return $locks;
	}

	/**
	 * Delete all the locks for the current user
	 *
	 * @param string $type An optional type name.
	 */
	public static function delete_for_user($type = null)
	{
		$uid = get_userid(FALSE);
		$db = CmsApp::get_instance()->GetDb();
		$parms = [$uid];
		$query = 'DELETE FROM '.CMS_DB_PREFIX.CmsLock::LOCK_TABLE.' WHERE uid = ?';
		if( $type ) {
			$query .= ' AND type = ?';
			$parms[] = trim($type);
		}
		$db->Execute($query,$parms);
	}

} // class
