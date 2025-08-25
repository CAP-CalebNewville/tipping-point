<?php
// Check if system is set up before trying to load database-dependent functions
include_once 'common.inc';
if (!isSystemInstalled()) {
    // Return an error image or redirect
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: text/plain');
    echo 'TippingPoint not configured. Please run setup.php first.';
    exit;
}

// System is set up, proceed normally
include_once 'func.inc';

// DEFINE VARIABLES
$chart_size = (isset($_GET["size"])) ? $_GET["size"] : "large";

$totarm_to = (isset($_GET["totarm_to"])) ? $_GET["totarm_to"] : "null";
$totwt_to = (isset($_GET["totwt_to"])) ? $_GET["totwt_to"] : "null";
$totarm_ldg = (isset($_GET["totarm_ldg"])) ? $_GET["totarm_ldg"] : "null";
$totwt_ldg = (isset($_GET["totwt_ldg"])) ? $_GET["totwt_ldg"] : "null";

// GET AIRCRAFT DATA
$aircraft_result = $db->query("SELECT weight_units, arm_units, max_landing_weight FROM aircraft WHERE id = ?", [$_REQUEST['tailnumber']]);
$aircraft = $db->fetchAssoc($aircraft_result);
$weight_units = $aircraft ? $aircraft['weight_units'] : 'Pounds'; // Default to Pounds if not found
$arm_units = $aircraft ? $aircraft['arm_units'] : 'Inches'; // Default to Inches if not found
$max_landing_weight = $aircraft ? $aircraft['max_landing_weight'] : null;

// GET AIRCRAFT CG ENVELOPE DATA
$envelope_name = isset($_GET['envelope']) ? $_GET['envelope'] : null;
$envelopes_data = [];

// Check if envelope_name column exists for backward compatibility
$has_envelope_columns = $db->hasColumn('aircraft_cg', 'envelope_name');

if ($has_envelope_columns) {
	if ($envelope_name) {
		// Single envelope mode (from admin.php) - show only specified envelope
		$result = $db->query("SELECT * FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ? ORDER BY id", [$_REQUEST['tailnumber'], $envelope_name]);
		$envelope_points = [];
		$envelope_color = 'blue';
		
		while($row = $db->fetchAssoc($result)) {
			// Skip 0/0 points that might be placeholders
			if ($row['arm'] == 0 && $row['weight'] == 0) {
				continue;
			}
			$envelope_points[] = ['arm' => $row['arm'], 'weight' => $row['weight']];
			if (isset($row['color'])) {
				$envelope_color = $row['color'];
			}
		}
		
		if (!empty($envelope_points)) {
			$envelopes_data[] = [
				'name' => $envelope_name,
				'color' => $envelope_color,
				'points' => $envelope_points
			];
		}
	} else {
		// Multiple envelope mode (from index.php) - show all envelopes
		$envelope_result = $db->query("SELECT DISTINCT envelope_name, color FROM aircraft_cg WHERE tailnumber = ? ORDER BY envelope_name", [$_REQUEST['tailnumber']]);
		
		while ($envelope = $db->fetchAssoc($envelope_result)) {
			$result = $db->query("SELECT * FROM aircraft_cg WHERE tailnumber = ? AND envelope_name = ? ORDER BY id", [$_REQUEST['tailnumber'], $envelope['envelope_name']]);
			$envelope_points = [];
			
			while($row = $db->fetchAssoc($result)) {
				// Skip 0/0 points that might be placeholders
				if ($row['arm'] == 0 && $row['weight'] == 0) {
					continue;
				}
				$envelope_points[] = ['arm' => $row['arm'], 'weight' => $row['weight']];
			}
			
			if (!empty($envelope_points)) {
				$envelopes_data[] = [
					'name' => $envelope['envelope_name'],
					'color' => $envelope['color'],
					'points' => $envelope_points
				];
			}
		}
	}
} else {
	// Fallback for old database structure - single envelope
	$result = $db->query("SELECT * FROM aircraft_cg WHERE tailnumber = ? ORDER BY id", [$_REQUEST['tailnumber']]);
	$envelope_points = [];
	
	while($row = $db->fetchAssoc($result)) {
		// Skip 0/0 points that might be placeholders
		if ($row['arm'] == 0 && $row['weight'] == 0) {
			continue;
		}
		$envelope_points[] = ['arm' => $row['arm'], 'weight' => $row['weight']];
	}
	
	if (!empty($envelope_points)) {
		$envelopes_data[] = [
			'name' => 'CG Envelope',
			'color' => 'blue',
			'points' => $envelope_points
		];
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Weight and Balance Chart</title>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<style>
body {
  margin: 0;
  padding: 0;
  overflow: hidden;
}
#chart_div {
  max-width: 100%;
  max-height: 100%;
  overflow: hidden;
}
</style>
</head>
<body>
<?php
	if ($chart_size=="small") {
		echo "<div id=\"chart_div\" style=\"width: 400px; height: 195px\"></div>\n";
	} elseif ($chart_size=="print") {
		echo "<div id=\"chart_div\" style=\"width: 720px; height: 950px\"></div>\n";
	} elseif ($chart_size=="large") {
		echo "<div id=\"chart_div\" style=\"width: 700px; height: 340px\"></div>\n";
	}
?>

<script type="text/javascript">

google.charts.load('current', {
  packages: ['corechart', 'line']
});
google.charts.setOnLoadCallback(drawBackgroundColor);

function drawBackgroundColor() {
  var data = new google.visualization.DataTable();
  data.addColumn('number', 'Weight');
  
  // Add columns for each envelope
<?php
foreach ($envelopes_data as $envelope) {
	echo "  data.addColumn('number', '" . htmlspecialchars($envelope['name']) . "');\n";
}
?>
  // Add columns for takeoff and landing points
  data.addColumn('number', 'Takeoff');
  data.addColumn('number', 'Landing');
<?php if ($max_landing_weight !== null): ?>
  data.addColumn('number', 'MLW');
<?php endif; ?>

  // Build chart data
  var chartData = [];
  
<?php
if (!empty($envelopes_data)) {
	// Find the maximum number of points across all envelopes
	$max_points = 0;
	foreach ($envelopes_data as $envelope) {
		$max_points = max($max_points, count($envelope['points']));
	}
	
	// Output each point as a row, plus closing point for each envelope
	for ($point_index = 0; $point_index <= $max_points; $point_index++) {
		echo "  chartData.push([";
		
		// X-axis value (arm) - use the first available envelope's arm value for this point
		$arm_value = 'null';
		foreach ($envelopes_data as $envelope) {
			if ($point_index == $max_points) {
				// Closing point - use first point to close the envelope
				if (isset($envelope['points'][0])) {
					$arm_value = $envelope['points'][0]['arm'];
					break;
				}
			} else if (isset($envelope['points'][$point_index])) {
				$arm_value = $envelope['points'][$point_index]['arm'];
				break;
			}
		}
		echo $arm_value . ", ";
		
		// Y values for each envelope
		foreach ($envelopes_data as $envelope) {
			if ($point_index == $max_points) {
				// Closing point - use first point to close the envelope
				if (isset($envelope['points'][0])) {
					echo $envelope['points'][0]['weight'];
				} else {
					echo "null";
				}
			} else if (isset($envelope['points'][$point_index])) {
				echo $envelope['points'][$point_index]['weight'];
			} else {
				echo "null";
			}
			echo ", ";
		}
		
		// Takeoff and landing points (always null in envelope data)
		echo "null, null";
		
		// MLW point (always null in envelope data)
		if ($max_landing_weight !== null) {
			echo ", null";
		}
		
		echo "]);\n";
	}
}

// Add separate points for takeoff and landing if they exist
if ($totarm_to != 'null' && $totwt_to != 'null') {
	echo "  chartData.push([" . $totarm_to . ", ";
	for ($i = 0; $i < count($envelopes_data); $i++) {
		echo "null, ";
	}
	echo $totwt_to . ", null";
	if ($max_landing_weight !== null) {
		echo ", null";
	}
	echo "]);\n";
}

if ($totarm_ldg != 'null' && $totwt_ldg != 'null') {
	echo "  chartData.push([" . $totarm_ldg . ", ";
	for ($i = 0; $i < count($envelopes_data); $i++) {
		echo "null, ";
	}
	echo "null, " . $totwt_ldg;
	if ($max_landing_weight !== null) {
		echo ", null";
	}
	echo "]);\n";
}

// Add MLW horizontal line if specified
if ($max_landing_weight !== null) {
	echo "// Find the arm range for the MLW line\n";
echo "var armRange = [];\n";
echo "for (var i = 0; i < chartData.length; i++) {\n";
echo "  if (chartData[i][0] !== null) {\n";
echo "    armRange.push(chartData[i][0]);\n";
echo "  }\n";
echo "}\n";
echo "if (armRange.length > 0) {\n";
echo "  var minArm = Math.min.apply(Math, armRange);\n";
echo "  var maxArm = Math.max.apply(Math, armRange);\n";
echo "  var armSpan = maxArm - minArm;\n";
echo "  var mlwStartArm = minArm - (armSpan * 0.1); // Extend 10% beyond data\n";
echo "  var mlwEndArm = maxArm + (armSpan * 0.1);   // Extend 10% beyond data\n";
echo "  \n";
echo "  // Add MLW line start point\n";
echo "  chartData.push([mlwStartArm, ";
for ($i = 0; $i < count($envelopes_data); $i++) {
  echo "null, ";
}
echo "null, null, " . $max_landing_weight . "]);\n";
echo "  \n";
echo "  // Add MLW line end point\n";
echo "  chartData.push([mlwEndArm, ";
for ($i = 0; $i < count($envelopes_data); $i++) {
  echo "null, ";
}
echo "null, null, " . $max_landing_weight . "]);\n";
echo "}\n";
}
?>

  data.addRows(chartData);

  var options = {
    hAxis: {
      title: '<?php echo htmlspecialchars($arm_units); ?> From Reference Datum'
    },
    vAxis: {
      title: '<?php echo htmlspecialchars($weight_units); ?>',
      textPosition: 'out'
    },
    backgroundColor: '#ffffff',
    series: {
<?php
// Configure series for each envelope
foreach ($envelopes_data as $index => $envelope) {
	echo "      " . $index . ": {\n";
	echo "        color: '" . $envelope['color'] . "',\n";
	echo "        visibleInLegend: true\n";
	echo "      }";
	if ($index < count($envelopes_data) - 1) echo ",";
	echo "\n";
}

// Add takeoff and landing series
$takeoff_index = count($envelopes_data);
$landing_index = count($envelopes_data) + 1;

if (count($envelopes_data) > 0) echo ",\n";
?>
      <?php echo $takeoff_index; ?>: {
        color: 'green',
        pointShape: 'circle',
        pointSize: 8,
        <?php if ($chart_size=="small") {
            echo "visibleInLegend: false\n";
        } else { 
            echo "visibleInLegend: true\n"; 
        } ?>
      },
      <?php echo $landing_index; ?>: {
        color: 'red',
        pointShape: 'circle',
        pointSize: 8,
        <?php if ($chart_size=="small") {
            echo "visibleInLegend: false\n";
        } else { 
            echo "visibleInLegend: true\n"; 
        } ?>
      }<?php if ($max_landing_weight !== null): ?>,
      <?php echo $landing_index + 1; ?>: {
        color: 'orange',
        lineWidth: 2,
        pointShape: 'none',
        pointSize: 0,
        <?php if ($chart_size=="small") {
            echo "visibleInLegend: false\n";
        } else { 
            echo "visibleInLegend: true\n"; 
        } ?>
      }<?php endif; ?>
    },
    chartArea: {
        <?php if ($chart_size=="small") {
            echo "left: 60, top: 10, right: 10, bottom: 30\n";
        } else {
            echo "left: 60, top: 10, right: 10, bottom: 40\n";
        } ?>
    },
    legend: {
      position: 'in'
    },
    pointSize: 5
  };

  var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
  
  // Listen for when chart is ready
  google.visualization.events.addListener(chart, 'ready', function() {
    // Signal to parent window that chart is loaded
    if (window.parent) {
      window.parent.postMessage('chartReady', '*');
    }
  });
  
  chart.draw(data, options);
}

</script>
</body>
</html>