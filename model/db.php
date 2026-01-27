<?php
/*
 * Database methods using PDO library
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please contact PhreeSoft for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-01-27
 * @filesource /model/db.php
 */

namespace bizuno;

class db extends \PDO
{
    var     $total_count = 0;
    var     $total_time  = 0;
    public  $connected   = false;
    private $max_input_size = 60; // maximum input form field display length for larger db fields
    private $driver = 'mysql';

    /**
     * Constructor to connect to db, sets $connected to true if successful, returns if no params sent
     * @param array $dbData - database credentials to auto-connect, if set
     */
    function __construct($dbData)
    {
        if (empty($dbData['host']) || empty($dbData['name']) || empty($dbData['user']) || empty($dbData['pass'])) { return; }
        $this->driver = !empty($dbData['type']) ? $dbData['type'] : 'mysql';
        $dns  = "{$dbData['type']}:host={$dbData['host']};dbname={$dbData['name']}";
        $user = $dbData['user'];
        $pass = $dbData['pass'];
        switch($this->driver) {
            default:
            case "mysql":
                try { parent::__construct($dns, $user, $pass); }
                catch (\PDOException $e) { return msgAdd("\nDB Connection failed, error: ".$e->getMessage()); } // ." with db settings: ".print_r($dbData, true)
                $this->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                $this->exec("SET character_set_results='utf8', character_set_client='utf8', character_set_connection='utf8', character_set_database='utf8', character_set_server='utf8'");
                break;
        }
        $this->connected = true;
    }

    /**
     * Generic SQL query wrapper for executing queries, has error logging and debug messages
     * @param string $sql - The SQL statement
     * @param string $action [default: stmt] - action to perform, choices are: insert, update, delete, row, rows, stmt
     * @return false on error, array or statement on success depending on request
     */
    function Execute($sql, $action='stmt', $verbose=false)
    {
        if (!$this->connected) { die('ERROR: Not connected to the db!'); }
        msgDebug("\nEntering Execute with action $action and sql: $sql");
        $error     = false;
        $output    = false;
        $msgResult = '';
        $time_start= explode(' ', microtime());
        switch ($action) {
            case 'insert': // returns id of new row inserted
                if (false !== $this->exec($sql)) { $output = $this->lastInsertId(); } else { $error = true; }
                $msgResult = "row ID = $output";
                break;
            case 'update':
            case 'delete': // returns affected rows
                $output = $this->exec($sql);
                if ($output === false) { $error = true; }
                $msgResult = "number of affected rows = $output";
                break;
            case 'row': // returns single table row
                $stmt = $this->query($sql);
                if ($stmt) {
                    $output = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if (!is_array($output)) { $output = []; }
                }
                else { $output = []; $error = true; }
                $msgResult = "number of fields = ".sizeof($output);
                break;
            case 'rows': // returns array of one or more table rows
                $stmt = $this->query($sql);
                if ($stmt) {
                    $output = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $stmt->closeCursor();
                } else { $output = []; $error = true; }
                $msgResult = "number of rows = ".sizeof($output);
                break;
            default:
            case 'stmt': // PDO Statement
                if (!$output = $this->query($sql)) { $error = true; }
                break;
        }
        $time_end = explode (' ', microtime());
        $query_time = $time_end[1] + $time_end[0] - $time_start[1] - $time_start[0];
        $this->total_time += $query_time;
        $this->total_count++;
        msgDebug("\nReturning result (in $query_time ms): $msgResult");
        if ($error) {
            $errorMsg = "\nSQL Error: ".print_r($this->errorInfo(), true);
            msgDebug($errorMsg, 'trap');
            // below fails in multi-business since BIZUNO_DB_PREFIX is not yet defined, maybe use prefix from construct to handle all cases
            if ($verbose || (dbTableExists(BIZUNO_DB_PREFIX.'configuration'))) { msgAdd($errorMsg); } // make sure we're installed
        }
        return $output;
    }

    /**
     * Used to update a database table to the Bizuno structure. Mostly for conversion from anther base (i.e. PhreeBooks)
     * @param string $table - The database table to update (performs an ALTER TABLE SQL command)
     * @param string $field - The database field name to alter
     * @param array $props - information pulled from the table structure of settings to add to the altered field.
     * @return boolean - Nothing of interest, just writes the table
     */
    function alterField($table, $field, $props=[])
    {
        if (!$table || !$field) { return; }
        $comment = $temp = [];
        $sql = "ALTER TABLE $table CHANGE `$field` `$field` ";
        if (!empty($props['dbType'])) { $sql .= ' '.$props['dbType']; }
        if (!empty($props['collate'])){ $sql .= " CHARACTER SET utf8 COLLATE ".$props['collate']; }
        if ( isset($props['null']) && strtolower($props['null'])=='no') { $sql .= ' NOT NULL'; }
        if ( isset($props['default'])) { $sql .= " DEFAULT '".$props['default']."'"; }
        if (!empty($props['extra']))  { $sql .= ' '.$props['extra']; }
        if (!empty($props['tag']))    { $comment['tag']  = $props['tag']; }
        if (!empty($props['tab']))    { $comment['tab']  = $props['tab']; }
        if (!empty($props['order']))  { $comment['order']= $props['order']; }
        if (!empty($props['label']))  { $comment['label']= $props['label']; }
        if (!empty($props['group']))  { $comment['group']= $props['group']; }
        if (!empty($props['type']))   { $comment['type'] = $props['type']; }
        if (!empty($props['req']))    { $comment['req']  = $props['req']; }
        foreach ($comment as $key => $value) { $temp[] = "$key:$value"; }
        if (sizeof($temp) > 0) { $sql .= " COMMENT '".implode(';',$temp)."'"; }
        dbGetResult($sql);
    }

    /**
     * This function builds the table structure as a basis for building pages and reading/writing the db
     * @param string $table - The database table to examine.
     * @param string $suffix - [optional, default ''] Loads the language based on the added suffix, used for multiplexed tables.
     * @param string $prefix - [optional, default ''] Loads the language based on the added prefix, used for multiplexed tables.
     * @param string $lang - [optional, default []] Language Overrides
     * @return array $output - [optional, default ''] Contains the table structural settings, indexed by the field name
     */
    public function loadStructure($table, $suffix='', $prefix='', $lang=[])
    {
        $output = [];
        if (!empty($GLOBALS['bizTables'][$table])) { return $GLOBALS['bizTables'][$table]; } // already loaded
        if (!$oResult = $this->query("SHOW FULL COLUMNS FROM $table")) {
            msgAdd("Failed loading structure for table: $table");
            return [];
        }
        $base_table= str_replace(BIZUNO_DB_PREFIX, '', $table);
        $result    = $oResult->fetchAll();
        $order     = 1;
        foreach ($result as $row) {
            $comment = [];
            if (!empty($row['Comment'])) {
                $temp = explode(';', $row['Comment']);
                foreach ($temp as $entry) {
                    $param = explode(':', $entry, 2);
                    $comment[trim($param[0])] = trim($param[1]);
                }
            }
            $output[$row['Field']] = [
                'table'  => $base_table,
                'dbfield'=> $table.'.'.$row['Field'], //id,
                'dbType' => $row['Type'],
                'field'  => $row['Field'],
                'break'  => true,
                'null'   => $row['Null'], //NO, YES
                'collate'=> $row['Collation'],
                'key'    => $row['Key'], //PRI,
                'default'=> $row['Default'], //'',
                'extra'  => $row['Extra'], //auto_increment,
                'comment'=> $row['Comment'],
                'tag'    => $prefix.(isset($comment['tag'])?$comment['tag']:$row['Field']).$suffix,
                'tab'    => isset($comment['tab'])  ? $comment['tab']  : 0,
                'group'  => isset($comment['group'])? $comment['group']: '',
                'col'    => isset($comment['col'])  ? $comment['col']  : 1,
                'order'  => isset($comment['order'])? $comment['order']: $order,
                'label'  => $this->guessLabel($table, $row['Field'], $suffix, $comment, $lang),
                'attr'   => $this->buildAttr($row, $comment)];
            $trash = [];
            $output[$row['Field']]['format'] = isset($comment['format']) ? $comment['format'] : $this->guessFormat($trash, $row['Type']); // db data type
            if (in_array(substr($row['Type'], 0, 4), ["ENUM", "enum"])) {
                $keys   = explode(',', str_replace(["ENUM", "enum", "(", ")", "'"], '', $row['Type']));
                $values = isset($comment['opts']) ? explode(':', $comment['opts']) : $keys;
                foreach ($keys as $idx => $key) {
                    $output[$row['Field']]['opts'][] = ['id'=>trim($key), 'text'=>isset($values[$idx]) ? trim($values[$idx]) : trim($key)];
                }
            }
            $order++;
        }
        $GLOBALS['bizTables'][$table] = $output; // save structure globally
        return $output;
    }

    private function guessLabel($table, $field, $suffix, $comment, $lang) {
        if (isset($comment['label'])){ return $comment['label']; }
        if (isset($lang[$field]))    { return $lang[$field]; }
        return !empty($suffix) ? lang($field.'_'.$suffix) : lang($field);
    }

    private function guessFormat(&$data, $type)
    {
        $data_type = (strpos($type,'(') === false) ? strtolower($type) : strtolower(substr($type,0,strpos($type,'(')));
        switch ($data_type) {
            case 'date':      $data['type']='date';    break;
            case 'time':      $data['type']='time';    break;
            case 'datetime':
            case 'timestamp': $data['type']='datetime';break;
            case 'bigint':
            case 'int':
            case 'mediumint':
            case 'smallint':
            case 'tinyint':   $data['type']='integer'; break;
            case 'decimal':
            case 'double':
            case 'float':     $data['type']='float';   break;
            case 'enum':
            case 'set':       $data['type']='select';  break;
            default:
            case 'char':
            case 'varchar':
            case 'tinyblob':
            case 'tinytext':
                $data['type']      = 'text';
                $data['size']      = min($this->max_input_size, trim(substr($type, strpos($type,'(')+1, strpos($type,')')-strpos($type,'(')-1)));
                $data['maxlength'] = trim(substr($type, strpos($type,'(')+1, strpos($type,')')-strpos($type,'(')-1));
                if ($data['maxlength'] > 128) { $data['type'] = 'textarea'; } break;
            case 'blob':
            case 'text':
            case 'mediumblob':
            case 'mediumtext':
            case 'longblob':
            case 'longtext':
                $data['type']      = 'textarea';
                $data['maxlength'] = '65535'; break;
        }
        return $data['type'];
    }

    /**
     * Builds the attributes to best guess the HTML structure, primarily used to build HTML input tags
     * @param array $fields - Contains the indexed field settings
     * @param array $comment - contains the working COMMENT array to build the attributes, stuff not contained in the basic mySQL table information
     * @return array $output - becomes the 'attr' index of the database field
     */
    private function buildAttr($fields, $comment)
    {
        $result= ['value'=>''];
        $this->guessFormat($result, $fields['Type']);
        if (isset($comment['type'])) { $result['type'] = $comment['type']; } // reset type if specified, messes up dropdowns
        if (isset($comment['req']) && $comment['req'] == '1') { $result['required'] = 'true'; }
        if ($fields['Default']) { $result['value'] = $fields['Default']; }
        switch ($result['type']) { // now some special cases
            case 'checkbox':
                if (isset($result['value']) && $result['value']) { $result['checked'] = 'checked'; }
                $result['value'] = 1;
                break;
            case 'select':   unset($result['size']); break;
            case 'textarea': $result['cols'] = 60; $result['rows'] = 4; break; // @todo this is a problem as it needs to vary depending on screen
            default:
        }
        return $result;
    }
}

/**
 * This function is a wrapper to start a db transaction
 * @global type $db - database connection
 */
function dbTransactionStart()
{
    global $db;
    msgDebug("\n/************ STARTING TRANSACTION *************/");
    if (!$db->inTransaction()) {
        if (!$db->beginTransaction()) { msgAdd("Houston, we have a problem. Failed starting transaction!"); }
    }
}

/**
 * This function is a wrapper to commit a db transaction
 * @global type $db - database connection
 */
function dbTransactionCommit()
{
    global $db;
    msgDebug("\n/************ COMMITTING TRANSACTION *************/");
    if ($db->inTransaction()) {
        if (!$db->commit()) { msgAdd("Houston, we have a problem. Failed committing transaction!"); }
    }
}

/**
 * This function is a wrapper to roll back a db transaction
 * @global type $db - database connection
 */
function dbTransactionRollback()
{
    global $db;
    msgDebug("\nRolling Back Transaction.");
    if (!$db->rollBack()) { msgAdd("Trying to roll back transaction when no transactions are active!"); }
}

/**
 * Writes values to the db, can be used for both inserting new rows or updating based on specified criteria
 * @global object $db - database connection
 * @param string $table - Database table (need to add prefix first)
 * @param array $data example array("field_name" => 'value_update' ...)
 * @param string $action choices are insert [DEFAULT] or update
 * @param string $parameters make up the WHERE statement during an update, only used for action == update
 * @param $quote [default: true] - quotes added to field, if turned off allows SQL methods
 * @return record id for insert, affected rows for update, false on error
 */
function dbWrite($table, $data, $action='insert', $parameters='', $quote=true)
{
    global $db;
    if (!is_object($db) || !$db->connected || !is_array($data) || sizeof($data) == 0) { return msgDebug("\nError: Tried to write but failed the entry testing!", 'trap'); }
    $sql = dbWritePrep($table, $data, $action, $parameters, $quote);
    return $db->Execute($sql, $action);
}

function dbWritePrep($table, $data, $action='insert', $parameters='', $quote=true)
{
    $columns = [];
    if ($action == 'insert') {
        $query = "INSERT INTO $table (`".implode('`, `', array_keys($data))."`) VALUES (";
        foreach ($data as $value) {
            if (is_array($value)) {
                msgDebug("\nExpecting string and instead got: ".print_r($value, true));
                msgAdd("Expecting string and instead got: ".print_r($value, true), 'caution');
            }
            if (is_null($value)) { $value = 'null'; }
            switch ((string)$value) {
                case 'NOW()':
                case 'now()': $query .= "now(), "; break;
                case 'NULL':
                case 'null':  $query .= "null, ";  break;
                default:      $query .= $quote ? "'".addslashes($value)."', " : "$value, "; break;
            }
        }
        $query = substr($query, 0, -2) . ')';
    } elseif ($action == 'update') {
        foreach ($data as $column => $value) {
            switch ((string)$value) {
                case 'NOW()':
                case 'now()': $columns[] = "`$column`=NOW()"; break;
                case 'NULL':
                case 'null':  $columns[] = "`$column`=NULL";  break;
                default:      $columns[] = $quote ? "`$column`='".addslashes((string)$value)."'" : "`$column`=$value"; break;
            }
        }
        $query = "UPDATE $table SET ".implode(', ', $columns) . ($parameters<>'' ? " WHERE $parameters" : '');
    }
    return $query;
}

/*
 * Sanitizes date fields to meet db formatting criteria
 * @param array data - List fo field values to modify, will become the sql field list
 * @param array $fields - Specific fields to test for invalid values
 */
function dbSanitizeDates(&$data, $fields)
{
    msgDebug("\nEntering dbSanitizeDates");
    foreach ($fields as $field) {
        msgDebug("\nWorking with field: $field and data = ".$data[$field]);
        if (!array_key_exists($field, $data)) { continue; }
        msgDebug(" ... Field is set");
        if (empty($data[$field]) || '0000-00-00'==$data[$field] || '0000-00-00 00:00:00'==$data[$field]) {
            $data[$field] = 'NULL';
            msgDebug(" ... Fixed to be = ".$data[$field]);
        }
    }
    // Temp patch for older db's with non-strict types
    if (array_key_exists('ach_routing', $data) && empty($data['ach_routing'])) { $data['ach_routing'] = 0; }
}

/**
 * Write the cache to the db if changes have been made
 * @global array $bizunoUser - User cache structure
 * @global array $bizunoLang - Language translation array
 * @global array $bizunoMod - Module cache structure
 * @param string $usrEmail - Users email address
 * @param string $lang - (xx_XX) ISO language to use for saving the language translations to a file in the myFiles folder
 * @return null - cache is written to database
 */
function dbWriteCache()
{
    global $db, $bizunoMod;
    msgDebug("\nentering dbWriteCache");
    if (isset($GLOBALS['updateModuleCache']) && empty($GLOBALS['noBizunoDB'])) {
        if (empty($db->connected) || !dbTableExists(BIZUNO_DB_PREFIX.'configuration') ) {
            return msgDebug("\nTrying to write to cache but user is not logged in or Bizuno not installed!");
        }
        // BOF - Delete old registry entries
        unset($bizunoMod['bizuno']['shop']); // clean up some unneeded fields
        // EOF - Delete old registry entries
        ksort($bizunoMod);
        foreach ($bizunoMod as $module => $settings) {
            if (!empty($GLOBALS['updateModuleCache'][$module])) {
                if (!isset($settings['properties'])) { continue; } // update by another module with this module not loaded, skip
                msgDebug("\nWriting config table for module: $module");
                dbWrite(BIZUNO_DB_PREFIX.'configuration', ['config_value'=>json_encode($settings)], 'update', "config_key='$module'");
                unset($GLOBALS['updateModuleCache'][$module]);
            }
        }
    }
}

function dbCleanRoles($strRoles='') {
    $arrRoles = explode(":", $strRoles);
    if (empty($arrRoles)) { return []; }
    $output = [];
    foreach ($arrRoles as $role) {
        if     (in_array($role, [-1, 0])) { $output[] = $role; } // handles All and None
        elseif (dbMetaGet($role, 'bizuno_role')) { $output[] = $role; }
    }
    return $output;
}

/**
 * Dumps the DB (or table) to a gzipped file into a specified folder
 * @param string $filename - Name of the file to create
 * @param string $dirWrite - Folder in the user root to write to, defaults to backups/
 * @param type $dbTable - (Default ALL TABLES), set to table name for a single table
 */
function dbDump($filename='bizuno_backup', $dirWrite='', $dbTable='')
{
    global $io;
    // set execution time limit to a large number to allow extra time
    set_time_limit(20000);
    $dbHost = BIZUNO_DB_CREDS['host'];
    $dbName = BIZUNO_DB_CREDS['name'];
    $dbUser = BIZUNO_DB_CREDS['user'];
    $dbPass = BIZUNO_DB_CREDS['pass'];
    $dbPath = BIZUNO_DATA.$dirWrite;
    $dbFile = $filename.".sql.gz";
    if (!$dbTable && BIZUNO_DB_PREFIX <> '') { // fetch table list (will be entire db if no prefix)
        if (!$stmt= dbGetResult("SHOW TABLES FROM $dbName LIKE '".BIZUNO_DB_PREFIX."%'")) { return; }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($rows) { foreach ($rows as $row) { $dbTable .= array_shift($row).' '; } }
    }
    if (!$io->validatePath($dirWrite.$dbFile, true, true)) { return; }
    $cmd    = "mysqldump --opt -h $dbHost -u $dbUser -p$dbPass $dbName $dbTable | gzip > $dbPath$dbFile";
    msgDebug("\n Executing command: $cmd");
    if (!function_exists('exec')) { msgAdd("php exec is disabled, the backup cannot be achieved this way!"); }
    exec($cmd);
    if (!file_exists($dbPath.$dbFile)) { return; } // for some reason the dump failed, could be out of disk space
    chmod($dbPath.$dbFile, 0664);
    return true;
}

/**
 * Deletes rows from the db based on the specified filter, if no filter is sent, a delete is not performed (as a safety measure)
 * @global type $db - database connection
 * @param string $table database table to act upon
 * @param string $filter [default: false] - forms the WHERE statement
 * @return integer - number of affected rows
 */
function dbDelete($table, $filter=false)
{
    global $db;
    if (!$filter) { return; }
    $sql = "DELETE FROM $table".($filter ? " WHERE $filter" : '');
    $row = $db->Execute($sql, 'delete');
    return $row;
}

/**
 * Pulls a value or array of values from a db table row
 * @global type $db - database connection
 * @param string $table - database table name
 * @param mixed $field - one (string) or more (array) fields to retrieve
 * @param string $filter - [default: false] criteria to limit results
 * @param boolean $quote - [default: true] to add quotes to field names, false to leave off for executing SQL functions, i.e. SUM(field)
 * @param boolean $verbose [default: false] - true to show any errors or messages if there is a problem
 * @return multitype - false if no results, string if field is string, keyed array if field is array
 */
function dbGetValue($table, $field, $filter=false, $quote=true, $verbose=false)
{
    global $db;
    if (!is_object($db)) { msgDebug("\ndb is NOT an object, need to initialize!!!"); return false; }
    if (!$db->connected) { msgDebug("\nnot connected to the db!!!"); return false; }
    $is_array = is_array($field) ? true : false;
    if (!is_array($field)) { $field = [$field]; }
    if ($quote) { $table = "`$table`"; }
    $sql = "SELECT ".($quote ? ("`".implode('`, `', $field)."`") : implode(', ', $field))." FROM $table".($filter ? " WHERE $filter" : '')." LIMIT 1";
    $result = $db->Execute($sql, 'row', $verbose);
    if ($result === false) { return; }
    if ($is_array) { return $result; }
    return is_array($result) ? array_shift($result) : false;
}

/**
 * Pulls a single row from a db table
 * @global type $db - database connection
 * @param string $table - database table name, prefix will be added
 * @param string $filter - query filter parameters,
 * @return array - table row results, false if error/no data
 */
function dbGetRow($table, $filter='', $quote=true, $verbose=false)
{
    global $db;
    if (!is_object($db)) { msgDebug("\ndb is NOT an object, need to initialize!!!"); return false; }
    if (!$db->connected) { msgDebug("\nNot connected to the db!!!"); return false; }
    if ($quote) { $sql = "SELECT * FROM `$table`".($filter ? " WHERE $filter" : '')." LIMIT 1"; }
    else        { $sql = "SELECT * FROM $table".($filter ? " WHERE $filter" : '')." LIMIT 1"; }
    $row = $db->Execute($sql, 'row', $verbose);
    return $row;
}

/**
 * Pulls multiple rows from a db table
 * @global type $db - database connection
 * @param string $table - db table name
 * @param string $filter - criteria to limit data
 * @param string $order - sort order of result
 * @param mixed $field - Leave blank for all fields in row (*), otherwise fields may be string or array
 * @param integer $limit - [default 0 - no limit] limit the number of results returned
 * @param boolean $quote - [default true] Specifies if quotes should be placed around the field names, false if already escaped
 * @return array - empty for no hits or array of rows (keyed array) for one or more hits
 */
function dbGetMulti($table, $filter='', $order='', $field='*', $limit=0, $quote=true)
{
    global $db;
    if (!is_object($db)) { msgDebug("\ndb is NOT an object, need to initialize!!!"); return []; }
    if (!$db->connected) { msgDebug("\nnot connected to the db!!!"); return []; }
    if (empty($field)) { $field = '*'; }
    if (is_array($field)) { $field = $quote ? "`".implode('`, `', $field)."`" : implode(', ', $field); }
    elseif ($field!='*')  { $field = $quote ? "`$field`" : $field; }
    $sql = "SELECT $field FROM $table".($filter ? " WHERE $filter" : '').(trim($order) ? " ORDER BY $order" : '');
    if ($limit) { $sql .= " LIMIT $limit"; }
    return $db->Execute($sql, 'rows');
}

/**
 * Executes a query and returns the resulting PDO statement
 * @global object $db - database connection
 * @param string $sql - the QUOTED, ESCAPED SQL to be executed
 * @param string $action [default: stmt] - action to perform, i.e. expected results
 * @return PDOStatement - Must be handled by caller to properly process results
 */
function dbGetResult($sql, $action='stmt')
{
    global $db;
    if (!is_object($db)) { return false; }
    if (!$db->connected) { return false; }
    return $db->Execute($sql, $action);
}

/**
 * This function takes an array of actions and executed them. Typical usage is after custom processing and end of script.
 * @param array $data - structure of resulting processing
 * @param string $hide_result - whether to show errors/success of hide message output
 * @return boolean - success or error
 */
function dbAction(&$data, $hide_result=false)
{
    $error = false;
    if (!is_array($data['dbAction'])) { return; } // nothing to do
    msgDebug("\nIn dbAction starting transaction with size of array = ".sizeof($data['dbAction']));
    dbTransactionStart();
    foreach ($data['dbAction'] as $table => $sql) {
        msgDebug("\nDatabase action for table $table and sql: $sql");
        if (!dbGetResult($sql)) { $error = true; }
    }
    if ($error) {
        if (!$hide_result) { msgAdd(lang('err_database_write')); }
        dbTransactionRollback();
        return;
    }
    if (!$hide_result) { msgAdd(lang('msg_database_write'), 'success'); }
    dbTransactionCommit();
    return true;
}

/**
 * Pulls a specific index from the settings field from a within a table for a single row.
 * @param string $table - db table name
 * @param string $index - index within the settings to extract information
 * @param string $filter - db filter used to restrict results to a single row
 * @return string - Result of setting, null if not present
 */
function dbPullSetting($table, $index, $filter = false)
{
    $settings = json_decode(dbGetValue($table, 'settings', $filter), true);
    return (isset($settings[$index])) ? $settings[$index] : $index;
}

/**
 * Checks for the existence of a table
 * @param string $table - db table to look for
 * @return boolean - true if table exist, false otherwise
 */
function dbTableExists($table)
{
    if (empty($GLOBALS['BIZUNO_TABLES'])) {
        if (!$stmt = dbGetResult("SHOW TABLES;")) { return; }
        if (!$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC)) { return; }
        $output = [];
        foreach ((array)$rows as $row) { $output[] = array_shift($row); }
        $GLOBALS['BIZUNO_TABLES'] = $output;
        msgDebug("\nRead ".sizeof($output)." tables in dbTableExists.");
    }
    return (in_array($table, $GLOBALS['BIZUNO_TABLES'])) ? true : false;
}

/**
 * Checks to see if a field exists within a table
 * @global type $db - database connection
 * @param string $table - db table containing the field to search for
 * @param string $field - field within the table to search for
 * @return boolean - true if field exists, false otherwise
 */
function dbFieldExists($table, $field)
{
    global $db;
    $result = $db->query("SHOW FIELDS FROM `$table`");
    if (!$result) { return; }
    foreach ($result as $row) {
        if ($row['Field'] == $field) { return true; }
    }
}

/**
 * Installs db tables when a module is installed based on the list passed
 * @param array $tables - list of tables to create
 * @return boolean true on success, false on error
 */
 function dbInstallTables($tables=[])
{
    foreach ($tables as $table => $props) {
        $fields = [];
        foreach ($props['fields'] as $field => $values) {
            $temp = "`$field` ".$values['format']." ".$values['attr'];
            if (isset($values['comment'])) { $temp .= " COMMENT '".$values['comment']."'"; }
            $fields[] = $temp;
        }
        msgDebug("\n    Creating table: $table");
        $sql = "CREATE TABLE IF NOT EXISTS `".BIZUNO_DB_PREFIX."$table` (".implode(', ', $fields).", ".$props['keys']." ) ".$props['attr'];
        dbGetResult($sql);
    }
}

/**
 * Wrapper to load the structure of a table to parse input variables
 * @global class $db - database connection
 * @param string $table - db table
 * @param string $suffix - [default: ''] suffix to use when building attributes
 * @param string $prefix - [default: ''] prefix to use when building attributes
 * @param array $lang - Language overrides
 * @return array - table properties
 */
function dbLoadStructure($table, $suffix='', $prefix='', $lang=[])
{
    global $db;
    msgDebug("\nEntering dbLoadStructure with table: $table, suffix = $suffix, prefix = $prefix");
    return $db->loadStructure($table, $suffix, $prefix, $lang);
}

/**
 * This function merges database data into the structure attributes
 * @param array $structure - database table structure
 * @param array $data - database data row to fill $attr['value']
 * @param string $suffix [default: ''] - adds a suffix to the index if present
 * @return array - modified $structure
 */
function dbStructureFill(&$structure, $data=[], $suffix='')
{
    if (!is_array($data)) { return; }
    foreach ($structure as $field => $values) {
        if (!isset($values['attr']['type'])) { $values['attr']['type'] = 'text'; }
        switch ($values['attr']['type']) {
            case 'checkbox':
                if (isset($data[$field.$suffix])) { // only adjust if the field is present, prevents clearing flag if field is not part of merge
                    if ($data[$field.$suffix]) { $structure[$field]['attr']['checked'] = 'checked'; } else { unset($structure[$field]['attr']['checked']); }
                }
                break;
            default:
                $structure[$field]['attr']['value'] = isset($data[$field.$suffix]) ? $data[$field.$suffix] : '';
        }
    }
}

/**
 * This function builds the SQL and loads into an array the result of the query.
 * @param array $data - the structure to build the SQL and read data
 * @return array - integer [total] - total number of rows, array [rows] - row data - can be sent directly to view
 */
function dbTableRead($data)
{
    global $currencies;
    msgDebug("\nEntering dbTableRead"); //  with data = ".print_r($data, true));
    // Need to force strict mode if more than one table as fields with same name will overlap resulting in bad content, i.e. column id gets last tables value
    if (sizeof($data['source']['tables']) > 1) { $data['strict'] = true; }
    $sqlTables  = dbTableReadTables  ($data['source']['tables']);
    $sqlCriteria= dbTableReadCriteria($data['source']['filters'], !empty($data['source']['search']) ? $data['source']['search'] : []);
    $sqlOrder   = dbTableReadOrder   ($data['source']['sort']);
    $output     = ['total'=>0, 'rows'=>[]];
    if (!empty($data['strict'])) {
        $aFields   = [];
        $columns = sortOrder($data['columns']);
        foreach ($columns as $key => $value) {
            if ($key == 'action') { continue; } // skip action column
            if (isset($value['field']) && strpos($value['field'], ":") !== false) { // look for embedded settings
                $parts = explode(":", $value['field'], 2);
                $aFields[] = "{$parts[0]} AS `{$parts[0]}`";
            } elseif (isset($value['field'])) {
                $aFields[] = $value['field']." AS `$key`";
            }
        }
        if (!$temp = dbGetMulti($sqlTables, $sqlCriteria, $sqlOrder, $aFields, 0, false)) { return $output; }
        $output['total'] = sizeof($temp);
        $result = array_slice($temp, ($data['page']-1)*$data['rows'], $data['rows']);
        msgDebug("\n started with ".$output['total']." rows, page = {$data['page']}, rows = {$data['rows']}, resulted in effective row count = ".sizeof($result));
    } else { // pull all columns irregardless of the field list
        $limit   = isset($data['rows']) && isset($data['page']) ? (($data['page']-1)*$data['rows']).", ".$data['rows'] : 0;
        $output['total'] = dbGetValue($sqlTables, 'count(*) AS cnt', $sqlCriteria, false);
        msgDebug("\n total rows via count(*) = ".$output['total']);
        if (!$result = dbGetMulti($sqlTables, $sqlCriteria, $sqlOrder, '*', $limit)) { return $output; }
    }
    foreach ($result as $row) {
        $GLOBALS['currentRow'] = $row; // save the raw data for aliases and formatting alterations of data
        if (isset($row['currency'])) {
            $currencies->iso  = $row['currency']; // @todo this needs to temporarily set a value in viewFormatter for processing
            $currencies->rate = isset($row['currency_rate']) ? $row['currency_rate'] : 1;
        }
        foreach ($data['columns'] as $key => $value) {
            if (isset($value['field']) && strpos($value['field'], ":") !== false) { // look for embedded settings
                $parts = explode(":", $value['field'], 2);
                $tmp = json_decode($row[$parts[0]], true);
                $row[$key] = isset($parts[1]) && isset($tmp[$parts[1]]) ? $tmp[$parts[1]] : '';
            }
            if (isset($value['alias'])) { $row[$key] = $GLOBALS['currentRow'][$value['alias']]; }
        }
        foreach ($row as $key => $value) {
            if (!empty($data['columns'][$key]['process'])){ $row[$key] = viewProcess($row[$key], $data['columns'][$key]['process']); }
            if (!empty($data['columns'][$key]['format'])) { $row[$key] = viewFormat ($row[$key], $data['columns'][$key]['format']); }
        }
        $output['rows'][] = $row;
    }
    msgDebug("\nReturning from dbTableRead with the first 10 rows = ".print_r(array_slice($output, 0, 10), true));
    return $output;
}

function dbTableReadTables($tables=[])
{
    $sqlTables = '';
    foreach ($tables as $table) {
        $sqlTables .= isset($table['join']) && strlen($table['join'])>0 ?  ' '.$table['join'] : '';
        $sqlTables .= ' '.$table['table'];
        $sqlTables .= isset($table['links'])&& strlen($table['links'])>0? ' ON '.$table['links'] : '';
    }
    return $sqlTables;
}

/**
 * 
 * @param array $crit - criteria to build filter
 * @param array $search - fields to search
 * @return type
 */
function dbTableReadCriteria($crit=[], $search=[])
{
    msgDebug("\nEntering dbTableReadCriteria with sizeof criteria = ".sizeof($crit));
    $criteria = [];
    if (!empty($crit)) {
        foreach ($crit as $key => $value) {
            if ($key=='search') {
                if (isset($value['attr']) && !empty($value['attr']['value'])) {
                    $criteria[] = dbGetSearch($value['attr']['value'], $search);
                }
            } else {
                if (!empty($value['sql'])) { $criteria[] = $value['sql']; }
            }
        }
    }
    return implode(' AND ', $criteria);
}

function dbTableReadOrder($sorts=[])
{
    if (empty($sorts)) { return ''; }
    $order = [];
    $sorted = sortOrder($sorts);
    foreach ($sorted as $value) { if (strlen($value['field']) > 1) { $order[] = $value['field']; } }
    return !empty($order) ? implode(', ', $order) : '';
}

/**
 * Pulls the meta value from one of the four meta tables
 * @param type $rID - record ID, use % for wildcard, 0 to search by key
 * @param type $key - meta_key prefix, append % for multiple searches 
 * @param type $table - [default: common] table to search, choices are: common, contacts, inventory, and journal
 * @param type $refID - [default: 0] reference id, use % for searches across multiple refs, values are table contact record id, table inventory record  id or journal_main record id
 * @param type $args - passed options, defaults: object=>false
 * @return mixed - for single hits: value or associative array; multiple hits: array
 */
function dbMetaGet($rID, $key, $table='common', $refID=0, $args=[])
{
    msgDebug("\nEntering dbMetaGet with rID = $rID, refID = $refID, table = $table, key = $key");
    $opts = array_replace(['object'=>false], $args);
    $multi= strpos($key, '%')!==false ? true : false;
    $crit = [];
    if (!empty($rID) && $rID<>'%') { // if we have an rID then the only other var needed is the table
        $crit[] = "id=$rID";
    } else {
        if ($table<>'common' && "$refID"<>'%') { $crit[] = "ref_id=$refID"; }
        $crit[] = 'meta_key'.($multi ? " LIKE '".addslashes($key)."'" : "='".addslashes($key)."'");
    }
    $rows = dbGetMulti(BIZUNO_DB_PREFIX."{$table}_meta", implode(' AND ', $crit));
    $resp = [];
    foreach ($rows as $row) {
        if ($opts['object']) {
            $val = json_decode($row['meta_value']);
            if (!is_object($val)) { $val = (object)['value'=>$val]; }
            $val->_rID   = $row['id'];
            $val->_refID = isset($row['ref_id'])?$row['ref_id']:0;
            $val->_table = $table;
        } else {
//            $val = json_decode($row['meta_value'], true); // This returned null when a string is in the meta_value field
//            if (!is_array($val)) { $val = ['value'=>$val]; }
            $val = json_validate($row['meta_value']) ? json_decode($row['meta_value'], true) : ['value'=>$row['meta_value']];
            if (!is_array($val)) { $val = [$val]; }
            $val = array_merge($val, ['_rID'=>$row['id'], '_refID'=>isset($row['ref_id'])?$row['ref_id']:0, '_table'=>$table]);
        }
        $resp[]= $val;
    }
    return "$rID"=='%' || $multi || sizeof($resp)>1 ? $resp : array_shift($resp); // quotes around $rID forces a string compare
}

/**
 * Inserts or updates 
 * @param type $rID = specific id within the specified table, if 0 then a new record will be generated
 * @param type $key = the value to use as the key, this replaces the current value with specified value
 * @param type $value = value to be written, arrays/objects will be json_encoded, scalars written directly
 * @param type $table - [default: common] Table to write data, choices are common, contacts, inventory, and journal
 * @param type $refID - [default: 0] reference to main record ID of tables contacts, inventory and journal
 * @return record ID if insert, number of affected records for updates
 */
function dbMetaSet($rID, $key, $value, $table='common', $refID=0)
{
    msgDebug("\nEntering dbMetaSet with rID = $rID, refID = $refID, table = $table, key = $key");
    $data = ['ref_id'=>!empty($refID)?$refID:0, 'meta_key'=>$key, 'meta_value'=>is_array($value) || is_object($value) ? json_encode($value) : $value];
    if ($table=='common') { unset($data['ref_id']); } // field not in the common table
    return dbWrite(BIZUNO_DB_PREFIX."{$table}_meta", $data, !empty($rID)?'update':'insert', "id=$rID");
}

/**
 * Removes a record from the specified table
 * @param type $rID - record id from within the specific table
 * @param type $table - [default: common] options are common, contacts, inventory and journal
 * @return null
 */
function dbMetaDelete($rID, $table='common')
{
    msgDebug("\nEntering dbMetaDelete with table = $table and rID = $rID");
    if (empty($rID)) { return msgDebug("\nTrying to delete but rID is not an integer"); } // simple error check
    dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."{$table}_meta WHERE id=$rID");
}

/**
 * This function builds the SQL and loads into an array the result of the query.
 * @param array $data - the structure to build the SQL and read data
 * @param boolean $raw - [default false] if true returns raw data before formatting and pagination operations
 * @return array - integer [total] - total number of rows, array [rows] - row data - can be sent directly to view
 */
function dbMetaRead($data, $raw=false)
{
    $defs = ['search'=>'','sort'=>'','order'=>'ASC','page'=>1,'rows'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')];
    $args = array_replace($defs, $data['settings']);
    msgDebug("\nEntering dbMetaRead with args after combine = ".print_r($args, true));
    switch ($data['metagrid']['metaTable']) {
        default:
        case 'common':    $metaData = dbMetaGet('%', $data['metagrid']['metaPrefix']);                                           break;
        case 'contacts':  $metaData = dbMetaGet('%', $data['metagrid']['metaPrefix'], 'contacts', $data['metagrid']['metaKey']); break;
        case 'inventory': $metaData = dbMetaGet('%', $data['metagrid']['metaPrefix'], 'inventory',$data['metagrid']['metaKey']); break;
        case 'journal':   $metaData = dbMetaGet('%', $data['metagrid']['metaPrefix'], 'journal',  $data['metagrid']['metaKey']); break;
    }
    if ($raw) { return $metaData; }
    $output = dbMetaReadSearch($metaData, $data['metagrid'], $args['search']);
    foreach ($output as $idx => $row) {
        foreach ($row as $key => $value) {
            if (!empty($data['metagrid']['columns'][$key]['process'])){ $output[$idx][$key] = viewProcess($value,    $data['metagrid']['columns'][$key]['process']); }
            if (!empty($data['metagrid']['columns'][$key]['format'])) { $output[$idx][$key] = viewFormat ($row[$key],$data['metagrid']['columns'][$key]['format']); }
        }
    }
    $output1 = sortOrder($output, $args['sort'], strtolower($args['order'])=='desc'?'desc':'asc'); // sort
    $results = array_slice($output1, ($args['page']-1)*$args['rows'], $args['rows']); // get slice
    return ['total'=>sizeof($output), 'rows'=>$results];
}

function dbMetaReadSearch($metaData, $grid, $search='')
{
    $output = [];
    msgDebug("\nEntering dbMetaReadSearch with search = $search and row count from db: ".sizeof($metaData));
    msgDebug("\n  and filters = ".print_r($grid['source']['filters'], true));
    msgDebug("\n  and search fields = ".print_r($grid['source']['search'], true));
    if (sizeof($metaData)) { msgDebug("\nFirst 10 rows: ".print_r(array_slice($metaData, 0, 10), true)); }
    foreach ($metaData as $row) {
        if (empty($grid['source']['filters'])) { $output[] = $row; continue; } // no filters
        $hit = [];
        foreach ($grid['source']['filters'] as $idx => $values) { // apply filters
            switch ($idx) {
                case 'search':
                    $hit[$idx] = empty($search) ? true : false;
                    foreach ($grid['source']['search'] as $field) {
                        if (isset($row[$field]) && strpos(strtolower($row[$field]), strtolower($search))!==false) { $hit[$idx] = true; }
                    }
                    break;
                default:
                    $hit[$idx] = $row[$idx]==$values['attr']['value']?true:false;
                    break;
            }
        }
        if (!in_array(false, $hit, true)) { $output[] = $row; }
    }
    msgDebug("\nPost dbMetaReadSearch, pruned output to ".sizeof($output)." rows.");
    return $output;
}

/**
 * Pulls data from a db table and builds a data array for a drop down HTML5 input field
 * @param string $table - db table name
 * @param string $id - table field name to be used as the id of the select drop down
 * @param string $field - table field name to be used as the description of the select drop down
 * @param string $filter - SQL filter to limit results
 * @param string $nullText - description to use for no selection (null id assumed)
 * @return array $output - formatted result array to be used for HTML5 input type select render function
 */
function dbBuildDropdown($table, $id='id', $field='description', $filter='', $nullText='')
{
    $output = [];
    if ($nullText) { $output[] = ['id'=>'0', 'text'=>$nullText]; }
    $sql = "SELECT $id AS id, $field AS text FROM $table"; // no ` as sql function may be used
    if ($filter) { $sql .= " WHERE $filter"; }
    if (!$stmt = dbGetResult($sql)) { return $output; }
    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($result as $row) { $output[] = ['id'=>$row['id'], 'text'=>$row['text']]; }
    return $output;
}

/**
 * Tries to find a contact record to match a set of submitted fields
 * @TODO - If the same email is used for BOTH customer and vendor then the wrong ID may be returned
 * @param type $value - the value to use to find the contact
 * @param type $type - Field to use for search, options are 'id' [default] or 'email'
 * @return array [contact_id, address_id] if found, false if not found
 */
function dbGetContact($value=0)
{
    msgDebug("\nEntering dbGetContact with value = $value");
    $contact = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=".intval($value));
    if (empty($contact)) { return addressLoad(); } // cID = 0, get HQ values
    return $contact;
}

/**
 * Calculates the current balance of a GL account, will add back current row on edits.
 * @param string $glAcct
 * @param db_date $postDate
 * @param integer $omitID
 * @return float
 */
function dbGetGLBalance($glAcct, $postDate='', $omitID=0) {
    if (empty($postDate)) { $postDate = biz_date('Y-m-d'); }
    $row     = dbGetRow(BIZUNO_DB_PREFIX.'journal_periods', "start_date<='$postDate' AND end_date>='$postDate'");
    if (!$row) { return msgAdd(sprintf(lang('err_gl_post_date_invalid'), $postDate)); }
    $balance = dbGetValue(BIZUNO_DB_PREFIX.'journal_history', 'beginning_balance', "period={$row['period']} AND gl_account='$glAcct'");
    $balance+= dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'SUM(debit_amount - credit_amount) as balance', "gl_account='$glAcct' AND post_date>='{$row['start_date']}' AND post_date<='$postDate'", false);
    // add back the record amount if editing
    if (!empty($omitID)) { $balance += dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'credit_amount', "ref_id=$omitID AND gl_type='ttl'"); }
    msgDebug("returning from dbGetGLBalance with balance = $balance");
    return $balance;
}

/**
 * Retrieves the cost of an inventory assembly using the item_cost field from the inventory table
 * @param integer $rID - inventory table record ID
 * @return float - calculated cost of the inventory item
 */
function dbGetInvAssyCost($rID=0)
{
    $cost = 0;
    if (empty($rID)) { return $cost; }
    $iID  = intval($rID);
    $items= getMetaInventory($iID, 'bill_of_materials');
    if (empty($items)) { $items[] = ['qty'=>1, 'sku'=>dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$iID")]; } // for non-assemblies
    foreach ($items as $row) {
        if (empty($GLOBALS['inventory'][$row['sku']]['unit_cost'])) {
            $GLOBALS['inventory'][$row['sku']]['unit_cost'] = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'item_cost', "sku='{$row['sku']}'");
        }
        $cost+= $row['qty'] * $GLOBALS['inventory'][$row['sku']]['unit_cost'];
    }
    return $cost;
}

/**
 * Takes the journal main ID and calculates the cogs for all items on the order using the inventory item_cost values
 */
function dbGetOrderCOGS($mainID=0)
{
    $cogs = 0;
    $items= dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=".(int)$mainID." AND gl_type='itm'", '', ['sku']);
    foreach ($items as $item) {
        if (empty($item['sku'])) { continue; }
        $cogs += dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'item_cost', "sku='".addslashes($item['sku'])."'");
    }
    return $cogs;
}

/**
 * Takes the journal main ID and calculates the cogs for all items on the order using the inventory item_cost values
 */
function dbGetCOGSj12($mainID=0)
{
    $row = dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id=".(int)$mainID." AND gl_type='cog'");
    $cogs = round($row['debit_amount'] + $row['credit_amount'], 2);
    msgDebug("\nEntering dbGetCOGSj12 with id=$mainID and calculated cogs = $cogs");
    return $cogs;
}

/**
 * Pulls the reports from the common_meta and build a list for to select a report
 */
function dbReportsCache()
{
    $temp   = dbMetaGet(0, 'phreeform_cache'); // see if it is set to get index
    $rID    = metaIdxClean($temp);
    $metaVal= dbMetaGet('%', 'phreeform');
    msgDebug("\nResult from dbMetaGet row count = ".sizeof($metaVal));
    $output = [];
    foreach ($metaVal as $row) {
        $output['r'.$row['_rID']] = ['_rID'   =>$row['_rID'],     'id'       =>$row['_rID'], 'parent_id'=>$row['parent_id'],
            'title'=>$row['title'],'mime_type'=>$row['mime_type'],'group_id' =>isset($row['groupname']) ? $row['groupname'] : $row['group_id'], // for legacy
            'users'=>$row['users'],'roles'    =>$row['roles']];
    }
    $sort0  = sortOrder($output,'title');
    msgDebug("\nWriting ".sizeof((array)$sort0)." sorted report/form from list."); // = ".print_r($sort0, true));
    dbMetaSet($rID, 'phreeform_cache', $sort0);
    return $sort0;
}

/**
 * 
 * @param type $roleID
 * @return type
 */
function dbGetRoleMenu()
{
    msgDebug("\nEntering dbGetRoleMenu");
    $role = getUserCache('role');
    return ['menuBar'=>['child'=>!empty($role['menuBar']) ? $role['menuBar'] : []]];
}

/**
 *
 * @param type $search
 * @param type $fields
 * @return type
 */
function dbGetSearch($search, $fields)
{
    $search_text = addslashes($search);
    return "(".implode(" LIKE '%$search_text%' OR ", $fields)." LIKE '%$search_text%')";
}

/**
 * Creates a list of available stores, including main store for use in views
 * @param boolean $addAll - [default false] Adds option All at top of list
 * @return array - ready to render as pull down
 */
function dbGetStores($addAll=false)
{
    if ($addAll) { $output[] = ['id'=>-1, 'text'=>lang('all')]; }
    $output[] = ['id'=>0, 'text'=> getModuleCache('bizuno', 'settings', 'company', 'id')];
    $crit  = dbTableExists(BIZUNO_DB_PREFIX.'address_book') ? "type='b'" : "ctype_b='1'"; // For legacy, pre 7.0 installs
    $result = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', $crit, 'short_name');
    foreach ($result as $row) { $output[] = ['id'=>$row['id'], 'text'=>$row['short_name']]; }
    return $output;
}

/**
 * Finds the quantity of a SKU in stock at a given store or all stores
 * @param string $sku - SKU to find store stock
 * @param integer $storeID - Store ID, default -1 - All
 * @return type
 */
function dbGetStoreQtyStock($sku, $storeID=-1)
{
    $crit = "sku='$sku'";
    if ($storeID>-1) { $crit .= " AND store_id=$storeID"; }
    $store_bal = dbGetValue(BIZUNO_DB_PREFIX.'inventory_history', "SUM(remaining)", $crit, false);
    $qty_owed  = dbGetValue(BIZUNO_DB_PREFIX.'journal_cogs_owed', "SUM(qty)",       $crit, false);
    return ($store_bal - $qty_owed);
}

/**
 * Retrieves fiscal year period details
 * @param integer $period - period to get data on
 * @return array - details of requested fiscal year period information
 */
function dbGetPeriodInfo($period)
{
    $values     = dbGetRow  (BIZUNO_DB_PREFIX.'journal_periods', "period='$period'");
    $period_min = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', "MIN(period)", "fiscal_year={$values['fiscal_year']}", false);
    $period_max = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', "MAX(period)", "fiscal_year={$values['fiscal_year']}", false);
    $fy_max     = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', ['MAX(fiscal_year) AS fiscal_year', 'MAX(period) AS period'], "", false);
    $output = [
        'period'       => $period,
        'period_start' => $values['start_date'],
        'period_end'   => $values['end_date'],
        'fiscal_year'  => $values['fiscal_year'],
        'period_min'   => $period_min,
        'period_max'   => $period_max,
        'fy_max'       => $fy_max['fiscal_year'],
        'fy_period_max'=> $fy_max['period']];
    msgDebug("\nCalculating period information, returning with values: ".print_r($output, true));
    return $output;
}

/**
 * Calculates fiscal dates, pulled from journal_period table
 * @param integer $period - The period to gather the db inform from
 * @return array - database table row results for the specified period
 */
function dbGetFiscalDates($period)
{
    $result = dbGetRow(BIZUNO_DB_PREFIX."journal_periods", "period=$period");
    msgDebug("\nCalculating fiscal dates with period = $period. Resulted in: ".print_r($result, true));
    if (!$result) { // post_date is out of range of defined accounting periods
        return msgAdd(sprintf(lang('err_gl_post_date_invalid'), "period $period"));
    }
    return $result;
}

/**
 * Generates a drop down list of the fiscal years in the system
 * @return array - formatted result array to be used for HTML5 input type select render function
 */
function dbFiscalDropDown()
{
    $stmt   = dbGetResult("SELECT DISTINCT fiscal_year FROM ".BIZUNO_DB_PREFIX."journal_periods GROUP BY fiscal_year ORDER BY fiscal_year ASC");
    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $output = [];
    foreach ($result as $row) { $output[] = ['id'=>$row['fiscal_year'], 'text'=>$row['fiscal_year']]; }
    return $output;
}

/**
 * Generates a drop down list of GL Accounts
 * @param string $inc_sel - include Please Select at beginning of drop down
 * @param array $limits - GL account types to restrict list to
 * @return array - formatted result array to be used for HTML5 input type select render function
 */
function dbGLDropDown($inc_sel=true, $limits=[], $hideInactive=true)
{
    $output = [];
    if ($inc_sel) { $output[] = ['id'=>'0', 'text'=>lang('select')]; }
    $chart = getModuleCache('phreebooks', 'chart');
    foreach ($chart as $row) {
        if ($hideInactive && !empty($row['inactive'])) { continue; }
        if (sizeof($limits)==0 || in_array($row['type'], $limits)) {
            $output[] = ['id'=>$row['id'], 'text'=>$row['id'].' : '.$row['title']];
        }
    }
    return $output;
}

/**
 * Sets the Bizuno cache with a complete list of users to quickly generate pull down menus
 */
function dbSetBizunoUsers()
{
    $crit  = dbTableExists(BIZUNO_DB_PREFIX.'address_book') ? "type='u'" : "ctype_u='1'"; // For legacy, pre 7.0 installs
    $users = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', $crit);
    msgDebug("\nEntering dbSetBizunoUsers, read ".sizeof((array)$users)."users from all roles."); // = ".print_r($users ,true));
    $output= [];
    foreach ($users as $user) {
        $metaPro= dbMetaGet(0, 'user_profile', 'contacts', $user['id']); // fetch the users role from the contacts_meta
        $roleID = $metaPro['role_id'];
        msgDebug("\n  Read user role ID = $roleID and short name from db to be = ".print_r($user['short_name'], true));
        $role   = dbMetaGet($roleID, 'bizuno_role');
        $output[] = ['id'=>$user['id'],
            'text'     => !empty($user['contact_last']) ? $user['contact_last'].', '.$user['contact_first'] : $user['primary_name'],
            'email'    => $user['email'],
            'inactive' => $user['inactive'],
            'role'     => $roleID,
            'groups'   => !empty($role['groups']) ? $role['groups'] : []]; //isset($userInfo['r'.$user['role']]) ? $userInfo['r'.$user['role']]  : 0];
    }
    $cache = sortOrder($output, 'text');
    setModuleCache('bizuno', 'users', '', $cache);
}

/**
 * Sets the Bizuno cache with a complete list of employees to quickly generate pull down menus
 */
function dbSetBizunoEmployees()
{
    msgDebug("\nEntering dbSetBizunoEmployees");
    $crit  = dbTableExists(BIZUNO_DB_PREFIX.'address_book') ? "type='e'" : "ctype_e='1'"; // For legacy, pre 7.0 installs
    $users = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', $crit);
    $output= [];
    foreach ($users as $user) {
        // try to see if the employee has a role assigned
        // @TODO - This will go much faster if the user and employee contact records are merged first
        $output[] = ['id'=>$user['id'],
            'text'    => !empty($user['contact_last']) ? $user['contact_last'].', '.$user['contact_first'] : $user['primary_name'],
            'email'   => $user['email'],
            'pin'     => isset($user['account_number']) ? $user['account_number'] : '', // PINs are set in the employee db record
            'inactive'=> $user['inactive']];
    }
    $cache = sortOrder($output, 'text');
    setModuleCache('bizuno', 'employees', '', $cache);
}

/**
 * Generates the date part of the SQL WHERE clause based on the encoded data.
 * Also generates the textual description for reports and forms
 * @param string $dateType - encoded date to format, typically: format:start_date:end_date
 * @param string $df - field to use in the table (less the DB_PREFIX)
 * @return array - [sql, description, start_date, end_date]
 */
function dbSqlDates($dateType='a', $df=false) {
    msgDebug("\nEntering dbSqlDates with dateType = $dateType and date field = $df");
    if (!$df) { $df = 'post_date'; }
    $dates = localeGetDates();
    $DateArray = explode(':', $dateType);
    $tnow = time();
    $dbeg = '1969-01-01';
    $dend = '2029-12-31';
    switch ($DateArray[0]) {
        case "a": // All, skip the date addition to the where statement, all dates in db
        case "all": // old way
            $sql  = '';
            $desc = '';
            break;
        case "b": // Date Range
            $sql  = '';
            $desc = lang('date_range');
            if ($DateArray[1] <> '') {
                $dbeg = clean($DateArray[1], 'date');
                $sql .= "$df>='$dbeg'";
                $desc.= ' '.lang('from').' '.$DateArray[1];
            }
            if ($DateArray[2] <> '') { // a value entered, check
                if (strlen($sql) > 0) { $sql .= ' AND '; }
                $dend = localeCalculateDate(clean($DateArray[2], 'date'), 1);
                $sql .= "$df<'$dend'";
                $desc.= ' '.lang('to').' '.$DateArray[2];
            }
            $desc .= '; ';
            break;
        case "c": // Today (specify range for datetime type fields to match for time parts)
            $dbeg = $dates['Today'];
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' = '.viewDate($dates['Today']).'; ';
            break;
        case "d": // This Week
            $dbeg = biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], biz_date('j', $tnow) - biz_date('w', $tnow), $dates['ThisYear']));
            $dend = localeCalculateDate(biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], biz_date('j', $tnow) - biz_date('w', $tnow)+6, $dates['ThisYear'])), 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate(localeCalculateDate($dend, -1)).'; ';
            break;
        case "e": // This Week to Date
            $dbeg = biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], biz_date('j', $tnow)-biz_date('w', $tnow), $dates['ThisYear']));
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dates['Today']).'; ';
            break;
        case "f": // This Month
            $dbeg = biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], 1, $dates['ThisYear']));
            $dend = localeCalculateDate(biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], $dates['TotalDays'], $dates['ThisYear'])), 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate(localeCalculateDate($dend, -1)).'; ';
            break;
        case "g": // This Month to Date
            $dbeg = biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], 1, $dates['ThisYear']));
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dates['Today']).'; ';
            break;
        case "h": // This Quarter
            $QtrStrt = getModuleCache('phreebooks', 'fy', 'period') - ((getModuleCache('phreebooks', 'fy', 'period') - 1) % 3);
            $temp = dbGetFiscalDates($QtrStrt);
            $dbeg = $temp['start_date'];
            $temp = dbGetFiscalDates($QtrStrt + 2);
            $dend = localeCalculateDate($temp['end_date'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($temp['end_date']).'; ';
            break;
        case "i": // Quarter to Date
            $QtrStrt = getModuleCache('phreebooks', 'fy', 'period') - ((getModuleCache('phreebooks', 'fy', 'period') - 1) % 3);
            $temp = dbGetFiscalDates($QtrStrt);
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dates['Today']).'; ';
            break;
        case "j": // This Year
            $YrStrt= getModuleCache('phreebooks', 'fy', 'period_min');
            $temp1 = dbGetFiscalDates($YrStrt);
            $dbeg  = $temp1['start_date'];
            $temp2 = dbGetFiscalDates($YrStrt + 11);
            $dend  = localeCalculateDate($temp2['end_date'], 1);
            $sql   = "$df>='$dbeg' AND $df<'$dend'";
            $desc  = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($temp2['end_date']).'; ';
            break;
        case "k": // Year to Date
            $YrStrt = getModuleCache('phreebooks', 'fy', 'period_min');
            $temp = dbGetFiscalDates($YrStrt);
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dates['Today']).'; ';
            break;
        case "l": // This Period
            $temp = dbGetFiscalDates(getModuleCache('phreebooks', 'fy', 'period'));
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($temp['end_date'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('period').' '.getModuleCache('phreebooks', 'fy', 'period').' ('.viewDate($dbeg).' '.lang('to').' '.viewDate($temp['end_date']).'); ';
            break;
        case 'm': // Last Fiscal Year
            $minPer = getModuleCache('phreebooks', 'fy', 'period_min');
            if ($minPer > 12) {
                $temp1 = dbGetFiscalDates($minPer-12);
                $dbeg  = $temp1['start_date'];
                $temp2 = dbGetFiscalDates($minPer-1);
                $dend  = localeCalculateDate($temp2['end_date'], 1);
                $desc  = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($temp2['end_date']).'; ';
            } else {
                $dbeg = '2000-01-01';
                $dend = '2000-12-31';
                $desc = lang('date_range').' No Data Available';
            }
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            break;
        case 'n': // Last Fiscal Year to Date (through same month-day of last fiscal year)
            $period = getModuleCache('phreebooks', 'fy', 'period_min');
            if ($period > 12) {
                $temp = dbGetFiscalDates($period-12);
                $dbeg = $temp['start_date'];
                $dend = localeCalculateDate($dates['Today'], 1, 0, -1);
                $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dend).'; ';
            } else {
                $dbeg = '2000-01-01';
                $dend = '2000-12-31';
                $desc = lang('date_range').' No Data Available';
            }
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            break;
        case 'o': // Reseverd for later
            break;
        case 'p': // Reseverd for later
            break;
        case 'q': // Reseverd for later
            break;
        case 'r': // Last Quarter
            $QtrStrt = (getModuleCache('phreebooks', 'fy', 'period') - ((getModuleCache('phreebooks', 'fy', 'period') - 1) % 3) - 3);
            $temp = dbGetFiscalDates($QtrStrt);
            $dbeg = $temp['start_date'];
            $temp = dbGetFiscalDates($QtrStrt + 2);
            $dend = localeCalculateDate($temp['end_date'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($temp['end_date']).'; ';
            break;
        case 's': // Last Period
            $lPer = getModuleCache('phreebooks', 'fy', 'period') - 1;
            $temp = dbGetFiscalDates($lPer);
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($temp['end_date'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('period')." $lPer (".viewDate($dbeg).' '.lang('to').' '.viewDate($temp['end_date']).'); ';
            break;
        case 't': // Last 30 days
            $dbeg = localeCalculateDate($dates['Today'], -30);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_30_days');
            break;
        case 'u': // Reseverd for later
            break;
        case 'v': // last 60 days
            $dbeg = localeCalculateDate($dates['Today'], -60);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_60_days');
            break;
        case 'w': // Last 90 days
            $dbeg = localeCalculateDate($dates['Today'], -90);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_90_days');
            break;
        case 'x': // Last 6 Months
            $dbeg = localeCalculateDate($dates['Today'], 0, -6);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_6_months');
            break;
        case 'y': // Last 12 Months
            $dbeg = localeCalculateDate($dates['Today'], 0, 0, -1);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_12_months');
            break;
        default: // date by period (integer)
        case "z":
            if (!intval($DateArray[0])) { $DateArray[0] = getModuleCache('phreebooks', 'fy', 'period'); }
            $temp = dbGetFiscalDates($DateArray[0]);
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($temp['end_date'], 1);
            // Assumes the table has a field named period
            $sql  = "period='{$DateArray[0]}'";
//          $sql  = "$df>='$dbeg' AND $df<'$dend'"; // was this before but breaks for trial balance report
            $desc = lang('period')." {$DateArray[0]} (".viewFormat($temp['start_date'], 'date')." - ".viewFormat($temp['end_date'], 'date')."); ";
            break;
    }
    return ['sql'=>$sql,'description'=>$desc,'start_date'=>$dbeg,'end_date'=>$dend];
}
/**
 * Generates the db sql criteria for present and historical accounting quarters
 * @param type $range - choice from select drop down to build sql
 * @param string $df - db Field to set criteria
 * @return array
 */
function dbSqlDatesQrtrs($range=0, $df='')
{
    $dates= dbSqlDates('h'); // this quarter
    switch ($range) {
        default: // current quarter
        case 0:  $ds = $dates['start_date'];                              $de = $dates['end_date'];                             break;
        case 1:  $ds = localeCalculateDate($dates['start_date'], 0,  -3); $de =localeCalculateDate($dates['end_date'], 0,  -3); break;
        case 2:  $ds = localeCalculateDate($dates['start_date'], 0,  -6); $de =localeCalculateDate($dates['end_date'], 0,  -6); break;
        case 3:  $ds = localeCalculateDate($dates['start_date'], 0,  -9); $de =localeCalculateDate($dates['end_date'], 0,  -9); break;
        case 4:  $ds = localeCalculateDate($dates['start_date'], 0, -12); $de =localeCalculateDate($dates['end_date'], 0, -12); break;
        case 5:  $ds = localeCalculateDate($dates['start_date'], 0, -15); $de =localeCalculateDate($dates['end_date'], 0, -15); break;
    }
    $sql  = "$df>='$ds' AND $df<'$de'";
    $desc = lang('date_range').' '.lang('from').' '.viewDate($ds).' '.lang('to').' '.viewDate(localeCalculateDate($de, -1)).'; ';
    return ['sql'=>$sql,'description'=>$desc,'start_date'=>$ds,'end_date'=>$de];
}
/**
 * Prepares a drop down values list of users
 * @param boolean $active_only - [default: true] Restrict list to active users only, default true
 * @param boolean $showNone - [default: true] Show None option (appears after ShowAll option and showSelect option
 * @param boolean $showAll - [default: true] Show All option (appears second, first if showSelect is false)
 * @return array - list of users in array ready for view in a HTML list element
 */
function listUsers($active_only=true, $showNone=true, $showAll=true)
{
    $output = [];
    if ($showAll)  { $output[] = ['id'=>'-1', 'text'=>lang('all')]; }
    if ($showNone) { $output[] = ['id'=> '0', 'text'=>lang('none')]; }
    if (!isset($GLOBALS['BIZUNO_USERS'])) {
        $GLOBALS['BIZUNO_USERS'] = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', "ctype_u='1'", 'contact_last', ['id', 'inactive', 'primary_name', 'contact_last', 'contact_first']);
    }
    foreach ($GLOBALS['BIZUNO_USERS'] as $user) {
        if ($active_only && $user['inactive']<>'0') { continue; }
        $output[] = ['id'=>$user['id'],'text'=>!empty($user['contact_last']) ? $user['contact_last'].', '.$user['contact_first'] : $user['primary_name']];
    }
    return $output;
}

/**
 * Prepares a drop down values list of roles
 * @param boolean $active_only - Restrict list to active roles only, default true
 * @param boolean $showNone - Show the None selection after the showAll and before the list
 * @param boolean $showAll - Show the All selection first
 * @return array - list of roles in array ready for view in a HTML list element
 */
function listRoles($active_only=true, $showNone=true, $showAll=true)
{
    $output = [];
    if ($showAll)  { $output[] = ['id'=>-1, 'text'=>lang('all')]; }
    if ($showNone) { $output[] = ['id'=> 0, 'text'=>lang('none')]; }
    if (!isset($GLOBALS['BIZUNO_ROLES'])) { $GLOBALS['BIZUNO_ROLES'] = dbMetaGet('%', 'bizuno_role'); }
    foreach ($GLOBALS['BIZUNO_ROLES'] as $row) {
        if ($active_only && !empty($row['inactive'])) { continue; }
        $output[] = ['id'=>$row['_rID'], 'text'=>$row['title']];
    }
    return $output;
}

/**
 * Converts the variable type to a string replacement, mostly used to build JavaScript variables
 * @param mixed $value - The value to encode
 * @return mixed - Value encoded
 */
function encodeType($value)
{
    switch (gettype($value)) {
        case "boolean": return $value ? 'true' : 'false';
        default:
        case "resource":
        case "integer":
        case "double":  return $value; // no quotes required
        case "NULL":
        case "string":  return "'".str_replace("'", "\'", (string)$value)."'"; // add quotes
        case "array":
        case "object":  return json_encode($value);
    }
}

/**
 * Validates the existence of a tab for which a custom field is placed, otherwise creates it
 * @param string $mID - [Required] Module ID, i.e. inventory, extFixedAssets, etc.
 * @param string $tID - [Required] Table Name, i.e. inventory, contacts, etc.
 * @param string $title - [Required] Tab title to search for, must match exactly, best to use lang() to match.
 * @param integer $order - [Default 50] Sort order of the tab within the list
 * @return integer - ID of the tab, either existing or newly created
 */
function validateTab($tName, $title, $order=50)
{
    $metaTabs = dbMetaGet('%', 'tabs');
    foreach ($metaTabs as $tab) { if ($tab['table']==$tName && $tab['title']==$title) { return $tab['_rID']; } }
    return dbMetaSet(0, 'tabs', ['table'=>$tName, 'title'=>$title, 'order'=>$order]);
}
