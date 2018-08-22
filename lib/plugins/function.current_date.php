<?php
#Plugin to...
#Deprecated since 2.3
#Copyright (C) 2004-2018 Ted Kulp <ted@cmsmadesimple.org>
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

function smarty_function_current_date($params, $template) {
	$format = '%b %c, %Y';
	if( !empty($params['format']) ) $format = trim($params['format']);

	$string = strftime($format,time());
	if( !empty($params['ucwords']) ) $string = ucwords($string);

	$out = cms_htmlentities($string);
	if( !empty($params['assign']) ) {
		$template->assign(trim($params['assign']),$out);
		return;
	}
	return $out;
}

function smarty_cms_about_function_current_date()
{
	echo <<<'EOS'
<p>Author: Ted Kulp &lt;ted@cmsmadesimple.org&gt;</p>
<p>Version: 1.0</p>
<p>
Change History:<br/>
None
</p>
EOS;
}
