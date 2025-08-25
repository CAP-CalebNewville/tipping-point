<?php
// Setup script for SQLite-based TippingPoint
include_once 'common.inc';
PageHeader("Initial Setup");
?>

<body>
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-8 col-md-10">
<div class="card mt-4">
<div class="card-header bg-primary text-white text-center">
<h3 class="mb-0">TippingPoint - Initial Setup</h3>
</div>
<div class="card-body">

<?php
// Check if already installed
if (isSystemInstalled() && (!isset($_REQUEST['func']) || $_REQUEST['func']=="")) {
	echo "<div class='alert alert-info text-center'>TippingPoint is already installed.</div>";
	echo "<div class='text-center mt-3'>";
	echo "<a href='index.php' class='btn btn-primary'>Go to Application</a> ";
	echo "<a href='admin.php' class='btn btn-outline-primary'>Admin Interface</a>";
	echo "</div>";

} else {
	switch (isset($_REQUEST["func"]) ? $_REQUEST["func"] : "") {
		case "step2":
			// Requirements check passed, create SQLite database
			try {
				include 'database.inc';
				$pdo = createSQLiteDatabase();
				
				echo "<div class='alert alert-success'>";
				echo "<h5 class='alert-heading'>Database Created Successfully!</h5>";
				echo "<p>SQLite database has been created and initialized.</p>";
				echo "</div>";
				
				echo "<h5 class='card-title'>System Configuration</h5>";
				echo "<form method='post' action='setup.php'><input type='hidden' name='func' value='step3'>";
				echo "<div class='mb-3'>";
				echo "<label for='site_name' class='form-label'>Site/Organization Name</label>";
				echo "<input type='text' class='form-control' id='site_name' name='site_name' placeholder='e.g., Hometown Flying Club' required>";
				echo "</div>";
				echo "<div class='mb-3'>";
				echo "<label for='administrator' class='form-label'>Administrator E-mail Address</label>";
				echo "<input type='email' class='form-control' id='administrator' name='administrator' placeholder='admin@example.com' required>";
				echo "</div>";
				echo "<div class='mb-3'>";
				echo "<label for='timezone' class='form-label'>Local Time Zone</label>";
				TimezoneList("America/New_York"); // Default to US Eastern
				echo "</div>";
				echo "<div class='d-grid'>";
				echo "<button type='submit' class='btn btn-primary'>Continue to Step 3</button>";
				echo "</div>";
				echo "</form>";
				
			} catch (Exception $e) {
				echo "<div class='alert alert-danger'>";
				echo "<h5 class='alert-heading'>Database Creation Failed</h5>";
				echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
				echo "</div>";
				echo "<a href='setup.php' class='btn btn-primary'>Try Again</a>";
			}
			break;

		case "step3":
			// Save configuration settings
			try {
				include 'database.inc';
				$db = getDB();
				
				// Insert system settings
				$settings = [
					['site_name', $_REQUEST['site_name']],
					['administrator', $_REQUEST['administrator']],
					['timezone', $_REQUEST['timezone']],
					['update_check', time()],
					['update_version', $ver],
					['database_type', 'sqlite'],
					['pilot_signature', '0']
				];
				
				foreach ($settings as $setting) {
					$db->query("INSERT INTO configuration (item, value) VALUES (?, ?)", $setting);
				}
				
				echo "<h5 class='card-title'>Create Administrator Account</h5>";
				echo "<p class='text-muted'>Create the first user account with administrative privileges.</p>";
				echo "<form method='post' action='setup.php'><input type='hidden' name='func' value='step4'>";
				echo "<div class='mb-3'>";
				echo "<label for='username' class='form-label'>Username</label>";
				echo "<input type='text' class='form-control' id='username' name='username' required>";
				echo "</div>";
				echo "<div class='mb-3'>";
				echo "<label for='password' class='form-label'>Password</label>";
				echo "<input type='password' class='form-control' id='password' name='password' minlength='6' required>";
				echo "<div class='form-text'>Password must be at least 6 characters long.</div>";
				echo "</div>";
				echo "<div class='mb-3'>";
				echo "<label for='name' class='form-label'>Full Name</label>";
				echo "<input type='text' class='form-control' id='name' name='name' required>";
				echo "</div>";
				echo "<div class='mb-3'>";
				echo "<label for='email' class='form-label'>E-mail Address</label>";
				echo "<input type='email' class='form-control' id='email' name='email' required>";
				echo "</div>";
				echo "<div class='d-grid'>";
				echo "<button type='submit' class='btn btn-success'>Complete Setup</button>";
				echo "</div>";
				echo "</form>";
				
			} catch (Exception $e) {
				echo "<div class='alert alert-danger'>";
				echo "<h5 class='alert-heading'>Configuration Error</h5>";
				echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
				echo "</div>";
			}
			break;

		case "step4":
			// Create administrator user and finish setup
			try {
				include 'database.inc';
				$db = getDB();
				
				// Create administrator user
				$hashedPassword = password_hash($_REQUEST['password'], PASSWORD_DEFAULT);
				$db->query("INSERT INTO users (username, password, name, email, superuser) VALUES (?, ?, ?, ?, 1)", [
					$_REQUEST['username'],
					$hashedPassword,
					$_REQUEST['name'],
					$_REQUEST['email']
				]);
				
				// Log setup completion
				$db->query("INSERT INTO audit (who, what) VALUES (?, ?)", [
					$_REQUEST['username'],
					'SETUP: Initial setup completed'
				]);
				
				// Create .htaccess file for security
				$htaccess_content = "# TippingPoint Security Rules - Generated by setup.php
# Protect sensitive files and directories

# Deny access to configuration files
<Files ~ \"\\.(inc|conf)$\">
    Require all denied
</Files>

# Deny access to database files
<Files ~ \"\\.(db|sqlite|sqlite3)$\">
    Require all denied
</Files>

# Deny access to backup files
<Files ~ \"\\.(bak|backup|old|tmp)$\">
    Require all denied
</Files>

# Deny access to log files
<Files ~ \"\\.(log)$\">
    Require all denied
</Files>

# Disable directory browsing
Options -Indexes

# Prevent access to sensitive file patterns
<FilesMatch \"(^#.*#|\\.(bak|config|dist|fla|inc|ini|log|psd|sh|sql|sw[op])|~)$\">
    Require all denied
</FilesMatch>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection \"1; mode=block\"
    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"
</IfModule>
";

				try {
					if (file_put_contents('.htaccess', $htaccess_content) !== false) {
						$htaccess_created = true;
						
						// Test if .htaccess is being processed by creating a simple test
						$test_htaccess = "# Test file\nRewriteEngine On\nRewriteRule ^test_htaccess_working$ - [R=410,L]";
						file_put_contents('.htaccess_test', $test_htaccess);
						
						// Check if we can detect if .htaccess processing works
						$htaccess_working = false;
						if (function_exists('apache_get_modules')) {
							$modules = apache_get_modules();
							$htaccess_working = in_array('mod_rewrite', $modules);
						}
						
						// Clean up test file
						if (file_exists('.htaccess_test')) {
							unlink('.htaccess_test');
						}
						
						$db->query("INSERT INTO audit (who, what) VALUES (?, ?)", [
							$_REQUEST['username'],
							'SETUP: Created .htaccess security file with protections for: *.inc, *.db, *.sqlite*, data/, .git/, backup files, and security headers. Working: ' . ($htaccess_working ? 'Yes' : 'Unknown - may need Apache AllowOverride configuration')
						]);
					} else {
						$htaccess_created = false;
						$htaccess_working = false;
					}
				} catch (Exception $e) {
					$htaccess_created = false;
					$htaccess_working = false;
					$db->query("INSERT INTO audit (who, what) VALUES (?, ?)", [
						$_REQUEST['username'],
						'SETUP: Failed to create .htaccess security file: ' . $e->getMessage()
					]);
				}
				
				echo "<div class='alert alert-success'>";
				echo "<h4 class='alert-heading'>Setup Complete!</h4>";
				echo "<p>TippingPoint has been successfully installed with SQLite database support.</p>";
				if ($htaccess_created) {
					if (isset($htaccess_working) && $htaccess_working) {
						echo "<p><i class='text-success'>✓ Security .htaccess file created and working</i></p>";
					} else {
						echo "<p><i class='text-warning'>⚠ Security .htaccess file created but may not be active</i></p>";
						echo "<div class='alert alert-warning mt-3'>";
						echo "<h6>Apache Configuration Needed</h6>";
						echo "<p>The .htaccess file was created but may not be protecting files. To enable .htaccess processing:</p>";
						echo "<ol>";
						echo "<li>Edit your Apache virtual host configuration</li>";
						echo "<li>Change <code>AllowOverride None</code> to <code>AllowOverride All</code></li>";
						echo "<li>Restart Apache: <code>sudo systemctl restart apache2</code></li>";
						echo "</ol>";
						echo "<p><strong>Alternative:</strong> Move the security rules from .htaccess directly into your Apache virtual host configuration.</p>";
						echo "</div>";
					}
				} else {
					echo "<p><i class='text-danger'>✗ Could not create .htaccess file - manual security configuration required</i></p>";
				}
				echo "<hr>";
				echo "<div class='row'>";
				echo "<div class='col-md-6'>";
				echo "<h6>What's Next:</h6>";
				echo "<ul>";
				echo "<li>Access the <strong>Admin Interface</strong> to configure aircraft</li>";
				echo "<li>Add your aircraft weight & balance data</li>";
				echo "<li>Start using the weight & balance calculator</li>";
				if ($htaccess_created) {
					if (isset($htaccess_working) && $htaccess_working) {
						echo "<li><small class='text-success'>Security: .htaccess protections active</small></li>";
					} else {
						echo "<li><small class='text-warning'>Security: .htaccess created (may need Apache config)</small></li>";
					}
				} else {
					echo "<li><small class='text-danger'>Security: Manual protection setup needed</small></li>";
				}
				echo "</ul>";
				echo "</div>";
				echo "<div class='col-md-6'>";
				echo "<h6>Administrator Login:</h6>";
				echo "<p><strong>Username:</strong> " . htmlspecialchars($_REQUEST['username']) . "<br>";
				echo "<strong>Password:</strong> [as entered]</p>";
				echo "</div>";
				echo "</div>";
				echo "</div>";
				
				echo "<div class='d-grid gap-2 d-md-block text-center'>";
				echo "<a href='admin.php' class='btn btn-primary'>Admin Interface</a> ";
				echo "<a href='index.php' class='btn btn-outline-primary'>Weight & Balance Calculator</a>";
				echo "</div>";
				
				echo "<div class='mt-4 text-center'>";
				echo "<small class='text-muted'>Need help? Visit our <a href='https://github.com/CAP-CalebNewville/tipping-point' target='_blank'>GitHub repository</a> for documentation and support.</small>";
				echo "</div>";
				
			} catch (Exception $e) {
				echo "<div class='alert alert-danger'>";
				echo "<h5 class='alert-heading'>Setup Error</h5>";
				echo "<p>Error creating administrator account: " . htmlspecialchars($e->getMessage()) . "</p>";
				echo "</div>";
			}
			break;

		default:
			// Step 1: Requirements check
			echo "<h5 class='card-title'>Welcome to TippingPoint Setup</h5>";
			echo "<p class='text-muted'>Let's check if your server meets the requirements to run TippingPoint.</p>";
			
			$requirementsPassed = displayRequirementsCheck();
			
			if ($requirementsPassed) {
				echo "<form method='post' action='setup.php'>";
				echo "<input type='hidden' name='func' value='step2'>";
				echo "<div class='d-grid'>";
				echo "<button type='submit' class='btn btn-success btn-lg'>Begin Setup</button>";
				echo "</div>";
				echo "</form>";
			} else {
				echo "<div class='alert alert-warning'>";
				echo "<h6 class='alert-heading'>Requirements Not Met</h6>";
				echo "<p>Please resolve the issues above before proceeding with setup. Contact your system administrator if you need assistance installing the required PHP extensions.</p>";
				echo "</div>";
				
				echo "<div class='text-center'>";
				echo "<a href='setup.php' class='btn btn-primary'>Recheck Requirements</a>";
				echo "</div>";
			}
			break;
	}
}
?>

</div>
</div>
</div>
</div>
</div>

<?php
PageFooter("TippingPoint Setup", "setup@tippingpoint", $ver);
?>