<?php
# base content class
# Copyright (C) 2004-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
# Thanks to Ted Kulp and all other contributors from the CMSMS Development Team.
# This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# BUT withOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.

namespace CMSMS {

use cms_config;
use cms_route_manager;
use cms_siteprefs;
use cms_utils;
use CmsApp;
use CmsContentException;
use CmsInvalidDataException;
use CMSMS\AdminUtils;
use CMSMS\ContentBase;
use CMSMS\ContentOperations;
use CMSMS\FormUtils;
use CMSMS\GroupOperations;
use CMSMS\internal\content_assistant;
use CMSMS\internal\global_cache;
use CMSMS\UserOperations;
use CmsRoute;
use const CMS_DB_PREFIX;
use const CMS_ROOT_URL;
use function check_permission;
use function cms_htmlentities;
use function cms_join_path;
use function create_file_dropdown;
use function debug_buffer;
use function endswith;
use function get_userid;
use function lang;
use function munge_string_to_url;

/**
 * Base level content object.
 *
 * This is the base level content class.  It is an abstract object and cannot be instantiated directly.
 * All content pages in the database must be derived from this class.
 *
 * @since		0.8
 * @package		CMS
 */
abstract class ContentBase
{
	/**
	 * @ignore
	 */
	const TAB_MAIN = 'aa_main_tab__';

	/**
	 * @ignore
	 */
	const TAB_NAV = 'zz_1nav_tab__';

	/**
	 * @ignore
	 */
	const TAB_LOGIC = 'zz_2logic_tab__';

	/**
	 * @ignore
	 */
	const TAB_OPTIONS = 'zz_3options_tab__';

	/**
	 * @ignore
	 */
	const TAB_PERMS = 'zz_4perms_tab__';

	/**
	 * The unique ID identifier of the element
	 * Integer
	 *
	 * @ignore
	 */
	protected $mId = -1;

	/**
	 * The name of the element (like a filename)
	 * String
	 *
	 * @internal
	 */
	protected $mName = '';

	/**
	 * The owner of the content
	 * Integer
	 *
	 * @internal
	 */
	protected $mOwner = -1;

	/**
	 * The properties part of the content. This is an object of the good type.
	 * It should contain all treatments specific to this type of content
	 *
	 * @internal
	 */
	protected $_props;

	/**
	 * The ID of the parent, 0 if none
	 * Integer
	 */
	protected $mParentId = -2;

	/**
	 * This is used too often to not be part of the base class
	 *
	 * @internal
	 */
	protected $mTemplateId = -1;

	/**
	 * The item order of the content in his level
	 * Integer
	 */
	protected $mItemOrder = -1;

	/**
	 * The metadata (head tags) for this content
	 *
	 * @internal
	 */
	protected $mMetadata = '';

	/**
	 * @internal
	 */
	protected $mTitleAttribute = '';

	/**
	 * @internal
	 */
	protected $mAccessKey = '';

	/**
	 * @internal
	 */
	protected $mTabIndex = '';

	/**
	 * The full hierarchy of the content
	 * String of the form : '1.4.3'
	 *
	 * @internal
	 */
	protected $mHierarchy = '';

	/**
	 * The full hierarchy of the content ids
	 * String of the form : '1.4.3'
	 *
	 * @internal
	 */
	protected $mIdHierarchy = '';

	/**
	 * The full path through the hierarchy
	 * String of the form : parent/parent/child
	 *
	 * @internal
	 */
	protected $mHierarchyPath = '';

	/**
	 * What should be displayed in a menu
	 *
	 * @internal
	 */
	protected $mMenuText = '';

	/**
	 * Is the content active ?
	 * Integer : 0 / 1
	 *
	 * @internal
	 */
	protected $mActive = false;

	/**
	 * Alias of the content
	 *
	 * @internal
	 */
	protected $mAlias;

	/**
	 * Old content-alias
	 *
	 * @internal
	 */
	protected $mOldAlias;

	/**
	 * Is this page cachable?
	 *
	 * @internal
	 */
	protected $mCachable = false;

	/**
	 * Secure access to this page?
	 *
	 * @internal
	 */
	protected $mSecure = false;

	/**
	 * URL
	 *
	 * @internal
	 */
	protected $mURL = '';

	/**
	 * Should it show up in the menu?
	 *
	 * @internal
	 */
	protected $mShowInMenu = false;

	/**
	 * Is this page the default?
	 *
	 * @internal
	 */
	protected $mDefaultContent = false;

	/**
	 * Last user to modify this content
	 *
	 * @internal
	 */
	protected $mLastModifiedBy = -1;

	/**
	 * Creation date
	 *
	 * @internal
	 */
	protected $mCreationDate = '';

	/**
	 * Modification date
	 *
	 * @internal
	 */
	protected $mModifiedDate = '';

	/**
	 * Additional editors array
	 *
	 * @internal
	 */
	protected $mAdditionalEditors;

	/**
	 * @internal
	 */
	private $_prop_defaults;

	/**
	 * state or meta information
	 *
	 * @internal
	 */
	private $_properties = [];

	/**
	 * @internal
	 */
	private $_editable_properties;

	/************************************************************************/
	/* Construction related													*/
	/************************************************************************/

	/**
	 * Generic constructor. Runs the SetInitialValues function.
	 */
	public function __construct()
	{
		$this->SetInitialValues();
		$this->SetProperties();
	}

	/**
	 * @ignore
	 */
	public function __clone()
	{
		$this->mId = -1;
		$this->mItemOrder = -1;
		$this->mURL = '';
		$this->mAlias = '';
	}

	/**
	 * Set some sane initial values of this object
	 *
	 * @abstract
	 * @internal
	 */
	protected function SetInitialValues()
	{
	}

	/**
	 * Subclasses should override this to set their property types after calling back here.
	 * NOTE this method is a significant contributor to the duration of each frontend request.
	 * Benchmark reported at https://steemit.com/php/@crell/php-use-associative-arrays-basically-never
	 * recommends (in spite of the URL) against stdClass data-storage in this sort of context.
	 * And arrays have been benchmarked here, they're faster.
	 *
	 * @abstract
	 * @internal
	 * @param array undeclared since 2.3 optional array of properties to be
	 * excluded from the initial properties. If present, each member an array
	 * [0] = name, [1] = value to return if the property is sought
	 */
	protected function SetProperties()
	{
		$defaults = [
			'title'=>[1,self::TAB_MAIN,1],

			'menutext'=>[1,self::TAB_NAV,1],
			'parent'=>[2,self::TAB_NAV,1],
			'page_url'=>[3,self::TAB_NAV],
			'showinmenu'=>[4,self::TAB_NAV],
			'titleattribute'=>[5,self::TAB_NAV],
			'accesskey'=>[6,self::TAB_NAV],
			'tabindex'=>[7,self::TAB_NAV],
			'target'=>[8,self::TAB_NAV],

			'alias'=>[1,self::TAB_OPTIONS],
			'active'=>[2,self::TAB_OPTIONS],
			// priority 3 is used by some subclasses
			'secure'=>[3,self::TAB_OPTIONS], //deprecated property since 2.3
			'cachable'=>[4,self::TAB_OPTIONS],
			'image'=>[5,self::TAB_OPTIONS],
			'thumbnail'=>[6,self::TAB_OPTIONS],
			'extra1'=>[7,self::TAB_OPTIONS],
			'extra2'=>[8,self::TAB_OPTIONS],
			'extra3'=>[9,self::TAB_OPTIONS],

			'owner'=>[1,self::TAB_PERMS],
			'additionaleditors'=>[2,self::TAB_PERMS],
		];

		$except = func_get_args(); //prevent subclass API incompatibility
		if( $except ) {
			$except = $except[0];
			$tmp = array_column($except, 0);
			$nonames = array_flip($tmp);
		    $defaults = array_diff_key($defaults, $nonames);
		}

		foreach( $defaults as $name => &$one ) {
			$this->_properties[] = [
				'tab' => $one[1],
				'priority' => $one[0],
				'name' => $name,
				'required' => !empty($one[2]),
				'basic' => false,
			];
		}
		unset($one);

		if( $except ) {
		    $this->_prop_defaults = [];
			foreach( $nonames as $name => &$one ) {
				$this->_prop_defaults[$name] = $except[$one][1] ?? '';
			}
			unset($one);
		}
	}

	/************************************************************************/
	/* Functions giving access to needed elements of the content			*/
	/************************************************************************/

	/**
	 * Return the page ID
	 */
	public function Id()
	{
		return $this->mId;
	}

	/**
	 * Set the numeric id of the content item
	 *
	 * @param int Integer id
	 * @access private
	 * @internal
	 */
	public function SetId($id)
	{
		$this->mId = $id;
	}

	/**
	 * Return a friendly name for this content type
	 *
	 * Normally the content type returns a string representing the name of the content type translated into the users current language
	 *
	 * @abstract
	 * @return string
	 */
	abstract public function FriendlyName();

	/**
	 * Return the page name
	 *
	 * @return string
	 */
	public function Name()
	{
		return $this->mName;
	}

	/**
	 * Set the the page name
	 *
	 * @param string $name The name.
	 */
	public function SetName($name)
	{
		$this->mName = $name;
	}

	/**
	 * Return the page alias
	 *
	 * @return string
	 */
	public function Alias()
	{
		return $this->mAlias;
	}

	/**
	 * Return the page type
	 *
	 * @return string
	 */
	public function Type()
	{
		$c = get_class($this);
		$p = strrpos($c, '\\');
		return ($p !== false) ? strtolower(substr($c, $p+1)) : strtolower($c);
	}

	/**
	 * Return the owner's user id
	 *
	 * @return int
	 */
	public function Owner()
	{
		return $this->mOwner;
	}

	/**
	 * Set the page owner.
	 * No validation is performed.
	 *
	 * @param int $owner Owner's user id
	 */
	public function SetOwner($owner)
	{
		$owner = (int)$owner;
		if( $owner <= 0 ) return;
		$this->mOwner = $owner;
	}

	/**
	 * Return the page metadata
	 *
	 * @return string
	 */
	public function Metadata()
	{
		return $this->mMetadata;
	}

	/**
	 * Return whether this content object handles the alias
	 *
	 * @abstract
	 * @return bool default is false.
	 */
	public function HandlesAlias()
	{
		return false;
	}

	/**
	 * Set the page metadata
	 *
	 * @param string $metadata The metadata
	 */
	public function SetMetadata($metadata)
	{
		$this->mMetadata = $metadata;
	}

	/**
	 * Return the page tabindex value
	 *
	 * @return int
	 */
	public function TabIndex()
	{
		return $this->mTabIndex;
	}

	/**
	 * Set the page tabindex value
	 *
	 * @param int $tabindex tab index
	 */
	public function SetTabIndex($tabindex)
	{
		$this->mTabIndex = $tabindex;
	}

	/**
	 * Return the page title attribute
	 *
	 * @return string
	 */
	public function TitleAttribute()
	{
		return $this->mTitleAttribute;
	}

	/**
	 * Return the creation date of this content object.
	 *
	 * @return int Unix Timestamp of the creation date
	 */
	public function GetCreationDate()
	{
		return strtotime($this->mCreationDate);
	}

	/**
	 * Return the date of the last modification of this content object.
	 *
	 * @return int Unix Timestamp of the modification date.
	 */
	public function GetModifiedDate()
	{
		return strtotime($this->mModifiedDate);
	}

	/**
	 * Set the title attribute of the page
	 *
	 * The title attribue can be used in navigations to set the "title=" attribute of a link
	 * some menu templates may ignore this.
	 *
	 * @param string $titleattribute The title attribute
	 */
	public function SetTitleAttribute($titleattribute)
	{
		$this->mTitleAttribute = $titleattribute;
	}

	/**
	 * Get the access key (for accessibility) for this page.
	 *
	 * @see http://www.w3schools.com/tags/att_global_accesskey.asp
	 * @return string
	 */
	public function AccessKey()
	{
		return $this->mAccessKey;
	}

	/**
	 * Set the access key (for accessibility) for this page
	 *
	 * @see http://www.w3schools.com/tags/att_global_accesskey.asp
	 * @param string $accesskey
	 */
	public function SetAccessKey($accesskey)
	{
		$this->mAccessKey = $accesskey;
	}

	/**
	 * Return the id of this page's parent.
	 * The parent id may be -2 to indicate a new page.
	 * A parent id value of -1 indicates that the page has no parent.
	 * oterwise a positive integer is returned.
	 *
	 * @return int
	 */
	public function ParentId()
	{
		return $this->mParentId;
	}

	/**
	 * Set the parent of this page
	 *
	 * @param int $parentid The numeric page parent id.  Use -1 for no parent.
	 */
	public function SetParentId($parentid)
	{
		$parentid = (int) $parentid;
		if( $parentid < 1 ) $parentid = -1;
		$this->mParentId = $parentid;
	}

	/**
	 * Return the id of the template associated with this content page.
	 *
	 * @return int.
	 */
	public function TemplateId()
	{
		return $this->mTemplateId;
	}

	/**
     * Return a smarty resource string for the template assigned to this page.
     *
     * @since 2.3
     * @abstract
     * @return string
     */
    public function TemplateResource() : string
    {
        die('this method must be overridden for displayable content pages');
    }

	/**
	 * Set the id of the template associated with this content page.
	 *
	 * @param int $templateid
	 */
	public function SetTemplateId($templateid)
	{
		$templateid = (int)$templateid;
		if( $templateid > 0 ) $this->mTemplateId = $templateid;
	}

	/**
	 * Return the itemOrder
	 * That is used to specify the order of this page among its peers
	 *
	 * @return int
	 */
	public function ItemOrder()
	{
		return $this->mItemOrder;
	}

	/**
	 * Set the page itemOrder
	 * That is used to specify the order of this page within the parent.
	 * A value of -1 indicates that a new item order will be calculated on save.
	 * Otherwise a positive integer is expected.
	 *
	 * @internal
	 * @param int $itemorder
	 */
	public function SetItemOrder($itemorder)
	{
		$itemorder = (int)$itemorder;
		if( $itemorder > 0 || $itemorder == -1 ) $this->mItemOrder = $itemorder;
	}

	/**
	 * Return the hierarchy of the current page.
	 * A string like #.##.## indicating the path to this page and its order
	 * This value uses the item order when calculating the output e.g. 3.3.3
	 * to indicate the third grandchild of the third child of the third root page.
	 *
	 * @return string
	 */
	public function Hierarchy()
	{
		$contentops = ContentOperations::get_instance();
		return $contentops->CreateFriendlyHierarchyPosition($this->mHierarchy);
	}

	/**
	 * Set the hierarchy
	 *
	 * @internal
	 * @param string $hierarchy
	 */
	public function SetHierarchy($hierarchy)
	{
		$this->mHierarchy = $hierarchy;
	}

	/**
	 * Return the id Hierarchy.
	 * A string like #.##.## indicating the path to the page and its order
	 * This property uses the id's of pages when calculating the output i.e: 21.5.17
	 * to indicate that page id 17 is the child of page with id 5 which is in turn the
	 * child of the page with id 21
	 *
	 * @return string
	 */
	final public function IdHierarchy()
	{
		return $this->mIdHierarchy;
	}

	/**
	 * Return the hierarchy path.
	 * Similar to the Hierarchy and IdHierarchy this string uses page aliases
	 * and outputs a string like root_alias/parent_alias/page_alias
	 *
	 * @return string
	 */
	final public function HierarchyPath()
	{
		return $this->mHierarchyPath;
	}

	/**
	 * Return the page-active state
	 *
	 * @return bool
	 */
	public function Active()
	{
		return $this->mActive;
	}

	/**
	 * Set this page as active
	 *
	 * @param bool $active
	 */
	public function SetActive($active)
	{
		$this->mActive = (bool)$active;
	}

	/**
	 * Return whether preview should be available for this content type
	 *
	 * @abstract
	 * @return bool
	 */
	public function HasPreview()
	{
		return false;
	}

	/**
	 * Return whether this content item should (by default) be shown in navigation menus.
	 *
	 * @abstract
	 * @return bool
	 */
	public function ShowInMenu()
	{
		return $this->mShowInMenu;
	}

	/**
	 * Set whether this page should be (by default) shown in menus
	 *
	 * @param bool $showinmenu
	 */
	public function SetShowInMenu($showinmenu)
	{
		$this->mShowInMenu = (bool) $showinmenu;
	}

	/**
	 * Return whether the page is the default.
	 * The default page is the one that is displayed when no alias or pageid is specified in the route
	 * Only one content page can be the default.
	 *
	 * @return bool
	 */
	final public function DefaultContent()
	{
		if( !$this->IsDefaultPossible() ) return false;
		return $this->mDefaultContent;
	}

	/**
	 * Set whether this page should be considered the default.
	 * Note: does not modify the flags for any other content page.
	 *
	 * @param bool $defaultcontent
	 */
	public function SetDefaultContent($defaultcontent)
	{
		if( $this->IsDefaultPossible() ) {
			$this->mDefaultContent = (bool) $defaultcontent;
		}
	}

	/**
	 * Return whether this page is cachable.
	 * Cachable pages (when enabled in global settings) are cached by the browser
	 * (also server side caching of HTML output may be enabled)
	 *
	 * @return bool
	 */
	public function Cachable()
	{
		return $this->mCachable;
	}

	/**
	 * Set whether this page is cachable
	 *
	 * @param bool $cachable
	 */
	public function SetCachable($cachable)
	{
		$this->mCachable = (bool) $cachable;
	}

	/**
	 * Return whether this page should be accessed via a secure protocol.
	 * The secure flag affects whether the ssl protocol and appropriate config entries are used when generating urls to this page.
	 * @deprecated since 2.3
	 *
	 * @return bool
	 */
	public function Secure()
	{
		return $this->mSecure;
	}

	/**
	 * Set whether this page should be accessed via a secure protocol.
	 * The secure flag affects whether the ssl protocol and appropriate config entries are used when generating urls to this page.
	 * @deprecated since 2.3
	 *
	 * @param bool $secure
	 */
	public function SetSecure($secure)
	{
		$this->mSecure = (bool)$secure;
	}


	/**
	 * Return the page URL (if any) associated with this content page.
	 * The page url is not the complete URL to the content page, but merely the 'stub' or 'slug' appended after the root url when accessing the site
	 * If the page is specified as the default page then the "page url" will be ignored.
	 * Some content types do not support page urls.
	 *
	 * @return string
	 */
	public function URL()
	{
		return $this->mURL;
	}

	/**
	 * Set the page URL associated with this content page.
	 * Verbatim, no immediate validation.
	 * The URL should be relative to the root URL i.e: /some/path/to/the/page
	 * Note: some content types do not support page URLs.
	 *
	 * @param string $url May be empty.
	 */
	public function SetURL($url)
	{
		$this->mURL = $url;
	}

	/**
	 * Return the integer id of the admin user that last modified this content item.
	 *
	 * @return int
	 */
	public function LastModifiedBy()
	{
		return $this->mLastModifiedBy;
	}

	/**
	 * Set the last modified date for this item
	 *
	 * @param int $lastmodifiedby
	 */
	public function SetLastModifiedBy($lastmodifiedby)
	{
		$lastmodifiedby = (int)$lastmodifiedby;
		if( $lastmodifiedby > 0 ) $this->mLastModifiedBy = $lastmodifiedby;
	}

	/**
	 * Return whether this content type requires an alias.
	 * Some content types that are not directly navigable do not require page aliases.
	 *
	 * @abstract
	 * @return bool
	 */
	public function RequiresAlias()
	{
		return true;
	}

	/**
	 * Return whether this content type is viewable (i.e: can be rendered).
	 * Some content types (like redirection links) are not viewable.
	 *
	 * @abstract
	 * @return bool Default is True
	 */
	public function IsViewable()
	{
		return true;
	}

	/**
	 * Return whether the current user is permitted to view this content page.
	 *
	 * @since 1.11.12
	 * @abstract
	 * @return boolean
	 */
	public function IsPermitted()
	{
		return true;
	}

	/**
	 * Return whether this content type is searchable.
	 *
	 * Searchable pages can be indexed by the search module.
	 *
	 * This function by default uses a combination of other abstract methods to
	 * determine whether the page is searchable but extended content types can override this.
	 *
	 * @since 2.0
	 * @return bool
	 */
	public function IsSearchable()
	{
		if( !$this->isPermitted() || !$this->IsViewable() || !$this->HasTemplate() || $this->IsSystemPage() ) return false;
		return $this->HasSearchableContent();
	}

	/**
	 * Return whether this content type may have content that can be used by a search module.
	 *
	 * Content types should override this method if they are special purpose
	 * content types which cannot support searchable content in any way.
	 * Example content types are ErrorPage, Section Header and Separator.
	 *
	 * @since 2.0
	 * @abstract
	 * @return bool
	 */
	protected function HasSearchableContent()
	{
		return true;
	}

	/**
	 * Return whether this content type can be the default page for a CMSMS website.
	 *
	 * The content editor module may adjust its user interface to not allow
	 * setting pages that return false for this method as the default page.
	 *
	 * @abstract
	 * @returns bool Default is false
	 */
	public function IsDefaultPossible()
	{
		return false;
	}

	/**
	 * Set the page alias for this content page.
	 * If an empty alias is supplied, and depending upon the doAutoAliasIfEnabled flag,
	 * and config entries a suitable alias may be calculated from other data in the page object.
	 * This method relies on the menutext and the name of the content page already being set.
	 *
	 * @param string $alias The alias
	 * @param bool $doAutoAliasIfEnabled Whether an alias should be calculated or not.
	 */
	public function SetAlias($alias = null, $doAutoAliasIfEnabled = true)
	{
		$contentops = ContentOperations::get_instance();
		$config = cms_config::get_instance();
		if( $alias === '' && $doAutoAliasIfEnabled && $config['auto_alias_content'] ) {
			$alias = trim($this->mMenuText);
			if( $alias === '' ) $alias = trim($this->mName);

			// auto generate an alias
			$tolower = true;
			$alias = munge_string_to_url($alias, $tolower);
			$res = $contentops->CheckAliasValid($alias);
			if( !$res ) {
				$alias = 'p'.$alias;
				$res = $contentops->CheckAliasValid($alias);
				if( !$res ) throw new CmsContentException(lang('invalidalias2'));
			}
		}

		if( $alias ) {
			// Make sure auto-generated new alias is not already in use on a different page, if it does, add "-2" to the alias

			// make sure we start with a valid alias.
			$res = $contentops->CheckAliasValid($alias);
			if( !$res ) throw new CmsContentException(lang('invalidalias2'));

			// now auto-increment the alias.
			$prefix = $alias;
			$num = 1;
			if( preg_match('/(.*)-([0-9]*)$/',$alias,$matches) ) {
				$prefix = $matches[1];
				$num = (int) $matches[2];
			}
			$test = $alias;
			do {
				if( !$contentops->CheckAliasUsed($test,$this->Id()) ) {
					$alias = $test;
					break;
				}
				$num++;
				$test = $prefix.'-'.$num;
			} while( $num < 100 );
			if( $num >= 100 ) throw new CmsContentException(lang('aliasalreadyused'));
		}

		$this->mAlias = $alias;
		global_cache::clear('content_quicklist');
		global_cache::clear('content_tree');
		global_cache::clear('content_flatlist');
	}

	/**
	 * Return the menu text for this content page.
	 * The MenuText is by default used as the text portion of a navigation link.
	 *
	 * @return string
	 */
	public function MenuText()
	{
		return $this->mMenuText;
	}

	/**
	 * Set the menu text for this content page
	 *
	 * @param string $menutext
	 */
	public function SetMenuText($menutext)
	{
		$this->mMenuText = $menutext;
	}

	/**
	 * Return the number of immediate child content items of this content item.
	 *
	 * @return int
	 */
	public function ChildCount()
	{
		$hm = CmsApp::get_instance()->GetHierarchyManager();
		$node = $hm->find_by_tag('id',$this->mId);
		if( $node ) return $node->count_children();
	}

	/**
	 * Content page objects only directly store enough information to build a basic navigation from content objects.
	 * This method will return all of the other parts of the content object.
	 *
	 * Note: this method does not directly load properties.
	 *
	 * @return array
	 */
	public function Properties()
	{
		return $this->_props;
	}

	/**
	 * Test whether this content page has the named property.
	 * Properties will be loaded from the database if necessary.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function HasProperty($name)
	{
		if( !$name ) return false;
		if( !is_array($this->_props) ) $this->_load_properties();
		if( !is_array($this->_props) ) return false;
		return isset($this->_props[$name]);
	}

	/**
	 * Get the value for the named property.
	 * Properties will be loaded from the database if necessary.
	 *
	 * @param string $name
	 * @return mixed String value, or null if the property does not exist.
	 */
	public function GetPropertyValue($name)
	{
		if( $this->HasProperty($name) ) return $this->_props[$name];
	}

	/**
	 * @ignore
	 */
	private function _load_properties() : bool
	{
		if( $this->mId <= 0 ) return false;

		$this->_props = [];
		$db = CmsApp::get_instance()->GetDb();
		$query = 'SELECT prop_name,content FROM '.CMS_DB_PREFIX.'content_props WHERE content_id = ?';
		$dbr = $db->GetAssoc($query,[(int)$this->mId]);
		if( $dbr !== false ) {
			$this->_props = $dbr;
			return true;
		}
		return false;
	}

	/**
	 * @ignore
	 */
	private function _save_properties() : bool
	{
		if( $this->mId <= 0 ) return false;
		if( !$this->_props ) return false;

		$db = CmsApp::get_instance()->GetDb();
		$query = 'SELECT prop_name FROM '.CMS_DB_PREFIX.'content_props WHERE content_id = ?';
		$gotprops = $db->GetCol($query,[$this->mId]);

		$now = $db->DbTimeStamp(time());
		$iquery = 'INSERT INTO '.CMS_DB_PREFIX."content_props
(content_id,type,prop_name,content,create_date,modified_date)
VALUES (?,?,?,?,$now,$now)";
		$uquery = 'UPDATE '.CMS_DB_PREFIX."content_props SET content = ?, modified_date = $now WHERE content_id = ? AND prop_name = ?";

		foreach( $this->_props as $key => $value ) {
			if( in_array($key,$gotprops) ) {
				// update (NB unreliable return value)
				$dbr = $db->Execute($uquery,[$value,$this->mId,$key]);
			}
			else {
				// insert
				$dbr = $db->Execute($iquery,[$this->mId,'string',$key,$value]);
				if( $dbr === false ) return false;
			}
		}
		return true;
	}

	/**
	 * Set the value of a the named property.
	 * This method will load properties for this content page if necessary.
	 *
	 * @param string $name The property name
	 * @param string $value The property value.
	 */
	public function SetPropertyValue($name, $value)
	{
		if( !is_array($this->_props) ) $this->_load_properties();
		$this->_props[$name] = $value;
	}

	/**
	 * Set the value of a the named property.
	 * This method will not load properties
	 *
	 * @param string $name The property name
	 * @param string $value The property value.
	 */
	public function SetPropertyValueNoLoad($name, $value)
	{
		if( !is_array($this->_props) ) $this->_props = [];
		$this->_props[$name] = $value;
	}

	/**
	 * An abstract method that extended content types can use to indicate whether or not they want children.
	 * Some content types, such as a separator do not want to have any children.
	 *
	 * @since 0.11
	 * @abstract
	 * @return bool Default true
	 */
	public function WantsChildren()
	{
		return true;
	}

	/**
	 * An abstract method that indicates that this content type is navigable and generates a useful URL.
	 *
	 * @abstract
	 * @return bool Default true
	 */
	public function HasUsableLink()
	{
		return true;
	}

	/**
	 * An abstract method indicating whether the content type is copyable.
	 *
	 * @abstract
	 * @return bool default false
	 */
	public function IsCopyable()
	{
		return false;
	}

	/**
	 * An abstract method to indicate whether this content type generates a system page.
	 * System pages are used to handle things like 404 errors etc.
	 *
	 * @abstract
	 * @return bool default false
	 */
	public function IsSystemPage()
	{
		return false;
	}

	/**
	 * Return whether ths page type uses a template.
	 * i.e: some content types like sectionheader and separator do not.
	 *
	 * @since 2.0
	 * @abstract
	 * @return bool default false
	 */
	public function HasTemplate()
	{
		return false;
	}

	/************************************************************************/
	/* The rest																*/
	/************************************************************************/

	/**
	 * Load the content of the object from an array.
	 * This method modifies the current object.
	 *
	 * There is no check on the data provided, because this is the job of
	 * ValidateData
	 *
	 * Upon failure the object comes back to initial values and returns false
	 *
	 * @param array $data Data as loaded from the database
	 * @param bool  $loadProperties Optionally load content properties at the same time.
	 * @returns	bool
	 */
	public function LoadFromData($data, $loadProperties = false)
	{
		$this->mAccessKey      = $data['accesskey'];
		$this->mActive         = ($data['active'] == 1);
		$this->mCachable       = ($data['cachable'] == 1);
		$this->mId             = $data['content_id'];
		$this->mName           = $data['content_name'];
		$this->mAlias          = $data['content_alias'];
		$this->mOldAlias       = $data['content_alias'];
		$this->mCreationDate   = $data['create_date'];
		$this->mDefaultContent = ($data['default_content'] == 1);
		$this->mHierarchy      = $data['hierarchy'];
		$this->mHierarchyPath  = $data['hierarchy_path'];
		$this->mIdHierarchy    = $data['id_hierarchy'];
		$this->mItemOrder      = $data['item_order'];
		$this->mLastModifiedBy = $data['last_modified_by'];
		$this->mMenuText       = $data['menu_text'];
		$this->mMetadata       = $data['metadata'];
		$this->mModifiedDate   = $data['modified_date'];
		$this->mOwner          = $data['owner_id'];
		$this->mURL            = $data['page_url'] ?? '';
		$this->mParentId       = $data['parent_id'];
		$this->mSecure         = $data['secure'] ?? false; //deprecated since 2.3
		$this->mShowInMenu     = ($data['show_in_menu'] == 1);
		$this->mTabIndex       = $data['tabindex'];
		$this->mTemplateId     = $data['template_id'];
		$this->mTitleAttribute = $data['titleattribute'];
//		N/A                    = func($data['wants_children']);

		$result = true;
		if( $loadProperties ) {
			$this->_load_properties();
			if( !is_array($this->_props) ) {
				$result = false;
				$this->SetInitialValues();
			}
		}

		$this->Load();
		return $result;
	}

	/**
	 * Convert the current object to an array.
	 *
	 * This can be considered a simple DTO (Data Transfer Object)
	 *
	 * @since 2.0
	 * @author Robert Campbell
	 * @return array
	 */
	public function ToData()
	{
		$ret = [];
		$ret['accesskey'] = $this->mAccessKey;
		$ret['active'] = ($this->mActive)?1:0;
		$ret['cachable'] = ($this->mCachable)?1:0;
		$ret['content_alias'] = $this->mAlias;
		$ret['content_id'] = $this->mId;
		$ret['content_name'] = $this->mName;
		$ret['create_date'] = $this->mCreationDate;
		$ret['default_content'] = ($this->mDefaultContent)?1:0;
		$ret['has_usable_link'] = $this->HasUsableLink();
		$ret['hierarchy'] = $this->mHierarchy;
		$ret['hierarchy_path'] = $this->mHierarchyPath;
		$ret['id_hierarchy'] = $this->mIdHierarchy;
		$ret['item_order'] = $this->mItemOrder;
		$ret['last_modified_by'] = $this->mLastModifiedBy;
		$ret['menu_text'] = $this->mMenuText;
		$ret['metadata'] = $this->mMetadata;
		$ret['modified_date'] = $this->mModifiedDate;
		$ret['owner_id'] = $this->mOwner;
		$ret['page_url'] = ($this->mURL)?1:0;
		$ret['parent_id'] = $this->mParentId;
		$ret['secure'] = $this->mSecure; //deprecated since 2.3
		$ret['show_in_menu'] = ($this->mShowInMenu)?1:0;
		$ret['tabindex'] = $this->mTabIndex;
		$ret['template_id'] = $this->mTemplateId;
		$ret['titleattribute'] = $this->mTitleAttribute;
		$ret['wants_children'] = $this->WantsChildren();
		return $ret;
	}

	/**
	 * Callback function for content types to use to preload content or other things if necessary.
	 * This is called right after the content is loaded from the database.
	 *
	 * @abstract
	 */
	protected function Load()
	{
	}

	/**
	 * Save or update the content.
	 *
	 * @todo This function should return something (or throw an exception)
	 */
	public function Save()
	{
		Events::SendEvent( 'Core', 'ContentEditPre', [ 'content' => &$this ] );

		if( !is_array($this->_props) ) {
			debug_buffer('save is loading properties');
			$this->_load_properties();
		}

		if( -1 < $this->mId ) {
			$this->Update();
		}
		else {
			$this->Insert();
		}

		$contentops = ContentOperations::get_instance();
		$contentops->SetContentModified();
		$contentops->SetAllHierarchyPositions();
		Events::SendEvent( 'Core', 'ContentEditPost', [ 'content' => &$this ] );
	}

	/**
	 * Update the database with the contents of the content object.
	 *
	 * This method will calculate a new item order for the object if necessary and then
	 * save the content record, the additional editors, and the properties.
	 * Additionally, if a page url is specified a static route will be created
	 *
	 * Because multiple content objects may be modified in one batch
	 * the calling function is responsible for ensuring that page hierarchies are
	 * updated.
	 *
	 * @see ContentOperations::SetAllHierarchyPositions()
	 * @todo this function should return something, or throw an exception.
	 */
	protected function Update()
	{
		$db = CmsApp::get_instance()->GetDb();

		// Figure out the item_order (if necessary)
		if( $this->mItemOrder < 1 ) {
			$query = 'SELECT '.$db->IfNull('MAX(item_order)','0').' AS new_order FROM '.CMS_DB_PREFIX.'content WHERE parent_id = ?';
			$dbr = (int)$db->GetOne($query,[$this->mParentId]);

			if( $dbr < 1 ) {
				$this->mItemOrder = 1;
			}
			else {
				$this->mItemOrder = $dbr + 1;
			}
		}

		$this->mModifiedDate = trim($db->DbTimeStamp(time()), "'");

		$query = 'UPDATE '.CMS_DB_PREFIX.'content SET
content_name = ?,
owner_id = ?,
type = ?,
template_id = ?,
parent_id = ?,
active = ?,
default_content = ?,
show_in_menu = ?,
cachable = ?,
secure = ?,
page_url = ?,
menu_text = ?,
content_alias = ?,
metadata = ?,
titleattribute = ?,
accesskey = ?,
tabindex = ?,
modified_date = ?,
item_order = ?,
last_modified_by = ?
WHERE content_id = ?';

		$db->Execute($query, [
			$this->mName,
			$this->mOwner,
			$this->Type(),
			$this->mTemplateId,
			$this->mParentId,
			($this->mActive         ? 1 : 0),
			($this->mDefaultContent ? 1 : 0),
			($this->mShowInMenu     ? 1 : 0),
			($this->mCachable       ? 1 : 0),
			($this->mSecure         ? 1 : 0),
			$this->mURL,
			$this->mMenuText,
			$this->mAlias,
			$this->mMetadata,
			$this->mTitleAttribute,
			$this->mAccessKey,
			$this->mTabIndex,
			$this->mModifiedDate,
			$this->mItemOrder,
			$this->mLastModifiedBy,
			(int) $this->mId
		]);

		if( isset($this->mAdditionalEditors) ) {
			$query = 'DELETE FROM '.CMS_DB_PREFIX.'additional_users WHERE content_id = ?';
			$dbr = $db->Execute($query, [$this->Id()]);

			foreach( $this->mAdditionalEditors as $oneeditor ) {
				$new_addt_id = $db->GenID(CMS_DB_PREFIX.'additional_users_seq'); //deprecated since 2.3 non AUTO additional_users_id
				$query = 'INSERT INTO '.CMS_DB_PREFIX.'additional_users (additional_users_id, user_id, content_id) VALUES (?,?,?)';
				$dbr = $db->Execute($query, [$new_addt_id, $oneeditor, $this->Id()]);
			}
		}

		if( $this->_props ) {
			// :TODO: maybe some error checking
			$res = $this->_save_properties();
		}

		cms_route_manager::del_static('','__CONTENT__',$this->mId);
		if( $this->mURL ) {
			$route = CmsRoute::new_builder($this->mURL,'__CONTENT__',$this->mId,null,true);
			cms_route_manager::add_static($route);
		}
	}

	/**
	 * Initially save a content object with no id to the database.
	 *
	 * Like the Update method this method will determine a new item order
	 * save the record, save properties and additional editors, but will not
	 * update the hierarchy positions.
	 *
	 * @see ContentOperations::SetAllHierarchyPositions()
	 */
	protected function Insert()
	{
		# :TODO: This function should return something
		# :TODO: Careful about hierarchy here, it has no value !
		# :TODO: Figure out proper item_order
		$db = CmsApp::get_instance()->GetDb();

		$query = 'SELECT content_id FROM '.CMS_DB_PREFIX.'content WHERE default_content = 1';
		$dflt_pageid = (int)$db->GetOne($query);
		if( $dflt_pageid < 1 ) $this->SetDefaultContent(true);

		// Figure out the item_order
		if( $this->mItemOrder < 1 ) {
			$query = 'SELECT MAX(item_order) AS new_order FROM '.CMS_DB_PREFIX.'content WHERE parent_id = ?';
			$dbr = (int)$db->GetOne($query, [$this->mParentId]);

			if( $dbr < 1) {
				$this->mItemOrder = 1;
			}
			else {
				$this->mItemOrder = $dbr + 1;
			}
		}

		$newid = $db->GenID(CMS_DB_PREFIX.'content_seq');
		$this->mId = $newid;

		$this->mModifiedDate = $this->mCreationDate = trim($db->DbTimeStamp(time()), "'");

		$query = 'INSERT INTO '.CMS_DB_PREFIX.'content (content_id, content_name, content_alias, type, owner_id, parent_id, template_id, item_order, hierarchy, id_hierarchy, active, default_content, show_in_menu, cachable, secure, page_url, menu_text, metadata, titleattribute, accesskey, tabindex, last_modified_by, create_date, modified_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

		$dbr = $db->Execute($query, [
			$newid,
			$this->mName,
			$this->mAlias,
			$this->Type(),
			$this->mOwner,
			$this->mParentId,
			$this->mTemplateId,
			$this->mItemOrder,
			$this->mHierarchy,
			$this->mIdHierarchy,
			($this->mActive         ? 1 : 0),
			($this->mDefaultContent ? 1 : 0),
			($this->mShowInMenu     ? 1 : 0),
			($this->mCachable       ? 1 : 0),
			($this->mSecure         ? 1 : 0),
			$this->mURL,
			$this->mMenuText,
			$this->mMetadata,
			$this->mTitleAttribute,
			$this->mAccessKey,
			$this->mTabIndex,
			$this->mLastModifiedBy,
			$this->mModifiedDate,
			$this->mCreationDate
		]);

		if( !$dbr ) {
			die($db->sql.'<br />'.$db->ErrorMsg());
		}

		if( $this->_props ) {
			// :TODO: maybe some error checking
			debug_buffer('save from ' . __LINE__);
			$this->_save_properties();
		}
		if( isset($this->mAdditionalEditors) ) {
			foreach( $this->mAdditionalEditors as $oneeditor ) {
				$new_addt_id = $db->GenID(CMS_DB_PREFIX.'additional_users_seq');
				$query = 'INSERT INTO '.CMS_DB_PREFIX.'additional_users (additional_users_id, user_id, content_id) VALUES (?,?,?)';
				$db->Execute($query, [$new_addt_id, $oneeditor, $this->Id()]);
			}
		}

		if( $this->mURL ) {
			$route = CmsRoute::new_builder($this->mURL,'__CONTENT__',$this->mId,'',true);
			cms_route_manager::add_static($route);
		}
	}

	/**
	 * Test whether the content object is valid.
	 * This function is used to check that no compulsory argument
	 * has been forgotten by the user
	 *
	 * We do not check the Id because there can be no Id (new content)
	 * That's up to Save to check this.
	 *
	 * @abstract
	 * @returns	mixed On error returns an array of strings, otherwise false
	 */
	public function ValidateData()
	{
		$errors = [];

		if( $this->mParentId < -1 ) {
			$errors[] = lang('invalidparent');
		}

		if( $this->mName === '' ) {
			if( $this->mMenuText ) {
				$this->mName = $this->mMenuText;
			}
			else {
				$errors[] = lang('nofieldgiven', lang('title'));
			}
		}

		if( $this->mMenuText === '' ) {
			if( $this->mName ) {
				$this->mMenuText = $this->mName;
			}
			else {
				$errors[] = lang('nofieldgiven', lang('menutext'));
			}
		}

		if( !$this->HandlesAlias()) {
			if( $this->mAlias != $this->mOldAlias || ($this->mAlias === '' && $this->RequiresAlias()) ) {
				$contentops = ContentOperations::get_instance();
				$error = $contentops->CheckAliasError($this->mAlias, $this->mId);
				if( $error !== false ) {
					$errors[] = $error;
				}
			}
		}

		$auto_type = content_assistant::auto_create_url();
		if( $this->mURL === '' && cms_siteprefs::get('content_autocreate_urls') ) {
			// create a valid url.
			if( !$this->DefaultContent() ) {
				if( cms_siteprefs::get('content_autocreate_flaturls',0) ) {
					// the default url is the alias... but not synced to the alias.
					$this->mURL = $this->mAlias;
				}
				else {
					// if it doesn't explicitly say 'flat' we're creating a hierarchical url.
					$hm = CmsApp::get_instance()->GetHierarchyManager();
					$node = $hm->find_by_tag('id',$this->ParentId());
					$stack = [$this->mAlias];
					$parent_url = '';
					$count = 0;
					while( $node ) {
						$tmp_content = $node->GetContent();
						if( $tmp_content ) {
							$tmp = $tmp_content->URL();
							if( $tmp && $count == 0 ) {
								// try to build the url out of the parent url.
								$parent_url = $tmp;
								break;
							}
							array_unshift($stack,$tmp_content->Alias());
						}
						$node = $node->GetParent();
						$count++;
					}

					$this->mURL = implode('/',$stack);
					if( $parent_url ) {
						// woot, we got a parent url.
						$this->mURL = $parent_url.'/'.$this->mAlias;
					}
				}
			}
		}
		if( $this->mURL === '' && cms_siteprefs::get('content_mandatory_urls') &&
			!$this->mDefaultContent && $this->HasUsableLink() ) {
			// page url is empty and mandatory
			$errors[] = lang('content_mandatory_urls');
		}
		else if( $this->mURL ) {
			// page url is not empty, silently delete bad chars
			$this->mURL = filter_var(trim($this->mURL,FILTER_SANITIZE_URL));
			// and validate it
			if( $this->mURL && !content_assistant::is_valid_url($this->mURL,$this->mId) ) {
				$errors[] = lang('invalid_url2');
			}
		}

		return ($errors) ? $errors:false;
	}

	/**
	 * Delete the current content object from the database.
	 *
	 * @todo this function should return something, or throw an exception
	 */
	public function Delete()
	{
		Events::SendEvent( 'Core', 'ContentDeletePre', [ 'content' => &$this ] );
		if( $this->mId > 0 ) {
			$db = CmsApp::get_instance()->GetDb();

			$query = 'DELETE FROM '.CMS_DB_PREFIX.'content WHERE content_id = ?';
			$dbr = $db->Execute($query, [$this->mId]);

			// Fix the item_order if necessary
			$query = 'UPDATE '.CMS_DB_PREFIX.'content SET item_order = item_order - 1 WHERE parent_id = ? AND item_order > ?';
			$dbr = $db->Execute($query,[$this->ParentId(),$this->ItemOrder()]); //NB unreliable result after update

			// DELETE properties
			$query = 'DELETE FROM '.CMS_DB_PREFIX.'content_props WHERE content_id = ?';
			$dbr = $db->Execute($query,[$this->mId]);
			$this->_props = null;

			// Delete additional editors.
			$query = 'DELETE FROM '.CMS_DB_PREFIX.'additional_users WHERE content_id = ?';
			$dbr = $db->Execute($query,[$this->mId]);
			$this->mAdditionalEditors = null;

			// Delete route
			if( $this->mURL ) cms_route_manager::del_static($this->mURL);
		}

		Events::SendEvent( 'Core', 'ContentDeletePost', [ 'content' => &$this ] );
		$this->mId = -1;
		$this->mItemOrder = -1;
	}

	/**
	 * Function for the subclass to parse out data for its parameters.
	 * This method is typically called from an editor form to allow modifying
	 * the content object from form input fields (usually $_POST)
	 *
	 * @param array $params The input array (usually from $_POST)
	 * @param bool  $editing Indicates whether this is an edit or add operation.
	 * @abstract
	 */
	public function FillParams($params, $editing = false)
	{
		// content property parameters
		$parameters = ['extra1','extra2','extra3','image','thumbnail'];
		foreach( $parameters as $oneparam ) {
			if( isset($params[$oneparam]) ) $this->SetPropertyValue($oneparam, $params[$oneparam]);
		}

		// go through the list of base parameters
		// setting them from params

		// title
		if( isset($params['title']) ) $this->mName = strip_tags($params['title']);

		// menu text
		if( isset($params['menutext']) ) $this->mMenuText = strip_tags(trim($params['menutext']));

		// parent id
		if( isset($params['parent_id']) ) {
			if( $params['parent_id'] == -2 && !$editing ) $params['parent_id'] = -1;
			if( $this->mParentId != $params['parent_id'] ) {
				$this->mHierarchy = '';
				$this->mItemOrder = -1;
			}
			$this->mParentId = (int) $params['parent_id'];
		}

		// active
		if( isset($params['active'])) {
			$this->mActive = (int) $params['active'];
			if( $this->DefaultContent() ) $this->mActive = 1;
		}

		// show in menu
		if( isset($params['showinmenu']) ) $this->mShowInMenu = (int) $params['showinmenu'];

		// alias
		// alias field can exist if the user has manage all content... OR alias is a basic property
		// and this user has other edit rights to the content page.
		// empty value on the alias field means we need to generate a new alias
		$new_alias = null;
		$alias_field_exists = isset( $params['alias'] );
		if( isset($params['alias']) ) $new_alias = trim(strip_tags($params['alias']));
		// if we are adding or we have a new alias, set alias to the field value, or calculate one, adjust as needed
		if( $new_alias || $alias_field_exists ) {
			$this->SetAlias($new_alias);
		}

		// target
		if( isset($params['target']) ) {
			$val = strip_tags($params['target']);
			if( $val == '---' ) $val = '';
			$this->SetPropertyValue('target', $val);
		}

		// title attribute
		if( isset($params['titleattribute']) ) $this->mTitleAttribute = trim(strip_tags($params['titleattribute']));

		// accesskey
		if( isset($params['accesskey']) ) $this->mAccessKey = strip_tags($params['accesskey']);

		// tab index
		if( isset($params['tabindex']) ) $this->mTabIndex = (int) $params['tabindex'];

		// cachable
		if( isset($params['cachable']) ) {
			$this->mCachable = (int) $params['cachable'];
		}
		else {
			$this->_handleRemovedBaseProperty('cachable','mCachable');
		}

		// secure
		if (isset($params['secure'])) {
			$this->mSecure = (int) $params['secure'];
		}
		else {
			$this->_handleRemovedBaseProperty('secure','mSecure');
		}

		// url
		if( isset($params['page_url']) ) {
			$tmp = trim($params['page_url']);
			if( $tmp && ($tmp == filter_var(trim($params['page_url']),FILTER_SANITIZE_URL)) ) {
				$this->mURL = $tmp;
			}
			else {
				$this->mURL = '';
				$this->_handleRemovedBaseProperty('page_url','mURL');
			}
		}
		else {
			$this->mURL = '';
			$this->_handleRemovedBaseProperty('page_url','mURL');
		}

		// owner
		if( isset($params['ownerid']) ) $this->SetOwner((int) $params['ownerid']);

		// additional editors
		if( isset($params['additional_editors']) ) {
			$addtarray = [];
			if( is_array($params['additional_editors']) ) {
				foreach( $params['additional_editors'] as $addt_user_id ) {
					$addtarray[] = (int) $addt_user_id;
				}
			}
			$this->SetAdditionalEditors($addtarray);
		}
	}

	/**
	 * Return the internally-generated URL for this content.
	 *
	 * @param bool $rewrite optional flag, default true. If true, and mod_rewrite is enabled, build an URL suitable for mod_rewrite.
	 * @return string
	 */
	public function GetURL($rewrite = true)
	{
		$config = cms_config::get_instance();
		$url = '';
		$alias = ($this->mAlias?$this->mAlias:$this->mId);

		$base_url = CMS_ROOT_URL;

		/* use root_url for default content */
		if($this->DefaultContent()) {
			$url =  $base_url . '/';
			return $url;
		}

		if( $rewrite ) {
			$url_rewriting = $config['url_rewriting'];
			$page_extension = $config['page_extension'];
			if( $url_rewriting == 'mod_rewrite' ) {
				$str = $this->HierarchyPath();
				if( $this->mURL ) $str = $this->mURL;	// we have a url path
				$url = $base_url . '/' . $str . $page_extension;
				return $url;
			}
			else if( isset($_SERVER['PHP_SELF']) && $url_rewriting == 'internal' ) {
				$str = $this->HierarchyPath();
				if( $this->mURL ) $str = $this->mURL; // we have a url path
				$url = $base_url . '/index.php/' . $str . $page_extension;
				return $url;
			}
		}

		$url = $base_url . '/index.php?' . $config['query_var'] . '=' . $alias;
		return $url;
	}

	/**
	 * Move this content up, or down with respect to its peers.
	 *
	 * Note: This method modifies two content objects.
	 *
	 * @since 2.0
	 * @param int $direction direction. negative value indicates up, positive value indicates down.
	 */
	public function ChangeItemOrder($direction)
	{
		$db = CmsApp::get_instance()->GetDb();
		$time = $db->DbTimeStamp(time());
		$parentid = $this->ParentId();
		$order = $this->ItemOrder();
		if( $direction < 0 && $this->ItemOrder() > 1 ) {
			// up
			$query = 'UPDATE '.CMS_DB_PREFIX.'content SET item_order = (item_order + 1), modified_date = '.$time.'
 WHERE item_order = ? AND parent_id = ?';
			$db->Execute($query,[$order-1,$parentid]);
			$query = 'UPDATE '.CMS_DB_PREFIX.'content SET item_order = (item_order - 1), modified_date = '.$time.'
 WHERE content_id = ?';
			$db->Execute($query,[$this->Id()]);
		}
		else if( $direction > 0 ) {
			// down.
			$query = 'UPDATE '.CMS_DB_PREFIX.'content SET item_order = (item_order - 1), modified_date = '.$time.'
 WHERE item_order = ? AND parent_id = ?';
			$db->Execute($query,[$order+1,$parentid]);
			$query = 'UPDATE '.CMS_DB_PREFIX.'content SET item_order = (item_order + 1), modified_date = '.$time.'
 WHERE content_id = ?';
			$db->Execute($query,[$this->Id()]);
		}
		global_cache::clear('content_tree');
		global_cache::clear('content_flatlist');
	}

	/**
	 * Return the raw value for a content property.
	 * If no property name is specified 'content_en' is assumed
	 *
	 * @abstract
	 * @param string $propname An optional property name to display.  If none specified, the system should assume content_en.
	 * @return string
	 */
	public function Show($propname = 'content_en')
	{
	}

	/**
	 * Return a list of all of the properties that may be edited by the current user when editing this content item
	 * in a content editor form.
	 *
	 * Content-type classes may override this method, but should call this base method.
	 *
	 * @abstract
	 * @return array Array of assoc. arrays, each of those having members
	 *  'name' (string), 'tab' (string), 'priority' (int), maybe 'required' (bool), maybe 'basic' (bool)
	 *  Other(s) may be added by a subclass
	 */
	public function GetEditableProperties()
	{
		$all = $this->IsEditable(true,false);
		if( !$all ) {
			$basic_properties = ['title','parent'];
			$tmp_basic_properties = cms_siteprefs::get('basic_attributes');
			if( $tmp_basic_properties ) {
				$tmp = explode(',',$tmp_basic_properties);
				$tmp_basic_properties = array_walk($tmp,function(&$one) { return trim($one); });
				$basic_properties = array_merge($tmp_basic_properties,$basic_properties);
			}
		}

		$ret = [];
		foreach( $this->_properties as &$one ) {
			if( $all || !empty($one['basic']) || in_array($one['name'],$basic_properties) ) {
				$ret[] = $one;
			}
		}
		unset($one);
		return $ret;
	}

	/**
	 * Check the current user's edit-authority
	 * @since 2.3
	 *
	 * @param $main optional flag whether to check for main-property editability. Default true
	 * @param $extra optional flag whether to check for membership of additional-editors. Default true
	 * @return bool
	 */
	protected function IsEditable(bool $main = true, bool $extra = true) : bool
	{
		$uid = get_userid();
		$ops = UserOperations::get_instance();
		if( $main ) {
			if( $ops->CheckPermission($uid,'Manage All Content')
			 || $ops->CheckPermission($uid,'Modify Any Page')
			 || $ops->CheckPermission($uid,'Add Pages') ) {
				if( !$extra ) {
					return true;
				}
			}
		}
		if( $extra ) {
			$eds = $this->GetAdditionalEditors();
			if( $eds ) {
				if( in_array($uid,$eds) ) {
	   				return true;
				}
				else {
					foreach( $eds as $one ) {
						if( $one < 0 ) {
							if( $ops->UserInGroup($uid,- (int)$one) ) {
								return true;
							}
						}
					}
				}
			}
		}
		return false;
	}

	/**
	 * Sort properties by their attributes - tab, priority, name
	 * @ignore
	 */
	private function _SortProperties(array $props) : array
	{
		if( count($props) > 1 ) {
		  usort($props,function($a,$b)
		  {
			$res = strcmp($a['tab'],$b['tab']);
			if( $res == 0 ) $res = $a['priority'] <=> $b['priority'];
			if( $res == 0 ) $res = strcmp($a['name'],$b['name']);
			return $res;
		  });
		}

		return $props;
	}

	/**
	 * @ignore
	 */
	private function _GetEditableProperties() : array
	{
		if( isset($this->_editable_properties) ) return $this->_editable_properties;

		$props = $this->_SortProperties($this->GetEditableProperties());
		$this->_editable_properties = $props;
		return $props;
	}

	/**
	 * Return a list of distinct sections that divide the various logical sections
	 * that this content type supports for editing.
	 * Used from a page that allows content editing.
	 *
	 * @abstract
	 * @return array Associative array of tab keys and labels.
	 */
	public function GetTabNames()
	{
		$props = $this->_GetEditableProperties();
		$arr = [];
		foreach( $props as &$one ) {
			if( !isset($one['tab']) || $one['tab'] === '' ) $one['tab'] = self::TAB_MAIN;
			$key = $one['tab'];
			if( endswith($key,'_tab__') ) { $lbl = lang($key); }
			else { $lbl = $key; }
			$arr[$key] = $lbl;
		}
		unset($one);
		return $arr;
	}

	/**
	 * Get an optional message for each tab.
	 *
	 * @abstract
	 * @since 2.0
	 * @param string $key the tab key (as returned with GetTabNames)
	 * @return string html text to display at the top of the tab.
	 */
	public function GetTabMessage($key)
	{
		switch( $key ) {
		case self::TAB_PERMS:
			return '<div class="information">'.lang('msg_permstab').'</div>';
			break;
		}
	}

	/**
	 * Get the elements for a specific tab.
	 *
	 * @param string $key tab key
	 * @param bool   $adding  Optional flag whether this is an add operation. Default false (i.e. edit).
	 * @return array Each member an array:
     *  [0] = prompt field
	 *  [1] = input field for the prompt with its js if needed
	 * or just a scalar false upon some errors
	 */
	public function GetTabElements($key, $adding = false)
	{
		$props = $this->_GetEditableProperties();
		$ret = [];
		foreach( $props as &$one ) {
			if( !isset($one['tab']) || $one['tab'] === '' ) $one['tab'] = self::TAB_MAIN;
			if( $one['tab'] == $key ) {
				$ret[] = $this->display_single_element($one['name'],$adding);
			}
		}
		unset($one);
		return $ret;
	}

	/**
	 * Return whether the current page has children.
	 *
	 * @param bool $activeonly Optional flag whether to test only for active children. Default false.
	 * @return bool
	 */
	public function HasChildren($activeonly = false)
	{
		if( $this->mId <= 0 ) return false;
		$hm = CmsApp::get_instance()->GetHierarchyManager();
		$node = $hm->quickfind_node_by_id($this->mId);
		if( !$node || !$node->has_children() ) return false;

		if( !$activeonly ) return true;
		$children = $node->get_children();
		if( $children ) {
			for( $i = 0, $n = count($children); $i < $n; $i++ ) {
				$content = $children[$i]->getContent();
				if( $content->Active() ) return true;
			}
		}

		return false;
	}

	/**
	 * Return a list of additional editors.
	 * Note: in the returned array, group id's are specified as negative integers.
	 *
	 * @return array user ids and group ids entitled to edit this content, or empty
	 */
	public function GetAdditionalEditors()
	{
		if( !isset($this->mAdditionalEditors) ) {
			$db = CmsApp::get_instance()->GetDb();

			$query = 'SELECT user_id FROM '.CMS_DB_PREFIX.'additional_users WHERE content_id = ?';
			$dbr = $db->GetCol($query,[$this->mId]);
			if( $dbr ) {
				$this->mAdditionalEditors = $dbr;
			}
			else {
				$this->mAdditionalEditors = [];
			}
		}
		return $this->mAdditionalEditors;
	}

	/**
	 * Set the list of additional editors.
	 * Note: in the provided array, group id's are specified as negative integers.
	 *
	 * @param mixed $editorarray Array of user ids and group ids, or null
	 */
	public function SetAdditionalEditors($editorarray)
	{
		$this->mAdditionalEditors = $editorarray;
	}

	/**
	 * Return all recorded user ids and group ids in a format that is suitable for
	 * use in a select field.
	 *
	 * @return array each member like id => name
	 * Note: group ids are expressed as negative integers in the keys.
	 */
	public static function GetAdditionalEditorOptions()
	{
		$opts = [];
		$userops = UserOperations::get_instance();
		$groupops = GroupOperations::get_instance();
		$allusers = $userops->LoadUsers();
		$allgroups = $groupops->LoadGroups();
		foreach( $allusers as &$one ) {
			$opts[$one->id] = $one->username;
		}
		foreach( $allgroups as &$one ) {
			if( $one->id == 1 ) continue; // exclude admin group (they have all privileges anyways)
			$val = - (int)$one->id;
			$opts[$val] = lang('group').': '.$one->name;
		}
		unset($one);

		return $opts;
	}

	/**
	 * Generate a <select> field for selecting additional editors.
	 * If a positive owner id is specified that user will be excluded from output select element.
	 *
	 * @see ContentBase::GetAdditionalEditorOptions()
	 * @param array $addteditors Array of additional editors
	 * @param int  $owner_id  The current owner of the page.
	 * @return string HTML output
	 */
	public static function GetAdditionalEditorInput($addteditors, $owner_id = -1)
	{
		$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_addteditor',lang('help_title_content_addteditor'));
		$ret[] = '<label for="addteditors">'.lang('additionaleditors').':</label>'.$help;
		$text = '<input name="additional_editors" type="hidden" value="" />';
		$text .= '<select id="addteditors" name="additional_editors[]" multiple="multiple" size="5">';

		$topts = self::GetAdditionalEditorOptions();
		foreach( $topts as $k => $v ) {
			if( $k == $owner_id ) continue;
			$text .= FormUtils::create_option(['label'=>$v,'value'=>$k],$addteditors);
		}

		$text .= '</select>';
		$ret[] = $text;
		return $ret;
	}

	/**
	 * Generate an input element to display the list of additional editors.
	 * This method is usually called from within this object.
	 *
	 * @param array $addteditors An optional array of additional editor id's (group ids specified with negative values)
	 * @return string The input element.
	 * @see ContentBase::GetAdditionalEditorInput()
	 */
	public function ShowAdditionalEditors($addteditors = null)
	{
		if( !$addteditors ) {
			$addteditors = $this->GetAdditionalEditors();
		}
		return self::GetAdditionalEditorInput($addteditors,$this->Owner());
	}

	/**
	 * Set the value (by member) of a base (not addon property) property of the
	 * content object for base properties that have been removed from the form.
	 *
	 * @ignore
	 */
	private function _handleRemovedBaseProperty(string $name, string $member) : bool
	{
		if( !$this->_properties ) return false;
		$fnd = false;
		foreach( $this->_properties as &$one ) {
			if( $one['name'] == $name ) {
				$fnd = true;
				break;
			}
		}
		unset($one);

		if( !$fnd ) {
			if( isset($this->_prop_defaults[$name]) ) {
				$this->$member = $this->_prop_defaults[$name];
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove a property from the known-properties list, and specify a default
	 * value to use if the property is sought.
	 *
	 * @param string $name The property name
	 * @param string $dflt The default value.
	 */
	protected function RemoveProperty($name, $dflt)
	{
		if( !$this->_properties ) return;
		for( $i = 0, $n = count($this->_properties); $i < $n; ++$i ) {
			if( $this->_properties[$i] && $this->_properties[$i]['name'] == $name ) {
				unset($this->_properties[$i]);
				if( $i < $n - 1 ) {
					$this->_properties = array_values($this->_properties);
				}
				$this->_prop_defaults[$name] = $dflt;
				return;
			}
		}
	}

	/**
	 * Add a property definition.
	 * NOTE this method can be a significant contributor to the duration of each frontend request
	 * @see comment for BaseContent::SetProperties() re data format
	 *
	 * @since 1.11
	 * @param string $name Property name
	 * @param int $priority Sort order
	 * @param string $tab Optional tab for the property (see tab constants) Default TAB_MAIN
	 * @param bool $required Optional flag whether the property is required Default false
	 * @param bool $basic Optional flag whether the property is basic (i.e. editable even by restricted editors) Default false
	 */
	protected function AddProperty($name, $priority, $tab = self::TAB_MAIN, $required = false, $basic = false)
	{
		if( !$tab ) $tab = self::TAB_MAIN;
		$this->_properties[] = [
			'tab' => (string)$tab,
			'priority' => (int)$priority,
			'name' => (string)$name,
			'required' => (bool)$required,
			'basic' => (bool)$basic,
		];
	}

	/**
	 * Add a property that is directly associated with a field in the content table
	 * @alias for AddProperty
	 * @deprecated since 2.3 (at most?)
	 *
	 * @param string $name The property name
	 * @param int    $priority The priority
	 * @param bool   $is_required Whether this field is required for this content type
	 */
	protected function AddBaseProperty($name, $priority, $is_required = false)
	{
		$this->AddProperty($name,$priority,self::TAB_MAIN,$is_required);
	}

	/**
	 * Alias for AddProperty
	 * @deprecated  since 2.3 (at most?)
	 *
	 * @param string $name
	 * @param int    $priority
	 * @param bool   $is_required
	 * @return null
	 */
	protected function AddContentProperty($name, $priority, $is_required = false)
	{
		return $this->AddProperty($name,$priority,self::TAB_MAIN,$is_required);
	}

	/**
	 * Get all of the properties of this content object (whether or not the user is entitled to view them)
	 *
	 * @since 2.3
	 * @return array of assoc. arrays
	 */
	public function GetPropertiesArray() : array
	{
		return $this->_SortProperties($this->_properties);
	}

	/**
	 * Get all of the properties of this content object (whether or not the user is entitled to view them)
	 *
	 * @since 2.0
	 * @deprecated since 2.3 Instead use ContentBase::GetPropertiesArray()
	 * @return array of stdClass objects
	 */
	public function GetProperties()
	{
		$ret = $this->_SortProperties($this->_properties);
		if( $ret ) {
			foreach( $ret as &$one ) {
				$one = (object)$one;
			}
		}
		return $ret;
	}

	/**
	 * Get html to display a single input element for an object basic or extended property.
	 *
	 * @abstract
	 * @param string $one The property name
	 * @param bool $adding Whether or not we are in add or edit mode.
	 * @return mixed 2-member array: [0] = label, [1] = input element | false
	 */
	protected function display_single_element($one, $adding)
	{
		$config = cms_config::get_instance();

		switch( $one ) {
		case 'title':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_title',lang('help_title_content_title'));
			return ['<label for="in_title">*'.lang('title').':</label>'.$help,
					'<input type="text" id="in_title" name="title" required="required" value="'.cms_htmlentities($this->mName).'" />'];

		case 'menutext':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_menutext',lang('help_title_content_menutext'));
			return ['<label for="in_menutext">*'.lang('menutext').':</label>'.$help,
					'<input type="text" name="menutext" id="in_menutext" value="'.cms_htmlentities($this->mMenuText).'" />'];

		case 'parent':
			$contentops = ContentOperations::get_instance();
			$tmp = $contentops->CreateHierarchyDropdown($this->mId, $this->mParentId, 'parent_id', ($this->mId > 0) ? 0 : 1, 1, 0, 1, 1);
			if( empty($tmp) && !check_permission(get_userid(),'Manage All Content') ) {
				return ['','<input type="hidden" name="parent_id" value="'.$this->mParentId.'" />'];
			}
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_parent',lang('help_title_content_parent'));
			if( !empty($tmp) ) return ['<label for="parent_id">*'.lang('parent').':</label>'.$help,$tmp];
			break;

		case 'active':
			if( !$this->DefaultContent() ) {
				$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_active',lang('help_title_content_active'));
				return ['<label for="id_active">'.lang('active').':</label>'.$help,
						'<input type="hidden" name="active" value="0" /><input class="pagecheckbox" type="checkbox" name="active" id="id_active" value="1"'.($this->mActive?' checked="checked"':'').' />'];
			}
			break;

		case 'showinmenu':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_showinmenu',lang('help_title_content_showinmenu'));
			return ['<label for="showinmenu">'.lang('showinmenu').':</label>'.$help,
					'<input type="hidden" name="showinmenu" value="0" /><input class="pagecheckbox" type="checkbox" value="1" name="showinmenu" id="showinmenu"'.($this->mShowInMenu?' checked="checked"':'').' />'];

		case 'target':
			$text = '<option value="---">'.lang('none').'</option>';
			$text .= '<option value="_blank"'.($this->GetPropertyValue('target')=='_blank'?' selected="selected"':'').'>_blank</option>';
			$text .= '<option value="_parent"'.($this->GetPropertyValue('target')=='_parent'?' selected="selected"':'').'>_parent</option>';
			$text .= '<option value="_self"'.($this->GetPropertyValue('target')=='_self'?' selected="selected"':'').'>_self</option>';
			$text .= '<option value="_top"'.($this->GetPropertyValue('target')=='_top'?' selected="selected"':'').'>_top</option>';
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_target',lang('help_title_content_target'));
			return ['<label for="target">'.lang('target').':</label>'.$help,
					'<select name="target" id="target">'.$text.'</select>'];

		case 'alias':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_page_alias',lang('help_title_page_alias'));
			return ['<label for="alias">'.lang('pagealias').':</label>'.$help,
					'<input type="text" name="alias" id="alias" value="'.$this->mAlias.'" />'];

		case 'cachable':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_cachable',lang('help_title_content_cachable'));
			return ['<label for="in_cachable">'.lang('cachable').':</label>'.$help,
					'<input type="hidden" name="cachable" value="0" /><input id="in_cachable" class="pagecheckbox" type="checkbox" value="1" name="cachable"'.($this->mCachable?' checked="checked"':'').' />'];

		case 'secure':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_secure',lang('help_title_content_secure'));
			return ['<label for="secure">'.lang('secure_page').':</label>'.$help,
					'<input type="hidden" name="secure" value="0"/><input id="secure" class="pagecheckbox" type="checkbox" value="1" name="secure"'.($this->mSecure?' checked="checked"':'').' />'];

		case 'page_url':
			if( !$this->DefaultContent() ) {
				$pretty_urls = $config['url_rewriting'] == 'none' ? 0 : 1;
				if( $pretty_urls != 0) {
					$str = '<input type="text" name="page_url" id="page_url" value="'.$this->mURL.'" size="50" maxlength="255" />';
					$prompt = '<label for="page_url">'.lang('page_url').':</label>';
					if( cms_siteprefs::get('content_mandatory_urls',0) ) $prompt = '*'.$prompt;
					$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_page_url',lang('help_title_page_url'));
					return [$prompt.$help,$str];
				}
			}
			break;

		case 'image':
			$dir = cms_join_path($config['image_uploads_path'],cms_siteprefs::get('content_imagefield_path'));
			$data = $this->GetPropertyValue('image');
			$filepicker = cms_utils::get_filepicker_module();
			if( $filepicker ) {
				$profile = $filepicker->get_default_profile( $dir, get_userid() );
				$profile = $profile->overrideWith( ['top'=>$dir, 'type'=>FileType::IMAGE] );
				$input = $filepicker->get_html( 'image', $data, $profile);
			}
			else {
				$input = create_file_dropdown('image',$dir,$data,'jpg,jpeg,png,gif','',true,'','thumb_',0,1);
			}
			if( !$input ) return false;
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_image',lang('help_title_content_image'));
			return ['<label for="image">'.lang('image').':</label>'.$help,$input];

		case 'thumbnail':
			$dir = cms_join_path($config['image_uploads_path'],cms_siteprefs::get('content_thumbnailfield_path'));
			$data = $this->GetPropertyValue('thumbnail');
			$filepicker = cms_utils::get_filepicker_module();
			if( $filepicker ) {
				$profile = $filepicker->get_default_profile( $dir, get_userid() );
				$profile = $profile->overrideWith( ['top'=>$dir, 'type'=>FileType::IMAGE, 'match_prefix'=>'thumb_' ] );
				$input = $filepicker->get_html( 'thumbnail', $data, $profile);
			}
			else {
				$input = create_file_dropdown('thumbnail',$dir,$data,'jpg,jpeg,png,gif','',true,'','thumb_',0,1);
			}
			if( !$input ) return false;
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_thumbnail',lang('help_title_content_thumbnail'));
			return ['<label for="thumbnail">'.lang('thumbnail').':</label>'.$help,$input];

		case 'titleattribute':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_titleattribute',lang('help_title_content_ta'));
			return ['<label for="titleattribute">'.lang('titleattribute').':</label>'.$help,
					'<input type="text" name="titleattribute" id="titleattribute" maxlength="255" size="80" value="'.cms_htmlentities($this->mTitleAttribute).'" />'];

		case 'accesskey':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_accesskey',lang('help_title_content_accesskey'));
			return ['<label for="accesskey">'.lang('accesskey').':</label>'.$help,
					'<input type="text" name="accesskey" id="accesskey" maxlength="5" value="'.cms_htmlentities($this->mAccessKey).'" />'];

		case 'tabindex':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_tabindex',lang('help_title_content_tabindex'));
			return ['<label for="tabindex">'.lang('tabindex').':</label>'.$help,
					'<input type="text" name="tabindex" id="tabindex" maxlength="5" value="'.cms_htmlentities($this->mTabIndex).'" />'];

		case 'extra1':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_extra1',lang('help_title_content_extra1'));
			return ['<label for="extra1">'.lang('extra1').':</label>'.$help,
					'<input type="text" name="extra1" id="extra1" maxlength="255" size="80" value="'.cms_htmlentities($this->GetPropertyValue('extra1')).'" />'];

		case 'extra2':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_extra2',lang('help_title_content_extra2'));
			return ['<label for="extra2">'.lang('extra2').':</label>'.$help,
					'<input type="text" name="extra2" id="extra2" maxlength="255" size="80" value="'.cms_htmlentities($this->GetPropertyValue('extra2')).'" />'];

		case 'extra3':
			$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_extra3',lang('help_title_content_extra3'));
			return ['<label for="extra3">'.lang('extra3').':</label>'.$help,
					'<input type="text" name="extra3" id="extra3" maxlength="255" size="80" value="'.cms_htmlentities($this->GetPropertyValue('extra3')).'" />'];

		case 'owner':
			$showadmin = ContentOperations::get_instance()->CheckPageOwnership(get_userid(), $this->Id());
			if( !$adding && (check_permission(get_userid(),'Manage All Content') || $showadmin) ) {
				$userops = UserOperations::get_instance();
				$help = '&nbsp;'.AdminUtils::get_help_tag('core','help_content_owner',lang('help_title_content_owner'));
				return ['<label for="owner">'.lang('owner').':</label>'.$help, $userops->GenerateDropdown($this->Owner())];
			}
			break;

		case 'additionaleditors':
			// do owner/additional-editor stuff
			if( $adding || check_permission(get_userid(),'Manage All Content') ||
				ContentOperations::get_instance()->CheckPageOwnership(get_userid(),$this->Id()) ) {
				return $this->ShowAdditionalEditors();
			}
			break;

		default:
			throw new CmsInvalidDataException('Attempt to display invalid property '.$one);
		}
	}
} // class

//backward-compatibility shiv
\class_alias(ContentBase::class, 'ContentBase', false);

} // namespace

namespace {
	 /**
	 * @ignore
	 */
	const CMS_CONTENT_HIDDEN_NAME = '--------';
	const __CMS_PREVIEW_PAGE__ = -100;
}
