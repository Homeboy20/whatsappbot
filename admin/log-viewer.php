<?php
/**
 * KwetuPizza Log Viewer
 * 
 * Admin page for viewing and managing plugin logs
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the log viewer page
 */
function kwetu_render_log_viewer() {
    // Handle actions
    if (isset($_POST['action']) && current_user_can('manage_options')) {
        if ($_POST['action'] === 'clear_logs' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'kwetu_clear_logs')) {
            kwetu_clear_log();
            echo '<div class="notice notice-success"><p>Logs cleared successfully.</p></div>';
        }
    }
    
    // Get log content
    $log_content = '';
    if (function_exists('kwetu_get_log_content')) {
        $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
        $log_content = kwetu_get_log_content($lines);
    } else {
        $log_file = plugin_dir_path(dirname(__FILE__)) . 'debug.txt';
        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
        }
    }
    
    // Filter by level if requested
    $filter_level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    if (!empty($filter_level)) {
        $filtered_content = '';
        $lines = explode("\n", $log_content);
        foreach ($lines as $line) {
            if (strpos($line, "[$filter_level]") !== false) {
                $filtered_content .= $line . "\n";
            }
        }
        $log_content = $filtered_content;
    }
    
    ?>
    <div class="wrap">
        <h1>KwetuPizza Log Viewer</h1>
        
        <div class="log-controls">
            <form method="get">
                <input type="hidden" name="page" value="kwetupizza-logs">
                
                <div class="log-filters">
                    <label for="lines">Show lines:</label>
                    <select name="lines" id="lines">
                        <option value="50" <?php selected(isset($_GET['lines']) ? $_GET['lines'] : '100', '50'); ?>>50</option>
                        <option value="100" <?php selected(isset($_GET['lines']) ? $_GET['lines'] : '100', '100'); ?>>100</option>
                        <option value="500" <?php selected(isset($_GET['lines']) ? $_GET['lines'] : '100', '500'); ?>>500</option>
                        <option value="1000" <?php selected(isset($_GET['lines']) ? $_GET['lines'] : '100', '1000'); ?>>1000</option>
                        <option value="0" <?php selected(isset($_GET['lines']) ? $_GET['lines'] : '100', '0'); ?>>All</option>
                    </select>
                    
                    <label for="level">Filter by level:</label>
                    <select name="level" id="level">
                        <option value="" <?php selected($filter_level, ''); ?>>All Levels</option>
                        <option value="INFO" <?php selected($filter_level, 'INFO'); ?>>INFO</option>
                        <option value="WARNING" <?php selected($filter_level, 'WARNING'); ?>>WARNING</option>
                        <option value="ERROR" <?php selected($filter_level, 'ERROR'); ?>>ERROR</option>
                        <option value="DEBUG" <?php selected($filter_level, 'DEBUG'); ?>>DEBUG</option>
                        <option value="EVENT" <?php selected($filter_level, 'EVENT'); ?>>EVENT</option>
                    </select>
                    
                    <button type="submit" class="button">Apply Filters</button>
                </div>
            </form>
            
            <form method="post" style="margin-top: 10px;">
                <?php wp_nonce_field('kwetu_clear_logs'); ?>
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="button button-secondary" onclick="return confirm('Are you sure you want to clear all logs?');">Clear Logs</button>
            </form>
        </div>
        
        <div class="log-viewer" style="margin-top: 20px;">
            <?php if (empty($log_content)): ?>
                <div class="notice notice-info">
                    <p>No logs available.</p>
                </div>
            <?php else: ?>
                <pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 600px; border: 1px solid #ddd;"><?php echo esc_html($log_content); ?></pre>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .log-controls {
            background: #fff;
            padding: 15px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .log-filters {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .log-filters label {
            font-weight: bold;
        }
    </style>
    <?php
}

/**
 * Add log viewer to admin menu
 */
function kwetu_add_log_viewer_menu() {
    add_submenu_page(
        'kwetupizza-dashboard',
        'Log Viewer',
        'Log Viewer',
        'manage_options',
        'kwetupizza-logs',
        'kwetu_render_log_viewer'
    );
}
add_action('admin_menu', 'kwetu_add_log_viewer_menu', 30); // Lower priority to ensure it appears at the end 