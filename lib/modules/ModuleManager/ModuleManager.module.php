<?php
# ModuleManager: an addon module for CMS Made Simple to allow browsing
#  remotely stored modules, viewing information about them, and downloading
#  or upgrading
# Copyright (C) 2013-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
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

use CMSMS\LogicException;
use ModuleManager\Command;
use ModuleManager\operations;

define('MINIMUM_REPOSITORY_VERSION','1.5');

class ModuleManager extends CMSModule
{
    const _dflt_request_url = 'https://www.cmsmadesimple.org/ModuleRepository/request/v2/';
    private $_operations;

    public function GetName() { return get_class($this); }
    public function GetFriendlyName() { return $this->Lang('friendlyname'); }
    public function GetVersion() { return '2.2'; }
    public function GetHelp() { return $this->Lang('help'); }
    public function GetAuthor() { return 'calguy1000'; }
    public function GetAuthorEmail() { return 'calguy1000@cmsmadesimple.org'; }
    public function GetChangeLog() { return @file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'changelog.inc'); }
    public function IsPluginModule() { return FALSE; }
    public function HasAdmin() { return TRUE; }
    public function IsAdminOnly() { return TRUE; }
    public function GetAdminSection() { return 'extensions'; }
    public function GetAdminDescription() { return $this->Lang('admindescription'); }
    public function LazyLoadAdmin() { return TRUE; }
    public function MinimumCMSVersion() { return '2.2.3'; }
    public function InstallPostMessage() { return $this->Lang('postinstall'); }
    public function UninstallPostMessage() { return $this->Lang('postuninstall'); }
    public function UninstallPreMessage() { return $this->Lang('really_uninstall'); }
    public function VisibleToAdminUser() { return ($this->CheckPermission('Modify Site Preferences') || $this->CheckPermission('Modify Modules')); }

    /**
     * @internal
     */
    public function get_operations()
    {
        if( !$this->_operations ) $this->_operations = new operations( $this );
        return $this->_operations;
    }

    protected function _DisplayErrorPage($id, &$params, $returnid, $message='')
    {
        $smarty = CmsApp::get_instance()->GetSmarty();
        $smarty->assign('title_error', $this->Lang('error'));
        $smarty->assign('message', $message);
        $smarty->assign('link_back',$this->CreateLink($id,'defaultadmin',$returnid, $this->Lang('back_to_module_manager')));

        // Display the populated template
        echo $this->ProcessTemplate('error.tpl');
    }

    public function Install()
    {
        $this->SetPreference('module_repository',self::_dflt_request_url);
    }

    public function Upgrade($oldversion, $newversion)
    {
        $this->SetPreference('module_repository',self::_dflt_request_url);
    }

    public function DoAction($action, $id, $params, $returnid=-1)
    {
        @set_time_limit(9999);
        /*
          $smarty = CmsApp::get_instance()->GetSmarty(); OR $smarty = \CMSMS\internal\Smarty::get_instance();
          $smarty->assign($this->GetName(), $this);
          $smarty->assign('mod', $this);
        */
        return parent::DoAction( $action, $id, $params, $returnid );
    }

    public function HasCapability($capability,$params = [])
    {
        if( $capability == 'clicommands' ) return class_exists('CMSMS\\CLI\\App');
    }

    /**
     * @since 2.3
     * @throws LogicException
     * @param \CMSMS\CLI\App $app (exists only in App mode)
     * @return array
     */
    public function get_cli_commands( $app ) : array
    {
       if( parent::get_cli_commands($app) === null ) return [];

        $out = [];
        $out[] = new Command\PingModuleServerCommand( $app );
        $out[] = new Command\ModuleExistsCommand( $app );
        $out[] = new Command\ModuleExportCommand( $app );
        $out[] = new Command\ModuleImportCommand( $app );
        $out[] = new Command\ModuleInstallCommand( $app );
        $out[] = new Command\ModuleUninstallCommand( $app );
        $out[] = new Command\ModuleRemoveCommand( $app );
        $out[] = new Command\ListModulesCommand( $app );
        $out[] = new Command\ReposListCommand( $app );
        $out[] = new Command\ReposDependsCommand( $app );
        $out[] = new Command\ReposGetXMLCommand( $app );
        return $out;
    }
} // class

