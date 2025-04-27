<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== Checking for Plugin Activation Issues ===\n\n";

// Check PHP version
echo "PHP Version: " . phpversion() . "\n";

// Check for required PHP extensions
$required_extensions = ['curl', 'json', 'mbstring', 'mysqli'];
echo "\nRequired PHP Extensions:\n";
foreach ($required_extensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? "OK" : "MISSING") . "\n";
}

// Check WordPress directory paths
echo "\nWordPress Directory Paths:\n";
if (!defined('ABSPATH')) {
    echo "ABSPATH: Not defined (running outside WordPress)\n";
} else {
    echo "ABSPATH: " . ABSPATH . "\n";
}

echo "Current script path: " . __FILE__ . "\n";
echo "Plugin directory: " . dirname(__FILE__) . "\n";

// Check file paths for required files
echo "\nChecking Required Files:\n";
$required_files = [
    'includes/common-functions.php',
    'includes/whatsapp-handler.php',
    'includes/order-tracking.php',
    'includes/kwetu-debug.php'
];

foreach ($required_files as $file) {
    $full_path = dirname(__FILE__) . '/' . $file;
    echo "$file: " . (file_exists($full_path) ? "OK" : "MISSING") . "\n";
}

// Check for database tables
echo "\nChecking Database Connection:\n";
try {
    // Attempt to load WordPress config
    if (file_exists('../../../wp-config.php')) {
        echo "wp-config.php found, attempting to load\n";
        include_once('../../../wp-config.php');
        
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_NAME')) {
            echo "Database credentials found\n";
            
            // Try to connect to the database
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            
            if ($conn->connect_error) {
                echo "Database connection failed: " . $conn->connect_error . "\n";
            } else {
                echo "Database connection successful\n";
                
                // Check if plugin tables exist
                $tables = [
                    'kwetupizza_users',
                    'kwetupizza_products',
                    'kwetupizza_orders',
                    'kwetupizza_order_items',
                    'kwetupizza_transactions',
                    'kwetupizza_addresses',
                    'kwetupizza_order_tracking'
                ];
                
                echo "\nChecking Plugin Tables:\n";
                
                // Get table prefix if available
                $prefix = defined('$table_prefix') ? $table_prefix : 'wp_';
                
                foreach ($tables as $table) {
                    $full_table_name = $prefix . $table;
                    $result = $conn->query("SHOW TABLES LIKE '$full_table_name'");
                    echo "$full_table_name: " . ($result->num_rows > 0 ? "EXISTS" : "NOT FOUND") . "\n";
                }
                
                $conn->close();
            }
        } else {
            echo "Database credentials not found in wp-config.php\n";
        }
    } else {
        echo "wp-config.php not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Try to include the main plugin file and catch any errors
echo "\nAttempting to include main plugin file:\n";
try {
    include_once('kwetu-pizza-plugin.php');
    echo "Main plugin file included successfully\n";
} catch (Error $e) {
    echo "PHP Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\n=== End of Checks ===\n"; 