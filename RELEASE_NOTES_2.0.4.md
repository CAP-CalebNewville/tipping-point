# TippingPoint 2.0.4 Release Notes

## 🐛 Bug Fix Release

TippingPoint 2.0.4 is a focused bug fix release addressing two issues on the Users administration page (`admin.php?func=users`): editing a user returned a blank page, and adding a new user silently failed.

---

## 🐛 Bug Fixes

### User Management
- **Fixed**: Saving changes on the Edit User form returned a blank page instead of the users list
- **Fixed**: Adding a new user with the Administrator option unchecked silently failed and no account was created

---

## 🔧 Technical Changes

### Root Cause
- **Edit save**: The post-update redirect back to the users list was commented out, so after the `UPDATE` ran the module rendered no content, leaving a blank page.
- **Add user**: The `users.superuser` column is `INTEGER NOT NULL`. The add handler passed the `superuser` checkbox value straight into the `INSERT`. When the box was left unchecked the field was absent from the request, binding `NULL` into the column and triggering a NOT NULL constraint violation that aborted the insert. The edit handler already normalized this value; the add handler now does the same.

### Files Modified
- `admin.php` - Restored the post-update redirect on the Users edit action; normalized the `superuser` checkbox to `"1"`/`"0"` before the add `INSERT` and in its audit log entry
- `common.inc` - Version bump to 2.0.4

---

## 📋 System Requirements

- **PHP**: 8.0+
- **Database**: SQLite 3.0+ (MySQL legacy support)
- **Web Server**: Apache/Nginx with mod_rewrite
- **Browser**: Modern browsers with JavaScript

---

## 🔄 Upgrade Notes

This release is fully compatible with 2.0.0, 2.0.1, 2.0.2, and 2.0.3. No database changes required.

**Upgrading from 2.0.3:**
- Simply replace the updated files
- No additional configuration needed
- All existing data remains intact

---

## 📞 Support

- **GitHub Issues**: [Repository Issues Page](https://github.com/CAP-CalebNewville/tipping-point/issues)
- **Documentation**: User Guide & FAQ

---

**Release Date**: July 20, 2026
**Version**: 2.0.4
**Compatibility**: Upgrades from versions 1.x, 2.0.0, 2.0.1, 2.0.2, and 2.0.3

*TippingPoint 2.0.4 - Professional Weight & Balance for Modern Aviation*
