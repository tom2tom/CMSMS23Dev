<?php
/*
AdminSearch module upgrade process
Copyright (C) 2012-2021 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
Thanks to Robert Campbell and all other contributors from the CMSMS Development Team.

This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>

CMS Made Simple is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of that license, or
(at your option) any later version.

CMS Made Simple is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of that license along with CMS Made Simple.
If not, see <https://www.gnu.org/licenses/>.
*/

use CMSMS\AppSingle;
use CMSMS\UserParams;

if (!function_exists('cmsms')) exit;

if (version_compare($oldversion,'1.1') < 1) {
    //enable deprecated class-aliases
    $tp1 = __DIR__.DIRECTORY_SEPARATOR.lib.DIRECTORY_SEPARATOR.'class.%s.php';
    $tp2 = CMS_ROOT_PATH.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'aliases'.DIRECTORY_SEPARATOR.'class.%s.php'; // into autoloader search-path
    foreach (['AdminSearch_tools','AdminSearch_slave'] as $nm) {
        $fp = sprintf($tp1, $nm);
        $tp = sprintf($tp2, $nm);
        copy($fp, $tp);
    }

    $me = $this->GetName();
    $userlist = AppSingle::UserOperations()->LoadUsers();
    foreach ($userlist as $user) {
        $userid = $user->id;
        $tmp = UserParams::get_for_user($userid,$me.'saved_search');
        if( $tmp ) {
            try {
                $init = unserialize($tmp,[]);
                $init += ['search_casesensitive' => false,];
                UserParams::set_for_user($userid,$me.'saved_search',serialize($init));
            }
            catch (Throwable $t) {
                //nothing here
            }
        }
    }
}