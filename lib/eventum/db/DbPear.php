<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 encoding=utf-8: */
// +----------------------------------------------------------------------+
// | Eventum - Issue Tracking System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2014 Eventum Team.                                     |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 51 Franklin Street, Suite 330                                          |
// | Boston, MA 02110-1301, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: Elan Ruusamäe <glen@delfi.ee>                               |
// +----------------------------------------------------------------------+

class DbPear implements DbInterface
{
    /** @var DB_common */
    private $db;

    /**
     * @var string
     */
    private $tablePrefix;

    public function __construct(array $config)
    {
        $dsn = array(
            'phptype'  => $config['driver'],
            'hostspec' => $config['hostname'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
        );

        // DBTYPE specific dsn settings
        switch ($dsn['phptype']) {
            case 'mysql':
            case 'mysqli':
                // if we are using some non-standard mysql port, pass that value in the dsn
                if ($config['port'] != 3306) {
                    $dsn['port'] = $config['port'];
                }
                break;
            default:
                if ($config['port']) {
                    $dsn['port'] = $config['port'];
                }
                break;
        }

        $db = DB::connect($dsn);
        $this->assertError($db, 1);

        // DBTYPE specific session setup commands
        switch ($dsn['phptype']) {
            case 'mysql':
            case 'mysqli':
                $db->query("SET SQL_MODE = ''");
                if (Language::isUTF8()) {
                    $db->query('SET NAMES utf8');
                }
                break;
            default:
                break;
        }

        $this->db = $db;
        $this->tablePrefix = $config['table_prefix'];
    }

    /**
     * @see DB_common::getOne
     */
    public function getOne($query, $params = array())
    {
        $query = $this->quoteSql($query, $params);
        $res = $this->db->getOne($query, $params);
        $this->assertError($res);

        return $res;
    }

    /**
     * @see DB_common::getAssoc
     */
    public function getAssoc(
        $query, $force_array = false, $params = array(),
        $fetchmode = DB_FETCHMODE_DEFAULT, $group = false
    ) {
        if (is_array($force_array)) {
            throw new LogicException('force_array passed as array, did you mean fetchPair or forgot extra arg?');
        }
        $query = $this->quoteSql($query, $params);
        $res = $this->db->getAssoc($query, $force_array, $params, $fetchmode, $group);
        $this->assertError($res);

        return $res;
    }

    /**
     * @see DB_common::getAssoc
     */
    public function fetchAssoc($query, $params = array(), $fetchmode = DB_FETCHMODE_DEFAULT)
    {
        $query = $this->quoteSql($query, $params);
        $res = $this->db->getAssoc($query, false, $params, $fetchmode, false);
        $this->assertError($res);

        return $res;
    }

    public function getPair($query, $params = array())
    {
        return $this->getAssoc($query, false, $params);
    }

    /**
     * @see DB_common::query
     */
    public function query($query, $params = array())
    {
        $query = $this->quoteSql($query, $params);
        $res = $this->db->query($query, $params);
        $this->assertError($res);

        return $res;
    }

    /**
     * @see DB_common::getAll
     */
    public function getAll($query, $params = array(), $fetchmode = DbInterface::DB_FETCHMODE_ASSOC)
    {
        $query = $this->quoteSql($query, $params);
        $res = $this->db->getAll($query, $params, $fetchmode);
        $this->assertError($res);

        return $res;
    }

    /**
     * @see DB_common::getRow
     */
    public function getRow($query, $params = array(), $fetchmode = DbInterface::DB_FETCHMODE_ASSOC)
    {
        $query = $this->quoteSql($query, $params);
        $res = $this->db->getRow($query, $params, $fetchmode);
        $this->assertError($res);

        return $res;
    }

    /**
     * @see DB_common::getCol
     * @deprecated
     */
    public function getCol($query, $col = 0, $params = array())
    {
        $query = $this->quoteSql($query, $params);
        $res = $this->db->getCol($query, $col, $params);
        $this->assertError($res);

        return $res;
    }

    /**
     * @see DbPear::getCol
     */
    public function getColumn($query, $params = array())
    {
        return $this->getCol($query, 0, $params);
    }

    /**
     * @see DB_common::quoteIdentifier
     */
    public function quoteIdentifier($str)
    {
        $res = $this->db->quoteIdentifier($str);
        $this->assertError($res);

        return $res;
    }

    /**
     * @see DB_common::escapeSimple
     */
    public function escapeSimple($str)
    {
        $res = $this->db->escapeSimple($str);
        $this->assertError($res);

        return $res;
    }

    public function affectedRows()
    {
        $res = $this->db->affectedRows();
        $this->assertError($res);

        return $res;
    }

    /**
     * Check if $e is PEAR error, if so, throw as DbException
     *
     * @param $e PEAR_Error|array|object|int
     */
    private function assertError($e, $depth = 2)
    {
        if (!Misc::isError($e)) {
            return;
        }

        list($file, $line) = self::getTrace($depth);
        Error_Handler::logError(array($e->getMessage(), $e->getDebugInfo()), $file, $line);

        $de = new DbException($e->getMessage(), $e->getCode());
        $de->setExceptionLocation($file, $line);

        error_log($de->getMessage());
        error_log($de->getTraceAsString());
        throw $de;
    }

    /**
     * Get array of FILE and LOCATION from backtrace
     *
     * @param int $depth
     * @return array
     */
    private static function getTrace($depth = 1)
    {
        $trace = debug_backtrace();
        if (!isset($trace[$depth])) {
            return null;
        }
        $caller = (object) $trace[$depth];
        if (!isset($caller->file)) {
            return null;
        }

        return array($caller->file, $caller->line);
    }

    /**
     * Processes a SQL statement by quoting table and column names that are enclosed within double brackets.
     * Tokens enclosed within double curly brackets are treated as table names, while
     * tokens enclosed within double square brackets are column names. They will be quoted accordingly.
     * Also, the percentage character "%" at the beginning or ending of a table name will be replaced
     * with [[tablePrefix]].
     *
     * @param string $sql the SQL to be quoted
     * @return string the quoted SQL
     * @see https://github.com/yiisoft/yii2/blob/2.0.0/framework/db/Connection.php#L761-L783
     */
    private function quoteSql($sql, $params)
    {
        /**
         * NOTE: PEAR driver treats these three as placeholders: '?&!'
         * but we want to use only '?', so need to quote these others first
         *
         * @see DB_common::prepare()
         */
        if (count($params)) {
            $sql = preg_replace('/((?<!\\\)[&!])/', '\\\$1', $sql);
        }

        // needed for PHP 5.3
        $that = $this;
        $tablePrefix = $this->tablePrefix;

        $sql = preg_replace_callback(
            '/(\\{\\{(%?[\w\-\. ]+%?)\\}\\}|\\[\\[([\w\-\. ]+)\\]\\])/',
            function ($matches) use ($that, $tablePrefix) {
                if (isset($matches[3])) {
                    return $that->quoteIdentifier($matches[3]);
                } else {
                    return str_replace('%', $tablePrefix, $that->quoteIdentifier($matches[2]));
                }
            },
            $sql
        );

        return $sql;
    }
}
