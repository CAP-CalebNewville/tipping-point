<?php
// THIS FILE IS FOR UPGRADING FROM 0.9.4 ONLY
include 'config.inc';

$con = mysqli_connect($dbserver,$dbuser,$dbpass,$dbname) or die(mysql_error());

echo "Upgrading database... ";
mysqli_query($con,"ALTER TABLE `aircraft_weights` CHANGE `fuelwt` `fuelwt` FLOAT NULL;");
mysqli_query($con,"ALTER TABLE `users` CHANGE `password` `password` VARCHAR(255) NOT NULL");
echo "done.<br>\n";

echo "Updating SUPERUSER passwords... ";
mysqli_query($con,"UPDATE users SET `password` ='" . password_hash("TippingPoint", PASSWORD_DEFAULT) . "' WHERE `superuser` = 1;");
echo "done.<br>";

echo "Deleting old files... ";
if (!unlink("pChart")) {
  echo "could not delete folder \"pChart\", please delete manually.<br>";
} else {
  echo "done.<br>";
}

echo "Protecting files... ";
chmod("setup.php", 0000);
chmod("upgrade.php", 0000);
echo "done<br><br>\n";

echo "Upgrade complete.<br><br>";
echo "<b>TAKE NOTE:</b> ALL ADMIN/SUPERUSER PASSWORDS HAVE BEEN RESET. THE NEW PASSWORD IS <pre>TippingPoint</pre><br>";
echo "AFTER YOU LOG IN, PLEASE RESET THE PASSWORDS FOR ALL USERS.<br><br>";
echo "Visit the <a href=\"admin.php\">admin area</a> or <a href=\"index.php\">main interface</a>.";

?>
