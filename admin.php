<?php
include 'func.inc';
PageHeader("Admin Interface");
session_start();
?>

<?php
// LOGIN CHECK

if ($_REQUEST['func'] != "login") {
	$loginuser = $_SESSION["loginuser"];
	$loginpass = $_SESSION["loginpass"];

	// Check if user has session data
	if (!empty($loginuser) && !empty($loginpass)) {
		$stmt = mysqli_prepare($con, "SELECT * FROM users WHERE username = ?");
		mysqli_stmt_bind_param($stmt, 's', $loginuser);
		mysqli_stmt_execute($stmt);
		$login_query = mysqli_stmt_get_result($stmt);
		$pass_verify = mysqli_fetch_assoc($login_query);
		if (password_verify($loginpass, $pass_verify['password'])) {
			$loginresult = mysqli_fetch_assoc($login_query);
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

echo "<body>\n";
echo "<nav class=\"navbar navbar-expand-lg navbar-dark bg-secondary fixed-top noprint\">\n";
echo "  <div class=\"container-fluid\">\n";
echo "    <a class=\"navbar-brand text-warning\" href=\"admin.php\" title=\"TippingPoint Administration Home\">TippingPoint</a>\n";
if (isset($_SESSION["user_name"])) {
    echo "    <div class=\"navbar-nav ms-auto\">\n";
    echo "      <a class=\"nav-link\" href=\"admin.php?func=system\">System Settings</a>\n";
    echo "      <a class=\"nav-link\" href=\"admin.php?func=aircraft\">Aircraft</a>\n";
    echo "      <a class=\"nav-link\" href=\"admin.php?func=users\">Users</a>\n";
    echo "      <a class=\"nav-link\" href=\"admin.php?func=audit\">Audit Log</a>\n";
    echo "      <a class=\"nav-link\" href=\"admin.php?func=logout\">Logout</a>\n";
    echo "    </div>\n";
}
echo "  </div>\n";
echo "</nav>\n";
echo "<div class=\"container-fluid\" style=\"margin-top: 80px;\">\n";

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
	
	mysqli_query($con,"UPDATE configuration SET `value` = '" . time() . "' WHERE `item` = 'update_check'");
	mysqli_query($con,"UPDATE configuration SET `value` = '" . $ver_dist . "' WHERE `item` = 'update_version'");
	mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'UPDATE_CHECK: installed " . $ver . ", available " . $ver_dist . "')");
}
if ($ver != $config['update_version'] && $loginlevel=="1") {
	echo "<div class=\"alert alert-warning text-center\">\n";
	echo "TippingPoint version " . $config['update_version'] . " is available, you are currently running version " . $ver . ".<br>\n";
	echo "View the <a href=\"https://github.com/CAP-CalebNewville/tipping-point/releases\" target=\"_blank\">releases page</a> to see what's new, or visit the <a href=\"https://github.com/CAP-CalebNewville/tipping-point\" target=\"_blank\">project homepage</a> to download.<br>\n";
	echo "</div>\n";
}


echo "<div class=\"row justify-content-center\">\n<div class=\"col-lg-8 col-md-10\">\n<div class=\"card mt-4\">\n<div class=\"card-body\">\n";

if ($_REQUEST['sysmsg']=="logout") { echo "<div class=\"alert alert-success text-center\">You have been logged out.</div>\n\n";
} elseif ($_REQUEST['sysmsg']=="login") { echo "<div class=\"alert alert-success text-center\">You have been logged in. Select a function from the navigation above.</div>\n\n";
} elseif ($_REQUEST['sysmsg']=="unauthorized") { echo "<div class=\"alert alert-danger text-center\">Sorry, you are not allowed to access that module.</div>\n\n";
} elseif ($_REQUEST['sysmsg']=="invalid") { echo "<div class=\"alert alert-danger text-center\">You have entered an invalid username/password combination.</div>\n\n";
} elseif ($_REQUEST['sysmsg']=="acdeleted") { echo "<div class=\"alert alert-success text-center\">The aircraft has been deleted.</div>\n\n"; }

switch ($_REQUEST["func"]) {
    case "login":
    	if ($_REQUEST['username']!="") {
    		// login validation code here - stay logged in for a week
    		// setcookie("loginuser", $_REQUEST['username'], time()+604800);
    		// setcookie("loginpass", md5($_REQUEST['password']), time()+604800);
				// Validate login credentials first
				$stmt = mysqli_prepare($con, "SELECT * FROM users WHERE username = ?");
				mysqli_stmt_bind_param($stmt, 's', $_REQUEST['username']);
				mysqli_stmt_execute($stmt);
				$login_query = mysqli_stmt_get_result($stmt);
				$user_data = mysqli_fetch_assoc($login_query);
				
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
	if ($_REQUEST['message']=="updated") {echo "<p style=\"color: #00AA00; text-align: center;\">Settings Updated.</p>\n\n";}
    	switch ($_REQUEST["func_do"]) {
    		case "update":
    			// SQL query to update system settings
			foreach ($_POST as $k=>$v) {
				if ($k!="func" && $k!="func_do") {
					$sql_query = "UPDATE configuration SET `value` = '" . $v . "' WHERE `item` = '" . $k . "';";
					mysqli_query($con,$sql_query);
					// Enter audit log
					mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'SYSTEM: " . addslashes($sql_query) . "');");
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
        		echo "<div class=\"d-grid\">";
        		echo "<button type=\"submit\" class=\"btn btn-primary\">Save Settings</button>";
        		echo "</div>";
        		echo "</form>";
	}
        break;

    case "aircraft":
        echo "<h3 class=\"text-primary mb-3\">Aircraft Module</h3>";
	switch ($_REQUEST["func_do"]) {
		case "add":
			switch ($_REQUEST["step"]) {
				case "2":
					// SQL query to add a new aircraft
					$sql_query = "INSERT INTO `aircraft` (`active`, `tailnumber`, `makemodel`, `emptywt`, `emptycg`, `maxwt`, `cglimits`, `cgwarnfwd`, `cgwarnaft`, `fuelunit`) VALUES ('0', "
					.           "'" . $_REQUEST['tailnumber'] . "', '" . $_REQUEST['makemodel'] . "', '" . $_REQUEST['emptywt'] . "', '" . $_REQUEST['emptycg'] . "', '" . $_REQUEST['maxwt'] . "', "
					.           "'" . $_REQUEST['cglimits'] . "', '" . $_REQUEST['cgwarnfwd'] . "', '" . $_REQUEST['cgwarnaft'] . "', '" . $_REQUEST['fuelunit'] . "');";
					mysqli_query($con,$sql_query);
					$aircraft_result = mysqli_query($con,"SELECT * FROM `aircraft` WHERE `tailnumber` = '" . $_REQUEST['tailnumber'] . "' ORDER BY `id` DESC LIMIT 1");
					$aircraft = mysqli_fetch_assoc($aircraft_result);
					// Enter in the audit log
					mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
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
					echo "<div class=\"mb-3\">\n";
					echo "<label for=\"tailnumber\" class=\"form-label\">Tail Number</label>\n";
					echo "<input type=\"text\" class=\"form-control\" id=\"tailnumber\" name=\"tailnumber\" placeholder=\"N123AB\" required>\n";
					echo "</div>\n";
					echo "<div class=\"mb-3\">\n";
					echo "<label for=\"makemodel\" class=\"form-label\">Make and Model</label>\n";
					echo "<input type=\"text\" class=\"form-control\" id=\"makemodel\" name=\"makemodel\" placeholder=\"Cessna Skyhawk\" required>\n";
					echo "</div>\n";
					echo "<div class=\"mb-3\">\n";
					echo "<label for=\"emptywt\" class=\"form-label\">Empty Weight</label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"emptywt\" name=\"emptywt\" placeholder=\"1556.3\" required>\n";
					echo "</div>\n";
					echo "<div class=\"mb-3\">\n";
					echo "<label for=\"emptycg\" class=\"form-label\">Empty CG</label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"emptycg\" name=\"emptycg\" placeholder=\"38.78\" required>\n";
					echo "</div>\n";
					echo "<div class=\"mb-3\">\n";
					echo "<label for=\"maxwt\" class=\"form-label\">Maximum Gross Weight</label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"maxwt\" name=\"maxwt\" placeholder=\"2550\" required>\n";
					echo "</div>\n";
					echo "<div class=\"mb-3\">\n";
					echo "<label for=\"cglimits\" class=\"form-label\" title=\"This is a text description of the center of gravity limits, it is not used in part of the validation/warning process.\">Textual CG Limits</label>\n";
					echo "<input type=\"text\" class=\"form-control\" id=\"cglimits\" name=\"cglimits\" placeholder=\"FWD 35 @ 1600 - 35 @ 1950 - 39.5 @ 2550, AFT 47.3\">\n";
					echo "<div class=\"form-text\">Text description of CG limits (not used in calculations)</div>\n";
					echo "</div>\n";
					echo "<div class=\"row\">\n";
					echo "<div class=\"col-md-6\">\n";
					echo "<div class=\"mb-3\">\n";
					echo "<label for=\"cgwarnfwd\" class=\"form-label\" title=\"This value will be used to pop up a warning if the calculated CG is less than this value.\">Forward CG Warning</label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"cgwarnfwd\" name=\"cgwarnfwd\" placeholder=\"35\" required>\n";
					echo "</div>\n";
					echo "</div>\n";
					echo "<div class=\"col-md-6\">\n";
					echo "<div class=\"mb-3\">\n";
					echo "<label for=\"cgwarnaft\" class=\"form-label\" title=\"This value will be used to pop up a warning if the calculated CG is greater than this value.\">Aft CG Warning</label>\n";
					echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"cgwarnaft\" name=\"cgwarnaft\" placeholder=\"47.3\" required>\n";
					echo "</div>\n";
					echo "</div>\n";
					echo "</div>\n";
					echo "<div class=\"mb-4\">\n";
					echo "<label for=\"fuelunit\" class=\"form-label\">Fuel Unit</label>\n";
					echo "<select class=\"form-select\" id=\"fuelunit\" name=\"fuelunit\">\n";
					echo "<option value=\"Gallons\">Gallons</option>\n";
					echo "<option value=\"Liters\">Liters</option>\n";
					echo "<option value=\"Pounds\">Pounds</option>\n";
					echo "<option value=\"Kilograms\">Kilograms</option>\n";
					echo "</select>\n";
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
					$sql_query1 = "DELETE FROM aircraft_cg WHERE `tailnumber` = " . $_REQUEST['tailnumber'] . ";";
					$sql_query2 = "DELETE FROM aircraft_weights WHERE `tailnumber` = " . $_REQUEST['tailnumber'] . ";";
					$sql_query3 = "DELETE FROM aircraft WHERE `id` = " . $_REQUEST['tailnumber'] . ";";
					mysqli_query($con,$sql_query1);
					mysqli_query($con,$sql_query2);
					mysqli_query($con,$sql_query3);
					// Enter in the audit log
					mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'ACDELETE: " . addslashes($sql_query1) . " " . addslashes($sql_query2) . " " . addslashes($sql_query3) . "');");
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
				$aircraft_result = mysqli_query($con,"SELECT * FROM aircraft WHERE id='" . $_REQUEST['tailnumber'] . "'");
				$aircraft = mysqli_fetch_assoc($aircraft_result);
				mysqli_query($con,"INSERT INTO aircraft (`active`, `tailnumber`, `makemodel`, `emptywt`, `emptycg`, `maxwt`, `cglimits`, `cgwarnfwd`, `cgwarnaft`, `fuelunit`) VALUES "
				. "('0', '" . $_REQUEST['newtailnumber'] . "', '" . $_REQUEST['newmakemodel'] . "', '" . $aircraft['emptywt'] . "', '" . $aircraft['emptycg'] . "', '" . $aircraft['maxwt'] . "', '" . $aircraft['cglimits']
				. "', '" . $aircraft['cgwarnfwd'] . "', '" . $aircraft['cgwarnaft'] . "', '" . $aircraft['fuelunit'] . "');");

				// get id of new aircraft
				$aircraft_result = mysqli_query($con,"SELECT * FROM aircraft WHERE tailnumber ='" . $_REQUEST['newtailnumber'] . "' ORDER BY id DESC LIMIT 1");
				$aircraft_new = mysqli_fetch_assoc($aircraft_result);

				// duplicate the weights
				$weights_result = mysqli_query($con,"SELECT * FROM aircraft_weights WHERE tailnumber='" . $_REQUEST['tailnumber'] . "'");
				while($row = mysqli_fetch_assoc($weights_result)) {
					mysqli_query($con,"INSERT INTO aircraft_weights (`tailnumber`, `order`, `item`, `weight`, `arm`, `emptyweight`, `fuel`, `fuelwt`) VALUES "
					. "('" . $aircraft_new['id'] . "', '" . $row['order'] . "', '" . $row['item'] . "', '" . $row['weight'] . "', '" . $row['arm'] . "', '" . $row['emptyweight'] . "', '"
					. $row['fuel'] . "', '" . $row['fuelwt'] . "');");
				}

				// duplicate the cg envelope
				$cg_result = mysqli_query($con,"SELECT * FROM aircraft_cg WHERE tailnumber='" . $_REQUEST['tailnumber'] . "'");
				while($row = mysqli_fetch_assoc($cg_result)) {
					mysqli_query($con,"INSERT INTO aircraft_cg (`tailnumber`, `arm`, `weight`) VALUES ('" . $aircraft_new['id'] . "', '" . $row['arm'] . "', '" . $row['weight'] . "');");
				}

				// Enter in the audit log
				mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser
				. "', 'DUPLICATE: (" . $aircraft['id'] . ", " . $aircraft['tailnumber'] . ", " . $aircraft['makemodel'] . ") AS (" . $aircraft_new['id'] . ", " . $_REQUEST['newtailnumber'] . ", " . $_REQUEST['newmakemodel'] . ")');");

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
			if ($_REQUEST['tailnumber']!="") {
				$aircraft_result = mysqli_query($con,"SELECT * FROM aircraft WHERE id='" . $_REQUEST['tailnumber'] . "'");
				$aircraft = mysqli_fetch_assoc($aircraft_result);

				echo "<div class=\"mb-4\">\n";
				echo "<h4 class=\"text-primary\">Editing Aircraft: " . htmlspecialchars($aircraft['tailnumber']) . "</h4>\n";
				echo "</div>\n";

				if ($_REQUEST['message']=="updated") {echo "<div class=\"alert alert-success text-center\">Aircraft Updated Successfully</div>\n\n";}

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
				
				// Using Bootstrap row/col layout to maintain inline form layout
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-sm-3\">\n";
				echo "<label class=\"form-label\" title=\"Should this aircraft show up in the list to be able to run weight and balance?\">Active</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-9\">\n";
				echo "<div class=\"form-check\">\n";
				echo "<input type=\"checkbox\" class=\"form-check-input\" name=\"active\" value=\"1\" id=\"active\"";
					if ($aircraft['active']==1) {echo" checked";}
					echo ">\n";
				echo "<label class=\"form-check-label\" for=\"active\">Aircraft is active and available for weight & balance</label>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-sm-3\">\n";
				echo "<label for=\"tailnumber\" class=\"form-label\">Tail Number</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-9\">\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"tailnumber\" name=\"tailnumber\" value=\"" . htmlspecialchars($aircraft['tailnumber']) . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-sm-3\">\n";
				echo "<label for=\"makemodel\" class=\"form-label\">Make and Model</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-9\">\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"makemodel\" name=\"makemodel\" value=\"" . htmlspecialchars($aircraft['makemodel']) . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-sm-3\">\n";
				echo "<label for=\"emptywt\" class=\"form-label\">Empty Weight</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-9\">\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"emptywt\" name=\"emptywt\" value=\"" . $aircraft['emptywt'] . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-sm-3\">\n";
				echo "<label for=\"emptycg\" class=\"form-label\">Empty CG</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-9\">\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"emptycg\" name=\"emptycg\" value=\"" . $aircraft['emptycg'] . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-sm-3\">\n";
				echo "<label for=\"maxwt\" class=\"form-label\">Maximum Gross Weight</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-9\">\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"maxwt\" name=\"maxwt\" value=\"" . $aircraft['maxwt'] . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-sm-3\">\n";
				echo "<label for=\"cglimits\" class=\"form-label\" title=\"This is a text description of the center of gravity limits, it is not used in part of the validation/warning process.\">Textual CG Limits</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-9\">\n";
				echo "<input type=\"text\" class=\"form-control\" id=\"cglimits\" name=\"cglimits\" value=\"" . htmlspecialchars($aircraft['cglimits']) . "\">\n";
				echo "<div class=\"form-text\">Text description of CG limits (not used in calculations)</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-3\">\n";
				echo "<div class=\"col-sm-6\">\n";
				echo "<div class=\"row\">\n";
				echo "<div class=\"col-sm-6\">\n";
				echo "<label for=\"cgwarnfwd\" class=\"form-label\" title=\"This value will be used to pop up a warning if the calculated CG is less than this value.\">Forward CG Warning</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-6\">\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"cgwarnfwd\" name=\"cgwarnfwd\" value=\"" . $aircraft['cgwarnfwd'] . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-6\">\n";
				echo "<div class=\"row\">\n";
				echo "<div class=\"col-sm-6\">\n";
				echo "<label for=\"cgwarnaft\" class=\"form-label\" title=\"This value will be used to pop up a warning if the calculated CG is greater than this value.\">Aft CG Warning</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-6\">\n";
				echo "<input type=\"number\" step=\"any\" class=\"form-control\" id=\"cgwarnaft\" name=\"cgwarnaft\" value=\"" . $aircraft['cgwarnaft'] . "\">\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";
				
				echo "<div class=\"row mb-4\">\n";
				echo "<div class=\"col-sm-3\">\n";
				echo "<label for=\"fuelunit\" class=\"form-label\">Fuel Unit</label>\n";
				echo "</div>\n";
				echo "<div class=\"col-sm-9\">\n";
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
				echo "</div>\n";
				
				echo "<div class=\"d-grid gap-2 d-md-flex justify-content-md-end\">\n";
				echo "<button type=\"submit\" class=\"btn btn-primary\">Save Basic Information</button>\n";
				echo "</div>\n";
				echo "</form>\n";
				echo "</div>\n";
				echo "</div>\n";

				// Aircraft CG envelope
				echo "<div class=\"card mb-4\">\n";
				echo "<div class=\"card-header\">\n";
				echo "<h4 class=\"card-title mb-0\">Center of Gravity Envelope</h4>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<div class=\"alert alert-info mb-4\">\n";
				echo "<small>Enter the data points for the CG envelope. It does not matter which point you start with or if you go clockwise or counter-clockwise, but they must be entered in order. The last point will automatically be connected back to the first. The graph below will update as you go.</small>\n";
				echo "</div>\n";
				
				$cg_result = mysqli_query($con,"SELECT * FROM aircraft_cg WHERE tailnumber=" . $aircraft['id']);
				echo "<form method=\"post\" action=\"admin.php\" name=\"cg\">\n";
				echo "<input type=\"hidden\" name=\"tailnumber\" value=\"" . $aircraft['id'] . "\">\n";
				echo "<input type=\"hidden\" name=\"func\" value=\"aircraft\">\n";
				echo "<input type=\"hidden\" name=\"func_do\" value=\"edit_do\">\n";
				echo "<input type=\"hidden\" name=\"what\" value=\"cg\">\n";
				
				echo "<div class=\"table-responsive\">\n";
				echo "<table class=\"table table-sm table-hover\">\n";
				echo "<thead class=\"table-dark\">\n";
				echo "<tr><th class=\"text-center\">Arm</th><th class=\"text-center\">Weight</th><th class=\"text-center\">Actions</th></tr>\n";
				echo "</thead>\n";
				echo "<tbody>\n";
				
				while($cg = mysqli_fetch_assoc($cg_result)) {
					echo "<tr>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"cgarm" . $cg['id'] . "\" value=\"" . $cg['arm'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 100px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"cgweight" . $cg['id'] . "\" value=\"" . $cg['weight'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 100px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<div class=\"btn-group btn-group-sm\" role=\"group\">\n";
					echo "<button type=\"button\" class=\"btn btn-outline-primary\" onClick=\"parent.location='http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=aircraft&amp;func_do=edit_do&amp;what=cg&amp;id=" . $cg['id'] . "&amp;cgarm=' + document.cg.cgarm" . $cg['id'] . ".value + '&amp;cgweight=' + document.cg.cgweight" . $cg['id'] . ".value + '&amp;tailnumber=" . $aircraft['id'] . "'\">Update</button>\n";
					echo "<button type=\"button\" class=\"btn btn-outline-danger\" onClick=\"if(confirm('Delete this CG point?')) parent.location='http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=aircraft&amp;func_do=edit_do&amp;what=cg_del&amp;id=" . $cg['id'] . "&amp;tailnumber=" . $aircraft['id'] . "'\">Delete</button>\n";
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
				echo "<button type=\"submit\" class=\"btn btn-success btn-sm\">Add Point</button>\n";
				echo "</td>\n";
				echo "</tr>\n";
				
				echo "</tbody>\n";
				echo "</table>\n";
				echo "</div>\n";
				echo "</form>\n";
				
				// CG Graph
				echo "<div class=\"text-center mt-4\">\n";
				echo "<h6 class=\"text-muted\">CG Envelope Graph</h6>\n";
				echo "<embed src=\"scatter.php?size=small&amp;tailnumber=" . $aircraft['id'] . "\" width=\"420\" height=\"220\" class=\"border rounded\">\n";
				echo "</div>\n";
				echo "</div>\n";
				echo "</div>\n";

				// Aircraft loading zones
				echo "<div class=\"card mb-4\">\n";
				echo "<div class=\"card-header\">\n";
				echo "<h4 class=\"card-title mb-0\">Loading Zones</h4>\n";
				echo "</div>\n";
				echo "<div class=\"card-body\">\n";
				echo "<div class=\"alert alert-info mb-4\">\n";
				echo "<small>Enter the data for each reference datum. Hover over column headers for detailed descriptions of each field.</small>\n";
				echo "</div>\n";
				
				$weights_result = mysqli_query($con,"SELECT * FROM aircraft_weights WHERE tailnumber = " . $aircraft['id'] . " ORDER BY  `aircraft_weights`.`order` ASC");
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
				echo "<th class=\"text-center\" title=\"Checking this box will cause the weight column to be locked on the spreadsheet so it cannot be changed.\">Empty<br>Weight</th>\n";
				echo "<th class=\"text-center\" title=\"Checking this box causes the spreadsheet to take it's entry in fuel and automatically compute the weight.\">Fuel</th>\n";
				echo "<th class=\"text-center\" title=\"If this row is used for fuel, specify how much a unit of fuel weighs (ie: 6 for AVGAS)\">Fuel Unit<br>Weight</th>\n";
				echo "<th class=\"text-center\" title=\"The default weight to be used for a row. If this is a fuel row, the default number of " . $aircraft['fuelunit'] . ".\">Weight or<br>" . htmlspecialchars($aircraft['fuelunit']) . "</th>\n";
				echo "<th class=\"text-center\" title=\"The number of inches from the reference datum for the row.\">Arm</th>\n";
				echo "<th class=\"text-center\">Actions</th>\n";
				echo "</tr>\n";
				echo "</thead>\n";
				echo "<tbody>\n";
				
				while ($weights = mysqli_fetch_assoc($weights_result)) {
					echo "<tr>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" name=\"order" . $weights['id'] . "\" value=\"" . $weights['order'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 60px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"text\" name=\"item" . $weights['id'] . "\" value=\"" . htmlspecialchars($weights['item']) . "\" class=\"form-control form-control-sm\" style=\"width: 140px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<div class=\"form-check d-flex justify-content-center\">\n";
					echo "<input type=\"checkbox\" class=\"form-check-input\" name=\"emptyweight" . $weights['id'] . "\" value=\"true\"";
						if ($weights['emptyweight']=="true") { echo(" checked"); }
					echo ">\n";
					echo "</div>\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<div class=\"form-check d-flex justify-content-center\">\n";
					echo "<input type=\"checkbox\" class=\"form-check-input\" name=\"fuel" . $weights['id'] . "\" value=\"true\" onclick=\"if(document.loading.fuel" . $weights['id'] . ".checked==false) {document.loading.fuelwt" . $weights['id'] . ".disabled=true;} else {document.loading.fuelwt" . $weights['id'] . ".disabled=false;}\"";
						if ($weights['fuel']=="true") { echo(" checked"); }
					echo ">\n";
					echo "</div>\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"fuelwt" . $weights['id'] . "\" value=\"" . $weights['fuelwt'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\"";
						if ($weights['fuel']=="false") { echo(" disabled"); }
					echo ">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"weight" . $weights['id'] . "\" value=\"" . $weights['weight'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<input type=\"number\" step=\"any\" name=\"arm" . $weights['id'] . "\" value=\"" . $weights['arm'] . "\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\">\n";
					echo "</td>\n";
					echo "<td class=\"text-center\">\n";
					echo "<div class=\"btn-group btn-group-sm\" role=\"group\">\n";
					echo "<button type=\"button\" class=\"btn btn-outline-primary\" onClick=\"parent.location='http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=aircraft&amp;func_do=edit_do&amp;what=loading&amp;id=" . $weights['id'] . "&amp;"
					.    "order=' + document.loading.order" . $weights['id'] . ".value + '&amp;item=' + encodeURIComponent(document.loading.item" . $weights['id'] . ".value) + '&amp;emptyweight=' + document.loading.emptyweight" . $weights['id'] . ".checked + '&amp;"
					.    "fuel=' + document.loading.fuel" . $weights['id'] . ".checked + '&amp;fuelwt=' + document.loading.fuelwt" . $weights['id'] . ".value + '&amp;weight=' + document.loading.weight" . $weights['id'] . ".value + '&amp;"
					.    "arm=' + document.loading.arm" . $weights['id'] . ".value + '&amp;tailnumber=" . $aircraft['id'] . "'\">Update</button>\n";
					echo "<button type=\"button\" class=\"btn btn-outline-danger\" onClick=\"if(confirm('Delete this loading zone?')) parent.location='http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?func=aircraft&amp;func_do=edit_do&amp;what=loading_del&amp;id=" . $weights['id'] . "&amp;tailnumber=" . $aircraft['id'] . "'\">Delete</button>\n";
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
				echo "<div class=\"form-check d-flex justify-content-center\">\n";
				echo "<input type=\"checkbox\" class=\"form-check-input\" name=\"new_emptyweight\" value=\"true\">\n";
				echo "</div>\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<div class=\"form-check d-flex justify-content-center\">\n";
				echo "<input type=\"checkbox\" class=\"form-check-input\" name=\"new_fuel\" value=\"true\" onclick=\"if(document.loading.new_fuel.checked==false) {document.loading.new_fuelwt.disabled=true;} else {document.loading.new_fuelwt.disabled=false;}\">\n";
				echo "</div>\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<input type=\"number\" step=\"any\" name=\"new_fuelwt\" class=\"form-control form-control-sm text-center\" placeholder=\"6\" style=\"width: 80px; display: inline-block;\" disabled>\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<input type=\"number\" step=\"any\" name=\"new_weight\" class=\"form-control form-control-sm text-center\" placeholder=\"Weight\" style=\"width: 80px; display: inline-block;\">\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<input type=\"number\" step=\"any\" name=\"new_arm\" class=\"form-control form-control-sm text-center\" placeholder=\"Arm\" style=\"width: 80px; display: inline-block;\">\n";
				echo "</td>\n";
				echo "<td class=\"text-center\">\n";
				echo "<button type=\"submit\" class=\"btn btn-success btn-sm\">Add Zone</button>\n";
				echo "</td>\n";
				echo "</tr>\n";
				
				echo "</tbody>\n";
				echo "</table>\n";
				echo "</div>\n";
				echo "</form>\n";
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
					$sql_query = "UPDATE aircraft SET active = '" . $active . "', tailnumber = '" . $_REQUEST['tailnumber'] . "', makemodel = '"
					. $_REQUEST['makemodel'] . "', emptywt = '" . $_REQUEST['emptywt'] . "', emptycg = '" . $_REQUEST['emptycg'] . "', maxwt = '" . $_REQUEST['maxwt']
					. "', cglimits = '" . $_REQUEST['cglimits'] . "', cgwarnfwd = '" . $_REQUEST['cgwarnfwd'] . "', cgwarnaft = '" . $_REQUEST['cgwarnaft']
					. "', fuelunit = '" . $_REQUEST['fuelunit'] . "' WHERE id = "
					. $_REQUEST['id'];
					mysqli_query($con,$sql_query);
					// Enter in the audit log
					mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $_REQUEST['tailnumber'] . ": " . addslashes($sql_query) . "');");
					header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['id'] . '&message=updated');
					break;
				case "cg":
					if ($_REQUEST['new_arm'] != "" && $_REQUEST['new_weight'] != "") {
						// SQL query to add a new CG line
						$sql_query = "INSERT INTO aircraft_cg (`id`, `tailnumber`, `arm`, `weight`) VALUES (NULL, '" . $_REQUEST['tailnumber'] . "', '" . $_REQUEST['new_arm'] . "', '" . $_REQUEST['new_weight'] . "');";
						mysqli_query($con,$sql_query);
						// Enter in the audit log
						$aircraft_query = mysqli_query($con,"SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
						$aircraft = mysqli_fetch_assoc($aircraft_query);
						mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
						header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					} else {
						// SQL query to edit CG information
						$sql_query = "UPDATE aircraft_cg SET arm = '" . $_REQUEST['cgarm'] . "', weight = '" . $_REQUEST['cgweight'] . "' WHERE id = '" . $_REQUEST['id'] . "';";
						mysqli_query($con,$sql_query);
						// Enter in the audit log
						$aircraft_query = mysqli_query($con,"SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
						$aircraft = mysqli_fetch_assoc($aircraft_query);
						mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
						header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					}
					break;
				case "cg_del":
					// SQL query to delete CG information
					$sql_query = "DELETE FROM aircraft_cg WHERE id = " . $_REQUEST['id'];
					mysqli_query($con,$sql_query);
					// Enter in the audit log
					$aircraft_query = mysqli_query($con,"SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
					$aircraft = mysqli_fetch_assoc($aircraft_query);
					mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
					header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					break;
				case "loading":
					if ($_REQUEST['new_item'] && $_REQUEST['new_arm'] != "") {
						// SQL query to add a new loading line
						$sql_query = "INSERT INTO aircraft_weights (`id`, `tailnumber`, `order`, `item`, `weight`, `arm`";
						if ($_REQUEST['new_fuel']=="true") { $sql_query = $sql_query . ", `fuelwt`, `fuel`"; }
						if ($_REQUEST['new_emptyweight']=="true") { $sql_query = $sql_query . ", `emptyweight`"; }
						$sql_query = $sql_query . ") VALUES (NULL, '" . $_REQUEST['tailnumber'] . "', '" . $_REQUEST['new_order'] . "', '" . $_REQUEST['new_item'] . "', '" . $_REQUEST['new_weight'] . "', '" . $_REQUEST['new_arm'] . "'";
						if ($_REQUEST['new_fuel']=="true") { $sql_query = $sql_query . ", '" . $_REQUEST['new_fuelwt'] . "', 'true'"; }
						if ($_REQUEST['new_emptyweight']=="true") { $sql_query = $sql_query . ", 'true'"; }
						$sql_query = $sql_query . ");";
						mysqli_query($con,$sql_query);
						// Enter in the audit log
						$aircraft_query = mysqli_query($con,"SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
						$aircraft = mysqli_fetch_assoc($aircraft_query);
						mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
						header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					} else {
						// SQL query to edit loading zones
						$sql_query = "UPDATE aircraft_weights SET `order` = '" . $_REQUEST['order'] . "', `item` = '" . $_REQUEST['item'] . "', `weight` = '" . $_REQUEST['weight'] . "', `arm` = '" . $_REQUEST['arm'] . "'";
						if ($_REQUEST['emptyweight']=="true") { $sql_query = $sql_query . ", `emptyweight` = 'true'"; }
						if ($_REQUEST['fuel']=="true") { $sql_query = $sql_query . ", `fuel` = '" . $_REQUEST['fuel'] . "', `fuelwt` = '" . $_REQUEST['fuelwt'] . "'"; }
						$sql_query = $sql_query . " WHERE id = '" . $_REQUEST['id'] . "';";

						mysqli_query($con,$sql_query);
						// Enter in the audit log
						$aircraft_query = mysqli_query($con,"SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
						$aircraft = mysqli_fetch_assoc($aircraft_query);
						mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
						header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=aircraft&func_do=edit&tailnumber=' . $_REQUEST['tailnumber'] . '&message=updated');
					}
					break;
				case "loading_del":
					// SQL query to delete loading information
					$sql_query = "DELETE FROM aircraft_weights WHERE id = " . $_REQUEST['id'];
					mysqli_query($con,$sql_query);
					// Enter in the audit log
					$aircraft_query = mysqli_query($con,"SELECT * FROM aircraft WHERE id = " . $_REQUEST['tailnumber']);
					$aircraft = mysqli_fetch_assoc($aircraft_query);
					mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', '" . $aircraft['tailnumber'] . ": " . addslashes($sql_query) . "');");
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
        if ($_REQUEST['message']=="added") { echo "<p style=\"color: #00AA00; text-align: center;\">User account added.</p>\n\n";
        } elseif ($_REQUEST['message']=="edited") { echo "<p style=\"color: #00AA00; text-align: center;\">User account edited.</p>\n\n";
        } elseif ($_REQUEST['message']=="deleted") { echo "<p style=\"color: #00AA00; text-align: center;\">User account deleted.</p>\n\n"; }
	switch ($_REQUEST["func_do"]) {
		case "add":
			if ($_REQUEST['what']=="Add") {
				// SQL query to add a new user
				$sql_query = "INSERT INTO users (`username`, `password`, `name`, `email`, `superuser`) "
				.            "VALUES ('" . mysqli_real_escape_string($con, $_REQUEST['username']) . "', '" . password_hash($_REQUEST['password'], PASSWORD_DEFAULT) . "', '" . mysqli_real_escape_string($con, $_REQUEST['name']) . "', '" . mysqli_real_escape_string($con, $_REQUEST['email']) . "', '" . mysqli_real_escape_string($con, $_REQUEST['superuser']) . "');";
				mysqli_query($con,$sql_query);
				// Enter in the audit log
				mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'USERS: " . addslashes($sql_query) . "');");
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
			if ($_REQUEST['what']=="Edit") {
				if ($_REQUEST['superuser']=="1") { $update_superuser = "1";	}
				else { $update_superuser = "0"; }
				// SQL query to edit a user
				$sql_query = "UPDATE users SET `username` = '" . $_REQUEST['username'] . "', ";
				if ($_REQUEST['password']!="") { $sql_query = $sql_query . "`password` = '" . password_hash($_REQUEST['password'], PASSWORD_DEFAULT) . "', "; }
				$sql_query = $sql_query . "`name` = '" . $_REQUEST['name'] . "', `email` = '" . $_REQUEST['email'] . "', `superuser` = '" . $update_superuser
				. "' WHERE id = '" . $_REQUEST['id'] . "';";

				mysqli_query($con,$sql_query);
				// Enter in the audit log
				mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'USERS: " . addslashes($sql_query) . "');");
//				header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=users&message=edited');
			} elseif ($_REQUEST['what']=="Delete") {
				$sql_query = "DELETE FROM users WHERE id = '" . $_REQUEST['id'] . "';";
				mysqli_query($con,$sql_query);
				// Enter in the audit log
				mysqli_query($con,"INSERT INTO audit (`id`, `timestamp`, `who`, `what`) VALUES (NULL, CURRENT_TIMESTAMP, '" . $loginuser . "', 'USERS: " . addslashes($sql_query) . "');");
				header('Location: http://' . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . '?func=users&message=deleted');
			} else {
				$result = mysqli_query($con,"SELECT * FROM users WHERE id = " . $_REQUEST['id']);
				$row = mysqli_fetch_assoc($result);
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
			$result = mysqli_query($con,"SELECT * FROM users ORDER BY `name`");
			while($row = mysqli_fetch_array($result)) {
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
    	if ($_REQUEST['offset']=="") { $lower=0;
    	} else { $lower=$_REQUEST['offset']; } 
    	$items_per_page = 50; // Reduced from 100 for better readability
    	$upper=($lower+$items_per_page);
    	
    	// Build query with search
    	$where_clause = "";
    	if ($search) {
    		$search_escaped = mysqli_real_escape_string($con, $search);
    		$where_clause = "WHERE who LIKE '%$search_escaped%' OR what LIKE '%$search_escaped%'";
    	}
    	
    	$result = mysqli_query($con,"SELECT * FROM audit $where_clause ORDER BY timestamp DESC LIMIT $lower, $items_per_page");
    	$rowcount_result = mysqli_query($con,"SELECT COUNT(*) as total FROM audit $where_clause");
    	$rowcount = mysqli_fetch_assoc($rowcount_result)['total'];
    	
    	// Display results in Bootstrap table
    	echo "<div class=\"table-responsive\">\n";
    	echo "<table class=\"table table-striped table-hover\">\n";
    	echo "<thead class=\"table-dark\">\n";
    	echo "<tr><th style=\"width: 180px;\">Timestamp</th><th style=\"width: 120px;\">User</th><th>Action</th></tr>\n";
    	echo "</thead>\n";
    	echo "<tbody>\n";
    	
    	if (mysqli_num_rows($result) == 0) {
    		echo "<tr><td colspan=\"3\" class=\"text-center text-muted py-4\">";
    		if ($search) {
    			echo "No audit entries found matching '" . htmlspecialchars($search) . "'";
    		} else {
    			echo "No audit entries found";
    		}
    		echo "</td></tr>\n";
    	} else {
    		while ($row = mysqli_fetch_array($result)) {
    			// Format timestamp
    			$formatted_time = date('M j, Y g:i A', strtotime($row['timestamp']));
    			
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
// mysqli_close();
?>
