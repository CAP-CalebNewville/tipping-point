<?php
// Setup script can use common.inc safely since it doesn't require config.inc
include 'common.inc';
PageHeader("Initial Setup");
?>

<body>
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-6 col-md-8">
<div class="card mt-4">
<div class="card-header bg-primary text-white text-center">
<h3 class="mb-0">TippingPoint - Initial Setup</h3>
</div>
<div class="card-body">

<?php
if (file_exists("config.inc") && $_REQUEST['func']=="") {
	echo "<div class='alert alert-info text-center'>Tipping point is already installed.</div>";
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
			echo "<h5 class='card-title'>Define system settings</h5>\n";
			echo "<form method='post' action='setup.php'><input type='hidden' name='func' value='step4'>\n";
			echo "<div class='mb-3'>\n";
			echo "<label for='site_name' class='form-label'>Site/Organization Name</label>\n";
			echo "<input type='text' class='form-control' id='site_name' name='site_name' required>\n";
			echo "</div>\n";
			echo "<div class='mb-3'>\n";
			echo "<label for='administrator' class='form-label'>Administrator E-mail Address</label>\n";
			echo "<input type='email' class='form-control' id='administrator' name='administrator' required>\n";
			echo "</div>\n";
			echo "<div class='mb-3'>\n";
			echo "<label for='timezone' class='form-label'>Local Time Zone</label>\n";
			// Use TimezoneList function from common.inc
			TimezoneList("");
			echo "</div>\n";
			echo "<div class='d-grid'>\n";
			echo "<button type='submit' class='btn btn-primary'>Continue to Step 4</button>\n";
			echo "</div>\n";
			echo "</form>\n";

		    	break;

		case "step4":
			// Load the config file we just created
			include "config.inc";
			$con = mysqli_connect($dbserver,$dbuser,$dbpass,$dbname) or die(mysqli_connect_error());
			// $ver is available from common.inc

			// Insert system settings into database
			$sql_query = "INSERT INTO `configuration` (`id`, `item`, `value`) "
			.    "VALUES (1, 'site_name', '" . $_REQUEST['site_name'] . "'), (2, 'administrator', '" . $_REQUEST['administrator'] . "'), (3, 'timezone', '" . $_REQUEST['timezone'] . "'), "
			.    "(4, 'update_check', '" . time() . "'), (5, 'update_version', '" . $ver . "');";
			mysqli_query($con,$sql_query);

			echo "<h5 class='card-title'>Create an administrative user</h5>\n";
			echo "<form method='post' action='setup.php'><input type='hidden' name='func' value='step5'>\n";
			echo "<div class='mb-3'>\n";
			echo "<label for='username' class='form-label'>Username</label>\n";
			echo "<input type='text' class='form-control' id='username' name='username' required>\n";
			echo "</div>\n";
			echo "<div class='mb-3'>\n";
			echo "<label for='password' class='form-label'>Password</label>\n";
			echo "<input type='password' class='form-control' id='password' name='password' required>\n";
			echo "</div>\n";
			echo "<div class='mb-3'>\n";
			echo "<label for='name' class='form-label'>Full Name</label>\n";
			echo "<input type='text' class='form-control' id='name' name='name' required>\n";
			echo "</div>\n";
			echo "<div class='mb-3'>\n";
			echo "<label for='email' class='form-label'>E-mail Address</label>\n";
			echo "<input type='email' class='form-control' id='email' name='email' required>\n";
			echo "</div>\n";
			echo "<div class='d-grid'>\n";
			echo "<button type='submit' name='what' class='btn btn-success' value='Finish'>Finish Setup</button>\n";
			echo "</div>\n";
			echo "</form>\n\n";

			break;

		case "step5":
			// Load the config file
			include "config.inc";
			$con = mysqli_connect($dbserver,$dbuser,$dbpass,$dbname) or die(mysqli_connect_error());
			// Insert administrative user
			$sql_query = "INSERT INTO `users` (`username`, `password`, `name`, `email`, `superuser`) VALUES "
			.	"('" . $_REQUEST['username'] . "', '" . password_hash($_REQUEST['password'], PASSWORD_DEFAULT) . "', '" . $_REQUEST['name'] . "', '" . $_REQUEST['email'] . "', '1')";
			mysqli_query($con,$sql_query);

			echo "<div class='alert alert-success'>\n";
			echo "<h5 class='alert-heading'>Setup Complete!</h5>\n";
			echo "<p>Initial setup is complete. Proceed to the <a href='admin.php' class='alert-link'>admin page</a> and create your first aircraft.</p>\n";
			echo "<hr>\n";
			echo "<p class='mb-0'>If you find bugs or have a suggestion, please <a href='https://github.com/CAP-CalebNewville/tipping-point/issues' target='_blank' class='alert-link'>let us know on GitHub</a>. Thanks for using TippingPoint!</p>\n";
			echo "</div>\n";

			chmod("setup.php", 0000);
			chmod("upgrade.php", 0000);
			break;

	    default:
		echo "<h5 class='card-title'>Database Configuration</h5>\n";
		echo "<p class='text-muted'>Enter your MySQL server information to begin setup.</p>\n";
		echo "<form method='post' action='setup.php'><input type='hidden' name='func' value='step2'>\n";
		echo "<div class='mb-3'>\n";
		echo "<label for='dbserver' class='form-label'>Database Server</label>\n";
		echo "<input type='text' class='form-control' id='dbserver' name='dbserver' value='localhost' required>\n";
		echo "</div>\n";
		echo "<div class='mb-3'>\n";
		echo "<label for='dbname' class='form-label'>Database Name</label>\n";
		echo "<input type='text' class='form-control' id='dbname' name='dbname' value='tippingpoint' required>\n";
		echo "</div>\n";
		echo "<div class='mb-3'>\n";
		echo "<label for='dbuser' class='form-label'>Database Username</label>\n";
		echo "<input type='text' class='form-control' id='dbuser' name='dbuser' required>\n";
		echo "</div>\n";
		echo "<div class='mb-3'>\n";
		echo "<label for='dbpass' class='form-label'>Database Password</label>\n";
		echo "<input type='password' class='form-control' id='dbpass' name='dbpass'>\n";
		echo "<div class='form-text'>Leave blank if no password is required.</div>\n";
		echo "</div>\n";
		echo "<div class='d-grid'>\n";
		echo "<button type='submit' class='btn btn-primary'>Continue to Step 2</button>\n";
		echo "</div>\n";
		echo "</form>\n\n";
	}
}
?>

</div>
</div>
</div>
</div>
</div>

<?php
// Use PageFooter from common.inc but we need some basic config for the footer
$site_name = "TippingPoint Setup";
$admin = "setup@tippingpoint";
PageFooter($site_name, $admin, $ver);
?>