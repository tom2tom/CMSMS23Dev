<?php
# ModuleManager class: ..
# Copyright (C) 2017-2018 Robert Campbell <calguy1000@cmsmadesimple.org>
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

namespace ModuleManager;

use cms_utils;
use CmsLangOperations;
use CMSMS\CLI\App;
use CMSMS\CLI\GetOptExt\Command;
use CMSMS\HookManager;
use CMSMS\ModuleOperations;
use CmsNlsOperations;
use RuntimeException;
use function audit;

class ModuleExportCommand extends Command
{
    public function __construct( App $app )
    {
        parent::__construct( $app, 'moma-export' );
        $this->setDescription('Export a known, installed, and available module to XML format for sharing');
        $this->addOperand( new Operand( 'module', Operand::REQUIRED ) );
    }

    public function handle()
    {
        $ops = ModuleOperations::get_instance();
        $moma = cms_utils::get_module('ModuleManager');
        $module = $this->getOperand('module')->value();
        $modinstance = $ops->get_module_instance($module,'',TRUE);
        if( !is_object($modinstance) ) throw new RuntimeException('Could not instantiate module '.$module);

        $old_display_errors = ini_set('display_errors',0);
        CmsLangOperations::allow_nonadmin_lang(TRUE);
        CmsNlsOperations::set_language('en_US');
        HookManager::do_hook('ModuleManager::BeforeModuleExport', [ 'module_name' => $module, 'version' => $modinstance->GetVersion() ] );
        $xmltext = $moma->get_operations()->create_xml_package($modinstance,$message,$files);
        HookManager::do_hook('ModuleManager::AfterModuleExport', [ 'module_name' => $module, 'version' => $modinstance->GetVersion() ] );
        if( $old_display_errors !== FALSE ) ini_set('display_errors',$old_display_errors);

        $xmlname = $modinstance->GetName().'-'.$modinstance->GetVersion().'.xml';
        file_put_contents( $xmlname, $xmltext );

        audit('',$moma->GetName(),'Exported '.$modinstance->GetName().' to '.$xmlname);
        echo "Created: $xmlname\n";
    }
} // class
