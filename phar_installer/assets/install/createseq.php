<?php

$table_ids = [
	'additional_users' => ['id' => 'additional_users_id'], //deprecated since 2.3
	'admin_bookmarks'  => ['id' => 'bookmark_id'],
	'content'          => ['id' => 'content_id'],
	'event_handlers'   => ['id' => 'handler_id', 'seq' => 'event_handler_seq'], //deprecated since 2.3
	'events'           => ['id' => 'event_id'],  //deprecated since 2.3
	'group_perms'      => ['id' => 'group_perm_id'], //deprecated since 2.3
	'groups'           => ['id' => 'group_id'],
	'permissions'      => ['id' => 'permission_id'],
	'users'            => ['id' => 'user_id'],
];

status_msg(ilang('install_update_sequences'));
foreach ($table_ids as $tablename => $tableinfo) {
	$sql = 'SELECT COALESCE(MAX(?),0) AS maxid FROM '.CMS_DB_PREFIX.$tablename;
	$max = $db->GetOne($sql,[$tableinfo['id']]);
	$tableinfo['seq'] = $tableinfo['seq'] ?? $tablename . '_seq';
	verbose_msg(ilang('install_updateseq',$tableinfo['seq']));
	$db->CreateSequence(CMS_DB_PREFIX.$tableinfo['seq'], $max);
}

