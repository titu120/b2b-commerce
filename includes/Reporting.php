<?php
namespace B2B;

if ( ! defined( 'ABSPATH' ) ) exit;

class Reporting {
    public function __construct() {

        add_action( 'wp_ajax_b2b_get_user_analytics', [ $this, 'get_user_analytics' ] );
        add_action( 'wp_ajax_b2b_get_performance_metrics', [ $this, 'get_performance_metrics' ] );
        add_action( 'wp_ajax_b2b_export_report', [ $this, 'export_report' ] );
    }

    

    public function analytics_page() {
        // Verify nonce for security
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'b2b_analytics_nonce')) {
            wp_die(esc_html__('Security check failed', 'b2b-commerce'));
        }
        
        $tab = sanitize_text_field(wp_unslash($_GET['tab'] ?? 'dashboard'));
        
        echo '<div class="b2b-admin-header">';
        echo '<h1><span class="icon dashicons dashicons-chart-line"></span>' . esc_html__('B2B Analytics & Reports', 'b2b-commerce') . '</h1>';
        echo '<p>' . esc_html__('Comprehensive analytics and reporting for your B2B operations.', 'b2b-commerce') . '</p>';
        echo '</div>';
        
        echo '<div class="b2b-admin-card">';
        echo '<nav class="nav-tab-wrapper">';
        $nonce = wp_create_nonce('b2b_analytics_nonce');
        echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-analytics&tab=dashboard&_wpnonce=' . $nonce)) . '" class="nav-tab' . ($tab === 'dashboard' ? ' nav-tab-active' : '') . '">' . esc_html__('Dashboard', 'b2b-commerce') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-analytics&tab=sales&_wpnonce=' . $nonce)) . '" class="nav-tab' . ($tab === 'sales' ? ' nav-tab-active' : '') . '">' . esc_html__('Sales Analytics', 'b2b-commerce') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-analytics&tab=users&_wpnonce=' . $nonce)) . '" class="nav-tab' . ($tab === 'users' ? ' nav-tab-active' : '') . '">' . esc_html__('User Analytics', 'b2b-commerce') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-analytics&tab=performance&_wpnonce=' . $nonce)) . '" class="nav-tab' . ($tab === 'performance' ? ' nav-tab-active' : '') . '">' . esc_html__('Performance', 'b2b-commerce') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-analytics&tab=reports&_wpnonce=' . $nonce)) . '" class="nav-tab' . ($tab === 'reports' ? ' nav-tab-active' : '') . '">' . esc_html__('Reports', 'b2b-commerce') . '</a>';
        echo '</nav>';
        
        switch ($tab) {
            case 'sales':
                $this->sales_analytics_tab();
                break;
            case 'users':
                $this->user_analytics_tab();
                break;
            case 'performance':
                $this->performance_tab();
                break;
            case 'reports':
                $this->reports_tab();
                break;
            default:
                $this->dashboard_tab();
                break;
        }
        
        echo '</div>';
    }

    private function dashboard_tab() {
        echo '<div class="b2b-dashboard-stats">';
        echo '<div class="stat-grid">';
        
        // Key metrics
        $total_revenue = $this->get_total_revenue();
        $total_orders = $this->get_total_orders();
        $active_users = $this->get_active_users();
        $avg_order_value = $this->get_average_order_value();
        
        echo '<div class="stat-card">';
        echo '<h3>' . esc_html__('Total Revenue', 'b2b-commerce') . '</h3>';
        echo '<p class="stat-value">' . wp_kses_post(wc_price($total_revenue)) . '</p>';
        echo '</div>';
        
        echo '<div class="stat-card">';
        echo '<h3>' . esc_html__('Total Orders', 'b2b-commerce') . '</h3>';
        echo '<p class="stat-value">' . esc_html(number_format($total_orders)) . '</p>';
        echo '</div>';
        
        echo '<div class="stat-card">';
        echo '<h3>' . esc_html__('Active Users', 'b2b-commerce') . '</h3>';
        echo '<p class="stat-value">' . esc_html(number_format($active_users)) . '</p>';
        echo '</div>';
        
        echo '<div class="stat-card">';
        echo '<h3>' . esc_html__('Avg Order Value', 'b2b-commerce') . '</h3>';
        echo '<p class="stat-value">' . wp_kses_post(wc_price($avg_order_value)) . '</p>';
        echo '</div>';
        
        echo '</div>';
        
        // Charts
        echo '<div class="chart-container">';
        echo '<div id="revenue-chart" style="height: 300px;"></div>';
        echo '<div id="orders-chart" style="height: 300px;"></div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<script>
        // Initialize charts with Chart.js
        document.addEventListener("DOMContentLoaded", function() {
            // Revenue chart
            const revenueCtx = document.getElementById("revenue-chart").getContext("2d");
            new Chart(revenueCtx, {
                type: "line",
                data: {
                    labels: ["' . esc_js(__('Jan', 'b2b-commerce')) . '", "' . esc_js(__('Feb', 'b2b-commerce')) . '", "' . esc_js(__('Mar', 'b2b-commerce')) . '", "' . esc_js(__('Apr', 'b2b-commerce')) . '", "' . esc_js(__('May', 'b2b-commerce')) . '", "' . esc_js(__('Jun', 'b2b-commerce')) . '"],
                    datasets: [{
                        label: "' . esc_js(__('Revenue', 'b2b-commerce')) . '",
                        data: [12000, 19000, 15000, 25000, 22000, 30000],
                        borderColor: "rgb(75, 192, 192)",
                        tension: 0.1
                    }]
                }
            });
            
            // Orders chart
            const ordersCtx = document.getElementById("orders-chart").getContext("2d");
            new Chart(ordersCtx, {
                type: "bar",
                data: {
                    labels: ["' . esc_js(__('Jan', 'b2b-commerce')) . '", "' . esc_js(__('Feb', 'b2b-commerce')) . '", "' . esc_js(__('Mar', 'b2b-commerce')) . '", "' . esc_js(__('Apr', 'b2b-commerce')) . '", "' . esc_js(__('May', 'b2b-commerce')) . '", "' . esc_js(__('Jun', 'b2b-commerce')) . '"],
                    datasets: [{
                        label: "' . esc_js(__('Orders', 'b2b-commerce')) . '",
                        data: [65, 59, 80, 81, 56, 55],
                        backgroundColor: "rgba(54, 162, 235, 0.2)",
                        borderColor: "rgb(54, 162, 235)",
                        borderWidth: 1
                    }]
                }
            });
        });
        </script>';
    }

    private function sales_analytics_tab() {
        echo '<div class="b2b-sales-analytics">';
        echo '<h2>' . esc_html__('Sales Analytics', 'b2b-commerce') . '</h2>';
        
        // Date range selector
        echo '<div class="date-range-selector">';
        echo '<label>' . esc_html__('Date Range:', 'b2b-commerce') . ' </label>';
        echo '<select id="date-range">';
        echo '<option value="7">' . esc_html__('Last 7 days', 'b2b-commerce') . '</option>';
        echo '<option value="30" selected>' . esc_html__('Last 30 days', 'b2b-commerce') . '</option>';
        echo '<option value="90">' . esc_html__('Last 90 days', 'b2b-commerce') . '</option>';
        echo '<option value="365">' . esc_html__('Last year', 'b2b-commerce') . '</option>';
        echo '</select>';
        echo '<button onclick="updateSalesData()" class="button">' . esc_html__('Update', 'b2b-commerce') . '</button>';
        echo '</div>';
        
        // Sales metrics
        echo '<div class="sales-metrics">';
        echo '<div class="metric-card">';
        echo '<h3>' . esc_html__('Revenue by Customer Type', 'b2b-commerce') . '</h3>';
        echo '<div id="revenue-by-type"></div>';
        echo '</div>';
        
        echo '<div class="metric-card">';
        echo '<h3>' . esc_html__('Top Products', 'b2b-commerce') . '</h3>';
        echo '<div id="top-products"></div>';
        echo '</div>';
        
        echo '<div class="metric-card">';
        echo '<h3>' . esc_html__('Sales Trend', 'b2b-commerce') . '</h3>';
        echo '<div id="sales-trend"></div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }

    private function user_analytics_tab() {
        echo '<div class="b2b-user-analytics">';
        echo '<h2>' . esc_html__('User Analytics', 'b2b-commerce') . '</h2>';
        
        // User statistics
        $total_users = $this->get_total_users();
        $new_users_this_month = $this->get_new_users_this_month();
        $active_users_this_month = $this->get_active_users_this_month();
        $conversion_rate = $this->get_conversion_rate();
        
        echo '<div class="user-stats">';
        echo '<div class="stat-item">';
        echo '<h3>' . esc_html__('Total Users', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html(number_format($total_users)) . '</p>';
        echo '</div>';
        
        echo '<div class="stat-item">';
        echo '<h3>' . esc_html__('New Users (This Month)', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html(number_format($new_users_this_month)) . '</p>';
        echo '</div>';
        
        echo '<div class="stat-item">';
        echo '<h3>' . esc_html__('Active Users (This Month)', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html(number_format($active_users_this_month)) . '</p>';
        echo '</div>';
        
        echo '<div class="stat-item">';
        echo '<h3>' . esc_html__('Conversion Rate', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html(number_format($conversion_rate, 1)) . '%</p>';
        echo '</div>';
        echo '</div>';
        
        // User activity chart
        echo '<div class="user-activity-chart">';
        echo '<h3>' . esc_html__('User Activity Over Time', 'b2b-commerce') . '</h3>';
        echo '<div id="user-activity-chart"></div>';
        echo '</div>';
        
        echo '</div>';
    }

    private function performance_tab() {
        echo '<div class="b2b-performance">';
        echo '<h2>' . esc_html__('Performance Metrics', 'b2b-commerce') . '</h2>';
        
        // Performance metrics
        $avg_response_time = $this->get_average_response_time();
        $order_fulfillment_rate = $this->get_order_fulfillment_rate();
        $customer_satisfaction = $this->get_customer_satisfaction();
        $repeat_customer_rate = $this->get_repeat_customer_rate();
        
        echo '<div class="performance-metrics">';
        echo '<div class="metric-item">';
        echo '<h3>' . esc_html__('Avg Response Time', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html($avg_response_time) . ' ' . esc_html__('hours', 'b2b-commerce') . '</p>';
        echo '</div>';
        
        echo '<div class="metric-item">';
        echo '<h3>' . esc_html__('Order Fulfillment Rate', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html(number_format($order_fulfillment_rate, 1)) . '%</p>';
        echo '</div>';
        
        echo '<div class="metric-item">';
        echo '<h3>' . esc_html__('Customer Satisfaction', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html(number_format($customer_satisfaction, 1)) . '/5</p>';
        echo '</div>';
        
        echo '<div class="metric-item">';
        echo '<h3>' . esc_html__('Repeat Customer Rate', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html(number_format($repeat_customer_rate, 1)) . '%</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }

    private function reports_tab() {
        echo '<div class="b2b-reports">';
        echo '<h2>' . esc_html__('Reports', 'b2b-commerce') . '</h2>';
        
        echo '<div class="report-options">';
        echo '<h3>' . esc_html__('Generate Reports', 'b2b-commerce') . '</h3>';
        
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="b2b_generate_report">';
        echo wp_kses(wp_nonce_field('b2b_generate_report', 'b2b_report_nonce', true, false), array('input' => array('type' => array(), 'name' => array(), 'value' => array())));
        
        echo '<p><label>' . esc_html__('Report Type:', 'b2b-commerce') . ' </label>';
        echo '<select name="report_type" required>';
        echo '<option value="">' . esc_html__('Select Report', 'b2b-commerce') . '</option>';
        echo '<option value="sales_summary">' . esc_html__('Sales Summary', 'b2b-commerce') . '</option>';
        echo '<option value="customer_analysis">' . esc_html__('Customer Analysis', 'b2b-commerce') . '</option>';
        echo '<option value="product_performance">' . esc_html__('Product Performance', 'b2b-commerce') . '</option>';
        echo '<option value="revenue_analysis">' . esc_html__('Revenue Analysis', 'b2b-commerce') . '</option>';
        echo '</select></p>';
        
        echo '<p><label>' . esc_html__('Date Range:', 'b2b-commerce') . ' </label>';
        echo '<select name="date_range" required>';
        echo '<option value="7">' . esc_html__('Last 7 days', 'b2b-commerce') . '</option>';
        echo '<option value="30">' . esc_html__('Last 30 days', 'b2b-commerce') . '</option>';
        echo '<option value="90">' . esc_html__('Last 90 days', 'b2b-commerce') . '</option>';
        echo '<option value="365">' . esc_html__('Last year', 'b2b-commerce') . '</option>';
        echo '</select></p>';
        
        echo '<p><label>' . esc_html__('Format:', 'b2b-commerce') . ' </label>';
        echo '<select name="format" required>';
        echo '<option value="csv">' . esc_html__('CSV', 'b2b-commerce') . '</option>';
        echo '<option value="pdf">' . esc_html__('PDF', 'b2b-commerce') . '</option>';
        echo '<option value="excel">' . esc_html__('Excel', 'b2b-commerce') . '</option>';
        echo '</select></p>';
        
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Generate Report', 'b2b-commerce') . '</button></p>';
        echo '</form>';
        echo '</div>';
        
        echo '</div>';
    }

    // Helper methods for analytics
    private function get_total_revenue() {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return 0;
        }
        
        $orders = wc_get_orders([
            'status' => 'completed',
            'limit' => -1
        ]);
        
        $total = 0;
        foreach ($orders as $order) {
            $total += $order->get_total();
        }
        
        return $total;
    }

    private function get_total_orders() {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return 0;
        }
        
        $orders = wc_get_orders([
            'status' => 'completed',
            'limit' => -1
        ]);
        
        return count($orders);
    }

    private function get_active_users() {
        $b2b_roles = apply_filters('b2b_user_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        $users = get_users([
            'role__in' => $b2b_roles,
            'meta_query' => [
                [
                    'key' => 'last_activity',
                    'value' => gmdate('Y-m-d', strtotime(apply_filters('b2b_active_user_period', '-30 days'))),
                    'compare' => '>=',
                    'type' => 'DATE'
                ]
            ]
        ]);
        
        return count($users);
    }

    private function get_average_order_value() {
        $orders = wc_get_orders([
            'status' => ['completed', 'processing'],
            'limit' => -1
        ]);
        
        if (empty($orders)) return 0;
        
        $total = 0;
        foreach ($orders as $order) {
            $total += $order->get_total();
        }
        
        return $total / count($orders);
    }

    private function get_total_users() {
        $b2b_roles = apply_filters('b2b_user_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        $users = get_users([
            'role__in' => $b2b_roles
        ]);
        
        return count($users);
    }

    private function get_new_users_this_month() {
        $b2b_roles = apply_filters('b2b_user_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        $users = get_users([
            'role__in' => $b2b_roles,
            'date_query' => [
                [
                    'after' => apply_filters('b2b_new_user_period', '1 month ago')
                ]
            ]
        ]);
        
        return count($users);
    }

    private function get_active_users_this_month() {
        $b2b_roles = apply_filters('b2b_user_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        $users = get_users([
            'role__in' => $b2b_roles,
            'meta_query' => [
                [
                    'key' => 'last_activity',
                    'value' => gmdate('Y-m-d', strtotime(apply_filters('b2b_active_user_period', '-30 days'))),
                    'compare' => '>=',
                    'type' => 'DATE'
                ]
            ]
        ]);
        
        return count($users);
    }

    private function get_conversion_rate() {
        $default_visitors = apply_filters('b2b_default_visitor_count', 1000);
        $total_visitors = get_option('b2b_total_visitors', $default_visitors);
        $total_orders = $this->get_total_orders();
        
        if ($total_visitors == 0) return 0;
        
        return ($total_orders / $total_visitors) * 100;
    }

    private function get_average_response_time() {
        // Placeholder - in real implementation, track actual response times
        $default_response_time = apply_filters('b2b_default_response_time', 2.5);
        return get_option('b2b_avg_response_time', $default_response_time);
    }

    private function get_order_fulfillment_rate() {
        $total_orders = wc_get_orders([
            'status' => ['completed', 'processing'],
            'limit' => -1
        ]);
        
        $fulfilled_orders = wc_get_orders([
            'status' => ['completed'],
            'limit' => -1
        ]);
        
        if (empty($total_orders)) return 0;
        
        return (count($fulfilled_orders) / count($total_orders)) * 100;
    }

    private function get_customer_satisfaction() {
        // Placeholder - in real implementation, track actual satisfaction scores
        $default_satisfaction = apply_filters('b2b_default_satisfaction_score', 4.2);
        return get_option('b2b_customer_satisfaction', $default_satisfaction);
    }

    private function get_repeat_customer_rate() {
        $customers = [];
        $repeat_customers = 0;
        
        $orders = wc_get_orders([
            'status' => ['completed', 'processing'],
            'limit' => -1
        ]);
        
        foreach ($orders as $order) {
            $customer_id = $order->get_customer_id();
            if (!isset($customers[$customer_id])) {
                $customers[$customer_id] = 0;
            }
            $customers[$customer_id]++;
        }
        
        foreach ($customers as $customer_id => $order_count) {
            if ($order_count > 1) {
                $repeat_customers++;
            }
        }
        
        if (empty($customers)) return 0;
        
        return ($repeat_customers / count($customers)) * 100;
    }
} 