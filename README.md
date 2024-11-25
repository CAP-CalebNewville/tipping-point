TippingPoint - Aircraft Weight & Balance
http://sourceforge.net/p/tippingpoint
========================================

Tipping point is a web-based aircraft weight and balance calculator with
graphing. It has support for multiple aircraft and administrators. The software
requires PHP and MySQL, and utilizes the pChart graphing library.

========================================

This is a final pre-production release.

Things we would like to add in the future:
* Multiple organizations (with aircraft) from a single install
* Automated installer
	- Genericize database
* Code cleanup
* Multiple weight envelopes on the graph (ie: normal category, utility category)
* "e-file" weight and balance (no printing and signing)

========================================

To Install:
1) Download the code and SQL file
2) Use the SQL file to create to create the blank database
3) Manually add a user account to the "users" table in the database,
   leave the password field blank
4) Extract the code
5) Edit "func.inc", fill in your MySQL server information

========================================

Notes:
This software utilizes the pChart graphing library, ensure that you comply
with the license terms of their software: http://www.pchart.net/license

========================================

Feedback/Contributing:
Please submit bugs, code improvements or other feedback on the project's
SourceForge site: http://sourceforge.net/p/tippingpoint
