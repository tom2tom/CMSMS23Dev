<?php
#Class for handling and dispatching events
#Copyright (C) 2004-2019 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
#Thanks to Ted Kulp and all other contributors from the CMSMS Development Team.
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

namespace CMSMS;

use cms_utils;
use CmsApp;
use CMSModule;
use CMSMS\internal\global_cachable;
use CMSMS\internal\global_cache;
use const CMS_DB_PREFIX;
use function debug_buffer;
use function lang;

/**
 * Class for handling and dispatching system and other defined events.
 *
 * @package CMS
 * @license GPL
 */
final class Events
{
	/**
	 * Cache data for 'static' handlers (stored in database)
	 * @ignore
	 */
	private static $_handlercache = null;

	/**
	 * Data for 'dynamic' handlers (registered during request)
	 * @ignore
	 */
	private static $_dynamic = null;

	/**
	 * @ignore
	 */
	private function __construct() {}

	/**
	 * @ignore
	 */
	private function __clone() {}

	/**
	 * Cache initiator called on demand
	 * @ignore
	 */
	public static function setup()
	{
		$obj = new global_cachable(__CLASS__,function()
			{
				$db = CmsApp::get_instance()->GetDb();
				$pref = CMS_DB_PREFIX;
				$sql = <<<EOS
SELECT e.event_id, eh.type, eh.class, eh.func, e.originator, e.event_name, eh.handler_order, eh.handler_id, eh.removable
FROM {$pref}event_handlers eh
INNER JOIN {$pref}events e ON e.event_id = eh.event_id
ORDER BY handler_order,event_id
EOS;
				return $db->GetArray($sql);
			});
		global_cache::add_cachable($obj);
	}

	/**
	 * Record an event in the database.
	 *
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @return bool
	 */
	public static function CreateEvent(string $originator, string $eventname) : bool
	{
		$db = CmsApp::get_instance()->GetDb();
		$id = $db->GenID( CMS_DB_PREFIX.'events_seq' );
		$originator = trim($originator);
		$eventname = trim($eventname);
		$pref = CMS_DB_PREFIX;
		$sql = <<<EOS
INSERT INTO {$pref}events (event_id,originator,event_name) SELECT ?,?,? FROM (SELECT 1 AS dmy) Z
WHERE NOT EXISTS (SELECT 1 FROM {$pref}events T WHERE T.originator=? AND T.event_name=?)
EOS;
		$dbr = $db->Execute($sql, [$id, $originator, $eventname, $originator, $eventname]);
		if( $dbr ) {
			global_cache::clear(__CLASS__);
			return true;
		}
		return false;
	}

	/**
	 * Remove an event from the database.
	 * This removes the event itself, and all handlers of the event
	 *
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @return bool
	 */
	public static function RemoveEvent(string $originator, string $eventname) : bool
	{
		$db = CmsApp::get_instance()->GetDb();

		// get the id
		$sql = 'SELECT event_id FROM '.CMS_DB_PREFIX.'events WHERE originator=? AND event_name=?';
		$id = (int) $db->GetOne($sql, [$originator, $eventname]);
		if( $id < 1 ) {
			// query failed, event not found
			return false;
		}

		// delete all handlers
		$sql = 'DELETE FROM '.CMS_DB_PREFIX.'event_handlers WHERE event_id=?';
		$db->Execute($sql, [$id]); // ignore failed result

		// then the event itself
		$sql = 'DELETE FROM '.CMS_DB_PREFIX.'events WHERE event_id=?';
		$db->Execute($sql, [$id]); // ignore failed result

		global_cache::clear(__CLASS__);
		return true;
	}

	/**
	 * Call all registered handlers of the given event.
	 *
	 * @param string $originator The event sender/owner - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @param mixed  $params Optional parameters associated with the event. Default []
	 */
	public static function SendEvent(string $originator, string $eventname, $params = [])
	{
		global $CMS_INSTALL_PAGE;
		if( isset($CMS_INSTALL_PAGE) ) return;
		$results = self::ListEventHandlers($originator, $eventname);
		if( $results ) {
			$params['_modulename'] = $originator; //might be 'Core'
			$params['_eventname'] = $eventname;
			$mgr = null;
			$smarty = null;
			foreach( $results as $row ) {
				$handler = $row['func'];
				switch( $row['type'] ) {
				  case 'M': //module
					if( !empty($row['class']) ) {
						// don't send event to the originator
						if( $row['class'] == $originator ) continue 2;

						// call the module event-handler
						$obj = CMSModule::GetModuleInstance($row['class']);
						if( $obj ) {
							debug_buffer('calling module ' . $row['class'] . ' from event ' . $eventname);
							$obj->DoEvent($originator, $eventname, $params);
						}
					}
					break;
				  case 'U': //UDT
					if( !empty($handler) ) {
						if( $mgr === null ) {
							$mgr = FilePluginOperations::get_instance();
						}
						debug_buffer($eventname.' event notice to file-plugin ' . $row['func']);
						$mgr->DoEvent($handler, $originator, $eventname, $params);
					}
					break;
				  case 'P': //regular plugin
					if( $smarty === null ) {
						$smarty = CmsApp::get_instance()->GetSmarty();
					}
					if( $smarty->is_plugin($handler) ) {
						if( function_exists('smarty_function_'.$handler) ) {
							$func = 'smarty_function_'.$handler;
						}
						elseif( function_exists('smarty_nocache_function_'.$handler) ) { //deprecated ?
							$func = 'smarty_nocache_function_'.$handler;
						}
						else {
							continue 2; //unlikely
						}
						call_user_function_array($func, [$originator, $eventname, $params]);
					}
					break;
//				  case 'C': //callable
				  default:
					if( !empty( $row['class']) && !empty( $row['func']) ) {
						//TODO validate
						$func = $row['class'].'::'.$row['func'];
						call_user_func_array($func, [$originator, $eventname, $params]);
					}
					break;
				}
			}
		}

		// notify other 'dynamic' handlers, if any
		HookManager::do_hook_simple($eventname, $originator, $eventname, $params); //too bad if same name for different originators!
	}

	/**
	 * Get a list of all sendable 'static' events
	 * Unlike the cached events-data, here we also report the numbers of event-handlers
	 *
	 * @return mixed array or false
	 */
	public static function ListEvents()
	{
		$db = CmsApp::get_instance()->GetDb();

		$pref = CMS_DB_PREFIX;
		$sql = <<<EOS
SELECT e.*, COUNT(eh.event_id) AS usage_count FROM {$pref}events e
LEFT OUTER JOIN {$pref}event_handlers eh ON e.event_id=eh.event_id
GROUP BY e.event_id
ORDER BY originator,event_name
EOS;
		$dbr = $db->Execute($sql);
		if( !$dbr ) return false;

		$result = [];
		while( $row = $dbr->FetchRow() ) {
			if( $row['originator'] == 'Core' || cms_utils::module_available($row['originator']) ) {
				$result[] = $row;
			}
		}
		$dbr->Close();
		return $result;
	}

	/**
	 * Get event help message (for a core event only).
	 *
	 * @param string $eventname The name of the event
	 * @return string Help for the event.
	 */
	public static function GetEventHelp(string $eventname) : string
	{
		return lang('event_help_'.strtolower($eventname));
	}

	/**
	 * Get event description (for a core event only).
	 *
	 * @param string $eventname The name of the event
	 * @return string Description of the event
	 */
	public static function GetEventDescription(string $eventname) : string
	{
		return lang('event_desc_'.strtolower($eventname));
	}

	/**
	 * Return the list of (static and dynamic) event handlers for an event.
	 *
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @return mixed If successful, an array of arrays, each element
	 *               in the array contains two elements 'handler_name', and 'module_handler',
	 *               any one of these could be null. If it fails, false is returned.
	 */
	public static function ListEventHandlers(string $originator, string $eventname)
	{
		$handlers = [];
		if( self::$_handlercache === null ) {
			self::$_handlercache = global_cache::get(__CLASS__);
		}
		if( self::$_handlercache ) {
			foreach( self::$_handlercache as $row ) {
				if( $row['originator'] == $originator && $row['event_name'] == $eventname ) $handlers[] = $row;
			}
		}

		if( self::$_dynamic ) {
			foreach( self::$_dynamic as $row ) {
				if( $row['originator'] == $originator && $row['event_name'] == $eventname ) $handlers[] = $row;
			}
		}

		if( $handlers ) return $handlers;
		return false;
	}

	/**
	 * @ignore
	 */
	public static function GetEventHandler(int $handler_id)
	{
		if( self::$_handlercache === null ) {
			self::$_handlercache = global_cache::get(__CLASS__);
		}
		if( self::$_handlercache ) {
			foreach( self::$_handlercache as $row ) {
				if( $row['handler_id'] == $handler_id ) return $row;
			}
		}
	}

	/**
	 * @ignore
	 * @param mixed  $callback an actual or pseudo callable or an equivalent string
	 *  As appropriate, the 'class' may be a module name or '', the 'method' may be
	 *  a UDT name or regular-plugin identifier or ''
	 * @param string $type Default 'auto'
	 * @return mixed 3-member array | false upon error
	 */
	private static function InterpretCallback($callback, string $type = 'auto')
	{
		$func = '';
		if( is_callable($callback,true,$func) ) {
			list($class,$method) = explode('::',$func);
		}
		elseif( is_string($callback) && $callback ) {
			list($class,$method) = explode('::',$callback,2);
		}
		else {
			return false;
		}

		switch( $type ) {
		 case 'module';
			$type = 'M';
			break;
		 case 'tag';
			$type = 'U';
			break;
		 case 'plugin';
			$type = 'P';
			break;
		 case 'callable';
			$type = 'C';
			break;
		}

		switch( $type ) {
		 case 'M';
			if( $method && !$class ) { $class = $method; $method = null; }
			if( !$class ) return false;
			elseif( $method ) { $type = 'C'; }
			break;
		 case 'U';
		 case 'P';
			if( $class && !$method ) { $method = $class; $class = null; }
			if( !$method ) return false;
			elseif( $class ) { $type = 'C'; }
			else { $class = null; }
			break;
		 case 'C';
			if( !$class || !$method ) return false;
			break;
		 case 'auto':
			if( $class && $method ) { $type = 'C'; }
			elseif( $class ) { $method = null; $type = 'M'; /*TODO $class is module name type=M | UDT name  method=class type=U | plugin name method=class type=P */ }
			elseif( $method ) { $class = null; $type = 'U'; /*TODO $method is module name class=method type=M | UDT name type=U | plugin name type=P */ }
			else return false;
			break;
		 default:
			return false;
		}

		return [$class,$method,$type];
	}

	/**
	 * Record a handler of the specified event.
	 * User Defined Tags may be event handlers, so that relevant admin users
	 * can customize event handling on-the-fly.
	 * @since 2.3
	 *
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @param mixed  $callback an actual or pseudo callable or an equivalent string
	 *  As appropriate, the 'class' may be a module name or '', the 'method' may be
	 *  a UDT name or regular-plugin identifier or ''
	 * @param string $type Optional indicator of $callback type
	 *  ('M' module 'U' UDT 'P' regular plugin 'C' callable). Default 'C'.
	 * @param bool   $removable Optional flag whether this event may be removed from the list. Default true.
	 * @return bool indicating success
	 */
	public static function AddStaticHandler(string $originator, string $eventname, $callback, string $type = 'C', bool $removable = true) : bool
	{
		$params = self::InterpretCallback($callback, $type);
		if( !$params || (empty($params[0] && empty($params[1]))) ) return false;

		$db = CmsApp::get_instance()->GetDb();
		// find the event, if any
		$sql = 'SELECT event_id FROM '.CMS_DB_PREFIX.'events WHERE originator=? AND event_name=?';
		$id = (int) $db->GetOne($sql, [$originator, $eventname]);
		if( $id < 1 ) {
			// query failed, event not found
			return false;
		}

		list($class, $method, $type) = $params;
		// check nothing is already recorded for the event and handler
		$sql = 'SELECT 1 FROM '.CMS_DB_PREFIX.'event_handlers WHERE event_id=? AND ';
		$params = [$id];

		if( $class && $method ) {
			$sql .= 'class=? AND func=?';
			$params[] = $class;
			$params[] = $method;
		}
		elseif( $class) {
			$sql .= 'class=? AND func IS NULL';
			$params[] = $class;
		}
		else { //$method
			$sql .= 'class IS NULL AND func=?';
			$params[] = $method;
		}
		$dbr = $db->GetOne($sql, $params);
		if( !$dbr ) {
			return false; // ach, something matches already
		}

		$handler_id = $db->GenId(CMS_DB_PREFIX.'event_handler_seq');
		// get a new handler order
		$sql = 'SELECT MAX(handler_order) AS newid FROM '.CMS_DB_PREFIX.'event_handlers WHERE event_id=?';
		$order = (int) $db->GetOne($sql, [$originator, $eventname]);
		if( $order < 1 ) {
			$order = 1;
		}
		else {
			++$order;
		}
		$mode = ( $removable ) ? 1:0;

		$sql = 'INSERT INTO '.CMS_DB_PREFIX.'event_handlers
(handler_id,event_id,class,func,type,removable,handler_order) VALUES (?,?,?,?,?,?,?)';
		$dbr = $db->Execute($sql, [$handler_id, $id, $class, $method, $type, $mode, $order]);
		global_cache::clear(__CLASS__);
		return ($dbr != false);
	}

	/**
	 * Record a handler of the specified event.
	 * User Defined Tags may be event handlers, so that relevant admin users
	 * can customize event handling on-the-fly.
	 * @deprecated since 2.3 Instead use AddStaticHandler()
	 *
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @param string $tag_name The name of a UDT. If not passed, no User Defined Tag is set.
	 * @param string $module_handler The name of a module. If not passed, no module is set.
	 * @param bool $removable Optional flag whether this event may be removed from the list. Default true.
	 * @return bool indicating success
	 */
	public static function AddEventHandler(string $originator, string $eventname, $tag_name = '', $module_handler = '', bool $removable = true) : bool
	{
		if( !($tag_name || $module_handler) ) return false;
		if( $tag_name && $module_handler ) return false;
		if( $tag_name ) {
			$module_handler = ''; //force string
			$type = 'U';
		}
		else {
			$tag_name = '';
			$type = 'M';
		}
		return self::AddStaticHandler($originator, $eventname, [$module_handler, $tag_name], $type, $removable);
	}

	/**
	 * @since 2.3
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @param mixed $callback an actual or pseudo callable or an equivalent string
	 *  As appropriate, the 'class' may be a module name or '', the 'method' may be
	 *  a UDT name or regular-plugin identifier or ''
	 * @param string $type Optional indicator of $callback type
	 *  ('M' module 'U' UDT 'P' regular plugin 'C' callable). Default 'C'.
	 * @return bool indicating success
	 */
	public static function AddDynamicHandler(string $originator, string $eventname, $callback, string $type='C') : bool
	{
		$params = self::InterpretCallback($callback, $type);
		if( !$params || (empty($params[0] && empty($params[1]))) ) return false;
		list($class, $method, $type) = $params;

		if( !is_array(self::$_dynamic) ) {
			self::$_dynamic = [];
		}
		self::$_dynamic[] = [
		 'originator'=>$originator,
		 'event_name'=>$eventname,
		 'class'=>$class,
		 'func'=>$method,
		 'type'=>$type,
		];
		self::$_dynamic = array_unique(self::$_dynamic,SORT_REGULAR);
		return true;
	}

	/**
	 * @ignore
	 */
	protected static function InternalRemoveHandler($handler)
	{
		$db = CmsApp::get_instance()->GetDb();
		$id = $handler['event_id'];

		// update any subsequent handlers
		$sql = 'UPDATE '.CMS_DB_PREFIX.'event_handlers SET handler_order = handler_order - 1 WHERE event_id=? AND handler_order>?';
		$db->Execute($sql, [$id, $handler['handler_order']]);

		// now delete this record
		$sql = 'DELETE FROM '.CMS_DB_PREFIX.'event_handlers WHERE event_id=? AND handler_id=?';
		$db->Execute($sql, [$id, $handler['handler_id']]);

		global_cache::clear(__CLASS__);
	}

	/**
	 * Remove an event handler given its id
	 *
	 * @param int $handler_id
	 */
	public static function RemoveEventHandlerById(int $handler_id)
	{
		$handler = self::GetEventHandler( $handler_id );
		if( $handler ) self::InternalRemoveHandler( $handler );
	}

	/**
	 * Remove a handler of the given event.
	 *
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @param mixed  $callback an actual or pseudo callable or an equivalent string
	 *  As appropriate, the 'class' may be a module name or '', the 'method' may be
	 *  a UDT name or regular-plugin identifier or ''
	 * @param string $type Optional indicator of $callback type
	 *  ('M' module 'U' UDT 'P' regular plugin 'C' callable). Default 'C'.
	 * @return bool indicating success
	 */
	public static function RemoveStaticHandler(string $originator, string $eventname, $callback, string $type='C')
	{
		$params = self::InterpretCallback($callback, $type);
		if( !$params || (empty($params[0] && empty($params[1]))) ) return false;

		$db = CmsApp::get_instance()->GetDb();
		// find the event id
		$sql = 'SELECT event_id FROM '.CMS_DB_PREFIX.'events WHERE originator=? AND event_name=?';
		$id = (int) $db->GetOne($sql, [$originator, $eventname]);
		if( $id < 1 ) {
			// query failed, event not found
			return false;
		}

		list($class, $method, $type) = $params;
		// find the handler
		$sql = 'SELECT * FROM '.CMS_DB_PREFIX.'event_handlers WHERE event_id=? AND ';
		$params = [$id];
		if( $class && $method ) {
			$sql .= 'class=? AND func=?';
			$params[] = $class;
			$params[] = $method;
		}
		elseif( $class) {
			$sql .= 'class=? AND func IS NULL';
			$params[] = $class;
		}
		else { //$method
			$sql .= 'class IS NULL AND func=?';
			$params[] = $method;
		}
		$row = $db->GetRow($sql, $params);
		if( !$row ) return false;

		self::InternalRemoveHandler($row);
		return true;
	}

	/**
	 * Remove a handler of the given event.
	 * @deprecated since 2.3 Instead use RemoveStaticHandler()
	 *
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @param mixed  $tag_name Optional name of a User Defined Tag which handles the specified event
	 * @param mixed  $module_handler Optional name of a module which handles the specified event
	 * @return bool indicating success or otherwise.
	 */
	public static function RemoveEventHandler(string $originator, string $eventname, $tag_name = '', $module_handler = '')
	{
		if( !($tag_name || $module_handler) ) return false;
		if( $tag_name && $module_handler ) return false;
		if( $tag_name ) {
			$module_handler = ''; //enforce string
			$type = 'U';
		}
		else {
			$tag_name = '';
			$type = 'M';
		}
		return self::RemoveStaticHandler($originator, $eventname, [$module_handler, $tag_name], $type);
	}

	/**
	 * Remove all handlers of the given event.
	 *
	 * @param string $originator The event 'owner' - a module name or 'Core'
	 * @param string $eventname The name of the event
	 * @return bool indicating success or otherwise
	 */
	public static function RemoveAllEventHandlers(string $originator, string $eventname)
	{
		$db = CmsApp::get_instance()->GetDb();

		// find the event id
		$sql = 'SELECT event_id FROM '.CMS_DB_PREFIX.'events WHERE originator=? AND event_name=?';
		$id = (int) $db->GetOne($sql, [$originator, $eventname]);
		if( $id < 1 ) {
			// query failed, event not found
			return false;
		}

		// delete handler(s) if any
		$sql = 'DELETE FROM '.CMS_DB_PREFIX.'event_handlers WHERE event_id= ?';
		$dbr = $db->Execute($sql, [$id]);
		global_cache::clear(__CLASS__);
		return ($dbr != false);
	}

	/**
	 * Increase an event handler's priority
	 *
	 * @param int $handler_id
	 */
	public static function OrderHandlerUp(int $handler_id)
	{
		$handler = self::GetEventHandler($handler_id);
		if( !$handler ) return;

		$db = CmsApp::get_instance()->GetDb();
		$sql = 'UPDATE '.CMS_DB_PREFIX.'event_handlers SET handler_order = handler_order + 1 WHERE event_id = ? AND handler_order = ?';
		$db->Execute( $sql, [ $handler['event_id'], $handler['handler_order'] - 1 ] );
		$sql = 'UPDATE '.CMS_DB_PREFIX.'event_handlers SET handler_order = handler_order - 1 WHERE event_id = ? AND handler_id = ?';
		$db->Execute( $sql, [ $handler['event_id'], $handler['handler_id'] ] );
		global_cache::clear(__CLASS__);
	}

	/**
	 * Decrease an event handler's priority
	 *
	 * @param int $handler_id
	 */
	public static function OrderHandlerDown(int $handler_id)
	{
		$handler = self::GetEventHandler($handler_id);
		if( !$handler ) return;

		if( $handler['handler_order'] < 2 ) return;

		$db = CmsApp::get_instance()->GetDb();
		$sql = 'UPDATE '.CMS_DB_PREFIX.'event_handlers SET handler_order = handler_order - 1 WHERE event_id = ? AND handler_order = ?';
		$db->Execute( $sql, [ $handler['event_id'], $handler['handler_order'] + 1 ] );
		$sql = 'UPDATE '.CMS_DB_PREFIX.'event_handlers SET handler_order = handler_order + 1 WHERE event_id = ? AND handler_id = ?';
		$db->Execute( $sql, [ $handler['event_id'], $handler['handler_id'] ] );
		global_cache::clear(__CLASS__);
	}
} //class

//backward-compatibility shiv
\class_alias(Events::class, 'Events', false);
