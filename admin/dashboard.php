<?php
//Dashboard Query Adjustments
if (!function_exists('kwetupizza_render_dashboard')) {
    function kwetupizza_render_dashboard() {
        global $wpdb;

    // Updated query for top-selling products
    $top_selling_products = $wpdb->get_results(
        "SELECT p.product_name, COUNT(oi.id) as total_sales
        FROM {$wpdb->prefix}kwetupizza_order_items oi
        JOIN {$wpdb->prefix}kwetupizza_products p ON oi.product_id = p.id
        GROUP BY p.product_name
        ORDER BY total_sales DESC
        LIMIT 5"
    );

    // Updated query for payment provider analysis
    $payment_providers = $wpdb->get_results(
        "SELECT payment_provider, COUNT(*) as total_transactions
        FROM {$wpdb->prefix}kwetupizza_transactions
        GROUP BY payment_provider"
    );


        // Fetch order growth data from the database (grouped by month)
        $order_growth_data = $wpdb->get_results("
            SELECT MONTH(created_at) as month, COUNT(*) as total_orders
            FROM {$wpdb->prefix}kwetupizza_orders
            WHERE YEAR(created_at) = YEAR(CURDATE())
            GROUP BY MONTH(created_at)
        ");

        $order_months = [];
        $order_counts = [];
        foreach ($order_growth_data as $data) {
            $order_months[] = date('F', mktime(0, 0, 0, $data->month, 10)); // Convert month number to month name
            $order_counts[] = $data->total_orders;
        }

        // Fetch popular products data from the database
        $popular_products_data = $wpdb->get_results("
            SELECT p.product_name, COUNT(o.id) as total_sales
            FROM {$wpdb->prefix}kwetupizza_order_items o
            JOIN {$wpdb->prefix}kwetupizza_products p ON o.product_id = p.id
            GROUP BY p.product_name
            ORDER BY total_sales DESC
            LIMIT 5
        ");

        $popular_product_names = [];
        $popular_product_sales = [];
        foreach ($popular_products_data as $data) {
            $popular_product_names[] = $data->product_name;
            $popular_product_sales[] = $data->total_sales;
        }

        // Fetch transaction volume breakdown data from the database
        $transaction_volume_data = $wpdb->get_results("
            SELECT payment_method, COUNT(*) as total_transactions
            FROM {$wpdb->prefix}kwetupizza_transactions
            GROUP BY payment_method
        ");

        $transaction_labels = [];
        $transaction_counts = [];
        foreach ($transaction_volume_data as $data) {
            $transaction_labels[] = ucfirst($data->payment_method);
            $transaction_counts[] = $data->total_transactions;
        }
    ?>
    <div class="wrap">
        <h1>KwetuPizza Business Insights Dashboard</h1>
        
        <div class="dashboard-container">
            <div class="dashboard-section">
                <h2>Order Growth</h2>
                <canvas id="orderGrowthChart" width="400" height="200"></canvas>
            </div>
            <div class="dashboard-section">
                <h2>Popular Products</h2>
                <canvas id="popularProductsChart" width="400" height="200"></canvas>
            </div>
            <div class="dashboard-section">
                <h2>Transaction Volume Breakdown</h2>
                <canvas id="transactionVolumeChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <style>
        .dashboard-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            margin-top: 20px;
        }
        .dashboard-section {
            width: 30%;
            text-align: center;
            margin-bottom: 40px;
        }
    </style>
    
    <!-- Include Chart.js from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Order Growth Chart
        var ctx1 = document.getElementById('orderGrowthChart').getContext('2d');
        var orderGrowthChart = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($order_months); ?>,
                datasets: [{
                    label: 'Order Growth',
                    data: <?php echo json_encode($order_counts); ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                }]
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Order Growth Over Time'
                },
            }
        });

        // Popular Products Chart
        var ctx2 = document.getElementById('popularProductsChart').getContext('2d');
        var popularProductsChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($popular_product_names); ?>,
                datasets: [{
                    label: 'Popular Products',
                    data: <?php echo json_encode($popular_product_sales); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(153, 102, 255, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Popular Products'
                },
            }
        });

        // Transaction Volume Chart
        var ctx3 = document.getElementById('transactionVolumeChart').getContext('2d');
        var transactionVolumeChart = new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($transaction_labels); ?>,
                datasets: [{
                    label: 'Transaction Volume',
                    data: <?php echo json_encode($transaction_counts); ?>,
                    backgroundColor: [
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Transaction Volume Breakdown'
                },
            }
        });
    </script>
    <?php
    }
}

// Load scripts for Chart.js
function kwetupizza_load_admin_assets() {
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
}
add_action('admin_enqueue_scripts', 'kwetupizza_load_admin_assets');
