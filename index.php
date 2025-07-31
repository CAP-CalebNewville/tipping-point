<?php 
// Check if system is set up before trying to load database-dependent functions
if (!file_exists('config.inc')) {
    // System not set up, redirect to setup
    include 'common.inc';
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
include 'func.inc';
PageHeader($config['site_name']); ?>
<style>
/* Override navbar padding for main interface */
body { padding-top: 0 !important; }

/* Print-specific styles for full width layout */
@media print {
  @page {
    size: Letter;
    margin: 0.25in;
  }
  
  html, body {
    height: 100vh !important;
    overflow: hidden !important;
  }
  
  .container {
    max-width: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
  }
  .row {
    margin-left: 0 !important;
    margin-right: 0 !important;
    flex: 1 !important;
  }
  .col-lg-10, .col-xl-8 {
    flex: 0 0 100% !important;
    max-width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    height: 100% !important;
  }
  .card {
    border: none !important;
    box-shadow: none !important;
    margin: 0 !important;
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
  }
  .card-body {
    padding: 0 !important;
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
  }
  
  /* Scale content to fit */
  .table-responsive {
    transform-origin: top left !important;
    transform: scale(0.82) !important;
    width: 122% !important;
  }
  
  /* Scale the chart iframe and reduce spacing */
  #wbimage {
    transform: scale(0.8) !important;
    transform-origin: center !important;
    margin: -30px auto -20px auto !important;
    height: 320px !important;
    width: 100% !important;
    max-width: 700px !important;
    overflow: hidden !important;
  }
  
  /* Reduce spacing around chart container */
  .mt-4 {
    margin-top: 0.5rem !important;
  }
  
  /* Ensure signature box fits */
  .alert-info {
    margin-bottom: 0 !important;
    padding: 0.5rem !important;
    font-size: 0.85em !important;
  }
  /* Ensure noprint elements are hidden */
  .noprint {
    display: none !important;
  }
  
  /* Hide form inputs and show values as text */
  input[type="number"] {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    outline: none !important;
    padding: 0 !important;
    margin: 0 !important;
    font-weight: inherit !important;
    text-align: inherit !important;
  }
  
  /* Make readonly inputs look like regular text */
  input[readonly] {
    background: transparent !important;
    border: none !important;
  }
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
	$stmt = mysqli_prepare($con, "SELECT * FROM aircraft WHERE id = ?");
	mysqli_stmt_bind_param($stmt, 'i', $_REQUEST['tailnumber']);
	mysqli_stmt_execute($stmt);
	$aircraft_result = mysqli_stmt_get_result($stmt);
	$aircraft = mysqli_fetch_assoc($aircraft_result);

	if (mysqli_num_rows($aircraft_result)=="0") {
		header("Location: http://" . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"] . "?message=invalid");
	} elseif ($aircraft['active']=="0") {
		header("Location: http://" . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"] . "?message=inactive");
	}

?>


<script type="text/javascript">

<!-- Hide script
function WeightBal() {
var df = document.forms[0];

<?php

$stmt = mysqli_prepare($con, "SELECT * FROM aircraft_weights WHERE tailnumber = ? ORDER BY `order` ASC");
mysqli_stmt_bind_param($stmt, 'i', $aircraft['id']);
mysqli_stmt_execute($stmt);
$weights_query = mysqli_stmt_get_result($stmt);
while($weights = mysqli_fetch_assoc($weights_query)) {
	if ($weights['fuel']=="true") {
		echo "df.line" . $weights['id'] . "_gallons_to.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_gallons_to"])) {echo($weights['weight']);} else { echo($_REQUEST["line" . $weights['id'] . "_gallons_to"]); }
			echo ";\n";
		echo "df.line" . $weights['id'] . "_wt_to.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_gallons_to"])) {echo(($weights['weight'] * $weights['fuelwt']));} else {echo(($_REQUEST["line" . $weights['id'] . "_gallons_to"] * $weights['fuelwt']));}
			echo ";\n";
		echo "df.line" . $weights['id'] . "_gallons_ldg.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_gallons_ldg"])) {echo($weights['weight']);} else { echo($_REQUEST["line" . $weights['id'] . "_gallons_ldg"]); }
			echo ";\n";
		echo "df.line" . $weights['id'] . "_wt_ldg.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_gallons_ldg"])) {echo(($weights['weight'] * $weights['fuelwt']));} else {echo(($_REQUEST["line" . $weights['id'] . "_gallons_ldg"] * $weights['fuelwt']));}
			echo ";\n";
	} else {
		echo "df.line" . $weights['id'] . "_wt.value = ";
			if (empty($_REQUEST["line" . $weights['id'] . "_wt"])) { echo($weights['weight']); } else { echo($_REQUEST["line" . $weights['id'] . "_wt"]); }
			echo  ";\n";
	}
	echo "df.line" . $weights['id'] . "_arm.value = Number(" . $weights['arm'] . ").toFixed(1);\n\n";
}

?>

  Process();
}

function Process() {
  var df = document.forms[0];

<?php

$stmt = mysqli_prepare($con, "SELECT * FROM aircraft_weights WHERE tailnumber = ? ORDER BY `order` ASC");
mysqli_stmt_bind_param($stmt, 'i', $aircraft['id']);
mysqli_stmt_execute($stmt);
$weights_query = mysqli_stmt_get_result($stmt);
while($weights = mysqli_fetch_assoc($weights_query)) {
	echo "var line" . $weights['id'] . "_arm = Number(df.line" . $weights['id'] ."_arm.value);" . "\n";
	if ($weights['fuel']=="true") {
		echo "var line" . $weights['id'] . "_gallons_to = df.line" . $weights['id'] . "_gallons_to.value;\n";
		echo "var line" . $weights['id'] . "_wt_to = line" . $weights['id'] . "_gallons_to * " . $weights['fuelwt'] . ";\n";
		echo "df.line" . $weights['id'] . "_wt_to.value = (line" . $weights['id'] . "_gallons_to * " . $weights['fuelwt'] . ").toFixed(1);\n";
		echo "var line" . $weights['id'] . "_mom_to = line" . $weights['id'] . "_wt_to * line" . $weights['id'] . "_arm;\n";
		echo "df.line" . $weights['id'] . "_mom_to.value = line" . $weights['id'] . "_mom_to.toFixed(1);\n";

		echo "var line" . $weights['id'] . "_gallons_ldg = df.line" . $weights['id'] . "_gallons_ldg.value;\n";
		echo "var line" . $weights['id'] . "_wt_ldg = line" . $weights['id'] . "_gallons_ldg * " . $weights['fuelwt'] . ";\n";
		echo "df.line" . $weights['id'] . "_wt_ldg.value = (line" . $weights['id'] . "_gallons_ldg * " . $weights['fuelwt'] . ").toFixed(1);\n";
		echo "var line" . $weights['id'] . "_mom_ldg = line" . $weights['id'] . "_wt_ldg * line" . $weights['id'] . "_arm;\n";
		echo "df.line" . $weights['id'] . "_mom_ldg.value = line" . $weights['id'] . "_mom_ldg.toFixed(1);\n\n";

		$momentlist_to[0] = $momentlist_to[0] . " -line" . $weights['id'] . "_mom_to";
		$wtlist_to[0] = $wtlist_to[0] . " -line" . $weights['id'] . "_wt_to";
		$momentlist_ldg[0] = $momentlist_ldg[0] . " -line" . $weights['id'] . "_mom_ldg";
		$wtlist_ldg[0] = $wtlist_ldg[0] . " -line" . $weights['id'] . "_wt_ldg";
	} else {
		echo "var line" . $weights['id'] . "_wt = Number(df.line" . $weights['id'] . "_wt.value);" . "\n";
		echo "var line" . $weights['id'] . "_mom = (Number(line" . $weights['id'] . "_wt) * Number(line" . $weights['id'] . "_arm));\n";
		echo "df.line" . $weights['id'] . "_mom.value = Number(line" . $weights['id'] . "_mom).toFixed(1);\n\n";

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
echo "var c1 = " . $aircraft['cgwarnfwd'] .";\n";
echo "var w2 = " . $aircraft['emptywt'] . ";\n";
echo "var c2 = " . $aircraft['cgwarnaft'] . ";\n";
echo "var overt  = Math.round(totwt_to - " . $aircraft['maxwt'] . ");\n\n";

echo "document.getElementById(\"wbimage\").setAttribute(\"src\",\"scatter.php?tailnumber=" . $aircraft['id'] . "&totarm_to=\" + totarm_to + \"&totwt_to=\" + totwt_to + \"&totarm_ldg=\" + totarm_ldg + \"&totwt_ldg=\" + totwt_ldg + \"\")";

?>

// WARNINGS
if  (parseFloat(Math.round(totwt_to))>w1) {
        var message = "\nBased on the provided data,\n"
            message += "this aircraft will be overweight at takeoff!\n"
       alert(message + "By " + overt + " lbs. ")
        inuse_flag = false;
    }

if  (parseFloat(Math.round(totarm_to*100)/100)>c2) {
        var message = "\nBased on the provided data,\n"
        message += "The takeoff CG may be AFT of limits\n"
        message += "for this aircraft. Please check the\n"
        message += "CG limitations as it applies to the\n"
        message += "weight and category of your flight.\n"
        alert(message)
        inuse_flag = false;
    }

if  ( (parseFloat(Math.round(totarm_to*100)/100)>c2)&&
         (parseFloat(Math.round(totarm_to*100)/100)<c1) &&
          (parseFloat(Math.round(totwt_to))> (w1 - ((w1-w2)/(c1-c2))*c1 + ((w1-w2)/(c1-c2))*(parseFloat(Math.round(totarm_to*100)/100)))))
            {
        var message = "\n(1)Based on the provided data,\n"
        message += "The takeoff CG may be FWD of limits\n"
        message += "for this aircraft. Please check the\n"
        message += "CG limitations as it applies to the\n"
        message += "weight and category of your flight.\n"
        alert(message)
        inuse_flag = false;
    }

if  (parseFloat(Math.round(totarm_to*100)/100)<c1) {
        var message = "\n(2)Based on the provided data,\n"
        message += "The takeoff CG may be FWD of limits\n"
        message += "for this aircraft. Please check the\n"
        message += "CG limitations as it applies to the\n"
        message += "weight and category of your flight.\n"
        alert(message)
        inuse_flag = false;
    }
 }
// -->

isamap = new Object();
isamap[0] = "_df"
isamap[1] = "_ov"
isamap[2] = "_ot"
isamap[3] = "_dn"

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
	$updated_query = mysqli_query($con,"SELECT `timestamp` FROM `audit` WHERE `what` LIKE '%" . $aircraft['tailnumber'] . "%' ORDER BY `timestamp` DESC LIMIT 1");
	$updated = mysqli_fetch_assoc($updated_query);
	echo "<small class=\"text-light\">Aircraft last updated: " . date("j M Y",strtotime($updated['timestamp'])-$timezoneoffset) . "</small>";
	?>
</div>
<div class="col-md-4 text-end">
<div class="row text-center">
<div class="col-4">
<small class="text-light d-block">Empty Wt</small>
<strong><?php echo $aircraft['emptywt']; ?></strong>
</div>
<div class="col-4">
<small class="text-light d-block">Empty CG</small>
<strong><?php echo $aircraft['emptycg']; ?></strong>
</div>
<div class="col-4">
<small class="text-light d-block">MGW</small>
<strong><?php echo $aircraft['maxwt']; ?></strong>
</div>
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
<th style="width:25%" class="text-center">Weight</th>
<th style="width:20%" class="text-center">Arm</th>
<th style="width:20%" class="text-center">Moment</th>
</tr>
</thead>
<tbody>

<?php

$stmt = mysqli_prepare($con, "SELECT * FROM aircraft_weights WHERE tailnumber = ? ORDER BY `aircraft_weights`.`order` ASC");
mysqli_stmt_bind_param($stmt, 'i', $aircraft['id']);
mysqli_stmt_execute($stmt);
$weights_query = mysqli_stmt_get_result($stmt);
while($weights = mysqli_fetch_assoc($weights_query)) {
	echo "<tr><td";
	if ($weights['fuel']=="false") {
		echo " colspan=\"2\"";
	}
	echo " class=\"align-middle\">" . $weights['item'] . "</td>\n";
	if ($weights['fuel']=="true") {
		echo "<td class=\"text-start align-middle\">";
		echo "<div class=\"d-flex align-items-center gap-2 mb-1\"><input type=\"number\" step=\"any\" name=\"line" . $weights['id'] . "_gallons_to\" tabindex=\"" . $tabindex . "\" onblur=\"Process()\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\"><small class=\"text-muted text-nowrap\">" . $aircraft['fuelunit'] . " TO</small></div>\n";
		echo "<div class=\"d-flex align-items-center gap-2\"><input type=\"number\" step=\"any\" name=\"line" . $weights['id'] . "_gallons_ldg\" tabindex=\"" . $tabindex . "\" onblur=\"Process()\" class=\"form-control form-control-sm text-center\" style=\"width: 60px;\"><small class=\"text-muted text-nowrap\">" . $aircraft['fuelunit'] . " LDG</small></div>";
		$tabindex++; echo "</td>\n";
		echo "<td class=\"text-center align-middle\"><div class=\"mb-1\"><input type=\"number\" name=\"line" . $weights['id'] . "_wt_to\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block; background-color: #f8f9fa;\"></div>";
		echo "<div><input type=\"number\" name=\"line" . $weights['id'] . "_wt_ldg\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block; background-color: #f8f9fa;\"></div></td>\n";
	} else {
		if ($weights['emptyweight']=="true") {
			echo "<td class=\"text-center align-middle\"><input type=\"number\" name=\"line" . $weights['id'] . "_wt\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block; background-color: #f8f9fa;\"></td>\n";
		} else {
			echo "<td class=\"text-center align-middle\"><input type=\"number\" step=\"any\" name=\"line" . $weights['id'] . "_wt\" tabindex=\"" . $tabindex . "\" onblur=\"Process()\" class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block;\"></td>\n";
		}
	}
	echo "<td class=\"text-center align-middle\"><input type=\"number\" name=\"line" . $weights['id'] . "_arm\" readonly class=\"form-control form-control-sm text-center\" style=\"width: 80px; display: inline-block; background-color: #f8f9fa;\"></td>\n";
	if ($weights['fuel']=="true") {
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
<tr>
<td colspan="5" class="bg-warning text-dark">
<strong>CG limits:</strong> <code><?php echo $aircraft['cglimits']; ?></code>
</td>
</tr>
</tfoot>
</table>
</div>

<div class="mt-4">
<?php
echo "<div class=\"text-center mb-3\">\n";
echo "<iframe id=\"wbimage\" src=\"loading.png\" width=\"100%\" height=\"360\" style=\"border:0px; max-width: 710px;\"></iframe>\n";
echo "</div>\n";
?>

<div class="alert alert-info">
<strong>Pilot Signature:</strong> X_________________________________________________<br>
<small>The Pilot In Command is responsible for ensuring all calculations are correct.<br>
<?php echo date("D, j M Y H:i:s T"); ?></small>
</div>

<div class="d-flex flex-wrap justify-content-center align-items-center gap-2 noprint">
<button type="submit" name="Submit" class="btn btn-primary" tabindex="<?php echo($tabindex); $tabindex++; ?>" onClick="Process()">Calculate</button>
<button type="button" name="Reset" class="btn btn-outline-secondary" onclick="WeightBal()">Reset</button>
<button type="button" class="btn btn-outline-info" onClick="window.print()">Print</button>
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
