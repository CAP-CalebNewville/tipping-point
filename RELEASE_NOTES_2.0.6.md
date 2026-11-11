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

---

## 📋 Upgrade Notes

**If you installed or upgraded to 2.0.0–2.0.5 and never ran the setup flow**, your installation contains the bundled development administrator account (username `inetwiz`). Please:

1. Log in and create your own administrator account on the Users page.
2. Delete the `inetwiz` account.

Installations where you created your own administrator, or a fresh install into an empty `data/` directory, are not affected by the bundled account, but should still verify that no `inetwiz` user exists.

**Upgrading from the old MySQL-based versions (pre-2.0)**: if a `data/tippingpoint.db` file was placed there by a 2.0.x package, move it aside before upgrading so the migration flow (`admin_migrate.php`) can run against your `config.inc`.

**Your existing database is not touched** by this release. As `data/*.db` is no longer tracked, `git pull` will not overwrite it.

---

## 🔧 Files Modified

- `admin.php` — initialize `$current_envelope_color` and JSON-encode it in generated JavaScript
- `index.php` — guard optional `tailnumber` / `message` request parameters
- `common.inc` — PHP 8.5 `curl_close()` deprecation; version bump to 2.0.6
- `.gitignore` — exclude `data/*.db`, `data/*.db-*`, `data/*.bak`
- `data/tippingpoint.db` — removed from the repository

---

## 📋 System Requirements

- PHP 8.0+ (8.5 deprecations addressed)
- SQLite3 / PDO SQLite
