<?php
/* DATABASE-DEPENDENT FUNCTIONS */
/* Functions that require config.inc and database connection - used after setup */

require 'config.inc';
require 'common.inc';

$con = mysqli_connect($dbserver,$dbuser,$dbpass,$dbname) or die(mysqli_error($con));
// mysqli_select_db($dbname) or die(mysql_error());

$config_result = mysqli_query($con,"SELECT * FROM `configuration`;");
while($config_row = mysqli_fetch_assoc($config_result)) {
  $config[$config_row['item']]=$config_row['value'];
}

// Set timezone based on config
$timezoneoffset = get_timezone_offset($config['timezone']);
date_default_timezone_set($config['timezone']);

function AircraftListActive() {
  require 'config.inc';
  $con = mysqli_connect($dbserver,$dbuser,$dbpass,$dbname) or die(mysqli_error($con));
	echo "<select name=\"tailnumber\" class=\"form-select\">\n";
	$result = mysqli_query($con,"SELECT * FROM `aircraft` WHERE `active` = 1 ORDER BY `tailnumber` ASC;");
	while($row = mysqli_fetch_array($result)) {
		echo "<option value=\"" . $row['id'] . "\">" . $row['tailnumber'] . " - " . $row['makemodel'] . "</option>\n";
	}
	echo "</select>\n";
}

function AircraftListAll() {
  require 'config.inc';
  $con = mysqli_connect($dbserver,$dbuser,$dbpass,$dbname) or die(mysqli_error($con));
	echo "<select name=\"tailnumber\" class=\"form-select\">\n";
	echo "<optgroup label=\"Active\">\n";
	$result = mysqli_query($con,"SELECT * FROM `aircraft` WHERE `active` = 1 ORDER BY `tailnumber` ASC;");
	while($row = mysqli_fetch_array($result)) {
		echo "<option value=\"" . $row['id'] . "\">" . $row['tailnumber'] . " - " . $row['makemodel'] . "</option>\n";
	}
	echo "</optgroup>\n";
	echo "<optgroup label=\"Inactive\">\n";
	$result = mysqli_query($con,"SELECT * FROM `aircraft` WHERE `active` = 0 ORDER BY `tailnumber` ASC;");
	while($row = mysqli_fetch_array($result)) {
		echo "<option value=\"" . $row['id'] . "\">" . $row['tailnumber'] . " - " . $row['makemodel'] . "</option>\n";
	}
	echo "</optgroup>\n";
	echo "</select>\n";
}

?>