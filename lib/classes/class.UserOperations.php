<?php
#Singleton class of user-related functions
#Copyright (C) 2004-2020 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
#Thanks to Ted Kulp and all other contributors from the CMSMS Development Team.
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

namespace CMSMS;

use cms_siteprefs;
use CmsApp;
use CmsException;
use CMSMS\DeprecationNotice;
use CMSMS\GroupOperations;
use CMSMS\User;
use CMSMS\UserOperations;
use const CMS_DB_PREFIX;
use const CMS_DEPREC;
use function get_userid;

/**
 * Singleton class for doing user-related functions.
 * Many User-class methods are just wrappers around these.
 *
 * @final
 * @package CMS
 * @license GPL
 *
 * @since 0.6.1
 */
final class UserOperations
{
	//TODO namespaced global variables here
	/* *
	 * @ignore
	 */
//	private static $_instance = null;

	/**
	 * @ignore
	 */
	private $_user_groups;

	/**
	 * @ignore
	 */
	private $_users;

	/**
	 * @ignore
	 */
	private $_saved_users = [];

	/* *
	 * @ignore
	 */
//	private function __construct() {}

	/**
     * @ignore
     */
    private function __clone() {}

	/**
	 * Get the singleton instance of this class.
	 * @deprecated since 2.9 use CMSMS\AppSingle::UserOperations()
	 * @return UserOperations
	 */
	public static function get_instance() : self
	{
        assert(empty(CMS_DEPREC), new DeprecationNotice('method','CMSMS\AppSingle::UserOperations()'));
		return AppSingle::UserOperations();
	}

	/**
	 * Gets a list of all users.
	 *
	 * @param int $limit  The maximum number of users to return
	 * @param int $offset Optional offset. Default 0
	 * @returns array An array of User objects
	 *
	 * @since 0.6.1
	 */
	public function LoadUsers(int $limit = 10000, int $offset = 0) : array
	{
		if (!is_array($this->_users)) {
			$db = CmsApp::get_instance()->GetDb();
			$result = [];

			$query = 'SELECT user_id, username, password, first_name, last_name, email, active, admin_access
					  FROM '.CMS_DB_PREFIX.'users ORDER BY username';
			$dbresult = $db->SelectLimit($query, $limit, $offset);

			while ($dbresult && !$dbresult->EOF) {
				$row = $dbresult->fields;
				$oneuser = new User();
				$oneuser->id = $row['user_id'];
				$oneuser->username = $row['username'];
				$oneuser->firstname = $row['first_name'];
				$oneuser->lastname = $row['last_name'];
				$oneuser->email = $row['email'];
				$oneuser->password = $row['password'];
				$oneuser->active = $row['active'];
				$oneuser->adminaccess = $row['admin_access'];
				$result[] = $oneuser;
				$dbresult->MoveNext();
			}

			$this->_users = $result;
		}

		return $this->_users;
	}

	/**
	 * Gets a list of all users in a given group.
	 *
	 * @param int $groupid Group for the loaded users
	 *
	 * @return array An array of User objects
	 */
	public function LoadUsersInGroup(int $groupid) : array
	{
		$db = CmsApp::get_instance()->GetDb();
		$result = [];

		$query = 'SELECT u.user_id, u.username, u.password, u.first_name, u.last_name, u.email, u.active, u.admin_access FROM '.CMS_DB_PREFIX.'users u, '.CMS_DB_PREFIX.'groups g, '.CMS_DB_PREFIX.'user_groups cg WHERE cg.user_id = u.user_id AND cg.group_id = g.group_id AND g.group_id = ? ORDER BY username';
		$dbresult = $db->Execute($query, [$groupid]);

		while ($dbresult && $row = $dbresult->FetchRow()) {
			$oneuser = new User();
			$oneuser->id = $row['user_id'];
			$oneuser->username = $row['username'];
			$oneuser->firstname = $row['first_name'];
			$oneuser->lastname = $row['last_name'];
			$oneuser->email = $row['email'];
			$oneuser->password = $row['password'];
			$oneuser->active = $row['active'];
			$oneuser->adminaccess = $row['admin_access'];
			$result[] = $oneuser;
		}

		return $result;
	}

	/**
	 * Loads a user by login/account/name.
	 * Does not use the cache, so use sparingly.
	 *
	 * @param string $username		 Username to load
	 * @param string $password		 Optional (but not really) Password to check against
	 * @param mixed $activeonly		 Optional flag whether to load the user if [s]he is active Default true
	 * @param mixed $adminaccessonly Optional flag whether to load the user if [s]he has admin access Default false
	 *
	 * @return mixed a User-class object or null or false
	 *
	 * @since 0.6.1
	 */
	public function LoadUserByUsername(string $username, string $password = '', bool $activeonly = true, bool $adminaccessonly = false)
	{
		// note: does not use cache
		$db = CmsApp::get_instance()->GetDb();

		$query = 'SELECT user_id,password FROM '.CMS_DB_PREFIX.'users';
		$where = ['username = ?'];
		$params = [$username];

		if ($activeonly) {
			$where[] = 'active = 1';
		}

		if ($adminaccessonly) {
			$where[] = 'admin_access = 1';
		}

		$query .= ' WHERE '.implode(' AND ', $where);

		$row = $db->GetRow($query,$params);
		if ($row) {
			$hash = $row['password'];
			$len = strlen(bin2hex($hash))/2; //ignore mb_ override
			if ($len > 32) { //bcrypt or argon2
				if (!password_verify($password, $hash)) {
					sleep(1);
					return;
				}
				if ((defined('PASSWORD_ARGON2I') && strncmp($hash, '$2y$', 4) == 0) //still uses bcrypt
					 || password_needs_rehash($hash, PASSWORD_DEFAULT)) {
					$oneuser = new User();
					$oneuser->SetPassword($password);
					$query = 'UPDATE '.CMS_DB_PREFIX.'users SET password = ? WHERE user_id = ?';
					$db->Execute($query, [$oneuser->password, $row['user_id']]);
				}
			} else {
				$tmp = md5(cms_siteprefs::get('sitemask', '').$password);
				if (!hash_equals($tmp, $hash)) {
					sleep(1);
					return;
				}
				$oneuser = new User();
				$oneuser->SetPassword($password);
				$query = 'UPDATE '.CMS_DB_PREFIX.'users SET password = ? WHERE user_id = ?';
				$db->Execute($query, [$oneuser->password, $row['user_id']]);
			}
			return $this->LoadUserByID($row['user_id']);
		}
	}

	/**
	 * Loads a user by user id.
	 *
	 * @param mixed $id User id to load
	 *
	 * @return mixed If successful, the filled User object | false
	 *
	 * @since 0.6.1
	 */
	public function LoadUserByID(int $id)
	{
		$id = (int) $id;
		if ($id < 1) {
			return false;
		}
		if (isset($this->_saved_users[$id])) {
			return $this->_saved_users[$id];
		}

		$result = false;
		$db = CmsApp::get_instance()->GetDb();

		$query = 'SELECT username, password, active, first_name, last_name, admin_access, email FROM '.CMS_DB_PREFIX.'users WHERE user_id = ?';
		$dbresult = $db->Execute($query, [$id]);

		while ($dbresult && $row = $dbresult->FetchRow()) {
			$oneuser = new User();
			$oneuser->id = $id;
			$oneuser->username = $row['username'];
			$oneuser->password = $row['password'];
			$oneuser->firstname = $row['first_name'];
			$oneuser->lastname = $row['last_name'];
			$oneuser->email = $row['email'];
			$oneuser->adminaccess = $row['admin_access'];
			$oneuser->active = $row['active'];
			$result = $oneuser;
		}

		$this->_saved_users[$id] = $result;
		return $result;
	}

	/**
	 * Saves a new user to the database.
	 *
	 * @param mixed $user User object to save
	 *
	 * @return mixed The new user id.  If it fails, it returns -1
	 *
	 * @since 0.6.1
	 */
	public function InsertUser($user) : int
	{
		$db = CmsApp::get_instance()->GetDb();
		$pref = CMS_DB_PREFIX;
		//setting create_date should be redundant with DT setting
		$query = <<<EOS
INSERT INTO {$pref}users
(username, password, active, first_name, last_name, email, admin_access, create_date)
SELECT ?,?,?,?,?,?,?,NOW() FROM (SELECT 1 AS dmy) Z
WHERE NOT EXISTS (SELECT 1 FROM {$pref}users T WHERE T.username=?)
EOS;
		$dbr = $db->Execute($query, [
			$user->username,
			$user->password,
			$user->active,
			$user->firstname,
			$user->lastname,
			$user->email,
			1,
			$user->username
		]);

		return ($dbr) ? $db->Insert_ID() : -1;
	}

	/**
	 * Updates an existing user in the database.
	 *
	 * @since 0.6.1
	 *
	 * @param mixed $user User object to save
	 *
	 * @return mixed If successful, true.  If it fails, false
	 */
	public function UpdateUser($user)
	{
		$result = false;
		$db = CmsApp::get_instance()->GetDb();

		// check for username conflict
		$query = 'SELECT user_id FROM '.CMS_DB_PREFIX.'users WHERE username = ? and user_id != ?';
		$tmp = $db->GetOne($query, [$user->username, $user->id]);
		if ($tmp) {
			return $result;
		}

		$now = $db->DbTimeStamp(time());
		$query = 'UPDATE '.CMS_DB_PREFIX.'users SET username = ?, password = ?, active = ?, modified_date = '.$now.', first_name = ?, last_name = ?, email = ?, admin_access = ? WHERE user_id = ?';
		//$dbresult = $db->Execute($query, array($user->username, $user->password, $user->active, $user->firstname, $user->lastname, $user->email, $user->adminaccess, $user->id));
		$dbresult = $db->Execute($query, [$user->username, $user->password, $user->active, $user->firstname, $user->lastname, $user->email, 1, $user->id]);
		if ($dbresult !== false) {
			$result = true;
		}

		return $result;
	}

	/**
	 * Deletes an existing user from the database.
	 *
	 * @since 0.6.1
	 *
	 * @param mixed $id Id of the user to delete
	 * @return bool indicating success
	 */
	public function DeleteUserByID($id)
	{
		if ($id <= 1) {
			return false;
		}

        if (!$this->CheckPermission(get_userid(false), 'Manage Users')) {
			return false;
		}

		$db = CmsApp::get_instance()->GetDb();

		$query = 'DELETE FROM '.CMS_DB_PREFIX.'user_groups WHERE user_id = ?';
		$result = $db->Execute($query, [$id]);

		$query = 'DELETE FROM '.CMS_DB_PREFIX.'additional_users WHERE user_id = ?';
		$result = $result && $db->Execute($query, [$id]);

		$query = 'DELETE FROM '.CMS_DB_PREFIX.'users WHERE user_id = ?';
		$result = $result && $db->Execute($query, [$id]);

		$query = 'DELETE FROM '.CMS_DB_PREFIX.'userprefs WHERE user_id = ?';
		$result = $result && $db->Execute($query, [$id]);

		return $result;
	}

	/**
	 * Show the number of pages the given user's id owns.
	 *
	 * @since 0.6.1
	 *
	 * @param mixed $id Id of the user to count
	 *
	 * @return mixed Number of pages they own.	0 if any problems
	 */
	public function CountPageOwnershipByID($id)
	{
		$result = 0;
		$db = CmsApp::get_instance()->GetDb();

		$query = 'SELECT count(*) AS count FROM '.CMS_DB_PREFIX.'content WHERE owner_id = ?';
		$dbresult = $db->Execute($query, [$id]);

		if ($dbresult && $dbresult->RecordCount() > 0) {
			$row = $dbresult->FetchRow();
			if (isset($row['count'])) {
				$result = $row['count'];
			}
		}

		return $result;
	}

	/**
	 * Generate an array of admin userids to usernames, suitable for use in a dropdown.
	 *
	 * @return array
	 *
	 * @since 2.2
	 */
	public function GetList()
	{
		$allusers = $this->LoadUsers();
		if (!count($allusers)) {
			return;
		}

		foreach ($allusers as $oneuser) {
			$out[$oneuser->id] = $oneuser->username;
		}
		return $out;
	}

	/**
	 * Generate an HTML select element containing a user list.
	 *
	 * @deprecated
	 *
	 * @param int	 $currentuserid
	 * @param string $name			The HTML element name
	 */
	public function GenerateDropdown($currentuserid = null, $name = 'ownerid')
	{
		$result = null;
		$list = $this->GetList();
		if ($list) {
			$result .= '<select name="'.$name.'">';
			foreach ($list as $uid => $username) {
				$result .= '<option value="'.$uid.'"';
				if ($uid == $currentuserid) {
					$result .= ' selected="selected"';
				}
				$result .= '>'.$username.'</option>';
			}
			$result .= '</select>';
		}
		return $result;
	}

	/**
	 * Tests $uid is a member of the group identified by $gid.
	 *
	 * @param int $uid User ID to test
	 * @param int $gid Group ID to test
	 *
	 * @return true if test passes, false otherwise
	 */
	public function UserInGroup($uid, $gid)
	{
		$groups = $this->GetMemberGroups($uid);
		return in_array($gid, $groups);
	}

	/**
	 * Test if the specified user is a member of the admin group, or is the first user account.
	 *
	 * @param int $uid
	 *
	 * @return bool
	 */
	public function IsSuperuser($uid)
	{
		if ($uid == 1) {
			return true;
		}
		$groups = $this->GetMemberGroups(1);
		if ($groups) {
			if (in_array($uid, $groups)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the ids of all groups to which the user belongs.
	 *
	 * @param int $uid
	 *
	 * @return array
	 */
	public function GetMemberGroups($uid)
	{
		if (!is_array($this->_user_groups) || !isset($this->_user_groups[$uid])) {
			$db = CmsApp::get_instance()->GetDb();
			$query = 'SELECT group_id FROM '.CMS_DB_PREFIX.'user_groups WHERE user_id = ?';
			$col = $db->GetCol($query, [(int) $uid]);
			if (!is_array($this->_user_groups)) {
				$this->_user_groups = [];
			}
			$this->_user_groups[$uid] = $col;
		}
		return $this->_user_groups[$uid];
	}

	/**
	 * Add the user to the specified group.
	 *
	 * @param int $uid
	 * @param int $gid
	 */
	public function AddMemberGroup($uid, $gid)
	{
		$uid = (int) $uid;
		$gid = (int) $gid;
		if ($uid < 1 || $gid < 1) {
			return;
		}

		$db = CmsApp::get_instance()->GetDb();
		$now = $db->DbTimeStamp(time());
		$query = 'INSERT INTO '.CMS_DB_PREFIX."user_groups
(group_id,user_id,create_date) VALUES (?,?,$now)";
//		$dbresult =
		$db->Execute($query, [$gid, $uid]);
		if (isset($this->_user_groups[$uid])) {
			unset($this->_user_groups[$uid]);
		}
	}

	/**
	 * Test if any users-group of which the specified user is a member has the specified permission(s).
	 *
	 * @param int	$userid
	 * @param mixed $permname single string or (since 2.3) an array of them, optionally with following bool
	 *
	 * @return bool
	 */
	public function CheckPermission($userid, $permname)
	{
		if ($userid <= 0) {
			return false;
		}
		$groups = $this->GetMemberGroups($userid);
		if ($userid == 1) {
			array_unshift($groups, 1);
			$groups = array_unique($groups, SORT_NUMERIC);
        }
		if (!$groups) {
			return false;
		}
		if (is_string($permname)) {
			$perms = [$permname];
		} elseif (is_array($permname)) {
			$arr = func_get_args(); //since we're not breaking the method API
			unset($arr[0]);
			$perms = $arr;
		} else {
			return false;
		}
		$ops = GroupOperations::get_instance();
		try {
			foreach ($groups as $gid) {
				if ($ops->CheckPermission($gid, ...$perms)) {
					return true;
				}
			}
		} catch (CmsException $e) {
			// nothing here
		}
		return false;
	}

	/**
	 * Get recorded data about the specified user, without password check.
	 * Does not use the cache, so use sparingly.
     * At least one of $username or $userid must be provided.
 	 *
	 * @since 2.3
	 * @param string $username Optional login/account name, used if provided
	 * @param int	 $userid Optional user id, used if $username not provided
	 *
	 * @return mixed User-class object | null if the user is not recognised.
	 */
	public function GetRecoveryData(string $username='', int $userid=-1)
	{
		$query = 'SELECT user_id FROM '.CMS_DB_PREFIX.'users WHERE ';
		if ($username) {
			$query .= 'username=?';
			$parms = [$username];
		} elseif ($userid > 0) {
			$query .= 'user_id=?';
			$parms = [$userid];
		} else {
			return null;
		}

		$db = CmsApp::get_instance()->GetDb();
		$uid = $db->GetOne($query, $parms);
		if ($uid) {
			return $this->LoadUserByID((int)$uid);
		}
		return null;
	}

} //class

//backward-compatibility shiv
\class_alias(UserOperations::class, 'UserOperations', false);
