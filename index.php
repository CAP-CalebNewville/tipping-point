<?php include 'func.inc';
   PageHeader($config['site_name']); ?>

<?php
if ($_REQUEST['tailnumber']=="") {
// NO AIRCRAFT SPECIFIED, SHOW ACTIVE AIRCRAFT LIST
	echo "<body>\n";
	echo "<table border=\"1\" cellpadding=\"3\" width=\"700\" align=\"center\">\n";
	echo "<tr><td>\n";
	echo "<div class=\"titletext\">" . $config['site_name'] . "</div>\n";
	if ($_REQUEST['message']=="invalid") { echo "<p style=\"color: #00AA00; text-align: center;\">You have selected an invalid aircraft.</p>\n\n";
        } elseif ($_REQUEST['message']=="inactive") { echo "<p style=\"color: #00AA00; text-align: center;\">The aircraft you have selected is currently inactive.</p>\n\n"; }
	echo "<p>Select aircraft tail number.</p>\n";

	echo "<form method=\"get\" action=\"index.php\">\n";
	AircraftListActive();
	echo "<input type=\"submit\" value=\"Go\"></form>\n";

	echo "</td></tr></table>\n";

} else {
// TAILNUMBER PROVIDED, VALIDATE
	// GET AIRCRAFT INFORMATION
	$aircraft_result = mysqli_query($con,"SELECT * FROM aircraft WHERE id=" . $_REQUEST['tailnumber']);
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

$weights_query = mysqli_query($con,"SELECT * FROM aircraft_weights WHERE tailnumber = " . $aircraft['id'] . " ORDER BY 'order' ASC");
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

$weights_query = mysqli_query($con,"SELECT * FROM aircraft_weights WHERE tailnumber = " . $aircraft['id'] . " ORDER BY 'order' ASC");
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
        inuse_flag = false
    }

if  (parseFloat(Math.round(totarm_to*100)/100)>c2) {
        var message = "\nBased on the provided data,\n"
        message += "The takeoff CG may be AFT of limits\n"
        message += "for this aircraft. Please check the\n"
        message += "CG limitations as it applies to the\n"
        message += "weight and category of your flight.\n"
        alert(message)
        inuse_flag = false
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
        inuse_flag = false
    }

if  (parseFloat(Math.round(totarm_to*100)/100)<c1) {
        var message = "\n(2)Based on the provided data,\n"
        message += "The takeoff CG may be FWD of limits\n"
        message += "for this aircraft. Please check the\n"
        message += "CG limitations as it applies to the\n"
        message += "weight and category of your flight.\n"
        alert(message)
        inuse_flag = false
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

<form method="get" action="index.php"><input type="hidden" name="tailnumber" value="<?php echo($aircraft['id']); ?>">
<table style="width:700px; margin-left:auto; margin-right:auto;">
<tr>
	<td colspan="4" rowspan="6">

<?php echo "<div class=\"titletext\">" . $config['site_name'] . "<br>" . $aircraft['makemodel'] . " " . $aircraft['tailnumber'] . "</div>";
	$updated_query = mysqli_query($con,"SELECT `timestamp` FROM `audit` WHERE `what` LIKE '%" . $aircraft['tailnumber'] . "%' ORDER BY `timestamp` DESC LIMIT 1");
	$updated = mysqli_fetch_assoc($updated_query);
	echo "Aircraft last updated: " . date("j M Y",strtotime($updated['timestamp'])-$timezoneoffset) . "<br>\n";
	?>

<p><b>PILOT SIGNATURE  X__________________________________________________</b><br>
The Pilot In Command is responsible for ensuring all calculations are correct.<br>
<?php echo date("D, j M Y H:i:s T"); ?></p>
</td>

<th>Empty Wt</th></tr><tr><td style="text-align: center;"><?php echo $aircraft['emptywt']; ?></td></tr>
<tr><th>Empty CG</th></tr><tr><td style="text-align: center;"><?php echo $aircraft['emptycg']; ?></td></tr>
<tr><th>MGW</th></tr><tr><td style="text-align: center;"><?php echo $aircraft['maxwt']; ?></td></tr>

<tr><th style="width:385px" colspan="2">Item</th><th style="width:105px">Weight</th><th style="width:105px">Arm</th><th style="width:105px">Moment</th></tr>

<?php

$weights_query = mysqli_query($con,"SELECT * FROM aircraft_weights WHERE tailnumber = " . $aircraft['id'] . " ORDER BY  `aircraft_weights`.`order` ASC");
while($weights = mysqli_fetch_assoc($weights_query)) {
	echo "<tr><td";
	if ($weights['fuel']=="false") {
		echo " colspan=\"2\"";
	}
	echo ">" . $weights['item'] . "</td>\n";
	if ($weights['fuel']=="true") {
		echo "<td style=\"text-align: center;\">";
		echo "<div style=\"display: inline-block; width: 50px;\"><input type=\"number\" step=\"any\" name=\"line" . $weights['id'] . "_gallons_to\" tabindex=\"" . $tabindex . "\" onblur=\"Process()\" class=\"numbergals\"></div><div style=\"display: inline-block; width: 120px;\">" . $aircraft['fuelunit'] . " Takeoff</div><br>\n";
		echo "<div style=\"display: inline-block; width: 50px;\"><input type=\"number\" step=\"any\" name=\"line" . $weights['id'] . "_gallons_ldg\" tabindex=\"" . $tabindex . "\" onblur=\"Process()\" class=\"numbergals\"></div><div style=\"display: inline-block; width: 120px;\">" . $aircraft['fuelunit'] . " Landing</div>";
		$tabindex++; echo "</td>\n";
		echo "<td style=\"text-align: center;\"><div><input type=\"number\" name=\"line" . $weights['id'] . "_wt_to\" readonly class=\"readonly numbers\"></div>";
		echo "<div><input type=\"number\" name=\"line" . $weights['id'] . "_wt_ldg\" readonly class=\"readonly numbers\"></div></td>\n";
	} else {
		if ($weights['emptyweight']=="true") {
			echo "<td style=\"text-align: center;\"><input type=\"number\" name=\"line" . $weights['id'] . "_wt\" readonly class=\"readonly numbers\"></td>\n";
		} else {
			echo "<td style=\"text-align: center;\"><input type=\"number\" step=\"any\" name=\"line" . $weights['id'] . "_wt\" tabindex=\"" . $tabindex . "\" onblur=\"Process()\" class=\"numbers\"></td>\n";
		}
	}
	echo "<td style=\"text-align: center;\"><input type=\"number\" name=\"line" . $weights['id'] . "_arm\" readonly class=\"readonly numbers\"></td>\n";
	if ($weights['fuel']=="true") {
		echo "<td style=\"text-align: center;\"><div><input type=\"number\" name=\"line" . $weights['id'] . "_mom_to\" readonly class=\"readonly numbers\">";
		echo "\n</div><div><input type=\"number\" name=\"line" . $weights['id'] . "_mom_ldg\" readonly class=\"readonly numbers\">";
	} else {
		echo "<td style=\"text-align: center;\"><div><input type=\"number\" name=\"line" . $weights['id'] . "_mom\" readonly class=\"readonly numbers\">";
	}
	echo "</div></td></tr>\n\n";
	$tabindex++;
}

?>

<tr style="background-color: #FFFF80">
<td style="text-align: right; font-weight: bold;" colspan="2">Totals at Takeoff<br>Landing</td>
<td style="text-align: center;"><div><input type="number" name="totwt_to" readonly class="readonly numbers"></div><div><input type="number" name="totwt_ldg" readonly class="readonly numbers"></div></td>
<td style="text-align: center;"><div><input type="number" name="totarm_to" readonly class="readonly numbers"></div><div><input type="number" name="totarm_ldg" readonly class="readonly numbers"></div></td>
<td style="text-align: center;"><div><input type="number" name="totmom_to" readonly class="readonly numbers"></div><div><input type="number" name="totmom_ldg" readonly class="readonly numbers"></div></td>
</tr>

<tr style="background-color: #FFFF80"><td colspan="5"><span style="font-weight: bold;">CG limits: </span><span style="font-family: monospace"><?php echo $aircraft['cglimits']; ?></span></td></tr>
</table>

<?php
echo "<iframe id=\"wbimage\" src=\"loading.png\" width=\"710\" height=\"360\" style=\"border:0px; display: block; margin-left: auto; margin-right: auto;\"></iframe>\n\n";
?>

<!-- <div class="noprint" style="text-align:center; font-style:italic;">(click graph to enlarge)</div> -->

<div id="toolbar" class="noprint" style="line-height:35px;">
<span style="width: 130px; float: left; line-height:40px;"><abbr title="TippingPoint is free, open source weight and balance software.  Click to find out how to use it for your flight department, flight school, FBO or even your own personal aircraft.">
<a href="http://www.TippingPointTool.com" target="_blank" style="font-size:22px; color: yellow;">TippingPoint</a></abbr></span>
<span style="width: 770px; text-align:center; float: right; line-height:45px;">
<input type="submit" name="Submit" value="Calculate" tabindex="<?php echo($tabindex); $tabindex++; ?>" onClick="Process()">&nbsp;&nbsp;
<input type="button" name="Reset" value="Reset" onclick="WeightBal()">&nbsp;&nbsp;
<input type="button" value="Print" onClick="window.print()">&nbsp;&nbsp;
<input type="button" value="e-File" disabled>&nbsp;&nbsp;
<a href="index.php">Choose Another Aircraft</a></span></div>

</form>

<?php
}
?>

<?php PageFooter($config['site_name'],$config['administrator'],$ver);
// mysqli_close();
?>
