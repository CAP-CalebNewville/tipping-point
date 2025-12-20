TippingPoint - Aircraft Weight & Balance
http://www.TippingPointTool.com
v2.0.2 - 20 Dec 2024
========================================

##Overview##
TippingPoint is a professional web-based aircraft weight and balance calculator with advanced graphing and safety validation. It features support for multiple aircraft, administrators, and comprehensive flight safety checks.

TippingPoint is ideal for flying clubs, flight schools, FBOs, and individual pilots. The administrator defines aircraft configurations through an intuitive web interface with support for multiple CG envelopes, configurable load types, and weight limits.

Pilots visit a modern, responsive interface to select their aircraft and input weights. The system provides real-time CG validation, weight limit checking, and safety warnings. Complete preflight weight and balance calculations can be finished in under a minute with professional-grade accuracy.

The current software version is 2.0.2 - a bug fix release addressing critical issues with CG envelope editing functionality. View the [changelog](https://github.com/CAP-CalebNewville/tipping-point/blob/main/changelog.txt) for release history.

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

##Key Features##
* **Multiple CG Envelopes**: Normal, Utility, Restricted categories with real-time validation
* **Advanced Safety Checks**: Weight limits, CG envelope violations, and MLW validation
* **Flexible Load Types**: Configurable weight categories and removable items
* **Multi-Unit Support**: Pounds/kilograms and gallons/liters with automatic conversion
* **Professional Interface**: Responsive design optimized for all devices
* **Enhanced Performance**: Optimized database queries and streamlined operations
* **SQLite Database**: High-performance local database with MySQL migration support

##Contributing##
If you find a bug, feature request or other suggestion, please submit it using the [issues](https://github.com/CAP-CalebNewville/tipping-point/issues) link above.
If you would like to contribute to the source code, please e-mail: <caleb.newville@akwg.cap.gov>

##Donations##
If you would like to "tip" the developer, you may do so via [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=34CMYSQG2R49Y).
