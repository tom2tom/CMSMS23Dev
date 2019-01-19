<?php
# DesignManager module uninstallation process.
# Copyright (C) 2012-2018 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

use CMSMS\Events;
use CMSMS\Group;

if (!isset($gCms)) {
    exit;
}

$dict = NewDataDictionary($db);
$sqlarray = $dict->DropTableSQL(CMS_DB_PREFIX.CmsLayoutTemplateType::TABLENAME);
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->DropTableSQL(CMS_DB_PREFIX.CmsLayoutTemplateCategory::TABLENAME);
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->DropTableSQL(CMS_DB_PREFIX.CmsLayoutTemplateCategory::TPLTABLE);
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->DropTableSQL(CMS_DB_PREFIX.CmsLayoutStylesheet::TABLENAME);
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->DropTableSQL(CMS_DB_PREFIX.CmsLayoutCollection::TABLENAME);
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->DropTableSQL(CMS_DB_PREFIX.CmsLayoutCollection::TPLTABLE);
$dict->ExecuteSQLArray($sqlarray);
$sqlarray = $dict->DropTableSQL(CMS_DB_PREFIX.CmsLayoutCollection::CSSTABLE);
$dict->ExecuteSQLArray($sqlarray);

$group = new Group();
$group->name = 'Designer';
try {
    Events::SendEvent('Core', 'DeleteGroupPre', ['group'=>&$group]);
    if ($group->Delete()) {
        Events::SendEvent('Core', 'DeleteGroupPost', ['group'=>&$group]);
    }
} catch (Exception $e) {
    return 2;
}

$this->RemovePreference();

$this->RemovePermission('Add Templates');
$this->RemovePermission('Manage Designs');
$this->RemovePermission('Manage Stylesheets');
$this->RemovePermission('Modify Templates');

// unregister events
foreach([
 'AddDesignPost',
 'AddDesignPre',

 'AddStylesheetPost',
 'AddStylesheetPre',
 'AddTemplatePost',
 'AddTemplatePre',
 'AddTemplateTypePost',
 'AddTemplateTypePre',

 'DeleteDesignPost',
 'DeleteDesignPre',

 'DeleteStylesheetPost',
 'DeleteStylesheetPre',
 'DeleteTemplatePost',
 'DeleteTemplatePre',
 'DeleteTemplateTypePost',
 'DeleteTemplateTypePre',

 'EditDesignPost',
 'EditDesignPre',

 'EditStylesheetPost',
 'EditStylesheetPre',
 'EditTemplatePost',
 'EditTemplatePre',
 'EditTemplateTypePost',
 'EditTemplateTypePre',

 'StylesheetPostCompile',
 'StylesheetPostRender',
 'StylesheetPreCompile',

 'TemplatePostCompile',
 'TemplatePreCompile',
 'TemplatePreFetch',
] as $name) {
    Events::RemoveEvent('Core',$name);
}
