<?php
# Class SecurityCheckTask: for periodic checks for and warnings about ...
# Copyright (C) 2016-2020 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

namespace CMSMS\tasks;

use CMSMS\AdminAlerts\TranslatableAlert;
use CMSMS\AppParams;
use CMSMS\Async\CronJob;
use CMSMS\Async\RecurType;
use const CMS_ROOT_PATH;
use const CONFIG_FILE_LOCATION;
use function cms_join_path;

class SecurityCheckTask extends CronJob
{
    public function __construct()
    {
        parent::__construct();
        $this->frequency = RecurType::RECUR_DAILY;
    }

    public function execute()
    {
        // check if config is writable
        if( is_writable(CONFIG_FILE_LOCATION) ) {
            $alert = new TranslatableAlert('Modify Site Preferences');
            $alert->name = self::class.'config'; // so that there can only ever be one alert of this type at a time.
            $alert->msgkey = 'config_writable';
            $alert->priority = $alert::PRIORITY_HIGH;
            $alert->titlekey = 'security_issue';
            $alert->save();
        }

        // check if install-file exists
        $pattern = cms_join_path(CMS_ROOT_PATH,'cmsms-*-install.php');
        $files = glob($pattern);
        if( $files ) {
            $fn = basename($files[0]);
            $alert = new TranslatableAlert('Modify Site Preferences');
            $alert->name = self::class.'install';
            $alert->msgkey = 'installfileexists';
            $alert->msgargs = $fn;
            $alert->priority = $alert::PRIORITY_HIGH;
            $alert->titlekey = 'security_issue';
            $alert->save();
        }

        // check if mail is configured
        // not really a security issue... but meh, it saves another class.
        if( !AppParams::get('mail_is_set',false) ) {
            $alert = new TranslatableAlert('Modify Site Preferences');
            $alert->name = self::class.'mail';
            $alert->msgkey = 'info_mail_notset';
            $alert->priority = $alert::PRIORITY_HIGH;
            $alert->titlekey = 'config_issue';
            $alert->save();
        }
    }
}

\class_alias('CMSMS\tasks\SecurityCheckTask', 'CmsSecurityCheckTask', false);
