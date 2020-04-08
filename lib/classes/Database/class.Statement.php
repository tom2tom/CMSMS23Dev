<?php
/*
Class Statement: represents a prepared SQL statement
Copyright (C) 2018-2020 CMS Made Simple Foundation <foundation@cmsmadesimple.org>
This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
*/

namespace CMSMS\Database;

use CMSMS\Database\compatibility;
use CMSMS\Database\Connection;
use CMSMS\Database\ResultSet;
use function debug_bt_to_log;
use function debug_to_log;

/**
 * A class defining a prepared database statement, and methods for
 * interacting with such statements.
 *
 * @since 2.3
 *
 * @property-read Connection $db The database connection
 * @property-read string $sql The SQL query
 */
class Statement
{
    const NOPARMCMD = 1295; // MySQL/MariaDB errno for deprecated non-parameterizable command

    /**
     * Connection object
     * @ignore
     */
    protected $_conn;

    /**
     * mysqli_stmt object
     * @ignore
     */
    protected $_stmt;

    /**
     * SQL command | null
     * @ignore
     */
    protected $_sql;

    /**
     * Whether prepare() succeeded
     * @ignore
     */
    protected $_prep = false;

    /**
     * Whether bind() succeeded
     * @ignore
     */
    protected $_bound = false;

    /**
     * Constructor.
     *
     * @param Connection      $conn The database connection
     * @param optional string $sql  The SQL query, default null
     */
    public function __construct(Connection $conn, $sql = null)
    {
        $this->_conn = $conn;
        $this->_sql = $sql;
    }

/* BAD !! TODO check proper cleanup happens anyway, upon destruction
    public function __destruct()
    {
        $this->close();
    }
*/
    /**
     * @ignore
     */
    public function __get($key)
    {
        switch ($key) {
         case 'db':
         case 'conn':
            return $this->_conn;
         case 'sql':
            return $this->_sql;
         case 'affected':
            return $this->_stmt->affected_rows;
        }
    }

    /**
     * Get the no. of rows affected by execution of the prepared command.
     *
     * @return int
     */
    public function affected_rows()
    {
        return $this->_stmt->affected_rows;
    }

    protected function processerror ($type, $errno, $error)
    {
        $this->_conn->OnError($type, $errno, $error);
    }

    /**
     * Prepare a command.
     *
     * @param optional string $sql parameterized SQL command default null
     * If $sql is not provided here, $this->_sql must have previously been
     * populated with the relevant command.
     *
     * @return bool indicating success
     */
    public function prepare($sql = null)
    {
        $mysql = $this->_conn->get_inner_mysql();
        if (!$mysql || !$this->_conn->isConnected()) {
            $errno = 5;
            $error = 'Attempt to create prepared statement when database is not connected';
            $this->processerror(Connection::ERROR_CONNECT, $errno, $error);
            $this->_prep = false;

            return false;
        } elseif (!($sql || $this->_sql)) {
            $errno = 1;
            $error = 'No SQL to prepare';
            $this->processerror(Connection::ERROR_PARAM, $errno, $error);
            $this->_prep = false;

            return false;
        }

        if (!$sql) {
            $sql = $this->_sql;
        } else {
            $this->_sql = $sql;
        }
        $this->_stmt = $mysql->stmt_init();
        $this->_prep = $this->_stmt->prepare((string) $sql);
        if ($this->_prep) {
            $this->_conn->errno = 0;
            $this->_conn->error = '';

            return true;
        }

        $errno = $this->_stmt->errno;
        if ($errno == self::NOPARMCMD) {
            //the SQL cannot be parameterized
            debug_to_log('SQL: '.$sql);
            debug_bt_to_log();
            //deprecated - setup to try to emulate the command, later
            //$this->_stmt persists (non-null)
            $this->_prep = true;
            $this->_conn->errno = $errno;
            $this->_conn->error = '';

            return true;
        }
        $error = $this->_stmt->error;
        $this->processerror (Connection::ERROR_PREPARE, $errno, $error);
        $this->_stmt = null;

        return false;
    }

    /*
     * @deprecated support for binding multiple sets of command-parameters
     *  in a single 2-D array, to be processed with ->next() until ->EOF()
     */
    private $all_tobind = [];
    private $now_bind = false;
    /**
     * @deprecated
     *
     * Go to the next member of an array of query-parameters that are
     *  being successively executed, and run the query
     */
    public function movenext()
    {
        $this->now_bind = next($this->all_bound);
        $this->bind($this->now_bind);
    }

    /**
     * @deprecated
     *
     * @return bool indicating we're now at the end of an array of
     * parameters that are being successively executed
     */
    public function EOF()
    {
        return !$this->now_bind;
    }

    /**
     * Bind parameters in $valsarr to the sql statement.
     *
     * @return bool indicating success
     */
    public function bind($valsarr)
    {
        if (!$this->_stmt) {
            if ($this->_sql) {
                $this->prepare($this->_sql);
                if (!$this->_prep) {
                    $this->_bound = false;

                    return false;
                }
            } else {
                $errno = 1;
                $error = 'No SQL to bind to';
                $this->processerror(Connection::ERROR_PARAM, $errno, $error);
                $this->_bound = false;

                return false;
            }
        }

        if (is_array($valsarr) && count($valsarr) == 1 && is_array($valsarr[0])) {
            $valsarr = $valsarr[0];
        } elseif (is_array($valsarr[0])) {
            //deprecated stuff
            $this->all_bound = $valsarr;
            $valsarr = $this->now_bind = reset($this->all_bound);
        }

        //deprecated - attempt emulation
        if ($this->_conn->errno == self::NOPARMCMD) {
            $sql = compatibility::interpret($this->_conn, $this->sql, $valsarr);
            if ($sql) {
                $this->_sql = $sql;
                $this->_bound = false;

                return true;
            } else {
                $this->_bound = false;

                return false;
            }
        }

        $types = '';
        $bound = [''];
        foreach ($valsarr as $k => &$val) {
            switch (gettype($val)) {
             case 'double': //i.e. float
//          $val = strtr($val, ',', '.');
                $types .= 'd';
                break;
             case 'boolean':
                $valsarr[$k] = $val ? 1 : 0;
             case 'integer':
                $types .= 'i';
                break;
//             case 'string':
//TODO handle blobs for data > max_allowed_packet, send them using ::send_long_data()
// to get the max_allowed_packet
//$mysql = $this->_conn->get_inner_mysql();
//$maxp = $mysql->query('SELECT @@global.max_allowed_packet')->fetch_array();
//             case 'array':
//             case 'object':
//             case 'resource':
//                $val = serialize($val);
//                $types .= 's';
//                break;
//             case 'NULL':
//             case 'unknown type':
             default:
                $types .= 's';
                break;
            }
            $bound[] = &$valsarr[$k];
        }
        unset($val);
        $bound[0] = $types;

        if ($this->_bound) {
            $this->_stmt->free_result();
        }

        if (call_user_func_array([$this->_stmt, 'bind_param'], $bound)) {
            $this->_conn->errno = 0;
            $this->_conn->error = '';
            $this->_bound = true;

            return true;
        }

        $errno = 6;
        $error = 'Failed to bind paramers to prepared statement';
        $this->processerror(Connection::ERROR_PARAM, $errno, $error);
        $this->_bound = false;

        return false;
    }

    /**
     * Execute the query, using supplied $valsarr (if any) as bound values.
     *
     * @param mixed $valsarr array of parameters to bind, or not set if
     *   running a deprecated multi-bind command
     * @return mixed object (ResultSet or EmptyResultSet or PrepResultSet) | int > 0 | false | null
     */
    public function execute($valsarr = null)
    {
        if (!$this->_stmt) {
            if ($this->_sql) {
                $this->prepare($this->_sql);
                if (!$this->_prep) {
                    $this->_bound = false;

                    return null;
                }
            } else {
                $errno = 1;
                $error = 'No SQL to prepare';
                $this->processerror(Connection::ERROR_PARAM, $errno, $error);

                return null;
            }
        }

        $pc = $this->_stmt->param_count;
        //check for deprecated multi-bind process
        if ($valsarr === null) {
            $valsarr = $this->now_bind;
        }

        if ($valsarr) {
            if (is_array($valsarr) && count($valsarr) == 1 && is_array($valsarr[0])) {
                $valsarr = $valsarr[0];
            }
            if ($pc == count($valsarr)) {
                $this->bind($valsarr);
                if (!$this->_bound) {
                    return null;
                }
            } else {
                //TODO this is in wrong spot : maybe not yet bound
                //check for deprecated emulation of non-parameterizable command
                if ($this->_conn->errno == self::NOPARMCMD) {
                    $sql = compatibility::interpret($this->_conn, $this->sql, $valsarr);
                    if ($sql) {
                        $this->_sql = $sql;
                    }

                    $this->_stmt = null;
                    $rs = $this->_conn->execute($this->_sql); //mysqli_result or false
                    if ($rs) {
                        $this->_conn->errno = 0;
                        $this->_conn->error = '';

                        return new ResultSet($rs);
                    } else {
                        $errno = 6;
                        $error = 'Unbindable SQL - '.$this->_sql;
                        $this->processerror(Connection::ERROR_PARAM, $errno, $error);

                        return null;
                    }
                }

                $errno = 2;
                $error = 'Incorrect number of bound parameters - should be '.$pc;
                $this->processerror(Connection::ERROR_PARAM, $errno, $error);

                return null;
            }
        } elseif ($pc > 0 && !$this->_bound) {
            $errno = 3;
            $error = 'No bound parameters, and no arguments passed';
            $this->processerror(Connection::ERROR_PARAM, $errno, $error);

            return null;
        }

        if (!$this->_stmt->execute()) {
            $errno = $this->_stmt->errno;
            $error = $this->_stmt->error;
            $this->processerror(Connection::ERROR_EXECUTE, $errno, $error);

            return null;
        }

        if ($this->_stmt->field_count > 0) {
            //doing a select/show/describe query
            if ($this->_conn->isNative()) {
                $rs = $this->_stmt->get_result(); //mysqli_result or false
                if ($rs) {
                    $this->_conn->errno = 0;
                    $this->_conn->error = '';

                    return new ResultSet($rs);
                } elseif (($n = $this->_stmt->errno) > 0) {
                    $error = $this->_stmt->error;
                    $this->processerror(Connection::ERROR_EXECUTE, $n, $error);

                    return null;
                } else { //should never happen
                    $errno = 99;
                    $error = 'No result (reason unknown)';
                    $this->processerror(Connection::ERROR_EXECUTE, $errno, $error);

                    return null;
                }
            } else {
                $this->_conn->errno = 0;
                $this->_conn->error = '';

                return new PrepResultSet($this->_stmt);
            }
        } else { //INSERT,UPDATE,DELETE etc
            $this->_conn->errno = 0;
            $this->_conn->error = '';

            $num = $this->_stmt->affected_rows;
            //TODO only for INSERT,UPDATE,DELETE
            // TODO if $num == 1 and is INSERT, return (($num = $this->_stmt->insert_id 1st-use ? | $this->_conn->get_inner_mysql()->insert_id every use?) > 0) ? $num : 1;
            // support strict 'false' checks
            return ($num > 0) ? $num : false;
        }
    }

    /**
     * Cleanup/release database resources
     * CHECKME automatic cleanup upon destruction?
     */
    public function close()
    {
        if ($this->_stmt) {
            if ($this->_bound) {
                $this->_stmt->free_result();
            }
            if ($this->_prep) {
                $this->_stmt->close();
            }
        }
    }
}
