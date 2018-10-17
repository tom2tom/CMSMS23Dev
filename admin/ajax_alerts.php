<?php
#ajax alerts processing
#Copyright (C) 2011-2018 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

use CMSMS\AdminAlerts\Alert;

$CMS_ADMIN_PAGE=1;
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';
$urlext='?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
try {
    $out = null;
    $uid = get_userid(FALSE);
    if( !$uid ) throw new Exception('Permission Denied'); // should be a 403, but meh.

    $op = cleanValue($_POST['op']);
    if( !$op ) $op = 'delete';
    $alert_name = cleanValue($_POST['alert']);

    switch( $op ) {
    case 'delete':
        $alert = Alert::load_by_name($alert_name);
        $alert->delete();
        break;
    default:
        throw new Exception('Unknown operation '.$op);
    }
    echo $out;
}
catch( Exception $e ) {
    // do 500 error.
    $handlers = ob_list_handlers();
    for ($cnt = 0; $cnt < count($handlers); $cnt++) { ob_end_clean(); }

    header('HTTP/1.0 500 '.$e->GetMessage());
    header('Status: 500 Server Error');
    echo $e->GetMessage();
}
exit;
