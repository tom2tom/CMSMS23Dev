<?php
#CMS Made Simple link content type
#Copyright (C) 2004-2018 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

namespace CMSMS\contenttypes;

use CMSMS\ContentBase;
use function check_permission;
use function cms_htmlentities;
use function get_userid;
use function lang;

/**
 * Implementation of the CMS Made Simple link content type
 *
 * Links are content objects that appear in navigations and implement a link to an externl
 * page or site.
 *
 * @package CMS
 * @subpackage content_types
 * @license GPL
 */
class Link extends ContentBase
{
	public function FriendlyName() { return lang('contenttype_redirlink'); }
	public function HasSearchableContent() { return false; }
	public function IsCopyable() { return true; }
	public function IsViewable() { return false; }

	public function SetProperties()
	{
		parent::SetProperties();
		$this->RemoveProperty('secure',false);
		$this->RemoveProperty('cachable',true);
		$this->AddProperty('url',3,self::TAB_MAIN,true,true);
	}

	public function FillParams($params, $editing = false)
	{
		parent::FillParams($params,$editing);

		if (isset($params)) {
			$parameters = ['url'];
			foreach ($parameters as $oneparam) {
				if (isset($params[$oneparam])) $this->SetPropertyValue($oneparam, $params[$oneparam]);
			}

			if (isset($params['file_url'])) $this->SetPropertyValue('url', $params['file_url']);
		}
	}

	public function TemplateResource() : string
	{
		return ''; //TODO
	}

	public function ValidateData()
	{
		$errors = parent::ValidateData();
		if( $errors === false )	$errors = [];

		if ($this->GetPropertyValue('url') == '') {
			$errors[]= lang('nofieldgiven', lang('url'));
			$result = false;
		}

		return (count($errors) > 0?$errors:false);
	}

	public function TabNames()
	{
		$res = [lang('main')];
		if( check_permission(get_userid(),'Manage All Content') ) {
			$res[] = lang('options');
		}
		return $res;
	}

	public function display_single_element($one, $adding)
	{
		switch($one) {
		case 'url':
			return [lang('url').':','<input type="text" name="url" size="80" value="'.cms_htmlentities($this->GetPropertyValue('url')).'" />'];
			break;

		default:
			return parent::display_single_element($one,$adding);
		}
	}

	public function EditAsArray($adding = false, $tab = 0, $showadmin = false)
	{
		switch($tab) {
		case '0':
			return $this->display_attributes($adding);
			break;
		case '1':
			return $this->display_attributes($adding,1);
			break;
		}
	}

	public function GetURL($rewrite = true)
	{
		return $this->GetPropertyValue('url');
		//return cms_htmlentities($this->GetPropertyValue('url'));
	}
}

//backward-compatibility shiv
\class_alias(Link::class, 'Link', false);
