<?php
#CMS - CMS Made Simple
#(c)2004 by Ted Kulp (wishy@users.sf.net) and (c) 2016 by Robert Campbell (calguy1000@cmsmadesimple.org)
#Visit our homepage at: http://www.cmsmadesimple.org
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
#along with this program; if not, write to the Free Software
#Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
#$Id$

namespace CMSMS;

$CMS_ADMIN_PAGE=1;
$CMS_LOGIN_PAGE=1;

require_once("../lib/include.php");
$gCms = \CmsApp::get_instance();
$db = $gCms->GetDb();
$login_ops = \CMSMS\LoginOperations::get_instance();
$params = [];

$auth_module = \ModuleOperations::get_instance()->GetAdminLoginModule();
if( !$auth_module ) throw new \LogicException('FATAL: Could not find a suitable authentication module');
$action = 'admin_login';
$id = '__';
$params = [];
$smarty = \CmsApp::get_instance()->GetSmarty();

if( isset( $_REQUEST['mact'] ) ) {
    $parts = explode(',', cms_htmlentities( $_REQUEST['mact'] ), 4 );
    $module = (isset($parts[0])?trim($parts[0]):null);
    $id = (isset($parts[1])?trim($parts[1]):$id);
    $action = (isset($parts[2])?trim($parts[2]):$action);

    if( $module != $auth_module->GetName() ) throw new \RuntimeException('Invalid module in MACT from login module');
    $params = \ModuleOperations::get_instance()->GetModuleParameters( $id );
}

cms_admin_sendheaders();
header("Content-Language: " . \CmsNlsOperations::get_current_language());
$content = $auth_module->DoActionBase( $action, $id, $params, null, $smarty );
$theme_object = \cms_utils::get_theme_object();
$theme_object->SetTitle( lang('logintitle') );
$theme_object->set_content( $content );
echo $theme_object->do_loginpage( 'login' );
return;
