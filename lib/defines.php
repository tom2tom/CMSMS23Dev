<?php
#Define session constants
#Copyright (C) 2018-2020 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
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

$config = cms_config::get_instance();

/**
 * Where private cachable files can be written.
 */
define('TMP_CACHE_LOCATION',$config['tmp_cache_location']);

/**
 * The smarty template compile directory.
 */
define('TMP_TEMPLATES_C_LOCATION',$config['tmp_templates_c_location']);

/**
 * Where public (browsable) cachable files can be written.
 */
define('PUBLIC_CACHE_LOCATION',$config['public_cache_location']);

/**
 * The URL for public cachable files.
 */
define('PUBLIC_CACHE_URL',$config['public_cache_url']);

/**
 * Whether CMSMS is in debug mode.
 */
define('CMS_DEBUG',$config['debug']);

/**
 * Whether to throw upon use of deprecated stuff.
 * @since 2.9
 */
define('CMS_DEPREC',CMS_DEBUG && $config['deprecations']);

/**
 * Where CMSMS is installed.
 */
define('CMS_ROOT_PATH',$config['root_path']);

/**
 * Where admin stuff is stored.
 */
define('CMS_ADMIN_PATH',$config['root_path'].DIRECTORY_SEPARATOR.$config['admin_dir']);

/**
 * Where non-core assets are stored.
 */
define('CMS_ASSETS_PATH',$config['assets_path']);

/**
 * Where simple-plugin files are stored.
 * @since 2.9
 */
define('CMS_FILETAGS_PATH',$config['simpletags_path']);

/**
 * The 'top' directory where javascript files are stored
 * @since 2.9
 */
define('CMS_SCRIPTS_PATH',$config['root_path'].DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'js');

/**
 * The site root URL.
 */
define('CMS_ROOT_URL',$config['root_url']);

/**
 * The site assets URL.
 * @since 2.9
 */
define('CMS_ASSETS_URL',$config['assets_url']);

/**
 * The 'top' URL where javascript files are stored
 * @since 2.9
 */
define('CMS_SCRIPTS_URL',$config['root_url'].'/lib/js');

/**
 * The site uploads URL.
 */
define('CMS_UPLOADS_URL',$config['uploads_url']);

/**
 * The database-table prefix.
 */
define('CMS_DB_PREFIX',$config['db_prefix']);

if( CMS_DEBUG ) {
    //for async DEBUG since 2.9
    define('ASYNCLOG', TMP_CACHE_LOCATION.DIRECTORY_SEPARATOR.'debug.log');
}

const CMS_DEFAULT_VERSIONCHECK_URL = 'https://www.cmsmadesimple.org/latest_version.php';
const CMS_SECURE_PARAM_NAME = '_sk_';
const CMS_JOB_KEY = '_sk_jobtype'; //derivative of CMS_SECURE_PARAM_NAME, need not be const
const CMS_USER_KEY = '_userkey_';

/**
 * Preview-page identifiers.
 */
const CMS_PREVIEW = '__cms_preview__';
const CMS_PREVIEW_TYPE = '__cms_preview_type__';
const CMS_PREVIEW_PAGEID = -100;
const __CMS_PREVIEW_PAGE__ = -100; //deprecated since 2.3
