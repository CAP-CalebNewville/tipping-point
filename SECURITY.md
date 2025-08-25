# TippingPoint Security Configuration

TippingPoint automatically creates an `.htaccess` file during setup to protect sensitive files. However, this requires proper Apache configuration to work.

## Quick Security Check

To test if your security protections are working, try accessing these URLs:
- `http://yoursite.com/path/to/tippingpoint/database.inc` (should be blocked)
- `http://yoursite.com/path/to/tippingpoint/data/` (should be blocked) 
- `http://yoursite.com/path/to/tippingpoint/data/tippingpoint.db` (should be blocked)

If you can access these files, your .htaccess protections are **not active**.

## Enabling .htaccess Protection

### Option 1: Apache Virtual Host Configuration (Recommended)

Edit your Apache virtual host configuration file and ensure you have:

```apache
<Directory "/var/www/html/wtbal">
    AllowOverride All
    Require all granted
</Directory>
```

Then restart Apache:
```bash
sudo systemctl restart apache2
```

**Important:** The .htaccess file cannot protect directories (like `data/`). For complete protection, you'll need to add directory rules to your Apache configuration (see Option 2 below).

### Option 2: Direct Apache Configuration (More Secure)

Instead of using .htaccess, add these rules directly to your Apache virtual host configuration:

```apache
<Directory "/var/www/html/wtbal">
    # Deny access to configuration files
    <Files ~ "\.(inc|conf)$">
        Require all denied
    </Files>

    # Deny access to database files
    <Files ~ "\.(db|sqlite|sqlite3)$">
        Require all denied
    </Files>

    # Deny access to backup files
    <Files ~ "\.(bak|backup|old|tmp)$">
        Require all denied
    </Files>

    # Deny access to log files
    <Files ~ "\.log$">
        Require all denied
    </Files>

    # Disable directory browsing
    Options -Indexes

    # Prevent access to sensitive file patterns
    <FilesMatch "(^#.*#|\.(bak|config|dist|fla|inc|ini|log|psd|sh|sql|sw[op])|~)$">
        Require all denied
    </FilesMatch>

    # Security headers
    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options nosniff
        Header always set X-Frame-Options DENY
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
    </IfModule>
</Directory>

# Protect data directory completely
<Directory "/var/www/html/wtbal/data">
    Require all denied
</Directory>

# Protect git directory if it exists
<Directory "/var/www/html/wtbal/.git">
    Require all denied
</Directory>
```

## Protected Files and Directories

The security configuration protects:

- **Configuration files**: `*.inc`, `*.conf`
- **Database files**: `*.db`, `*.sqlite`, `*.sqlite3`
- **Data directory**: `data/` (complete access denial)
- **Backup files**: `*.bak`, `*.backup`, `*.old`, `*.tmp`
- **Log files**: `*.log`
- **Git repository**: `.git/` directory
- **Development files**: Editor temp files, config files, etc.

## Additional Security Recommendations

1. **File Permissions**: Ensure proper file permissions:
   ```bash
   chmod 644 *.php *.inc
   chmod 755 data/
   chmod 600 data/*.db
   ```

2. **Database Location**: Consider moving the SQLite database outside the web root:
   - Move `data/tippingpoint.db` to `/var/lib/tippingpoint/`
   - Update `database.inc` with the new path

3. **HTTPS**: Always use HTTPS in production:
   - Configure SSL certificate
   - Redirect HTTP to HTTPS

4. **Regular Updates**: Keep TippingPoint updated to the latest version

5. **Backup Security**: Ensure backups are stored securely and not web-accessible

## Troubleshooting

If protections aren't working:

1. Check Apache error logs: `/var/log/apache2/error.log`
2. Verify .htaccess file exists and has correct content
3. Test Apache configuration: `sudo apache2ctl configtest`
4. Ensure mod_rewrite is enabled: `sudo a2enmod rewrite`
5. Check AllowOverride settings in your virtual host

## Testing Your Security

Create a simple test script to verify protections:

```bash
#!/bin/bash
echo "Testing TippingPoint security..."
curl -s -o /dev/null -w "%{http_code}" http://yoursite.com/path/database.inc
echo " - database.inc (should be 403)"
curl -s -o /dev/null -w "%{http_code}" http://yoursite.com/path/data/
echo " - data/ directory (should be 403)"
```

Expected result: All tests should return HTTP 403 (Forbidden).