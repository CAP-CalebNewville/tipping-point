# TippingPoint 2.0.6 Release Notes

## 🔧 Bug Fix Release

TippingPoint 2.0.6 fixes a packaging problem that broke new installs and upgrades from older versions, along with several PHP 8.x warnings. **Anyone who installed or upgraded to 2.0.0–2.0.5 should read the Upgrade Notes below.**

---

## 🔒 Packaging / Security Fix

### Development database shipped with releases
- **Fixed**: Releases 2.0.0–2.0.5 included a development SQLite database (`data/tippingpoint.db`) containing a pre-created administrator account.
  - Because the file already existed, `setup.php` reported "TippingPoint is already installed" and never offered to create an administrator.
  - Upgrades from the older MySQL-based versions saw an existing SQLite database and skipped the migration flow.
  - The only login available was the bundled development account.
- **Changed**: `data/*.db` files are no longer part of the repository or release packages. A database is created by `setup.php` (new installs) or `admin_migrate.php` (upgrades from MySQL).
- The repository history was rewritten to remove the file. Tags v2.0.0–v2.0.5 were re-created and now point to commits without the database. Existing clones should be re-cloned.

---

## 🐛 Bug Fixes

- **CG envelopes**: Creating an envelope on an aircraft with no envelopes defined produced `Undefined variable $current_envelope_color`. The warning was written into the page's JavaScript, which broke Create Envelope and other admin scripts. The value is now always initialized and safely encoded. *(Reported by a user running PHP 8.5.)*
- **Aircraft selection page**: `Undefined array key "tailnumber"` / `"message"` warnings appeared after "Choose Another Aircraft" or when opening `index.php` directly. *(Reported by a user running PHP 8.5.)*
- **PHP 8.5**: `curl_close()` is deprecated; it is now only called on PHP versions where it is required.
- **Undefined array key warnings (PHP 8.x)**: Every read of request (`$_REQUEST`/`$_GET`/`$_POST`) and session values in `admin.php`, `ajax_handler.php`, `index.php`, `scatter.php` and `setup.php` now tolerates a missing key. Previously, opening a page without its expected parameters (for example `scatter.php` with no aircraft) printed warnings into the page.
- **Missing records**: Deleting or editing a CG point, envelope, loading item or user that no longer exists, or opening the Edit User page with an unknown id, produced `Trying to access array offset on false` warnings. These cases are now handled quietly, and Edit User shows "User not found".
- **Fresh installs**: A newly created database included deprecated CG columns, so the first admin login was redirected to a "Database Cleanup Required" screen. New installs now get the current schema directly. (Upgrades from MySQL still go through the migration, unchanged.)
- **Add User**: submitting the add-user form without a username caused an uncaught database exception; it now redisplays the form.
- **Deprecated null arguments**: passing a missing value to `trim()`, `htmlspecialchars()`, `password_hash()` and `password_verify()` (deprecated in PHP 8.1+) is fixed.

---

## 📋 Upgrade Notes

**If you installed or upgraded to 2.0.0–2.0.5 and never ran the setup flow**, your installation contains an administrator account that came bundled with the package. Please:

1. Open the Users page and review every account. Create your own administrator account if you don't have one.
2. Delete any account you did not create.

Installations where you created your own administrator, or a fresh install into an empty `data/` directory, should still review the Users list to confirm no unfamiliar accounts exist.

**Upgrading from the old MySQL-based versions (pre-2.0)**: if a `data/tippingpoint.db` file was placed there by a 2.0.x package, move it aside before upgrading so the migration flow (`admin_migrate.php`) can run against your `config.inc`.

**Your existing database is not touched** by this release. As `data/*.db` is no longer tracked, `git pull` will not overwrite it.

---

## 🔧 Files Modified

- `admin.php` — initialize `$current_envelope_color` and JSON-encode it in generated JavaScript; tolerate missing request/session keys and missing records; "User not found" handling; require a username when adding a user
- `ajax_handler.php`, `scatter.php`, `index.php` — tolerate missing request keys
- `setup.php`, `database.inc` — new installs are created without the deprecated CG columns (the MySQL migration schema is unchanged)
- `common.inc` — PHP 8.5 `curl_close()` deprecation; version bump to 2.0.6
- `.gitignore` — exclude `data/*.db`, `data/*.db-*`, `data/*.bak`
- `data/tippingpoint.db` — removed from the repository

---

## 📋 System Requirements

- PHP 8.0+ (8.5 deprecations addressed)
- SQLite3 / PDO SQLite
