# TippingPoint 2.0.3 Release Notes

## 🐛 Bug Fix Release

TippingPoint 2.0.3 is a focused bug fix release addressing a critical issue where changes to Aircraft Basic Information were not being saved to the database.

---

## 🐛 Bug Fixes

### Aircraft Basic Information
- **Fixed**: Changes made in the Aircraft Basic Information form showed an "Aircraft Updated Successfully" toast but were not saved to the database
- **Fixed**: AJAX handler routing caused `basics` form submissions to silently fail
- **Fixed**: Success toast displayed regardless of whether the database update actually occurred

### Ajax Request Handling
- **Fixed**: Missing Ajax handler for aircraft basic information updates in `ajax_handler.php`
- **Improved**: Proper JSON response body checking in `submitBasicInformation()` instead of relying solely on HTTP status code
- **Enhanced**: Error messages now include server-provided detail when updates fail

---

## 🔧 Technical Changes

### Root Cause
All AJAX requests with `func=aircraft&func_do=edit_do` were intercepted and routed to `ajax_handler.php`. The `basics` case was not implemented, so requests fell through to the default error handler without touching the database. Additionally, the JavaScript checked `response.ok` (HTTP 200) rather than `data.success` from the JSON body, causing the success toast to always appear.

### Files Modified
- `ajax_handler.php` - Added `basics` case to handle aircraft basic information UPDATE query and audit log entry
- `admin.php` - Fixed `submitBasicInformation()` to check `data.success` from JSON response instead of `response.ok`
- `common.inc` - Version bump to 2.0.3

---

## 📋 System Requirements

- **PHP**: 8.0+
- **Database**: SQLite 3.0+ (MySQL legacy support)
- **Web Server**: Apache/Nginx with mod_rewrite
- **Browser**: Modern browsers with JavaScript

---

## 🔄 Upgrade Notes

This release is fully compatible with 2.0.0, 2.0.1, and 2.0.2. No database changes required.

**Upgrading from 2.0.2:**
- Simply replace the updated files
- No additional configuration needed
- All existing data remains intact

---

## 📞 Support

- **GitHub Issues**: [Repository Issues Page](https://github.com/CAP-CalebNewville/tipping-point/issues)
- **Documentation**: User Guide & FAQ

---

**Release Date**: February 24, 2026
**Version**: 2.0.3
**Compatibility**: Upgrades from versions 1.x, 2.0.0, 2.0.1, and 2.0.2

*TippingPoint 2.0.3 - Professional Weight & Balance for Modern Aviation*
