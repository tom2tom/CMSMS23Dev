<?php
#Plugin to ...
#Copyright (C) 2004-2017 Ted Kulp <ted@cmsmadesimple.org>
#Copyright (C) 2018 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

function smarty_function_cms_lang_info($params, $template)
{
	$lang = CmsNlsOperations::get_current_language();
	if( isset($params['lang']) ) {
		$lang = trim($params['lang']);
	}
	$info = CmsNlsOperations::get_language_info($lang);
	if( !$info ) return;

	if( isset($params['assign']) ) {
		$template->assign(trim($params['assign']),$info);
		return;
	}
	return $info;
}
