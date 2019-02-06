<?php
#procedure to display a user's bookmarks
#Copyright (C) 2004-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

$CMS_ADMIN_PAGE = 1;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'include.php';

check_login();

$urlext = '?'.CMS_SECURE_PARAM_NAME.'='.$_SESSION[CMS_USER_KEY];
$userid = get_userid();
$access = check_permission($userid, 'Manage My Bookmarks'); //TODO or 'Manage Bookmarks' or always
$padd = $access || check_permission($userid, 'Add Bookmarks');

$bookops = CMSMS\BookmarkOperations::get_instance();
$marklist = $bookops->LoadBookmarks($userid);
$n = count($marklist);
$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;
$limit = 20;

if ($n > $limit) {
	$pagination = pagination($page, $n, $limit); //TODO
	$minsee = $page * $limit - $limit;
	$maxsee = $page * $limit - 1;
} else {
	$pagination = null;
	$minsee = 0;
	$maxsee = $n;
}

$themeObject = cms_utils::get_theme_object();

$iconadd = $themeObject->DisplayImage('icons/system/newobject.gif', lang('addbookmark'),'','','systemicon');
$iconedit = $themeObject->DisplayImage('icons/system/edit.gif', lang('edit'),'','','systemicon');
$icondel = $themeObject->DisplayImage('icons/system/delete.gif', lang('delete'),'','','systemicon');

$smarty = CMSMS\internal\Smarty::get_instance();

$smarty->assign([
	'access' => $access,
	'addurl' => 'addbookmark.php',
	'deleteurl' => 'deletebookmark.php',
	'editurl' => 'editbookmark.php',
	'iconadd' => $iconadd,
	'icondel' => $icondel,
	'iconedit' => $iconedit,
	'marklist' => $marklist,
	'maxsee' => $maxsee,
	'minsee' => $minsee,
	'padd' => $padd,
	'pagination' => $pagination,
	'urlext' => $urlext,
]);

include_once 'header.php';
$smarty->display('listbookmarks.tpl');
include_once 'footer.php';
