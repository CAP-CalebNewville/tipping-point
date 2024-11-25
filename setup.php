<?php ob_start(); ?>
<html>
<head>
<title>TippingPoint - Initial Setup</title>

<style type="text/css">
<!--
body {font-family: Cambria, Tahoma, Verdana; font-size: 12px;}
input, select {font-family: Cambria, Tahoma, Verdana; font-size: 11px; border:1px solid #AAAAAA;}
th {background-color: #4F81BD; text-align: center;}
abbr {border-bottom: 1px dashed; cursor: help;}
.titletext {color: #17365D; font-size: 24px;}
.readonly {background-color: #CCCCCC;}
.numbers {text-align: right; width: 70px;}
.numbergals {text-align: right; width: 40px;}
@media print { .noprint { display: none; } }
-->
</style>

<body><table border="1" cellpadding="3" width="700" align="center"><tr><td>
	<div class="titletext" style="text-align: center">TippingPoint - Initial Setup</div>


<?php
if (file_exists("config.inc") && $_REQUEST['func']=="") {
	echo "Tipping point is already installed.";
	chmod("setup.php", 0000);
	chmod("upgrade.php", 0000);

} else {
	switch ($_REQUEST["func"]) {
		case "step2":
			// Write config file
			$configfile = fopen("config.inc", "w+");
			fwrite($configfile,
			"<?php\n\$dbserver=\"" . $_REQUEST['dbserver'] . "\";\n"
			. "\$dbname=\"" . $_REQUEST['dbname'] . "\";\n"
			. "\$dbuser=\"" . $_REQUEST['dbuser'] . "\";\n"
			. "\$dbpass=\"" . $_REQUEST['dbpass'] . "\";\n?>");

			// Create database
			$con = mysqli_connect($_REQUEST['dbserver'],$_REQUEST['dbuser'],$_REQUEST['dbpass']) or die(mysqli_connect_error());
			$sql_query = "CREATE DATABASE IF NOT EXISTS " . $_REQUEST['dbname'] . " ;";
			mysqli_multi_query($con,$sql_query);

			// Populate database
			$con = mysqli_connect($_REQUEST['dbserver'],$_REQUEST['dbuser'],$_REQUEST['dbpass'],$_REQUEST['dbname']) or die(mysqli_connect_error());
			$sql_query = "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n"
			.	"SET time_zone = \"+00:00\";\n"
			.   "CREATE TABLE IF NOT EXISTS `aircraft` (`id` int(11) NOT NULL auto_increment, `active` tinyint(1) NOT NULL default '1', `tailnumber` char(25) NOT NULL, `makemodel` char(50) NOT NULL, `emptywt` float NOT NULL, `emptycg` float NOT NULL, `maxwt` float NOT NULL, `cglimits` char(60) NOT NULL, `cgwarnfwd` float NOT NULL, `cgwarnaft` float NOT NULL, `fuelunit` char(25) NOT NULL, PRIMARY KEY  (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1;\n"
			.   "CREATE TABLE IF NOT EXISTS `aircraft_cg` (`id` int(11) NOT NULL auto_increment, `tailnumber` int(11) NOT NULL, `arm` float NOT NULL, `weight` float NOT NULL, PRIMARY KEY  (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1;\n"
			.   "CREATE TABLE IF NOT EXISTS `aircraft_weights` (`id` int(11) NOT NULL auto_increment, `tailnumber` int(11) NOT NULL, `order` smallint(3) NOT NULL, `item` char(50) NOT NULL, `weight` float NOT NULL, `arm` float NOT NULL, `emptyweight` enum('true','false') NOT NULL default 'false', `fuel` enum('true','false') NOT NULL default 'false', `fuelwt` float NOT NULL, PRIMARY KEY  (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1;\n"
			.   "CREATE TABLE IF NOT EXISTS `audit` (`id` int(11) NOT NULL auto_increment, `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP, `who` char(24) NOT NULL, `what` varchar(32768) NOT NULL, PRIMARY KEY  (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1;\n"
			.   "CREATE TABLE IF NOT EXISTS `configuration` (`id` int(11) NOT NULL auto_increment, `item` char(30) NOT NULL, `value` varchar(255) NOT NULL, PRIMARY KEY  (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1;\n"
			.   "CREATE TABLE IF NOT EXISTS `users` (`id` int(11) NOT NULL auto_increment, `username` char(24) NOT NULL, `password` char(32) NOT NULL, `name` char(48) NOT NULL, `email` char(48) NOT NULL, `superuser` tinyint(4) NOT NULL, PRIMARY KEY  (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1;\n";
			mysqli_multi_query($con,$sql_query);

			header("Location: http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?func=step3");

		case "step3":
			$config['timezone'] = "America/Anchorage";
			require "func.inc";

			echo("<p>Define system settings.</p>\n"
						.    "<form method=\"post\" action=\"setup.php\"><input type=\"hidden\" name=\"func\" value=\"step4\">\n"
	        	.    "<table align=\"center\">\n"
		        .    "<tr><td align=\"right\">Site/Organization Name</td><td><input type=\"text\" name=\"site_name\"></td></tr>\n"
		        .    "<tr><td align=\"right\">Administrator E-mail Address</td><td><input type=\"email\" name=\"administrator\"></td></tr>\n"
		        .    "<tr><td align=\"right\">Local Time Zone</td><td>\n");
		             TimeZoneList("");
	        	echo "</td></tr>\n"
		        .    "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Step 3\"></td></tr></table></form>\n";

		    	break;

		case "step4":
			$config['timezone'] = "America/Anchorage";
			include "func.inc";
			$con = mysqli_connect($dbserver,$dbuser,$dbpass,$dbname) or die(mysqli_connect_error());

			// Insert system settings into database
			$sql_query = "INSERT INTO `configuration` (`id`, `item`, `value`) "
			.    "VALUES (1, 'site_name', '" . $_REQUEST['site_name'] . "'), (2, 'administrator', '" . $_REQUEST['administrator'] . "'), (3, 'timezone', '" . $_REQUEST['timezone'] . "'), "
			.    "(4, 'update_check', '" . time() . "'), (5, 'update_version', '" . $ver . "');";
			mysqli_query($con,$sql_query);

			echo("<p>Create an administrative user.</p>\n"
			.    "<form method=\"post\" action=\"setup.php\"><input type=\"hidden\" name=\"func\" value=\"step5\">\n"
			.    "<table align=\"center\" width=\"100%\" border=\"0\">\n"
			.    "<tr><td align=\"right\" width=\"50%\">Username</td><td width=\"50%\"><input type=\"text\" name=\"username\"></td></tr>\n"
			.    "<tr><td align=\"right\">Password</td><td><input type=\"password\" name=\"password\"></td></tr>\n"
			.    "<tr><td align=\"right\">Name</td><td><input type=\"text\" name=\"name\"></td></tr>\n"
			.    "<tr><td align=\"right\">E-mail</td><td><input type=\"email\" name=\"email\"></td></tr>\n"
			.    "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"what\" value=\"Finish\"></td></tr>\n"
			.    "</table></form>\n\n"
			);

			break;

		case "step5":
			include "func.inc";
			// Insert administrative user
			$sql_query = "INSERT INTO `users` (`username`, `password`, `name`, `email`, `superuser`) VALUES "
			.	"('" . $_REQUEST['username'] . "', '" . password_hash($_REQUEST['password'], PASSWORD_DEFAULT) . "', '" . $_REQUEST['name'] . "', '" . $_REQUEST['email'] . "', '1')";
			mysqli_query($con,$sql_query);

			echo("<p>Initial setup is complete.  Proceed to the <a href=\"admin.php\">admin page</a> and create your first aircraft.</p>"
			.    "<p>If you find bugs or have a suggestion, please <a href=\"https://sourceforge.net/p/tippingpoint/tickets/\" target=\"_blank\">let us know</a>.  Thanks for using TippingPoint!</p>\n");

			chmod("setup.php", 0000);
			chmod("upgrade.php", 0000);
			break;

	    default:
		echo("<p>Enter your MySQL server information.</p>\n"
		.    "<form method=\"post\" action=\"setup.php\"><input type=\"hidden\" name=\"func\" value=\"step2\">\n"
		.    "<table align=\"center\">\n"
		.    "<tr><td align=\"right\">Database Server</td><td><input type=\"text\" name=\"dbserver\" value=\"localhost\"></td></tr>\n"
		.    "<tr><td align=\"right\">Database Name</td><td><input type=\"text\" name=\"dbname\" value=\"tippingpoint\"></td></tr>\n"
		.    "<tr><td align=\"right\">Database Username</td><td><input type=\"text\" name=\"dbuser\"></td></tr>\n"
		.    "<tr><td align=\"right\">Database Password</td><td><input type=\"text\" name=\"dbpass\"></td></tr>\n"
		.    "<td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Step 2\"></td></tr>\n"
		.    "</table></form>\n\n"
		);
	}

}
?>


</td></tr></table>
<p class="noprint" style="text-align:center; font-size:12px;"><i><a href="http://www.TippingPointTool.com" target="_blank">TippingPoint - Open Source Weight &amp; Balance Software</a></i></p>

</body></html>
