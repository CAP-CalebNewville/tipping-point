# TippingPoint 2.0.2 Release Notes

## 🐛 Bug Fix Release

TippingPoint 2.0.2 is a focused bug fix release addressing critical issues with CG envelope editing functionality.

---

## 🐛 Bug Fixes

### CG Envelope Editing
- **Fixed**: Mixed content error when updating CG envelope points on HTTPS sites
- **Fixed**: CG envelope point updates not saving to database
- **Fixed**: "Failed to fetch" error when editing CG points

### HTTPS & Security
- **Improved**: HTTPS detection now properly handles proxy and load balancer environments
- **Enhanced**: Support for `X-Forwarded-Proto` header in proxy configurations

### Ajax Request Handling
- **Fixed**: Missing Ajax handler for CG point updates in `ajax_handler.php`
- **Improved**: Proper JSON response handling for CG envelope operations
- **Enhanced**: Page refresh after successful CG point updates

---

## 🔧 Technical Changes

### Files Modified
- `admin.php` - Added Ajax detection for CG updates, improved HTTPS protocol detection
- `ajax_handler.php` - Added update handler for existing CG points
- `common.inc` - Version bump to 2.0.2

### Code Quality
- Removed hardcoded HTTP URLs in favor of dynamic protocol detection
- Improved condition checking with proper `isset()` usage
- Enhanced error handling and user feedback

---

## 📋 System Requirements

- **PHP**: 8.0+
- **Database**: SQLite 3.0+ (MySQL legacy support)
- **Web Server**: Apache/Nginx with mod_rewrite
- **Browser**: Modern browsers with JavaScript

---

## 🔄 Upgrade Notes

This release is fully compatible with 2.0.0 and 2.0.1. No database changes required.

**Upgrading from 2.0.1:**
- Simply replace the updated files
- No additional configuration needed
- All existing data remains intact

---

## 📞 Support

- **GitHub Issues**: [Repository Issues Page](https://github.com/CAP-CalebNewville/tipping-point/issues)
- **Documentation**: User Guide & FAQ

---

**Release Date**: December 20, 2024
**Version**: 2.0.2
**Compatibility**: Upgrades from versions 1.x, 2.0.0, and 2.0.1

*TippingPoint 2.0.2 - Professional Weight & Balance for Modern Aviation*
