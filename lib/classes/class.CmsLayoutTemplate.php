<?php
#class representing a LayoutTemplate.
#Copyright (C) 2014-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

//namespace CMSMS;

use CMSMS\AdminUtils;
use CMSMS\Events;
use CMSMS\internal\Smarty;
use CMSMS\internal\TemplateCache;
use CMSMS\User;
use CMSMS\UserOperations;

/**
 * A class to represent a LayoutTemplate.
 *
 * @package CMS
 * @license GPL
 * @since 2.0
 * @author Robert Campbell <calguy1000@cmsmadesimple.org>
 */
class CmsLayoutTemplate
{
   /**
	* @ignore
	*/
	const TABLENAME = 'layout_templates';

   /**
	* @ignore
	*/
	const ADDUSERSTABLE = 'layout_tpl_addusers';

   /**
	* @ignore
	*/
	private $_dirty;

   /**
	* @ignore
	*/
	private $_data = ['listable'=>true];

   /**
	* @ignore
	*/
	private $_addt_editors;

   /**
	* @ignore
	*/
	private $_design_assoc;

   /**
	* @ignore
	*/
	private $_cat_assoc;

   /**
	* @ignore
	*/
	private static $_obj_cache;

   /**
	* @ignore
	*/
	private static $_name_cache;

   /**
	* @ignore
	*/
	private static $_lock_cache;

   /**
	* @ignore
	*/
	private static $_lock_cache_loaded;

   /**
	* @ignore
	*/
	public function __clone()
	{
		if( isset($this->_data['id']) ) unset($this->_data['id']);
		$this->_data['type_dflt'] = false;
		$this->_dirty = true;
	}

   /**
	* Get the integer id of this template
	*
	* @return int
	*/
	public function get_id()
	{
		return $this->_data['id'] ?? 0;
	}

   /**
	* Get the template name
	*
	* @return string
	*/
	public function get_name()
	{
		return $this->_data['name'] ?? '';
	}

   /**
	* Set the name of the template
	*
	* The template name cannot be empty, can only consist of a few characters
	* in the name and must be unique
	*
	* @param string $str
	* @throws CmsInvalidDataException
	*/
	public function set_name($str)
	{
		if( !AdminUtils::is_valid_itemname($str) ) {
			throw new CmsInvalidDataException("Invalid characters in name: $str");
		}
		$this->_data['name'] = $str;
		$this->_dirty = true;
	}

	/**
	* Get the owner/originator of the template
	* @since 2.3
	*
	* @return string
	*/
	public function get_originator()
	{
		return $this->_data['originator'] ?? '';
	}

	/**
	* Set the owner/originator of the template
	* @since 2.3
	* @param string $str
	*/
	public function set_originator($str)
	{
		$str = trim($str);
		if( $str === '') $str = null;
		$this->_data['originator'] = $str;
		$this->_dirty = true;
	}

   /**
	* Get the content of the template
	*
	* @return string
	*/
	public function get_content()
	{
		return $this->_data['content'] ?? '';
	}

   /**
	* Set the content of the template
	*
	* @param string $str Smarty template text
	*/
	public function set_content($str)
	{
		$str = trim($str);
		if( !$str ) $str = '{* Empty Smarty Template *}';
		$this->_data['content'] = $str;
		$this->_dirty = true;
	}

   /**
	* Get the description for the template
	*
	* @return string
	*/
	public function get_description()
	{
		return $this->_data['description'] ?? '';
	}

   /**
	* Set the description for the template
	*
	* @param string $str
	*/
	public function set_description($str)
	{
		$str = trim($str);
		$this->_data['description'] = $str;
		$this->_dirty = true;
	}

   /**
	* Get the type id for the template
	*
	* @return int
	*/
	public function get_type_id()
	{
		return $this->_data['type_id'] ?? 0;
	}

   /**
	* Set the type id for the template
	*
	* @throws CmsLogicException
	* @param mixed $a Either an instance of CmsLayoutTemplateType object, an integer type id, or a string template type identifier
	* @see CmsLayoutTemplateType
	*/
	public function set_type($a)
	{
		$n = null;
		if( $a instanceof CmsLayoutTemplateType ) {
			$n = $a->get_id();
		}
		else if( is_numeric($a) && (int)$a > 0 ) {
			$n = (int)$a;
		}
		else if( is_string($a) && $a !== '' ) {
			$type = CmsLayoutTemplateType::load($a);
			$n = $type->get_id();
		}
		else {
			throw new CmsLogicException('Invalid data passed to '.__METHOD__);
		}

		$this->_data['type_id'] = (int) $n;
		$this->_dirty = true;
	}

   /**
	* Get the flag indicating that this template is the default template for its type
	*
	* @return bool
	* @see CmsLayoutTemplateType
	*/
	public function get_type_dflt()
	{
		return !empty($this->_data['type_dflt']);
	}

   /**
	* Set the flag that indicates that this template is the default template for its type
	* Only one template can be the default for a template type
	*
	* @param bool $flag
	* @see CmsLayoutTemplateType
	*/
	public function set_type_dflt($flag)
	{
		$n = (bool)$flag;
		$this->_data['type_dflt'] = $n;
		$this->_dirty = true;
	}

   /**
	* Get the category id for this template
	* A template is not required to be associated with a category
	*
	* @deprecated since 2.3 templates may be in multiple categories
	* @param int
	*/
	public function get_category_id()
	{
		return $this->_data['category_id'] ?? 0;
	}

   /**
	* Get the category for this template
	* A template is not required to be associated with a category
	*
	* @deprecated since 2.3 templates may be in multiple categories
	* @param CmsLayoutTemplateCategory
	* @see CmsLayoutTemplateCategory
	*/
	public function &get_category()
	{
		$n = $this->get_category_id();
		if( $n > 0 ) return CmsLayoutTemplateCategory::load($n);
	}

   /**
	* Get numeric id corresponding to $a
	* @since 2.3
	* @throws CmsLogicException
	* @param mixed $a A CmsLayoutTemplateCategory object, an integer category id, or a string category name.
	* @return int
	*/
	protected function get_categoryid($a) : int
	{
		if( is_numeric($a) && (int)$a > 0 ) {
			return (int)$a;
		}
		elseif( (is_string($a) && strlen($a)) || (int)$a > 0 ) {
			$ob = CmsLayoutTemplateCategory::load($a);
			if( $ob ) {
				return $ob->get_id();
			}
		}
		elseif( $a instanceof CmsLayoutTemplateCategory ) {
			return $a->get_id();
		}
		throw new CmsLogicException('Invalid data passed to '.__METHOD__);
	}

   /**
	* Get a list of the category id's that this template is associated with
	*
	* @since 2.3
	* @return array Array of integers
	*/
	public function get_categories() : array
	{
		if( !is_array($this->_cat_assoc) ) {
			if( !$this->get_id() ) return [];
			$db = CmsApp::get_instance()->GetDb();
			$query = 'SELECT category_id FROM '.CMS_DB_PREFIX.CmsLayoutTemplateCategory::TPLTABLE.' WHERE tpl_id = ? ORDER BY tpl_order';
			$tmp = $db->GetCol($query,[(int)$this->get_id()]);
			if( $tmp ) $this->_cat_assoc = $tmp;
			else $this->_cat_assoc = [];
		}
		return $this->_cat_assoc;
	}

   /**
	* Set the category for a template
	*
	* @deprecated since 2.3 templates may be in multiple categories
	* @throws CmsLogicException
	* @param mixed $a Either a CmsLayoutTemplateCategory object, a category name (string) or category id (int)
	* @see CmsLayoutTemplateCategory
	*/
	public function set_category($a)
	{
		if( !$a ) return;
		$n = $this->get_categoryid($a);
		if( empty($this->_cat_assoc) || !in_array($n, $this->_cat_assoc) ) $this->_cat_assoc[] = (int) $n;
		$this->_data['category_id'] = (int) $n;
		$this->_dirty = true;
	}

   /**
	* Set the list of categories that this template is associated with
	*
	* @since 2.3
	* @throws CmsInvalidDataException
	* @param array $x Array of integers.
	*/
	public function set_categories(array $x)
	{
		if( !is_array($x) ) return;

		foreach( $x as $y ) {
			if( !is_numeric($y) || (int) $y < 1 ) throw new CmsInvalidDataException('Invalid data in design list.  Expect array of integers');
		}

		$this->_cat_assoc = $x;
		$this->_dirty = true;
	}

   /**
	* Associate another category with this template
	*
	* @since 2.3
	* @throws CmsLogicException
	* @param mixed $a A CmsLayoutCollection object, an integer design id, or a string design name.
	* @see CmsLayoutTemplateCategory
	*/
	public function add_category($a)
	{
		$n = $this->get_categoryid($a);
		$this->get_categories();
		if( !is_array($this->_cat_assoc) ) {
			$this->_cat_assoc = [$n];
			$this->_dirty = true;
		}
		elseif( !in_array($n, $this->_cat_assoc) ) {
			$this->_cat_assoc[] = (int) $n;
			$this->_dirty = true;
		}
	}

   /**
	* Remove a category from the list of associated categories
	*
	* @since 2.3
	* @throws CmsLogicException
	* @param mixed $a A CmsLayoutCollection object, an integer design id, or a string design name.
	* @see CmsLayoutTemplateCategory
	*/
	public function remove_category($a)
	{
		$this->get_categories();
		if( empty($this->_cat_assoc) ) return;

		$n = $this->get_categoryid($a);
		if( in_array($n, $this->_cat_assoc) ) {
			foreach( $this->_cat_assoc as &$one ) {
				if( $n == $one ) {
					unset($one);
					break;
				}
			}
			unset($one);
			$this->_dirty = true;
		}
	}

   /**
	* Get a list of the design id's that this template is associated with
	*
	* @return array Array of integers
	*/
	public function get_designs()
	{
		if( !is_array($this->_design_assoc) ) {
			if( !$this->get_id() ) return [];
			$db = CmsApp::get_instance()->GetDb();
			$query = 'SELECT design_id FROM '.CMS_DB_PREFIX.CmsLayoutCollection::TPLTABLE.' WHERE tpl_id = ?';
			$tmp = $db->GetCol($query,[(int)$this->get_id()]);
			if( $tmp ) $this->_design_assoc = $tmp;
			else $this->_design_assoc = [];

		}
		return $this->_design_assoc;
	}

   /**
	* Set the list of designs that this template is associated with
	*
	* @throws CmsInvalidDataException
	* @param array $x Array of integers.
	*/
	public function set_designs($x)
	{
		if( !is_array($x) ) return;

		foreach( $x as $y ) {
			if( !is_numeric($y) || (int) $y < 1 ) throw new CmsInvalidDataException('Invalid data in design list.  Expect array of integers');
		}

		$this->_design_assoc = $x;
		$this->_dirty = true;
	}

   /**
	* Get numeric id corresponding to $a
	* @throws CmsLogicException
	* @param mixed $a A CmsLayoutCollection object, an integer design id, or a string design name.
	* @return int
	*/
	protected function get_designid($a) : int
	{
		if( is_numeric($a) && (int)$a > 0 ) {
			return (int)$a;
		}
		elseif( is_string($a) && $a !== '' ) {
			$ob = CmsLayoutCollection::load($a);
			if( $ob ) {
				return $ob->get_id();
			}
		}
		elseif( $a instanceof CmsLayoutCollection ) {
			return $a->get_id();
		}
		throw new CmsLogicException('Invalid data passed to '.__METHOD__);
	}

   /**
	* Associate another design with this template
	*
	* @throws CmsLogicException
	* @param mixed $a A CmsLayoutCollection object, an integer design id, or a string design name.
	* @see CmsLayoutCollection
	*/
	public function add_design($a)
	{
		$n = $this->get_designid($a);
		$this->get_designs();
		if( !is_array($this->_design_assoc) ) {
			$this->_design_assoc = [$n];
			$this->_dirty = true;
		}
		elseif( !in_array($n, $this->_design_assoc) ) {
			$this->_design_assoc[] = (int) $n;
			$this->_dirty = true;
		}
	}

   /**
	* Remove a design from the list of associated designs
	*
	* @throws CmsLogicException
	* @param mixed $a A CmsLayoutCollection object, an integer design id, or a string design name.
	* @see CmsLayoutCollection
	*/
	public function remove_design($a)
	{
		$this->get_designs();
		if( empty($this->_design_assoc) ) return;

		$n = $this->get_designid($a);
		if( in_array($n, $this->_design_assoc) ) {
			foreach( $this->_design_assoc as &$one ) {
				if( $n == $one ) {
					unset($one);
					break;
				}
			}
			unset($one);
			$this->_dirty = true;
		}
	}

   /**
	* Get the integer owner id of this template
	*
	* @return int
	*/
	public function get_owner_id()
	{
		return $this->_data['owner_id'] ?? -1;
	}

   /**
	* Set the owner id
	*
	* @throws CmsInvalidDataException
	* @param mixed $a An integer admin user id, a string admin username, or an instance of a User object
	* @see User
	*/
	public function set_owner($a)
	{
		$n = null;
		if( is_numeric($a) && $a > 0 ) {
			$n = (int)$a;
		}
		else if( is_string($a) && $a !== '' ) {
			// load the user by name.
			$ops = UserOperations::get_instance();
			$ob = $ops->LoadUserByUsername($a);
			if( $a instanceof User ) $n = $a->id;
		}
		else if( $a instanceof User ) {
			$n = $a->id;
		}

		if( $n < 1 ) throw new CmsInvalidDataException('Owner id must be valid in '.__METHOD__);
		$this->_data['owner_id'] = (int) $n;
		$this->_dirty = true;
	}

   /**
	* Get the date that this template was initially stored in the database
	* The created date can not be externally modified
	*
	* @return int
	*/
	public function get_created()
	{
		return $this->_data['created'] ?? 0;
	}

   /**
	* Get the date that this template was last saved to the database
	* The modified date cannot be externally modified
	*
	* @return int
	*/
	public function get_modified()
	{
		return $this->_data['modified'] ?? 0;
	}

   /**
	* Get a list of user id's other than the owner that are allowed edit functionality to this template
	*
	* @return array Array of integer user ids
	*/
	public function get_additional_editors()
	{
		if( is_null($this->_addt_editors) ) {
			if( $this->get_id() ) {
				$db = CmsApp::get_instance()->GetDb();
				$query = 'SELECT user_id FROM '.CMS_DB_PREFIX.self::ADDUSERSTABLE.' WHERE tpl_id = ?';
				$col = $db->GetCol($query,[$this->get_id()]);
				if( $col ) $this->_addt_editors = $col;
				else $this->_addt_editors = [];
			}
		}
		if( $this->_addt_editors ) return $this->_addt_editors;
	}

   /**
	* @ignore
	*/
	private static function _resolve_user($a)
	{
		if( is_numeric($a) && $a > 0 ) return $a;
		if( is_string($a) && strlen($a) ) {
			$ops = UserOperations::get_instance();
			$ob = $ops->LoadUserByUsername($a);
			if( $a instanceof User ) return $a->id;
		}
		if( $a instanceof User ) return $a->id;
		throw new CmsLogicException('Could not resolve '.$a.' to a user id');
	}

   /**
	* Set the admin user accounts (other than the owner) that are allowed to edit this template object
	*
	* @throws CmsInvalidDataException
	* @param mixed $a Accepts an array of strings (usernames) or an array of integers (user ids, and negative group ids)
	*/
	public function set_additional_editors($a)
	{
		if( !is_array($a) ) {
			if( is_string($a) || (is_numeric($a) && $a > 0) ) {
				// maybe a single value...
				$res = self::_resolve_user($a);
				$this->_addt_editors = [$res];
				$this->_dirty = true;
			}
		}
		else {
			$tmp = [];
			for( $i = 0, $n = count($a); $i < $n; $i++ ) {
				if( !is_numeric($a[$i]) ) continue;
				$tmp2 = (int)$a[$i];  // intentional cast to int, may have negative values.
				if( $tmp2 != 0 ) {
					$tmp[] = $tmp2;
				}
				else if( is_string($a[$i]) ) {
					$tmp[] = self::_resolve_user($a[$i]);
				}
			}
			$this->_addt_editors = $tmp;
			$this->_dirty = true;
		}
	}

   /**
	* Test whether the user specified has edit ability for this template object
	*
	* @param mixed $a Either a username (string) or an integer user id.
	* @return bool
	*/
	public function can_edit($a)
	{
		$res = self::_resolve_user($a);
		if( $res == $this->get_owner_id() ) return true;
		if( is_array($this->_addt_editors) && count($this->_addt_editors) && !in_array($res,$this->_addt_editors) ) return true;
		return false;
	}

   /**
	* Get whether this template is listable in public template lists.
	*
	* @return bool
	* @since 2.1
	*/
	public function get_listable()
	{
		return $this->_data['listable'] ?? true;
	}

   /**
	* Get whether this template is listable in public template lists.
	*
	* @return bool
	* @since 2.1
	*/
	public function is_listable()
	{
		return $this->get_listable();
	}

   /**
	* Get whether this template is listable in public template lists.
	*
	* @param bool $flag The value for the listable attribute.
	* @return bool
	* @since 2.1
	*/
	public function set_listable($flag)
	{
		$this->_data['listable'] = cms_to_bool($flag);
	}

   /**
	* Process the template through smarty
	*
	* @return string
	*/
	public function process()
	{
		$smarty = Smarty::get_instance();
		return $smarty->fetch('cms_template:id='.$this->get_id());
	}

   /**
	* Validate that the data is complete enough to save to the database
	*
	* @throws CmsInvalidDataException
	*/
	protected function validate()
	{
		if( !$this->get_name() ) throw new CmsInvalidDataException('Each template must have a name');
		if( endswith($this->get_name(),'.tpl') ) throw new CmsInvalidDataException('Invalid name for a database template');
		if( !AdminUtils::is_valid_itemname($this->get_name()) ) {
			throw new CmsInvalidDataException('There are invalid characters in the template name');
		}

		if( !$this->get_content() ) throw new CmsInvalidDataException('Each template must have some content');
		if( $this->get_type_id() <= 0 ) throw new CmsInvalidDataException('Each template must be associated with a type');

		$db = CmsApp::get_instance()->GetDb();
		$tmp = null;
		if( $this->get_id() ) {
			// double check the name.
			$query = 'SELECT id FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE name = ? AND id != ?';
			$tmp = $db->GetOne($query,[$this->get_name(),$this->get_id()]);
		} else {
			// double-check the name.
			$query = 'SELECT id FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE name = ?';
			$tmp = $db->GetOne($query,[$this->get_name()]);
		}
		if( $tmp ) {
			throw new CmsInvalidDataException('Template with the same name already exists.');
		}
	}

   /**
	* @ignore
	*/
	protected function _get_anyowner()
	{
		$tmp = $this->get_originator();
		if( $tmp ) {
			return $tmp;
		}
		$tid = $this->get_type_id();
		if( $tid > 0 ) {
			$db = CmsApp::get_instance()->GetDb();
			$query = 'SELECT originator FROM '.CMS_DB_PREFIX.CmsLayoutTemplateType::TABLENAME.' WHERE id = ?';
			$dbr = $db->GetOne($query,[$tid]);
			if( $dbr ) {
				return $dbr;
			}
		}
		return null;
	}

   /**
	* @ignore
	*/
	protected function _update()
	{
		if( !$this->_dirty ) return;
		$this->validate();

		$now = time();
		$tplid = $this->get_id();

		$query = 'UPDATE '.CMS_DB_PREFIX.self::TABLENAME.' SET
originator=?,
name=?,
content=?,
description=?,
type_id=?,
type_dflt=?,
owner_id=?,
listable=?,
modified=?
WHERE id=?';
		$db = CmsApp::get_instance()->GetDb();
//		$dbr =
		$db->Execute($query,
		[$this->_get_anyowner(),
		 $this->get_name(),
		 $this->get_content(),
		 $this->get_description(),
		 $this->get_type_id(),
		 $this->get_type_dflt(),
		 $this->get_owner_id(),
		 $this->get_listable(),
		 $now,$tplid
		]);
//		if( !$dbr ) throw new CmsSQLErrorException($db->sql.' -- '.$db->ErrorMsg());

		if( $this->get_type_dflt() ) {
			// if it's default for a type, unset default flag for all other templates of this type
			$query = 'UPDATE '.CMS_DB_PREFIX.self::TABLENAME.' SET type_dflt = 0 WHERE type_id = ? AND type_dflt = 1 AND id != ?';
//			$dbr =
			$db->Execute($query,[$this->get_type_id(),$tplid]);
//			if( !$dbr ) throw new CmsSQLErrorException($db->sql.' -- '.$db->ErrorMsg());
		}

		$query = 'DELETE FROM '.CMS_DB_PREFIX.self::ADDUSERSTABLE.' WHERE tpl_id = ?';
		$dbr = $db->Execute($query,[$tplid]);
		if( !$dbr ) throw new CmsSQLErrorException($db->sql.' --5 '.$db->ErrorMsg());

		$t = $this->get_additional_editors();
		if( $t ) {
			$query = 'INSERT INTO '.CMS_DB_PREFIX.self::ADDUSERSTABLE.' (tpl_id,user_id) VALUES(?,?)';
			foreach( $t as $one ) {
				$db->Execute($query,[$tplid,(int)$one]);
			}
		}

		$query = 'DELETE FROM '.CMS_DB_PREFIX.CmsLayoutCollection::TPLTABLE.' WHERE tpl_id = ?';
		$dbr = $db->Execute($query,[$tplid]);
		if( !$dbr ) throw new CmsSQLErrorException($db->sql.' --6 '.$db->ErrorMsg());
		$t = $this->get_designs();
		if( $t ) {
			$query = 'INSERT INTO '.CMS_DB_PREFIX.CmsLayoutCollection::TPLTABLE.' (design_id,tpl_id,tpl_order) VALUES(?,?,?)';
			$i = 1;
			foreach( $t as $one ) {
				//TODO use statement
				$db->Execute($query,[(int)$one,$tplid,$i]);
				++$i;
			}
		}

		$query = 'DELETE FROM '.CMS_DB_PREFIX.CmsLayoutTemplateCategory::TPLTABLE.' WHERE tpl_id = ?';
		$db->Execute($query,[$tplid]);
		$t = $this->get_categories();
		if( $t ) {
			$stmt = $db->Prepare('INSERT INTO '.CMS_DB_PREFIX.CmsLayoutTemplateCategory::TPLTABLE.' (category_id,tpl_id,tpl_order) VALUES(?,?,?)');
			$i = 1;
			foreach( $t as $one ) {
				$db->Execute($stmt,[(int)$one,$tplid,$i]);
				++$i;
			}
		}

		TemplateCache::clear_cache();
		audit($tplid,'CMSMS','Template '.$this->get_name().' Updated');
		$this->_dirty = false;
	}

   /**
	* @ignore
	*/
	protected function _insert()
	{
		if( !$this->_dirty ) return;
		$this->validate();

		$now = time();

		// insert the record
		$query = 'INSERT INTO '.CMS_DB_PREFIX.self::TABLENAME.
' (originator,name,content,description,type_id,type_dflt,owner_id,listable,created,modified)
VALUES (?,?,?,?,?,?,?,?,?,?)';
		$db = CmsApp::get_instance()->GetDb();
		$dbr = $db->Execute($query,
		[$this->_get_anyowner(),
		 $this->get_name(),
		 $this->get_content(),
		 $this->get_description(),
		 $this->get_type_id(),
		 $this->get_type_dflt(),
		 $this->get_owner_id(),
		 $this->get_listable(),
		 $now,$now
		]);
		if( !$dbr ) {
			throw new CmsSQLErrorException($db->sql.' --7 '.$db->ErrorMsg());
		}

		$tplid = $this->_data['id'] = $db->Insert_ID();

		if( $this->get_type_dflt() ) {
			// if it's default for a type, unset default flag for all other records with this type
			$query = 'UPDATE '.CMS_DB_PREFIX.self::TABLENAME.' SET type_dflt = 0 WHERE type_id = ? AND type_dflt = 1 AND id != ?';
//			$dbr =
			$db->Execute($query,[$this->get_type_id(),$tplid]);
//			if( !$dbr ) throw new CmsSQLErrorException($db->sql.' -- '.$db->ErrorMsg());
		}

		$t = $this->get_additional_editors();
		if( $t ) {
			$query = 'INSERT INTO '.CMS_DB_PREFIX.self::ADDUSERSTABLE.' (tpl_id,user_id) VALUES(?,?)';
			foreach( $t as $one ) {
				//TODO use statement
				$db->Execute($query,[$tplid,(int)$one]);
			}
		}

		$t = $this->get_designs();
		if( $t ) {
			$query = 'INSERT INTO '.CMS_DB_PREFIX.CmsLayoutCollection::TPLTABLE.' (design_id,tpl_id,tpl_order) VALUES(?,?,?)';
			$i = 1;
			foreach( $t as $one ) {
				$db->Execute($query,[(int)$one,$tplid,$i]);
				++$i;
			}
		}

		$t = $this->get_categories();
		if( $t ) {
			$query = 'INSERT INTO '.CMS_DB_PREFIX.CmsLayoutTemplateCategory::TPLTABLE.' (category_id,tpl_id,tpl_order) VALUES(?,?,?)';
			$i = 1;
			foreach( $t as $one ) {
				$db->Execute($query,[(int)$one,$tplid,$i]);
				++$i;
			}
		}

		$this->_dirty = false;
		TemplateCache::clear_cache();
		audit($tplid,'CMSMS','Template '.$this->get_name().' Created');
	}

   /**
	* Save this template object to the database
	*/
	public function save()
	{
		if( $this->get_id() ) {
			Events::SendEvent( 'Core', 'EditTemplatePre', [ get_class() => &$this ] );
			$this->_update();
			Events::SendEvent( 'Core', 'EditTemplatePost', [ get_class() => &$this ] );
			return;
		}
		Events::SendEvent( 'Core', 'AddTemplatePre', [ get_class() => &$this ] );
		$this->_insert();
		Events::SendEvent( 'Core', 'AddTemplatePost', [ get_class() => &$this ] );
	}

   /**
	* Delete this template object from the database
	*/
	public function delete()
	{
		if( !$this->get_id() ) return;

		Events::SendEvent( 'Core', 'DeleteTemplatePre', [ get_class() => &$this ] );
		$db = CmsApp::get_instance()->GetDb();
		$query = 'DELETE FROM '.CMS_DB_PREFIX.CmsLayoutCollection::TPLTABLE.' WHERE tpl_id = ?';
		$db->Execute($query,[$this->get_id()]);

		$query = 'DELETE FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE id = ?';
		$db->Execute($query,[$this->get_id()]);

		@unlink($this->get_content_filename());

		TemplateCache::clear_cache();
		audit($this->get_id(),'CMSMS','Template '.$this->get_name().' Deleted');
		Events::SendEvent( 'Core', 'DeleteTemplatePost', [ get_class() => &$this ] );
		unset($this->_data['id']);
		$this->_dirty = true;
	}

   /**
	* @ignore
	*/
	private static function get_locks()
	{
		if( !self::$_lock_cache_loaded ) {
			$tmp = CmsLockOperations::get_locks('template');
			if( $tmp ) {
				self::$_lock_cache = [];
				foreach( $tmp as $one ) {
					self::$_lock_cache[$one['oid']] = $one;
				}
			}
			self::$_lock_cache_loaded = true;
		}
		return self::$_lock_cache;
	}

   /**
	* Get any applicable lock for this template object
	*
	* @returns CmsLock
	* @see CmsLock
	*/
	public function get_lock()
	{
		$locks = self::get_locks();
		if( isset($locks[$this->get_id()]) ) return $locks[$this->get_id()];
	}

   /**
	* Test if this object currently has a lock
	*
	* @return bool
	*/
	public function locked()
	{
		$lock = $this->get_lock();
		return is_object($lock);
	}

   /**
	* Tests if any lock associated with this object is expired
	*
	* @return booln
	*/
	public function lock_expired()
	{
		$lock = $this->get_lock();
		if( !is_object($lock) ) return false;
		return $lock->expired();
	}

   /**
	* @ignore
	*/
	private static function _load_from_data($row,$design_list = null) : self
	{
		$ob = new self();
		$ob->_data = $row;
		$fn = $ob->get_content_filename();
		if( is_file($fn) && is_readable($fn) ) {
			$ob->_data['content'] = file_get_contents($fn);
			$ob->_data['modified'] = filemtime($fn);
		}
		if( is_array($design_list) ) $ob->_design_assoc = $design_list;

		self::$_obj_cache[$ob->get_id()] = $ob;
		self::$_name_cache[$ob->get_name()] = $ob->get_id();
		return $ob;
	}

   /**
	* Load a bulk list of templates
	*
	* @param int[] $list Array of integer template ids
	* @param bool $deep Optionally load attached data.
	* @return array Array of CmsLayoutTemplate objects
	*/
	public static function load_bulk($list,$deep = true)
	{
		if( !$list ) return;

		$list2 = [];
		foreach( $list as $one ) {
			if( !is_numeric($one) ) continue;
			$one = (int)$one;
			if( $one < 1 ) continue;
			if( isset(self::$_obj_cache[$one]) ) continue;
			$list2[] = $one;
		}
		$list2 = array_unique($list2);

		if( $list2 ) {
			// get the data and populate the cache.
			$db = CmsApp::get_instance()->GetDb();
			$designs_by_tpl = [];

			if( $deep ) {
				foreach( $list2 as $one ) {
					$designs_by_tpl[$one] = [];
				}
				$dquery = 'SELECT tpl_id,design_id FROM '.CMS_DB_PREFIX.CmsLayoutCollection::TPLTABLE.
				 ' WHERE tpl_id IN ('.implode(',',$list2).') ORDER BY tpl_id,tpl_order';
				$designs_tmp1 = $db->GetArray($dquery);
				foreach( $designs_tmp1 as $row ) {
					$designs_by_tpl[$row['tpl_id']][] = $row['design_id'];
				}
			}

			$query = 'SELECT * FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE id IN ('.implode(',',$list2).')';
			$dbr = $db->GetArray($query);
			if( $dbr ) {
				foreach( $dbr as $row ) {
					self::_load_from_data($row,($designs_by_tpl[$row['id']] ?? null));
				}
			}
		}

		// pull what we can from the cache
		$out = [];
		foreach( $list as $one ) {
			if( !is_numeric($one) ) continue;
			$one = (int)$one;
			if( $one < 1 ) continue;
			if( isset(self::$_obj_cache[$one]) ) $out[] = self::$_obj_cache[$one];
		}
		return $out;
	}

   /**
	* Load a specific template
	*
	* @throws CmsDataNotFoundException
	* @param mixed $a Either an integer template id, or a template name (string)
	* @return CmsLayoutTemplate
	*/
	public static function load($a)
	{
		static $_nn = 0;

		$db = CmsApp::get_instance()->GetDb();
		$row = null;
		if( is_numeric($a) && $a > 0 ) {
			if( isset(self::$_obj_cache[$a]) ) return self::$_obj_cache[$a];
			$query = 'SELECT * FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE id = ?';
			$row = $db->GetRow($query,[(int)$a]);
		}
		else if( is_string($a) && $a !== '' ) {
			if( isset(self::$_name_cache[$a]) ) {
				$n = self::$_name_cache[$a];
				return self::$_obj_cache[$n];
			}

			$query = 'SELECT * FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE name = ?';
			$row = $db->GetRow($query,[$a]);
		}
		if( !$row ) throw new CmsDataNotFoundException('Could not find template identified by '.$a);

		return self::_load_from_data($row);
	}

   /**
	* Get a list of the templates owned by a specific user
	*
	* @throws CmsInvalidDataException
	* @param mixed $a An integer user id, or a string user name
	* @return array Array of integer template ids
	*/
	public static function get_owned_templates($a)
	{
		$n = self::_resolve_user($a);
		if( $n <= 0 ) throw new CmsInvalidDataException('Invalid user specified to get_owned_templates');

		$query = new CmsLayoutTemplateQuery(['u'=>$n]);
		$tmp = $query->GetMatchedTemplateIds();
		return self::load_bulk($tmp);
	}

   /**
	* Perform an advanced query on templates
	*
	* @see CmsLayoutTemplateQuery
	* @param array $params
	*/
	public static function template_query($params)
	{
		$query = new CmsLayoutTemplateQuery($params);
		$out = self::load_bulk($query->GetMatchedTemplateIds());

		if( isset($params['as_list']) && count($out) ) {
			$tmp2 = [];
			foreach( $out as $one ) {
				$tmp2[$one->get_id()] = $one->get_name();
			}
			return $tmp2;
		}
		return $out;
	}

   /**
	* Get a list of the templates that a specific user can edit
	*
	* @param mixed $a An integer userid or a string username or null
	* @throws CmsInvalidDataException
	*/
	public static function get_editable_templates($a)
	{
		$n = self::_resolve_user($a);
		if( $n <= 0 ) throw new CmsInvalidDataException('Invalid user specified to get_owned_templates');

		$db = CmsApp::get_instance()->GetDb();
		$query = 'SELECT id FROM '.CMS_DB_PREFIX.self::TABLENAME;
		$parms = [];
		if( !UserOperations::get_instance()->CheckPermission($n,'Modify Templates') ) {
			$query .= ' WHERE owner_id = ?';
			$parms[] = $n;
		}
		$tmp1 = $db->GetCol($query,$parms);

		$query = 'SELECT tpl_id FROM '.CMS_DB_PREFIX.self::ADDUSERSTABLE.' WHERE user_id = ?';
		$tmp2 = $db->GetCol($query,[$n]);

		if( is_array($tmp1) && is_array($tmp2) ) {
			$tmp = array_merge($tmp1,$tmp2);
		} else if( is_array($tmp1) ) {
			$tmp = $tmp1;
		} else if( is_array($tmp2) ) {
			$tmp = $tmp2;
		}

		if( $tmp ) {
			$tmp = array_unique($tmp);
			if( $tmp ) return self::load_bulk($tmp);
		}
	}

   /**
	* Test if the user specified can edit the specified template
	* This is a convenience method that loads the template, and then tests if the specified user has edit ability to it.
	*
	* @param mixed $tpl An integer template id, or a string template name
	* @param mixed $userid An integer user id, or a string user name.  If no userid is specified the currently logged in userid is used
	* @return bool
	* @see CmsLayoutTemplate::load()
	*/
	public static function user_can_edit($tpl,$userid = null)
	{
		if( is_null($userid) ) $userid = get_userid();

		// get the template
		// scan owner/additional uers
		$obj = self::load($tpl);
		if( $obj->get_owner_id() == $userid ) return true;

		// get the user groups
		$addt_users = $obj->get_additional_editors();
		if( $addt_users ) {
			if( in_array($userid,$addt_users) ) return true;

			$grouplist = UserOperations::get_instance()->GetMemberGroups();
			if( $grouplist ) {
				foreach( $addt_users as $one ) {
					if( $one < 0 && in_array($one*-1,$grouplist) ) return true;
				}
			}
		}

		return false;
	}

   /**
	* Create a new template of the specific type
	*
	* @throws CmsInvalidDataException
	* @param mixed $t A CmsLayoutTemplateType object, An integer template type id, or a string template type identifier
	* @return CmsLayoutTemplate
	*/
	public static function &create_by_type($t)
	{
		$t2 = null;
		if( is_int($t) || is_string($t) ) {
			$t2 = CmsLayoutTemplateType::load($t);
		}
		else if( $t instanceof CmsLayoutTemplateType ) {
			$t2 = $t;
		}

		if( !$t2 ) throw new CmsInvalidDataException('Invalid data passed to CmsLayoutTemplate::create_by_type()');

		return $t2->create_new_template();
	}

   /**
	* Load the default template of a specified type
	*
	* @throws CmsInvalidDataException
	* @throws CmsDataNotFoundException
	* @param mixed $t A CmsLayoutTemplateType object, An integer template type id, or a string template type identifier
	* @return CmsLayoutTemplate
	*/
	public static function load_dflt_by_type($t)
	{
		$t2 = null;
		if( is_int($t) || is_string($t) ) {
			$t2 = CmsLayoutTemplateType::load($t);
		}
		else if( $t instanceof CmsLayoutTemplateType ) {
			$t2 = $t;
		}

		if( !$t2 ) throw new CmsInvalidDataException('Invalid data passed to CmsLayoutTemplate::;load_dflt_by_type()');

		// search our preloaded template first
		if( self::$_obj_cache ) {
			foreach( self::$_obj_cache as $tpl ) {
				if( $tpl->get_type_id() == $t2->get_id() && $tpl->get_type_dflt() ) return $tpl;
			}
		}

		$db = CmsApp::get_instance()->GetDb();
		$query = 'SELECT * FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE type_id = ? AND type_dflt = ?';
		$row = $db->GetRow($query,[$t2->get_id(),1]);
		if( !$row ) throw new CmsDataNotFoundException('Could not find default CmsLayoutTemplate row for type '.$t2->get_name());

		return self::_load_from_data($row);
	}

   /**
	* Load all templates of a specific type
	*
	* @throws CmsDataNotFoundException
	* @param CmsLayoutTemplateType $type
	* @return array Array of CmsLayoutTemplate objects, or null
	*/
	public static function load_all_by_type(CmsLayoutTemplateType $type)
	{
		$db = CmsApp::get_instance()->GetDb();
		$query = 'SELECT * FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE type_id = ?';
		$tmp = $db->GetArray($query,[$type->get_id()]);
		if( !$tmp ) return;

		$out = [];
		foreach( $tmp as $row ) {
			$out[] = self::_load_from_data($row);
		}
		return $out;
	}

   /**
	* Process a template through smarty given the template name
	* @param string $name
	* @return string
	*/
	public static function process_by_name($name)
	{
		$smarty = Smarty::get_instance();
		return $smarty->fetch('cms_template:name='.$this->get_name());
	}

   /**
	* Process the default template of a specified type
	*
	* @param mixed $t A CmsLayoutTemplateType object, an integer template type id, or a string template type identifier
	* @return string
	*/
	public static function process_dflt($t)
	{
		$smarty = Smarty::get_instance();
		$tpl = self::load_dflt_by_type($t);
		return $smarty->fetch('cms_template:id='.$tpl->get_id());
	}

   /**
	* Get the ids of all loaded templates
	*
	* @return mixed Array of integer template ids or null
	*/
	public static function get_loaded_templates()
	{
		if( self::$_obj_cache && count(self::$_obj_cache) ) return array_keys(self::$_obj_cache);
	}

   /**
	* Generate a unique name for a template
	*
	* @param string $prototype A prototype template name
	* @param string $prefix An optional name prefix.
	* @return string
	* @throws CmsInvalidDataException
	* @throws CmsLogicException
	*/
	public static function generate_unique_name($prototype,$prefix = null)
	{
		if( !$prototype ) throw new CmsInvalidDataException('Prototype name cannot be empty');
		$db = CmsApp::get_instance()->GetDb();
		$query = 'SELECT id FROM '.CMS_DB_PREFIX.self::TABLENAME.' WHERE name = ?';
		for( $i = 0; $i < 25; $i++ ) {
			$name = $prefix.$prototype;
			if( $i == 0 ) $name = $prototype;
			if( $i > 1 ) $name = $prefix.$prototype.' '.$i;
			$tmp = $db->GetOne($query,[$name]);
			if( !$tmp ) return $name;
		}
		throw new CmsLogicException('Could not generate a template name for '.$prototype);
	}

   /**
	* Return the template type object for this template.
	*
	* @return mixed CmsLayoutTemplateType or null
	* @since 2.2
	*/
	public function &get_type()
	{
		$obj = null;
		$tid = $this->get_type_id();
		if( $tid > 0 ) $obj = CmsLayoutTemplateType::load($this->get_type_id());
		return $obj;
	}

   /**
	* Get the usage string (if any) for this template.
	*
	* @return string A sample usage string for this template.
	* @since 2.2
	*/
	public function get_usage_string()
	{
		$type = $this->get_type();
		return $type->get_usage_string($this->get_name());
	}

   /**
	* Get the filename that will be used to read template contents from file.
	*
	* @since 2.2
	* @return string
	*/
	public function get_content_filename()
	{
		$config = cms_config::get_instance();
		$name = munge_string_to_url($this->get_name()).'.'.$this->get_id().'.tpl';
		return cms_join_path($config['assets_path'],'templates',$name);
	}

   /**
	* Does this template have an associated file.
	*
	* @since 2.2
	* @return bool
	*/
	public function has_content_file()
	{
		$fn = $this->get_content_filename();
		return ( is_file($fn) && is_readable($fn) );
	}
} // class
