<?php
#Plugin to ...
#Copyright (C) 2004-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

/**
 * @param array $params
 * @param CMSMS\internal\template_wrapper $template
 * @return mixed result of template conversion, or null
 */
function smarty_function_global_content($params, $template)
{
	$template->assign('gcb_params',$params);
	$txt = $template->fetch('cms_template:'.$params['name']);
	if( isset($params['assign']) ) {
		$template->assign(trim($params['assign']),$txt);
		return;
	}
	return $txt;
}

