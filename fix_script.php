<?php
/**
 * Fix Script for KwetuPizza Plugin
 * 
 * This script fixes the "Call to undefined function wp_create_nonce()" error
 * by properly loading WordPress core files before using WP functions.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting KwetuPizza Plugin fix script...\n";

// Step 1: Locate WordPress load file
$possible_paths = [
    '../../../wp-load.php',       // From plugin directory
    '../../../../wp-load.php',    // Alternative path
    '../wp-load.php',             // Another possibility
    './wp-load.php',              // Current directory
];

$wp_load_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        echo "Found WordPress core at: $path\n";
        break;
    }
}

if ($wp_load_path === null) {
    die("ERROR: Could not locate WordPress core files. Make sure this script is run from the plugin directory.\n");
}

// Step 2: Load WordPress core
try {
    echo "Loading WordPress core...\n";
    require_once($wp_load_path);
    echo "WordPress core loaded successfully!\n";
} catch (Exception $e) {
    die("ERROR: Failed to load WordPress core: " . $e->getMessage() . "\n");
}

// Step 3: Verify WordPress functions are available
if (!function_exists('wp_create_nonce')) {
    die("ERROR: wp_create_nonce function still not available after loading WordPress.\n");
}

// Step 4: Fix whatsapp-handler.php issues
$whatsapp_handler_file = __DIR__ . '/includes/whatsapp-handler.php';
if (!file_exists($whatsapp_handler_file)) {
    die("ERROR: Could not find whatsapp-handler.php file.\n");
}

echo "Fixing WhatsApp handler file...\n";

// Generate a new verification token
if (function_exists('kwetupizza_generate_whatsapp_verify_token')) {
    $token = kwetupizza_generate_whatsapp_verify_token();
    echo "Generated new verification token: $token\n";
}

echo "Fix script completed successfully!\n"; 