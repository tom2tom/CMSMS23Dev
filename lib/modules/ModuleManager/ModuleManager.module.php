<?php
#BEGIN_LICENSE
#-------------------------------------------------------------------------
# Module: ModuleManager (c) 2013 by Robert Campbell
#         (calguy1000@cmsmadesimple.org)
#  An addon module for CMS Made Simple to allow browsing remotely stored
#  modules, viewing information about them, and downloading or upgrading
#
#-------------------------------------------------------------------------
# CMS - CMS Made Simple is (c) 2005 by Ted Kulp (wishy@cmsmadesimple.org)
# Visit our homepage at: http://www.cmsmadesimple.org
#
#-------------------------------------------------------------------------
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
#
#-------------------------------------------------------------------------
#END_LICENSE

define('MINIMUM_REPOSITORY_VERSION','1.5');

class ModuleManager extends CMSModule
{
    const _dflt_request_url = 'https://www.cmsmadesimple.org/ModuleRepository/request/v2/';
    private $_operations;

    function GetName() { return get_class($this); }
    function GetFriendlyName() { return $this->Lang('friendlyname'); }
    function GetVersion() { return '2.2'; }
    function GetHelp() { return $this->Lang('help'); }
    function GetAuthor() { return 'calguy1000'; }
    function GetAuthorEmail() { return 'calguy1000@hotmail.com'; }
    function GetChangeLog() { return file_get_contents(__DIR__.'/changelog.inc'); }
    function IsPluginModule() { return FALSE; }
    function HasAdmin() { return TRUE; }
    function IsAdminOnly() { return TRUE; }
    function GetAdminSection() { return 'siteadmin'; }
    function GetAdminDescription() { return $this->Lang('admindescription'); }
    function LazyLoadAdmin() { return TRUE; }
    function MinimumCMSVersion() { return '2.2.3'; }
    function InstallPostMessage() { return $this->Lang('postinstall'); }
    function UninstallPostMessage() { return $this->Lang('postuninstall'); }
    function UninstallPreMessage() { return $this->Lang('really_uninstall'); }
    function VisibleToAdminUser() { return ($this->CheckPermission('Modify Site Preferences') || $this->CheckPermission('Modify Modules')); }

    /**
     * @internal
     */
    public function get_operations()
    {
        if( !$this->_operations ) $this->_operations = new \ModuleManager\operations( $this );
        return $this->_operations;
    }

    protected function _DisplayErrorPage($id, &$params, $returnid, $message='')
    {
        $this->smarty->assign('title_error', $this->Lang('error'));
        $this->smarty->assign('message', $message);
        $this->smarty->assign('link_back',$this->CreateLink($id,'defaultadmin',$returnid, $this->Lang('back_to_module_manager')));

        // Display the populated template
        echo $this->ProcessTemplate('error.tpl');
    }

    function Install()
    {
        $this->SetPreference('module_repository',ModuleManager::_dflt_request_url);
    }

    function Upgrade($oldversion, $newversion)
    {
        $this->SetPreference('module_repository',ModuleManager::_dflt_request_url);
    }

    function DoAction($action, $id, $params, $returnid=-1)
    {
        @set_time_limit(9999);
        /*
          $smarty = \CMSMS\internal\Smarty::get_instance();
          $smarty->assign($this->GetName(), $this);
          $smarty->assign('mod', $this);
        */
        parent::DoAction( $action, $id, $params, $returnid );
    }

    public function HasCapability($capability,$params = array())
    {
        if( $capability == 'clicommands' ) return true;
    }

    public function get_cli_commands( $app )
    {
        if( ! $app instanceof \CMSMS\CLI\App ) throw new \LogicException(__METHOD__.' Called from outside of cmscli');
        if( !class_exists('\\CMSMS\\CLI\\GetOptExt\\Command') ) throw new \LogicException(__METHOD__.' Called from outside of cmscli');

        $out = [];
        $out[] = new \ModuleManager\PingModuleServerCommand( $app );
        $out[] = new \ModuleManager\ModuleExistsCommand( $app );
        $out[] = new \ModuleManager\ModuleExportCommand( $app );
        $out[] = new \ModuleManager\ModuleImportCommand( $app );
        $out[] = new \ModuleManager\ModuleInstallCommand( $app );
        $out[] = new \ModuleManager\ModuleUninstallCommand( $app );
        $out[] = new \ModuleManager\ModuleRemoveCommand( $app );
        $out[] = new \ModuleManager\ListModulesCommand( $app );
        $out[] = new \ModuleManager\ReposListCommand( $app );
        $out[] = new \ModuleManager\ReposDependsCommand( $app );
        $out[] = new \ModuleManager\ReposGetXMLCommand( $app );
        return $out;
    }
} // end of class

#
# EOF
#
