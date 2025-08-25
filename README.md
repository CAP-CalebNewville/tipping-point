TippingPoint - Aircraft Weight & Balance
http://www.TippingPointTool.com
v1.3.0 - 31 Jul 2025
========================================

##Overview##
TippingPoint is a web-based aircraft weight and balance calculator with graphing. It has support for multiple aircraft and administrators.

TippingPoint is ideal for flying clubs, flight schools, FBOs or even individuals.  The administrator (owner/mechanic/operations officer/etc) defines the aircraft configuration using a simple web interface.

Pilots visit an equally intuitive web interface, choose their tail number, and plug in their weights.  The entire preflight weight and balance can be completed in under a minute.

The current software version is 1.3.0.  View the [changelog](https://github.com/CAP-CalebNewville/tipping-point/blob/main/changelog.txt) for release history.

##Requirements##
* A web server with PHP 8.0+
* SQLite3 support (built into most PHP installations)
* PDO SQLite extension

**Note:** MySQL/MariaDB is still supported for existing installations and can be migrated to SQLite automatically.

##Installation##
1. Download the code
2. Extract the archive to your webserver
3. Ensure your web server can write to the TippingPoint directory
4. Visit http://yourserver/TippingPoint/setup.php
5. Follow the setup wizard to configure your installation

**For existing MySQL installations:** Your data can be automatically migrated to SQLite through the admin interface.

##Download##
[Download latest version](https://github.com/CAP-CalebNewville/tipping-point/releases)

##Future Improvements##
* Multiple weight envelopes on the graph (ie: normal category, utility category)

##Contributing##
If you find a bug, feature request or other suggestion, please submit it using the [issues](https://github.com/CAP-CalebNewville/tipping-point/issues) link above.
If you would like to contribute to the source code, please e-mail: <caleb.newville@akwg.cap.gov>

##Donations##
If you would like to "tip" the developer, you may do so via [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=34CMYSQG2R49Y).
