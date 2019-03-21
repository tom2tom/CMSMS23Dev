<?php
#admin functions: site-content export/import
#Copyright (C) 2018-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

/*
This file is used during site installation (among other uses).
So API's, classes, methods, globals etc must be valid during installation
as well as normal operation.
*/
//install-only methods - admin export ok?

use CMSMS\Database\Connection;
use CMSMS\StylesheetOperations;
use CMSMS\TemplateOperations;
use function cms_installer\lang;

const CONTENT_DTD_VERSION = '0.7';
const CONTENT_DTD_MINVERSION = '0.7';

/**
 *
 * @param XMLWriter $xwm
 * @param Connection $db database connection
 * @param array $structarray
 * @param string $thistype
 * @param int $indent
 */
function fill_section(XMLWriter $xwm, Connection $db, array $structarray, string $thistype, int $indent)
{
	$pref = "\n".str_repeat("\t", $indent);
	$props = $structarray[$thistype];

	if (!empty($props['table'])) {
		$contents = reset($props['subtypes']);
		$fields = implode(',',array_keys($contents));
		$sql = 'SELECT '.$fields.' FROM '.CMS_DB_PREFIX.$props['table'];
	} elseif (!empty($props['sql'])) {
		$sql = sprintf($props['sql'], CMS_DB_PREFIX);
	} elseif (empty($props['subtypes'])) {
		$sql = '';
	} else {
		$xwm->text($pref);
		$xwm->startElement($thistype);
		foreach ($props['subtypes'] as $one=>$dat) {
			fill_section($xwm, $db, $props['subtypes'], $one, $indent+1);
		}
		$xwm->text($pref);
		$xwm->endElement(); //$thistype
	}
	if ($sql) {
		$rows = $db->getArray($sql);
		if ($rows) {
			$xwm->text($pref);
			$xwm->startElement($thistype);
			$name = key($props['subtypes']);
			foreach ($rows as $row) {
				$xwm->text($pref."\t");
				$xwm->startElement($name);
				foreach ($row as $key=>$val) {
					if (isset($props['subtypes'][$name][$key])) {
						$A = $props['subtypes'][$name][$key];
						if ((empty($A['keeps']) || in_array($val, $A['keeps'])) &&
							($val || !isset($A['notempty']))) {
							$xwm->text($pref."\t\t");
							if ($val && isset($A['isdata']) && is_string($val) && !is_numeric($val)) {
								$xwm->startElement($key);
								$xwm->writeCdata(htmlspecialchars($val, ENT_XML1));
								$xwm->endElement();
							} else {
								$xwm->writeElement($key, (string)$val);
							}
						}
					}
				}
				$xwm->text($pref."\t");
				$xwm->endElement();
			}
			$xwm->text($pref);
			$xwm->endElement(); //$thistype
		}
	}
}

/**
 * Export site content (pages, templates, designs, styles etc) to XML file.
 * Support files (in the uploads folder) and UDT's (in the assets/user_plugins folder)
 * are recorded as such, and will be copied into the specified $filesfolder if it exists.
 * Otherwise, that remains a manual task.
 *
 * @param string $xmlfile filesystem path of file to use
 * @param string $filesfolder path of installer-tree folder which will contain any 'support' files
 * @param Connection $db database connection
 */
function export_content(string $xmlfile, string $filesfolder, Connection $db)
{
/*	data arrangement
	mostly, table- and field-names must be manually reconciled with database schema
	optional sub-key parameters:
	 isdata >> process field value via htmlspecialchars($val, ENT_XML1) to prevent parser confusion
	 optional >> ignore/omit a field whose value is falsy i.e. optional item in the dtd
     keeps >> array of field-value(s) which will be included (subject to optional)
*/
	$skeleton = [
     'designs' => [
      'table' => 'layout_designs',
      'subtypes' => [
       'design' => [
        'id' => [],
        'name' => [],
        'description' => ['optional' => 1],
        'dflt' => ['optional'=>1],
       ]
      ]
     ],
     'stylesheets' => [
      'table' => 'layout_stylesheets',
      'subtypes' => [
       'stylesheet' => [
        'id' => [],
        'name' => [],
        'description' => ['optional' => 1],
        'media_type' => ['optional' => 1],
        'content' => ['isdata' => 1],
        'contentfile' => ['optional' => 1],
       ]
      ]
     ],
     'designstyles' => [
      'sql' => 'SELECT * FROM %slayout_design_cssassoc ORDER BY css_id,item_order',
      'subtypes' => [
       'designcss' => [
        'design_id' => [],
        'css_id' => [],
        'item_order' => ['optional' => 1],
       ]
      ]
     ],
     'tpltypes' => [
      'sql' => 'SELECT * FROM %slayout_tpl_type WHERE originator=\'__CORE__\' ORDER BY name',
      'subtypes' => [
       'tpltype' => [
        'id' => [],
        'originator' => [],
        'name' => [],
        'dflt_contents' => ['isdata' => 1, 'optional' => 1],
        'description' => ['optional' => 1],
        'lang_cb' => ['optional' => 1],
        'dflt_content_cb' => ['optional' => 1],
        'help_content_cb' => ['optional' => 1],
        'has_dflt' => ['optional' => 1],
        'requires_contentblocks' => ['optional' => 1],
        'one_only' => ['optional' => 1],
        'owner' => [],
       ]
      ]
     ],
     'categories' => [
      'table' => 'layout_tpl_categories',
      'subtypes' => [
       'category' => [
        'id' => [],
        'name' => [],
        'description' => ['optional' => 1],
        'item_order' => ['optional' => 1],
       ]
      ]
     ],
     'templates' => [
      'sql' => 'SELECT * FROM %slayout_templates WHERE originator=\'__CORE__\' ORDER BY name',
      'subtypes' => [
       'template' => [
        'id' => [],
        'originator' => [],
        'name' => [],
        'content' => ['isdata'=>1],
        'description' => ['optional' => 1],
        'type_id' => [],
        'owner_id' => [],
        'type_dflt' => ['optional' => 1],
        'listable' => ['optional' => 1],
        'contentfile' => ['optional' => 1],
       ]
      ]
     ],
     'designtemplates' => [
      'sql' => 'SELECT * FROM %slayout_design_tplassoc ORDER BY tpl_id,tpl_order',
      'subtypes' => [
       'designtpl' => [
        'design_id' => [],
        'tpl_id' => [],
        'tpl_order' => ['optional' => 1],
       ]
      ]
     ],
     'categorytemplates' => [
      'sql' => 'SELECT * FROM %slayout_cat_tplassoc ORDER BY tpl_id,tpl_order',
      'subtypes' => [
       'cattpl' => [
        'category_id' => [],
        'tpl_id' => [],
        'tpl_order' => ['optional' => 1],
       ]
      ]
     ],
     'pages' => [
      'sql' => 'SELECT * FROM %scontent ORDER BY parent_id,content_id',
      'subtypes' => [
       'page' => [
        'content_id' => [],
        'content_name' => [],
        'content_alias' => [],
        'type' => [],
        'template_id' => [],
        'parent_id' => [],
        'active' => ['keeps'=>[1]],
        'default_content' => ['keeps'=>[1]],
        'show_in_menu' => ['keeps'=>[1]],
        'menu_text' => ['isdata'=>1],
        'cachable' => ['keeps'=>[1]],
       ]
      ]
     ],
     'properties' => [
      'table' => 'content_props',
      'subtypes' => [
       'property' => [
        'content_id' => [],
        'prop_name' => [],
        'content' => ['isdata'=>1],
       ]
      ]
     ],
    ];

	@unlink($xmlfile);

	//worker-object
	$xwm = new XMLWriter();
	$xwm->openMemory();
	$xwm->setIndent(false); //self-managed indentation

	$xw = new XMLWriter();
	$xw->openUri('file://'.$xmlfile);
	$xw->setIndent(true);
	$xw->setIndentString("\t");
	$xw->startDocument('1.0', 'UTF-8');

	//these data must be manually reconciled with $skeleton[] above
	$xw->writeDtd('cmsmssitedata', null, null, '
 <!ELEMENT dtdversion (#PCDATA)>
 <!ELEMENT designs (design+)>
 <!ELEMENT design (id,name,description?,dflt?)>
 <!ELEMENT id (#PCDATA)>
 <!ELEMENT name (#PCDATA)>
 <!ELEMENT description (#PCDATA)>
 <!ELEMENT dflt (#PCDATA)>
 <!ELEMENT stylesheets (stylesheet+)>
 <!ELEMENT stylesheet (id,name,description?,media_type?,content,contentfile?)>
 <!ELEMENT media_type (#PCDATA)>
 <!ELEMENT content (#PCDATA)>
 <!ELEMENT contentfile (#PCDATA)>
 <!ELEMENT designstyles (designcss+)>
 <!ELEMENT designcss (design_id,css_id,item_order)>
 <!ELEMENT design_id (#PCDATA)>
 <!ELEMENT css_id (#PCDATA)>
 <!ELEMENT item_order (#PCDATA)>
 <!ELEMENT tpltypes (tpltype+)>
 <!ELEMENT tpltype (id,originator,name,dflt_contents?,description?,lang_cb?,dflt_content_cb?,help_content_cb?,has_dflt?,requires_contentblocks?,one_only?,owner)>
 <!ELEMENT originator (#PCDATA)>
 <!ELEMENT dflt_contents (#PCDATA)>
 <!ELEMENT lang_cb (#PCDATA)>
 <!ELEMENT dflt_content_cb (#PCDATA)>
 <!ELEMENT help_content_cb (#PCDATA)>
 <!ELEMENT has_dflt (#PCDATA)>
 <!ELEMENT requires_contentblocks (#PCDATA)>
 <!ELEMENT one_only (#PCDATA)>
 <!ELEMENT owner (#PCDATA)>
 <!ELEMENT categories (category+)>
 <!ELEMENT category (id,name,description?,item_order?)>
 <!ELEMENT templates (template)>
 <!ELEMENT template (id,originator,name,content,description?,type_id?,owner_id?,type_dflt?,listable?,contentfile?)>
 <!ELEMENT type_id (#PCDATA)>
 <!ELEMENT owner_id (#PCDATA)>
 <!ELEMENT type_dflt (#PCDATA)>
 <!ELEMENT listable (#PCDATA)>
 <!ELEMENT designtemplates (designtpl+)>
 <!ELEMENT designtpl (design_id,tpl_id,tpl_order?)>
 <!ELEMENT tpl_id (#PCDATA)>
 <!ELEMENT tpl_order (#PCDATA)>
 <!ELEMENT categorytemplates (cattpl+)>
 <!ELEMENT cattpl (category_id,tpl_id,tpl_order?)>
 <!ELEMENT pages (page+)>
 <!ELEMENT page (content_id,content_name,content_alias?,type,template_id,parent_id,active?,default_content?,show_in_menu?,menu_text?,cachable?)>
 <!ELEMENT content_id (#PCDATA)>
 <!ELEMENT content_name (#PCDATA)>
 <!ELEMENT content_alias (#PCDATA)>
 <!ELEMENT type (#PCDATA)>
 <!ELEMENT template_id (#PCDATA)>
 <!ELEMENT parent_id (#PCDATA)>
 <!ELEMENT active (#PCDATA)>
 <!ELEMENT default_content (#PCDATA)>
 <!ELEMENT show_in_menu (#PCDATA)>
 <!ELEMENT menu_text (#PCDATA)>
 <!ELEMENT cacheable (#PCDATA)>
 <!ELEMENT properties (property+)>
 <!ELEMENT property (content_id,prop_name,content)>
 <!ELEMENT prop_name (#PCDATA)>
 <!ELEMENT files (sourcedir?,file+)>
 <!ELEMENT file (name,topath,(frompath|embedded),content?)>
 <!ELEMENT topath (#PCDATA)>
 <!ELEMENT frompath (#PCDATA)>
 <!ELEMENT embedded (#PCDATA)>
 <!ELEMENT userplugins (sourcedir?,file+)>
 <!ELEMENT file (name,(frompath|embedded),content?)>
');

	$xw->startElement('cmsmssitedata');
	$xw->writeElement('dtdversion', CONTENT_DTD_VERSION);

	foreach ($skeleton as $one=>$props) {
		fill_section($xwm, $db, $skeleton, $one, 1);
		$xw->writeRaw($xwm->flush());
	}

	$xw->text("\n");

	$copynow = is_dir($filesfolder);
	$config = cms_config::get_instance();
	$frombase = $config['uploads_path'];
	if(is_dir($frombase)) {
		$skip = strlen($frombase) + 1;

		$xw->startElement('files');
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($frombase,
				FilesystemIterator::KEY_AS_PATHNAME |
				FilesystemIterator::FOLLOW_SYMLINKS |
				FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY |
			RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iter as $p=>$info) {
			if (!$info->isDir()) {
				$name = $info->getBasename();
				if (fnmatch('index.htm?', $name)) continue;
				$tail = substr($p, $skip);
				if ($copynow) {
					$tp = $filesfolder.DIRECTORY_SEPARATOR.$tail;
					$dir = dirname($tp);
					@mkdir($dir, 0771, true);
					@copy($p, $tp);
				}
				$xw->startElement('file');
				$xw->writeElement('name', $name);
				//TODO if !$copynow, consider embedding some files as base64_encoded esp. if only a few
				$td = dirname($tail);
				if ($td == '.') $td = '';
				$xw->writeElement('frompath', $td);
				$xw->writeElement('topath', $td);
				$xw->endElement(); // file
			}
		}
		$xw->endElement(); // files
	}

	$frombase =	CMS_ASSETS_PATH.DIRECTORY_SEPARATOR.'user_plugins'.DIRECTORY_SEPARATOR;
	$skip = strlen($frombase);
	if ($copynow) {
		$dir = $filesfolder.DIRECTORY_SEPARATOR.'user_plugins';
		@mkdir($dir, 0771, true);
		$copycount = 0;
	}

	$xw->startElement('userplugins');
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($frombase,
			FilesystemIterator::KEY_AS_PATHNAME |
			FilesystemIterator::FOLLOW_SYMLINKS |
			FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::LEAVES_ONLY |
		RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iter as $p=>$info) {
		if (!$info->isDir()) {
			$name = $info->getBasename();
			if (!endswith($name, '.php')) continue;
			if ($copynow) {
				@copy($p, $dir.DIRECTORY_SEPARATOR.$name);
				++$copycount;
			}
			$xw->startElement('file');
			$xw->writeElement('name', $name);
			//TODO if !$copynow, consider embedding some files as htmlspecialchars-encoded esp. if only a few
/*			$tail = substr($p, $skip);
            $td = dirname($tail);
            if ($td == '.') $td = '';
			$xw->writeElement('frompath', $td);
*/
			$xw->writeElement('frompath', '');
			$xw->endElement(); // file
		}
	}
	$xw->endElement(); // userplugins
	if ($copynow && $copycount == 0) {
		@rmdir($dir);
	}

	$xw->endElement(); // cmsmsinstall
	$xw->endDocument();
	$xw->flush(false);
}

/**
 * This method is used during site installation (among other uses)
 *
 * @global type $CMS_INSTALL_PAGE
 * @param string $xmlfile filesystem path of file to import
 * @param string $filesfolder Optional 'non-default' filesystem path of folder
 *  containing 'support' files e.g. images, iconfonts.
 * @return string status/error message or ''
 */
function import_content(string $xmlfile, string $filesfolder = '') : string
{
	// security checks right here, to supplement upstream/external
	global $CMS_INSTALL_PAGE;
	if (isset($CMS_INSTALL_PAGE)) {
		$runtime = false;
		//NOTE must conform this class with installer
		$valid = class_exists('cms_installer\wizard\wizard'); //TODO some other check too
	} else {
		$runtime = true;
		$uid = get_userid(false);
		if ($uid) {
			$valid = check_permission($uid,'Manage All Content');
		} else {
			// TODO etc e.g. when force-feeding, maybe async
			$valid = false;
		}
	}
	if (!$valid) {
		return ''; //silent exit
	}

	libxml_use_internal_errors(true);
	$xml = simplexml_load_file($xmlfile, 'SimpleXMLElement', LIBXML_NOCDATA);
	if ($xml === false) {
		if ($runtime) {
			$val = 'Failed to load file '.$xmlfile; //TODO lang('')
		} else {
			$val = lang('error_filebad',$xmlfile);
		}
		foreach (libxml_get_errors() as $error) {
			$val .= "\n".'Line '.$error->line.': '.$error->message;
		}
		libxml_clear_errors();
		return $val;
	}

	$val = (string)$xml->dtdversion;
	if (version_compare($val, CONTENT_DTD_MINVERSION) < 0) {
		if ($runtime) {
			return 'Invalid file format';
		} else {
			return lang('error_filebad',$xmlfile);
		}
	}

	$designs = [];
	$types = [];
	$categories = [];
	$templates = [];
	$styles = [];
	$pages = [-1 => -1];
	$pageobs = [];

	foreach ($xml->children() as $typenode) {
		if ($typenode->count() > 0) {
			switch ($typenode->getName()) {
				case 'designs':
					if (!$runtime) {
						verbose_msg(lang('install_default_designs'));
					}
					foreach ($typenode->children() as $node) {
						$ob = new CmsLayoutCollection();
						try {
							$ob->set_name((string)$node->name);
						} catch (Exception $e) {
							continue;
						}
						$ob->set_description((string)$node->description);
						$ob->set_default((string)$node->dflt != false);
						$ob->save();
						$designs[(string)$node->id] = $ob->get_id();
					}
					break;
				case 'stylesheets':
					if (!$runtime) {
						verbose_msg(lang('install_stylesheets'));
					}
					foreach ($typenode->children() as $node) {
						$ob = new CmsLayoutStylesheet();
						try {
							$ob->set_name((string)$node->name);
						} catch (Exception $e) {
							continue;
						}
						$ob->set_description((string)$node->description);
						try {
							$ob->set_content(htmlspecialchars_decode((string)$node->content));
						} catch (Exception $e) {
							continue;
						}
						$ob->set_media_types((string)$node->media_type);
						$ob->save();
						$styles[(string)$node->id] = $ob->get_id();
					}
					break;
				case 'designstyles': //relations between styles and designs
					$bank = [];
					foreach ($typenode->children() as $node) {
						$val = (string)$node->css_id;
						$val2 = (string)$node->design_id;
						if (isset($styles[$val]) && isset($designs[$val2])) {
							$val = $styles[$val];
							$bank[$val][0][] = $designs[$val2];
							$bank[$val][1][] = intval((string)$node->item_order);
						}
					}
					foreach ($bank as $sid=>$arr) {
						try {
							$ob = StylesheetOperations::load_stylesheet($sid);
						} catch (Exception $e) {
							continue;
						}
						array_multisort($arr[1], $arr[0]);
						$ob->set_designs($arr[0]);
						$ob->save();
					}
					break;
				case 'tpltypes':
					if (!$runtime) {
						verbose_msg(lang('install_templatetypes'));
						$val2 = '__CORE__'; //TODO get real value
					} else {
						$val2 = CmsLayoutTemplateType::CORE;
					}
					$pattern = '/^([as]:\d+:|[Nn](ull)?;)/';
					foreach ($typenode->children() as $node) {
						$val = (string)$node->originator;
						if (!$val) {
							$val = $val2;
						} elseif ($val != $val2) {
							continue; //core-only: modules' template-data installed by them
						}
						$ob = new CmsLayoutTemplateType();
						try {
							$ob->set_name((string)$node->name);
						} catch (Exception $e) {
							continue;
						}
						$ob->set_originator($val);
						$val = (string)$node->description;
						if ($val !== '') $ob->set_description($val);
						$ob->set_owner(1);
						$val3 = (string)$node->dflt_contents;
						if ($val3 !== '') {
							$ob->set_dflt_contents(htmlspecialchars_decode($val3));
							$ob->set_dflt_flag(true);
						} else {
							$ob->set_dflt_flag(false);
						}
						$ob->set_oneonly_flag((string)$node->one_only != false);
						$ob->set_content_block_flag((string)$node->requires_contentblocks != false);
						$val = (string)$node->lang_cb;
						if ($val) {
							if (preg_match($pattern, $val)) {
								$val = unserialize($val, []);
							}
							$ob->set_lang_callback($val);
						}
						$val = (string)$node->help_content_cb;
						if ($val) {
							if (preg_match($pattern, $val)) {
								$val = unserialize($val, []);
							}
							$ob->set_help_callback($val);
						}
						if ($val3 !== '') {
							$val = (string)$node->dflt_content_cb;
							if ($val) {
								if (preg_match($pattern, $val)) {
									$val = unserialize($val, []);
								}
								$ob->set_content_callback($val);
								try {
									$ob->reset_content_to_factory();
								} catch (Exception $e) {
									$dbg = 1;
								}
							}
						}
						$ob->save();
						$types[(string)$node->id] = $ob->get_id();
					}
					break;
				case 'categories':
					if (!$runtime) {
						verbose_msg(lang('install_categories'));
					}
					foreach ($typenode->children() as $node) {
						$ob = new CmsLayoutTemplateCategory();
						try {
							$ob->set_name((string)$node->name);
						} catch (Exception $e) {
							continue;
						}
						$ob->set_description((string)$node->description);
						$ob->set_item_order(intval((string)$node->item_order));
						$ob->save();
						$categories[(string)$node->id] = $ob->get_id();
					}
					break;
				case 'templates':
					if (!$runtime) {
						verbose_msg(lang('install_templates'));
					}
					foreach ($typenode->children() as $node) {
						$val = (string)$node->type_id;
						if (!isset($types[$val])) {
							continue;
						}
						$val2 = (string)$node->originator;
						if ($val2 && $val2 !== '__CORE__') { //TODO get real value
							continue; //anonymous && core only: modules' template-data installed by them
						}
						$ob = new CmsLayoutTemplate();
						try {
							if ($val2) $ob->set_originator($val2);
							$ob->set_name((string)$node->name);
							$ob->set_type($types[$val]);
							$ob->set_description((string)$node->description);
							$ob->set_owner(1);
							$val = (string)$node->category_id;
							if ($val !== '') $ob->set_category($val); //name or id
							$ob->set_type_dflt((string)$node->type_dflt != false);
							$ob->set_content(htmlspecialchars_decode((string)$node->content));
							$ob->save();
							$templates[(string)$node->id] = $ob->get_id();
						} catch (Exception $e) {
							continue;
						}
					}
					break;
				case 'designtemplates': //relations between templates and designs
					$bank = [];
					foreach ($typenode->children() as $node) {
						$val = (string)$node->tpl_id;
						$val2 = (string)$node->design_id;
						if (isset($templates[$val]) && isset($designs[$val2])) {
							$val = $templates[$val];
							$bank[$val][0][] = $designs[$val2];
							$bank[$val][1][] = intval((string)$node->tpl_order);
						}
					}
					foreach ($bank as $tid=>$arr) {
						try {
							$ob = TemplateOperations::load_template($tid);
						} catch (Exception $e) {
							continue;
						}
						array_multisort($arr[1], $arr[0]);
						$ob->set_designs($arr[0]);
						$ob->save();
					}
					break;
				case 'categorytemplates': //relations between templates and categories
					$bank = [];
					foreach ($typenode->children() as $node) {
						$val = (string)$node->tpl_id;
						$val2 = (string)$node->category_id;
						if (isset($templates[$val]) && isset($categories[$val2])) {
							$val = $templates[$val];
							$bank[$val][0][] = $categories[$val2];
							$bank[$val][1][] = intval((string)$node->tpl_order);
						}
					}
					foreach ($bank as $tid=>$arr) {
						try {
							$ob = TemplateOperations::load_template($tid);
						} catch (Exception $e) {
							continue;
						}
						array_multisort($arr[1], $arr[0]);
						$ob->set_categories($arr[0]);
						$ob->save();
					}
					break;
				case 'pages':
					if (!$runtime) {
						verbose_msg(lang('install_contentpages'));
					}
					$eid = -99;
					foreach ($typenode->children() as $node) {
						$classname = ucfirst((string)$node->type);  //CHECKME original case
						switch ($classname) {
							case 'Errorpage': $classname = 'ErrorPage'; break;
							case 'Pagelink': $classname = 'PageLink'; break;
							case 'Sectionheader': $classname = 'SectionHeader'; break;
						}
						$pagetype = '\\CMSMS\\contenttypes\\'.$classname;
						$ob = new $pagetype();
						$ob->SetName((string)$node->content_name);
						$ob->SetAlias((string)$node->content_alias);
						$ob->SetTemplateId($templates[(string)$node->template_id] ?? --$eid);
						$ob->SetDefaultContent((string)$node->default_content != false);
						$ob->SetOwner(1);
						$val = $pages[(string)$node->parent_id] ?? --$eid;
						$ob->SetParentId($val); //TODO update later if $eid
						$ob->SetActive((string)$node->active != false);
						$ob->SetShowInMenu((string)$node->show_in_menu != false);
						$val = (string)$node->menu_text;
						if ($val) $val = htmlspecialchars_decode($val);
						$ob->SetMenuText($val);
						$ob->SetCachable((string)$node->cachable != false);
						$ob->Save();
						$val = (string)$node->content_id;
						$pages[$val] = $ob->Id();
						$pageobs[$val] = $ob;
					}
					break;
				case 'properties': //must be processed after pages
					foreach ($typenode->children() as $node) {
						$ob = $pageobs[(string)$node->content_id] ?? null;
						if ($ob) {
							$ob->SetPropertyValue((string)$node->prop_name, htmlspecialchars_decode((string)$node->content));
						}
					}
					foreach ($pageobs as $ob) {
						$ob->Save();
					}
					break;
				case 'files':
					$config = cms_config::get_instance();
					$tobase = $config['uploads_path'];
					if ($tobase) {
						$tobase .= DIRECTORY_SEPARATOR;
					} else {
						continue;
					}
					if ($filesfolder) {
						//TODO validity check e.g. somewhere absolute in installer tree
						$frombase = $filesfolder.DIRECTORY_SEPARATOR;
					} else {
						$frombase = '';
					}

					foreach ($typenode->children() as $node) {
						$name = (string)$node->name;
						$to = $tobase.(string)$node->topath;
						if (!endswith($to, DIRECTORY_SEPARATOR)) {
							$to .= DIRECTORY_SEPARATOR;
						}
						if ((string)$node->embedded) {
							@file_put_contents($to.$name, base64_decode((string)$node->content));
						} else {
							$from = (string)$node->frompath;
							if ($from) {
 								if (!preg_match('~^ *(?:\/|\\\\|\w:\\\\|\w:\/)~', $from)) { //not absolute
									if ($frombase) {
										$from = $frombase.$from;
									} else {
										$from = CMS_ROOT_PATH.DIRECTORY_SEPARATOR.$from;
									}
								} else {
									//TODO validity check e.g. somewhere absolute in installer tree
								}
								$from .= DIRECTORY_SEPARATOR;
							} elseif ($frombase) {
								$from = $frombase;
							} else {
								continue;
							}
							$dir = dirname($to.$name);
							@mkdir($dir, 0771, true);
							// intentional fail if path(s) bad
							@copy($from.$name, $to.$name);
						}
					}

					$iter = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($config['uploads_path'],
						  FilesystemIterator::CURRENT_AS_PATHNAME |
						  FilesystemIterator::SKIP_DOTS),
						RecursiveIteratorIterator::SELF_FIRST);
						foreach ($iter as $to) {
							if (is_dir($to)) {
								@touch($to.DIRECTORY_SEPARATOR.'index.html');
							}
						}
					break;
				case 'userplugins':
					$tobase = CMS_ASSETS_PATH.DIRECTORY_SEPARATOR.'user_plugins'.DIRECTORY_SEPARATOR;
					if ($filesfolder) {
						//TODO validity check e.g. somewhere absolute in installer tree
						$frombase = $filesfolder.DIRECTORY_SEPARATOR;
					} else {
						$frombase = '';
					}

					foreach ($typenode->children() as $node) {
						$name = (string)$node->name;
						if ((string)$node->embedded) {
							@file_put_contents($tobase.$name, htmlspecialchars_decode((string)$node->content));
						} else {
							$from = (string)$node->frompath;
							if ($from) {
 								if (!preg_match('~^ *(?:\/|\\\\|\w:\\\\|\w:\/)~', $from)) { //not absolute
									if ($frombase) {
										$from = $frombase.$from;
									} else {
										$from = CMS_ROOT_PATH.DIRECTORY_SEPARATOR.$from;
									}
								} else {
									//TODO validity check e.g. somewhere absolute in installer tree
								}
								$from .= DIRECTORY_SEPARATOR;
							} elseif ($frombase) {
								$from = $frombase;
							} else {
								continue;
							}
							@copy($from.$name, $tobase.$name);
						}
					}
					break;
			}
		}
	}

	return '';
}
