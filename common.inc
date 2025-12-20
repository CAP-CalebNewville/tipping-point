<?php
/* COMMON FUNCTIONS AND VARIABLES */
/* Functions that don't require database connection - safe for setup scripts */

// Prevent multiple includes
if (defined('COMMON_INC_LOADED')) return;
define('COMMON_INC_LOADED', true);

$ver = "2.0.2";
$tabindex=1;

function PageHeader($site_name) {
	?>
	<!DOCTYPE HTML>
	<html data-bs-theme="auto">
	<head>
	<title>TippingPoint - <?php echo($site_name); ?></title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

	<style type="text/css">
	<!--
	body {padding-top: 60px;} /* Space for fixed navbar */
	.readonly {background-color: #CCCCCC;}
	.numbers {text-align: right; width: 70px;}
	.numbergals {text-align: right; width: 40px;}
	@media print { .noprint { display: none; } body {padding-top: 0;} }
	
	/* Custom table styling for compatibility */
	.admin-table {
		border: 1px solid #dee2e6;
		border-collapse: collapse;
	}
	.admin-table th, .admin-table td {
		border: 1px solid #dee2e6;
		padding: 8px;
	}
	.admin-table th {
		background-color: #4F81BD;
		color: white;
		text-align: center;
	}
	.admin-table tr:nth-child(odd) {
		background-color: #FFFFFF;
	}
	.admin-table tr:nth-child(even) {
		background-color: #f8f9fa;
	}
	-->
	</style>
	<?php
}

function PageFooter($site_name,$admin,$ver) {
	?>
	<p class="noprint" style="text-align:center; font-size:12px; font-style:italic;"><?php echo($site_name); ?> - <a href="mailto:<?php echo($admin); ?>?subject=TippingPoint%20on%20<?php echo($_SERVER["HTTP_HOST"]); ?>"><?php echo($admin); ?></a><br />
	<a href="https://github.com/CAP-CalebNewville/tipping-point" target="_blank">TippingPoint - Open Source Weight &amp; Balance Software</a> - Version <?php echo($ver); ?></p>
	<div style="height: 20px;"></div>
	</body></html>
	<?php
}

function TimezoneList($str) {
	echo("<select id=\"timezone\" name=\"timezone\" class=\"form-select\">");
	$timezone_identifiers = DateTimeZone::listIdentifiers();
	$continent = "";
	foreach( $timezone_identifiers as $value ){
		if ( preg_match('/^(America|Antartica|Arctic|Asia|Atlantic|Europe|Indian|Pacific)\//', $value ) ){
			$ex=explode("/",$value);//obtain continent,city
			if ($continent!=$ex[0]){
				if ($continent!="") echo "</optgroup>\n";
				echo "<optgroup label=\"".$ex[0]."\">\n";
				$continent=$ex[0];
			}
			$city=$ex[1];
			if (isset($ex[2]) && !is_null($ex[2])) { $city=$city . "/" . $ex[2]; }
		            echo "<option value=\"".$value."\"";
		            if ($str==$value) {
		            	echo " selected";
		            }
		            echo ">".$city."</option>\n";
	        }
	}
	echo("</optgroup></select>");
}

function isSystemInstalled() {
    // Check if system is installed - either SQLite database exists or MySQL config exists
    return file_exists(dirname(__FILE__) . '/data/tippingpoint.db') || file_exists('config.inc');
}

function get_timezone_offset($remote_tz, $origin_tz = null) {
    if($origin_tz === null) {
        if(!is_string($origin_tz = date_default_timezone_get())) {
            return false; // A UTC timestamp was returned -- bail out!
        }
    }
    $origin_dtz = new DateTimeZone($origin_tz);
    $remote_dtz = new DateTimeZone($remote_tz);
    $origin_dt = new DateTime("now", $origin_dtz);
    $remote_dt = new DateTime("now", $remote_dtz);
    $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
    return $offset;
}

function checkHtaccessSupport() {
    $result = array(
        'status' => false,
        'message' => 'Unknown',
        'fix' => 'Configure Apache to process .htaccess files'
    );
    
    try {
        // Strategy: Test against existing .htaccess rules by creating a file that should be blocked
        // Create a .inc file (which should be blocked by existing .htaccess rules)
        $test_content_file = dirname(__FILE__) . '/htaccess_test_file.inc';
        
        // Create a test .inc file with recognizable content
        $test_file_content = "HTACCESS_TEST_FILE_SHOULD_BE_BLOCKED";
        if (file_put_contents($test_content_file, $test_file_content) === false) {
            $result['message'] = 'Cannot create test files';
            $result['fix'] = 'Check directory write permissions';
            return $result;
        }
        
        // Test 2: Try to access the test .inc file via HTTP (should be blocked by existing .htaccess)
        $http_test_successful = false;
        if (function_exists('curl_init') && isset($_SERVER['HTTP_HOST'])) {
            // Build the test URL
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/wtbal/';
            $base_path = dirname($request_uri);
            if ($base_path === '/') $base_path = '';
            $test_url = 'http://' . $_SERVER['HTTP_HOST'] . $base_path . '/htaccess_test_file.inc';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $test_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_NOBODY, false); // We want to check content too
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // In case of self-signed certs
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($response !== false && empty($curl_error)) {
                $http_test_successful = true;
                
                if ($http_code == 403) {
                    // Perfect! .htaccess is blocking access as expected
                    $result['status'] = true;
                    $result['message'] = 'Working (AllowOverride active - HTTP 403)';
                    $result['fix'] = '';
                } elseif ($http_code == 200 && strpos($response, $test_file_content) !== false) {
                    // File is accessible, .htaccess is not working
                    $result['status'] = false;
                    $result['message'] = 'Not working (AllowOverride disabled - HTTP 200)';
                    $result['fix'] = 'Enable AllowOverride in Apache virtual host configuration. Add: AllowOverride All';
                } elseif ($http_code == 404) {
                    // File not found - might be path issue, try fallback detection
                    $result['message'] = 'HTTP test inconclusive (404 - path issue?)';
                    $result['fix'] = 'Cannot test automatically. Verify AllowOverride is enabled in Apache configuration.';
                } else {
                    // Other HTTP codes
                    $result['message'] = "HTTP test inconclusive (HTTP $http_code)";
                    $result['fix'] = 'Cannot test automatically. Verify AllowOverride is enabled in Apache configuration.';
                }
            } else {
                // cURL failed
                $result['message'] = 'HTTP test failed (' . ($curl_error ?: 'connection error') . ')';
                $result['fix'] = 'Cannot test automatically. Verify AllowOverride is enabled in Apache configuration.';
            }
        }
        
        // Clean up test files
        if (file_exists($test_content_file)) {
            unlink($test_content_file);
        }
        
        // Fallback detection if HTTP test wasn't possible or conclusive
        if (!$http_test_successful || !$result['status']) {
            // Check Apache modules as secondary indicator
            if (function_exists('apache_get_modules')) {
                $modules = apache_get_modules();
                $has_rewrite = in_array('mod_rewrite', $modules);
                
                if ($has_rewrite && !$http_test_successful) {
                    $result['message'] = 'Apache + mod_rewrite detected (HTTP test unavailable)';
                    $result['fix'] = 'Verify AllowOverride is enabled. HTTP test could not confirm.';
                } elseif (!$has_rewrite) {
                    $result['message'] = 'Apache detected, mod_rewrite not available';
                    $result['fix'] = 'Enable mod_rewrite: sudo a2enmod rewrite && sudo systemctl restart apache2';
                }
            } elseif (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false) {
                if (!$http_test_successful) {
                    $result['message'] = 'Apache detected (modules unknown, HTTP test failed)';
                    $result['fix'] = 'Enable AllowOverride and mod_rewrite in Apache configuration';
                }
            } else {
                if (!$http_test_successful) {
                    $result['message'] = 'Web server unknown (HTTP test failed)';
                    $result['fix'] = 'If using Apache, enable AllowOverride. For other servers, configure appropriate restrictions.';
                }
            }
        }
        
    } catch (Exception $e) {
        $result['message'] = 'Test failed: ' . $e->getMessage();
        $result['fix'] = 'Check server configuration and permissions';
    }
    
    return $result;
}

function checkSystemRequirements() {
    $requirements = array();
    $allPassed = true;
    
    // Check PHP version
    $phpVersion = phpversion();
    $requirements['php_version'] = array(
        'name' => 'PHP Version',
        'required' => '8.0+',
        'current' => $phpVersion,
        'status' => version_compare($phpVersion, '8.0.0', '>='),
        'fix' => 'Update PHP to version 8.0 or higher. Contact your hosting provider or system administrator.'
    );
    if (!$requirements['php_version']['status']) $allPassed = false;
    
    // Check SQLite3 extension
    $requirements['sqlite3'] = array(
        'name' => 'SQLite3 Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('sqlite3') ? 'Enabled' : 'Not Available',
        'status' => extension_loaded('sqlite3'),
        'fix' => 'Install the PHP SQLite3 extension. Try: apt-get install php-sqlite3 (Ubuntu/Debian) or yum install php-sqlite3 (CentOS/RHEL)'
    );
    if (!$requirements['sqlite3']['status']) $allPassed = false;
    
    // Check PDO SQLite extension
    $requirements['pdo_sqlite'] = array(
        'name' => 'PDO SQLite Extension',
        'required' => 'Enabled',
        'current' => extension_loaded('pdo_sqlite') ? 'Enabled' : 'Not Available',
        'status' => extension_loaded('pdo_sqlite'),
        'fix' => 'Install the PHP PDO SQLite extension. Usually included with php-sqlite3 package or enable in php.ini'
    );
    if (!$requirements['pdo_sqlite']['status']) $allPassed = false;
    
    // Check file permissions
    $dataDir = dirname(__FILE__) . '/data';
    $canWrite = is_writable(dirname(__FILE__));
    $requirements['write_permissions'] = array(
        'name' => 'Directory Write Permissions',
        'required' => 'Writable',
        'current' => $canWrite ? 'Writable' : 'Not Writable',
        'status' => $canWrite,
        'fix' => 'Make directory writable: chmod 755 ' . dirname(__FILE__) . ' or contact your system administrator'
    );
    if (!$requirements['write_permissions']['status']) $allPassed = false;
    
    // Check session support
    $requirements['session'] = array(
        'name' => 'Session Support',
        'required' => 'Enabled',
        'current' => extension_loaded('session') ? 'Enabled' : 'Not Available',
        'status' => extension_loaded('session'),
        'fix' => 'Session support should be built into PHP. Check your PHP installation or contact your hosting provider.'
    );
    if (!$requirements['session']['status']) $allPassed = false;
    
    // Check Apache .htaccess support
    $htaccess_supported = checkHtaccessSupport();
    $requirements['htaccess_support'] = array(
        'name' => 'Apache .htaccess Support',
        'required' => 'Working (for security)',
        'current' => $htaccess_supported['status'] ? 'Working' : $htaccess_supported['message'],
        'status' => $htaccess_supported['status'],
        'fix' => $htaccess_supported['fix'],
        'warning' => true  // This is a warning, not a hard requirement
    );
    // Note: Don't fail overall check for htaccess - it's a security enhancement, not a hard requirement
    
    return array('requirements' => $requirements, 'all_passed' => $allPassed);
}

function displayRequirementsCheck($showFailedOnly = false) {
    $check = checkSystemRequirements();
    
    echo '<div class="card mb-4">';
    echo '<div class="card-header ' . ($check['all_passed'] ? 'bg-success text-white' : 'bg-warning text-dark') . '">';
    echo '<h5 class="mb-0">System Requirements Check</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    
    if ($check['all_passed']) {
        echo '<div class="alert alert-success">All system requirements are met!</div>';
    } else {
        echo '<div class="alert alert-danger">Some requirements are not met. Please resolve these issues before proceeding.</div>';
    }
    
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm">';
    echo '<thead><tr><th>Requirement</th><th>Required</th><th>Current</th><th>Status</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($check['requirements'] as $req) {
        if ($showFailedOnly && $req['status']) continue;
        
        // Handle warnings differently from hard requirements
        if (isset($req['warning']) && $req['warning']) {
            $statusClass = $req['status'] ? 'text-success' : 'text-warning';
            $statusIcon = $req['status'] ? '✓' : '⚠';
        } else {
            $statusClass = $req['status'] ? 'text-success' : 'text-danger';
            $statusIcon = $req['status'] ? '✓' : '✗';
        }
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($req['name']) . '</td>';
        echo '<td>' . htmlspecialchars($req['required']) . '</td>';
        echo '<td>' . htmlspecialchars($req['current']) . '</td>';
        echo '<td class="' . $statusClass . '">' . $statusIcon . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
    
    // Show fix suggestions for failed requirements and warnings
    $failedRequirements = array_filter($check['requirements'], function($req) {
        return !$req['status'] && (!isset($req['warning']) || !$req['warning']);
    });
    
    $warnings = array_filter($check['requirements'], function($req) {
        return !$req['status'] && isset($req['warning']) && $req['warning'];
    });
    
    if (!empty($failedRequirements)) {
        echo '<div class="mt-3">';
        echo '<h6 class="text-danger">Critical Issues to Fix:</h6>';
        echo '<div class="accordion" id="fixAccordion">';
        
        $index = 0;
        foreach ($failedRequirements as $key => $req) {
            echo '<div class="accordion-item">';
            echo '<h2 class="accordion-header" id="heading' . $index . '">';
            echo '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' . $index . '" aria-expanded="false">';
            echo '<span class="text-danger me-2">✗</span>' . htmlspecialchars($req['name']);
            echo '</button>';
            echo '</h2>';
            echo '<div id="collapse' . $index . '" class="accordion-collapse collapse" data-bs-parent="#fixAccordion">';
            echo '<div class="accordion-body">';
            echo '<strong>Issue:</strong> ' . htmlspecialchars($req['current']) . '<br>';
            echo '<strong>Required:</strong> ' . htmlspecialchars($req['required']) . '<br>';
            echo '<strong>Solution:</strong> ' . htmlspecialchars($req['fix']);
            echo '</div>';
            echo '</div>';
            echo '</div>';
            $index++;
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    // Show security warnings/recommendations
    if (!empty($warnings)) {
        echo '<div class="mt-3">';
        echo '<h6 class="text-warning">Security Recommendations:</h6>';
        echo '<div class="accordion" id="warningAccordion">';
        
        $warningIndex = 1000; // Start from different index to avoid conflicts
        foreach ($warnings as $key => $req) {
            echo '<div class="accordion-item">';
            echo '<h2 class="accordion-header" id="warningHeading' . $warningIndex . '">';
            echo '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#warningCollapse' . $warningIndex . '" aria-expanded="false">';
            echo '<span class="text-warning me-2">⚠</span>' . htmlspecialchars($req['name']);
            echo '</button>';
            echo '</h2>';
            echo '<div id="warningCollapse' . $warningIndex . '" class="accordion-collapse collapse" data-bs-parent="#warningAccordion">';
            echo '<div class="accordion-body">';
            echo '<strong>Current Status:</strong> ' . htmlspecialchars($req['current']) . '<br>';
            echo '<strong>Recommended:</strong> ' . htmlspecialchars($req['required']) . '<br>';
            echo '<strong>How to Fix:</strong> ' . htmlspecialchars($req['fix']);
            echo '<div class="mt-2"><small class="text-muted">Note: This is a security enhancement and won\'t prevent TippingPoint from running.</small></div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            $warningIndex++;
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    
    return $check['all_passed'];
}

/**
 * Calculate fuel weight per unit based on fuel type and measurement units
 * 
 * @param string $fuel_type - "100LL/Mogas" or "Jet A"
 * @param string $fuel_unit - "Gallons", "Liters", "Pounds", "Kilograms"  
 * @param string $weight_unit - "Pounds" or "Kilograms"
 * @return float - Weight per unit of fuel
 */
function getFuelWeightPerUnit($fuel_type, $fuel_unit, $weight_unit) {
    // Base fuel densities in pounds per gallon
    $fuel_densities = [
        '100LL/Mogas' => 6.0,    // 6 lbs/gallon
        'Jet A' => 5.75          // 5.75 lbs/gallon
    ];
    
    if (!isset($fuel_densities[$fuel_type])) {
        return 0; // Unknown fuel type
    }
    
    $base_density = $fuel_densities[$fuel_type]; // lbs/gallon
    
    switch ($fuel_unit) {
        case 'Gallons':
            $weight_per_unit = $base_density; // lbs/gallon
            break;
            
        case 'Liters':
            // Convert from lbs/gallon to lbs/liter (1 gallon = 3.78541 liters)
            $weight_per_unit = $base_density / 3.78541; // lbs/liter
            break;
            
        case 'Pounds':
            $weight_per_unit = 1.0; // lbs/lb = 1
            break;
            
        case 'Kilograms':
            $weight_per_unit = 2.20462; // lbs/kg (1 kg = 2.20462 lbs)
            break;
            
        default:
            return 0; // Unknown fuel unit
    }
    
    // Convert to target weight unit if needed
    if ($weight_unit === 'Kilograms') {
        $weight_per_unit = $weight_per_unit / 2.20462; // Convert lbs to kg
    }
    
    return $weight_per_unit;
}

/**
 * Calculate total fuel weight based on quantity, fuel type, and units
 * 
 * @param float $quantity - Amount of fuel
 * @param string $fuel_type - "100LL/Mogas" or "Jet A"
 * @param string $fuel_unit - "Gallons", "Liters", "Pounds", "Kilograms"
 * @param string $weight_unit - "Pounds" or "Kilograms"
 * @return float - Total weight of fuel
 */
function calculateFuelWeight($quantity, $fuel_type, $fuel_unit, $weight_unit) {
    $weight_per_unit = getFuelWeightPerUnit($fuel_type, $fuel_unit, $weight_unit);
    return $quantity * $weight_per_unit;
}

?>
