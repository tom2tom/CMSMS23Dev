<?php
# DesignManager module action: clear locks
# Copyright (C) 2014-2018 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
# Thanks to Robert Campbell and all other contributors from the CMSMS Development Team.
# This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.

if( !isset($gCms) ) exit;
if( !$this->VisibleToAdminUser() ) exit;

$uid = get_userid(FALSE);
$type = (isset($params['type']) ) ? trim($params['type']) : 'template';
$is_admin = UserOperations::get_instance($uid,1);

$type = strtolower($type);
switch( $type ) {
case 'tpl':
case 'templates':
case 'template':
    $type = 'template';
    $this->SetCurrentTab('templates');
    break;
case 'css':
case 'stylesheets':
case 'stylesheet':
    $type = 'stylesheet';
    $this->SetCurrentTab('stylesheets');
    break;
default:
    $this->Redirect($id,'defaultadmin');
}

if( $is_admin ) {
    // clear all locks of type content
    $db = cmsms()->GetDb();
    $sql = 'DELETE FROM '.CMS_DB_PREFIX.CmsLock::LOCK_TABLE.' WHERE type = ?';
    $db->Execute($sql,[$type]);
    cms_notice("Cleared all $type locks");
} else {
    // clear only my locks
    CmsLockOperations::delete_for_user($type);
    cms_notice("Cleared his own $type locks");
}

$this->SetMessage($this->Lang('msg_lockscleared'));
$this->RedirectToAdminTab();

