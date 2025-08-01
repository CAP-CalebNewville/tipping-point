<?php
// Check if system is set up before trying to load database-dependent functions
if (!file_exists('config.inc')) {
    // Return an error image or redirect
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: text/plain');
    echo 'TippingPoint not configured. Please run setup.php first.';
    exit;
}

// System is set up, proceed normally
include 'func.inc';

// DEFINE VARIABLES
$chart_size = (isset($_GET["size"])) ? $_GET["size"] : "large";
$plot_envelope = "";

$totarm_to = (isset($_GET["totarm_to"])) ? $_GET["totarm_to"] : "null";
$totwt_to = (isset($_GET["totwt_to"])) ? $_GET["totwt_to"] : "null";
$totarm_ldg = (isset($_GET["totarm_ldg"])) ? $_GET["totarm_ldg"] : "null";
$totwt_ldg = (isset($_GET["totwt_ldg"])) ? $_GET["totwt_ldg"] : "null";

// GET AIRCRAFT CG ENVELOPE DATA
$stmt = mysqli_prepare($con, "SELECT * FROM aircraft_cg WHERE tailnumber = ?");
mysqli_stmt_bind_param($stmt, 'i', $_REQUEST['tailnumber']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while($row = mysqli_fetch_array($result)) {
	$arm[] = $row['arm'];
	$weight[] = $row['weight'];
	$plot_envelope = $plot_envelope . "[" . $row['arm'] . ", " . $row['weight'] . ", null, null],\n";
}
	// We have to add the first point back to the end so we have a connected graph
	$arm[] = $arm[0];
	$weight[] = $weight[0];
	$plot_envelope = $plot_envelope . "[" . $arm[0] . ", " . $weight[0] . ", null, null]";

// NEW CODE HERE ///////////////////////////////////////////////////////////////
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
  data.addColumn('number', 'CG Envelope');
  data.addColumn('number', 'Takeoff');
  data.addColumn('number', 'Landing');

  data.addRows([
<?php
		echo $plot_envelope . ",\n";
		echo "[" . $totarm_to . ", null, " . $totwt_to . ", null],\n";
		echo "[" . $totarm_ldg . ", null, null, " . $totwt_ldg . "]\n";
		?>
  ]);

  var options = {
    hAxis: {
      title: 'Inches From Reference Datum'
    },
    vAxis: {
      title: 'Pounds',
      textPosition: 'out',
      viewWindowMode: 'maximized'
    },
    backgroundColor: '#ffffff',
    series: {
      0: {
        color: 'blue',
        visibleInLegend: true
      },
      1: {
        color: 'green',
				<?php if ($chart_size=="small") {
					echo "visibleInLegend: false\n";
				} else { echo "visibleInLegend: true\n"; }
				?>
      },
      2: {
        color: 'red',
				<?php if ($chart_size=="small") {
					echo "visibleInLegend: false\n";
				} else { echo "visibleInLegend: true\n"; }
				?>
      }
    },
    chartArea: {
			<?php if ($chart_size=="small") {
				echo "
						left: 60,
						top: 10,
						right: 10,
						bottom: 30\n";
} else {
	echo "
      left: 60,
      top: 10,
      right: 10,
      bottom: 40\n";
}?>
    },
    legend: {
      position: 'in'
    },
    pointSize: 5
  };

  var chart = new google.visualization.LineChart(document.getElementById('chart_div'));

/*
	// Wait for the chart to finish drawing before calling the getImageURI() method.
	google.visualization.events.addListener(chart, 'ready', function () {
		chart_div.innerHTML = '<img src="' + chart.getImageURI() + '">';
		console.log(chart_div.innerHTML);
	});
*/

  chart.draw(data, options);
}

</script>
</body>
</html>
