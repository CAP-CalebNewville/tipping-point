<?php
/* DATABASE ABSTRACTION LAYER */
/* Supports both MySQL and SQLite for backward compatibility during migration */

if (!class_exists('DatabaseConnection')) {
class DatabaseConnection {
    private $connection;
    private $type; // 'mysql' or 'sqlite'
    
    public function __construct() {
        // Check if we should use SQLite (new installations or migrated ones)
        if (file_exists(dirname(__FILE__) . '/data/tippingpoint.db')) {
            $this->initSQLite();
        } elseif (file_exists('config.inc')) {
            $this->initMySQL();
        } else {
            throw new Exception('No database configuration found');
        }
    }
    
    private function initSQLite() {
        $dbPath = dirname(__FILE__) . '/data/tippingpoint.db';
        try {
            $this->connection = new PDO('sqlite:' . $dbPath);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->type = 'sqlite';
            
            // Check if tables exist, if not create them
            $result = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='audit'");
            if ($result->fetch() === false) {
                $this->createTables();
            }
        } catch (PDOException $e) {
            throw new Exception('SQLite connection failed: ' . $e->getMessage());
        }
    }
    
    private function initMySQL() {
        require_once 'config.inc';
        try {
            $this->connection = mysqli_connect($dbserver, $dbuser, $dbpass, $dbname);
            if (!$this->connection) {
                throw new Exception('MySQL connection failed: ' . mysqli_connect_error());
            }
            $this->type = 'mysql';
        } catch (Exception $e) {
            throw new Exception('MySQL connection failed: ' . $e->getMessage());
        }
    }
    
    public function getType() {
        return $this->type;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        if ($this->type === 'sqlite') {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } else {
            // For MySQL, we'll need to handle parameters differently
            if (empty($params)) {
                $stmt = mysqli_prepare($this->connection, $sql);
                mysqli_stmt_execute($stmt);
                return mysqli_stmt_get_result($stmt);
            } else {
                $stmt = mysqli_prepare($this->connection, $sql);
                if (!empty($params)) {
                    // Auto-detect parameter types
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } else {
                            $types .= 's';
                        }
                    }
                    mysqli_stmt_bind_param($stmt, $types, ...$params);
                }
                mysqli_stmt_execute($stmt);
                return mysqli_stmt_get_result($stmt);
            }
        }
    }
    
    public function prepare($sql) {
        if ($this->type === 'sqlite') {
            return $this->connection->prepare($sql);
        } else {
            return mysqli_prepare($this->connection, $sql);
        }
    }
    
    public function execute($stmt, $params = []) {
        if ($this->type === 'sqlite') {
            return $stmt->execute($params);
        } else {
            if (!empty($params)) {
                // Auto-detect parameter types for MySQL
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                }
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            return mysqli_stmt_execute($stmt);
        }
    }
    
    public function getResult($stmt) {
        if ($this->type === 'sqlite') {
            return $stmt; // PDO statements are already result objects
        } else {
            return mysqli_stmt_get_result($stmt);
        }
    }
    
    public function fetchAssoc($result) {
        if ($this->type === 'sqlite') {
            return $result->fetch(PDO::FETCH_ASSOC);
        } else {
            return mysqli_fetch_assoc($result);
        }
    }
    
    public function fetchArray($result) {
        if ($this->type === 'sqlite') {
            return $result->fetch(PDO::FETCH_BOTH);
        } else {
            return mysqli_fetch_array($result);
        }
    }
    
    public function fetchAll($result) {
        if ($this->type === 'sqlite') {
            return $result->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            return $rows;
        }
    }
    
    public function numRows($result) {
        if ($this->type === 'sqlite') {
            // SQLite doesn't have a direct row count for SELECT statements
            // We'll need to use a different approach
            return $result->rowCount();
        } else {
            return mysqli_num_rows($result);
        }
    }
    
    public function lastInsertId() {
        if ($this->type === 'sqlite') {
            return $this->connection->lastInsertId();
        } else {
            return mysqli_insert_id($this->connection);
        }
    }
    
    public function escape($string) {
        if ($this->type === 'sqlite') {
            return $this->connection->quote($string);
        } else {
            return mysqli_real_escape_string($this->connection, $string);
        }
    }
    
    public function getTableColumns($table_name) {
        if ($this->type === 'sqlite') {
            return $this->query("PRAGMA table_info(" . $table_name . ")");
        } else {
            return $this->query("SHOW COLUMNS FROM " . $table_name);
        }
    }
    
    public function hasColumn($table_name, $column_name) {
        $columns = $this->getTableColumns($table_name);
        while ($column = $this->fetchAssoc($columns)) {
            if ($this->type === 'sqlite') {
                if ($column['name'] == $column_name) {
                    return true;
                }
            } else {
                if ($column['Field'] == $column_name) {
                    return true;
                }
            }
        }
        return false;
    }
    
    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS aircraft (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            active INTEGER NOT NULL DEFAULT 1,
            tailnumber TEXT NOT NULL,
            makemodel TEXT NOT NULL,
            emptywt REAL NOT NULL,
            emptycg REAL NOT NULL,
            maxwt REAL NOT NULL,
            cglimits TEXT NULL,
            cgwarnfwd REAL NULL,
            cgwarnaft REAL NULL,
            fuelunit TEXT NOT NULL,
            weight_units TEXT NOT NULL DEFAULT 'Pounds',
            arm_units TEXT NOT NULL DEFAULT 'Inches',
            fuel_type TEXT NOT NULL DEFAULT '100LL/Mogas',
            max_landing_weight REAL NULL
        );
        
        CREATE TABLE IF NOT EXISTS aircraft_cg (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tailnumber INTEGER NOT NULL,
            arm REAL NOT NULL,
            weight REAL NOT NULL,
            envelope_name TEXT NULL DEFAULT 'Normal',
            color TEXT NULL DEFAULT 'blue'
        );
        
        CREATE TABLE IF NOT EXISTS aircraft_weights (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tailnumber INTEGER NOT NULL,
            'order' INTEGER NOT NULL,
            item TEXT NOT NULL,
            weight REAL NOT NULL,
            arm REAL NOT NULL,
            emptyweight TEXT NULL,
            fuel TEXT NULL,
            fuelwt REAL NULL,
            type TEXT NOT NULL DEFAULT 'Variable Weight no limit',
            weight_limit REAL NULL
        );
        
        CREATE TABLE IF NOT EXISTS audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            who TEXT NOT NULL,
            what TEXT NOT NULL
        );
        
        CREATE TABLE IF NOT EXISTS configuration (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item TEXT NOT NULL,
            value TEXT NOT NULL
        );
        
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            password TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            superuser INTEGER NOT NULL
        );
        ";
        
        $this->connection->exec($sql);
    }
    
    public function close() {
        if ($this->type === 'sqlite') {
            $this->connection = null;
        } else {
            mysqli_close($this->connection);
        }
    }
}
}

// Global database instance
$db = null;

if (!function_exists('getDB')) {
    function getDB() {
        global $db;
        if ($db === null) {
            $db = new DatabaseConnection();
        }
        return $db;
    }
}

if (!function_exists('createSQLiteDatabase')) {
function createSQLiteDatabase() {
    $dataDir = dirname(__FILE__) . '/data';
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            throw new Exception('Failed to create data directory');
        }
    }
    
    $dbPath = $dataDir . '/tippingpoint.db';
    
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables with SQLite-compatible syntax
        $sql = "
        CREATE TABLE IF NOT EXISTS aircraft (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            active INTEGER NOT NULL DEFAULT 1,
            tailnumber TEXT NOT NULL,
            makemodel TEXT NOT NULL,
            emptywt REAL NOT NULL,
            emptycg REAL NOT NULL,
            maxwt REAL NOT NULL,
            cglimits TEXT NULL,
            cgwarnfwd REAL NULL,
            cgwarnaft REAL NULL,
            fuelunit TEXT NOT NULL,
            weight_units TEXT NOT NULL DEFAULT 'Pounds',
            arm_units TEXT NOT NULL DEFAULT 'Inches',
            fuel_type TEXT NOT NULL DEFAULT '100LL/Mogas',
            max_landing_weight REAL NULL
        );
        
        CREATE TABLE IF NOT EXISTS aircraft_cg (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tailnumber INTEGER NOT NULL,
            arm REAL NOT NULL,
            weight REAL NOT NULL,
            envelope_name TEXT NULL DEFAULT 'Normal',
            color TEXT NULL DEFAULT 'blue'
        );
        
        CREATE TABLE IF NOT EXISTS aircraft_weights (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tailnumber INTEGER NOT NULL,
            'order' INTEGER NOT NULL,
            item TEXT NOT NULL,
            weight REAL NOT NULL,
            arm REAL NOT NULL,
            emptyweight TEXT NULL,
            fuel TEXT NULL,
            fuelwt REAL NULL,
            type TEXT NOT NULL DEFAULT 'Variable Weight no limit',
            weight_limit REAL NULL
        );
        
        CREATE TABLE IF NOT EXISTS audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            who TEXT NOT NULL,
            what TEXT NOT NULL
        );
        
        CREATE TABLE IF NOT EXISTS configuration (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item TEXT NOT NULL,
            value TEXT NOT NULL
        );
        
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            password TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            superuser INTEGER NOT NULL
        );
        ";
        
        $pdo->exec($sql);
        return $pdo;
        
    } catch (PDOException $e) {
        throw new Exception('Failed to create SQLite database: ' . $e->getMessage());
    }
}
}

?>