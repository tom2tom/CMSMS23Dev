<?php
# CMSContentManager module ajax-processor action
# Coopyright (C) 2013-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
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

use CMSContentManager\ContentListBuilder;
use CMSMS\ContentOperations;

if( !isset($gCms) ) exit;
if( !$this->CanEditContent() ) exit;

$term = null;
$out = [];

if( isset($_REQUEST['term']) ) {
    // find all pages with this text...
    // that this user can edit.
    $term = trim( strip_tags( filter_var( $_REQUEST['term'], FILTER_SANITIZE_STRING ) ) );
}
if( $term ) {
    $query = 'SELECT content_id,hierarchy,content_name,menu_text,page_url,content_alias FROM '.CMS_DB_PREFIX.'content
              WHERE (content_name LIKE ? OR menu_text LIKE ? OR page_url LIKE ? OR content_alias LIKE ?)';
    $str ='%'.$term.'%';
    $parms = [ $str, $str, $str, $str ];

    if( !($this->CheckPermission('Manage All Content') || $this->CheckPermission('Modify Any Page')) ) {
        $pages = author_pages(get_userid(FALSE));
        if( !$pages ) return;

        // query only these pages.
        $query .= ' AND content_id IN ('.implode(',',$pages).')';
    }

    $list = $db->GetArray($query,$parms);
    if( $list ) {
        $builder = new ContentListBuilder($this);
        $builder->expand_all(); // it'd be cool to open all parents to each item.
        $contentops = ContentOperations::get_instance();
        foreach( $list as $row ) {
            $label = $contentops->CreateFriendlyHierarchyPosition($row['hierarchy']);
            $label = $row['content_name'].' / '.$row['menu_text'];
            if( $row['content_alias'] ) $label .= ' / '.$row['content_alias'];
            if( $row['page_url'] ) $label .= ' / '.$row['page_url'];
            $out[] = ['label'=>$label,'value'=>$row['content_id']];
        }
    }
}

echo json_encode($out);
exit;

