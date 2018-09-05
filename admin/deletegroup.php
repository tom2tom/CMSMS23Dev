<?php
#procedure to delete an admin group
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

use CMSMS\Events;

if (!isset($_GET['group_id'])) {
    return;
}

$CMS_ADMIN_PAGE=1;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';

check_login();

$urlext='?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
$userid = get_userid();
if (!check_permission($userid, 'Manage Groups')) {
    cms_utils::get_theme_object()->ParkNotice('error', lang('needpermissionto', '"Manage Groups"'));
    redirect('listgroups.php'.$urlext);
}

$group_id = (int) $_GET['group_id'];
if ($group_id == 1) {
    // can't delete this group
    cms_utils::get_theme_object()->ParkNotice('error', lang('error_deletespecialgroup'));
    redirect('listgroups.php'.$urlext);
}

$gCms = cmsms();
$userops = $gCms->GetUserOperations();
if ($userops->UserInGroup($userid,$group_id)) {
    // can't delete a group to which the current user belongs
    cms_utils::get_theme_object()->ParkNotice('error', lang('cantremove')); //TODO
    redirect('listgroups.php'.$urlext);
}

$groupops = $gCms->GetGroupOperations();
$groupobj = $groupops->LoadGroupByID($group_id);

if ($groupobj) {
	$group_name = $groupobj->name;

	// now do the work
	Events::SendEvent('Core', 'DeleteGroupPre', [ 'group'=>&$groupobj ] );

	if ($groupobj->Delete()) {
		Events::SendEvent('Core', 'DeleteGroupPost', [ 'group'=>&$groupobj ] );
		// put mention into the admin log
		audit($group_id, 'Admin User Group: '.$group_name, 'Deleted');
	} else {
	    cms_utils::get_theme_object()->ParkNotice('error', lang('failure'));
	}
} else {
    cms_utils::get_theme_object()->ParkNotice('error', lang('invalid'));
}

redirect('listgroups.php'.$urlext);
