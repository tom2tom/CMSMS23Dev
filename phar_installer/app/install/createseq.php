<?php

if (isset($CMS_INSTALL_CREATE_TABLES)) {
    $table_ids = array(
        'additional_users'          => array('id' => 'additional_users_id'),
        'admin_bookmarks'           => array('id' => 'bookmark_id'),
        'content'                   => array('id' => 'content_id'),
        'content_props'             => array('id' => 'content_id'),
        'events'                    => array('id' => 'event_id'),
        'event_handlers'            => array('id' => 'handler_id', 'seq' => 'event_handler_seq'),
        'group_perms'               => array('id' => 'group_perm_id'),
        'groups'                    => array('id' => 'group_id'),
        'users'                     => array('id' => 'user_id'),
        'userplugins'               => array('id' => 'userplugin_id'),
        'permissions'               => array('id' => 'permission_id')
    );

    status_msg(ilang('install_update_sequences'));
    foreach ($table_ids as $tablename => $tableinfo)
    {
        $sql = 'SELECT COALESCE(MAX(?),0) AS maxid FROM '.CMS_DB_PREFIX.$tablename;
        $max = $db->GetOne($sql,array($tableinfo['id']));
        $tableinfo['seq'] = $tableinfo['seq'] ?? $tablename . '_seq';
        verbose_msg(ilang('install_updateseq',$tableinfo['seq']));
        $db->CreateSequence(CMS_DB_PREFIX.$tableinfo['seq'], $max);
    }
}

?>
