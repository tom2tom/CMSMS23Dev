<?php
#procedure to assign a user to another group
#Copyright (C) 2004-2017 Ted Kulp <ted@cmsmadesimple.org>
#Copyright (C) 2018 The CMSMS Dev Team <coreteam@cmsmadesimple.org>
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

$CMS_ADMIN_PAGE=1;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';

check_login();

$urlext='?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
if (isset($_POST['cancel'])) {
    redirect('listusers.php'.$urlext);
//    return;
}

$userid = get_userid();
$access = check_permission($userid, 'Manage Groups');

$themeObject = cms_utils::get_theme_object();

if (!$access) {
//TODO some immediate popup    lang('needpermissionto', '"Manage Groups"');
    return;
}

$group_id = (isset($_GET['group_id'])) ? (int)$_GET['group_id'] : -1;

$gCms = cmsms();
$userops = $gCms->GetUserOperations();
$adminuser = ($userops->UserInGroup($userid, 1) || $userid == 1);
$message = '';

$db = $gCms->GetDb();
$smarty = CMSMS\internal\Smarty::get_instance();

if (!empty($_POST['filter'])) {
    $disp_group = cleanValue($_POST['groupsel']);
    cms_userprefs::set_for_user($userid, 'changegroupassign_group', $disp_group);
}
$disp_group = cms_userprefs::get_for_user($userid, 'changegroupassign_group', -1);

// always display the group pulldown
$groupops = $gCms->GetGroupOperations();
$tmp = new stdClass();
$tmp->name = lang('all_groups');
$tmp->id=-1;
$allgroups = [$tmp];
$groups = [$tmp];
$groupidlist = [];
$group_list = $groupops->LoadGroups();
foreach ($group_list as $onegroup) {
    $groupidlist[] = $onegroup->id;
    if ($onegroup->id == 1 && !$adminuser) {
        continue;
    }
    $allgroups[] = $onegroup;
    if ($disp_group == -1 || $disp_group == $onegroup->id) {
        $groups[] = $onegroup;
    }
}
$smarty->assign('group_list', $groups);
$smarty->assign('allgroups', $allgroups);
$smarty->assign('groupidlist', implode(',', $groupidlist));

if (isset($_POST['submit'])) {
    foreach ($groups as $onegroup) {
        if ($onegroup->id <= 0) {
            continue;
        }
        // Send the ChangeGroupAssignPre event
        \CMSMS\HookManager::do_hook('Core::ChangeGroupAssignPre',
             ['group' => $onegroup, 'users' => $userops->LoadUsersInGroup($onegroup->id)]
        );
        $query = 'DELETE FROM '.CMS_DB_PREFIX.'user_groups WHERE group_id = ? AND user_id != ?';
        $result = $db->Execute($query, array($onegroup->id,$userid));
        $iquery = 'INSERT INTO '.CMS_DB_PREFIX.
            'user_groups (group_id, user_id, create_date, modified_date) VALUES (?,?,NOW(),NOW())';

        cleanArray($_POST);
        foreach ($_POST as $key=>$value) {
            if (strncmp($key, 'ug', 2) == 0) {
                $keyparts = explode('_', $key);
                if ($keyparts[2] == $onegroup->id && $value == '1') {
                    $result = $db->Execute($iquery, [$onegroup->id,$keyparts[1]]);
                }
            }
        }

        \CMSMS\HookManager::do_hook('Core::ChangeGroupAssignPost',
            ['group' => $onegroup, 'users' => $userops->LoadUsersInGroup($onegroup->id)]
        );
        // put mention into the admin log
        audit($group_id, 'Assignment Group ID: '.$group_id, 'Changed');
    }

    // put mention into the admin log
    audit($userid, 'Assignment User ID: '.$userid, 'Changed');
    $message = lang('assignmentchanged');
    $gCms->clear_cached_files();
}

$query = 'SELECT u.user_id, u.username, ug.group_id FROM '.
    CMS_DB_PREFIX.'users u LEFT JOIN '.CMS_DB_PREFIX.
    'user_groups ug ON u.user_id = ug.user_id ORDER BY u.username';
$result = $db->Execute($query);

$user_struct = [];
while ($result && $row = $result->FetchRow()) {
    if (isset($user_struct[$row['user_id']])) {
        $str = &$user_struct[$row['user_id']];
        $str->group[$row['group_id']]=1;
    } else {
        $thisUser = new stdClass();
        $thisUser->group = [];
        if (!empty($row['group_id'])) {
            $thisUser->group[$row['group_id']] = 1;
        }
        $thisUser->id = $row['user_id'];
        $thisUser->name = $row['username'];
        $user_struct[$row['user_id']] = $thisUser;
    }
}
$smarty->assign('users', $user_struct);
$smarty->assign('adminuser', ($adminuser?1:0));

if (!empty($message)) {
    $themeObject->RecordNotice('success', $message);
}

$selfurl = basename(__FILE__);
$smarty->assign('selfurl', $selfurl);
$smarty->assign('urlext', $urlext);
$smarty->assign('disp_group', $disp_group);
$smarty->assign('user_id', $userid);
$smarty->assign('pagesubtitle', lang('groupassignments', $user_struct[$userid]->name));

include_once 'header.php';
$smarty->display('changeusergroup.tpl');
include_once 'footer.php';
