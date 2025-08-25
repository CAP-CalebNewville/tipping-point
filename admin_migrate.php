<?php
// Migration script from MySQL to SQLite
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
    header('Location: admin.php?sysmsg=unauthorized');
    exit;
}

PageHeader("Database Migration");
?>

<body>
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-8 col-md-10">
<div class="card mt-4">
<div class="card-header bg-info text-white text-center">
<h3 class="mb-0">MySQL to SQLite Migration</h3>
</div>
<div class="card-body">

<?php
// Check if we should show this page
if (!file_exists('config.inc')) {
    echo "<div class='alert alert-warning text-center'>";
    echo "<h5 class='alert-heading'>No MySQL Configuration Found</h5>";
    echo "<p>This system appears to be using SQLite already or hasn't been set up yet.</p>";
    echo "<a href='index.php' class='btn btn-primary'>Go to Application</a>";
    echo "</div>";
    echo "</div></div></div></div></div>";
    PageFooter("TippingPoint Migration", "migration@tippingpoint", $ver);
    exit;
}

// Check if SQLite database already exists
if (file_exists(dirname(__FILE__) . '/data/tippingpoint.db')) {
    // Check if we're here for deprecated column removal
    if (isset($_REQUEST['reason']) && $_REQUEST['reason'] == 'deprecated_columns') {
        echo "<div class='alert alert-warning text-center'>";
        echo "<h5 class='alert-heading'>Database Cleanup Required</h5>";
        echo "<p>Your database contains deprecated CG warning columns that need to be removed.</p>";
        echo "<p>The system now uses advanced CG envelope validation instead of simple forward/aft limits.</p>";
        echo "<hr>";
        echo "<form method='post' action='admin_migrate.php'>";
        echo "<input type='hidden' name='func' value='cleanup'>";
        echo "<button type='submit' class='btn btn-warning'>Remove Deprecated Columns</button> ";
        echo "<a href='admin.php' class='btn btn-outline-secondary'>Skip (Not Recommended)</a>";
        echo "</form>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-info text-center'>";
        echo "<h5 class='alert-heading'>Migration Already Complete</h5>";
        echo "<p>This system has already been migrated to SQLite.</p>";
        echo "<a href='index.php' class='btn btn-primary'>Go to Application</a>";
        echo "</div>";
        echo "</div></div></div></div></div>";
        PageFooter("TippingPoint Migration", "migration@tippingpoint", $ver);
        exit;
    }
}

switch (isset($_REQUEST["func"]) ? $_REQUEST["func"] : "") {
    case "migrate":
        echo "<h5 class='card-title'>Migration in Progress</h5>";
        
        try {
            // Load MySQL configuration
            require 'config.inc';
            
            // Connect to MySQL
            $mysql_con = mysqli_connect($dbserver, $dbuser, $dbpass, $dbname);
            if (!$mysql_con) {
                throw new Exception('Failed to connect to MySQL: ' . mysqli_connect_error());
            }
            
            echo "<div class='alert alert-info'>Connected to MySQL database...</div>";
            
            // Create SQLite database
            include 'database.inc';
            $sqlite_pdo = createSQLiteDatabase();
            
            echo "<div class='alert alert-info'>Created SQLite database...</div>";
            
            // Start migration
            $tables = ['configuration', 'users', 'aircraft', 'aircraft_weights', 'aircraft_cg', 'audit'];
            $total_records = 0;
            
            foreach ($tables as $table) {
                echo "<div class='alert alert-secondary'>Migrating table: $table</div>";
                
                // Get data from MySQL
                $stmt = mysqli_prepare($mysql_con, "SELECT * FROM `$table`");
                mysqli_stmt_execute($stmt);
                $mysql_result = mysqli_stmt_get_result($stmt);
                if (!$mysql_result) {
                    throw new Exception("Failed to read from MySQL table $table: " . mysqli_error($mysql_con));
                }
                
                $records = 0;
                while ($row = mysqli_fetch_assoc($mysql_result)) {
                    $records++;
                    
                    // Prepare data for SQLite
                    $columns = array_keys($row);
                    $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                    $values = array_values($row);
                    
                    // Handle special cases for SQLite compatibility
                    if ($table === 'aircraft_weights') {
                        // 'order' is a reserved word in SQLite, need to quote it
                        $column_names = array_map(function($col) {
                            return $col === 'order' ? '"order"' : $col;
                        }, $columns);
                        $sql = "INSERT INTO $table (" . implode(',', $column_names) . ") VALUES ($placeholders)";
                    } else {
                        $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES ($placeholders)";
                    }
                    
                    $stmt = $sqlite_pdo->prepare($sql);
                    if (!$stmt->execute($values)) {
                        throw new Exception("Failed to insert data into SQLite table $table");
                    }
                }
                
                echo "<div class='alert alert-success'>Migrated $records records from $table</div>";
                $total_records += $records;
            }
            
            // Close MySQL connection
            mysqli_close($mysql_con);
            
            // Update configuration to indicate SQLite migration
            $stmt = $sqlite_pdo->prepare("INSERT OR REPLACE INTO configuration (item, value) VALUES ('database_type', 'sqlite')");
            $stmt->execute();
            
            $stmt = $sqlite_pdo->prepare("INSERT INTO audit (who, what) VALUES (?, ?)");
            $stmt->execute(['system', 'MIGRATION: Completed MySQL to SQLite migration']);
            
            // Remove deprecated CG warning columns
            echo "<div class='alert alert-info'>Removing deprecated CG warning columns...</div>";
            
            try {
                // Check if columns exist before attempting to remove them
                $columns_check = $sqlite_pdo->query("PRAGMA table_info(aircraft)");
                $has_cgwarnfwd = false;
                $has_cgwarnaft = false;
                
                while ($column = $columns_check->fetch(PDO::FETCH_ASSOC)) {
                    if ($column['name'] == 'cgwarnfwd') $has_cgwarnfwd = true;
                    if ($column['name'] == 'cgwarnaft') $has_cgwarnaft = true;
                }
                
                if ($has_cgwarnfwd || $has_cgwarnaft) {
                    // SQLite doesn't support DROP COLUMN directly, so we need to recreate the table
                    $sqlite_pdo->exec("BEGIN TRANSACTION");
                    
                    // Create new table structure without cgwarnfwd and cgwarnaft columns
                    $sqlite_pdo->exec("CREATE TABLE aircraft_new (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        active INTEGER NOT NULL,
                        tailnumber TEXT NOT NULL,
                        makemodel TEXT NOT NULL,
                        emptywt REAL NOT NULL,
                        emptycg REAL NOT NULL,
                        maxwt REAL NOT NULL,
                        cglimits TEXT NULL,
                        fuelunit TEXT NOT NULL,
                        weight_units TEXT NOT NULL DEFAULT 'Pounds',
                        fuel_type TEXT NOT NULL DEFAULT '100LL/Mogas'
                    )");
                    
                    // Copy data to new table (excluding cgwarnfwd and cgwarnaft)
                    $sqlite_pdo->exec("INSERT INTO aircraft_new (id, active, tailnumber, makemodel, emptywt, emptycg, maxwt, cglimits, fuelunit, weight_units, fuel_type)
                                      SELECT id, active, tailnumber, makemodel, emptywt, emptycg, maxwt, cglimits, fuelunit, 
                                             COALESCE(weight_units, 'Pounds'), COALESCE(fuel_type, '100LL/Mogas')
                                      FROM aircraft");
                    
                    // Replace old table with new one
                    $sqlite_pdo->exec("DROP TABLE aircraft");
                    $sqlite_pdo->exec("ALTER TABLE aircraft_new RENAME TO aircraft");
                    
                    $sqlite_pdo->exec("COMMIT");
                    
                    echo "<div class='alert alert-success'>✓ Removed deprecated CG warning columns (cgwarnfwd, cgwarnaft)</div>";
                    
                    // Log the column removal
                    $stmt = $sqlite_pdo->prepare("INSERT INTO audit (who, what) VALUES (?, ?)");
                    $stmt->execute(['system', 'MIGRATION: Removed deprecated CG warning columns']);
                } else {
                    echo "<div class='alert alert-info'>• CG warning columns already removed or not present</div>";
                }
            } catch (Exception $e) {
                echo "<div class='alert alert-warning'>⚠ Could not remove CG warning columns: " . htmlspecialchars($e->getMessage()) . "</div>";
                // Continue with migration even if column removal fails
            }
            
            echo "<div class='alert alert-success'>";
            echo "<h5 class='alert-heading'>Migration Complete!</h5>";
            echo "<p>Successfully migrated $total_records records from MySQL to SQLite.</p>";
            echo "<hr>";
            echo "<h6>What's Changed:</h6>";
            echo "<ul>";
            echo "<li>Database engine changed from MySQL to SQLite</li>";
            echo "<li>All your data has been preserved</li>";
            echo "<li>No configuration changes needed</li>";
            echo "<li>Improved performance and easier backups</li>";
            echo "</ul>";
            echo "</div>";
            
            echo "<div class='alert alert-warning'>";
            echo "<h6 class='alert-heading'>Important Notes:</h6>";
            echo "<ul>";
            echo "<li>Your MySQL database is still intact and unchanged</li>";
            echo "<li>The system will now use SQLite automatically</li>";
            echo "<li>You can remove config.inc if you no longer need MySQL access</li>";
            echo "<li>Regular backups are recommended - simply copy the /data/ folder</li>";
            echo "</ul>";
            echo "</div>";
            
            echo "<div class='alert alert-info'>";
            echo "<h6 class='alert-heading'>Final Step: Database Upgrade</h6>";
            echo "<p>The migration is complete, but your SQLite database needs to be upgraded to the latest schema to ensure all features work properly.</p>";
            echo "<div class='d-grid gap-2 d-md-block text-center'>";
            echo "<a href='admin_upgrade.php' class='btn btn-warning'>Run Database Upgrade</a>";
            echo "</div>";
            echo "</div>";
            
            echo "<div class='alert alert-info mt-3'>";
            echo "<h6 class='alert-heading'><i class='fas fa-info-circle'></i> Important Reminder</h6>";
            echo "<p class='mb-0'>Please verify your aircraft data and configurations after the migration process to ensure everything is working correctly.</p>";
            echo "</div>";
            
            echo "<div class='d-grid gap-2 d-md-block text-center'>";
            echo "<a href='admin.php' class='btn btn-outline-secondary'>Skip to Admin Interface</a> ";
            echo "<a href='index.php' class='btn btn-outline-secondary'>Skip to Calculator</a>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>";
            echo "<h5 class='alert-heading'>Migration Failed</h5>";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>Your MySQL database has not been modified. You can try the migration again.</p>";
            echo "</div>";
            echo "<a href='admin_migrate.php' class='btn btn-primary'>Try Again</a>";
        }
        break;
        
    case "cleanup":
        echo "<h5 class='card-title'>Database Cleanup in Progress</h5>";
        
        try {
            // Connect to SQLite database
            include 'database.inc';
            $db = getDB();
            
            echo "<div class='alert alert-info'>Checking for deprecated CG warning columns...</div>";
            
            // Check if columns exist before attempting to remove them
            $columns_check = $db->query("PRAGMA table_info(aircraft)");
            $has_cgwarnfwd = false;
            $has_cgwarnaft = false;
            
            while ($column = $db->fetchAssoc($columns_check)) {
                if ($column['name'] == 'cgwarnfwd') $has_cgwarnfwd = true;
                if ($column['name'] == 'cgwarnaft') $has_cgwarnaft = true;
            }
            
            if ($has_cgwarnfwd || $has_cgwarnaft) {
                echo "<div class='alert alert-info'>Removing deprecated columns: " . 
                     ($has_cgwarnfwd ? 'cgwarnfwd ' : '') . 
                     ($has_cgwarnaft ? 'cgwarnaft' : '') . "</div>";
                
                // SQLite doesn't support DROP COLUMN directly, so we need to recreate the table
                $db->query("BEGIN TRANSACTION");
                
                // Create new table structure without cgwarnfwd and cgwarnaft columns
                $db->query("CREATE TABLE aircraft_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    active INTEGER NOT NULL,
                    tailnumber TEXT NOT NULL,
                    makemodel TEXT NOT NULL,
                    emptywt REAL NOT NULL,
                    emptycg REAL NOT NULL,
                    maxwt REAL NOT NULL,
                    cglimits TEXT NULL,
                    fuelunit TEXT NOT NULL,
                    weight_units TEXT NOT NULL DEFAULT 'Pounds',
                    fuel_type TEXT NOT NULL DEFAULT '100LL/Mogas'
                )");
                
                // Copy data to new table (excluding cgwarnfwd and cgwarnaft)
                $db->query("INSERT INTO aircraft_new (id, active, tailnumber, makemodel, emptywt, emptycg, maxwt, cglimits, fuelunit, weight_units, fuel_type)
                           SELECT id, active, tailnumber, makemodel, emptywt, emptycg, maxwt, cglimits, fuelunit, 
                                  COALESCE(weight_units, 'Pounds'), COALESCE(fuel_type, '100LL/Mogas')
                           FROM aircraft");
                
                // Replace old table with new one
                $db->query("DROP TABLE aircraft");
                $db->query("ALTER TABLE aircraft_new RENAME TO aircraft");
                
                $db->query("COMMIT");
                
                echo "<div class='alert alert-success'>";
                echo "<h5 class='alert-heading'>Cleanup Complete!</h5>";
                echo "<p>Successfully removed deprecated CG warning columns.</p>";
                echo "<hr>";
                echo "<h6>What Changed:</h6>";
                echo "<ul>";
                echo "<li>Removed deprecated cgwarnfwd (Forward CG Warning) column</li>";
                echo "<li>Removed deprecated cgwarnaft (Aft CG Warning) column</li>";
                echo "<li>System now uses advanced CG envelope validation</li>";
                echo "<li>All aircraft data has been preserved</li>";
                echo "</ul>";
                echo "</div>";
                
                // Log the column removal
                $db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", 
                          ['system', 'CLEANUP: Removed deprecated CG warning columns']);
                
                echo "<div class='alert alert-info mt-3'>";
                echo "<h6 class='alert-heading'><i class='fas fa-info-circle'></i> Important Reminder</h6>";
                echo "<p class='mb-0'>Please verify your aircraft data and configurations after the migration process to ensure everything is working correctly.</p>";
                echo "</div>";
                
                echo "<div class='d-grid gap-2 d-md-block text-center'>";
                echo "<a href='admin.php' class='btn btn-primary'>Continue to Admin Interface</a> ";
                echo "<a href='index.php' class='btn btn-outline-primary'>Weight & Balance Calculator</a>";
                echo "</div>";
                
            } else {
                echo "<div class='alert alert-info'>";
                echo "<h5 class='alert-heading'>No Cleanup Needed</h5>";
                echo "<p>No deprecated CG warning columns were found in your database.</p>";
                echo "</div>";
                
                echo "<div class='alert alert-info mt-3'>";
                echo "<h6 class='alert-heading'><i class='fas fa-info-circle'></i> Important Reminder</h6>";
                echo "<p class='mb-0'>Please verify your aircraft data and configurations after the migration process to ensure everything is working correctly.</p>";
                echo "</div>";
                
                echo "<div class='text-center'>";
                echo "<a href='admin.php' class='btn btn-primary'>Continue to Admin Interface</a>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>";
            echo "<h5 class='alert-heading'>Cleanup Failed</h5>";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>Your database has not been modified. You can try the cleanup again or contact support.</p>";
            echo "</div>";
            echo "<a href='admin_migrate.php?reason=deprecated_columns' class='btn btn-primary'>Try Again</a> ";
            echo "<a href='admin.php' class='btn btn-outline-secondary'>Skip Cleanup</a>";
        }
        break;
        
    default:
        // Show migration overview and requirements check
        echo "<h5 class='card-title'>Database Migration</h5>";
        echo "<p class='text-muted'>This will migrate your existing MySQL database to SQLite for improved performance and easier management.</p>";
        
        $requirementsPassed = displayRequirementsCheck();
        
        if ($requirementsPassed) {
            // Test MySQL connection
            try {
                require 'config.inc';
                $test_con = mysqli_connect($dbserver, $dbuser, $dbpass, $dbname);
                if (!$test_con) {
                    throw new Exception(mysqli_connect_error());
                }
                
                // Get database statistics
                $stats = [];
                $tables = ['configuration', 'users', 'aircraft', 'aircraft_weights', 'aircraft_cg', 'audit'];
                foreach ($tables as $table) {
                    $stmt = mysqli_prepare($test_con, "SELECT COUNT(*) as count FROM `$table`");
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $row = mysqli_fetch_assoc($result);
                    $stats[$table] = $row['count'];
                }
                mysqli_close($test_con);
                
                echo "<div class='alert alert-success'>";
                echo "<h6 class='alert-heading'>MySQL Connection Successful</h6>";
                echo "<p>Found the following data to migrate:</p>";
                echo "<ul>";
                foreach ($stats as $table => $count) {
                    echo "<li><strong>" . ucfirst(str_replace('_', ' ', $table)) . ":</strong> $count records</li>";
                }
                echo "</ul>";
                echo "</div>";
                
                echo "<div class='alert alert-info'>";
                echo "<h6 class='alert-heading'>About This Migration</h6>";
                echo "<ul>";
                echo "<li><strong>Safe:</strong> Your MySQL data will remain unchanged</li>";
                echo "<li><strong>Fast:</strong> Migration typically takes less than a minute</li>";
                echo "<li><strong>Automatic:</strong> The system will use SQLite after migration</li>";
                echo "<li><strong>Reversible:</strong> You can always go back to MySQL if needed</li>";
                echo "</ul>";
                echo "</div>";
                
                echo "<form method='post' action='admin_migrate.php'>";
                echo "<input type='hidden' name='func' value='migrate'>";
                echo "<div class='d-grid'>";
                echo "<button type='submit' class='btn btn-success btn-lg'>Start Migration</button>";
                echo "</div>";
                echo "</form>";
                
            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>";
                echo "<h6 class='alert-heading'>MySQL Connection Failed</h6>";
                echo "<p>Cannot connect to your MySQL database: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p>Please check your config.inc file and ensure MySQL is running.</p>";
                echo "</div>";
            }
            
        } else {
            echo "<div class='alert alert-warning'>";
            echo "<h6 class='alert-heading'>Requirements Not Met</h6>";
            echo "<p>Please resolve the issues above before proceeding with migration. Contact your system administrator if you need assistance installing the required PHP extensions.</p>";
            echo "</div>";
        }
        break;
}
?>

</div>
</div>
</div>
</div>
</div>

<?php
PageFooter("TippingPoint Migration", "migration@tippingpoint", $ver);
?>