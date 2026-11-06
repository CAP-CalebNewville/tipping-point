# TippingPoint 2.0.5 Release Notes

## 🔒 Security Release

TippingPoint 2.0.5 is a **security release** that closes a vulnerability allowing an unauthenticated visitor to create administrator accounts, along with several related hardening fixes. **All installations should upgrade promptly.**

---

## 🔒 Security Fixes

### Unauthenticated administrator account creation (critical)
- **Fixed**: The admin authentication redirect did not stop script execution, so an unauthenticated request could fall through into privileged request handlers. Authentication redirects now terminate the request.
- **Fixed**: The Users management handler (`admin.php?func=users`) performed add/edit/delete actions without verifying the caller was an administrator. It now enforces an authorization check.
- **Fixed**: The setup installer (`setup.php`) could be re-run on an already-configured system to insert new administrator accounts. The installer now refuses to create an administrator once any user account exists.

### Session handling
- **Fixed**: The user's password was stored in the session in plaintext. Sessions now store only an authentication flag, the account is re-validated from the database on each request, and the session ID is regenerated on login to mitigate session fixation.

### Cross-site scripting (XSS)
- **Fixed**: Numeric weight/fuel values from the request were echoed unescaped into a script block on the weight & balance form; they are now cast to numbers.
- **Fixed**: User-supplied username and name values were rendered unescaped on the admin Users list; they are now HTML-escaped.

### File exposure hardening
- **Added**: Deny rules for the `data/` directory and for include/database/backup files, preventing the SQLite database and PHP source from being downloaded over HTTP. **Note:** these `.htaccess` rules only take effect when Apache `AllowOverride` is enabled for the document root (not `None`). See Upgrade Notes.

---

## 🔧 Files Modified

- `admin.php` — stop execution after auth redirects; authorization gate on the users handler; removed plaintext password from the session; escaped Users-list output
- `admin_migrate.php`, `admin_upgrade.php` — use the session authentication flag instead of the stored password
- `setup.php` — refuse to create an administrator when a user already exists
- `index.php` — cast request-supplied numeric values before output
- `.htaccess`, `data/.htaccess` — deny web access to the data directory and sensitive files
- `common.inc` — version bump to 2.0.5

---

## 📋 System Requirements

- **PHP**: 8.0+
- **Database**: SQLite 3.0+ (MySQL legacy support)
- **Web Server**: Apache/Nginx with mod_rewrite
- **Browser**: Modern browsers with JavaScript

---

## 🔄 Upgrade Notes

This release is fully compatible with 2.0.x. No database schema changes are required.

**Upgrading from 2.0.4:**
- Replace the updated files.
- **Ensure Apache `AllowOverride` is not `None`** for the document root, otherwise the `.htaccess` protections (including blocking database download) are silently ignored. Verify with:
  `curl -o /dev/null -w '%{http_code}\n' https://<your-site>/data/tippingpoint.db` → should return `403`.
- After upgrading, existing admin sessions are invalidated by design; users will log in again.

**Post-upgrade actions (strongly recommended if you were exposed):**
- Review the Users list and remove any accounts you did not create.
- Reset passwords for all users if the database may have been downloaded.
- Remove or rename `setup.php` once installation is complete.

---

## 📞 Support

- **GitHub Issues**: [Repository Issues Page](https://github.com/CAP-CalebNewville/tipping-point/issues)
- **Documentation**: User Guide & FAQ

---

**Release Date**: November 6, 2026
**Version**: 2.0.5
**Compatibility**: Upgrades from versions 1.x, 2.0.0, 2.0.1, 2.0.2, 2.0.3, and 2.0.4

*TippingPoint 2.0.5 - Professional Weight & Balance for Modern Aviation*
