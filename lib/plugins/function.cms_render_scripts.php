<?php
#Plugin which aggregates accumulated javascript for use in a page or template
#Copyright (C) 2018-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

function smarty_function_cms_render_scripts( $params, $template )
{
	$combiner = CmsApp::get_instance()->GetScriptManager();
	$force = (isset($params['force'])) ? cms_to_bool($params['force']) : false;
	$nocache = (isset($params['nocache'])) ? cms_to_bool($params['nocache']) : false;
	$defer = (isset($params['defer'])) ? cms_to_bool($params['defer']) : true;
	if( $defer ) $defer = 'defer';

	$out = null;
	$filename = $combiner->render_scripts( PUBLIC_CACHE_LOCATION, $force );
	if( $nocache ) $filename .= '?t='.time();

	if( $filename ) {
		$fmt = "<script src=\"%s\" $defer></script>";
		$out = sprintf( $fmt, PUBLIC_CACHE_URL."/$filename" );
	}

	if( isset($params['assign']) ) {
		$template->assign( trim($params['assign']), $out );
	} else {
		return $out;
	}
}

function smarty_cms_help_function_cms_render_scripts()
{
	echo lang_by_realm('tags','help_function_render_scripts');
}

function smarty_cms_about_function_cms_render_scripts()
{
	echo <<<'EOS'
<p>Author: Robert Campbell &lt;calguy1000@cmsmadesimple.org&gt;</p>
<p>Version: 1.0</p>
<p>
Change History:<br />
None
</p>
EOS;
}

