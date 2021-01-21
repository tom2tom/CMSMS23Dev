<?php
/*
Job: generate a notice about draft news item(s)
Copyright (C) 2017-2020 CMS Made Simple Foundation <foundation@cmsmadesimple.org>

This file is part of CMS Made Simple module: News

Refer to license and other details at the top of file News.module.php
*/
namespace News;

use CMSMS\AppSingle;
use CMSMS\Async\CronJob;
use CMSMS\Async\RecurType;
use News\DraftMessageAlert;
use CMS_DB_PREFIX;

class CreateDraftAlertJob extends CronJob
{
    public function __construct($params = [])
    {
        parent:: __construct($params);
        $this->name = 'News\\CreateDraftAlert';
        $this->frequency = RecurType::RECUR_15M;
    }

    /**
     * @ignore
     * @return int 0|1|2 indicating execution status
     */
    public function execute()
    {
        $time = time();
        $db = AppSingle::Db();
        $query = 'SELECT COUNT(news_id) FROM '.CMS_DB_PREFIX.'module_news WHERE status = \'draft\' AND (end_time IS NULL OR end_time=0 OR end_time > '.$time.')';
        $count = $db->GetOne($query);
        if ($count) {
            $alert = new DraftMessageAlert($count);
            $alert->save();
            return 2;
        }
        return 1;
    }
}
