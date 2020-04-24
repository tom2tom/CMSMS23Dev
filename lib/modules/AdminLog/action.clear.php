<?php
/*
AdminLog module action: clear the log
Copyright (C) 2017-2020 CMS Made Simple Foundation <foundationcmsmadesimple.org>
This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
*/

if( !isset($gCms) ) exit;
if( !$this->CheckPermission('Clear Admin Log') ) exit;

$this->storage->clear();
unset($_SESSION['adminlog_filter']);
audit('','Admin log','Cleared');
$this->SetMessage($this->Lang('msg_cleared'));
$this->RedirectToAdminTab();
