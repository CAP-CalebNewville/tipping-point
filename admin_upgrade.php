<?php
// Upgrade script for TippingPoint
// Check if system is set up before trying to load database-dependent functions
include_once 'common.inc';
if (!isSystemInstalled()) {
    // System not set up, redirect to setup
    PageHeader('Setup Required');
    ?>
    
    <body>
    <div class="container">
    <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
    <div class="card mt-4">
    <div class="card-header bg-warning text-dark text-center">
    <h3 class="mb-0">Setup Required</h3>
    </div>
    <div class="card-body text-center">
    <div class="alert alert-warning">
    <h5 class="alert-heading">TippingPoint Not Configured</h5>
    <p>TippingPoint has not been set up yet. Please run the initial setup to configure your database and create an administrator account.</p>
    <hr>
    <a href="setup.php" class="btn btn-primary">Start Setup</a>
    </div>
    </div>
    </div>
    </div>
    </div>
    </div>
    
    <?php
    PageFooter('TippingPoint Setup', 'setup@tippingpoint', $ver);
    exit;
}

// System is set up, proceed normally
include_once 'func.inc';

// Start session and check authentication
session_start();

// Check if user is authenticated and is administrator
$loginuser = isset($_SESSION["loginuser"]) ? $_SESSION["loginuser"] : "";
$loginpass = isset($_SESSION["loginpass"]) ? $_SESSION["loginpass"] : "";
$loginlevel = "0";

// Check if user has session data
if (!empty($loginuser) && !empty($loginpass)) {
    $login_query = $db->query("SELECT * FROM users WHERE username = ?", [$loginuser]);
    $pass_verify = $db->fetchAssoc($login_query);
    if ($pass_verify && password_verify($loginpass, $pass_verify['password'])) {
        $loginlevel = $pass_verify['superuser'];
    }
}

// Check if user is administrator (loginlevel = 1)
if ($loginlevel != "1") {
    // Don't redirect to avoid infinite loop - show message instead
    PageHeader("System Upgrade Required");
    ?>
    
    <body>
    <div class="container">
    <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
    <div class="card mt-4">
    <div class="card-header bg-warning text-dark text-center">
    <h3 class="mb-0">System Upgrade Required</h3>
    </div>
    <div class="card-body text-center">
    <div class="alert alert-warning">
    <h5 class="alert-heading">Administrator Access Required</h5>
    <p>TippingPoint requires a system upgrade, but only administrators can perform this operation.</p>
    <p>Please contact your system administrator to complete the upgrade.</p>
    <hr>
    <a href="admin.php" class="btn btn-primary">Login as Administrator</a>
    </div>
    </div>
    </div>
    </div>
    </div>
    </div>
    
    <?php
    PageFooter('TippingPoint Upgrade', 'admin@tippingpoint', $ver);
    exit;
}

PageHeader("System Upgrade");
?>

<body>
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-8 col-md-10">
<div class="card mt-4">
<div class="card-header bg-warning text-dark text-center">
<h3 class="mb-0">TippingPoint - System Upgrade</h3>
</div>
<div class="card-body">

<?php
// Check if upgrade is needed
function needsUpgrade() {
    try {
        include_once 'database.inc';
        $db = getDB();
        
        // Check if type and weight_limit columns exist in aircraft_weights table
        $has_type_column = $db->hasColumn('aircraft_weights', 'type');
        $has_weight_limit_column = $db->hasColumn('aircraft_weights', 'weight_limit');
        
        // Check if envelope_name and color columns exist in aircraft_cg table
        $has_envelope_name_column = $db->hasColumn('aircraft_cg', 'envelope_name');
        $has_color_column = $db->hasColumn('aircraft_cg', 'color');
        
        // Check if weight_units, arm_units, and fuel_type columns exist in aircraft table
        $has_weight_units_column = $db->hasColumn('aircraft', 'weight_units');
        $has_arm_units_column = $db->hasColumn('aircraft', 'arm_units');
        $has_fuel_type_column = $db->hasColumn('aircraft', 'fuel_type');
        
        // Check pilot_signature configuration
        $result = $db->query("SELECT COUNT(*) as count FROM configuration WHERE item = 'pilot_signature'");
        $row = $db->fetchAssoc($result);
        $has_pilot_signature = ($row['count'] > 0);
        
        // Check if fuelwt column still exists (should be removed)
        $has_fuelwt_column = $db->hasColumn('aircraft_weights', 'fuelwt');
        
        // Check if deprecated CG warning columns still exist (should be removed)
        $has_cgwarn_columns = $db->hasColumn('aircraft', 'cgwarnfwd') || $db->hasColumn('aircraft', 'cgwarnaft');
        $has_cglimits_column = $db->hasColumn('aircraft', 'cglimits');
        $has_max_landing_weight_column = $db->hasColumn('aircraft', 'max_landing_weight');
        
        // Upgrade needed if any feature is missing or deprecated columns exist
        if (!$has_type_column || !$has_weight_limit_column || !$has_pilot_signature || !$has_weight_units_column || !$has_arm_units_column || !$has_fuel_type_column || !$has_envelope_name_column || !$has_color_column || $has_cgwarn_columns || $has_cglimits_column || !$has_max_landing_weight_column) {
            return true;
        }
        
        // Also check version for standard upgrade path
        $result = $db->query("SELECT value FROM configuration WHERE item = 'update_version'");
        $row = $db->fetchAssoc($result);
        
        if (!$row) {
            return true; // No version info, definitely needs upgrade
        }
        
        $current_version = $row['value'];
        return version_compare($current_version, '2.0.0', '<');
    } catch (Exception $e) {
        return true; // Error accessing database, assume upgrade needed
    }
}

// Perform the upgrade
function performUpgrade() {
    global $ver;
    
    try {
        include_once 'database.inc';
        $db = getDB();
        
        echo "<div class='alert alert-info'>Starting upgrade process...</div>";
        
        // Add pilot_signature setting if it doesn't exist
        $result = $db->query("SELECT COUNT(*) as count FROM configuration WHERE item = 'pilot_signature'");
        $row = $db->fetchAssoc($result);
        
        if ($row['count'] == 0) {
            $db->query("INSERT INTO configuration (item, value) VALUES (?, ?)", ['pilot_signature', '0']);
            echo "<div class='alert alert-success'>✓ Added pilot signature setting</div>";
        } else {
            echo "<div class='alert alert-info'>• Pilot signature setting already exists</div>";
        }
        
        // Add type column to aircraft_weights table and migrate data
        $has_type_column = $db->hasColumn('aircraft_weights', 'type');
        
        if (!$has_type_column) {
            // Add the type column
            $db->query("ALTER TABLE aircraft_weights ADD COLUMN type TEXT NOT NULL DEFAULT 'Variable Weight no limit'");
            echo "<div class='alert alert-success'>✓ Added type column to aircraft_weights table</div>";
            
            // Migrate existing data
            $db->query("UPDATE aircraft_weights SET type = 'Empty Weight' WHERE emptyweight = 'true'");
            $db->query("UPDATE aircraft_weights SET type = 'Fuel' WHERE fuel = 'true'");
            echo "<div class='alert alert-success'>✓ Migrated existing loading zone data to new type system</div>";
            
            // Remove old columns after successful migration
            if ($db->getType() === 'sqlite') {
                // SQLite doesn't support DROP COLUMN directly, so we need to recreate the table
                $db->query("BEGIN TRANSACTION");
                
                // Create new table structure without old columns but with weight_limit
                $db->query("CREATE TABLE aircraft_weights_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tailnumber INTEGER NOT NULL,
                    'order' INTEGER NOT NULL,
                    item TEXT NOT NULL,
                    weight REAL NOT NULL,
                    arm REAL NOT NULL,
                    fuelwt REAL NOT NULL,
                    type TEXT NOT NULL DEFAULT 'Variable Weight no limit',
                    weight_limit REAL NULL
                )");
                
                // Copy data to new table
                $db->query("INSERT INTO aircraft_weights_new (id, tailnumber, 'order', item, weight, arm, fuelwt, type, weight_limit)
                           SELECT id, tailnumber, 'order', item, weight, arm, fuelwt, type, NULL FROM aircraft_weights");
                
                // Replace old table with new one
                $db->query("DROP TABLE aircraft_weights");
                $db->query("ALTER TABLE aircraft_weights_new RENAME TO aircraft_weights");
                
                $db->query("COMMIT");
            } else {
                // MySQL supports DROP COLUMN directly
                $db->query("ALTER TABLE aircraft_weights DROP COLUMN emptyweight");
                $db->query("ALTER TABLE aircraft_weights DROP COLUMN fuel");
            }
            echo "<div class='alert alert-success'>✓ Removed old emptyweight and fuel columns</div>";
        } else {
            echo "<div class='alert alert-info'>• Loading zone type column already exists</div>";
        }
        
        // Check for weight_limit column separately
        $has_weight_limit_column = $db->hasColumn('aircraft_weights', 'weight_limit');
        
        // Separate check for weight_limit column (needed for existing installations that have type but not weight_limit)
        if (!$has_weight_limit_column) {
            // Add the weight_limit column to existing table
            $db->query("ALTER TABLE aircraft_weights ADD COLUMN weight_limit REAL NULL");
            echo "<div class='alert alert-success'>✓ Added weight_limit column to aircraft_weights table</div>";
        } else {
            echo "<div class='alert alert-info'>• Weight limit column already exists</div>";
        }
        
        // Check for weight_units, arm_units, and fuel_type columns in aircraft table
        $has_weight_units_column = $db->hasColumn('aircraft', 'weight_units');
        $has_arm_units_column = $db->hasColumn('aircraft', 'arm_units');
        $has_fuel_type_column = $db->hasColumn('aircraft', 'fuel_type');
        
        // Add weight_units column to aircraft table if missing
        if (!$has_weight_units_column) {
            $db->query("ALTER TABLE aircraft ADD COLUMN weight_units TEXT NOT NULL DEFAULT 'Pounds'");
            echo "<div class='alert alert-success'>✓ Added weight_units column to aircraft table</div>";
        } else {
            echo "<div class='alert alert-info'>• Weight units column already exists</div>";
        }
        
        // Add arm_units column to aircraft table if missing
        if (!$has_arm_units_column) {
            $db->query("ALTER TABLE aircraft ADD COLUMN arm_units TEXT NOT NULL DEFAULT 'Inches'");
            echo "<div class='alert alert-success'>✓ Added arm_units column to aircraft table</div>";
        } else {
            echo "<div class='alert alert-info'>• Arm units column already exists</div>";
        }
        
        // Add fuel_type column to aircraft table if missing
        if (!$has_fuel_type_column) {
            $db->query("ALTER TABLE aircraft ADD COLUMN fuel_type TEXT NOT NULL DEFAULT '100LL/Mogas'");
            echo "<div class='alert alert-success'>✓ Added fuel_type column to aircraft table</div>";
        } else {
            echo "<div class='alert alert-info'>• Fuel type column already exists</div>";
        }
        
        // Add envelope_name and color columns to aircraft_cg table
        $has_envelope_name_column = $db->hasColumn('aircraft_cg', 'envelope_name');
        $has_color_column = $db->hasColumn('aircraft_cg', 'color');
        
        if (!$has_envelope_name_column) {
            $db->query("ALTER TABLE aircraft_cg ADD COLUMN envelope_name TEXT NOT NULL DEFAULT 'Normal'");
            echo "<div class='alert alert-success'>✓ Added envelope_name column to aircraft_cg table</div>";
        } else {
            echo "<div class='alert alert-info'>• Envelope name column already exists</div>";
        }
        
        if (!$has_color_column) {
            $db->query("ALTER TABLE aircraft_cg ADD COLUMN color TEXT NOT NULL DEFAULT 'blue'");
            echo "<div class='alert alert-success'>✓ Added color column to aircraft_cg table</div>";
        } else {
            echo "<div class='alert alert-info'>• Color column already exists</div>";
        }
        
        // Clean up any 0/0 placeholder points that might exist
        $cleanup_result = $db->query("DELETE FROM aircraft_cg WHERE arm = 0 AND weight = 0");
        echo "<div class='alert alert-success'>✓ Cleaned up placeholder CG points</div>";
        
        // Remove fuelwt column from aircraft_weights table if it exists
        $has_fuelwt_column = $db->hasColumn('aircraft_weights', 'fuelwt');
        
        if ($has_fuelwt_column) {
            try {
                // Instead of dropping the column (which causes locking issues), 
                // we'll set default values for existing records and let the app ignore it
                $db->query("UPDATE aircraft_weights SET fuelwt = 0 WHERE fuelwt IS NULL");
                echo "<div class='alert alert-success'>✓ Set default values for fuel weight column</div>";
                echo "<div class='alert alert-info'>• The application now calculates fuel weights automatically based on fuel type</div>";
            } catch (Exception $e) {
                echo "<div class='alert alert-warning'>⚠ Fuel weight column exists but will be ignored by the application</div>";
                echo "<div class='alert alert-info'>• The application now calculates fuel weights automatically based on fuel type</div>";
            }
        } else {
            echo "<div class='alert alert-info'>• Fuel weight column already removed</div>";
        }
        
        // Remove deprecated CG warning columns from aircraft table if they exist
        $has_cgwarn_columns = $db->hasColumn('aircraft', 'cgwarnfwd') || $db->hasColumn('aircraft', 'cgwarnaft');
        
        if ($has_cgwarn_columns) {
            try {
                // Modern SQLite supports DROP COLUMN directly - use the simple approach
                $db->query("ALTER TABLE aircraft DROP COLUMN cgwarnfwd");
                echo "<div class='alert alert-success'>✓ Removed cgwarnfwd column</div>";
                
                $db->query("ALTER TABLE aircraft DROP COLUMN cgwarnaft");
                echo "<div class='alert alert-success'>✓ Removed cgwarnaft column</div>";
                
                echo "<div class='alert alert-success'>✓ Removed deprecated CG warning columns from aircraft table</div>";
            } catch (Exception $e) {
                echo "<div class='alert alert-warning'>⚠ Could not remove deprecated CG warning columns: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='alert alert-info'>• Deprecated CG warning columns already removed</div>";
        }
        
        // Remove deprecated cglimits column from aircraft table if it exists
        $has_cglimits_column = $db->hasColumn('aircraft', 'cglimits');
        
        if ($has_cglimits_column) {
            try {
                $db->query("ALTER TABLE aircraft DROP COLUMN cglimits");
                echo "<div class='alert alert-success'>✓ Removed deprecated cglimits column from aircraft table</div>";
            } catch (Exception $e) {
                echo "<div class='alert alert-warning'>⚠ Could not remove deprecated cglimits column: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='alert alert-info'>• Deprecated cglimits column already removed</div>";
        }
        
        // Add max_landing_weight column to aircraft table if missing
        $has_max_landing_weight_column = $db->hasColumn('aircraft', 'max_landing_weight');
        
        if (!$has_max_landing_weight_column) {
            $db->query("ALTER TABLE aircraft ADD COLUMN max_landing_weight REAL NULL");
            echo "<div class='alert alert-success'>✓ Added max_landing_weight column to aircraft table</div>";
        } else {
            echo "<div class='alert alert-info'>• Maximum landing weight column already exists</div>";
        }
        
        // Update version number
        $db->query("UPDATE configuration SET value = ? WHERE item = 'update_version'", [$ver]);
        echo "<div class='alert alert-success'>✓ Updated version to {$ver}</div>";
        
        echo "<div class='alert alert-success'><strong>Upgrade completed successfully!</strong></div>";
        
        echo "<div class='alert alert-info'>";
        echo "<h6 class='alert-heading'><i class='fas fa-info-circle'></i> Important Reminder</h6>";
        echo "<p class='mb-0'>Please verify your aircraft data and configurations after the upgrade process to ensure everything is working correctly.</p>";
        echo "</div>";
        
        echo "<div class='d-grid'>";
        echo "<a href='admin.php' class='btn btn-primary'>Continue to Administration</a>";
        echo "</div>";
        
        return true;
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Upgrade failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        return false;
    }
}

// Main upgrade logic
if (!needsUpgrade()) {
    echo "<div class='alert alert-info text-center'>";
    echo "<h5>No Upgrade Required</h5>";
    echo "<p>Your TippingPoint installation is already up to date (version {$ver}).</p>";
    echo "<a href='admin.php' class='btn btn-primary'>Continue to Administration</a>";
    echo "</div>";
} else {
    if (isset($_REQUEST['confirm']) && $_REQUEST['confirm'] == 'yes') {
        performUpgrade();
    } else {
        // Check what specifically needs upgrading
        include_once 'database.inc';
        $db = getDB();
        $missing_features = [];
        
        // Check type and weight_limit columns, and fuelwt column (should be removed)
        $has_type_column = $db->hasColumn('aircraft_weights', 'type');
        $has_weight_limit_column = $db->hasColumn('aircraft_weights', 'weight_limit');
        $has_fuelwt_column = $db->hasColumn('aircraft_weights', 'fuelwt');
        if (!$has_type_column) {
            $missing_features[] = "Loading zones Type system";
        }
        if (!$has_weight_limit_column) {
            $missing_features[] = "Weight limit feature for Variable Weight with limit types";
        }
        // Note: fuelwt column is no longer considered a blocking issue
        
        // Check envelope_name and color columns in aircraft_cg table
        $has_envelope_name_column = $db->hasColumn('aircraft_cg', 'envelope_name');
        $has_color_column = $db->hasColumn('aircraft_cg', 'color');
        if (!$has_envelope_name_column) {
            $missing_features[] = "Multiple CG envelope support (envelope_name column)";
        }
        if (!$has_color_column) {
            $missing_features[] = "CG envelope color coding (color column)";
        }
        
        // Check pilot signature
        $result = $db->query("SELECT COUNT(*) as count FROM configuration WHERE item = 'pilot_signature'");
        $row = $db->fetchAssoc($result);
        if ($row['count'] == 0) {
            $missing_features[] = "Pilot signature setting";
        }
        
        // Check aircraft table columns
        $has_weight_units_column = $db->hasColumn('aircraft', 'weight_units');
        $has_arm_units_column = $db->hasColumn('aircraft', 'arm_units');
        $has_fuel_type_column = $db->hasColumn('aircraft', 'fuel_type');
        $has_cgwarn_columns = $db->hasColumn('aircraft', 'cgwarnfwd') || $db->hasColumn('aircraft', 'cgwarnaft');
        $has_cglimits_column = $db->hasColumn('aircraft', 'cglimits');
        $has_max_landing_weight_column = $db->hasColumn('aircraft', 'max_landing_weight');
        if (!$has_weight_units_column) {
            $missing_features[] = "Weight units setting for aircraft";
        }
        if (!$has_arm_units_column) {
            $missing_features[] = "Arm units setting for aircraft";
        }
        if (!$has_fuel_type_column) {
            $missing_features[] = "Fuel type setting for aircraft";
        }
        if ($has_cgwarn_columns) {
            $missing_features[] = "Removal of deprecated CG warning columns";
        }
        if ($has_cglimits_column) {
            $missing_features[] = "Removal of deprecated textual CG limits column";
        }
        if (!$has_max_landing_weight_column) {
            $missing_features[] = "Maximum landing weight (MLW) feature";
        }
        
        echo "<div class='alert alert-warning text-center'>";
        echo "<h5>Database Upgrade Required</h5>";
        echo "<p>Your TippingPoint installation is missing some version 2.0.0 features.</p>";
        echo "<p><strong>Missing features detected:</strong></p>";
        echo "<ul class='text-start'>";
        foreach ($missing_features as $feature) {
            echo "<li>{$feature}</li>";
        }
        echo "</ul>";
        echo "<p class='text-muted'><small>Please ensure you have backed up your database before proceeding.</small></p>";
        echo "<form method='post'>";
        echo "<input type='hidden' name='confirm' value='yes'>";
        echo "<button type='submit' class='btn btn-warning'>Proceed with Upgrade</button>";
        echo "</form>";
        echo "</div>";
    }
}
?>

</div>
</div>
</div>
</div>
</div>

<?php
// Use site_name from config if available, otherwise use default
try {
    include_once 'database.inc';
    $db = getDB();
    $result = $db->query("SELECT value FROM configuration WHERE item = 'site_name'");
    $row = $db->fetchAssoc($result);
    $site_name = $row ? $row['value'] : 'TippingPoint';
    
    $result = $db->query("SELECT value FROM configuration WHERE item = 'administrator'");
    $row = $db->fetchAssoc($result);
    $admin = $row ? $row['value'] : '';
} catch (Exception $e) {
    $site_name = 'TippingPoint';
    $admin = '';
}

PageFooter($site_name, $admin, $ver);
?>