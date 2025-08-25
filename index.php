<?php 
// Check if system is set up before trying to load database-dependent functions
include_once 'common.inc';
if (!isSystemInstalled()) {
    // System not set up, redirect to setup
    PageHeader('Setup Required');
    ?>
    <style>
    /* Override navbar padding for main interface */
    body { padding-top: 0 !important; }
    </style>
    
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

// Check if upgrade is needed before proceeding
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

PageHeader($config['site_name']); ?>
<style>
/* Override navbar padding for main interface */
body { padding-top: 0 !important; }

@media print {
  @page { margin: 0.25in; }
  .container { max-width: none; padding: 0; margin: 0; }
  .card { border: none; box-shadow: none; margin: 0; }
  .card-body { padding: 0; }
  .noprint { display: none; }
  input { border: none; background: transparent; padding: 0; }
}
</style>

<?php
if ($_REQUEST['tailnumber']=="") {
// NO AIRCRAFT SPECIFIED, SHOW ACTIVE AIRCRAFT LIST
	echo "<body>\n";
	echo "<div class=\"container\">\n";
	echo "<div class=\"row justify-content-center\">\n";
	echo "<div class=\"col-lg-6 col-md-8\">\n";
	echo "<div class=\"card mt-4\">\n";
	echo "<div class=\"card-header bg-primary text-white text-center\">\n";
	echo "<h3 class=\"mb-0\">" . $config['site_name'] . "</h3>\n";
	echo "</div>\n";
	echo "<div class=\"card-body\">\n";
	if ($_REQUEST['message']=="invalid") { echo "<div class=\"alert alert-warning text-center\">You have selected an invalid aircraft.</div>\n\n";
        } elseif ($_REQUEST['message']=="inactive") { echo "<div class=\"alert alert-warning text-center\">The aircraft you have selected is currently inactive.</div>\n\n"; }
	echo "<h5 class=\"card-title\">Select Aircraft</h5>\n";
	echo "<p class=\"text-muted\">Choose an aircraft to perform weight and balance calculations.</p>\n";

	echo "<form method=\"get\" action=\"index.php\">\n";
	echo "<div class=\"mb-3\">\n";
	echo "<label for=\"tailnumber\" class=\"form-label\">Aircraft</label>\n";
	AircraftListActive();
	echo "</div>\n";
	echo "<div class=\"d-grid\">\n";
	echo "<button type=\"submit\" class=\"btn btn-primary\">Continue</button>\n";
	echo "</div>\n";
	echo "</form>\n";

	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";
	echo "</div>\n";

} else {
// TAILNUMBER PROVIDED, VALIDATE
	// GET AIRCRAFT INFORMATION
	$aircraft_result = $db->query("SELECT * FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
	$aircraft = $db->fetchAssoc($aircraft_result);

	if (!$aircraft) {
		header("Location: http://" . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"] . "?message=invalid");
		exit;
	} elseif ($aircraft['active']=="0") {
		header("Location: http://" . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"] . "?message=inactive");
		exit;
	}

	// Cache weights data - used multiple times throughout the page
	$weights_data = [];
	$weights_query = $db->query("SELECT * FROM aircraft_weights WHERE tailnumber = ? ORDER BY \"order\" ASC", [$aircraft['id']]);
	while($weights = $db->fetchAssoc($weights_query)) {
		$weights_data[] = $weights;
	}

?>


<script type="text/javascript">

<!-- Hide script

<?php
// Add CG envelope data for validation (global scope)
echo "// CG Envelope data for validation\n";
echo "var envelopes = [];\n";

// Get envelope data for JavaScript validation - Optimized single query
$envelope_data_query = $db->query("SELECT envelope_name, arm, weight FROM aircraft_cg WHERE tailnumber = ? AND (arm != 0 OR weight != 0) ORDER BY envelope_name, id", [$aircraft['id']]);
$envelopes_grouped = [];
while ($row = $db->fetchAssoc($envelope_data_query)) {
    if (!isset($envelopes_grouped[$row['envelope_name']])) {
        $envelopes_grouped[$row['envelope_name']] = [];
    }
    $envelopes_grouped[$row['envelope_name']][] = ['arm' => (float)$row['arm'], 'weight' => (float)$row['weight']];
}

foreach ($envelopes_grouped as $envelope_name => $points) {
    if (!empty($points)) {
        echo "envelopes.push({\n";
        echo "    name: '" . addslashes($envelope_name) . "',\n";
        echo "    points: " . json_encode($points) . "\n";
        echo "});\n";
    }
}
echo "\n";
?>
function WeightBal() {
var df = document.forms[0];

<?php

foreach($weights_data as $weights) {
	if (isset($weights['type']) && $weights['type'] == 'Fuel') {
		echo "df.line" . $weights['id'] . "_gallons_to.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_gallons_to"])) {echo($weights['weight']);} else { echo($_REQUEST["line" . $weights['id'] . "_gallons_to"]); }
			echo ";\n";
		$fuel_weight_per_unit = getFuelWeightPerUnit($aircraft['fuel_type'], $aircraft['fuelunit'], $aircraft['weight_units']);
		echo "df.line" . $weights['id'] . "_wt_to.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_gallons_to"])) {echo(($weights['weight'] * $fuel_weight_per_unit));} else {echo(($_REQUEST["line" . $weights['id'] . "_gallons_to"] * $fuel_weight_per_unit));}
			echo ";\n";
		echo "df.line" . $weights['id'] . "_gallons_ldg.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_gallons_ldg"])) {echo($weights['weight']);} else { echo($_REQUEST["line" . $weights['id'] . "_gallons_ldg"]); }
			echo ";\n";
		echo "df.line" . $weights['id'] . "_wt_ldg.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_gallons_ldg"])) {echo(($weights['weight'] * $fuel_weight_per_unit));} else {echo(($_REQUEST["line" . $weights['id'] . "_gallons_ldg"] * $fuel_weight_per_unit));}
			echo ";\n";
	} else {
		if (isset($weights['type']) && $weights['type'] == 'Fixed Weight Removable') {
			// For Fixed Weight Removable items, set checkbox state
			echo "df.line" . $weights['id'] . "_installed.checked = ";
			if (!empty($_REQUEST["line" . $weights['id'] . "_installed"])) {
				// Use form state if available (user interaction)
				echo "true";
			} elseif (!empty($weights['weight_limit']) && $weights['weight_limit'] == 1) {
				// Use database default state
				echo "true";
			} else {
				echo "false";
			}
			echo ";\n";
		} else {
			echo "df.line" . $weights['id'] . "_wt.value = ";
				if (empty($_REQUEST["line" . $weights['id'] . "_wt"])) { echo($weights['weight']); } else { echo($_REQUEST["line" . $weights['id'] . "_wt"]); }
				echo  ";\n";
		}
	}
	echo "df.line" . $weights['id'] . "_arm.value = Number(" . $weights['arm'] . ").toFixed(1);\n\n";
}

?>

  Process();
}

function Process() {
  var df = document.forms[0];

<?php

// Initialize arrays for moment and weight calculations
$momentlist = array("");
$wtlist = array("");
$momentlist_to = array("");
$wtlist_to = array("");
$momentlist_ldg = array("");
$wtlist_ldg = array("");

foreach($weights_data as $weights) {
	echo "var line" . $weights['id'] . "_arm = Number(df.line" . $weights['id'] ."_arm.value);" . "\n";
	if (isset($weights['type']) && $weights['type'] == 'Fuel') {
		echo "var line" . $weights['id'] . "_gallons_to = df.line" . $weights['id'] . "_gallons_to.value;\n";
		$fuel_weight_per_unit = getFuelWeightPerUnit($aircraft['fuel_type'], $aircraft['fuelunit'], $aircraft['weight_units']);
		echo "var line" . $weights['id'] . "_wt_to = line" . $weights['id'] . "_gallons_to * " . $fuel_weight_per_unit . ";\n";
		echo "df.line" . $weights['id'] . "_wt_to.value = (line" . $weights['id'] . "_gallons_to * " . $fuel_weight_per_unit . ").toFixed(1);\n";
		echo "var line" . $weights['id'] . "_mom_to = line" . $weights['id'] . "_wt_to * line" . $weights['id'] . "_arm;\n";
		echo "df.line" . $weights['id'] . "_mom_to.value = line" . $weights['id'] . "_mom_to.toFixed(1);\n";

		echo "var line" . $weights['id'] . "_gallons_ldg = df.line" . $weights['id'] . "_gallons_ldg.value;\n";
		echo "var line" . $weights['id'] . "_wt_ldg = line" . $weights['id'] . "_gallons_ldg * " . $fuel_weight_per_unit . ";\n";
		echo "df.line" . $weights['id'] . "_wt_ldg.value = (line" . $weights['id'] . "_gallons_ldg * " . $fuel_weight_per_unit . ").toFixed(1);\n";
		echo "var line" . $weights['id'] . "_mom_ldg = line" . $weights['id'] . "_wt_ldg * line" . $weights['id'] . "_arm;\n";
		echo "df.line" . $weights['id'] . "_mom_ldg.value = line" . $weights['id'] . "_mom_ldg.toFixed(1);\n\n";

		$momentlist_to[0] = $momentlist_to[0] . " -line" . $weights['id'] . "_mom_to";
		$wtlist_to[0] = $wtlist_to[0] . " -line" . $weights['id'] . "_wt_to";
		$momentlist_ldg[0] = $momentlist_ldg[0] . " -line" . $weights['id'] . "_mom_ldg";
		$wtlist_ldg[0] = $wtlist_ldg[0] . " -line" . $weights['id'] . "_wt_ldg";
	} else {
		if (isset($weights['type']) && $weights['type'] == 'Fixed Weight Removable') {
			echo "var line" . $weights['id'] . "_installed = df.line" . $weights['id'] . "_installed.checked;\n";
			echo "var line" . $weights['id'] . "_wt = line" . $weights['id'] . "_installed ? " . $weights['weight'] . " : 0;\n";
			echo "var line" . $weights['id'] . "_mom = (Number(line" . $weights['id'] . "_wt) * Number(line" . $weights['id'] . "_arm));\n";
			echo "df.line" . $weights['id'] . "_mom.value = Number(line" . $weights['id'] . "_mom).toFixed(1);\n\n";
		} else {
			echo "var line" . $weights['id'] . "_wt = Number(df.line" . $weights['id'] . "_wt.value);" . "\n";
			echo "var line" . $weights['id'] . "_mom = (Number(line" . $weights['id'] . "_wt) * Number(line" . $weights['id'] . "_arm));\n";
			echo "df.line" . $weights['id'] . "_mom.value = Number(line" . $weights['id'] . "_mom).toFixed(1);\n\n";
		}

		$momentlist[0] = $momentlist[0] . " -line" . $weights['id'] . "_mom";
		$wtlist[0] = $wtlist[0] . " -line" . $weights['id'] . "_wt";
	}
}
echo "var totmom_to = -1 * (" . print_r($momentlist[0],TRUE) . print_r($momentlist_to[0],TRUE) . ");\n";
echo "df.totmom_to.value = totmom_to.toFixed(1);\n";
echo "var totmom_ldg = -1 * (" . print_r($momentlist[0],TRUE) . print_r($momentlist_ldg[0],TRUE) . ");\n";
echo "df.totmom_ldg.value = totmom_ldg.toFixed(1);\n\n";

echo "var totwt_to = -1 * (" . print_r($wtlist[0],TRUE) . print_r($wtlist_to[0],TRUE) . ");\n";
echo "df.totwt_to.value = totwt_to.toFixed(1);\n";
echo "var totwt_ldg = -1 * (" . print_r($wtlist[0],TRUE) . print_r($wtlist_ldg[0],TRUE) . ");\n";
echo "df.totwt_ldg.value = totwt_ldg.toFixed(1);\n\n";

echo "var totarm_to = totmom_to / totwt_to;\n";
echo "df.totarm_to.value = Math.round(totarm_to*100)/100;\n\n";
echo "var totarm_ldg = totmom_ldg / totwt_ldg;\n";
echo "df.totarm_ldg.value = Math.round(totarm_ldg*100)/100;\n\n";

echo "var w1 = " . $aircraft['maxwt'] . ";\n";
echo "var w2 = " . $aircraft['emptywt'] . ";\n";
echo "var max_landing_weight = " . (isset($aircraft['max_landing_weight']) && $aircraft['max_landing_weight'] !== null ? $aircraft['max_landing_weight'] : 'null') . ";\n";
echo "var overt  = Math.round(totwt_to - " . $aircraft['maxwt'] . ");\n\n";

echo "\n";

echo "// Variables to track chart loading state\n";
echo "var isFirstLoad = true;\n";
echo "var spinnerTimeout = null;\n";
echo "var chartLoaded = false;\n\n";
echo "// Function to show loading spinner\n";
echo "function showChartLoading() {\n";
echo "  document.getElementById('chart-loading').style.display = 'flex';\n";
echo "  document.getElementById('wbimage').style.opacity = '0';\n";
echo "}\n\n";
echo "// Function to hide loading spinner\n";
echo "function hideChartLoading() {\n";
echo "  document.getElementById('chart-loading').style.display = 'none';\n";
echo "  document.getElementById('wbimage').style.opacity = '1';\n";
echo "  // Clear any pending spinner timeout\n";
echo "  if (spinnerTimeout) {\n";
echo "    clearTimeout(spinnerTimeout);\n";
echo "    spinnerTimeout = null;\n";
echo "  }\n";
echo "}\n\n";
echo "// Function to show chart (used for smooth transitions)\n";
echo "function showChart() {\n";
echo "  document.getElementById('chart-loading').style.display = 'none';\n";
echo "  document.getElementById('wbimage').style.opacity = '1';\n";
echo "}\n\n";
echo "// Function to load chart with delayed spinner\n";
echo "function loadChart() {\n";
echo "  chartLoaded = false;\n";
echo "  \n";
echo "  if (isFirstLoad) {\n";
echo "    // Show spinner immediately on first load\n";
echo "    showChartLoading();\n";
echo "    isFirstLoad = false;\n";
echo "  } else {\n";
echo "    // On subsequent loads, keep the old chart visible\n";
echo "    // Only show spinner if new chart takes longer than 500ms\n";
echo "    document.getElementById('wbimage').style.opacity = '1';\n";
echo "    document.getElementById('chart-loading').style.display = 'none';\n";
echo "    \n";
echo "    spinnerTimeout = setTimeout(function() {\n";
echo "      if (!chartLoaded) {\n";
echo "        showChartLoading();\n";
echo "      }\n";
echo "    }, 500);\n";
echo "  }\n";
echo "  \n";
echo "  document.getElementById('wbimage').setAttribute('src', 'scatter.php?tailnumber=" . $aircraft['id'] . "&totarm_to=' + totarm_to + '&totwt_to=' + totwt_to + '&totarm_ldg=' + totarm_ldg + '&totwt_ldg=' + totwt_ldg);\n";
echo "}\n\n";
echo "// Listen for chart ready message from iframe\n";
echo "window.addEventListener('message', function(event) {\n";
echo "  if (event.data === 'chartReady') {\n";
echo "    chartLoaded = true;\n";
echo "    hideChartLoading();\n";
echo "  }\n";
echo "});\n\n";
echo "// Load initial chart\n";
echo "loadChart();";

?>

// WARNINGS
if  (parseFloat(Math.round(totwt_to))>w1) {
        var message = "Based on the provided data, this aircraft will be <strong>overweight at takeoff</strong> by <strong>" + overt + " lbs</strong>.<br>";
        message += "<small class='text-muted'>Reduce weight or verify weight entries before flight.</small>";
        showWeightWarning("takeoff", message);
        inuse_flag = false;
    } else {
        clearWeightWarning("takeoff");
    }


// Check CG envelope violations
if (!checkCGEnvelope(totarm_to, totwt_to)) {
    showCGEnvelopeWarning("takeoff", totarm_to, totwt_to);
    inuse_flag = false;
} else {
    clearCGEnvelopeWarning("takeoff");
}

if (!checkCGEnvelope(totarm_ldg, totwt_ldg)) {
    showCGEnvelopeWarning("landing", totarm_ldg, totwt_ldg);
    inuse_flag = false;
} else {
    clearCGEnvelopeWarning("landing");
}

// Check maximum landing weight limit
if (max_landing_weight !== null && totwt_ldg > max_landing_weight) {
    var mlw_excess = Math.round(totwt_ldg - max_landing_weight);
    showMLWWarning("landing", totwt_ldg, max_landing_weight, mlw_excess);
    inuse_flag = false;
} else {
    clearMLWWarning("landing");
}

// Update chart with new values
loadChart();
 }
// -->

isamap = new Object();
isamap[0] = "_df"
isamap[1] = "_ov"
isamap[2] = "_ot"
isamap[3] = "_dn"

// Weight limit validation function
function validateWeightLimit(input, limit) {
    var value = parseFloat(input.value);
    if (!isNaN(value) && value > limit) {
        input.style.borderColor = '#dc3545';
        input.style.backgroundColor = '#f8d7da';
        input.title = 'Weight exceeds limit of ' + limit + '. Current value: ' + value;
        // Optionally reset to limit
        setTimeout(function() {
            if (confirm('Weight (' + value + ') exceeds the limit (' + limit + '). Set to maximum allowed?')) {
                input.value = limit;
                input.style.borderColor = '';
                input.style.backgroundColor = '';
                input.title = 'Maximum weight: ' + limit;
                Process(); // Recalculate after correction
            }
        }, 100);
    } else {
        input.style.borderColor = '';
        input.style.backgroundColor = '';
        input.title = 'Maximum weight: ' + limit;
    }
}

// Fuel validation function - ensure LDG fuel is not greater than TO fuel
function validateFuelBalance(input, fuelId) {
    var toInput = document.getElementsByName('line' + fuelId + '_gallons_to')[0];
    var ldgInput = document.getElementsByName('line' + fuelId + '_gallons_ldg')[0];
    
    if (!toInput || !ldgInput) return;
    
    var toValue = parseFloat(toInput.value) || 0;
    var ldgValue = parseFloat(ldgInput.value) || 0;
    
    if (ldgValue > toValue) {
        ldgInput.style.borderColor = '#dc3545';
        ldgInput.style.backgroundColor = '#f8d7da';
        ldgInput.title = 'Landing fuel (' + ldgValue + ') cannot exceed takeoff fuel (' + toValue + ')';
        
        setTimeout(function() {
            if (confirm('Landing fuel (' + ldgValue + ') cannot exceed takeoff fuel (' + toValue + '). Set landing fuel to takeoff fuel value?')) {
                ldgInput.value = toValue;
                ldgInput.style.borderColor = '';
                ldgInput.style.backgroundColor = '';
                ldgInput.title = '';
                Process(); // Recalculate after correction
            }
        }, 100);
    } else {
        ldgInput.style.borderColor = '';
        ldgInput.style.backgroundColor = '';
        ldgInput.title = '';
    }
}

// CG Envelope validation functions
function isPointInPolygon(point, polygon) {
    var x = point.arm, y = point.weight;
    var inside = false;
    
    for (var i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        var xi = polygon[i].arm, yi = polygon[i].weight;
        var xj = polygon[j].arm, yj = polygon[j].weight;
        
        if (((yi > y) != (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
            inside = !inside;
        }
    }
    
    return inside;
}

function checkCGEnvelope(arm, weight, phase) {
    if (!envelopes || envelopes.length === 0) return true; // No envelopes defined, skip check
    
    var point = {arm: arm, weight: weight};
    var insideAnyEnvelope = false;
    
    // Check if point is inside any envelope
    for (var i = 0; i < envelopes.length; i++) {
        if (isPointInPolygon(point, envelopes[i].points)) {
            insideAnyEnvelope = true;
            break;
        }
    }
    
    return insideAnyEnvelope;
}

function showCGEnvelopeWarning(phase, arm, weight) {
    var warningContainer = document.getElementById("cg-warnings");
    if (!warningContainer) {
        // Create warning container if it doesn't exist
        warningContainer = document.createElement("div");
        warningContainer.id = "cg-warnings";
        warningContainer.className = "mb-3";
        
        // Insert before the weight and balance table
        var tableContainer = document.querySelector(".card-body");
        tableContainer.insertBefore(warningContainer, tableContainer.firstChild);
    }
    
    // Create warning alert with prominent phase display
    var alertDiv = document.createElement("div");
    alertDiv.className = "alert alert-danger alert-dismissible";
    var phaseUpper = phase.toUpperCase();
    var phaseBadge = '<span class="badge bg-dark me-2">' + phaseUpper + '</span>';
    
    alertDiv.innerHTML = '<div class="d-flex align-items-center">' +
        '<i class="bi bi-exclamation-triangle-fill me-2" style="font-size: 1.2em;"></i>' +
        '<div class="flex-grow-1">' +
        '<div class="mb-1">' + phaseBadge + '<strong>CG ENVELOPE VIOLATION</strong></div>' +
        'The ' + phase + ' CG is <strong>OUTSIDE</strong> the approved envelope!<br>' +
        'CG: <strong>' + arm.toFixed(2) + '</strong> at <strong>' + weight.toFixed(1) + ' lbs</strong><br>' +
        '<small class="text-muted">This aircraft may be unsafe to fly in this configuration. Verify CG limits and redistribute weight as needed.</small>' +
        '</div>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
        '</div>';
    
    // Remove any existing warnings for this phase
    var existingWarnings = warningContainer.querySelectorAll('[data-phase="' + phase + '"]');
    existingWarnings.forEach(function(warning) {
        warning.remove();
    });
    
    // Add phase identifier for removal
    alertDiv.setAttribute('data-phase', phase);
    
    // Add to container
    warningContainer.appendChild(alertDiv);
}

function clearCGEnvelopeWarning(phase) {
    var warningContainer = document.getElementById("cg-warnings");
    if (warningContainer) {
        var existingWarnings = warningContainer.querySelectorAll('[data-phase="' + phase + '"]');
        existingWarnings.forEach(function(warning) {
            warning.remove();
        });
    }
}

function showWeightWarning(phase, message) {
    var warningContainer = document.getElementById("cg-warnings");
    if (!warningContainer) {
        // Create warning container if it doesn't exist
        warningContainer = document.createElement("div");
        warningContainer.id = "cg-warnings";
        warningContainer.className = "mb-3";
        
        // Insert before the weight and balance table
        var tableContainer = document.querySelector(".card-body");
        tableContainer.insertBefore(warningContainer, tableContainer.firstChild);
    }
    
    // Create warning alert
    var alertDiv = document.createElement("div");
    alertDiv.className = "alert alert-warning alert-dismissible";
    alertDiv.innerHTML = '<div class="d-flex align-items-center">' +
        '<i class="bi bi-exclamation-triangle-fill me-2" style="font-size: 1.2em;"></i>' +
        '<div class="flex-grow-1">' +
        '<strong>WEIGHT LIMIT EXCEEDED</strong><br>' +
        message +
        '</div>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
        '</div>';
    
    // Remove any existing weight warnings for this phase
    var existingWarnings = warningContainer.querySelectorAll('[data-warning-type="weight"][data-phase="' + phase + '"]');
    existingWarnings.forEach(function(warning) {
        warning.remove();
    });
    
    // Add identifiers for removal
    alertDiv.setAttribute('data-phase', phase);
    alertDiv.setAttribute('data-warning-type', 'weight');
    
    // Add to container
    warningContainer.appendChild(alertDiv);
}

function clearWeightWarning(phase) {
    var warningContainer = document.getElementById("cg-warnings");
    if (warningContainer) {
        var existingWarnings = warningContainer.querySelectorAll('[data-warning-type="weight"][data-phase="' + phase + '"]');
        existingWarnings.forEach(function(warning) {
            warning.remove();
        });
    }
}

function showMLWWarning(phase, actualWeight, maxWeight, excess) {
    var warningContainer = document.getElementById("cg-warnings");
    if (!warningContainer) {
        // Create warning container if it doesn't exist
        warningContainer = document.createElement("div");
        warningContainer.id = "cg-warnings";
        warningContainer.className = "mb-3";
        
        // Insert before the weight and balance table
        var tableContainer = document.querySelector(".card-body");
        tableContainer.insertBefore(warningContainer, tableContainer.firstChild);
    }
    
    // Create warning alert with prominent phase display
    var alertDiv = document.createElement("div");
    alertDiv.className = "alert alert-danger alert-dismissible";
    var phaseUpper = phase.toUpperCase();
    var phaseBadge = '<span class="badge bg-dark me-2">' + phaseUpper + '</span>';
    
    alertDiv.innerHTML = '<div class="d-flex align-items-center">' +
        '<i class="bi bi-exclamation-triangle-fill me-2" style="font-size: 1.2em;"></i>' +
        '<div class="flex-grow-1">' +
        '<div class="mb-1">' + phaseBadge + '<strong>MAXIMUM LANDING WEIGHT EXCEEDED</strong></div>' +
        'The ' + phase + ' weight is <strong>' + excess + ' <?php echo strtolower($aircraft['weight_units']); ?></strong> over the limit!<br>' +
        'Weight: <strong>' + actualWeight.toFixed(1) + ' <?php echo strtolower($aircraft['weight_units']); ?></strong> | MLW Limit: <strong>' + maxWeight + ' <?php echo strtolower($aircraft['weight_units']); ?></strong><br>' +
        '<small class="text-muted">Landing over maximum landing weight may violate aircraft limitations. Reduce fuel or payload before landing.</small>' +
        '</div>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
        '</div>';
    
    // Remove any existing MLW warnings for this phase
    var existingWarnings = warningContainer.querySelectorAll('[data-warning-type="mlw"][data-phase="' + phase + '"]');
    existingWarnings.forEach(function(warning) {
        warning.remove();
    });
    
    // Add identifiers for removal
    alertDiv.setAttribute('data-phase', phase);
    alertDiv.setAttribute('data-warning-type', 'mlw');
    
    // Add to container
    warningContainer.appendChild(alertDiv);
}

function clearMLWWarning(phase) {
    var warningContainer = document.getElementById("cg-warnings");
    if (warningContainer) {
        var existingWarnings = warningContainer.querySelectorAll('[data-warning-type="mlw"][data-phase="' + phase + '"]');
        existingWarnings.forEach(function(warning) {
            warning.remove();
        });
    }
}

// Prevent negative numbers in real-time
function preventNegativeInput(input) {
    input.addEventListener('input', function() {
        if (this.value < 0) {
            this.value = 0;
        }
    });
    
    // Also handle paste events
    input.addEventListener('paste', function() {
        var self = this;
        setTimeout(function() {
            if (self.value < 0) {
                self.value = 0;
            }
        }, 10);
    });
}

// Apply to all number inputs when page loads
document.addEventListener('DOMContentLoaded', function() {
    var numberInputs = document.querySelectorAll('input[type="number"]:not([readonly])');
    numberInputs.forEach(function(input) {
        preventNegativeInput(input);
    });
});

</script>
</head>

<body onload="WeightBal();">

<div class="container">
<div class="row justify-content-center">
<div class="col-lg-10 col-xl-8">
<div class="card mt-4">
<div class="card-header bg-primary text-white">
<div class="row align-items-center">
<div class="col-md-8">
<?php echo "<h4 class=\"mb-0\">" . $config['site_name'] . "</h4>";
echo "<h5 class=\"mb-0 text-light\">" . $aircraft['makemodel'] . " " . $aircraft['tailnumber'] . "</h5>";
	$updated_query = $db->query("SELECT timestamp FROM audit WHERE what LIKE ? ORDER BY timestamp DESC LIMIT 1", ['%' . $aircraft['tailnumber'] . '%']);
	$updated = $db->fetchAssoc($updated_query);
	echo "<small class=\"text-light\">Aircraft last updated: " . date("j M Y",strtotime($updated['timestamp'])-$timezoneoffset) . "</small>";
	?>
</div>
<div class="col-md-4 text-end">
<div class="row text-center">
<?php 
$has_mlw = isset($aircraft['max_landing_weight']) && $aircraft['max_landing_weight'] !== null;
$col_class = $has_mlw ? 'col-3' : 'col-4';
?>
<div class="<?php echo $col_class; ?>">
<small class="text-light d-block">Empty Wt</small>
<strong><?php echo $aircraft['emptywt']; ?></strong>
</div>
<div class="<?php echo $col_class; ?>">
<small class="text-light d-block">Empty CG</small>
<strong><?php echo $aircraft['emptycg']; ?></strong>
</div>
<div class="<?php echo $col_class; ?>">
<small class="text-light d-block">MGW</small>
<strong><?php echo $aircraft['maxwt']; ?></strong>
</div>
<?php if ($has_mlw): ?>
<div class="<?php echo $col_class; ?>">
<small class="text-light d-block">MLW</small>
<strong><?php echo $aircraft['max_landing_weight']; ?></strong>
</div>
<?php endif; ?>
</div>
</div>
</div>
</div>
<div class="card-body">

<form method="get" action="index.php"><input type="hidden" name="tailnumber" value="<?php echo($aircraft['id']); ?>">

<div class="table-responsive">
<table class="table table-sm table-bordered">
<thead class="table-primary">
<tr>
<th style="width:35%" colspan="2">Item</th>
<th style="width:25%" class="text-center">Weight (<?php echo strtolower($aircraft['weight_units']); ?>)</th>
<th style="width:20%" class="text-center">Arm</th>
<th style="width:20%" class="text-center">Moment</th>
</tr>
</thead>
<tbody>

<?php

foreach($weights_data as $weights) {
	echo "<tr><td";
	if (!isset($weights['type']) || $weights['type'] != 'Fuel') {
		echo " colspan=\"2\"";
	}
	echo " class=\"align-middle\">" . $weights['item'];
	// Add weight limit display if it exists (but not for Fixed Weight Removable items)
	if (isset($weights['weight_limit']) && $weights['weight_limit'] > 0 && (!isset($weights['type']) || $weights['type'] != 'Fixed Weight Removable')) {
		echo " <span class=\"text-muted small\">(max " . $weights['weight_limit'] . ")</span>";
	}
	// Add weight display for Fixed Weight Removable items
	if (isset($weights['type']) && $weights['type'] == 'Fixed Weight Removable') {
		echo " <span class=\"text-muted small\">(" . $weights['weight'] . " " . strtolower($aircraft['weight_units']) . ")</span>";
	}
	echo "</td>\n";
	if (isset($weights['type']) && $weights['type'] == 'Fuel') {
		echo "<td class=\"text-start align-middle\">";
		echo "<div class=\"d-flex align-items-center gap-2 mb-1\"><input type=\"number\" step=\"any\" min=\"0\" name=\"line" . $weights['id'] . "_gallons_to\" tabindex=\"" . $tabindex . "\" onblur=\"Process(); validateFuelBalance(this, " . $weights['id'] . ");\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\"><small class=\"text-muted text-nowrap\">" . $aircraft['fuelunit'] . " TO</small></div>\n";
		echo "<div class=\"d-flex align-items-center gap-2\"><input type=\"number\" step=\"any\" min=\"0\" name=\"line" . $weights['id'] . "_gallons_ldg\" tabindex=\"" . $tabindex . "\" onblur=\"Process(); validateFuelBalance(this, " . $weights['id'] . ");\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\"><small class=\"text-muted text-nowrap\">" . $aircraft['fuelunit'] . " LDG</small></div>";
		$tabindex++; echo "</td>\n";
		echo "<td class=\"text-center align-middle\"><div class=\"mb-1\"><input type=\"number\" name=\"line" . $weights['id'] . "_wt_to\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block; background-color: #f8f9fa;\"></div>";
		echo "<div><input type=\"number\" name=\"line" . $weights['id'] . "_wt_ldg\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block; background-color: #f8f9fa;\"></div></td>\n";
	} else {
		if (isset($weights['type']) && $weights['type'] == 'Empty Weight') {
			echo "<td class=\"text-center align-middle\"><input type=\"number\" name=\"line" . $weights['id'] . "_wt\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block; background-color: #f8f9fa;\"></td>\n";
		} elseif (isset($weights['type']) && $weights['type'] == 'Fixed Weight Removable') {
			echo "<td class=\"text-center align-middle\">";
			echo "<div class=\"form-check d-inline-block\">";
			echo "<input type=\"checkbox\" name=\"line" . $weights['id'] . "_installed\" id=\"line" . $weights['id'] . "_installed\" tabindex=\"" . $tabindex . "\" onchange=\"Process()\" class=\"form-check-input\" style=\"transform: scale(1.2);\"";
			// Check if this item should be checked (form state or database default)
			if (!empty($_REQUEST["line" . $weights['id'] . "_installed"])) {
				// Use form state if available (user interaction)
				echo " checked";
			} elseif (!empty($weights['weight_limit']) && $weights['weight_limit'] == 1) {
				// Use database default state
				echo " checked";
			}
			echo ">";
			echo "<input type=\"hidden\" name=\"line" . $weights['id'] . "_wt\" value=\"" . $weights['weight'] . "\">";
			echo "</div>";
			echo "</td>\n";
		} else {
			echo "<td class=\"text-center align-middle\"><input type=\"number\" step=\"any\" min=\"0\" name=\"line" . $weights['id'] . "_wt\" tabindex=\"" . $tabindex . "\" onblur=\"Process()\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\"";
			// Add max attribute and validation if there's a weight limit
			if (isset($weights['weight_limit']) && $weights['weight_limit'] > 0) {
				echo " max=\"" . $weights['weight_limit'] . "\" title=\"Maximum weight: " . $weights['weight_limit'] . "\" oninput=\"validateWeightLimit(this, " . $weights['weight_limit'] . ")\"";
			}
			echo "></td>\n";
		}
	}
	echo "<td class=\"text-center align-middle\"><input type=\"number\" name=\"line" . $weights['id'] . "_arm\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block; background-color: #f8f9fa;\"></td>\n";
	if (isset($weights['type']) && $weights['type'] == 'Fuel') {
		echo "<td class=\"text-center align-middle\"><div class=\"mb-1\"><input type=\"number\" name=\"line" . $weights['id'] . "_mom_to\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 90px; display: inline-block; background-color: #f8f9fa;\">";
		echo "</div><div><input type=\"number\" name=\"line" . $weights['id'] . "_mom_ldg\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 90px; display: inline-block; background-color: #f8f9fa;\">";
	} else {
		echo "<td class=\"text-center align-middle\"><div><input type=\"number\" name=\"line" . $weights['id'] . "_mom\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 90px; display: inline-block; background-color: #f8f9fa;\">";
	}
	echo "</div></td></tr>\n\n";
	$tabindex++;
}

?>

</tbody>
<tfoot class="table-warning">
<tr>
<td class="text-end fw-bold align-middle" colspan="2">
<div>Totals at Takeoff</div>
<div>Landing</div>
</td>
<td class="text-center align-middle">
<div class="mb-1"><input type="number" name="totwt_to" readonly class="form-control form-control-sm text-center fw-bold" style="width: 95px; display: inline-block; background-color: #fff3cd;"></div>
<div><input type="number" name="totwt_ldg" readonly class="form-control form-control-sm text-center fw-bold" style="width: 95px; display: inline-block; background-color: #fff3cd;"></div>
</td>
<td class="text-center align-middle">
<div class="mb-1"><input type="number" name="totarm_to" readonly class="form-control form-control-sm text-center fw-bold" style="width: 95px; display: inline-block; background-color: #fff3cd;"></div>
<div><input type="number" name="totarm_ldg" readonly class="form-control form-control-sm text-center fw-bold" style="width: 95px; display: inline-block; background-color: #fff3cd;"></div>
</td>
<td class="text-center align-middle">
<div class="mb-1"><input type="number" name="totmom_to" readonly class="form-control form-control-sm text-center fw-bold" style="width: 105px; display: inline-block; background-color: #fff3cd;"></div>
<div><input type="number" name="totmom_ldg" readonly class="form-control form-control-sm text-center fw-bold" style="width: 105px; display: inline-block; background-color: #fff3cd;"></div>
</td>
</tr>
</tfoot>
</table>
</div>
<div class="mt-4">
<?php
echo "<div class=\"text-center mb-3\" style=\"position: relative;\">\n";
echo "<iframe id=\"wbimage\" src=\"data:text/html,<html><body style='margin:0;display:flex;align-items:center;justify-content:center;height:100vh;font-family:system-ui'><div class='spinner-border text-primary' role='status'><span class='visually-hidden'>Loading...</span></div></body></html>\" width=\"100%\" height=\"360\" style=\"border:0px; max-width: 710px; opacity: 0;\"></iframe>\n";
echo "<div id=\"chart-loading\" style=\"position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 710px; height: 360px; display: flex; align-items: center; justify-content: center; background: white; border: 1px solid #ddd;\">\n";
echo "<div class=\"spinner-border text-primary\" role=\"status\"><span class=\"visually-hidden\">Loading chart...</span></div>\n";
echo "</div>\n";
echo "</div>\n";
?>

<?php if (isset($config['pilot_signature']) && $config['pilot_signature'] == '1') { ?>
<div class="alert alert-info d-none d-print-block">
<strong>Pilot In Command:</strong> X_________________________________________________<br>
<small>I have verified all calculations are correct.<br>
<?php echo date("D, j M Y H:i:s T"); ?></small>
</div>
<?php } ?>

<div class="d-flex flex-wrap justify-content-center align-items-center gap-2 noprint">
<button type="submit" name="Submit" class="btn btn-primary" tabindex="<?php echo($tabindex); $tabindex++; ?>" onClick="Process()">Calculate</button>
<button type="button" name="Reset" class="btn btn-outline-primary" onclick="WeightBal()">Reset</button>
<button type="button" class="btn btn-outline-primary" onClick="window.print()">Print</button>
<a href="index.php" class="btn btn-outline-primary">Choose Another Aircraft</a>
</div>

</form>

</div>

</div>
</div>
</div>
</div>

<?php
}
?>

<?php PageFooter($config['site_name'],$config['administrator'],$ver);
// mysqli_close();
?>
