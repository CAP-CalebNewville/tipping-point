TippingPoint - Aircraft Weight & Balance
http://sourceforge.net/p/tippingpoint
v0.9.2 - 30 Nov 2011
========================================

Tipping point is a web-based aircraft weight and balance calculator with
graphing. It has support for multiple aircraft and administrators. The software
requires PHP and MySQL, and utilizes the pChart graphing library.

========================================

This is a pre-production release.

Things we would like to add in the future:
* Multiple organizations (with aircraft) from a single install
* Automated installer
	- Genericize database
* Code cleanup
* Multiple weight envelopes on the graph (ie: normal category, utility category)
* "e-file" weight and balance (no printing and signing)

========================================

To Install:
1) Download the code
2) Extract the archive to your webserver
3) Use "tippingpoint.sql" in the archive to create the blank database
4) Edit "func.inc", fill in your MySQL server information
5) Visit http://yourserver/TippingPoint/admin.php -- login with user "admin" and no password
6) Visit the "Edit Users" section and create a more secure user account
7) Visit the "Edit System Settings"

========================================

Notes:
This software utilizes the pChart graphing library, ensure that you comply
with the license terms of their software: http://www.pchart.net/license

========================================

Feedback/Contributing:
Please submit bugs, code improvements or other feedback on the project's
SourceForge site: http://sourceforge.net/p/tippingpoint
