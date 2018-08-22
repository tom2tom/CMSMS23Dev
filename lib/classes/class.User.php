<?php
#admin-user-class for CMSMS
#Copyright (C) 2004-2010 Ted Kulp <ted@cmsmadesimple.org>
#Copyright (C) 2011-2018 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

/**
 * User class definition
 *
 * @package CMS
 * @license GPL
 */

/**
 * Generic admin user class.  This can be used for any logged in user or user related function.
 *
 * @package CMS
 * @since 0.6.1
 * @license GPL
 */
 
namespace CMSMS;

use cms_siteprefs;

class User
{
	/**
	 * @var int $id User id
	 */
	public $id;

	/**
	 * @var string Username
	 */
	public $username;

	/**
	 * @var string $password Password (md5 encoded)
	 */
	public $password;

	/**
	 * @var string $firstname Users First Name
	 */
	public $firstname;

	/**
	 * @var string $lastname Last Name
	 */
	public $lastname;

	/**
	 * @var string $email Users Email Address
	 */
	public $email;

	/**
	 * @var bool $active Active Flag
	 */
	public $active;

	/**
	 * @var bool $adminaccess Flag to tell whether user can login to admin panel
	 */
	public $adminaccess;

	/**
	 * Generic constructor.  Runs the SetInitialValues fuction.
	 */
	public function __construct()
	{
		$this->SetInitialValues();
	}

	/**
	 * Sets object to some sane initial values
	 *
	 * @since 0.6.1
	 */
	public function SetInitialValues()
	{
		$this->id = -1;
		$this->username = '';
		$this->password = '';
		$this->firstname = '';
		$this->lastname = '';
		$this->email = '';
		$this->active = false;
		$this->adminaccess = false;
	}

	/**
	 * Sets the user's active state.
	 *
	 * @since 2.3
	 * @param bool $flag The active state.
	 */
	public function SetActive($flag = true)
	{
		$this->active = (bool) $flag;
	}

	/**
	 * Encrypts and sets password for the User
	 *
	 * @since 0.6.1
	 * @param string $password The plaintext password.
	 */
	public function SetPassword($password)
	{
		$this->password = password_hash( $password, PASSWORD_DEFAULT );
	}

	/**
	 * Authenticate a users password.
	 *
	 * @since 2.3
	 * @param string $password The plaintext password.
	 * @author calguy1000
	 */
	public function Authenticate( $password )
	{
		if( strlen($this->password) == 32 && strpos( $this->password, '.') === FALSE ) {
			// old md5 methodology
			$hash = md5( cms_siteprefs::get('sitemask','').$password);
			return ($hash == $this->password);
		} else {
			return password_verify( $password, $this->password );
		}
	}

	/**
	 * Saves the user to the database.  If no user_id is set, then a new record
	 * is created.  If the uset_id is set, then the record is updated to all values
	 * in the User object.
	 *
	 * @returns mixed If successful, true.  If it fails, false.
	 * @since 0.6.1
	 */
	public function Save()
	{
		$result = false;

		$userops = UserOperations::get_instance();
		if ($this->id > -1) {
			$result = $userops->UpdateUser($this);
		}
		else {
			$newid = $userops->InsertUser($this);
			if ($newid > -1) {
				$this->id = $newid;
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * Delete the record for this user from the database and resets
	 * all values to their initial values.
	 *
	 * @returns mixed If successful, true.  If it fails, false.
	 * @since 0.6.1
	 */
	public function Delete()
	{
		$result = false;
		if ($this->id > -1) {
			$userops = UserOperations::get_instance();
			$result = $userops->DeleteUserByID($this->id);
			if ($result) $this->SetInitialValues();
		}
		return $result;
	}
} //class

//backward-compatiblity shiv
\class_alias(User::class, 'User', false);
