<?php

use CMSMS\Group;

status_msg('Upgrading schema for CMSMS 2.1.1');

//$gCms = cmsms();
$perms = ['Manage Designs','Manage My Settings','Manage My Account','Manage My Bookmarks'];
$all_perms = [];
foreach( $perms as $one_perm ) {
    $permission = new CmsPermission();
    $permission->source = 'Core';
    $permission->name = $one_perm;
    $permission->text = $one_perm;
    $permission->save();
    $all_perms[$one_perm] = $permission;
}

$groups = Group::load_all();
foreach( $groups as $group ) {
    if( strtolower($group->name == 'designer') ) {
        $group->GrantPermission('Manage Designs');
    }
    $group->GrantPermission('Manage My Settings');
    $group->GrantPermission('Manage My Account');
    $group->GrantPermission('Manage My Bookmarks');
}

