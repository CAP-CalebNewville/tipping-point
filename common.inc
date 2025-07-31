<?php
/* COMMON FUNCTIONS AND VARIABLES */
/* Functions that don't require database connection - safe for setup scripts */

$ver = "1.2.1";
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
	foreach( $timezone_identifiers as $value ){
		if ( preg_match('/^(America|Antartica|Arctic|Asia|Atlantic|Europe|Indian|Pacific)\//', $value ) ){
			$ex=explode("/",$value);//obtain continent,city
			if ($continent!=$ex[0]){
				if ($continent!="") echo "</optgroup>\n";
				echo "<optgroup label=\"".$ex[0]."\">\n";
			}
			$city=$ex[1];
			if (is_null($ex[2])==FALSE) { $city=$city . "/" . $ex[2]; }
			$continent=$ex[0];
		            echo "<option value=\"".$value."\"";
		            if ($str==$value) {
		            	echo " selected";
		            }
		            echo ">".$city."</option>\n";
	        }
	}
	echo("</optgroup></select>");
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

?>
