<?php
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

// Upgrade checks will be performed after authentication


session_start();

// Handle Ajax requests BEFORE any HTML output
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($is_ajax && isset($_REQUEST['func']) && $_REQUEST['func'] == 'aircraft' && isset($_REQUEST['func_do']) && $_REQUEST['func_do'] == 'edit_do') {
    // Include necessary files for Ajax handling
    include_once 'func.inc';
    
    // Initialize loginlevel and check authentication
    $loginlevel = "0";
    $loginuser = isset($_SESSION["loginuser"]) ? $_SESSION["loginuser"] : "";
    $loginpass = isset($_SESSION["loginpass"]) ? $_SESSION["loginpass"] : "";
    
    if (!empty($loginuser) && !empty($loginpass)) {
        $login_query = $db->query("SELECT * FROM users WHERE username = ?", [$loginuser]);
        $pass_verify = $db->fetchAssoc($login_query);
        if ($pass_verify && password_verify($loginpass, $pass_verify['password'])) {
            $loginlevel = $pass_verify['superuser'];
        }
    }
    
    // Check if user is authenticated
    if ($loginlevel != "1") {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }
    
    // Handle the Ajax request
    include 'ajax_handler.php';
    exit;
}

PageHeader("Admin Interface");
?>

<?php
// LOGIN CHECK

// Initialize loginlevel to default value
$loginlevel = "0";

if (!isset($_REQUEST['func']) || $_REQUEST['func'] != "login") {
	$loginuser = isset($_SESSION["loginuser"]) ? $_SESSION["loginuser"] : "";
	$loginpass = isset($_SESSION["loginpass"]) ? $_SESSION["loginpass"] : "";

	// Check if user has session data
	if (!empty($loginuser) && !empty($loginpass)) {
		$login_query = $db->query("SELECT * FROM users WHERE username = ?", [$loginuser]);
		$pass_verify = $db->fetchAssoc($login_query);
		if ($pass_verify && password_verify($loginpass, $pass_verify['password'])) {
			$loginlevel = $pass_verify['superuser'];
			$_SESSION["user_name"] = $pass_verify['name'];
		} else {
			// Invalid credentials - redirect with error
			header('Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?func=login&sysmsg=invalid');
		}
	} else {
		// No session data - redirect to login without error
		header('Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?func=login');
	}
}

// Check if migration from MySQL to SQLite is needed BEFORE upgrade checks
// This prevents modifying the original MySQL database before migration
if ($loginlevel == "1" && file_exists('config.inc') && !file_exists(dirname(__FILE__) . '/data/tippingpoint.db')) {
    // Skip upgrade checks and go directly to show migration option
    // We'll perform upgrade checks after migration or if user declines migration
} else {
    // Perform upgrade checks only for authenticated administrators
    if ($loginlevel == "1") {
        // Check if upgrade is needed
        if (isset($config['update_version']) && version_compare($config['update_version'], '2.0.0', '<')) {
            header('Location: admin_upgrade.php');
            exit;
        }

        // Additional check for required columns existence (in case config wasn't updated properly)
        try {
            $test_query = $db->query("SELECT type, weight_limit FROM aircraft_weights LIMIT 1");
            $test_query2 = $db->query("SELECT weight_units, fuel_type FROM aircraft LIMIT 1");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'no such column:') !== false) {
                header('Location: admin_upgrade.php');
                exit;
            }
        }
    }
}

echo "<body>\n";
echo "<nav class=\"navbar navbar-expand-lg navbar-dark bg-secondary fixed-top noprint\">\n";
echo "  <div class=\"container-fluid\">\n";
echo "    <a class=\"navbar-brand text-warning\" href=\"admin.php\" title=\"TippingPoint Administration Home\">TippingPoint</a>\n";
if (isset($_SESSION["user_name"])) {
    echo "    <button class=\"navbar-toggler\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#navbarNav\" aria-controls=\"navbarNav\" aria-expanded=\"false\" aria-label=\"Toggle navigation\">\n";
    echo "      <span class=\"navbar-toggler-icon\"></span>\n";
    echo "    </button>\n";
    echo "    <div class=\"collapse navbar-collapse\" id=\"navbarNav\">\n";
    echo "      <div class=\"navbar-nav ms-auto\">\n";
    echo "        <a class=\"nav-link\" href=\"admin.php?func=system\">System Settings</a>\n";
    echo "        <a class=\"nav-link\" href=\"admin.php?func=aircraft\">Aircraft</a>\n";
    echo "        <a class=\"nav-link\" href=\"admin.php?func=users\">Users</a>\n";
    echo "        <a class=\"nav-link\" href=\"admin.php?func=audit\">Audit Log</a>\n";
    echo "        <a class=\"nav-link\" href=\"admin.php?func=logout\">Logout</a>\n";
    echo "      </div>\n";
    echo "    </div>\n";
}
echo "  </div>\n";
echo "</nav>\n";
echo "<div class=\"container-fluid\">\n";

// UPDATE CHECK
if ($config['update_check'] < (time()-86400)) {
	// Get latest release from GitHub API
	$github_api_url = "https://api.github.com/repos/CAP-CalebNewville/tipping-point/releases/latest";
	$context = stream_context_create([
		'http' => [
			'method' => 'GET',
			'header' => 'User-Agent: TippingPoint-Update-Check'
		]
	]);
	$github_response = file_get_contents($github_api_url, false, $context);
	
	if ($github_response !== false) {
		$release_data = json_decode($github_response, true);
		if (isset($release_data['tag_name'])) {
			$ver_dist = ltrim($release_data['tag_name'], 'v'); // Remove 'v' prefix if present
		} else {
			$ver_dist = $config['update_version']; // Fallback to current version if API fails
		}
	} else {
		$ver_dist = $config['update_version']; // Fallback to current version if API fails
	}
	
	$db->query("UPDATE configuration SET `value` = ? WHERE `item` = 'update_check'", [time()]);
	$db->query("UPDATE configuration SET `value` = ? WHERE `item` = 'update_version'", [$ver_dist]);
	$current_user = isset($_SESSION["loginuser"]) ? $_SESSION["loginuser"] : 'system';
	$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$current_user, 'UPDATE_CHECK: installed ' . $ver . ', available ' . $ver_dist]);
}
if ($ver != $config['update_version'] && $loginlevel=="1") {
	echo "<div class=\"alert alert-warning text-center\">\n";
	echo "TippingPoint version " . $config['update_version'] . " is available, you are currently running version " . $ver . ".<br>\n";
	echo "View the <a href=\"https://github.com/CAP-CalebNewville/tipping-point/releases\" target=\"_blank\">releases page</a> to see what's new, or visit the <a href=\"https://github.com/CAP-CalebNewville/tipping-point\" target=\"_blank\">project homepage</a> to download.<br>\n";
	echo "</div>\n";
}

// Helper function to create readable audit messages
function createAuditMessage($action, $data = []) {
    $message = $action;
    if (!empty($data)) {
        $parts = [];
        foreach ($data as $key => $value) {
            $parts[] = "{$key}={$value}";
        }
        $message .= ": " . implode(", ", $parts);
    }
    return $message;
}


// Check for deprecated CG warning columns that need to be removed
if ($loginlevel=="1") {
    try {
        $has_deprecated_columns = $db->hasColumn('aircraft', 'cgwarnfwd') || $db->hasColumn('aircraft', 'cgwarnaft') || $db->hasColumn('aircraft', 'cglimits');
        
        if ($has_deprecated_columns) {
            header('Location: admin_upgrade.php?reason=deprecated_columns');
            exit;
        }
    } catch (Exception $e) {
        // If we can't check for deprecated columns, continue anyway
    }
}

// Check for missing arm_units column
if ($loginlevel=="1") {
    try {
        $has_arm_units_column = $db->hasColumn('aircraft', 'arm_units');
        
        if (!$has_arm_units_column) {
            header('Location: admin_upgrade.php?reason=missing_arm_units');
            exit;
        }
    } catch (Exception $e) {
        // If we can't check for arm_units column, continue anyway
    }
}

echo "<div class=\"row justify-content-center\">\n<div class=\"col-lg-8 col-md-10\">\n<div class=\"card mt-4\">\n<div class=\"card-body\">\n";

// Display migration prompt if needed (before upgrade checks)
if ($loginlevel=="1" && file_exists('config.inc') && !file_exists(dirname(__FILE__) . '/data/tippingpoint.db')) {
	echo "<div class=\"alert alert-info text-center\">\n";
	echo "<h5 class=\"alert-heading\">Database Migration Available</h5>\n";
	echo "<p>TippingPoint now uses SQLite for improved performance and easier deployment. Your MySQL data can be migrated automatically.</p>\n";
	echo "<p><strong>Benefits:</strong> Better performance, easier backups, simpler deployment, no database server required.</p>\n";
	echo "<a href=\"admin_migrate.php\" class=\"btn btn-primary\">Migrate to SQLite</a> \n";
	echo "<button class=\"btn btn-outline-secondary\" onclick=\"this.parentElement.style.display='none';\">Remind Me Later</button>\n";
	echo "</div>\n";
}

if (isset($_REQUEST['sysmsg'])) {
    $sysmsg = $_REQUEST['sysmsg'];
    if ($sysmsg=="logout") { echo "<div class=\"alert alert-success text-center\">You have been logged out.</div>\n\n";
    } elseif ($sysmsg=="login") { echo "<div class=\"alert alert-success text-center\">You have been logged in. Select a function from the navigation above.</div>\n\n";
    } elseif ($sysmsg=="unauthorized") { echo "<div class=\"alert alert-danger text-center\">Sorry, you are not allowed to access that module.</div>\n\n";
    } elseif ($sysmsg=="invalid") { echo "<div class=\"alert alert-danger text-center\">You have entered an invalid username/password combination.</div>\n\n";
    } elseif ($sysmsg=="acdeleted") { echo "<div class=\"alert alert-success text-center\">The aircraft has been deleted.</div>\n\n"; }
}

switch (isset($_REQUEST["func"]) ? $_REQUEST["func"] : "") {
    case "login":
    	if (isset($_REQUEST['username']) && $_REQUEST['username']!="" && isset($_REQUEST['password'])) {
    		// login validation code here - stay logged in for a week
    		// setcookie("loginuser", $_REQUEST['username'], time()+604800);
    		// setcookie("loginpass", md5($_REQUEST['password']), time()+604800);
				// Validate login credentials first
				$login_query = $db->query("SELECT * FROM users WHERE username = ?", [$_REQUEST['username']]);
				$user_data = $db->fetchAssoc($login_query);
				
				if ($user_data && password_verify($_REQUEST['password'], $user_data['password'])) {
					// Valid login - set session and redirect
					$_SESSION["loginuser"] = $_REQUEST['username'];
					$_SESSION["loginpass"] = $_REQUEST['password'];
					header('Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?sysmsg=login');
				} else {
					// Invalid login - redirect with error
					header('Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?func=login&sysmsg=invalid');
				}
    	} else {
    		// print login form
				echo "<div class=\"text-center mb-4\">\n";
				echo "<h2 class=\"text-primary\">Tipping Point Administration</h2>\n";
				echo "</div>\n";
    		echo "<form method=\"post\" action=\"admin.php\" class=\"mx-auto\" style=\"max-width: 400px;\">\n";
    		echo "<input type=\"hidden\" name=\"func\" value=\"login\">\n";
    		echo "<div class=\"mb-3\">\n";
    		echo "<label for=\"username\" class=\"form-label\">Username</label>\n";
    		echo "<input type=\"text\" class=\"form-control\" id=\"username\" name=\"username\" required>\n";
    		echo "</div>\n";
    		echo "<div class=\"mb-3\">\n";
    		echo "<label for=\"password\" class=\"form-label\">Password</label>\n";
    		echo "<input type=\"password\" class=\"form-control\" id=\"password\" name=\"password\" required>\n";
    		echo "</div>\n";
    		echo "<div class=\"d-grid\">\n";
    		echo "<button type=\"submit\" class=\"btn btn-primary\">Login</button>\n";
    		echo "</div>\n";
    		echo "</form>\n";
    	}
    	break;

    case "logout":
    	//setcookie("loginuser", "", time()-3600);
    	//setcookie("loginpass", "", time()-3600);
			session_unset();
			session_destroy();
    	header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=login&sysmsg=logout');
    	break;

    case "system":
	if ($loginlevel!="1") {
		header('Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?sysmsg=unauthorized');
	}

        echo "<h3 class=\"text-primary mb-3\">System Module</h3>";
	if (isset($_REQUEST['message']) && $_REQUEST['message']=="updated") {echo "<p style=\"color: #00AA00; text-align: center;\">Settings Updated.</p>\n\n";}
    	switch (isset($_REQUEST["func_do"]) ? $_REQUEST["func_do"] : "") {
    		case "update":
    			// SQL query to update system settings
			foreach ($_POST as $k=>$v) {
				if ($k!="func" && $k!="func_do") {
					// Use INSERT OR REPLACE to handle new config items
					$sql_query = "INSERT OR REPLACE INTO configuration (item, value) VALUES (?, ?);";
					$db->query($sql_query, [$k, $v]);
					// Enter audit log
					$audit_message = createAuditMessage("Updated system setting", [$k => $v]);
					$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, 'SYSTEM: ' . $audit_message]);
				}
			}
    			header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=system&message=updated');
    			break;
		default:
        		echo "<p class=\"mb-4\">This module adjusts settings that affect the entire software package.</p>";
        		echo "<form method=\"post\" action=\"admin.php\" class=\"mx-auto\" style=\"max-width: 600px;\">";
        		echo "<input type=\"hidden\" name=\"func\" value=\"system\">";
        		echo "<input type=\"hidden\" name=\"func_do\" value=\"update\">";
        		echo "<div class=\"mb-3\">";
        		echo "<label for=\"site_name\" class=\"form-label\">Site/Organization Name</label>";
        		echo "<input type=\"text\" class=\"form-control\" id=\"site_name\" name=\"site_name\" value=\"" . htmlspecialchars($config['site_name']) . "\">";
        		echo "</div>";
        		echo "<div class=\"mb-3\">";
        		echo "<label for=\"administrator\" class=\"form-label\">Administrator E-mail Address</label>";
        		echo "<input type=\"email\" class=\"form-control\" id=\"administrator\" name=\"administrator\" value=\"" . htmlspecialchars($config['administrator']) . "\">";
        		echo "</div>";
        		echo "<div class=\"mb-3\">";
        		echo "<label for=\"timezone\" class=\"form-label\">Local Time Zone</label>";
        		echo "<div>";
		             TimeZoneList($config['timezone']);
        		echo "</div>";
        		echo "</div>";
        		echo "<div class=\"mb-3\">";
        		echo "<label for=\"pilot_signature\" class=\"form-label\">Pilot Signature</label>";
        		echo "<select class=\"form-select\" id=\"pilot_signature\" name=\"pilot_signature\">";
        		echo "<option value=\"0\"";
        		if (!isset($config['pilot_signature']) || $config['pilot_signature'] != '1') { echo " selected"; }
        		echo ">No</option>";
        		echo "<option value=\"1\"";
        		if (isset($config['pilot_signature']) && $config['pilot_signature'] == '1') { echo " selected"; }
        		echo ">Yes</option>";
        		echo "</select>";
        		echo "<div class=\"form-text\">Display \"Pilot Signature\" block on printed weight and balance forms.</div>";
        		echo "</div>";
        		echo "<div class=\"d-grid\">";
        		echo "<button type=\"submit\" class=\"btn btn-primary\">Save Settings</button>";
        		echo "</div>";
        		echo "</form>";
	}
        break;

    case "aircraft":
        echo "<h3 class=\"text-primary mb-3\">Aircraft Module</h3>";
	switch (isset($_REQUEST["func_do"]) ? $_REQUEST["func_do"] : "") {
		case "add":
			switch (isset($_REQUEST["step"]) ? $_REQUEST["step"] : "") {
				case "2":
					// SQL query to add a new aircraft
					$sql_query = "INSERT INTO `aircraft` (`active`, `tailnumber`, `makemodel`, `emptywt`, `emptycg`, `maxwt`, `fuelunit`, `weight_units`, `fuel_type`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
					$db->query($sql_query, ['0', $_REQUEST['tailnumber'], $_REQUEST['makemodel'], $_REQUEST['emptywt'], $_REQUEST['emptycg'], $_REQUEST['maxwt'], $_REQUEST['fuelunit'], $_REQUEST['weight_units'], $_REQUEST['fuel_type']]);
					$aircraft_result = $db->query("SELECT * FROM `aircraft` WHERE `tailnumber` = ? ORDER BY `id` DESC LIMIT 1", [$_REQUEST['tailnumber']]);
					$aircraft = $db->fetchAssoc($aircraft_result);
					// Enter in the audit log
					$audit_data = [
						'tailnumber' => $_REQUEST['tailnumber'],
						'makemodel' => $_REQUEST['makemodel'],
						'emptywt' => $_REQUEST['emptywt'],
						'emptycg' => $_REQUEST['emptycg'],
						'maxwt' => $_REQUEST['maxwt'],
						'fuelunit' => $_REQUEST['fuelunit'],
						'weight_units' => $_REQUEST['weight_units'],
						'fuel_type' => $_REQUEST['fuel_type']
					];
					$audit_message = createAuditMessage("Created new aircraft", $audit_data);
					$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ": " . $audit_message]);
					echo "<p>Aircraft " . $aircraft['tailnumber'] . " added successfully.  Now go to the <a href=\"admin.php?func=aircraft&amp;func_do=edit&amp;tailnumber=" . $aircraft['id'] . "\">aircraft editor</a> to complete the CG envelope and loading zones.</p>\n";
					break;
				default:
					echo "<div class=\"row justify-content-center\">\n";
					echo "<div class=\"col-lg-8 col-md-10\">\n";
					echo "<div class=\"card\">\n";
					echo "<div class=\"card-header\">\n";
					echo "<h5 class=\"card-title mb-0\">Add New Aircraft - Step 1</h5>\n";
					echo "</div>\n";
					echo "<div class=\"card-body\">\n";
					echo "<div class=\"mb-4\">\n";
					echo "<p class=\"mb-2\">Define the basic information about the aircraft.</p>\n";
					echo "<p class=\"text-muted small mb-0\">Fill in the aircraft specifications. Hover over field labels for additional help information.</p>\n";
					echo "</div>\n";
					echo "<form method=\"post\" action=\"admin.php\">\n";
					echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
					echo "<input type=\"hidden\" name=\"func_do\" value=\"add\">\n";
					echo "<input type=\"hidden\" name=\"step\" value=\"2\">\n";
					// Convert table structure to Bootstrap form groups
					echo "<div class=\"row mb-3\">\n";
					echo "<div class=\"col-md-6\">\n";
					echo "<label for=\"tailnumber\" class=\"form-label\">Tail Number</label>\n";
					echo "<input type=\"text\" class=\"form-control\" id=\"tailnumber\" name=\"tailnumber\" placeholder=\"N123AB\" required>\n";
					echo "</div>\n";
					echo "<div class=\"col-md-6\">\n";
					echo "<label for=\"makemodel\" class=\"form-label\">Make and Model</label>\n";
					echo "<input type=\"text\" class=\"form-control\" id=\"makemodel\" name=\"makemodel\" placeholder=\"Cessna Skyhawk\" required>\n";
					echo "</div>\n";
					echo "</div>\n";
					echo "<div class=\"row mb-3\">\n";
					echo "<div class=\"col-md-3\">\n";
					echo "<label for=\"emptywt\" class=\"form-label\">Empty Weight</label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"emptywt\" name=\"emptywt\" placeholder=\"1556.3\" required>\n";
					echo "</div>\n";
					echo "<div class=\"col-md-3\">\n";
					echo "<label for=\"emptycg\" class=\"form-label\">Empty CG</label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"emptycg\" name=\"emptycg\" placeholder=\"38.78\" required>\n";
					echo "</div>\n";
					echo "<div class=\"col-md-3\">\n";
					echo "<label for=\"maxwt\" class=\"form-label\">Maximum Gross Weight</label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"maxwt\" name=\"maxwt\" placeholder=\"2550\" required>\n";
					echo "</div>\n";
					echo "<div class=\"col-md-3\">\n";
					echo "<label for=\"max_landing_weight\" class=\"form-label\">Maximum Landing Weight <small class=\"text-muted\">(Optional)</small></label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"max_landing_weight\" name=\"max_landing_weight\" placeholder=\"e.g. 2300\" value=\"" . (isset($aircraft['max_landing_weight']) ? htmlspecialchars($aircraft['max_landing_weight']) : '') . "\">\n";
					echo "</div>\n";
					echo "</div>\n";
					echo "<div class=\"row mb-4\">\n";
					echo "<div class=\"col-md-3\">\n";
					echo "<label for=\"weight_units\" class=\"form-label\">Weight Units</label>\n";
					echo "<select class=\"form-select\" id=\"weight_units\" name=\"weight_units\">\n";
					echo "<option value=\"Pounds\">Pounds</option>\n";
					echo "<option value=\"Kilograms\">Kilograms</option>\n";
					echo "</select>\n";
					echo "</div>\n";
					echo "<div class=\"col-md-3\">\n";
					echo "<label for=\"arm_units\" class=\"form-label\">Arm Units</label>\n";
					echo "<select class=\"form-select\" id=\"arm_units\" name=\"arm_units\">\n";
					echo "<option value=\"Inches\">Inches</option>\n";
					echo "<option value=\"Centimeters\">Centimeters</option>\n";
					echo "</select>\n";
					echo "</div>\n";
					echo "<div class=\"col-md-3\">\n";
					echo "<label for=\"fuelunit\" class=\"form-label\">Fuel Units</label>\n";
					echo "<select class=\"form-select\" id=\"fuelunit\" name=\"fuelunit\">\n";
					echo "<option value=\"Gallons\">Gallons</option>\n";
					echo "<option value=\"Liters\">Liters</option>\n";
					echo "<option value=\"Pounds\">Pounds</option>\n";
					echo "<option value=\"Kilograms\">Kilograms</option>\n";
					echo "</select>\n";
					echo "</div>\n";
					echo "<div class=\"col-md-3\">\n";
					echo "<label for=\"fuel_type\" class=\"form-label\">Fuel Type</label>\n";
					echo "<select class=\"form-select\" id=\"fuel_type\" name=\"fuel_type\">\n";
					echo "<option value=\"100LL/Mogas\">100LL/Mogas</option>\n";
					echo "<option value=\"Jet A\">Jet A</option>\n";
					echo "</select>\n";
					echo "</div>\n";
					echo "</div>\n";
					echo "<div class=\"d-grid gap-2 d-md-flex justify-content-md-end\">\n";
					echo "<a href=\"admin.php?func=aircraft\" class=\"btn btn-outline-secondary me-md-2\">Cancel</a>\n";
					echo "<button type=\"submit\" class=\"btn btn-primary\">Continue to Step 2</button>\n";
					echo "</div>\n";
					echo "</form>\n";
					echo "</div>\n";
					echo "</div>\n";
					echo "</div>\n";
					echo "</div>\n";
			}
			break;
		case "delete":
			if ($_REQUEST['tailnumber']!="") {
				if ($_REQUEST['confirm']=="DELETE FOREVER") {
					// Get aircraft info before deletion for audit log
					$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
					$aircraft = $db->fetchAssoc($aircraft_query);
					
					$sql_query1 = "DELETE FROM aircraft_cg WHERE `tailnumber` = ?";
					$sql_query2 = "DELETE FROM aircraft_weights WHERE `tailnumber` = ?";
					$sql_query3 = "DELETE FROM aircraft WHERE `id` = ?";
					$db->query($sql_query1, [$_REQUEST['tailnumber']]);
					$db->query($sql_query2, [$_REQUEST['tailnumber']]);
					$db->query($sql_query3, [$_REQUEST['tailnumber']]);
					// Enter in the audit log
					$audit_data = [
						'tailnumber' => $aircraft['tailnumber'],
						'makemodel' => $aircraft['makemodel'],
						'aircraft_id' => $_REQUEST['tailnumber']
					];
					$audit_message = createAuditMessage("Deleted aircraft and all associated data", $audit_data);
					$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, 'ACDELETE: ' . $audit_message]);
					header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&sysmsg=acdeleted');
				} else {
					echo "<div class=\"alert alert-warning\">\n";
					echo "<h5 class=\"alert-heading\">Aircraft NOT Deleted</h5>\n";
					echo "<p class=\"mb-2\">In the confirmation box, you must type the words <strong>\"DELETE FOREVER\"</strong> in all caps.</p>\n";
					echo "<p class=\"mb-0\">Use your browser's back button to try again.</p>\n";
					echo "</div>\n";
				}
			} else {
				echo "<div class=\"row justify-content-center\">\n";
				echo "<div class=\"col-lg-8 col-md-10\">\n";
				echo "<div class=\"card border-danger\">\n";
				echo "<div class=\"card-header bg-danger text-white\">\n";
				echo "<h5 class=\"card-title mb-0\"><i class=\"bi bi-exclamation-triangle\"></i> Delete Aircraft - PERMANENT ACTION</h5>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<div class=\"alert alert-danger mb-4\">\n";
				echo "<h6 class=\"alert-heading\"><strong>⚠️ WARNING: This is permanent and CANNOT be undone!</strong></h6>\n";
				echo "<p class=\"mb-2\">Aircraft deletion is a permanent action. The aircraft and all of its associated data will be gone forever.</p>\n";
				echo "<p class=\"mb-0\">If you wish to temporarily deactivate an aircraft profile, use the <a href=\"admin.php?func=aircraft&amp;func_do=edit\" class=\"alert-link\">edit</a> screen instead. This is useful for a single aircraft with multiple configurations (wheels/skis/floats).</p>\n";
				echo "</div>\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"delete\">\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"tailnumber\" class=\"form-label\">Choose Aircraft to Delete</label>\n";
				AircraftListAll();
				echo "</div>\n";
				echo "<div class=\"mb-4\">\n";
				echo "<label for=\"confirm\" class=\"form-label\">Type the words \"DELETE FOREVER\" to confirm</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"confirm\" name=\"confirm\" placeholder=\"DELETE FOREVER\" required>\n";
				echo "<div class=\"form-text text-danger\">You must type exactly: DELETE FOREVER (in all caps)</div>\n";
				echo "</div>\n";
				echo "<div class=\"d-grid gap-2 d-md-flex justify-content-md-between\">\n";
				echo "<a href=\"admin.php?func=aircraft\" class=\"btn btn-outline-secondary\">Cancel</a>\n";
				echo "<button type=\"submit\" class=\"btn btn-danger\" onclick=\"return window.confirm('Are you REALLY sure you want to PERMANENTLY delete this aircraft?')\">Delete Aircraft Forever</button>\n";
				echo "</div>\n";
				echo "</form>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
			}
			break;
		case "duplicate":
			if ($_REQUEST['tailnumber']!="") {
				// create the new aircraft
				$aircraft_result = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
				$aircraft = $db->fetchAssoc($aircraft_result);
				$db->query("INSERT INTO aircraft (`active`, `tailnumber`, `makemodel`, `emptywt`, `emptycg`, `maxwt`, `fuelunit`, `weight_units`, `arm_units`, `fuel_type`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
					['0', $_REQUEST['newtailnumber'], $_REQUEST['newmakemodel'], $aircraft['emptywt'], $aircraft['emptycg'], $aircraft['maxwt'], $aircraft['fuelunit'], $aircraft['weight_units'], $aircraft['arm_units'], $aircraft['fuel_type']]);

				// get id of new aircraft
				$aircraft_result = $db->query("SELECT * FROM aircraft WHERE tailnumber = ? ORDER BY id DESC LIMIT 1", [$_REQUEST['newtailnumber']]);
				$aircraft_new = $db->fetchAssoc($aircraft_result);

				// duplicate the weights
				$weights_result = $db->query("SELECT * FROM aircraft_weights WHERE tailnumber = ?", [$_REQUEST['tailnumber']]);
				while($row = $db->fetchAssoc($weights_result)) {
					$db->query("INSERT INTO aircraft_weights (`tailnumber`, `order`, `item`, `weight`, `arm`, `fuelwt`, `type`, `weight_limit`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
						[$aircraft_new['id'], $row['order'], $row['item'], $row['weight'], $row['arm'], $row['fuelwt'] ?? 0, $row['type'] ?? 'Variable Weight no limit', $row['weight_limit'] ?? null]);
				}

				// duplicate the cg envelopes
				$cg_result = $db->query("SELECT * FROM aircraft_cg WHERE tailnumber = ?", [$_REQUEST['tailnumber']]);
				while($row = $db->fetchAssoc($cg_result)) {
					$envelope_name = isset($row['envelope_name']) ? $row['envelope_name'] : 'Normal';
					$color = isset($row['color']) ? $row['color'] : 'blue';
					$db->query("INSERT INTO aircraft_cg (`tailnumber`, `arm`, `weight`, `envelope_name`, `color`) VALUES (?, ?, ?, ?, ?)", 
						[$aircraft_new['id'], $row['arm'], $row['weight'], $envelope_name, $color]);
				}

				// Enter in the audit log
				$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, 
					'DUPLICATE: (' . $aircraft['id'] . ', ' . $aircraft['tailnumber'] . ', ' . $aircraft['makemodel'] . ') AS (' . $aircraft_new['id'] . ', ' . $_REQUEST['newtailnumber'] . ', ' . $_REQUEST['newmakemodel'] . ')']);

				echo "<div class=\"alert alert-success\">\n";
				echo "<h5 class=\"alert-heading\">Aircraft Duplicated Successfully!</h5>\n";
				echo "<p class=\"mb-2\">The aircraft has been cloned with the new tail number: <strong>" . htmlspecialchars($_REQUEST['newtailnumber']) . "</strong></p>\n";
				echo "<p class=\"mb-0\">Next step: <a href=\"admin.php?func=aircraft&amp;func_do=edit&amp;tailnumber=" . $aircraft_new['id'] . "\" class=\"alert-link\">Complete the aircraft configuration</a> by editing the CG envelope and loading zones.</p>\n";
				echo "</div>\n";
			} else {
				echo "<div class=\"row justify-content-center\">\n";
				echo "<div class=\"col-lg-8 col-md-10\">\n";
				echo "<div class=\"card\">\n";
				echo "<div class=\"card-header\">\n";
				echo "<h5 class=\"card-title mb-0\">Duplicate Aircraft</h5>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<div class=\"mb-4\">\n";
				echo "<p class=\"mb-3\">Clone an existing aircraft to create a new one with the same specifications.</p>\n";
				echo "<p class=\"text-muted small mb-0\">This is useful for aircraft with multiple configurations (wheels/skis/floats) or similar models that share most specifications.</p>\n";
				echo "</div>\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"duplicate\">\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"tailnumber\" class=\"form-label\">Choose Aircraft to Duplicate</label>\n";
				AircraftListAll();
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"newtailnumber\" class=\"form-label\">New Tail Number</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"newtailnumber\" name=\"newtailnumber\" placeholder=\"N123AB\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-4\">\n";
				echo "<label for=\"newmakemodel\" class=\"form-label\">New Make and Model</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"newmakemodel\" name=\"newmakemodel\" placeholder=\"Cessna Skyhawk\" required>\n";
				echo "</div>\n";
				echo "<div class=\"d-grid gap-2 d-md-flex justify-content-md-end\">\n";
				echo "<a href=\"admin.php?func=aircraft\" class=\"btn btn-outline-secondary me-md-2\">Cancel</a>\n";
				echo "<button type=\"submit\" class=\"btn btn-success\">Duplicate Aircraft</button>\n";
				echo "</div>\n";
				echo "</form>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
			}
			break;
		case "edit":
			if (isset($_REQUEST['tailnumber']) && $_REQUEST['tailnumber']!="") {
				// Check if database needs upgrade before any output
				$has_envelope_columns = $db->hasColumn('aircraft_cg', 'envelope_name');
				
				if (!$has_envelope_columns) {
					// Redirect to upgrade script
					header('Location: admin_upgrade.php');
					exit;
				}
				
				$aircraft_result = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
				$aircraft = $db->fetchAssoc($aircraft_result);

				echo "<div class=\"mb-4\">\n";
				echo "<h4 class=\"text-primary\">Editing Aircraft: " . htmlspecialchars($aircraft['tailnumber']) . " ";
				if ($aircraft['active'] == 1) {
					echo "<span class=\"badge bg-success\" style=\"font-size: 0.8rem; vertical-align: middle;\">Active</span>";
				} else {
					echo "<span class=\"badge bg-secondary\" style=\"font-size: 0.8rem; vertical-align: middle;\">Inactive</span>";
				}
				echo "</h4>\n";
				echo "<div class=\"mt-2\">\n";
				echo "<a href=\"index.php?tailnumber=" . htmlspecialchars($aircraft['id']) . "\" target=\"_blank\" class=\"btn btn-outline-primary btn-sm\">\n";
				echo "<i class=\"fas fa-external-link-alt\"></i> View Aircraft\n";
				echo "</a>\n";
				echo "</div>\n";
				echo "</div>\n";

				if (isset($_REQUEST['message']) && $_REQUEST['message']=="updated") {echo "<div class=\"alert alert-success text-center\">Aircraft Updated Successfully</div>\n\n";}

				// Aircraft basic information
				echo "<div class=\"card mb-4\">\n";
				echo "<div class=\"card-header\">\n";
				echo "<h4 class=\"card-title mb-0\">Aircraft Basic Information</h4>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"id\" value=\"" . $aircraft['id'] . "\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit_do\">\n";
				echo "<input type=\"hidden\" name=\"what\" value=\"basics\">\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-md-6\">\n";
				echo "<label for=\"tailnumber\" class=\"form-label\">Tail Number</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"tailnumber\" name=\"tailnumber\" value=\"" . htmlspecialchars($aircraft['tailnumber']) . "\">\n";
				echo "</div>\n";
				echo "<div class=\"col-md-6\">\n";
				echo "<label for=\"makemodel\" class=\"form-label\">Make and Model</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"makemodel\" name=\"makemodel\" value=\"" . htmlspecialchars($aircraft['makemodel']) . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-md-3\">\n";
				echo "<label for=\"emptywt\" class=\"form-label\">Empty Weight</label>\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"emptywt\" name=\"emptywt\" value=\"" . $aircraft['emptywt'] . "\">\n";
				echo "</div>\n";
				echo "<div class=\"col-md-3\">\n";
				echo "<label for=\"emptycg\" class=\"form-label\">Empty CG</label>\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"emptycg\" name=\"emptycg\" value=\"" . $aircraft['emptycg'] . "\">\n";
				echo "</div>\n";
				echo "<div class=\"col-md-3\">\n";
				echo "<label for=\"maxwt\" class=\"form-label\">Maximum Gross Weight</label>\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"maxwt\" name=\"maxwt\" value=\"" . $aircraft['maxwt'] . "\">\n";
				echo "</div>\n";
				echo "<div class=\"col-md-3\">\n";
				echo "<label for=\"max_landing_weight\" class=\"form-label\">Maximum Landing Weight</label>\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"max_landing_weight\" name=\"max_landing_weight\" placeholder=\"Optional - e.g. 2300\" value=\"" . (isset($aircraft['max_landing_weight']) ? htmlspecialchars($aircraft['max_landing_weight']) : '') . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				
				
				
				echo "<div class=\"row mb-4\">\n";
				echo "<div class=\"col-md-3\">\n";
				echo "<label for=\"weight_units\" class=\"form-label\">Weight Units</label>\n";
				echo "<select class=\"form-select\" id=\"weight_units\" name=\"weight_units\">\n";
				echo "<option value=\"Pounds\"";
					if(isset($aircraft['weight_units']) && $aircraft['weight_units']=="Pounds") {echo " selected";}
				echo ">Pounds</option>\n";
				echo "<option value=\"Kilograms\"";
					if(isset($aircraft['weight_units']) && $aircraft['weight_units']=="Kilograms") {echo " selected";}
				echo ">Kilograms</option>\n";
				echo "</select>\n";
				echo "</div>\n";
				echo "<div class=\"col-md-3\">\n";
				echo "<label for=\"arm_units\" class=\"form-label\">Arm Units</label>\n";
				echo "<select class=\"form-select\" id=\"arm_units\" name=\"arm_units\">\n";
				echo "<option value=\"Inches\"";
					if(isset($aircraft['arm_units']) && $aircraft['arm_units']=="Inches") {echo " selected";}
				echo ">Inches</option>\n";
				echo "<option value=\"Centimeters\"";
					if(isset($aircraft['arm_units']) && $aircraft['arm_units']=="Centimeters") {echo " selected";}
				echo ">Centimeters</option>\n";
				echo "</select>\n";
				echo "</div>\n";
				echo "<div class=\"col-md-3\">\n";
				echo "<label for=\"fuelunit\" class=\"form-label\">Fuel Units</label>\n";
				echo "<select class=\"form-select\" id=\"fuelunit\" name=\"fuelunit\">\n";
				echo "<option value=\"Gallons\"";
					if($aircraft['fuelunit']=="Gallons") {echo " selected";}
				echo ">Gallons</option>\n";
				echo "<option value=\"Liters\"";
					if($aircraft['fuelunit']=="Liters") {echo " selected";}
				echo ">Liters</option>\n";
				echo "<option value=\"Pounds\"";
					if($aircraft['fuelunit']=="Pounds") {echo " selected";}
				echo ">Pounds</option>\n";
				echo "<option value=\"Kilograms\"";
					if($aircraft['fuelunit']=="Kilograms") {echo " selected";}
				echo ">Kilograms</option>\n";
				echo "</select>\n";
				echo "</div>\n";
				echo "<div class=\"col-md-3\">\n";
				echo "<label for=\"fuel_type\" class=\"form-label\">Fuel Type</label>\n";
				echo "<select class=\"form-select\" id=\"fuel_type\" name=\"fuel_type\">\n";
				echo "<option value=\"100LL/Mogas\"";
					if(isset($aircraft['fuel_type']) && $aircraft['fuel_type']=="100LL/Mogas") {echo " selected";}
				echo ">100LL/Mogas</option>\n";
				echo "<option value=\"Jet A\"";
					if(isset($aircraft['fuel_type']) && $aircraft['fuel_type']=="Jet A") {echo " selected";}
				echo ">Jet A</option>\n";
				echo "</select>\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row align-items-center\">\n";
				echo "<div class=\"col-md-3\">\n";
				echo "</div>\n";
				echo "<div class=\"col-md-6 text-center\">\n";
				echo "<div class=\"form-check form-switch\" style=\"gap: 0.25rem; display: flex; align-items: center; justify-content: center;\">\n";
				echo "<input type=\"checkbox\" class=\"form-check-input\" name=\"active\" value=\"1\" id=\"active\" style=\"margin: 0;\"";
					if ($aircraft['active']==1) {echo" checked";}
					echo ">\n";
				echo "<label class=\"form-check-label\" for=\"active\" style=\"margin: 0;\">Show aircraft in the weight & balance list</label>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "<div class=\"col-md-3 text-end\">\n";
				echo "<button type=\"submit\" class=\"btn btn-primary\">Save Basic Information</button>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</form>\n";
				echo "</div>\n";
				echo "</div>\n";

				// Aircraft CG envelopes (multiple envelopes support)
				echo "<div class=\"card mb-4\">\n";
				echo "<div class=\"card-header d-flex justify-content-between align-items-center\">\n";
				echo "<h4 class=\"card-title mb-0\">Center of Gravity Envelopes</h4>\n";
				echo "<div class=\"btn-group\" role=\"group\">\n";
				echo "<button type=\"button\" class=\"btn btn-success btn-sm\" data-bs-toggle=\"modal\" data-bs-target=\"#newEnvelopeModal\">Add New Envelope</button>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				
				// Help information for CG envelopes
				echo "<div class=\"alert alert-info mb-3\">\n";
				echo "<h6 class=\"alert-heading\"><i class=\"fas fa-info-circle\"></i> Where to Find CG Envelope Data</h6>\n";
				echo "<p class=\"mb-2\">CG envelope data can typically be found in:</p>\n";
				echo "<ul class=\"mb-2\">\n";
				echo "<li>Your aircraft's <strong>Flight Manual (AFM/POH)</strong></li>\n";
				echo "<li>FAA <strong>Type Certificate Data Sheet (TCDS)</strong> - <a href=\"https://drs.faa.gov/browse/TCDSMODEL/doctypeDetails\" target=\"_blank\" rel=\"noopener\">Available here <i class=\"fas fa-external-link-alt\"></i></a></li>\n";
				echo "</ul>\n";
				echo "<p class=\"mb-0\"><strong>Important:</strong> Always verify that STCs (Supplemental Type Certificates) or other modifications have not superseded the manufacturer's original data. Modified aircraft may have different CG limits.</p>\n";
				echo "</div>\n";
				
				// Get all unique envelopes for this aircraft
				$envelope_result = $db->query("SELECT DISTINCT envelope_name, color FROM aircraft_cg WHERE tailnumber = ? ORDER BY envelope_name", [$aircraft['id']]);
				$envelopes = [];
				while ($envelope = $db->fetchAssoc($envelope_result)) {
					$envelopes[] = $envelope;
				}
				
				// If we have a current envelope from URL that's not in the list (newly created), add it
				if (isset($_GET['envelope']) && !empty($_GET['envelope'])) {
					$current_from_url = $_GET['envelope'];
					$found_in_list = false;
					foreach ($envelopes as $env) {
						if ($env['envelope_name'] === $current_from_url) {
							$found_in_list = true;
							break;
						}
					}
					if (!$found_in_list) {
						// Add the envelope from URL with color from URL parameter or default
						$color_from_url = isset($_GET['envelope_color']) ? $_GET['envelope_color'] : 'blue';
						$envelopes[] = ['envelope_name' => $current_from_url, 'color' => $color_from_url];
					}
				}
				
				// If no envelopes exist, show info message
				if (empty($envelopes)) {
					echo "<div class=\"alert alert-info text-center\">\n";
					echo "<h6>No CG Envelopes Defined</h6>\n";
					echo "<p class=\"mb-2\">This aircraft doesn't have any CG envelopes defined yet.</p>\n";
					echo "<button type=\"button\" class=\"btn btn-primary\" data-bs-toggle=\"modal\" data-bs-target=\"#newEnvelopeModal\">Create First Envelope</button>\n";
					echo "</div>\n";
				} else {
					// Envelope selector and current envelope display
					$current_envelope = isset($_GET['envelope']) ? $_GET['envelope'] : $envelopes[0]['envelope_name'];
					$current_envelope_color = 'primary';
					
					// Check for envelope color in URL parameter first (for newly created envelopes)
					if (isset($_GET['envelope_color']) && !empty($_GET['envelope_color'])) {
						$current_envelope_color = $_GET['envelope_color'];
					} else {
						// Find current envelope color from existing envelopes
						foreach ($envelopes as $env) {
							if ($env['envelope_name'] == $current_envelope) {
								$current_envelope_color = $env['color'];
								break;
							}
						}
					}
					
					echo "<div class=\"row mb-3\">\n";
					echo "<div class=\"col-md-8\">\n";
					echo "<div class=\"alert alert-info mb-0\">\n";
					echo "<small>Enter the data points for the CG envelope. It does not matter which point you start with or if you go clockwise or counter-clockwise, but they must be entered in order. The last point will automatically be connected back to the first.</small>\n";
					echo "</div>\n";
					echo "</div>\n";
					
					// Only show envelope selector if multiple envelopes exist
					if (count($envelopes) > 1) {
						echo "<div class=\"col-md-4\">\n";
						echo "<div class=\"card border-" . $current_envelope_color . "\">\n";
						echo "<div class=\"card-body p-2\">\n";
						echo "<h6 class=\"card-title mb-2\">Current Envelope</h6>\n";
						echo "<select class=\"form-select form-select-sm\" onchange=\"window.location.href='admin.php?func=aircraft&func_do=edit&tailnumber=" . $aircraft['id'] . "&envelope=' + this.value\">\n";
						foreach ($envelopes as $env) {
							$selected = ($env['envelope_name'] == $current_envelope) ? 'selected' : '';
							echo "<option value=\"" . htmlspecialchars($env['envelope_name']) . "\" {$selected}>" . htmlspecialchars($env['envelope_name']) . " (" . ucfirst($env['color']) . ")</option>\n";
						}
						echo "</select>\n";
						echo "<div class=\"mt-2\">\n";
						echo "<button type=\"button\" class=\"btn btn-outline-primary btn-sm\" onclick=\"editEnvelope('" . htmlspecialchars($current_envelope) . "', '" . htmlspecialchars($current_envelope_color) . "')\">Edit Envelope</button>\n";
						echo "</div>\n";
						echo "</div>\n";
						echo "</div>\n";
						echo "</div>\n";
					}
					echo "</div>\n";
					
					// Get CG points for current envelope
					$cg_result = $db->query("SELECT * FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ?", [$aircraft['id'], $current_envelope]);
					echo "<form method=\"post\" action=\"admin.php\" name=\"cg\">\n";
					echo "<input type=\"hidden\" name=\"tailnumber\" value=\"" . $aircraft['id'] . "\">\n";
					echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
					echo "<input type=\"hidden\" name=\"func_do\" value=\"edit_do\">\n";
					echo "<input type=\"hidden\" name=\"what\" value=\"cg\">\n";
					echo "<input type=\"hidden\" name=\"envelope_name\" value=\"" . htmlspecialchars($current_envelope) . "\">\n";
					// Pass envelope color if provided in URL (for new envelopes)
					if (isset($_GET['envelope_color']) && !empty($_GET['envelope_color'])) {
						echo "<input type=\"hidden\" name=\"envelope_color\" value=\"" . htmlspecialchars($_GET['envelope_color']) . "\">\n";
					}
					
					echo "<div class=\"table-responsive\">\n";
					echo "<table class=\"table table-sm table-hover\">\n";
					echo "<thead class=\"table-dark\">\n";
					echo "<tr><th class=\"text-center\">Arm</th><th class=\"text-center\">Weight</th><th class=\"text-center\">Actions</th></tr>\n";
					echo "</thead>\n";
					echo "<tbody>\n";
					
					while($cg = $db->fetchAssoc($cg_result)) {
						echo "<tr>\n";
						echo "<td class=\"text-center\">\n";
						echo "<input type=\"number\" step=\"any\" name=\"cgarm" . $cg['id'] . "\" value=\"" . $cg['arm'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 100px; display: inline-block;\">\n";
						echo "</td>\n";
						echo "<td class=\"text-center\">\n";
						echo "<input type=\"number\" step=\"any\" name=\"cgweight" . $cg['id'] . "\" value=\"" . $cg['weight'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 100px; display: inline-block;\">\n";
						echo "</td>\n";
						echo "<td class=\"text-center\">\n";
						echo "<div class=\"btn-group btn-group-sm\" role=\"group\">\n";
						echo "<button type=\"button\" class=\"btn btn-outline-primary\" onclick=\"updateCGPoint(" . $cg['id'] . ", " . $aircraft['id'] . ", '" . htmlspecialchars($current_envelope, ENT_QUOTES) . "')\">Update</button>\n";
						echo "<button type=\"button\" class=\"btn btn-outline-danger\" onclick=\"deleteCGPoint(" . $cg['id'] . ", " . $aircraft['id'] . ")\">Delete</button>\n";
						echo "</div>\n";
						echo "</td>\n";
						echo "</tr>\n";
					}
					
					// Add new CG point row
					echo "<tr class=\"table-success\">\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"new_arm\" class=\"form-control form-control-sm text-center\" placeholder=\"Arm\" style=\"width: 100px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"new_weight\" class=\"form-control form-control-sm text-center\" placeholder=\"Weight\" style=\"width: 100px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<button type=\"button\" class=\"btn btn-success btn-sm\" onclick=\"addCGPoint(" . $aircraft['id'] . ", '" . htmlspecialchars($current_envelope, ENT_QUOTES) . "', '" . $current_envelope_color . "')\">Add Point</button>\n";
					echo "</td>\n";
					echo "</tr>\n";
					
					echo "</tbody>\n";
					echo "</table>\n";
					echo "</div>\n";
					echo "</form>\n";
					
					// CG Graph
					echo "<div class=\"text-center mt-4\">\n";
					echo "<embed src=\"scatter.php?size=small&amp;tailnumber=" . $aircraft['id'] . "&amp;envelope=" . urlencode($current_envelope) . "\" width=\"420\" height=\"220\" class=\"border rounded\">\n";
					echo "</div>\n";
				}
				
				echo "</div>\n";
				echo "</div>\n";
				
				// New Envelope Modal
				echo "<div class=\"modal fade\" id=\"newEnvelopeModal\" tabindex=\"-1\">\n";
				echo "<div class=\"modal-dialog\">\n";
				echo "<div class=\"modal-content\">\n";
				echo "<div class=\"modal-header\">\n";
				echo "<h5 class=\"modal-title\">Add New CG Envelope</h5>\n";
				echo "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n";
				echo "</div>\n";
				echo "<div class=\"modal-body\">\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"envelope_name\" class=\"form-label\">Envelope Name</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"envelope_name\" name=\"envelope_name\" placeholder=\"e.g., Normal, Utility, Aerobatic\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"envelope_color\" class=\"form-label\">Color</label>\n";
				echo "<select class=\"form-select\" id=\"envelope_color\" name=\"envelope_color\" required>\n";
				
				// Get used colors to disable them
				$used_colors = [];
				foreach ($envelopes as $env) {
					$used_colors[] = $env['color'];
				}
				
				$available_colors = [
					'blue' => 'Blue',
					'purple' => 'Purple', 
					'brown' => 'Brown',
					'gray' => 'Gray',
					'black' => 'Black',
					'lime' => 'Lime',
					'cyan' => 'Cyan',
					'magenta' => 'Magenta',
					'maroon' => 'Maroon',
					'teal' => 'Teal'
				];
				
				foreach ($available_colors as $color_value => $color_name) {
					$disabled = in_array($color_value, $used_colors) ? ' disabled' : '';
					$suffix = in_array($color_value, $used_colors) ? ' (Already Used)' : '';
					echo "<option value=\"{$color_value}\"{$disabled}>{$color_name}{$suffix}</option>\n";
				}
				
				echo "</select>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "<div class=\"modal-footer\">\n";
				echo "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Cancel</button>\n";
				echo "<button type=\"button\" class=\"btn btn-primary\" onclick=\"createEnvelope(" . $aircraft['id'] . ")\">Create Envelope</button>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				
				// Edit Envelope Modal
				echo "<div class=\"modal fade\" id=\"editEnvelopeModal\" tabindex=\"-1\">\n";
				echo "<div class=\"modal-dialog\">\n";
				echo "<div class=\"modal-content\">\n";
				echo "<div class=\"modal-header\">\n";
				echo "<h5 class=\"modal-title\">Edit CG Envelope</h5>\n";
				echo "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n";
				echo "</div>\n";
				echo "<div class=\"modal-body\">\n";
				echo "<input type=\"hidden\" id=\"edit_old_envelope_name\" value=\"\">\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"edit_envelope_name\" class=\"form-label\">Envelope Name</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"edit_envelope_name\" name=\"envelope_name\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"edit_envelope_color\" class=\"form-label\">Color</label>\n";
				echo "<select class=\"form-select\" id=\"edit_envelope_color\" name=\"envelope_color\" required>\n";
				echo "<!-- Options will be populated dynamically by JavaScript -->\n";
				echo "</select>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "<div class=\"modal-footer\">\n";
				echo "<button type=\"button\" class=\"btn btn-danger me-auto\" onclick=\"deleteCurrentEnvelope()\">Delete Envelope</button>\n";
				echo "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Cancel</button>\n";
				echo "<button type=\"button\" class=\"btn btn-primary\" onclick=\"updateEnvelope(" . $aircraft['id'] . ")\">Save Changes</button>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				
				// JavaScript for envelope management
				echo "<script>\n";
				echo "function editEnvelope(envelopeName, envelopeColor) {\n";
				echo "  document.getElementById('edit_old_envelope_name').value = envelopeName;\n";
				echo "  document.getElementById('edit_envelope_name').value = envelopeName;\n";
				echo "  \n";
				echo "  // Populate color dropdown with validation\n";
				echo "  var colorSelect = document.getElementById('edit_envelope_color');\n";
				echo "  var usedColors = [];\n";
				
				// Pass used colors from PHP to JavaScript, excluding the current envelope's color
				echo "  var allUsedColors = " . json_encode(array_column($envelopes, 'color')) . ";\n";
				echo "  usedColors = allUsedColors.filter(function(color) { return color !== envelopeColor; });\n";
				echo "  \n";
				echo "  var availableColors = {\n";
				echo "    'blue': 'Blue',\n";
				echo "    'purple': 'Purple',\n";
				echo "    'brown': 'Brown',\n";
				echo "    'gray': 'Gray',\n";
				echo "    'black': 'Black',\n";
				echo "    'lime': 'Lime',\n";
				echo "    'cyan': 'Cyan',\n";
				echo "    'magenta': 'Magenta',\n";
				echo "    'maroon': 'Maroon',\n";
				echo "    'teal': 'Teal'\n";
				echo "  };\n";
				echo "  \n";
				echo "  // Clear and repopulate color select\n";
				echo "  colorSelect.innerHTML = '';\n";
				echo "  \n";
				echo "  for (var colorValue in availableColors) {\n";
				echo "    var option = document.createElement('option');\n";
				echo "    option.value = colorValue;\n";
				echo "    var isUsed = usedColors.includes(colorValue);\n";
				echo "    option.disabled = isUsed;\n";
				echo "    option.textContent = availableColors[colorValue] + (isUsed ? ' (Already Used)' : '');\n";
				echo "    if (colorValue === envelopeColor) {\n";
				echo "      option.selected = true;\n";
				echo "    }\n";
				echo "    colorSelect.appendChild(option);\n";
				echo "  }\n";
				echo "  \n";
				echo "  var modal = new bootstrap.Modal(document.getElementById('editEnvelopeModal'));\n";
				echo "  modal.show();\n";
				echo "}\n";
				echo "function createEnvelope(tailnumber) {\n";
				echo "  var nameInput = document.getElementById('envelope_name');\n";
				echo "  var colorSelect = document.getElementById('envelope_color');\n";
				echo "  \n";
				echo "  if (!nameInput.value || !nameInput.value.trim()) {\n";
				echo "    showErrorMessage('Please enter an envelope name');\n";
				echo "    return;\n";
				echo "  }\n";
				echo "  \n";
				echo "  // Check if selected color is disabled\n";
				echo "  var selectedOption = colorSelect.options[colorSelect.selectedIndex];\n";
				echo "  if (selectedOption.disabled) {\n";
				echo "    showErrorMessage('Selected color is already in use. Please choose a different color.');\n";
				echo "    return;\n";
				echo "  }\n";
				echo "  \n";
				echo "  var formData = new FormData();\n";
				echo "  formData.append('func', 'aircraft');\n";
				echo "  formData.append('func_do', 'edit_do');\n";
				echo "  formData.append('what', 'envelope_add');\n";
				echo "  formData.append('tailnumber', tailnumber);\n";
				echo "  formData.append('envelope_name', nameInput.value.trim());\n";
				echo "  formData.append('envelope_color', colorSelect.value);\n";
				echo "  \n";
				echo "  fetch('admin.php', {\n";
				echo "    method: 'POST',\n";
				echo "    headers: {\n";
				echo "      'X-Requested-With': 'XMLHttpRequest'\n";
				echo "    },\n";
				echo "    body: formData\n";
				echo "  }).then(response => response.json()).then(data => {\n";
				echo "    if (data.success) {\n";
				echo "      showSuccessMessage('Envelope created successfully');\n";
				echo "      // Close the modal\n";
				echo "      var modal = bootstrap.Modal.getInstance(document.getElementById('newEnvelopeModal'));\n";
				echo "      modal.hide();\n";
				echo "      // Clear the form\n";
				echo "      nameInput.value = '';\n";
				echo "      colorSelect.selectedIndex = 0;\n";
				echo "      // Redirect to the new envelope\n";
				echo "      window.location.href = 'admin.php?func=aircraft&func_do=edit&tailnumber=' + tailnumber + '&envelope=' + encodeURIComponent(data.envelope_name) + '&envelope_color=' + encodeURIComponent(data.envelope_color);\n";
				echo "    } else {\n";
				echo "      showErrorMessage(data.error || 'Failed to create envelope');\n";
				echo "    }\n";
				echo "  }).catch(error => {\n";
				echo "    showErrorMessage('Error creating envelope: ' + error.message);\n";
				echo "  });\n";
				echo "}\n";
				echo "function deleteCurrentEnvelope() {\n";
				echo "  var envelopeName = document.getElementById('edit_envelope_name').value;\n";
				echo "  if (confirm('Are you sure you want to delete the \"' + envelopeName + '\" envelope? This will remove all CG points for this envelope.')) {\n";
				echo "    window.location.href = 'admin.php?func=aircraft&func_do=edit_do&what=envelope_delete&envelope_name=' + encodeURIComponent(envelopeName) + '&tailnumber=" . $aircraft['id'] . "';\n";
				echo "  }\n";
				echo "}\n";
				echo "function updateEnvelope(tailnumber) {\n";
				echo "  var oldNameInput = document.getElementById('edit_old_envelope_name');\n";
				echo "  var nameInput = document.getElementById('edit_envelope_name');\n";
				echo "  var colorSelect = document.getElementById('edit_envelope_color');\n";
				echo "  \n";
				echo "  if (!nameInput.value || !nameInput.value.trim()) {\n";
				echo "    showErrorMessage('Please enter an envelope name');\n";
				echo "    return;\n";
				echo "  }\n";
				echo "  \n";
				echo "  // Check if selected color is disabled\n";
				echo "  var selectedOption = colorSelect.options[colorSelect.selectedIndex];\n";
				echo "  if (selectedOption.disabled) {\n";
				echo "    showErrorMessage('Selected color is already in use. Please choose a different color.');\n";
				echo "    return;\n";
				echo "  }\n";
				echo "  \n";
				echo "  var formData = new FormData();\n";
				echo "  formData.append('func', 'aircraft');\n";
				echo "  formData.append('func_do', 'edit_do');\n";
				echo "  formData.append('what', 'envelope_edit');\n";
				echo "  formData.append('tailnumber', tailnumber);\n";
				echo "  formData.append('old_envelope_name', oldNameInput.value);\n";
				echo "  formData.append('envelope_name', nameInput.value.trim());\n";
				echo "  formData.append('envelope_color', colorSelect.value);\n";
				echo "  \n";
				echo "  fetch('admin.php', {\n";
				echo "    method: 'POST',\n";
				echo "    headers: {\n";
				echo "      'X-Requested-With': 'XMLHttpRequest'\n";
				echo "    },\n";
				echo "    body: formData\n";
				echo "  }).then(response => response.json()).then(data => {\n";
				echo "    if (data.success) {\n";
				echo "      showSuccessMessage('Envelope updated successfully');\n";
				echo "      // Close the modal\n";
				echo "      var modal = bootstrap.Modal.getInstance(document.getElementById('editEnvelopeModal'));\n";
				echo "      modal.hide();\n";
				echo "      // Redirect to the updated envelope\n";
				echo "      window.location.href = 'admin.php?func=aircraft&func_do=edit&tailnumber=' + tailnumber + '&envelope=' + encodeURIComponent(data.envelope_name) + '&envelope_color=' + encodeURIComponent(data.envelope_color);\n";
				echo "    } else {\n";
				echo "      showErrorMessage(data.error || 'Failed to update envelope');\n";
				echo "    }\n";
				echo "  }).catch(error => {\n";
				echo "    showErrorMessage('Error updating envelope: ' + error.message);\n";
				echo "  });\n";
				echo "}\n";
				echo "</script>\n";

				// Aircraft loading zones
				echo "<div class=\"card mb-4\">\n";
				echo "<div class=\"card-header\">\n";
				echo "<h4 class=\"card-title mb-0\">Loading Zones</h4>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<div class=\"alert alert-info mb-4\">\n";
				echo "<small>Enter the data for each reference datum. Hover over column headers for detailed descriptions of each field.</small>\n";
				echo "</div>\n";
				
				$weights_result = $db->query("SELECT * FROM aircraft_weights WHERE tailnumber = ? ORDER BY `order` ASC", [$aircraft['id']]);
				echo "<form method=\"post\" action=\"admin.php\" name=\"loading\">\n";
				echo "<input type=\"hidden\" name=\"tailnumber\" value=\"" . $aircraft['id'] . "\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit_do\">\n";
				echo "<input type=\"hidden\" name=\"what\" value=\"loading\">\n";
				
				echo "<div class=\"table-responsive\">\n";
				echo "<table class=\"table table-sm table-hover\">\n";
				echo "<thead class=\"table-dark\">\n";
				echo "<tr>\n";
				echo "<th class=\"text-center\" title=\"This is a number which determines the vertical listing order of the row.\">Order</th>\n";
				echo "<th class=\"text-center\" title=\"A short textual description of the row.\">Item</th>\n";
				echo "<th class=\"text-center\" title=\"Select the type of loading zone: Empty Weight (locked on spreadsheet), Variable Weight no limit, Variable Weight with limit, Fixed Weight Removable, or Fuel (automatically computes weight from fuel quantity).\">Type</th>\n";
				echo "<th class=\"text-center\" title=\"Weight limit, default level, or default installed state depending on type\">&nbsp;</th>\n";
				echo "<th class=\"text-center\" title=\"The default weight to be used for a row. If this is a fuel row, the default number of " . $aircraft['fuelunit'] . ".\">Default<br>Value</th>\n";
				echo "<th class=\"text-center\" title=\"The number of inches from the reference datum for the row.\">Arm</th>\n";
				echo "<th class=\"text-center\">Actions</th>\n";
				echo "</tr>\n";
				echo "</thead>\n";
				echo "<tbody>\n";
				
				while ($weights = $db->fetchAssoc($weights_result)) {
					echo "<tr>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" name=\"order" . $weights['id'] . "\" value=\"" . $weights['order'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 60px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"text\" name=\"item" . $weights['id'] . "\" value=\"" . htmlspecialchars($weights['item']) . "\" class=\"form-control form-control-sm\" style=\"width: 140px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<select name=\"type" . $weights['id'] . "\" class=\"form-select form-select-sm\" style=\"width: 220px; display: inline-block;\" onchange=\"toggleWeightLimit(" . $weights['id'] . ")\">\n";
					$selected_type = isset($weights['type']) ? $weights['type'] : 'Variable Weight no limit';
					$type_options = [
						'Empty Weight' => 'Empty Weight',
						'Variable Weight no limit' => 'Variable Weight no limit',
						'Variable Weight with limit' => 'Variable Weight with limit',
						'Fixed Weight Removable' => 'Fixed Weight Removable',
						'Fuel' => 'Fuel'
					];
					foreach ($type_options as $value => $label) {
						echo "<option value=\"" . htmlspecialchars($value) . "\"";
						if ($selected_type == $value) { echo " selected"; }
						echo ">" . htmlspecialchars($label) . "</option>\n";
					}
					echo "</select>\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					if ($selected_type == "Variable Weight with limit") {
						echo "<div class=\"d-flex align-items-center justify-content-center gap-1\">";
						echo "<input type=\"number\" step=\"any\" name=\"weight_limit" . $weights['id'] . "\" value=\"" . ($weights['weight_limit'] ?? '') . "\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\" id=\"weight_limit" . $weights['id'] . "\" placeholder=\"Max\">";
						echo "<small class=\"text-muted text-nowrap\">" . $aircraft['weight_units'] . " max</small>";
						echo "</div>";
					} elseif ($selected_type == "Fuel") {
						echo "<div class=\"d-flex align-items-center justify-content-center gap-1\">";
						echo "<input type=\"number\" step=\"any\" name=\"weight_limit" . $weights['id'] . "\" value=\"" . ($weights['weight_limit'] ?? '') . "\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\" id=\"weight_limit" . $weights['id'] . "\" placeholder=\"Max\">";
						echo "<small class=\"text-muted text-nowrap\">" . $aircraft['fuelunit'] . " max</small>";
						echo "</div>";
					} elseif ($selected_type == "Fixed Weight Removable") {
						echo "<div class=\"d-flex align-items-center justify-content-center gap-1\">";
						echo "<input type=\"checkbox\" name=\"weight_limit" . $weights['id'] . "\" id=\"weight_limit" . $weights['id'] . "\" class=\"form-check-input\" style=\"transform: scale(1.2);\" value=\"1\"";
						if (!empty($weights['weight_limit']) && $weights['weight_limit'] == 1) {
							echo " checked";
						}
						echo ">";
						echo "<small class=\"text-muted text-nowrap\">default installed</small>";
						echo "</div>";
					} else {
						echo "<span style=\"display: none;\"><input type=\"hidden\" name=\"weight_limit" . $weights['id'] . "\" value=\"\" id=\"weight_limit" . $weights['id'] . "\"></span>\n";
					}
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"weight" . $weights['id'] . "\" value=\"" . $weights['weight'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\" placeholder=\"" . ($selected_type === 'Fuel' ? 'Qty' : 'Weight') . "\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"arm" . $weights['id'] . "\" value=\"" . $weights['arm'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<div class=\"btn-group btn-group-sm\" role=\"group\">\n";
					echo "<button type=\"button\" class=\"btn btn-outline-primary\" onclick=\"updateLoadingZone(" . $weights['id'] . ", " . $aircraft['id'] . ")\">Update</button>\n";
					echo "<button type=\"button\" class=\"btn btn-outline-danger\" onclick=\"deleteLoadingZone(" . $weights['id'] . ", " . $aircraft['id'] . ")\">Delete</button>\n";
					echo "</div>\n";
					echo "</td>\n";
					echo "</tr>\n";
				}
				
				// Add new loading zone row
				echo "<tr class=\"table-success\">\n";
				echo "<td class=\"text-center\">\n";
				echo "<input type=\"number\" step=\"any\" name=\"new_order\" class=\"form-control form-control-sm text-center\" placeholder=\"#\" style=\"width: 60px; display: inline-block;\">\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<input type=\"text\" name=\"new_item\" class=\"form-control form-control-sm\" placeholder=\"Item name\" style=\"width: 140px; display: inline-block;\">\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<select name=\"new_type\" class=\"form-select form-select-sm\" style=\"width: 220px; display: inline-block;\" onchange=\"toggleNewWeightLimit()\">\n";
				echo "<option value=\"Variable Weight no limit\" selected>Variable Weight no limit</option>\n";
				echo "<option value=\"Empty Weight\">Empty Weight</option>\n";
				echo "<option value=\"Variable Weight with limit\">Variable Weight with limit</option>\n";
				echo "<option value=\"Fixed Weight Removable\">Fixed Weight Removable</option>\n";
				echo "<option value=\"Fuel\">Fuel</option>\n";
				echo "</select>\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<div class=\"align-items-center justify-content-center gap-1\" style=\"display: none;\" id=\"new_limit_container\">";
				echo "<input type=\"number\" step=\"any\" name=\"new_weight_limit\" class=\"form-control form-control-sm text-center\" placeholder=\"Max\" style=\"width: 60px;\" id=\"new_weight_limit\">";
				echo "<small class=\"text-muted text-nowrap\" id=\"new_limit_text\">limit</small>";
				echo "</div>";
				echo "<div class=\"align-items-center justify-content-center gap-1\" style=\"display: none;\" id=\"new_default_container\">";
				echo "<input type=\"checkbox\" name=\"new_default_installed\" class=\"form-check-input\" style=\"transform: scale(1.2);\" value=\"1\" id=\"new_default_checkbox\">";
				echo "<small class=\"text-muted text-nowrap\">default installed</small>";
				echo "</div>";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<input type=\"number\" step=\"any\" name=\"new_weight\" class=\"form-control form-control-sm text-center\" placeholder=\"Weight\" style=\"width: 80px; display: inline-block;\" id=\"new_weight_input\">\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<input type=\"number\" step=\"any\" name=\"new_arm\" class=\"form-control form-control-sm text-center\" placeholder=\"Arm\" style=\"width: 80px; display: inline-block;\">\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<button type=\"button\" class=\"btn btn-success btn-sm\" onclick=\"addLoadingZone(" . $aircraft['id'] . ")\">Add Zone</button>\n";
				echo "</td>\n";
				echo "</tr>\n";
				
				echo "</tbody>\n";
				echo "</table>\n";
				echo "</div>\n";
				echo "</form>\n";
				echo "<script>\n";
				echo "function toggleWeightLimit(id, forceDefaultInstalled) {\n";
				echo "  var typeSelect = document.getElementsByName('type' + id)[0];\n";
				echo "  var weightLimitInput = document.getElementById('weight_limit' + id);\n";
				echo "  var weightLimitCell = weightLimitInput.closest('td');\n";
				echo "  \n";
				echo "  // Handle weight limit input\n";
				echo "  if (typeSelect.value === 'Variable Weight with limit') {\n";
				echo "    var currentValue = weightLimitInput.value || '';\n";
				echo "    weightLimitCell.innerHTML = '<div class=\"d-flex align-items-center justify-content-center gap-1\"><input type=\"number\" step=\"any\" name=\"weight_limit' + id + '\" value=\"' + currentValue + '\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\" id=\"weight_limit' + id + '\" placeholder=\"Max\"><small class=\"text-muted text-nowrap\">" . $aircraft['weight_units'] . " max</small></div>';\n";
				echo "  } else if (typeSelect.value === 'Fuel') {\n";
				echo "    var currentValue = weightLimitInput.value || '';\n";
				echo "    weightLimitCell.innerHTML = '<div class=\"d-flex align-items-center justify-content-center gap-1\"><input type=\"number\" step=\"any\" name=\"weight_limit' + id + '\" value=\"' + currentValue + '\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\" id=\"weight_limit' + id + '\" placeholder=\"Max\"><small class=\"text-muted text-nowrap\">" . $aircraft['fuelunit'] . " max</small></div>';\n";
				echo "  } else if (typeSelect.value === 'Fixed Weight Removable') {\n";
				echo "    var isChecked = '';\n";
				echo "    if (forceDefaultInstalled !== undefined) {\n";
				echo "      isChecked = forceDefaultInstalled ? ' checked' : '';\n";
				echo "    } else {\n";
				echo "      isChecked = (weightLimitInput.value == '1') ? ' checked' : '';\n";
				echo "    }\n";
				echo "    weightLimitCell.innerHTML = '<div class=\"d-flex align-items-center justify-content-center gap-1\"><input type=\"checkbox\" name=\"weight_limit' + id + '\" id=\"weight_limit' + id + '\" class=\"form-check-input\" style=\"transform: scale(1.2);\" value=\"1\"' + isChecked + '><small class=\"text-muted text-nowrap\">default installed</small></div>';\n";
				echo "  } else {\n";
				echo "    weightLimitCell.innerHTML = '<span style=\"display: none;\"><input type=\"hidden\" name=\"weight_limit' + id + '\" value=\"\" id=\"weight_limit' + id + '\"></span>';\n";
				echo "  }\n";
				echo "}\n";
				echo "function toggleNewWeightLimit() {\n";
				echo "  var typeSelect = document.getElementsByName('new_type')[0];\n";
				echo "  var limitContainer = document.getElementById('new_limit_container');\n";
				echo "  var defaultContainer = document.getElementById('new_default_container');\n";
				echo "  var limitText = document.getElementById('new_limit_text');\n";
				echo "  var weightLimitInput = document.getElementById('new_weight_limit');\n";
				echo "  var weightInput = document.getElementById('new_weight_input');\n";
				echo "  \n";
				echo "  // Hide all containers first\n";
				echo "  limitContainer.style.display = 'none';\n";
				echo "  defaultContainer.style.display = 'none';\n";
				echo "  \n";
				echo "  // Update weight placeholder based on type\n";
				echo "  if (weightInput) {\n";
				echo "    if (typeSelect.value === 'Fuel') {\n";
				echo "      weightInput.placeholder = 'Qty';\n";
				echo "    } else {\n";
				echo "      weightInput.placeholder = 'Weight';\n";
				echo "    }\n";
				echo "  }\n";
				echo "  \n";
				echo "  // Show appropriate container based on type\n";
				echo "  if (typeSelect.value === 'Variable Weight with limit') {\n";
				echo "    limitContainer.style.display = 'flex';\n";
				echo "    limitContainer.style.alignItems = 'center';\n";
				echo "    limitContainer.style.justifyContent = 'center';\n";
				echo "    limitContainer.style.gap = '0.25rem';\n";
				echo "    limitText.textContent = '" . $aircraft['weight_units'] . " max';\n";
				echo "  } else if (typeSelect.value === 'Fuel') {\n";
				echo "    limitContainer.style.display = 'flex';\n";
				echo "    limitContainer.style.alignItems = 'center';\n";
				echo "    limitContainer.style.justifyContent = 'center';\n";
				echo "    limitContainer.style.gap = '0.25rem';\n";
				echo "    limitText.textContent = '" . $aircraft['fuelunit'] . " max';\n";
				echo "  } else if (typeSelect.value === 'Fixed Weight Removable') {\n";
				echo "    defaultContainer.style.display = 'flex';\n";
				echo "    defaultContainer.style.alignItems = 'center';\n";
				echo "    defaultContainer.style.justifyContent = 'center';\n";
				echo "    defaultContainer.style.gap = '0.25rem';\n";
				echo "  } else {\n";
				echo "    weightLimitInput.value = '';\n";
				echo "  }\n";
				echo "}\n";
				echo "// Initialize on page load\n";
				echo "document.addEventListener('DOMContentLoaded', function() {\n";
				echo "  toggleNewWeightLimit();\n";
				echo "});\n";
				echo "\n";
				echo "// Ajax functions for CG envelope management\n";
				echo "function updateCGPoint(id, tailnumber, envelope) {\n";
				echo "  var armInput = document.querySelector('input[name=\"cgarm' + id + '\"]');\n";
				echo "  var weightInput = document.querySelector('input[name=\"cgweight' + id + '\"]');\n";
				echo "  \n";
				echo "  if (!armInput || !weightInput) return;\n";
				echo "  \n";
				echo "  var formData = new FormData();\n";
				echo "  formData.append('func', 'aircraft');\n";
				echo "  formData.append('func_do', 'edit_do');\n";
				echo "  formData.append('what', 'cg');\n";
				echo "  formData.append('id', id);\n";
				echo "  formData.append('cgarm', armInput.value);\n";
				echo "  formData.append('cgweight', weightInput.value);\n";
				echo "  formData.append('envelope_name', envelope);\n";
				echo "  formData.append('tailnumber', tailnumber);\n";
				echo "  \n";
				echo "  fetch('admin.php', {\n";
				echo "    method: 'POST',\n";
				echo "    body: formData\n";
				echo "  }).then(response => {\n";
				echo "    if (response.ok) {\n";
				echo "      showSuccessMessage('CG point updated successfully');\n";
				echo "      // Refresh the chart\n";
				echo "      var chartEmbed = document.querySelector('embed[src*=\"scatter.php\"]');\n";
				echo "      if (chartEmbed) {\n";
				echo "        chartEmbed.src = chartEmbed.src.split('?')[0] + '?size=small&tailnumber=' + tailnumber + '&envelope=' + encodeURIComponent(envelope) + '&_t=' + Date.now();\n";
				echo "      }\n";
				echo "    } else {\n";
				echo "      showErrorMessage('Failed to update CG point');\n";
				echo "    }\n";
				echo "  }).catch(error => {\n";
				echo "    showErrorMessage('Error updating CG point: ' + error.message);\n";
				echo "  });\n";
				echo "}\n";
				echo "\n";
				echo "function deleteCGPoint(id, tailnumber) {\n";
				echo "  if (id === undefined || id === null || id === '') {\n";
				echo "    showErrorMessage('Cannot delete: Invalid CG point ID');\n";
				echo "    return;\n";
				echo "  }\n";
				echo "  if (!confirm('Delete this CG point?')) return;\n";
				echo "  \n";
				echo "  var formData = new FormData();\n";
				echo "  formData.append('func', 'aircraft');\n";
				echo "  formData.append('func_do', 'edit_do');\n";
				echo "  formData.append('what', 'cg_del');\n";
				echo "  formData.append('id', id);\n";
				echo "  formData.append('tailnumber', tailnumber);\n";
				echo "  \n";
				echo "  fetch('admin.php', {\n";
				echo "    method: 'POST',\n";
				echo "    headers: {\n";
				echo "      'X-Requested-With': 'XMLHttpRequest'\n";
				echo "    },\n";
				echo "    body: formData\n";
				echo "  }).then(response => response.json()).then(data => {\n";
				echo "    if (data.success) {\n";
				echo "      showSuccessMessage('CG point deleted successfully');\n";
				echo "      // Remove the row from the table by finding all delete buttons and matching the ID\n";
				echo "      var deleteButtons = document.querySelectorAll('button[onclick*=\"deleteCGPoint\"]');\n";
				echo "      for (var i = 0; i < deleteButtons.length; i++) {\n";
				echo "        var onclick = deleteButtons[i].getAttribute('onclick');\n";
				echo "        if (onclick && onclick.includes('deleteCGPoint(' + id + ',')) {\n";
				echo "          deleteButtons[i].closest('tr').remove();\n";
				echo "          break;\n";
				echo "        }\n";
				echo "      }\n";
				echo "      // Refresh the chart\n";
				echo "      var chartEmbed = document.querySelector('embed[src*=\"scatter.php\"]');\n";
				echo "      if (chartEmbed) {\n";
				echo "        chartEmbed.src = chartEmbed.src + '&_t=' + Date.now();\n";
				echo "      }\n";
				echo "    } else {\n";
				echo "      showErrorMessage(data.error || 'Failed to delete CG point');\n";
				echo "    }\n";
				echo "  }).catch(error => {\n";
				echo "    showErrorMessage('Error deleting CG point: ' + error.message);\n";
				echo "  });\n";
				echo "}\n";
				echo "\n";
				echo "function addCGPoint(tailnumber, envelope, envelopeColor) {\n";
				echo "  if (!envelopeColor) envelopeColor = '" . $current_envelope_color . "';\n";
				echo "  \n";
				echo "  var armInput = document.querySelector('input[name=\"new_arm\"]');\n";
				echo "  var weightInput = document.querySelector('input[name=\"new_weight\"]');\n";
				echo "  \n";
				echo "  if (!armInput || !weightInput || !armInput.value || !weightInput.value) {\n";
				echo "    showErrorMessage('Please enter both arm and weight values');\n";
				echo "    return;\n";
				echo "  }\n";
				echo "  \n";
				echo "  var formData = new FormData();\n";
				echo "  formData.append('func', 'aircraft');\n";
				echo "  formData.append('func_do', 'edit_do');\n";
				echo "  formData.append('what', 'cg');\n";
				echo "  formData.append('new_arm', armInput.value);\n";
				echo "  formData.append('new_weight', weightInput.value);\n";
				echo "  formData.append('envelope_name', envelope);\n";
				echo "  formData.append('envelope_color', envelopeColor);\n";
				echo "  formData.append('tailnumber', tailnumber);\n";
				echo "  \n";
				echo "  fetch('admin.php', {\n";
				echo "    method: 'POST',\n";
				echo "    headers: {\n";
				echo "      'X-Requested-With': 'XMLHttpRequest'\n";
				echo "    },\n";
				echo "    body: formData\n";
				echo "  }).then(response => response.json()).then(data => {\n";
				echo "    if (data.success) {\n";
				echo "      showSuccessMessage('CG point added successfully');\n";
				echo "      \n";
				echo "      // Collect the data BEFORE clearing the form\n";
				echo "      var rowData = {\n";
				echo "        id: data.id,\n";
				echo "        arm: armInput.value,\n";
				echo "        weight: weightInput.value\n";
				echo "      };\n";
				echo "      \n";
				echo "      // Clear the input fields\n";
				echo "      armInput.value = '';\n";
				echo "      weightInput.value = '';\n";
				echo "      \n";
				echo "      // Refresh the chart to show the new point\n";
				echo "      var chartEmbed = document.querySelector('embed[src*=\"scatter.php\"]');\n";
				echo "      if (chartEmbed) {\n";
				echo "        chartEmbed.src = chartEmbed.src.split('?')[0] + '?size=small&tailnumber=' + tailnumber + '&envelope=' + encodeURIComponent(envelope) + '&_t=' + Date.now();\n";
				echo "      }\n";
				echo "      \n";
				echo "      // Add the new row to the table dynamically\n";
				echo "      addNewCGRow(rowData, tailnumber, envelope);\n";
				echo "    } else {\n";
				echo "      showErrorMessage(data.error || 'Failed to add CG point');\n";
				echo "    }\n";
				echo "  }).catch(error => {\n";
				echo "    showErrorMessage('Error adding CG point: ' + error.message);\n";
				echo "  });\n";
				echo "}\n";
				echo "\n";
				echo "// Ajax functions for loading zone management\n";
				echo "function updateLoadingZone(id, tailnumber) {\n";
				echo "  var orderInput = document.querySelector('input[name=\"order' + id + '\"]');\n";
				echo "  var itemInput = document.querySelector('input[name=\"item' + id + '\"]');\n";
				echo "  var typeSelect = document.querySelector('select[name=\"type' + id + '\"]');\n";
				echo "  var weightLimitInput = document.querySelector('input[name=\"weight_limit' + id + '\"]');\n";
				echo "  var weightInput = document.querySelector('input[name=\"weight' + id + '\"]');\n";
				echo "  var armInput = document.querySelector('input[name=\"arm' + id + '\"]');\n";
				echo "  \n";
				echo "  if (!orderInput || !itemInput || !typeSelect || !weightInput || !armInput) return;\n";
				echo "  \n";
				echo "  // Validate Empty Weight type uniqueness\n";
				echo "  if (typeSelect.value === 'Empty Weight') {\n";
				echo "    var existingEmptyWeight = false;\n";
				echo "    var typeSelects = document.querySelectorAll('select[name^=\"type\"]');\n";
				echo "    for (var i = 0; i < typeSelects.length; i++) {\n";
				echo "      // Skip the current row being updated and the new_type select\n";
				echo "      if (typeSelects[i].name !== 'new_type' && typeSelects[i].name !== 'type' + id && typeSelects[i].value === 'Empty Weight') {\n";
				echo "        existingEmptyWeight = true;\n";
				echo "        break;\n";
				echo "      }\n";
				echo "    }\n";
				echo "    if (existingEmptyWeight) {\n";
				echo "      showErrorMessage('Empty Weight type can only exist once per aircraft');\n";
				echo "      return;\n";
				echo "    }\n";
				echo "  }\n";
				echo "  \n";
				echo "  var formData = new FormData();\n";
				echo "  formData.append('func', 'aircraft');\n";
				echo "  formData.append('func_do', 'edit_do');\n";
				echo "  formData.append('what', 'loading');\n";
				echo "  formData.append('id', id);\n";
				echo "  formData.append('order', orderInput.value);\n";
				echo "  formData.append('item', itemInput.value);\n";
				echo "  formData.append('type', typeSelect.value);\n";
				echo "  formData.append('weight_limit', weightLimitInput ? weightLimitInput.value : '');\n";
				echo "  formData.append('weight', weightInput.value);\n";
				echo "  formData.append('arm', armInput.value);\n";
				echo "  formData.append('tailnumber', tailnumber);\n";
				echo "  \n";
				echo "  fetch('admin.php', {\n";
				echo "    method: 'POST',\n";
				echo "    body: formData\n";
				echo "  }).then(response => response.json()).then(data => {\n";
				echo "    if (data.success) {\n";
				echo "      showSuccessMessage('Loading zone updated successfully');\n";
				echo "    } else {\n";
				echo "      showErrorMessage(data.error || 'Failed to update loading zone');\n";
				echo "    }\n";
				echo "  }).catch(error => {\n";
				echo "    showErrorMessage('Error updating loading zone: ' + error.message);\n";
				echo "  });\n";
				echo "}\n";
				echo "\n";
				echo "function deleteLoadingZone(id, tailnumber) {\n";
				echo "  if (!confirm('Delete this loading zone?')) return;\n";
				echo "  \n";
				echo "  var formData = new FormData();\n";
				echo "  formData.append('func', 'aircraft');\n";
				echo "  formData.append('func_do', 'edit_do');\n";
				echo "  formData.append('what', 'loading_del');\n";
				echo "  formData.append('id', id);\n";
				echo "  formData.append('tailnumber', tailnumber);\n";
				echo "  \n";
				echo "  fetch('admin.php', {\n";
				echo "    method: 'POST',\n";
				echo "    body: formData\n";
				echo "  }).then(response => {\n";
				echo "    if (response.ok) {\n";
				echo "      showSuccessMessage('Loading zone deleted successfully');\n";
				echo "      // Remove the row from the table\n";
				echo "      var button = document.querySelector('button[onclick*=\"deleteLoadingZone(' + id + '\"]');\n";
				echo "      if (button) {\n";
				echo "        button.closest('tr').remove();\n";
				echo "      }\n";
				echo "    } else {\n";
				echo "      showErrorMessage('Failed to delete loading zone');\n";
				echo "    }\n";
				echo "  }).catch(error => {\n";
				echo "    showErrorMessage('Error deleting loading zone: ' + error.message);\n";
				echo "  });\n";
				echo "}\n";
				echo "\n";
				echo "function addLoadingZone(tailnumber) {\n";
				echo "  // Use querySelectorAll to find all inputs and select the one with a value\n";
				echo "  function getActiveInput(name) {\n";
				echo "    var inputs = document.querySelectorAll('input[name=\"' + name + '\"], select[name=\"' + name + '\"]');\n";
				echo "    for (var i = 0; i < inputs.length; i++) {\n";
				echo "      if (inputs[i].value && inputs[i].value.trim() !== '') {\n";
				echo "        return inputs[i];\n";
				echo "      }\n";
				echo "    }\n";
				echo "    // If no input with value found, return the last one (most likely the visible form)\n";
				echo "    return inputs.length > 0 ? inputs[inputs.length - 1] : null;\n";
				echo "  }\n";
				echo "  \n";
				echo "  // Get form inputs, preferring ones with values\n";
				echo "  var orderInputs = document.querySelectorAll('input[name=\"new_order\"]');\n";
				echo "  var orderInput = orderInputs.length > 0 ? orderInputs[orderInputs.length - 1] : null;\n";
				echo "  \n";
				echo "  var itemInput = getActiveInput('new_item');\n";
				echo "  var armInput = getActiveInput('new_arm');\n";
				echo "  \n";
				echo "  var typeSelects = document.querySelectorAll('select[name=\"new_type\"]');\n";
				echo "  var typeSelect = typeSelects.length > 0 ? typeSelects[typeSelects.length - 1] : null;\n";
				echo "  \n";
				echo "  var weightInputs = document.querySelectorAll('input[name=\"new_weight\"]');\n";
				echo "  var weightInput = weightInputs.length > 0 ? weightInputs[weightInputs.length - 1] : null;\n";
				echo "  \n";
				echo "  var weightLimitInput = document.querySelector('#new_weight_limit');\n";
				echo "  var defaultInstalledInput = document.querySelector('#new_default_checkbox');\n";
				echo "  \n";
				echo "  if (!itemInput || !itemInput.value || !itemInput.value.trim()) {\n";
				echo "    showErrorMessage('Please enter an item name');\n";
				echo "    return;\n";
				echo "  }\n";
				echo "  if (!armInput || !armInput.value || !armInput.value.trim()) {\n";
				echo "    showErrorMessage('Please enter an arm value');\n";
				echo "    return;\n";
				echo "  }\n";
				echo "  \n";
				echo "  // Type-specific validation\n";
				echo "  var selectedType = typeSelect ? typeSelect.value : 'Variable Weight no limit';\n";
				echo "  \n";
				echo "  if (selectedType === 'Empty Weight') {\n";
				echo "    // Check if Empty Weight already exists\n";
				echo "    var existingEmptyWeight = false;\n";
				echo "    var typeSelects = document.querySelectorAll('select[name^=\"type\"]');\n";
				echo "    for (var i = 0; i < typeSelects.length; i++) {\n";
				echo "      if (typeSelects[i].name !== 'new_type' && typeSelects[i].value === 'Empty Weight') {\n";
				echo "        existingEmptyWeight = true;\n";
				echo "        break;\n";
				echo "      }\n";
				echo "    }\n";
				echo "    if (existingEmptyWeight) {\n";
				echo "      showErrorMessage('Empty Weight type can only exist once per aircraft');\n";
				echo "      return;\n";
				echo "    }\n";
				echo "    if (!weightInput || !weightInput.value || !weightInput.value.trim()) {\n";
				echo "      showErrorMessage('Empty Weight type requires a weight value');\n";
				echo "      return;\n";
				echo "    }\n";
				echo "  } else if (selectedType === 'Variable Weight with limit') {\n";
				echo "    if (!weightLimitInput || !weightLimitInput.value || !weightLimitInput.value.trim()) {\n";
				echo "      showErrorMessage('Variable Weight with limit type requires a max value');\n";
				echo "      return;\n";
				echo "    }\n";
				echo "  } else if (selectedType === 'Fixed Weight Removable') {\n";
				echo "    if (!weightInput || !weightInput.value || !weightInput.value.trim()) {\n";
				echo "      showErrorMessage('Fixed Weight Removable type requires a weight value');\n";
				echo "      return;\n";
				echo "    }\n";
				echo "  } else if (selectedType === 'Fuel') {\n";
				echo "    if (!weightLimitInput || !weightLimitInput.value || !weightLimitInput.value.trim()) {\n";
				echo "      showErrorMessage('Fuel type requires a max value');\n";
				echo "      return;\n";
				echo "    }\n";
				echo "  }\n";
				echo "  \n";
				echo "  var formData = new FormData();\n";
				echo "  formData.append('func', 'aircraft');\n";
				echo "  formData.append('func_do', 'edit_do');\n";
				echo "  formData.append('what', 'loading');\n";
				echo "  formData.append('new_order', orderInput ? orderInput.value : '');\n";
				echo "  formData.append('new_item', itemInput.value);\n";
				echo "  formData.append('new_type', typeSelect ? typeSelect.value : 'Variable Weight no limit');\n";
				echo "  formData.append('new_weight_limit', weightLimitInput ? weightLimitInput.value : '');\n";
				echo "  formData.append('new_default_installed', defaultInstalledInput && defaultInstalledInput.checked ? '1' : '0');\n";
				echo "  formData.append('new_weight', weightInput ? weightInput.value : '0');\n";
				echo "  formData.append('new_arm', armInput.value);\n";
				echo "  formData.append('tailnumber', tailnumber);\n";
				echo "  \n";
				echo "  fetch('admin.php', {\n";
				echo "    method: 'POST',\n";
				echo "    headers: {\n";
				echo "      'X-Requested-With': 'XMLHttpRequest'\n";
				echo "    },\n";
				echo "    body: formData\n";
				echo "  }).then(response => response.json()).then(data => {\n";
				echo "    if (data.success) {\n";
				echo "      showSuccessMessage('Loading zone added successfully');\n";
				echo "      \n";
				echo "      // Collect the data BEFORE clearing the form\n";
				echo "      var rowData = {\n";
				echo "        item: itemInput.value,\n";
				echo "        type: typeSelect ? typeSelect.value : 'Variable Weight no limit',\n";
				echo "        weight_limit: weightLimitInput ? weightLimitInput.value : '',\n";
				echo "        default_installed: defaultInstalledInput && defaultInstalledInput.checked,\n";
				echo "        weight: weightInput ? weightInput.value : '0',\n";
				echo "        arm: armInput.value,\n";
				echo "        order: orderInput ? orderInput.value : ''\n";
				echo "      };\n";
				echo "      \n";
				echo "      // Clear the input fields\n";
				echo "      if (orderInput) orderInput.value = '';\n";
				echo "      itemInput.value = '';\n";
				echo "      if (typeSelect) typeSelect.selectedIndex = 0;\n";
				echo "      if (weightLimitInput) weightLimitInput.value = '';\n";
				echo "      if (defaultInstalledInput) defaultInstalledInput.checked = false;\n";
				echo "      if (weightInput) weightInput.value = '';\n";
				echo "      armInput.value = '';\n";
				echo "      // Reset any visible weight limit containers\n";
				echo "      var limitContainer = document.getElementById('new_limit_container');\n";
				echo "      var defaultContainer = document.getElementById('new_default_container');\n";
				echo "      if (limitContainer) limitContainer.style.display = 'none';\n";
				echo "      if (defaultContainer) defaultContainer.style.display = 'none';\n";
				echo "      \n";
				echo "      // Add the new row to the table dynamically with real database ID\n";
				echo "      rowData.id = data.id;  // Add the real database ID from server response\n";
				echo "      addNewLoadingZoneRow(rowData, tailnumber);\n";
				echo "    } else {\n";
				echo "      showErrorMessage('Failed to add loading zone');\n";
				echo "    }\n";
				echo "  }).catch(error => {\n";
				echo "    showErrorMessage('Error adding loading zone: ' + error.message);\n";
				echo "  });\n";
				echo "}\n";
				echo "\n";
				echo "// Helper functions for showing messages\n";
				echo "function showSuccessMessage(message) {\n";
				echo "  var alertDiv = document.createElement('div');\n";
				echo "  alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';\n";
				echo "  alertDiv.style.top = '20px';\n";
				echo "  alertDiv.style.right = '20px';\n";
				echo "  alertDiv.style.zIndex = '9999';\n";
				echo "  alertDiv.innerHTML = message + '<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>';\n";
				echo "  document.body.appendChild(alertDiv);\n";
				echo "  setTimeout(() => alertDiv.remove(), 3000);\n";
				echo "}\n";
				echo "\n";
				echo "function showErrorMessage(message) {\n";
				echo "  var alertDiv = document.createElement('div');\n";
				echo "  alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';\n";
				echo "  alertDiv.style.top = '20px';\n";
				echo "  alertDiv.style.right = '20px';\n";
				echo "  alertDiv.style.zIndex = '9999';\n";
				echo "  alertDiv.innerHTML = message + '<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>';\n";
				echo "  document.body.appendChild(alertDiv);\n";
				echo "  setTimeout(() => alertDiv.remove(), 5000);\n";
				echo "}\n";
				echo "\n";
				echo "// Function to add a new loading zone row to the table\n";
				echo "function addNewLoadingZoneRow(data, tailnumber) {\n";
				echo "  var table = document.querySelector('form[name=\"loading\"] tbody');\n";
				echo "  if (!table) return;\n";
				echo "  \n";
				echo "  // Use the real database ID from the server response\n";
				echo "  var dbId = data.id;\n";
				echo "  \n";
				echo "  // Find the 'Add Zone' row to insert before it\n";
				echo "  var addRow = table.querySelector('tr.table-success');\n";
				echo "  if (!addRow) return;\n";
				echo "  \n";
				echo "  // Create the new row HTML\n";
				echo "  var newRow = document.createElement('tr');\n";
				echo "  newRow.innerHTML = \n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<input type=\"number\" name=\"order' + dbId + '\" value=\"' + (data.order || '') + '\" class=\"form-control form-control-sm text-center\" style=\"width: 60px; display: inline-block;\">' +\n";
				echo "    '</td>' +\n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<input type=\"text\" name=\"item' + dbId + '\" value=\"' + data.item + '\" class=\"form-control form-control-sm\" style=\"width: 140px; display: inline-block;\">' +\n";
				echo "    '</td>' +\n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<select name=\"type' + dbId + '\" class=\"form-select form-select-sm\" style=\"width: 220px; display: inline-block;\" onchange=\"toggleWeightLimit(' + dbId + ')\">' +\n";
				echo "        '<option value=\"Empty Weight\"' + (data.type === 'Empty Weight' ? ' selected' : '') + '>Empty Weight</option>' +\n";
				echo "        '<option value=\"Variable Weight no limit\"' + (data.type === 'Variable Weight no limit' ? ' selected' : '') + '>Variable Weight no limit</option>' +\n";
				echo "        '<option value=\"Variable Weight with limit\"' + (data.type === 'Variable Weight with limit' ? ' selected' : '') + '>Variable Weight with limit</option>' +\n";
				echo "        '<option value=\"Fixed Weight Removable\"' + (data.type === 'Fixed Weight Removable' ? ' selected' : '') + '>Fixed Weight Removable</option>' +\n";
				echo "        '<option value=\"Fuel\"' + (data.type === 'Fuel' ? ' selected' : '') + '>Fuel</option>' +\n";
				echo "      '</select>' +\n";
				echo "    '</td>' +\n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<div class=\"d-flex align-items-center justify-content-center gap-1\" style=\"display: none;\" id=\"weight_limit_container_' + dbId + '\">' +\n";
				echo "        '<input type=\"number\" step=\"any\" name=\"weight_limit' + dbId + '\" value=\"' + (data.weight_limit || '') + '\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\" id=\"weight_limit' + dbId + '\" placeholder=\"Max\">' +\n";
				echo "        '<small class=\"text-muted text-nowrap\" id=\"weight_limit_text_' + dbId + '\">limit</small>' +\n";
				echo "      '</div>' +\n";
				echo "      '<div class=\"d-flex align-items-center justify-content-center gap-1\" style=\"display: none;\" id=\"default_installed_container_' + dbId + '\">' +\n";
				echo "        '<input type=\"checkbox\" name=\"weight_limit' + dbId + '\" class=\"form-check-input\" style=\"transform: scale(1.2);\" value=\"1\" id=\"default_installed_' + dbId + '\"' + (data.default_installed ? ' checked' : '') + '>' +\n";
				echo "        '<small class=\"text-muted text-nowrap\">default installed</small>' +\n";
				echo "      '</div>' +\n";
				echo "      '<span style=\"display: none;\" id=\"hidden_weight_limit_' + dbId + '\">' +\n";
				echo "        '<input type=\"hidden\" name=\"weight_limit' + dbId + '\" value=\"\" id=\"weight_limit_hidden_' + dbId + '\">' +\n";
				echo "      '</span>' +\n";
				echo "    '</td>' +\n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<input type=\"number\" step=\"any\" name=\"weight' + dbId + '\" value=\"' + data.weight + '\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\" placeholder=\"' + (data.type === 'Fuel' ? 'Qty' : 'Weight') + '\">' +\n";
				echo "    '</td>' +\n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<input type=\"number\" step=\"any\" name=\"arm' + dbId + '\" value=\"' + data.arm + '\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\">' +\n";
				echo "    '</td>' +\n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<div class=\"btn-group btn-group-sm\" role=\"group\">' +\n";
				echo "        '<button type=\"button\" class=\"btn btn-outline-primary\" onclick=\"updateLoadingZone(' + data.id + ', ' + tailnumber + ')\">Update</button>' +\n";
				echo "        '<button type=\"button\" class=\"btn btn-outline-danger\" onclick=\"deleteLoadingZone(' + data.id + ', ' + tailnumber + ')\">Delete</button>' +\n";
				echo "      '</div>' +\n";
				echo "    '</td>';\n";
				echo "  \n";
				echo "  // Insert the new row before the add row\n";
				echo "  table.insertBefore(newRow, addRow);\n";
				echo "  \n";
				echo "  // Initialize the weight limit fields based on the selected type\n";
				echo "  setTimeout(function() {\n";
				echo "    if (typeof toggleWeightLimit === 'function') {\n";
				echo "      toggleWeightLimit(dbId, data.default_installed);\n";
				echo "    }\n";
				echo "  }, 100);\n";
				echo "}\n";
				echo "\n";
				echo "// Function to add a new CG point row to the table\n";
				echo "function addNewCGRow(data, tailnumber, envelope) {\n";
				echo "  var table = document.querySelector('form[name=\"cg\"] tbody');\n";
				echo "  if (!table) return;\n";
				echo "  \n";
				echo "  // Use the real database ID from the server response\n";
				echo "  var dbId = data.id;\n";
				echo "  \n";
				echo "  // Find the 'Add Point' row to insert before it\n";
				echo "  var addRow = table.querySelector('tr.table-success');\n";
				echo "  if (!addRow) return;\n";
				echo "  \n";
				echo "  // Create the new row HTML with proper input fields and action buttons\n";
				echo "  var newRow = document.createElement('tr');\n";
				echo "  newRow.innerHTML = \n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<input type=\"number\" step=\"any\" name=\"cgarm' + dbId + '\" value=\"' + data.arm + '\" class=\"form-control form-control-sm text-center\" style=\"width: 100px; display: inline-block;\">' +\n";
				echo "    '</td>' +\n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<input type=\"number\" step=\"any\" name=\"cgweight' + dbId + '\" value=\"' + data.weight + '\" class=\"form-control form-control-sm text-center\" style=\"width: 100px; display: inline-block;\">' +\n";
				echo "    '</td>' +\n";
				echo "    '<td class=\"text-center\">' +\n";
				echo "      '<div class=\"btn-group btn-group-sm\" role=\"group\">' +\n";
				echo "        '<button type=\"button\" class=\"btn btn-outline-primary\" onclick=\"updateCGPoint(' + dbId + ', ' + tailnumber + ', \\'' + envelope + '\\')\">Update</button>' +\n";
				echo "        '<button type=\"button\" class=\"btn btn-outline-danger\" onclick=\"deleteCGPoint(' + dbId + ', ' + tailnumber + ')\">Delete</button>' +\n";
				echo "      '</div>' +\n";
				echo "    '</td>';\n";
				echo "  \n";
				echo "  // Insert the new row before the add row\n";
				echo "  table.insertBefore(newRow, addRow);\n";
				echo "}\n";
				echo "</script>\n";
				echo "</div>\n";
				echo "</div>\n";

			} else {
				echo "<div class=\"row justify-content-center\">\n";
				echo "<div class=\"col-lg-6 col-md-8\">\n";
				echo "<div class=\"card\">\n";
				echo "<div class=\"card-header\">\n";
				echo "<h5 class=\"card-title mb-0\">Edit Aircraft</h5>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<p class=\"mb-4\">Choose an aircraft to modify its configuration, CG envelope, and loading zones.</p>\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit\">\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"tailnumber\" class=\"form-label\">Select Aircraft to Edit</label>\n";
				AircraftListAll();
				echo "</div>\n";
				echo "<div class=\"d-grid gap-2 d-md-flex justify-content-md-end\">\n";
				echo "<a href=\"admin.php?func=aircraft\" class=\"btn btn-outline-secondary me-md-2\">Cancel</a>\n";
				echo "<button type=\"submit\" class=\"btn btn-primary\">Edit Aircraft</button>\n";
				echo "</div>\n";
				echo "</form>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
			}
			break;
		case "edit_do":
			switch ($_REQUEST["what"]) {
				case "basics":
					// SQL query to edit basic aircraft information
					$active = isset($_REQUEST['active']) && $_REQUEST['active'] != '' ? $_REQUEST['active'] : '0';
					$mlw_value = isset($_REQUEST['max_landing_weight']) && $_REQUEST['max_landing_weight'] !== '' ? $_REQUEST['max_landing_weight'] : null;
					$sql_query = "UPDATE aircraft SET active = ?, tailnumber = ?, makemodel = ?, emptywt = ?, emptycg = ?, maxwt = ?, fuelunit = ?, weight_units = ?, arm_units = ?, fuel_type = ?, max_landing_weight = ? WHERE id = ?";
					$db->query($sql_query, [$active, $_REQUEST['tailnumber'], $_REQUEST['makemodel'], $_REQUEST['emptywt'], $_REQUEST['emptycg'], $_REQUEST['maxwt'], $_REQUEST['fuelunit'], $_REQUEST['weight_units'], $_REQUEST['arm_units'], $_REQUEST['fuel_type'], $mlw_value, $_REQUEST['id']]);
					// Enter in the audit log
					$audit_data = [
						'active' => $active,
						'tailnumber' => $_REQUEST['tailnumber'],
						'makemodel' => $_REQUEST['makemodel'],
						'emptywt' => $_REQUEST['emptywt'],
						'emptycg' => $_REQUEST['emptycg'],
						'maxwt' => $_REQUEST['maxwt'],
						'fuelunit' => $_REQUEST['fuelunit'],
						'weight_units' => $_REQUEST['weight_units'],
						'fuel_type' => $_REQUEST['fuel_type'],
						'max_landing_weight' => $mlw_value
					];
					$audit_message = createAuditMessage("Updated aircraft basics", $audit_data);
					$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $_REQUEST['tailnumber'] . ': ' . $audit_message]);
					header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['id'] . '&message=updated');
					break;
				case "cg":
					$envelope_name = isset($_REQUEST['envelope_name']) ? $_REQUEST['envelope_name'] : 'Normal';
					if ($_REQUEST['new_arm'] != "" && $_REQUEST['new_weight'] != "") {
						// Check if this envelope already exists to get its color, or use provided color, or use default
						$color_query = $db->query("SELECT DISTINCT color FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ? LIMIT 1", [$_REQUEST['tailnumber'], $envelope_name]);
						$color_result = $db->fetchAssoc($color_query);
						
						// Use existing color if found, otherwise use provided envelope_color, otherwise default to blue
						if ($color_result) {
							$envelope_color = $color_result['color'];
						} elseif (isset($_REQUEST['envelope_color']) && !empty($_REQUEST['envelope_color'])) {
							$envelope_color = $_REQUEST['envelope_color'];
						} else {
							$envelope_color = 'blue';
						}
						
						// SQL query to add a new CG line
						$sql_query = "INSERT INTO aircraft_cg (`id`, `tailnumber`, `arm`, `weight`, `envelope_name`, `color`) VALUES (NULL, ?, ?, ?, ?, ?)";
						$db->query($sql_query, [$_REQUEST['tailnumber'], $_REQUEST['new_arm'], $_REQUEST['new_weight'], $envelope_name, $envelope_color]);
						
						// Get the ID of the newly inserted row
						$new_cg_id = $db->lastInsertId();
						
						// Enter in the audit log
						$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
						$aircraft = $db->fetchAssoc($aircraft_query);
						$audit_data = ['arm' => $_REQUEST['new_arm'], 'weight' => $_REQUEST['new_weight'], 'envelope' => $envelope_name];
						$audit_message = createAuditMessage("Added CG envelope point", $audit_data);
						$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ': ' . $audit_message]);
						
						// Check if this is an Ajax request (from our JavaScript)
						$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
						if ($is_ajax) {
							// Return JSON response for Ajax requests
							header('Content-Type: application/json');
							echo json_encode([
								'success' => true,
								'id' => $new_cg_id,
								'arm' => $_REQUEST['new_arm'],
								'weight' => $_REQUEST['new_weight'],
								'envelope_name' => $envelope_name
							]);
							exit;
						} else {
							// Traditional redirect for non-Ajax requests
							$redirect_url = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&envelope=' . urlencode($envelope_name) . '&message=updated';
							header('Location: ' . $redirect_url);
						}
					} else {
						// SQL query to edit CG information
						$sql_query = "UPDATE aircraft_cg SET arm = ?, weight = ? WHERE id = ?";
						$db->query($sql_query, [$_REQUEST['cgarm'], $_REQUEST['cgweight'], $_REQUEST['id']]);
						// Enter in the audit log
						$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
						$aircraft = $db->fetchAssoc($aircraft_query);
						$audit_data = ['arm' => $_REQUEST['cgarm'], 'weight' => $_REQUEST['cgweight'], 'cg_id' => $_REQUEST['id'], 'envelope' => $envelope_name];
						$audit_message = createAuditMessage("Updated CG envelope point", $audit_data);
						$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ': ' . $audit_message]);
						$redirect_url = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&envelope=' . urlencode($envelope_name) . '&message=updated';
						header('Location: ' . $redirect_url);
					}
					break;
				case "envelope_delete":
					// Delete entire envelope
					$envelope_name = $_REQUEST['envelope_name'];
					
					// Get envelope info before deletion for audit log
					$envelope_query = $db->query("SELECT COUNT(*) as point_count FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ?", [$_REQUEST['tailnumber'], $envelope_name]);
					$envelope_info = $db->fetchAssoc($envelope_query);
					
					// Delete all points for this envelope
					$sql_query = "DELETE FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ?";
					$db->query($sql_query, [$_REQUEST['tailnumber'], $envelope_name]);
					
					// Enter in the audit log
					$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
					$aircraft = $db->fetchAssoc($aircraft_query);
					$audit_data = ['envelope_name' => $envelope_name, 'points_deleted' => $envelope_info['point_count']];
					$audit_message = createAuditMessage("Deleted CG envelope", $audit_data);
					$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ': ' . $audit_message]);
					
					header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					break;
				case "envelope_edit":
					// Edit envelope name and/or color
					$old_envelope_name = $_REQUEST['old_envelope_name'];
					$new_envelope_name = trim($_REQUEST['envelope_name']);
					$new_envelope_color = $_REQUEST['envelope_color'];
					
					if (!empty($new_envelope_name)) {
						// Check if the new name conflicts with another envelope (unless it's the same name)
						if ($old_envelope_name !== $new_envelope_name) {
							$check_query = $db->query("SELECT COUNT(*) as count FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ?", [$_REQUEST['tailnumber'], $new_envelope_name]);
							$check_result = $db->fetchAssoc($check_query);
							
							if ($check_result['count'] > 0) {
								// Name conflict - redirect with error (could be enhanced with error message)
								header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&envelope=' . urlencode($old_envelope_name) . '&error=name_conflict');
								break;
							}
						}
						
						// Update all CG points for this envelope with new name and color
						$sql_query = "UPDATE aircraft_cg SET envelope_name = ?, color = ? WHERE tailnumber = ? AND envelope_name = ?";
						$db->query($sql_query, [$new_envelope_name, $new_envelope_color, $_REQUEST['tailnumber'], $old_envelope_name]);
						
						// Enter in the audit log
						$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
						$aircraft = $db->fetchAssoc($aircraft_query);
						$audit_data = [
							'old_name' => $old_envelope_name,
							'new_name' => $new_envelope_name,
							'new_color' => $new_envelope_color
						];
						$audit_message = createAuditMessage("Updated CG envelope", $audit_data);
						$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ': ' . $audit_message]);
						
						$redirect_url = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&envelope=' . urlencode($new_envelope_name) . '&message=updated';
						header('Location: ' . $redirect_url);
					}
					break;
				case "cg_del":
					// Get CG info before deletion for audit log
					$cg_query = $db->query("SELECT * FROM aircraft_cg WHERE id = ?", [$_REQUEST['id']]);
					$cg_info = $db->fetchAssoc($cg_query);
					
					// SQL query to delete CG information
					$sql_query = "DELETE FROM aircraft_cg WHERE id = ?";
					$db->query($sql_query, [$_REQUEST['id']]);
					// Enter in the audit log
					$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
					$aircraft = $db->fetchAssoc($aircraft_query);
					$audit_data = [
						'cg_id' => $_REQUEST['id'],
						'arm' => $cg_info['arm'],
						'weight' => $cg_info['weight']
					];
					$audit_message = createAuditMessage("Deleted CG envelope point", $audit_data);
					$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ': ' . $audit_message]);
					header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					break;
				case "loading":
					if (isset($_REQUEST['new_item']) && $_REQUEST['new_item'] && isset($_REQUEST['new_arm']) && $_REQUEST['new_arm'] != "") {
						// Set order to highest existing order + 1 if not specified
						$order = $_REQUEST['new_order'];
						if (empty($order)) {
							$max_order_query = $db->query("SELECT MAX(`order`) as max_order FROM aircraft_weights WHERE tailnumber = ?", [$_REQUEST['tailnumber']]);
							$max_order_result = $db->fetchAssoc($max_order_query);
							$order = ($max_order_result['max_order'] ?? 0) + 1;
						}
						
						// SQL query to add a new loading line with new type system
						$type = $_REQUEST['new_type'] ?? 'Variable Weight no limit';
						if ($type === 'Variable Weight with limit' || $type === 'Fuel') {
							$weight_limit = !empty($_REQUEST['new_weight_limit']) ? $_REQUEST['new_weight_limit'] : null;
						} elseif ($type === 'Fixed Weight Removable') {
							$weight_limit = !empty($_REQUEST['new_default_installed']) ? 1 : 0;
						} else {
							$weight_limit = null;
						}
						
						// Validate weight against limit (only for Variable Weight with limit and Fuel types)
						if (($type === 'Variable Weight with limit' || $type === 'Fuel') && $weight_limit !== null && $_REQUEST['new_weight'] > $weight_limit) {
							echo "<div class='alert alert-danger'>Error: Weight (" . $_REQUEST['new_weight'] . ") cannot exceed weight limit (" . $weight_limit . ").</div>";
							break;
						}
						
						$params = [null, $_REQUEST['tailnumber'], $order, $_REQUEST['new_item'], $_REQUEST['new_weight'], $_REQUEST['new_arm'], 0, $type, $weight_limit];
						$sql_query = "INSERT INTO aircraft_weights (`id`, `tailnumber`, `order`, `item`, `weight`, `arm`, `fuelwt`, `type`, `weight_limit`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
						$db->query($sql_query, $params);
						
						// Get the ID of the newly inserted row
						$new_weight_id = $db->lastInsertId();
						
						// Enter in the audit log
						$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
						$aircraft = $db->fetchAssoc($aircraft_query);
						$audit_data = [
							'order' => $order,
							'item' => $_REQUEST['new_item'],
							'weight' => $_REQUEST['new_weight'],
							'arm' => $_REQUEST['new_arm'],
							'type' => $type
						];
						if ($weight_limit !== null) {
							$audit_data['weight_limit'] = $weight_limit;
						}
						$audit_message = createAuditMessage("Added aircraft loading item", $audit_data);
						$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ': ' . $audit_message]);
						
						// Check if this is an Ajax request (from our JavaScript)
						$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
						if ($is_ajax) {
							// Return JSON response for Ajax requests
							header('Content-Type: application/json');
							echo json_encode([
								'success' => true,
								'id' => $new_weight_id,
								'order' => $order,
								'item' => $_REQUEST['new_item'],
								'weight' => $_REQUEST['new_weight'],
								'arm' => $_REQUEST['new_arm'],
								'type' => $type,
								'weight_limit' => $weight_limit
							]);
							exit;
						} else {
							// Traditional redirect for non-Ajax requests
							header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
						}
					} else {
						// SQL query to edit loading zones with new type system
						$type = $_REQUEST['type'] ?? 'Variable Weight no limit';
						if ($type === 'Variable Weight with limit' || $type === 'Fuel') {
							$weight_limit = !empty($_REQUEST['weight_limit']) ? $_REQUEST['weight_limit'] : null;
						} elseif ($type === 'Fixed Weight Removable') {
							$weight_limit = !empty($_REQUEST['weight_limit']) ? 1 : 0;
						} else {
							$weight_limit = null;
						}
						
						// Validate weight against limit (only for Variable Weight with limit and Fuel types)
						if (($type === 'Variable Weight with limit' || $type === 'Fuel') && $weight_limit !== null && $_REQUEST['weight'] > $weight_limit) {
							echo "<div class='alert alert-danger'>Error: Weight (" . $_REQUEST['weight'] . ") cannot exceed weight limit (" . $weight_limit . ").</div>";
							break;
						}
						
						$params = [$_REQUEST['order'], $_REQUEST['item'], $_REQUEST['weight'], $_REQUEST['arm'], 0, $type, $weight_limit, $_REQUEST['id']];
						$sql_query = "UPDATE aircraft_weights SET `order` = ?, `item` = ?, `weight` = ?, `arm` = ?, `fuelwt` = ?, `type` = ?, `weight_limit` = ? WHERE id = ?";

						$db->query($sql_query, $params);
						// Enter in the audit log
						$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
						$aircraft = $db->fetchAssoc($aircraft_query);
						$audit_data = [
							'weight_id' => $_REQUEST['id'],
							'order' => $_REQUEST['order'],
							'item' => $_REQUEST['item'],
							'weight' => $_REQUEST['weight'],
							'arm' => $_REQUEST['arm'],
							'type' => $type
						];
						if ($weight_limit !== null) {
							$audit_data['weight_limit'] = $weight_limit;
						}
						$audit_message = createAuditMessage("Updated aircraft loading item", $audit_data);
						$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ': ' . $audit_message]);
						header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					}
					break;
				case "loading_del":
					// Get loading info before deletion for audit log
					$loading_query = $db->query("SELECT * FROM aircraft_weights WHERE id = ?", [$_REQUEST['id']]);
					$loading_info = $db->fetchAssoc($loading_query);
					
					// SQL query to delete loading information
					$sql_query = "DELETE FROM aircraft_weights WHERE id = ?";
					$db->query($sql_query, [$_REQUEST['id']]);
					// Enter in the audit log
					$aircraft_query = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
					$aircraft = $db->fetchAssoc($aircraft_query);
					$audit_data = [
						'weight_id' => $_REQUEST['id'],
						'item' => $loading_info['item'],
						'weight' => $loading_info['weight'],
						'arm' => $loading_info['arm'],
						'order' => $loading_info['order']
					];
					$audit_message = createAuditMessage("Deleted aircraft loading item", $audit_data);
					$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, $aircraft['tailnumber'] . ': ' . $audit_message]);
					header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
			}

			break;
		default:
		        echo "<p>This module edits aircraft weight and balance templates.</p>\n";
		        echo "<a href=\"admin.php?func=aircraft&amp;func_do=add\">Add Aicraft</a><br>\n";
		        echo "<a href=\"admin.php?func=aircraft&amp;func_do=edit\">Edit Aicraft</a><br>\n";
		        echo "<a href=\"admin.php?func=aircraft&amp;func_do=duplicate\">Duplicate Aicraft</a><br>\n";
		        echo "<a href=\"admin.php?func=aircraft&amp;func_do=delete\">Delete Aicraft</a>\n";
	}
        break;

    case "users":
        echo "<h3 class=\"text-primary mb-3\">Users Module</h3>";
        if (isset($_REQUEST['message']) && $_REQUEST['message']=="added") { echo "<p style=\"color: #00AA00; text-align: center;\">User account added.</p>\n\n";
        } elseif (isset($_REQUEST['message']) && $_REQUEST['message']=="edited") { echo "<p style=\"color: #00AA00; text-align: center;\">User account edited.</p>\n\n";
        } elseif (isset($_REQUEST['message']) && $_REQUEST['message']=="deleted") { echo "<p style=\"color: #00AA00; text-align: center;\">User account deleted.</p>\n\n"; }
	switch (isset($_REQUEST["func_do"]) ? $_REQUEST["func_do"] : "") {
		case "add":
			if (isset($_REQUEST['what']) && $_REQUEST['what']=="Add") {
				// SQL query to add a new user
				$sql_query = "INSERT INTO users (`username`, `password`, `name`, `email`, `superuser`) VALUES (?, ?, ?, ?, ?)";
				$db->query($sql_query, [$_REQUEST['username'], password_hash($_REQUEST['password'], PASSWORD_DEFAULT), $_REQUEST['name'], $_REQUEST['email'], $_REQUEST['superuser']]);
				// Enter in the audit log
				$audit_data = [
					'username' => $_REQUEST['username'],
					'name' => $_REQUEST['name'],
					'email' => $_REQUEST['email'],
					'superuser' => $_REQUEST['superuser']
				];
				$audit_message = createAuditMessage("Created new user", $audit_data);
				$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, 'USERS: ' . $audit_message]);
				header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=users&message=added');
			} else {
				echo "<div class=\"row justify-content-center\">\n";
				echo "<div class=\"col-lg-8 col-md-10\">\n";
				echo "<div class=\"card\">\n";
				echo "<div class=\"card-header\">\n";
				echo "<h5 class=\"card-title mb-0\">Add New User</h5>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"users\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"add\">\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"username\" class=\"form-label\">Username</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"username\" name=\"username\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"password\" class=\"form-label\">Password</label>\n";
				echo "<input type=\"password\" class=\"form-control\" id=\"password\" name=\"password\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"name\" class=\"form-label\">Full Name</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"name\" name=\"name\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"email\" class=\"form-label\">Email Address</label>\n";
				echo "<input type=\"email\" class=\"form-control\" id=\"email\" name=\"email\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-4\">\n";
				echo "<div class=\"form-check\">\n";
				echo "<input type=\"checkbox\" class=\"form-check-input\" id=\"superuser\" name=\"superuser\" value=\"1\">\n";
				echo "<label class=\"form-check-label\" for=\"superuser\" title=\"Administrative users can edit system settings, all users, and view the audit log.\">\n";
				echo "Administrator User\n";
				echo "</label>\n";
				echo "<div class=\"form-text\">Administrators can manage system settings, users, and view audit logs.</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "<div class=\"d-grid gap-2 d-md-flex justify-content-md-end\">\n";
				echo "<a href=\"admin.php?func=users\" class=\"btn btn-outline-secondary me-md-2\">Cancel</a>\n";
				echo "<button type=\"submit\" name=\"what\" value=\"Add\" class=\"btn btn-success\">Add User</button>\n";
				echo "</div>\n";
				echo "</form>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
			}
			break;
		case "edit":
			if (isset($_REQUEST['what']) && $_REQUEST['what']=="Edit") {
				if ($_REQUEST['superuser']=="1") { $update_superuser = "1";	}
				else { $update_superuser = "0"; }
				// SQL query to edit a user
				$params = [$_REQUEST['username']];
				$sql_query = "UPDATE users SET `username` = ?";
				
				if ($_REQUEST['password']!="") { 
					$sql_query .= ", `password` = ?";
					$params[] = password_hash($_REQUEST['password'], PASSWORD_DEFAULT);
				}
				$sql_query .= ", `name` = ?, `email` = ?, `superuser` = ? WHERE id = ?";
				$params[] = $_REQUEST['name'];
				$params[] = $_REQUEST['email'];
				$params[] = $update_superuser;
				$params[] = $_REQUEST['id'];

				$db->query($sql_query, $params);
				// Enter in the audit log
				$audit_data = [
					'user_id' => $_REQUEST['id'],
					'username' => $_REQUEST['username'],
					'name' => $_REQUEST['name'],
					'email' => $_REQUEST['email'],
					'superuser' => $update_superuser,
					'password_changed' => $_REQUEST['password'] != "" ? 'yes' : 'no'
				];
				$audit_message = createAuditMessage("Updated user", $audit_data);
				$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, 'USERS: ' . $audit_message]);
//				header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=users&message=edited');
			} elseif (isset($_REQUEST['what']) && $_REQUEST['what']=="Delete") {
				// Get user info before deletion for audit log
				$user_query = $db->query("SELECT * FROM users WHERE id = ?", [$_REQUEST['id']]);
				$user_info = $db->fetchAssoc($user_query);
				
				$sql_query = "DELETE FROM users WHERE id = ?";
				$db->query($sql_query, [$_REQUEST['id']]);
				// Enter in the audit log
				$audit_data = [
					'user_id' => $_REQUEST['id'],
					'username' => $user_info['username'],
					'name' => $user_info['name'],
					'email' => $user_info['email']
				];
				$audit_message = createAuditMessage("Deleted user", $audit_data);
				$db->query("INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, ?, ?)", [$loginuser, 'USERS: ' . $audit_message]);
				header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=users&message=deleted');
			} else {
				$result = $db->query("SELECT * FROM users WHERE id = ?", [$_REQUEST['id']]);
				$row = $db->fetchAssoc($result);
				echo "<div class=\"row justify-content-center\">\n";
				echo "<div class=\"col-lg-8 col-md-10\">\n";
				echo "<div class=\"card\">\n";
				echo "<div class=\"card-header\">\n";
				echo "<h5 class=\"card-title mb-0\">Edit User: " . htmlspecialchars($row['username']) . "</h5>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<form method=\"post\" action=\"admin.php\">\n";
				echo "<input type=\"hidden\" name=\"id\" value=\"" . $row['id'] . "\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"users\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit\">\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"username\" class=\"form-label\">Username</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"username\" name=\"username\" value=\"" . htmlspecialchars($row['username']) . "\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"password\" class=\"form-label\">Password</label>\n";
				echo "<input type=\"password\" class=\"form-control\" id=\"password\" name=\"password\" placeholder=\"Leave blank to keep current password\">\n";
				echo "<div class=\"form-text\">Leave empty to keep the current password unchanged.</div>\n";
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"name\" class=\"form-label\">Full Name</label>\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"name\" name=\"name\" value=\"" . htmlspecialchars($row['name']) . "\" required>\n";
				echo "</div>\n";
				echo "<div class=\"mb-3\">\n";
				echo "<label for=\"email\" class=\"form-label\">Email Address</label>\n";
				echo "<input type=\"email\" class=\"form-control\" id=\"email\" name=\"email\" value=\"" . htmlspecialchars($row['email']) . "\" required>\n";
				echo "</div>\n";
				if ($loginlevel=="1") {
					echo "<div class=\"mb-4\">\n";
					echo "<div class=\"form-check\">\n";
					echo "<input type=\"checkbox\" class=\"form-check-input\" id=\"superuser\" name=\"superuser\" value=\"1\"";
					if ($row['superuser']=="1") { echo " checked"; }
					echo ">\n";
					echo "<label class=\"form-check-label\" for=\"superuser\" title=\"Administrative users can edit system settings, all users, and view the audit log.\">\n";
					echo "Administrator User\n";
					echo "</label>\n";
					echo "<div class=\"form-text\">Administrators can manage system settings, users, and view audit logs.</div>\n";
					echo "</div>\n";
					echo "</div>\n";
				}
				echo "<div class=\"d-grid gap-2 d-md-flex justify-content-md-between\">\n";
				echo "<button type=\"submit\" name=\"what\" value=\"Delete\" class=\"btn btn-danger\" onClick=\"return window.confirm('Are you REALLY sure you want to PERMANENTLY delete this account?');\">Delete User</button>\n";
				echo "<div>\n";
				echo "<a href=\"admin.php?func=users\" class=\"btn btn-outline-secondary me-2\">Cancel</a>\n";
				echo "<button type=\"submit\" name=\"what\" value=\"Edit\" class=\"btn btn-primary\">Save Changes</button>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</form>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
			}
			break;
		default:
		        echo "<p class=\"mb-4\">This module edits system users.</p>\n";
		        echo "<form method=\"post\" action=\"admin.php\">\n";
		        echo "<input type=\"hidden\" name=\"func\" value=\"users\">\n";
		        echo "<input type=\"hidden\" name=\"func_do\" value=\"add\">\n";
			echo "<div class=\"table-responsive\">\n";
			echo "<table class=\"table table-striped table-hover\">\n";
			echo "<tr><th>Username</th><th>Name</th><th>Admin</th><th>&nbsp;</th></tr>\n";
			$result = $db->query("SELECT * FROM users ORDER BY `name`");
			while($row = $db->fetchArray($result)) {
				echo "<tr><td>" . $row['username'] . "</td><td>" . $row['name'] . "</td><td>";
				if ($row['superuser']=="1") { echo "Yes"; } else { echo "No"; }
				echo "</td><td>\n";
				if ($loginuser==$row['username'] || $loginlevel=="1") {
					echo "<input type=\"button\" name=\"edit\" value=\"Edit\" onClick=\"parent.location='http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=users&amp;func_do=edit&amp;id=" . $row['id'] . "'\">\n";
				} else { echo "&nbsp;"; }
				echo "</td></tr>\n";
			}
			echo "</table>\n";
			echo "</div>\n";
			echo "</form>\n";
			if ($loginlevel=="1") {
				echo "<div class=\"text-center mt-3\"><a href=\"admin.php?func=users&amp;func_do=add\" class=\"btn btn-success\">Add New User</a></div>\n";
			}
	}
        break;

    case "audit":
	if ($loginlevel!="1") {
		header('Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?sysmsg=unauthorized');
	}

    	echo "<h3 class=\"text-primary mb-4\">Audit Log</h3>";
    	
    	// Search functionality
    	$search = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
    	echo "<div class=\"row mb-4\">\n";
    	echo "<div class=\"col-md-6\">\n";
    	echo "<form method=\"GET\" action=\"admin.php\" class=\"d-flex\">\n";
    	echo "<input type=\"hidden\" name=\"func\" value=\"audit\">\n";
    	echo "<input type=\"text\" class=\"form-control me-2\" name=\"search\" placeholder=\"Search audit log...\" value=\"" . htmlspecialchars($search) . "\">\n";
    	echo "<button type=\"submit\" class=\"btn btn-outline-primary\">Search</button>\n";
    	if ($search) {
    		echo "<a href=\"admin.php?func=audit\" class=\"btn btn-outline-secondary ms-2\">Clear</a>\n";
    	}
    	echo "</form>\n";
    	echo "</div>\n";
    	echo "</div>\n";
    	
    	// Pagination logic
    	if (!isset($_REQUEST['offset']) || $_REQUEST['offset']=="") { $lower=0;
    	} else { $lower=$_REQUEST['offset']; } 
    	$items_per_page = 50; // Reduced from 100 for better readability
    	$upper=($lower+$items_per_page);
    	
    	// Build query with search
    	$params = [];
    	$where_clause = "";
    	if ($search) {
    		$where_clause = "WHERE who LIKE ? OR what LIKE ?";
    		$search_pattern = '%' . $search . '%';
    		$params = [$search_pattern, $search_pattern];
    	}
    	
    	$result = $db->query("SELECT * FROM audit $where_clause ORDER BY timestamp DESC LIMIT $lower, $items_per_page", $params);
    	$rowcount_result = $db->query("SELECT COUNT(*) as total FROM audit $where_clause", $params);
    	$rowcount = $db->fetchAssoc($rowcount_result)['total'];
    	
    	// Display results in Bootstrap table
    	echo "<div class=\"table-responsive\">\n";
    	echo "<table class=\"table table-striped table-hover\">\n";
    	echo "<thead class=\"table-dark\">\n";
    	echo "<tr><th style=\"width: 180px;\">Timestamp</th><th style=\"width: 120px;\">User</th><th>Action</th></tr>\n";
    	echo "</thead>\n";
    	echo "<tbody>\n";
    	
    	$hasRows = false;
    	while ($row = $db->fetchArray($result)) {
    		$hasRows = true;
    			// Format timestamp - convert from UTC to local timezone
    			$utc_time = new DateTime($row['timestamp'], new DateTimeZone('UTC'));
    			$utc_time->setTimezone(new DateTimeZone(date_default_timezone_get()));
    			$formatted_time = $utc_time->format('M j, Y g:i A');
    			
    			// Determine action type and add badge
    			$action = $row['what'];
    			$badge_class = 'bg-secondary';
    			if (strpos($action, 'LOGIN') !== false) $badge_class = 'bg-success';
    			elseif (strpos($action, 'DELETE') !== false || strpos($action, 'ACDELETE') !== false) $badge_class = 'bg-danger';
    			elseif (strpos($action, 'UPDATE') !== false || strpos($action, 'SYSTEM') !== false) $badge_class = 'bg-warning text-dark';
    			elseif (strpos($action, 'INSERT') !== false || strpos($action, 'USERS') !== false) $badge_class = 'bg-info';
    			elseif (strpos($action, 'UPDATE_CHECK') !== false) $badge_class = 'bg-primary';
    			
    			// Extract action type for badge
    			$action_type = 'OTHER';
    			if (strpos($action, ':') !== false) {
    				$parts = explode(':', $action, 2);
    				$action_type = trim($parts[0]);
    			}
    			
    			echo "<tr>\n";
    			echo "<td><small class=\"text-muted\">" . $formatted_time . "</small></td>\n";
    			echo "<td><span class=\"badge bg-light text-dark\">" . htmlspecialchars($row['who']) . "</span></td>\n";
    			echo "<td>\n";
    			echo "<span class=\"badge $badge_class me-2\">" . htmlspecialchars($action_type) . "</span>\n";
    			echo "<small>" . htmlspecialchars($action) . "</small>\n";
    			echo "</td>\n";
    			echo "</tr>\n";
    	}
    	
    	if (!$hasRows) {
    		echo "<tr><td colspan=\"3\" class=\"text-center text-muted py-4\">";
    		if ($search) {
    			echo "No audit entries found matching '" . htmlspecialchars($search) . "'";
    		} else {
    			echo "No audit entries found";
    		}
    		echo "</td></tr>\n";
    	}
    	
    	echo "</tbody>\n";
    	echo "</table>\n";
    	echo "</div>\n";
    	
    	// Bootstrap pagination
    	if ($rowcount > $items_per_page) {
    		echo "<nav aria-label=\"Audit log pagination\">\n";
    		echo "<ul class=\"pagination justify-content-center\">\n";
    		
    		// Previous button
    		if ($lower > 0) {
    			$prev_offset = max(0, $lower - $items_per_page);
    			$search_param = $search ? '&search=' . urlencode($search) : '';
    			echo "<li class=\"page-item\"><a class=\"page-link\" href=\"admin.php?func=audit&offset=$prev_offset$search_param\">Previous</a></li>\n";
    		} else {
    			echo "<li class=\"page-item disabled\"><span class=\"page-link\">Previous</span></li>\n";
    		}
    		
    		// Page numbers
    		$total_pages = ceil($rowcount / $items_per_page);
    		$current_page = floor($lower / $items_per_page) + 1;
    		$start_page = max(1, $current_page - 2);
    		$end_page = min($total_pages, $current_page + 2);
    		
    		for ($i = $start_page; $i <= $end_page; $i++) {
    			$page_offset = ($i - 1) * $items_per_page;
    			$search_param = $search ? '&search=' . urlencode($search) : '';
    			if ($i == $current_page) {
    				echo "<li class=\"page-item active\"><span class=\"page-link\">$i</span></li>\n";
    			} else {
    				echo "<li class=\"page-item\"><a class=\"page-link\" href=\"admin.php?func=audit&offset=$page_offset$search_param\">$i</a></li>\n";
    			}
    		}
    		
    		// Next button
    		if ($lower + $items_per_page < $rowcount) {
    			$next_offset = $lower + $items_per_page;
    			$search_param = $search ? '&search=' . urlencode($search) : '';
    			echo "<li class=\"page-item\"><a class=\"page-link\" href=\"admin.php?func=audit&offset=$next_offset$search_param\">Next</a></li>\n";
    		} else {
    			echo "<li class=\"page-item disabled\"><span class=\"page-link\">Next</span></li>\n";
    		}
    		
    		echo "</ul>\n";
    		echo "</nav>\n";
    	}
    	
    	// Show total count
    	echo "<div class=\"text-center text-muted mt-3\">\n";
    	if ($search) {
    		echo "Found $rowcount entries matching '" . htmlspecialchars($search) . "'";
    	} else {
    		echo "Total: $rowcount audit entries";
    	}
    	echo "</div>\n";
    	break;

	 default:
      echo "<div class=\"text-center mb-4\">\n<h2 class=\"text-primary\">Tipping Point Administration</h2>\n<p class=\"text-muted\">Choose a menu item from the navigation above.</p>\n</div>\n";
}

echo "</div>\n</div>\n</div>\n</div>\n</div>\n";

?>


<?php
PageFooter($config['site_name'],$config['administrator'],$ver);
// Database connection will be closed automatically
?>
