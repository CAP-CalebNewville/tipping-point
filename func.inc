<?php
/* DATABASE-DEPENDENT FUNCTIONS */
/* Functions that require database connection - used after setup */

require_once 'common.inc';
require_once 'database.inc';

// Initialize database connection
try {
    $db = getDB();
    $con = $db->getConnection(); // For backward compatibility with existing code
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Load configuration
$config = array();
try {
    $config_result = $db->query("SELECT * FROM configuration");
    while($config_row = $db->fetchAssoc($config_result)) {
        $config[$config_row['item']] = $config_row['value'];
    }
} catch (Exception $e) {
    // If configuration table doesn't exist, we might be in a migration state
    $config = array('timezone' => 'America/New_York'); // Default fallback
}

// Set timezone based on config
if (isset($config['timezone'])) {
    $timezoneoffset = get_timezone_offset($config['timezone']);
    date_default_timezone_set($config['timezone']);
} else {
    $timezoneoffset = 0;
    date_default_timezone_set('America/New_York');
}

function AircraftListActive() {
    global $db;
    
    echo "<select name=\"tailnumber\" class=\"form-select\">\n";
    
    $result = $db->query("SELECT * FROM aircraft WHERE active = 1 ORDER BY tailnumber ASC");
    
    while($row = $db->fetchAssoc($result)) {
        echo "<option value=\"" . $row['id'] . "\">" . htmlspecialchars($row['tailnumber']) . " - " . htmlspecialchars($row['makemodel']) . "</option>\n";
    }
    
    echo "</select>\n";
}

function AircraftListAll() {
    global $db;
    
    echo "<select name=\"tailnumber\" class=\"form-select\">\n";
    echo "<optgroup label=\"Active\">\n";
    
    // Active aircraft
    $result = $db->query("SELECT * FROM aircraft WHERE active = 1 ORDER BY tailnumber ASC");
    
    while($row = $db->fetchAssoc($result)) {
        echo "<option value=\"" . $row['id'] . "\">" . htmlspecialchars($row['tailnumber']) . " - " . htmlspecialchars($row['makemodel']) . "</option>\n";
    }
    
    echo "</optgroup>\n";
    echo "<optgroup label=\"Inactive\">\n";
    
    // Inactive aircraft
    $result = $db->query("SELECT * FROM aircraft WHERE active = 0 ORDER BY tailnumber ASC");
    
    while($row = $db->fetchAssoc($result)) {
        echo "<option value=\"" . $row['id'] . "\">" . htmlspecialchars($row['tailnumber']) . " - " . htmlspecialchars($row['makemodel']) . "</option>\n";
    }
    
    echo "</optgroup>\n";
    echo "</select>\n";
}

?>