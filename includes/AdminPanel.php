<?php
    namespace B2B;

    if (! defined('ABSPATH')) {
        exit;
    }

    class AdminPanel
    {
        public function __construct()
        {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_notices', [$this, 'show_admin_notifications']);
            add_action('wp_ajax_b2b_dismiss_notification', [$this, 'dismiss_notification']);
            add_action('admin_post_b2b_update_quote', [$this, 'handle_update_quote']);
            add_action('wp_ajax_b2b_update_quote_ajax', [$this, 'handle_update_quote_ajax']);
            add_action('admin_post_b2b_update_inquiry', [$this, 'handle_update_inquiry']);
            add_action('admin_post_b2b_delete_inquiry', [$this, 'handle_delete_inquiry']);
            add_action('admin_post_b2b_migrate_quotes', [$this, 'handle_migrate_quotes']);
        }

        // Add main admin menu and submenus
        public function add_admin_menu()
        {
            add_menu_page(
                __('B2B Commerce', 'b2b-commerce'),
                __('B2B Commerce', 'b2b-commerce'),
                'manage_options',
                'b2b-dashboard',
                [$this, 'dashboard_page'],
                'dashicons-store',
                30
            );

            // Dashboard is the main page, so we don't need a duplicate submenu
            // The main menu page will show the dashboard content

            add_submenu_page(
                'b2b-dashboard',
                __('User Management', 'b2b-commerce'),
                __('User Management', 'b2b-commerce'),
                'manage_options',
                'b2b-users',
                [$this, 'user_management_page']
            );

            add_submenu_page(
                'b2b-dashboard',
                __('Add B2B User', 'b2b-commerce'),
                __('Add B2B User', 'b2b-commerce'),
                'manage_options',
                'b2b-add-user',
                [$this, 'add_b2b_user_page']
            );

            add_submenu_page(
                'b2b-dashboard',
                __('Pricing Rules', 'b2b-commerce'),
                __('Pricing Rules', 'b2b-commerce'),
                'manage_options',
                'b2b-pricing',
                [$this, 'pricing_page']
            );

            add_submenu_page(
                'b2b-dashboard',
                __('Order Management', 'b2b-commerce'),
                __('Order Management', 'b2b-commerce'),
                'manage_options',
                'b2b-orders',
                [$this, 'order_management_page']
            );

            // Quotes Management
            add_submenu_page(
                'b2b-dashboard',
                __('Quotes (Pro)', 'b2b-commerce'),
                __('Quotes (Pro)', 'b2b-commerce'),
                'manage_options',
                'b2b-quotes-upgrade',
                [$this, 'quotes_upgrade_page']
            );

            // Product Inquiries Management
            add_submenu_page(
                'b2b-dashboard',
                __('Product Inquiries (Pro)', 'b2b-commerce'),
                __('Product Inquiries (Pro)', 'b2b-commerce'),
                'manage_options',
                'b2b-inquiries-upgrade',
                [$this, 'inquiries_upgrade_page']
            );

            add_submenu_page(
                'b2b-dashboard',
                __('Settings', 'b2b-commerce'),
                __('Settings', 'b2b-commerce'),
                'manage_options',
                'b2b-settings',
                [$this, 'settings_page']
            );

            // Catalog Mode
            add_submenu_page(
                'b2b-dashboard',
                __('Catalog Mode', 'b2b-commerce'),
                __('Catalog Mode', 'b2b-commerce'),
                'manage_options',
                'b2b-catalog',
                [$this, 'catalog_mode_page']
            );

            // Checkout Controls
            add_submenu_page(
                'b2b-dashboard',
                __('Checkout Controls', 'b2b-commerce'),
                __('Checkout Controls', 'b2b-commerce'),
                'manage_options',
                'b2b-checkout-controls',
                [$this, 'checkout_controls_page']
            );

            // VAT Settings
            add_submenu_page(
                'b2b-dashboard',
                __('Email Templates', 'b2b-commerce'),
                __('Email Templates', 'b2b-commerce'),
                'manage_options',
                'b2b-emails',
                [$this, 'email_templates_page']
            );

            add_submenu_page(
                'b2b-dashboard',
                __('Analytics', 'b2b-commerce'),
                __('Analytics', 'b2b-commerce'),
                'manage_options',
                'b2b-analytics',
                [$this, 'analytics_page']
            );

            add_submenu_page(
                'b2b-dashboard',
                __('Import/Export', 'b2b-commerce'),
                __('Import/Export', 'b2b-commerce'),
                'manage_options',
                'b2b-import-export',
                [$this, 'import_export_page']
            );

            // System Test - Essential for CodeCanyon support
            add_submenu_page(
                'b2b-dashboard',
                __('System Test', 'b2b-commerce'),
                __('System Test', 'b2b-commerce'),
                'manage_options',
                'b2b-test',
                [$this, 'test_page']
            );
        }

        // Render the admin wrapper with unified navigation
        public function render_admin_wrapper($current_page, $content)
        {
            echo '<div class="b2b-admin-wrapper">';

            // Unified Navigation Header
            echo '<div class="b2b-unified-nav">';
            echo '<div class="b2b-nav-header">';
            echo '<div class="b2b-nav-brand">';
            echo '<span class="dashicons dashicons-store" style="color: #2196f3; font-size: 1.5em; margin-right: 10px;"></span>';
            echo '<div>';
            echo '<h2 style="margin: 0; color: #23272f; font-size: 1.3em;">' . __('B2B Commerce', 'b2b-commerce') . '</h2>';
            echo '<small style="color: #666; font-size: 0.9em;">' . __('Premium B2B & Wholesale Solution', 'b2b-commerce') . '</small>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Unified Navigation Menu
            echo '<div class="b2b-nav-menu">';

            // Get notification counts
            $quotes            = get_option('b2b_quote_requests', []);
            $inquiries         = get_option('b2b_product_inquiries', []);
            $pending_quotes    = 0;
            $pending_inquiries = 0;

            foreach ($quotes as $quote) {
                if (($quote['status'] ?? 'pending') === 'pending') {
                    $pending_quotes++;
                }
            }

            foreach ($inquiries as $inquiry) {
                if (($inquiry['status'] ?? 'pending') === 'pending') {
                    $pending_inquiries++;
                }
            }

            // Keep this list in sync with the WP admin submenu for consistency
            $menu_items = [
                'b2b-dashboard'         => [__('Dashboard', 'b2b-commerce'), 'dashicons-chart-area', 0],
                'b2b-users'             => [__('User Management', 'b2b-commerce'), 'dashicons-groups', 0],
                'b2b-add-user'          => [__('Add B2B User', 'b2b-commerce'), 'dashicons-plus', 0],
                'b2b-pricing'           => [__('Pricing Rules', 'b2b-commerce'), 'dashicons-tag', 0],
                'b2b-orders'            => [__('Order Management', 'b2b-commerce'), 'dashicons-cart', 0],
                'b2b-quotes-upgrade'    => [__('Quotes (Pro)', 'b2b-commerce'), 'dashicons-email-alt', 0],
                'b2b-inquiries-upgrade' => [__('Product Inquiries (Pro)', 'b2b-commerce'), 'dashicons-format-chat', 0],
                'b2b-settings'          => [__('Settings', 'b2b-commerce'), 'dashicons-admin-generic', 0],
                'b2b-catalog'           => [__('Catalog Mode', 'b2b-commerce'), 'dashicons-visibility', 0],
                'b2b-checkout-controls' => [__('Checkout Controls', 'b2b-commerce'), 'dashicons-admin-settings', 0],
                'b2b-emails'            => [__('Email Templates', 'b2b-commerce'), 'dashicons-email', 0],
                'b2b-analytics'         => [__('Analytics', 'b2b-commerce'), 'dashicons-chart-line', 0],
                'b2b-import-export'     => [__('Import/Export', 'b2b-commerce'), 'dashicons-upload', 0],
                'b2b-test'              => [__('System Test', 'b2b-commerce'), 'dashicons-admin-tools', 0],
            ];

            foreach ($menu_items as $page => $item) {
                $is_active   = ($current_page === $page) ? 'active' : '';
                $icon        = $item[1];
                $title       = $item[0];
                $badge_count = $item[2];

                echo '<a href="' . esc_url(admin_url('admin.php?page=' . $page)) . '" class="b2b-nav-item ' . $is_active . '">';
                echo '<span class="dashicons ' . $icon . '"></span>';
                echo '<span>' . $title . '</span>';
                echo '</a>';
            }
            echo '</div>';
            echo '</div>';

            // Main Content Area
            echo '<div class="b2b-admin-content">';
            echo $content;
            echo '</div>';

            echo '</div>';
        }

        // Dashboard page
        public function dashboard_page()
        {
            // Get comprehensive statistics
            $total_users   = count(get_users(['role__in' => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']]));
            $pending_users = count(get_users([
                'role__in'   => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'],
                'meta_key'   => 'b2b_approval_status',
                'meta_value' => 'pending',
            ]));
            $approved_users = count(get_users([
                'role__in'   => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'],
                'meta_key'   => 'b2b_approval_status',
                'meta_value' => 'approved',
            ]));
            $rejected_users = count(get_users([
                'role__in'   => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'],
                'meta_key'   => 'b2b_approval_status',
                'meta_value' => 'rejected',
            ]));

            // Get WooCommerce statistics
            $total_revenue   = 0;
            $total_orders    = 0;
            $recent_orders   = [];
            $monthly_revenue = 0;

            if (class_exists('WooCommerce') && function_exists('wc_get_orders')) {
                try {
                    $recent_orders = wc_get_orders(['limit' => 10, 'orderby' => 'date', 'order' => 'DESC']);
                    $all_orders    = wc_get_orders(['limit' => -1, 'status' => 'completed']);
                    $total_orders  = count($all_orders);

                    foreach ($all_orders as $order) {
                        $total_revenue += $order->get_total();
                    }

                    // Get monthly revenue
                    $current_month  = date('Y-m');
                    $monthly_orders = wc_get_orders([
                        'limit'        => -1,
                        'status'       => 'completed',
                        'date_created' => '>=' . $current_month . '-01',
                    ]);

                    foreach ($monthly_orders as $order) {
                        $monthly_revenue += $order->get_total();
                    }
                } catch (Exception $e) {
                    // Log error but don't break the dashboard
                    error_log('B2B Commerce WooCommerce Error: ' . $e->getMessage());
                }
            }

            // Get pricing rules count
            global $wpdb;
            $table               = $wpdb->prefix . 'b2b_pricing_rules';
            $pricing_rules_count = 0;
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                $pricing_rules_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            }

            // Get user role breakdown
            $role_breakdown = [];
            $roles          = ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'];
            foreach ($roles as $role) {
                $role_breakdown[$role] = count(get_users(['role' => $role]));
            }

            // Get quotes and inquiries counts
            $quotes            = get_option('b2b_quote_requests', []);
            $inquiries         = get_option('b2b_product_inquiries', []);
            $pending_quotes    = 0;
            $pending_inquiries = 0;

            foreach ($quotes as $quote) {
                if (($quote['status'] ?? 'pending') === 'pending') {
                    $pending_quotes++;
                }
            }

            foreach ($inquiries as $inquiry) {
                if (($inquiry['status'] ?? 'pending') === 'pending') {
                    $pending_inquiries++;
                }
            }

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-chart-area"></span>' . __('B2B Commerce Dashboard', 'b2b-commerce') . '</h1>
    <p>' . __('Welcome to B2B Commerce. Monitor your business performance and manage your B2B operations.', 'b2b-commerce') . '</p>
</div>

<!-- Main Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Total B2B Users', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 2em; font-weight: bold; color: #2196f3;">' . $total_users . '</p>
                <small style="color: #666;">' . __('Active B2B customers', 'b2b-commerce') . '</small>
            </div>
            <span class="dashicons dashicons-groups" style="font-size: 2.5em; color: #2196f3;"></span>
        </div>
    </div>

    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Pending Approvals', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 2em; font-weight: bold; color: #ff9800;">' . $pending_users . '</p>
                <small style="color: #666;">' . __('Awaiting approval', 'b2b-commerce') . '</small>
            </div>
            <span class="dashicons dashicons-clock" style="font-size: 2.5em; color: #ff9800;"></span>
        </div>
    </div>

    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Total Revenue', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 2em; font-weight: bold; color: #9c27b0;">$' . number_format($total_revenue, 2) . '</p>
                <small style="color: #666;">' . __('All time revenue', 'b2b-commerce') . '</small>
            </div>
            <span class="dashicons dashicons-chart-line" style="font-size: 2.5em; color: #9c27b0;"></span>
        </div>
    </div>

    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Total Orders', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 2em; font-weight: bold; color: #4caf50;">' . $total_orders . '</p>
                <small style="color: #666;">' . __('Completed orders', 'b2b-commerce') . '</small>
            </div>
            <span class="dashicons dashicons-cart" style="font-size: 2.5em; color: #4caf50;"></span>
        </div>
    </div>
</div>

<!-- Secondary Statistics Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Approved Users', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 1.5em; font-weight: bold; color: #4caf50;">' . $approved_users . '</p>
            </div>
            <span class="dashicons dashicons-yes-alt" style="font-size: 2em; color: #4caf50;"></span>
        </div>
    </div>

    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Rejected Users', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 1.5em; font-weight: bold; color: #f44336;">' . $rejected_users . '</p>
            </div>
            <span class="dashicons dashicons-no-alt" style="font-size: 2em; color: #f44336;"></span>
        </div>
    </div>

    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Monthly Revenue', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 1.5em; font-weight: bold; color: #ff9800;">$' . number_format($monthly_revenue, 2) . '</p>
            </div>
            <span class="dashicons dashicons-calendar-alt" style="font-size: 2em; color: #ff9800;"></span>
        </div>
    </div>

    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Pricing Rules', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 1.5em; font-weight: bold; color: #2196f3;">' . $pricing_rules_count . '</p>
            </div>
            <span class="dashicons dashicons-tag" style="font-size: 2em; color: #2196f3;"></span>
        </div>
    </div>

    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Pending Quotes', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 1.5em; font-weight: bold; color: #ff9800;">' . $pending_quotes . '</p>
            </div>
            <span class="dashicons dashicons-email-alt" style="font-size: 2em; color: #ff9800;"></span>
        </div>
    </div>

    <div class="b2b-admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; color: #666; font-size: 1.2em;">' . __('Pending Inquiries', 'b2b-commerce') . '</h3>
                <p style="margin: 15px 0 5px 0; font-size: 1.5em; font-weight: bold; color: #e91e63;">' . $pending_inquiries . '</p>
            </div>
            <span class="dashicons dashicons-format-chat" style="font-size: 2em; color: #e91e63;"></span>
        </div>
    </div>
</div>


<!-- User Role Breakdown -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-groups"></span>' . __('User Role Breakdown', 'b2b-commerce') . '</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="text-align: center; padding: 24px; border: 1px solid rgb(0 0 0 / .06) !important; border-radius: 6px;">
            <h3 style="margin: 0 0 15px 0; color: #2196f3;">' . __('B2B Customers', 'b2b-commerce') . '</h3>
            <p style="margin: 0; font-size: 1.5em; font-weight: bold;">' . $role_breakdown['b2b_customer'] . '</p>
        </div>
        <div style="text-align: center; padding: 24px; border: 1px solid rgb(0 0 0 / .06) !important; border-radius: 6px;">
            <h3 style="margin: 0 0 15px 0; color: #4caf50;">' . __('Wholesale', 'b2b-commerce') . '</h3>
            <p style="margin: 0; font-size: 1.5em; font-weight: bold;">' . $role_breakdown['wholesale_customer'] . '</p>
        </div>
        <div style="text-align: center; padding: 24px; border: 1px solid rgb(0 0 0 / .06) !important; border-radius: 6px;">
            <h3 style="margin: 0 0 15px 0; color: #ff9800;">' . __('Distributors', 'b2b-commerce') . '</h3>
            <p style="margin: 0; font-size: 1.5em; font-weight: bold;">' . $role_breakdown['distributor'] . '</p>
        </div>
        <div style="text-align: center; padding: 24px; border: 1px solid rgb(0 0 0 / .06) !important; border-radius: 6px;">
            <h3 style="margin: 0 0 15px 0; color: #9c27b0;">' . __('Retailers', 'b2b-commerce') . '</h3>
            <p style="margin: 0; font-size: 1.5em; font-weight: bold;">' . $role_breakdown['retailer'] . '</p>
        </div>
    </div>
</div>';

            // Add recent orders if available
            if (! empty($recent_orders)) {
                $content .= '
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-list-view"></span>' . __('Recent Orders', 'b2b-commerce') . '</div>
    <table class="b2b-admin-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>';
                foreach ($recent_orders as $order) {
                    $status_class = $order->get_status() === 'completed' ? 'success' : ($order->get_status() === 'processing' ? 'warning' : 'danger');
                    $content .= '
            <tr>
                <td><strong><a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '">#' . $order->get_id() . '</a></strong></td>
                <td>' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '</td>
                <td>' . esc_html($order->get_date_created()->date('Y-m-d H:i')) . '</td>
                <td><strong>' . esc_html(get_woocommerce_currency_symbol() . number_format($order->get_total(), 2)) . '</strong></td>
                <td><span class="b2b-badge b2b-badge-' . $status_class . '">' . esc_html(wc_get_order_status_name($order->get_status())) . '</span></td>
                <td>
                    <a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '" class="b2b-admin-btn" style="padding: 4px 8px; font-size: 0.8em;"><span class="icon dashicons dashicons-edit"></span>' . __('View', 'b2b-commerce') . '</a>
                </td>
            </tr>';
                }
                $content .= '
        </tbody>
    </table>
    <div style="margin-top: 15px; text-align: center;">
        <a href="' . admin_url('edit.php?post_type=shop_order') . '" class="b2b-admin-btn">' . __('View All Orders', 'b2b-commerce') . '</a>
    </div>
</div>';
            } else {
                $content .= '
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-list-view"></span>' . __('Recent Orders', 'b2b-commerce') . '</div>
    <div style="text-align: center; padding: 40px 20px; color: #666;">
        <div class="b2b-empty-state">
            <span class="dashicons dashicons-cart" style="font-size: 3em; color: #ddd;"></span>
            <p>' . __('No orders found', 'b2b-commerce') . '</p>
            <small>' . __('Orders will appear here once customers start placing orders', 'b2b-commerce') . '</small>
        </div>
    </div>
</div>';
            }


            $this->render_admin_wrapper('b2b-dashboard', $content);
        }

        // User management page
        public function user_management_page()
        {
            $role     = $_GET['role'] ?? '';
            $approval = $_GET['approval'] ?? '';
            $args     = ['role__in' => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']];
            if ($role) {
                $args['role'] = $role;
            }

            if ($approval) {
                $args['meta_key'] = 'b2b_approval_status';
            }

            if ($approval) {
                $args['meta_value'] = $approval;
            }

            $users = get_users($args);

            // Show success messages
            $success_message = '';
            if (isset($_GET['approved']) && $_GET['approved'] == '1') {
                $success_message = '<div class="b2b-admin-card" style="color: #4caf50; border-color: #4caf50;"><span class="icon dashicons dashicons-yes-alt"></span> ' . __('User approved successfully!', 'b2b-commerce') . '</div>';
            }
            if (isset($_GET['rejected']) && $_GET['rejected'] == '1') {
                $success_message = '<div class="b2b-admin-card" style="color: #f44336; border-color: #f44336;"><span class="icon dashicons dashicons-no-alt"></span> ' . __('User rejected successfully!', 'b2b-commerce') . '</div>';
            }

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-groups"></span>' . __('User Management', 'b2b-commerce') . '</h1>
    <p>' . __('Manage B2B users, approve applications, and control user access.', 'b2b-commerce') . '</p>
</div>';

            if ($success_message) {
                $content .= $success_message;
            }
            $content .= '
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-filter"></span>' . __('Filter Users', 'b2b-commerce') . '</div>
    <form method="get" class="b2b-admin-form" style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="b2b-users">
        <div class="b2b-admin-form-group">
            <label for="role">' . __('Role', 'b2b-commerce') . '</label>
            <select name="role" id="role">
                <option value="">' . __('All Roles', 'b2b-commerce') . '</option>
                <option value="b2b_customer"' . selected($role, 'b2b_customer', false) . '>' . __('B2B Customer', 'b2b-commerce') . '</option>
                <option value="wholesale_customer"' . selected($role, 'wholesale_customer', false) . '>' . __('Wholesale', 'b2b-commerce') . '</option>
                <option value="distributor"' . selected($role, 'distributor', false) . '>' . __('Distributor', 'b2b-commerce') . '</option>
                <option value="retailer"' . selected($role, 'retailer', false) . '>' . __('Retailer', 'b2b-commerce') . '</option>
            </select>
        </div>
        <div class="b2b-admin-form-group">
            <label for="approval">' . __('Approval Status', 'b2b-commerce') . '</label>
            <select name="approval" id="approval">
                <option value="">' . __('All Status', 'b2b-commerce') . '</option>
                <option value="pending"' . selected($approval, 'pending', false) . '>' . __('Pending', 'b2b-commerce') . '</option>
                <option value="approved"' . selected($approval, 'approved', false) . '>' . __('Approved', 'b2b-commerce') . '</option>
                <option value="rejected"' . selected($approval, 'rejected', false) . '>' . __('Rejected', 'b2b-commerce') . '</option>
            </select>
        </div>
        <div class="b2b-admin-form-group">
            <button type="submit" class="b2b-admin-btn"><span class="icon dashicons dashicons-search"></span>' . __('Filter', 'b2b-commerce') . '</button>
        </div>
    </form>
</div>
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-groups"></span>' . __('B2B Users', 'b2b-commerce') . ' (' . count($users) . ')</div>

    <!-- Add New User Button -->
    <div style="margin-bottom: 20px; padding: 24px; background: #f8f9fa; border-radius: 8px;">
        <a href="' . admin_url('admin.php?page=b2b-add-user') . '" class="b2b-admin-btn b2b-admin-btn-success" style="text-decoration: none;">
            <span class="icon dashicons dashicons-plus"></span>' . __('Add New B2B User', 'b2b-commerce') . '
        </a>
    </div>


    <table class="b2b-admin-table">
        <thead>
            <tr>
                <th>' . __('User', 'b2b-commerce') . '</th>
                <th>' . __('Email', 'b2b-commerce') . '</th>
                <th>' . __('Role', 'b2b-commerce') . '</th>
                <th>' . __('Company', 'b2b-commerce') . '</th>
                <th>' . __('Approval Status', 'b2b-commerce') . '</th>
                <th>' . __('Actions', 'b2b-commerce') . '</th>
            </tr>
        </thead>
        <tbody>';
            foreach ($users as $user) {
                $approval_status = get_user_meta($user->ID, 'b2b_approval_status', true) ?: 'pending';
                $badge_class     = $approval_status === 'approved' ? 'success' : ($approval_status === 'rejected' ? 'danger' : 'warning');
                $content .= '
            <tr>
                <td><strong>' . esc_html($user->user_login) . '</strong></td>
                <td>' . esc_html($user->user_email) . '</td>
                <td>' . esc_html(implode(', ', $user->roles)) . '</td>
                <td>' . esc_html(get_user_meta($user->ID, 'company_name', true)) . '</td>
                <td><span class="b2b-badge b2b-badge-' . $badge_class . '">' . esc_html($approval_status) . '</span></td>
                <td>
                    <a href="' . admin_url('user-edit.php?user_id=' . $user->ID) . '" class="b2b-admin-btn" style="padding: 6px 12px; font-size: 0.9em;"><span class="icon dashicons dashicons-edit"></span>' . __('Edit', 'b2b-commerce') . '</a>';

                // Add approval/rejection buttons for pending users
                if ($approval_status === 'pending') {
                    $content .= '
                    <a href="' . admin_url('admin-post.php?action=b2b_approve_user&user_id=' . $user->ID . '&_wpnonce=' . wp_create_nonce('b2b_approve_user_' . $user->ID)) . '" class="b2b-admin-btn b2b-admin-btn-success" style="padding: 6px 12px; font-size: 0.9em; margin-left: 5px;"><span class="icon dashicons dashicons-yes-alt"></span>' . __('Approve', 'b2b-commerce') . '</a>
                    <a href="' . admin_url('admin-post.php?action=b2b_reject_user&user_id=' . $user->ID . '&_wpnonce=' . wp_create_nonce('b2b_reject_user_' . $user->ID)) . '" class="b2b-admin-btn b2b-admin-btn-danger" style="padding: 6px 12px; font-size: 0.9em; margin-left: 5px;"><span class="icon dashicons dashicons-no-alt"></span>' . __('Reject', 'b2b-commerce') . '</a>';
                }

                $content .= '
                </td>
            </tr>';
            }
            $content .= '
        </tbody>
    </table>
</div>';
            $this->render_admin_wrapper('b2b-users', $content);
        }

        // Simple wrappers to reuse main settings sections (for sidebar entries)
        public function catalog_mode_page()
        {
            if (! current_user_can('manage_options')) {
                return;
            }

            $opts = get_option('b2b_catalog_mode', []);
            if (isset($_POST['b2b_catalog_nonce']) && wp_verify_nonce($_POST['b2b_catalog_nonce'], 'b2b_catalog_mode')) {
                $opts = [
                    'hide_prices_guests'  => isset($_POST['b2b_catalog_mode']['hide_prices_guests']) ? 1 : 0,
                    'disable_add_to_cart' => isset($_POST['b2b_catalog_mode']['disable_add_to_cart']) ? 1 : 0,
                    'force_quote_mode'    => isset($_POST['b2b_catalog_mode']['force_quote_mode']) ? 1 : 0,
                ];
                update_option('b2b_catalog_mode', $opts);
                echo '<div class="b2b-admin-card" style="color:#2196f3;">' . __('Catalog mode saved.', 'b2b-commerce') . '</div>';
            }
            $content = '<div class="b2b-admin-header"><h1><span class="icon dashicons dashicons-visibility"></span>' . __('Catalog Mode', 'b2b-commerce') . '</h1><p>' . __('Control price visibility and purchasing.', 'b2b-commerce') . '</p></div>';
            $content .= '<div class="b2b-admin-card"><form method="post" class="b2b-admin-form">' . wp_nonce_field('b2b_catalog_mode', 'b2b_catalog_nonce', true, false);

            // Row 1
            $content .= '<div style="display:flex; align-items:center; justify-content:space-between; padding:15px; background:#f8f9fa; border-radius:6px; margin-bottom:12px;">';
            $content .= '<div><label style="margin:0; font-weight:600; color:#23272f;">' . __('Hide prices for guests', 'b2b-commerce') . '</label><p style="margin:5px 0 0 0; color:#666; font-size:0.9em;">' . __('Guests will see "Login to see prices" instead of price.', 'b2b-commerce') . '</p></div>';
            $content .= '<label class="b2b-admin-toggle"><input type="checkbox" name="b2b_catalog_mode[hide_prices_guests]" value="1" ' . checked($opts['hide_prices_guests'] ?? '', 1, false) . '><span class="b2b-admin-toggle-slider"></span></label>';
            $content .= '</div>';

            // Row 2
            $content .= '<div style="display:flex; align-items:center; justify-content:space-between; padding:15px; background:#f8f9fa; border-radius:6px; margin-bottom:12px;">';
            $content .= '<div><label style="margin:0; font-weight:600; color:#23272f;">' . __('Disable add to cart for guests', 'b2b-commerce') . '</label><p style="margin:5px 0 0 0; color:#666; font-size:0.9em;">' . __('Prevents guests from purchasing until they log in.', 'b2b-commerce') . '</p></div>';
            $content .= '<label class="b2b-admin-toggle"><input type="checkbox" name="b2b_catalog_mode[disable_add_to_cart]" value="1" ' . checked($opts['disable_add_to_cart'] ?? '', 1, false) . '><span class="b2b-admin-toggle-slider"></span></label>';
            $content .= '</div>';

            // Row 3
            $content .= '<div style="display:flex; align-items:center; justify-content:space-between; padding:15px; background:#f8f9fa; border-radius:6px;">';
            $content .= '<div><label style="margin:0; font-weight:600; color:#23272f;">' . __('Force Quote Mode (site‑wide)', 'b2b-commerce') . '</label><p style="margin:5px 0 0 0; color:#666; font-size:0.9em;">' . __('Disables add to cart for everyone; customers can request quotes instead.', 'b2b-commerce') . '</p></div>';
            $content .= '<label class="b2b-admin-toggle"><input type="checkbox" name="b2b_catalog_mode[force_quote_mode]" value="1" ' . checked($opts['force_quote_mode'] ?? '', 1, false) . '><span class="b2b-admin-toggle-slider"></span></label>';
            $content .= '</div>';

            $content .= '<div style="margin-top:15px;"><button class="b2b-admin-btn" type="submit"><span class="icon dashicons dashicons-saved"></span>' . __('Save', 'b2b-commerce') . '</button></div></form></div>';
            $this->render_admin_wrapper('b2b-catalog', $content);
        }

        public function checkout_controls_page()
        {
            if (! current_user_can('manage_options')) {
                return;
            }

            $role_payment      = get_option('b2b_role_payment_methods', []);
            $role_shipping     = get_option('b2b_role_shipping_methods', []);
            $quantity_settings = get_option('b2b_quantity_settings', ['enforce_min_qty' => 1]);

            if (isset($_POST['b2b_checkout_controls_nonce']) && wp_verify_nonce($_POST['b2b_checkout_controls_nonce'], 'b2b_checkout_controls')) {
                $roles = ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'];
                $rp    = [];
                $rs    = [];
                foreach ($roles as $role) {
                    $rp[$role] = isset($_POST['b2b_role_payment_methods'][$role]) ? array_map('sanitize_text_field', (array) $_POST['b2b_role_payment_methods'][$role]) : [];
                    $rs[$role] = isset($_POST['b2b_role_shipping_methods'][$role]) ? array_map('sanitize_text_field', (array) $_POST['b2b_role_shipping_methods'][$role]) : [];
                }
                update_option('b2b_role_payment_methods', $rp);
                update_option('b2b_role_shipping_methods', $rs);

                // Save quantity settings
                $quantity_settings = [
                    'enforce_min_qty'  => isset($_POST['b2b_quantity_settings']['enforce_min_qty']) ? 1 : 0,
                    'min_qty_behavior' => sanitize_text_field($_POST['b2b_quantity_settings']['min_qty_behavior'] ?? 'warning'),
                ];
                update_option('b2b_quantity_settings', $quantity_settings);

                $role_payment  = $rp;
                $role_shipping = $rs;
                echo '<div class="b2b-admin-card" style="color:#2196f3;">' . __('Checkout controls saved.', 'b2b-commerce') . '</div>';
            }
            $content = '<div class="b2b-admin-header"><h1><span class="icon dashicons dashicons-admin-settings"></span>' . __('Checkout Controls', 'b2b-commerce') . '</h1><p>' . __('Restrict checkout methods per role.', 'b2b-commerce') . '</p></div>';
            $content .= '<div class="b2b-admin-card" style="max-width: 1100px;"><form method="post" class="b2b-admin-form">' . wp_nonce_field('b2b_checkout_controls', 'b2b_checkout_controls_nonce', true, false);
            if (class_exists('WC_Payment_Gateways')) {
                // In admin, get_available_payment_gateways() can be empty. Collect all registered gateways.
                $gateways = [];
                if (function_exists('WC') && WC()->payment_gateways()) {
                    $gateways = WC()->payment_gateways->payment_gateways(); // all gateways
                }
                if (empty($gateways)) {
                    $gateways = \WC_Payment_Gateways::instance()->payment_gateways();
                }

                $gateway_titles = [];
                foreach ($gateways as $gid => $gateway) {$gateway_titles[$gid] = $gateway->get_title();}
                $roles = ['b2b_customer' => __('B2B Customer', 'b2b-commerce'), 'wholesale_customer' => __('Wholesale', 'b2b-commerce'), 'distributor' => __('Distributor', 'b2b-commerce'), 'retailer' => __('Retailer', 'b2b-commerce')];
                $content .= '<h3 style="margin-top:0;">' . __('Payment Gateways', 'b2b-commerce') . '</h3>';
                if (empty($gateway_titles)) {
                    $content .= '<p style="color:#666;">' . __('No gateways found. Enable them in', 'b2b-commerce') . ' <a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('WooCommerce → Payments', 'b2b-commerce') . '</a>.</p>';
                } else {
                    $content .= '<table class="b2b-admin-table b2b-matrix-table"><thead><tr><th>' . __('Role', 'b2b-commerce') . '</th>';
                    foreach ($gateway_titles as $gid => $title) {
                        $content .= '<th>' . esc_html($title) . '</th>';
                    }
                    $content .= '</tr></thead><tbody>';
                    foreach ($roles as $role_key => $role_label) {
                        $content .= '<tr><td><strong>' . esc_html($role_label) . '</strong></td>';
                        foreach ($gateway_titles as $gid => $title) {
                            $checked = in_array($gid, $role_payment[$role_key] ?? []) ? 'checked' : '';
                            $content .= '<td style="text-align:center;">'
                            . '<input type="checkbox" name="b2b_role_payment_methods[' . esc_attr($role_key) . '][]" value="' . esc_attr($gid) . '" ' . $checked . '>'
                                . '</td>';
                        }
                        $content .= '</tr>';
                    }
                    $content .= '</tbody></table>';
                }
            }

            if (class_exists('WC_Shipping')) {
                $shipping_methods = \WC_Shipping::instance()->get_shipping_methods();
                // Map to base IDs and titles (flat_rate, free_shipping, local_pickup)
                $method_titles = [];
                foreach ($shipping_methods as $mid => $method) {$method_titles[$method->id] = $method->get_method_title();}
                // Keep only common core methods in predictable order if present
                $order   = ['flat_rate', 'free_shipping', 'local_pickup'];
                $ordered = [];
                foreach ($order as $id) {if (isset($method_titles[$id])) {$ordered[$id] = $method_titles[$id];}}
                // append any others
                foreach ($method_titles as $id => $title) {if (! isset($ordered[$id])) {$ordered[$id] = $title;}}
                $roles = ['b2b_customer' => __('B2B Customer', 'b2b-commerce'), 'wholesale_customer' => __('Wholesale', 'b2b-commerce'), 'distributor' => __('Distributor', 'b2b-commerce'), 'retailer' => __('Retailer', 'b2b-commerce')];
                $content .= '<h3 style="margin-top:24px;">' . __('Shipping Methods', 'b2b-commerce') . '</h3>';
                if (empty($ordered)) {
                    $content .= '<p style="color:#666;">' . __('No shipping methods found. Configure them in', 'b2b-commerce') . ' <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping') . '">' . __('WooCommerce → Shipping', 'b2b-commerce') . '</a>.</p>';
                } else {
                    $content .= '<table class="b2b-admin-table b2b-matrix-table"><thead><tr><th>' . __('Role', 'b2b-commerce') . '</th>';
                    foreach ($ordered as $id => $title) {$content .= '<th>' . esc_html($title) . '</th>';}
                    $content .= '</tr></thead><tbody>';
                    foreach ($roles as $role_key => $role_label) {
                        $content .= '<tr><td><strong>' . esc_html($role_label) . '</strong></td>';
                        foreach ($ordered as $id => $title) {
                            $checked = in_array($id, $role_shipping[$role_key] ?? []) ? 'checked' : '';
                            $content .= '<td style="text-align:center;">'
                            . '<input type="checkbox" name="b2b_role_shipping_methods[' . esc_attr($role_key) . '][]" value="' . esc_attr($id) . '" ' . $checked . '>'
                                . '</td>';
                        }
                        $content .= '</tr>';
                    }
                    $content .= '</tbody></table>';
                }
            }

            // Quantity Settings Section
            $content .= '<h3 style="margin-top:30px; border-top:1px solid #e0e0e0; padding-top:20px;">' . __('Minimum Quantity Settings', 'b2b-commerce') . '</h3>';
            $content .= '<p style="color:#666; margin-bottom:20px;">' . __('Control how minimum quantity requirements are enforced during checkout.', 'b2b-commerce') . '</p>';

            $content .= '<div style="display:flex; align-items:center; justify-content:space-between; padding:15px; background:#f8f9fa; border-radius:8px; margin-bottom:12px;">';
            $content .= '<div><label style="margin:0; font-weight:600; color:#23272f;">' . __('Enforce Minimum Quantity', 'b2b-commerce') . '</label><p style="margin:5px 0 0 0; color:#666; font-size:0.9em;">' . __('When enabled, customers must meet minimum quantity requirements to checkout.', 'b2b-commerce') . '</p></div>';
            $content .= '<label class="b2b-admin-toggle"><input type="checkbox" name="b2b_quantity_settings[enforce_min_qty]" value="1" ' . checked($quantity_settings['enforce_min_qty'] ?? 1, 1, false) . '><span class="b2b-admin-toggle-slider"></span></label>';
            $content .= '</div>';

            $content .= '<div style="display:flex; align-items:center; justify-content:space-between; padding:15px; background:#f8f9fa; border-radius:8px; margin-bottom:12px;">';
            $content .= '<div><label style="margin:0; font-weight:600; color:#23272f;">' . __('Behavior when minimum not met', 'b2b-commerce') . '</label><p style="margin:5px 0 0 0; color:#666; font-size:0.9em;">' . __('Choose how to handle customers who don\'t meet minimum quantities.', 'b2b-commerce') . '</p></div>';
            $content .= '<select name="b2b_quantity_settings[min_qty_behavior]" style="padding:8px; border:1px solid #ddd; border-radius:4px;">';
            $content .= '<option value="error" ' . selected($quantity_settings['min_qty_behavior'] ?? 'warning', 'error', false) . '>' . __('Block checkout (Error)', 'b2b-commerce') . '</option>';
            $content .= '<option value="warning" ' . selected($quantity_settings['min_qty_behavior'] ?? 'warning', 'warning', false) . '>' . __('Allow with warning', 'b2b-commerce') . '</option>';
            $content .= '<option value="ignore" ' . selected($quantity_settings['min_qty_behavior'] ?? 'warning', 'ignore', false) . '>' . __('Ignore completely', 'b2b-commerce') . '</option>';
            $content .= '</select>';
            $content .= '</div>';

            $content .= '<div style="margin-top:18px; display:flex; gap:10px;">';
            $content .= '<button class="b2b-admin-btn" type="submit"><span class="icon dashicons dashicons-saved"></span>' . __('Save', 'b2b-commerce') . '</button>';
            $content .= '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '" class="b2b-admin-btn" style="background:#eef3fb;color:#1976d2;box-shadow:none;">' . __('Open WooCommerce Payments', 'b2b-commerce') . '</a>';
            $content .= '</div></form></div>';
            $this->render_admin_wrapper('b2b-checkout-controls', $content);
        }

        // Add B2B User page
        public function add_b2b_user_page()
        {
            $success_message = '';
            $error_message   = '';

            // Handle form submission
            if (isset($_POST['b2b_add_user_nonce']) && wp_verify_nonce($_POST['b2b_add_user_nonce'], 'b2b_add_user')) {
                $username = sanitize_user($_POST['username']);
                $email    = sanitize_email($_POST['email']);
                $role     = sanitize_text_field($_POST['role']);
                $company  = sanitize_text_field($_POST['company']);
                $password = $_POST['password'];

                // Validate required fields
                if (empty($username) || empty($email) || empty($role) || empty($password)) {
                    $error_message = '<div class="b2b-admin-card" style="color: #dc3545;"><span class="icon dashicons dashicons-no-alt"></span> ' . __('All fields are required!', 'b2b-commerce') . '</div>';
                } elseif (! is_email($email)) {
                    $error_message = '<div class="b2b-admin-card" style="color: #dc3545;"><span class="icon dashicons dashicons-no-alt"></span> ' . __('Please enter a valid email address!', 'b2b-commerce') . '</div>';
                } elseif (username_exists($username)) {
                    $error_message = '<div class="b2b-admin-card" style="color: #dc3545;"><span class="icon dashicons dashicons-no-alt"></span> ' . __('Username already exists!', 'b2b-commerce') . '</div>';
                } elseif (email_exists($email)) {
                    $error_message = '<div class="b2b-admin-card" style="color: #dc3545;"><span class="icon dashicons dashicons-no-alt"></span> ' . __('Email already exists!', 'b2b-commerce') . '</div>';
                } else {
                    // Create the user
                    $user_id = wp_create_user($username, $password, $email);

                    if (is_wp_error($user_id)) {
                        $error_message = '<div class="b2b-admin-card" style="color: #dc3545;"><span class="icon dashicons dashicons-no-alt"></span> Error creating user: ' . $user_id->get_error_message() . '</div>';
                    } else {
                        // Set the role
                        $user = new \WP_User($user_id);
                        $user->set_role($role);

                        // Add company name
                        update_user_meta($user_id, 'company_name', $company);

                        // Set approval status
                        update_user_meta($user_id, 'b2b_approval_status', 'approved');

                        $success_message = '<div class="b2b-admin-card" style="color: #2196f3;"><span class="icon dashicons dashicons-yes-alt"></span> ' . __('B2B user created successfully!', 'b2b-commerce') . '</div>';
                    }
                }
            }

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-plus"></span>' . __('Add New B2B User', 'b2b-commerce') . '</h1>
    <p>' . __('Create a new B2B user account with specific role and permissions.', 'b2b-commerce') . '</p>
</div>';

            if ($error_message) {
                $content .= $error_message;
            }

            if ($success_message) {
                $content .= $success_message;
            }

            $content .= '
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-plus"></span>' . __('Create B2B User', 'b2b-commerce') . '</div>
    <form method="post" class="b2b-admin-form" id="b2b-add-user-form">
        ' . wp_nonce_field('b2b_add_user', 'b2b_add_user_nonce', true, false) . '

        <div class="b2b-admin-form-group">
            <label for="username">' . __('Username', 'b2b-commerce') . ' *</label>
            <input type="text" id="username" name="username" required>
        </div>

        <div class="b2b-admin-form-group">
            <label for="email">' . __('Email', 'b2b-commerce') . ' *</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="b2b-admin-form-group">
            <label for="password">' . __('Password', 'b2b-commerce') . ' *</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="b2b-admin-form-group">
            <label for="role">' . __('B2B Role', 'b2b-commerce') . ' *</label>
            <select id="role" name="role" required>
                <option value="">' . __('Select Role', 'b2b-commerce') . '</option>
                <option value="b2b_customer">' . __('B2B Customer', 'b2b-commerce') . '</option>
                <option value="wholesale_customer">' . __('Wholesale Customer', 'b2b-commerce') . '</option>
                <option value="distributor">' . __('Distributor', 'b2b-commerce') . '</option>
                <option value="retailer">' . __('Retailer', 'b2b-commerce') . '</option>
            </select>
        </div>

        <div class="b2b-admin-form-group">
            <label for="company">' . __('Company Name', 'b2b-commerce') . '</label>
            <input type="text" id="company" name="company">
        </div>

        <button type="submit" class="b2b-admin-btn"><span class="icon dashicons dashicons-plus"></span>' . __('Create B2B User', 'b2b-commerce') . '</button>
    </form>
</div>';

            $this->render_admin_wrapper('b2b-add-user', $content);
        }

        // Order management page
        public function order_management_page()
        {
            // Get recent orders
            $recent_orders = [];
            if (class_exists('WooCommerce') && function_exists('wc_get_orders')) {
                try {
                    $recent_orders = wc_get_orders(['limit' => 20, 'orderby' => 'date', 'order' => 'DESC']);

                    // Debug: Log the number of orders found
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('B2B Order Management: Found ' . count($recent_orders) . ' orders');
                    }

                    // Fallback: If no orders found, check if there are any orders in the database
                    if (empty($recent_orders)) {
                        global $wpdb;
                        $order_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status != 'trash'");
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('B2B Order Management: Database shows ' . $order_count . ' orders');
                        }
                    }
                } catch (Exception $e) {
                    // Log error but don't break the page
                    error_log('B2B Order Management Error: ' . $e->getMessage());
                }
            }

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-cart"></span>' . __('Order Management', 'b2b-commerce') . '</h1>
    <p>' . __('Monitor and manage B2B orders, track status, and view order details.', 'b2b-commerce') . '</p>
</div>
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-list-view"></span>' . __('Recent Orders', 'b2b-commerce') . ' (' . count($recent_orders) . ')</div>';

            if (! empty($recent_orders)) {
                $content .= '
    <table class="b2b-admin-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>';

                foreach ($recent_orders as $order) {
                    $customer      = $order->get_customer_id() ? get_userdata($order->get_customer_id()) : null;
                    $customer_name = $customer ? $customer->display_name : ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                    if (empty(trim($customer_name))) {
                        $customer_name = $order->get_billing_email();
                    }

                    $status_class = $order->get_status() === 'completed' ? 'success' : ($order->get_status() === 'processing' ? 'warning' : 'danger');

                    $content .= '
            <tr>
                <td><strong><a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '">#' . $order->get_id() . '</a></strong></td>
                <td>' . esc_html($order->get_date_created()->date('Y-m-d H:i')) . '</td>
                <td>' . esc_html($customer_name) . '</td>
                <td><strong>' . esc_html(get_woocommerce_currency_symbol() . number_format($order->get_total(), 2)) . '</strong></td>
                <td><span class="b2b-badge b2b-badge-' . $status_class . '">' . esc_html(wc_get_order_status_name($order->get_status())) . '</span></td>
                <td>
                    <a href="' . admin_url('post.php?post=' . $order->get_id() . '&action=edit') . '" class="b2b-admin-btn" style="padding: 4px 8px; font-size: 0.8em;"><span class="icon dashicons dashicons-edit"></span>' . __('View', 'b2b-commerce') . '</a>
                </td>
            </tr>';
                }

                $content .= '
        </tbody>
    </table>
    <div style="margin-top: 15px; text-align: center;">
        <a href="' . admin_url('edit.php?post_type=shop_order') . '" class="b2b-admin-btn">' . __('View All Orders', 'b2b-commerce') . '</a>
    </div>';
            } else {
                $content .= '
    <table class="b2b-admin-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Date</th>
                <th>Customer</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="3" style="text-align: center; padding: 40px 20px; color: #666;">
                    <div class="b2b-empty-state">
                        <span class="dashicons dashicons-cart"></span>
                        <p>No orders found</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>';
            }

            $content .= '
</div>';
            $this->render_admin_wrapper('b2b-orders', $content);
        }

        // B2B Pricing page
        public function pricing_page()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'b2b_pricing_rules';

            // Get existing rules
            $rules = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY id DESC", $table));

            // Handle form submission
            if (isset($_POST['b2b_pricing_nonce']) && wp_verify_nonce($_POST['b2b_pricing_nonce'], 'b2b_pricing_action')) {
                $this->save_pricing_rule();
            }

            // Show success message if redirected after update
            if (isset($_GET['updated']) && $_GET['updated'] == '1') {
                $content = '<div class="notice notice-success"><p>Pricing rule updated successfully!</p></div>';
            }

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-tag"></span>B2B Pricing Management</h1>
    <p>Set up wholesale pricing for different customer types.</p>
</div>

<!-- Quick Pricing Setup -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-plus"></span>Add Pricing Rule</div>
    <form method="post" class="b2b-admin-form">
        ' . wp_nonce_field('b2b_pricing_action', 'b2b_pricing_nonce', true, false) . '

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="b2b-admin-form-group">
                <label for="role">Customer Type *</label>
                <select name="role" id="role" required>
                    <option value="">Choose customer type</option>
                    <option value="wholesale_customer">Wholesale Customer</option>
                    <option value="distributor">Distributor</option>
                    <option value="retailer">Retailer</option>
                </select>
            </div>

            <div class="b2b-admin-form-group">
                <label for="type">Pricing Type *</label>
                <select name="type" id="type" required>
                    <option value="percentage">Percentage Discount</option>
                    <option value="fixed">Fixed Price</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <div class="b2b-admin-form-group">
                <label for="product_id">Product (Optional)</label>
                <select name="product_id" id="product_id">
                    <option value="0">All Products (Global Rule)</option>';

            // Get all products
            $products = get_posts([
                'post_type'   => 'product',
                'numberposts' => -1,
                'post_status' => 'publish',
            ]);

            foreach ($products as $product) {
                $content .= '<option value="' . $product->ID . '">' . esc_html($product->post_title) . '</option>';
            }

            $content .= '
                </select>
                <small>Leave as "All Products" for global pricing, or select specific product</small>
            </div>

            <div class="b2b-admin-form-group">
                <label for="min_qty">Min Quantity</label>
                <input type="number" name="min_qty" id="min_qty" min="1" value="1" placeholder="1">
                <small>Minimum order quantity required</small>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;">
            <div class="b2b-admin-form-group">
                <label for="price">' . __('Value', 'b2b-commerce') . ' *</label>
                <input type="number" name="price" id="price" step="0.01" required placeholder="' . __('Enter value', 'b2b-commerce') . '">
                <small>' . __('For discount: use negative number (e.g., -15 for 15% off)', 'b2b-commerce') . '</small>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="b2b-admin-btn"><span class="icon dashicons dashicons-saved"></span>' . __('Save Pricing Rule', 'b2b-commerce') . '</button>
        </div>
    </form>
</div>

<!-- Edit Modal -->
<div id="editModal" class="b2b-modal" style="display: none;">
    <div class="b2b-modal-content">
        <div class="b2b-modal-header">
            <h3>' . __('Edit Pricing Rule', 'b2b-commerce') . '</h3>
            <span class="b2b-modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="post" action="">
            <input type="hidden" name="edit_rule_id" id="edit_rule_id" value="">
            ' . wp_nonce_field('b2b_pricing_action', 'b2b_pricing_nonce', true, false) . '

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="b2b-admin-form-group">
                    <label for="edit_role">' . __('Customer Type', 'b2b-commerce') . '</label>
                    <select name="role" id="edit_role" required>
                        <option value="">' . __('Choose customer type', 'b2b-commerce') . '</option>
                        <option value="wholesale_customer">' . __('Wholesale Customer', 'b2b-commerce') . '</option>
                        <option value="distributor">' . __('Distributor', 'b2b-commerce') . '</option>
                        <option value="retailer">' . __('Retailer', 'b2b-commerce') . '</option>
                    </select>
                </div>

                <div class="b2b-admin-form-group">
                    <label for="edit_type">' . __('Discount Type', 'b2b-commerce') . '</label>
                    <select name="type" id="edit_type" required>
                        <option value="percentage">' . __('Percentage Discount', 'b2b-commerce') . '</option>
                        <option value="fixed">' . __('Fixed Price', 'b2b-commerce') . '</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div class="b2b-admin-form-group">
                    <label for="edit_product_id">Product</label>
                    <select name="product_id" id="edit_product_id">
                        <option value="0">All Products (Global Rule)</option>';

            // Get all products
            $products = get_posts([
                'post_type'   => 'product',
                'numberposts' => -1,
                'post_status' => 'publish',
            ]);

            foreach ($products as $product) {
                $content .= '<option value="' . $product->ID . '">' . esc_html($product->post_title) . '</option>';
            }

            $content .= '
                    </select>
                </div>

                <div class="b2b-admin-form-group">
                    <label for="edit_min_qty">Min Quantity</label>
                    <input type="number" name="min_qty" id="edit_min_qty" min="1" value="1" required>
                </div>
            </div>

            <div class="b2b-admin-form-group" style="margin-top: 20px;">
                <label for="edit_price">' . __('Value', 'b2b-commerce') . '</label>
                <input type="number" name="price" id="edit_price" step="0.01" required>
                <small id="edit_price_help">' . __('Enter percentage (e.g., 20 for 20% discount) or fixed price', 'b2b-commerce') . '</small>
            </div>

            <div class="b2b-modal-footer">
                <button type="button" class="b2b-admin-btn b2b-admin-btn-secondary" onclick="closeEditModal()">' . __('Cancel', 'b2b-commerce') . '</button>
                <button type="submit" class="b2b-admin-btn">' . __('Update Rule', 'b2b-commerce') . '</button>
            </div>
        </form>
    </div>
</div>

<style>
.b2b-modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.b2b-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.b2b-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.b2b-modal-header h3 {
    margin: 0;
    color: #333;
}

.b2b-modal-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.b2b-modal-close:hover {
    color: #000;
}

.b2b-modal form {
    padding: 20px;
}

.b2b-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.b2b-modal-footer button {
    margin-left: 10px;
}
</style>

<script>
function openEditModal(id, role, type, price, minQty, productId) {
    document.getElementById("edit_rule_id").value = id;
    document.getElementById("edit_role").value = role;
    document.getElementById("edit_type").value = type;
    document.getElementById("edit_price").value = price;
    document.getElementById("edit_min_qty").value = minQty;
    document.getElementById("edit_product_id").value = productId;

    document.getElementById("editModal").style.display = "block";
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
}

// Close modal when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById("editModal");
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>

        <!-- Current Pricing Rules -->
        <div class="b2b-admin-card">
            <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-list-view"></span>' . __('Current Pricing Rules', 'b2b-commerce') . ' (' . count($rules) . ')</div>';

            if (empty($rules)) {
                $content .= '<p style="text-align: center; color: #666; padding: 20px;">No pricing rules found. Add your first rule above.</p>';
            } else {
                $content .= '
        <table class="b2b-admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Customer Type</th>
                    <th>Pricing Type</th>
                    <th>Value</th>
                    <th>Min Quantity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';

                foreach ($rules as $rule) {
                    $value_display = $rule->type === 'percentage' ? abs($rule->price) . '% discount' : '$' . number_format($rule->price, 2);

                    // Get product name
                    $product_name = 'All Products';
                    if ($rule->product_id > 0) {
                        $product      = get_post($rule->product_id);
                        $product_name = $product ? $product->post_title : 'Product #' . $rule->product_id;
                    }

                    $content .= '
                <tr>
                    <td>' . esc_html($product_name) . '</td>
                    <td>' . esc_html(ucfirst(str_replace('_', ' ', $rule->role))) . '</td>
                    <td>' . esc_html(ucfirst($rule->type)) . '</td>
                    <td>' . esc_html($value_display) . '</td>
                    <td>' . esc_html($rule->min_qty ?: 'Any') . '</td>
                    <td>
                        <button type="button" class="b2b-admin-btn b2b-admin-btn-secondary" style="padding: 6px 12px; font-size: 0.9em; margin-right: 5px;" onclick="openEditModal(' . $rule->id . ', \'' . esc_js($rule->role) . '\', \'' . esc_js($rule->type) . '\', ' . $rule->price . ', ' . $rule->min_qty . ', ' . $rule->product_id . ')"><span class="icon dashicons dashicons-edit"></span>' . __('Edit', 'b2b-commerce') . '</button>
                        <a href="' . admin_url('admin-post.php?action=b2b_delete_pricing_rule&id=' . $rule->id . '&_wpnonce=' . wp_create_nonce('b2b_delete_pricing_rule')) . '" class="b2b-admin-btn b2b-admin-btn-danger" style="padding: 6px 12px; font-size: 0.9em;" onclick="return confirm(\'' . esc_js(__('Delete this pricing rule?', 'b2b-commerce')) . '\')"><span class="icon dashicons dashicons-trash"></span>' . __('Delete', 'b2b-commerce') . '</a>
                    </td>
                </tr>';
                }

                $content .= '
            </tbody>
        </table>';
            }

            $content .= '
        </div>

        <!-- Simple Help -->
        <div class="b2b-admin-card">
            <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-info"></span>How It Works</div>
            <div style="padding: 20px;">
                <h4>Simple Pricing Rules</h4>
                <p>Set different prices for different customer types:</p>
                <ul style="margin-left: 20px;">
                    <li><strong>Wholesale Customer:</strong> Usually gets 10-20% discount</li>
                    <li><strong>Distributor:</strong> Usually gets 20-30% discount</li>
                    <li><strong>Retailer:</strong> Usually gets 5-15% discount</li>
                </ul>

                <h4>Example Values</h4>
                <ul style="margin-left: 20px;">
                    <li><strong>Percentage Discount:</strong> Enter -15 for 15% off</li>
                    <li><strong>Fixed Price:</strong> Enter 19.99 for exact price</li>
                    <li><strong>Min Quantity:</strong> Set minimum order amount</li>
                </ul>
            </div>
        </div>';

            $this->render_admin_wrapper('b2b-pricing', $content);
        }

        // Save pricing rule with comprehensive error handling
        private function save_pricing_rule()
        {
            try {
                // Check if WooCommerce is active
                if (! class_exists('WooCommerce')) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('WooCommerce is required for pricing rules.', 'b2b-commerce') . '</p></div>';
                    return;
                }

                // Verify nonce and permissions
                if (! isset($_POST['b2b_pricing_nonce']) || ! wp_verify_nonce($_POST['b2b_pricing_nonce'], 'b2b_pricing_action')) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'b2b-commerce') . '</p></div>';
                    return;
                }

                if (! current_user_can('manage_options')) {
                    echo '<div class="notice notice-error"><p>' . esc_html__('You do not have permission to perform this action.', 'b2b-commerce') . '</p></div>';
                    return;
                }

                // Validate required fields
                $required_fields = ['role', 'type', 'price', 'min_qty'];
                $errors          = [];

                foreach ($required_fields as $field) {
                    if (! isset($_POST[$field]) || empty($_POST[$field])) {
                        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                    }
                }

                if (! empty($errors)) {
                    echo '<div class="notice notice-error"><p>' . implode('<br>', $errors) . '</p></div>';
                    return;
                }

                // Sanitize and validate data
                $role       = sanitize_text_field($_POST['role']);
                $type       = sanitize_text_field($_POST['type']);
                $price      = floatval($_POST['price']);
                $min_qty    = intval($_POST['min_qty']);
                $product_id = intval($_POST['product_id'] ?? 0);

                // Validate price
                if ($price == 0 && $type === 'percentage') {
                    echo '<div class="notice notice-error"><p>' . __('Price cannot be zero for percentage discounts.', 'b2b-commerce') . '</p></div>';
                    return;
                }

                // Validate minimum quantity
                if ($min_qty < 1) {
                    echo '<div class="notice notice-error"><p>' . __('Minimum quantity must be at least 1.', 'b2b-commerce') . '</p></div>';
                    return;
                }

                // Insert into database with error handling
                global $wpdb;
                $table = $wpdb->prefix . 'b2b_pricing_rules';

                // Ensure table exists
                if (! $this->ensure_pricing_table_exists()) {
                    echo '<div class="notice notice-error"><p>' . __('Database table could not be created. Please check your database permissions.', 'b2b-commerce') . '</p></div>';
                    return;
                }

                $data = [
                    'product_id' => $product_id,
                    'role'       => $role,
                    'user_id'    => 0,
                    'group_id'   => 0,
                    'geo_zone'   => '',
                    'start_date' => '',
                    'end_date'   => '',
                    'min_qty'    => $min_qty,
                    'max_qty'    => 0,
                    'price'      => $price,
                    'type'       => $type,
                ];

                // Check if this is an edit operation
                $edit_rule_id = isset($_POST['edit_rule_id']) ? intval($_POST['edit_rule_id']) : 0;

                if ($edit_rule_id > 0) {
                    // Update existing rule
                    $result  = $wpdb->update($table, $data, ['id' => $edit_rule_id]);
                    $message = 'Pricing rule updated successfully!';

                    // Debug: Log the update
                    error_log("B2B Pricing: Updated rule ID $edit_rule_id with price $price");
                } else {
                    // Insert new rule
                    $result  = $wpdb->insert($table, $data);
                    $message = 'Pricing rule saved successfully!';
                }

                if ($result === false) {
                    echo '<div class="notice notice-error"><p>' . sprintf(__('Failed to save pricing rule. Database error: %s', 'b2b-commerce'), esc_html($wpdb->last_error)) . '</p></div>';
                } else {
                    // Redirect with success message
                    wp_redirect(admin_url('admin.php?page=b2b-pricing&updated=1'));
                    exit;
                }

            } catch (Exception $e) {
                echo '<div class="notice notice-error"><p>' . sprintf(__('Error saving pricing rule: %s', 'b2b-commerce'), esc_html($e->getMessage())) . '</p></div>';
            }
        }

        // Ensure pricing table exists
        private function ensure_pricing_table_exists()
        {
            try {
                global $wpdb;
                $table = $wpdb->prefix . 'b2b_pricing_rules';

                // Check if table exists
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

                if ($exists !== $table) {

                    if (class_exists('B2B\\PricingManager')) {
                        B2B\PricingManager::create_pricing_table();

                        // Check again
                        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                        return $exists === $table;
                    }
                    return false;
                }

                return true;
            } catch (Exception $e) {
                error_log('B2B Commerce Table Check Error: ' . $e->getMessage());
                return false;
            }
        }

        // Email template customization page
        public function email_templates_page()
        {
            // Handle form submission
            if (isset($_POST['b2b_email_nonce']) && wp_verify_nonce($_POST['b2b_email_nonce'], 'b2b_email_templates')) {
                $email_templates = [
                    'user_approval_subject'  => sanitize_text_field($_POST['user_approval_subject'] ?? ''),
                    'user_approval_message'  => wp_kses_post($_POST['user_approval_message'] ?? ''),
                    'user_rejection_subject' => sanitize_text_field($_POST['user_rejection_subject'] ?? ''),
                    'user_rejection_message' => wp_kses_post($_POST['user_rejection_message'] ?? ''),
                    'welcome_subject'        => sanitize_text_field($_POST['welcome_subject'] ?? ''),
                    'welcome_message'        => wp_kses_post($_POST['welcome_message'] ?? ''),
                    'order_status_subject'   => sanitize_text_field($_POST['order_status_subject'] ?? ''),
                    'order_status_message'   => wp_kses_post($_POST['order_status_message'] ?? ''),
                    'pricing_change_subject' => sanitize_text_field($_POST['pricing_change_subject'] ?? ''),
                    'pricing_change_message' => wp_kses_post($_POST['pricing_change_message'] ?? ''),
                ];

                update_option('b2b_email_templates', $email_templates);
                $success_message = '<div class="b2b-admin-card" style="color: #4caf50; border-color: #4caf50; background: #f1f8e9;"><span class="icon dashicons dashicons-yes-alt"></span> ' . __('Email templates saved successfully!', 'b2b-commerce') . '</div>';
            }

            $templates = get_option('b2b_email_templates', []);

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-email"></span>' . __('Email Templates', 'b2b-commerce') . '</h1>
    <p>' . __('Customize email notifications for B2B users and administrators.', 'b2b-commerce') . '</p>
</div>';

            if (isset($success_message)) {
                $content .= $success_message;
            }

            $content .= '
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-email"></span>' . __('Email Template Management', 'b2b-commerce') . '</div>

    <form method="post" class="b2b-admin-form">
        ' . wp_nonce_field('b2b_email_templates', 'b2b_email_nonce', true, false) . '

        <div class="b2b-admin-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #23272f;">' . __('User Approval Notification', 'b2b-commerce') . '</h3>
            <div class="b2b-admin-form-group">
                <label for="user_approval_subject">' . __('Subject Line:', 'b2b-commerce') . '</label>
                <input type="text" id="user_approval_subject" name="user_approval_subject" value="' . esc_attr($templates['user_approval_subject'] ?? __('Your B2B Account Has Been Approved', 'b2b-commerce')) . '" placeholder="' . esc_attr(__('Your B2B Account Has Been Approved', 'b2b-commerce')) . '">
            </div>
            <div class="b2b-admin-form-group">
                <label for="user_approval_message">' . __('Message:', 'b2b-commerce') . '</label>
                <textarea id="user_approval_message" name="user_approval_message" rows="6" placeholder="Dear {user_name},&#10;&#10;Congratulations! Your B2B account has been approved. You can now log in and access wholesale pricing.&#10;&#10;Login URL: {login_url}&#10;&#10;Best regards,&#10;{site_name}">' . esc_textarea($templates['user_approval_message'] ?? 'Dear {user_name},

Congratulations! Your B2B account has been approved. You can now log in and access wholesale pricing.

Login URL: {login_url}

Best regards,
{site_name}') . '</textarea>
                <p style="font-size: 0.9em; color: #666; margin-top: 5px;">' . __('Available variables:', 'b2b-commerce') . ' {user_name}, {login_url}, {site_name}</p>
            </div>
        </div>

        <div class="b2b-admin-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #23272f;">' . __('User Rejection Notification', 'b2b-commerce') . '</h3>
            <div class="b2b-admin-form-group">
                <label for="user_rejection_subject">' . __('Subject Line:', 'b2b-commerce') . '</label>
                <input type="text" id="user_rejection_subject" name="user_rejection_subject" value="' . esc_attr($templates['user_rejection_subject'] ?? 'B2B Account Application Status') . '" placeholder="B2B Account Application Status">
            </div>
            <div class="b2b-admin-form-group">
                <label for="user_rejection_message">' . __('Message:', 'b2b-commerce') . '</label>
                <textarea id="user_rejection_message" name="user_rejection_message" rows="6" placeholder="Dear {user_name},&#10;&#10;We regret to inform you that your B2B account application has been rejected. Please contact us for more information.&#10;&#10;Best regards,&#10;{site_name}">' . esc_textarea($templates['user_rejection_message'] ?? 'Dear {user_name},

We regret to inform you that your B2B account application has been rejected. Please contact us for more information.

Best regards,
{site_name}') . '</textarea>
                <p style="font-size: 0.9em; color: #666; margin-top: 5px;">' . __('Available variables:', 'b2b-commerce') . ' {user_name}, {site_name}</p>
            </div>
        </div>

        <div class="b2b-admin-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #23272f;">' . __('Welcome Email for New B2B Users', 'b2b-commerce') . '</h3>
            <div class="b2b-admin-form-group">
                <label for="welcome_subject">' . __('Subject Line:', 'b2b-commerce') . '</label>
                <input type="text" id="welcome_subject" name="welcome_subject" value="' . esc_attr($templates['welcome_subject'] ?? 'Welcome to {site_name} B2B Platform') . '" placeholder="Welcome to {site_name} B2B Platform">
            </div>
            <div class="b2b-admin-form-group">
                <label for="welcome_message">' . __('Message:', 'b2b-commerce') . '</label>
                <textarea id="welcome_message" name="welcome_message" rows="6" placeholder="Dear {user_name},&#10;&#10;Welcome to our B2B platform! Your account has been created successfully.&#10;&#10;Login URL: {login_url}&#10;Dashboard: {dashboard_url}&#10;&#10;Best regards,&#10;{site_name}">' . esc_textarea($templates['welcome_message'] ?? 'Dear {user_name},

Welcome to our B2B platform! Your account has been created successfully.

Login URL: {login_url}
Dashboard: {dashboard_url}

Best regards,
{site_name}') . '</textarea>
                <p style="font-size: 0.9em; color: #666; margin-top: 5px;">' . __('Available variables:', 'b2b-commerce') . ' {user_name}, {login_url}, {dashboard_url}, {site_name}</p>
            </div>
        </div>

        <div class="b2b-admin-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #23272f;">Order Status Updates</h3>
            <div class="b2b-admin-form-group">
                <label for="order_status_subject">Subject Line:</label>
                <input type="text" id="order_status_subject" name="order_status_subject" value="' . esc_attr($templates['order_status_subject'] ?? 'Order #{order_id} Status Update') . '" placeholder="Order #{order_id} Status Update">
            </div>
            <div class="b2b-admin-form-group">
                <label for="order_status_message">Message:</label>
                <textarea id="order_status_message" name="order_status_message" rows="6" placeholder="Dear {user_name},&#10;&#10;Your order #{order_id} status has been updated to: {order_status}&#10;&#10;Order Total: {order_total}&#10;Order Date: {order_date}&#10;&#10;View Order: {order_url}&#10;&#10;Best regards,&#10;{site_name}">' . esc_textarea($templates['order_status_message'] ?? 'Dear {user_name},

Your order #{order_id} status has been updated to: {order_status}

Order Total: {order_total}
Order Date: {order_date}

View Order: {order_url}

Best regards,
{site_name}') . '</textarea>
                <p style="font-size: 0.9em; color: #666; margin-top: 5px;">Available variables: {user_name}, {order_id}, {order_status}, {order_total}, {order_date}, {order_url}, {site_name}</p>
            </div>
        </div>

        <div class="b2b-admin-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #23272f;">Pricing Change Notifications</h3>
            <div class="b2b-admin-form-group">
                <label for="pricing_change_subject">Subject Line:</label>
                <input type="text" id="pricing_change_subject" name="pricing_change_subject" value="' . esc_attr($templates['pricing_change_subject'] ?? 'Pricing Update Notification') . '" placeholder="Pricing Update Notification">
            </div>
            <div class="b2b-admin-form-group">
                <label for="pricing_change_message">Message:</label>
                <textarea id="pricing_change_message" name="pricing_change_message" rows="6" placeholder="Dear {user_name},&#10;&#10;We wanted to inform you about recent pricing updates for your account.&#10;&#10;Please log in to view the latest pricing information.&#10;&#10;Login URL: {login_url}&#10;&#10;Best regards,&#10;{site_name}">' . esc_textarea($templates['pricing_change_message'] ?? 'Dear {user_name},

We wanted to inform you about recent pricing updates for your account.

Please log in to view the latest pricing information.

Login URL: {login_url}

Best regards,
{site_name}') . '</textarea>
                <p style="font-size: 0.9em; color: #666; margin-top: 5px;">Available variables: {user_name}, {login_url}, {site_name}</p>
            </div>
        </div>

        <div class="b2b-admin-form-group">
            <button type="submit" class="b2b-admin-btn b2b-admin-btn-success">
                <span class="icon dashicons dashicons-saved"></span>Save Email Templates
            </button>
        </div>
    </form>
</div>';

            $this->render_admin_wrapper('b2b-emails', $content);
        }

        // Settings page
        public function settings_page()
        {
            $opts = get_option('b2b_general_settings', []);

            // Handle form submission
            if (isset($_POST['b2b_settings_nonce']) && wp_verify_nonce($_POST['b2b_settings_nonce'], 'b2b_settings')) {
                $new_settings = [
                    'company_required'     => isset($_POST['b2b_general_settings']['company_required']) ? 1 : 0,
                    'auto_approve'         => isset($_POST['b2b_general_settings']['auto_approve']) ? 1 : 0,
                    'require_tax_id'       => isset($_POST['b2b_general_settings']['require_tax_id']) ? 1 : 0,
                    'enable_white_label'   => isset($_POST['b2b_general_settings']['enable_white_label']) ? 1 : 0,
                    'enable_notifications' => isset($_POST['b2b_general_settings']['enable_notifications']) ? 1 : 0,
                    'enable_custom_fields' => isset($_POST['b2b_general_settings']['enable_custom_fields']) ? 1 : 0,
                ];

                update_option('b2b_general_settings', $new_settings);
                $opts            = $new_settings;
                $success_message = '<div class="b2b-admin-card" style="color: #2196f3; border-color: #2196f3; background: #f8f9fa;"><span class="icon dashicons dashicons-yes-alt"></span> ' . __('Settings saved successfully!', 'b2b-commerce') . '</div>';
            }

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-admin-generic"></span>' . __('Settings', 'b2b-commerce') . '</h1>
    <p>' . __('Configure B2B Commerce settings and customize your B2B experience.', 'b2b-commerce') . '</p>
</div>';

            if (isset($success_message)) {
                $content .= $success_message;
            }

            $content .= '
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-admin-generic"></span>' . __('General Settings', 'b2b-commerce') . '</div>

    <form method="post" class="b2b-admin-form">
        ' . wp_nonce_field('b2b_settings', 'b2b_settings_nonce', true, false) . '

        <div class="b2b-admin-form-group">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; background: #f8f9fa; border-radius: 6px; margin-bottom: 15px;">
                <div>
                    <label for="b2b_company_required" style="margin: 0; font-weight: 600; color: #23272f;">' . __('Require Company Name', 'b2b-commerce') . '</label>
                    <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">' . __('Yes, require company name during registration', 'b2b-commerce') . '</p>
                </div>
                <label class="b2b-admin-toggle">
                    <input type="checkbox" id="b2b_company_required" name="b2b_general_settings[company_required]" value="1" ' . checked($opts['company_required'] ?? '', 1, false) . '>
                    <span class="b2b-admin-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="b2b-admin-form-group">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; background: #f8f9fa; border-radius: 6px; margin-bottom: 15px;">
                <div>
                    <label for="b2b_auto_approve" style="margin: 0; font-weight: 600; color: #23272f;">' . __('Auto-approve New Users', 'b2b-commerce') . '</label>
                    <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">' . __('Automatically approve new B2B registrations', 'b2b-commerce') . '</p>
                </div>
                <label class="b2b-admin-toggle">
                    <input type="checkbox" id="b2b_auto_approve" name="b2b_general_settings[auto_approve]" value="1" ' . checked($opts['auto_approve'] ?? '', 1, false) . '>
                    <span class="b2b-admin-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="b2b-admin-form-group">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; background: #f8f9fa; border-radius: 6px; margin-bottom: 15px;">
                <div>
                    <label for="b2b_require_tax_id" style="margin: 0; font-weight: 600; color: #23272f;">' . __('Require Tax ID', 'b2b-commerce') . '</label>
                    <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">' . __('Require tax ID during registration', 'b2b-commerce') . '</p>
                </div>
                <label class="b2b-admin-toggle">
                    <input type="checkbox" id="b2b_require_tax_id" name="b2b_general_settings[require_tax_id]" value="1" ' . checked($opts['require_tax_id'] ?? '', 1, false) . '>
                    <span class="b2b-admin-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="b2b-admin-form-group">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; background: #f8f9fa; border-radius: 6px; margin-bottom: 15px;">
                <div>
                    <label for="b2b_enable_white_label" style="margin: 0; font-weight: 600; color: #23272f;">' . __('Enable White Labeling', 'b2b-commerce') . '</label>
                    <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">' . __('Remove plugin branding for client sites', 'b2b-commerce') . '</p>
                </div>
                <label class="b2b-admin-toggle">
                    <input type="checkbox" id="b2b_enable_white_label" name="b2b_general_settings[enable_white_label]" value="1" ' . checked($opts['enable_white_label'] ?? '', 1, false) . '>
                    <span class="b2b-admin-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="b2b-admin-form-group">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; background: #f8f9fa; border-radius: 6px; margin-bottom: 15px;">
                <div>
                    <label for="b2b_enable_notifications" style="margin: 0; font-weight: 600; color: #23272f;">' . __('Enable Admin Notifications', 'b2b-commerce') . '</label>
                    <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">' . __('Show notifications for pending approvals and new orders', 'b2b-commerce') . '</p>
                </div>
                <label class="b2b-admin-toggle">
                    <input type="checkbox" id="b2b_enable_notifications" name="b2b_general_settings[enable_notifications]" value="1" ' . checked($opts['enable_notifications'] ?? '', 1, false) . '>
                    <span class="b2b-admin-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="b2b-admin-form-group">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; background: #f8f9fa; border-radius: 6px; margin-bottom: 15px;">
                <div>
                    <label for="b2b_enable_custom_fields" style="margin: 0; font-weight: 600; color: #23272f;">' . __('Enable Custom Fields', 'b2b-commerce') . '</label>
                    <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">' . __('Allow custom fields in user registration and product forms', 'b2b-commerce') . '</p>
                </div>
                <label class="b2b-admin-toggle">
                    <input type="checkbox" id="b2b_enable_custom_fields" name="b2b_general_settings[enable_custom_fields]" value="1" ' . checked($opts['enable_custom_fields'] ?? '', 1, false) . '>
                    <span class="b2b-admin-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <button type="submit" class="b2b-admin-btn"><span class="icon dashicons dashicons-saved"></span>' . __('Save Changes', 'b2b-commerce') . '</button>
        </div>
    </form>
</div>

<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-info"></span>' . __('Settings Information', 'b2b-commerce') . '</div>
    <div style="padding: 20px; background: #f8f9fa; border-radius: 6px;">
        <h4 style="margin: 0 0 15px 0; color: #23272f;">' . __('About These Settings', 'b2b-commerce') . '</h4>
        <ul style="margin: 0; padding-left: 20px; color: #666;">
            <li><strong>' . __('Company Name:', 'b2b-commerce') . '</strong> ' . __('When enabled, users must provide their company name during registration', 'b2b-commerce') . '</li>
            <li><strong>' . __('Auto-approve:', 'b2b-commerce') . '</strong> ' . __('When enabled, new B2B registrations are automatically approved', 'b2b-commerce') . '</li>
            <li><strong>' . __('Tax ID:', 'b2b-commerce') . '</strong> ' . __('When enabled, users must provide their tax ID during registration', 'b2b-commerce') . '</li>
            <li><strong>' . __('White Labeling:', 'b2b-commerce') . '</strong> ' . __('When enabled, the plugin branding is removed from client sites', 'b2b-commerce') . '</li>
            <li><strong>' . __('Admin Notifications:', 'b2b-commerce') . '</strong> ' . __('When enabled, you receive notifications for pending approvals and new orders', 'b2b-commerce') . '</li>
            <li><strong>' . __('Custom Fields:', 'b2b-commerce') . '</strong> ' . __('When enabled, custom fields can be added to user registration and product forms', 'b2b-commerce') . '</li>
        </ul>
    </div>
</div>';

            $this->render_admin_wrapper('b2b-settings', $content);
        }

        // Quotes upgrade page
        public function quotes_upgrade_page()
        {
            if (! current_user_can('manage_options')) {
                return;
            }

            $content = '<div class="b2b-admin-header"><h1><span class="icon dashicons dashicons-email-alt"></span>' . __('Quote Management', 'b2b-commerce') . '</h1><p>' . __('Quote management is available in B2B Commerce.', 'b2b-commerce') . '</p></div>';

            $content .= '<div class="b2b-admin-card" style="text-align: center; padding: 40px;">
                <div style="font-size: 48px; color: #0073aa; margin-bottom: 20px;">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <h2 style="color: #0073aa; margin-bottom: 20px;">' . __('Upgrade to B2B Commerce', 'b2b-commerce') . '</h2>
                <p style="font-size: 16px; color: #666; margin-bottom: 30px;">' . __('Get access to advanced quote management features:', 'b2b-commerce') . '</p>
                
                <div style="text-align: left; max-width: 600px; margin: 0 auto;">
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Quote Request Management', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Quote Approval/Rejection Workflow', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Email Notifications for Quotes', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Quote Status Tracking', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Quote Analytics & Reporting', 'b2b-commerce') . '
                        </li>
                    </ul>
                </div>
                
                <div style="margin-top: 30px;">
                    <a href="#" class="button button-primary button-hero" style="font-size: 16px;">
                        ' . __('Upgrade to Pro', 'b2b-commerce') . '
                    </a>
                </div>
            </div>';

            $this->render_admin_wrapper('b2b-quotes-upgrade', $content);
        }

        // Product Inquiries upgrade page
        public function inquiries_upgrade_page()
        {
            if (! current_user_can('manage_options')) {
                return;
            }

            $content = '<div class="b2b-admin-header"><h1><span class="icon dashicons dashicons-format-chat"></span>' . __('Product Inquiries', 'b2b-commerce') . '</h1><p>' . __('Product inquiries management is available in B2B Commerce.', 'b2b-commerce') . '</p></div>';

            $content .= '<div class="b2b-admin-card" style="text-align: center; padding: 40px;">
                <div style="font-size: 48px; color: #0073aa; margin-bottom: 20px;">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <h2 style="color: #0073aa; margin-bottom: 20px;">' . __('Upgrade to B2B Commerce', 'b2b-commerce') . '</h2>
                <p style="font-size: 16px; color: #666; margin-bottom: 30px;">' . __('Get access to advanced product inquiry features:', 'b2b-commerce') . '</p>
                
                <div style="text-align: left; max-width: 600px; margin: 0 auto;">
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Product Inquiry Management', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Inquiry Response System', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Email Notifications for Inquiries', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Inquiry Status Tracking', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Customer Communication History', 'b2b-commerce') . '
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <span class="dashicons dashicons-yes" style="color: #4caf50; margin-right: 10px;"></span>
                            ' . __('Inquiry Analytics & Reporting', 'b2b-commerce') . '
                        </li>
                    </ul>
                </div>
                
                <div style="margin-top: 30px;">
                    <a href="#" class="button button-primary button-hero" style="font-size: 16px;">
                        ' . __('Upgrade to Pro', 'b2b-commerce') . '
                    </a>
                </div>
            </div>';

            $this->render_admin_wrapper('b2b-inquiries-upgrade', $content);
        }

        // Old quotes_page method - replaced with upgrade page
        public function quotes_page_old()
        {
            if (! current_user_can('manage_options')) {
                return;
            }

            $quotes = get_option('b2b_quote_requests', []);

            // Migrate existing quotes to include user_email if missing
            $quotes_updated = false;
            foreach ($quotes as $index => $quote) {
                if (! isset($quote['user_email']) && isset($quote['user_id'])) {
                    $user                         = get_userdata($quote['user_id']);
                    $quotes[$index]['user_email'] = $user ? $user->user_email : __('User not found', 'b2b-commerce');
                    $quotes_updated               = true;
                }
            }

            // Save updated quotes if any were modified
            if ($quotes_updated) {
                update_option('b2b_quote_requests', $quotes);
            }

            $content = '<div class="b2b-admin-header"><h1><span class="icon dashicons dashicons-email-alt"></span>' . __('Quotes', 'b2b-commerce') . '</h1><p>' . __('Manage incoming quote requests.', 'b2b-commerce') . '</p></div>';

            // Show success message if quote was updated
            if (isset($_GET['updated']) && $_GET['updated'] == '1') {
                $status      = sanitize_text_field($_GET['status'] ?? '');
                $status_text = ucfirst($status);
                $content .= '<div class="notice notice-success is-dismissible"><p><strong>' . __('Success!', 'b2b-commerce') . '</strong> ' . sprintf(__('Quote has been %s.', 'b2b-commerce'), esc_html($status_text)) . '</p></div>';
            }

            // Add migration button if there are quotes without emails
            $quotes_without_emails = 0;
            foreach ($quotes as $quote) {
                if (! isset($quote['user_email']) && isset($quote['user_id'])) {
                    $quotes_without_emails++;
                }
            }

            if ($quotes_without_emails > 0) {
                $migrate_url = wp_nonce_url(admin_url('admin-post.php?action=b2b_migrate_quotes'), 'b2b_migrate_quotes');
                $content .= '<div class="b2b-admin-card" style="margin-bottom: 20px; background: #fff3cd; border: 1px solid #ffeaa7;">';
                $content .= '<div style="padding: 24px;">';
                $content .= '<h4 style="margin: 0 0 10px 0; color: #856404;">⚠️ Data Migration Required</h4>';
                $content .= '<p style="margin: 0 0 15px 0; color: #856404;">Found ' . $quotes_without_emails . ' quote request(s) with missing email data. Click the button below to fix this.</p>';
                $content .= '<a href="' . esc_url($migrate_url) . '" class="b2b-admin-btn" style="background: #dc3545; color: #fff;">Migrate Quote Data</a>';
                $content .= '</div>';
                $content .= '</div>';
            }

            $content .= '<div class="b2b-admin-card">';
            if (empty($quotes)) {
                $content .= '<p>No quote requests found.</p>';
            } else {
                $content .= '<table class="b2b-admin-table"><thead><tr><th>Date</th><th>User</th><th>Product</th><th>Qty</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                foreach ($quotes as $index => $q) {
                    $product = function_exists('wc_get_product') ? wc_get_product($q['product_id']) : null;
                    $content .= '<tr>';
                    $content .= '<td>' . esc_html($q['date'] ?? '') . '</td>';
                    $content .= '<td>' . esc_html($q['user_email'] ?? 'No email') . '</td>';
                    $content .= '<td>' . esc_html($product ? $product->get_name() : ('#' . ($q['product_id'] ?? ''))) . '</td>';
                    $content .= '<td>' . esc_html($q['quantity'] ?? '') . '</td>';
                    $content .= '<td>' . esc_html($q['message'] ?? '') . '</td>';
                    $content .= '<td>' . esc_html(ucfirst($q['status'] ?? 'pending')) . '</td>';
                    $content .= '<td><button type="button" class="b2b-admin-btn b2b-quote-action" data-index="' . $index . '" data-action="approve">' . __('Approve', 'b2b-commerce') . '</button> <button type="button" class="b2b-admin-btn b2b-admin-btn-danger b2b-quote-action" data-index="' . $index . '" data-action="decline">' . __('Decline', 'b2b-commerce') . '</button> <button type="button" class="b2b-admin-btn b2b-admin-btn-danger b2b-delete-quote" data-index="' . $index . '">' . __('Delete', 'b2b-commerce') . '</button></td>';
                    $content .= '</tr>';
                }
                $content .= '</tbody></table>';
            }
            $content .= '</div>';

            // Add CSS and JavaScript to improve button functionality
            $content .= '<style>
        .b2b-quote-action {
            display: inline-block;
            padding: 6px 12px;
            margin: 2px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
            min-width: 60px;
        }
        .b2b-quote-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .b2b-quote-action.processing {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .b2b-admin-btn {
            background: #0073aa;
            color: white;
            border: 1px solid #0073aa;
        }
        .b2b-admin-btn:hover {
            background: #005a87;
            color: white;
        }
        .b2b-admin-btn-danger {
            background: #dc3545;
            border-color: #dc3545;
        }
        .b2b-admin-btn-danger:hover {
            background: #c82333;
        }
        .b2b-delete-quote {
            background: #6c757d !important;
            border-color: #6c757d !important;
            color: white !important;
            padding: 6px 12px !important;
            font-size: 12px !important;
            min-width: 60px !important;
        }
        .b2b-delete-quote:hover {
            background: #5a6268 !important;
            border-color: #5a6268 !important;
        }
        </style>';

            $content .= '<script>
        jQuery(document).ready(function($) {
            $(".b2b-quote-action").on("click", function() {
                var $btn = $(this);
                if ($btn.hasClass("processing")) {
                    return false;
                }

                var index = $btn.data("index");
                var action = $btn.data("action");
                var confirmText = action === "approve" ? "Are you sure you want to approve this quote?" : "Are you sure you want to decline this quote?";

                if (!confirm(confirmText)) {
                    return false;
                }

                $btn.addClass("processing").text("Processing...");

                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "b2b_update_quote_ajax",
                        quote_index: index,
                        status: action + "d", // approved or declined
                        nonce: "' . wp_create_nonce('b2b_update_quote_ajax') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            var notice = $("<div class=\"notice notice-success is-dismissible\"><p><strong>Success!</strong> " + response.data.message + "</p></div>");
                            $(".b2b-admin-header").after(notice);

                            // Update the status in the table
                            $btn.closest("tr").find("td:nth-child(6)").text(response.data.status.charAt(0).toUpperCase() + response.data.status.slice(1));

                            // Remove the buttons or disable them
                            $btn.closest("td").html("<span class=\"b2b-status-badge b2b-status-" + response.data.status + "\">" + response.data.status.charAt(0).toUpperCase() + response.data.status.slice(1) + "</span>");
                        } else {
                            alert("Error: " + response.data);
                        }
                    },
                    error: function() {
                        alert("Error: Failed to update quote. Please try again.");
                    },
                    complete: function() {
                        $btn.removeClass("processing").text(action === "approve" ? "' . esc_js(__('Approve', 'b2b-commerce')) . '" : "' . esc_js(__('Decline', 'b2b-commerce')) . '");
                    }
                });
            });

            $(".b2b-delete-quote").on("click", function() {
                var $btn = $(this);
                if ($btn.hasClass("processing")) {
                    return false;
                }

                var index = $btn.data("index");

                if (!confirm("Are you sure you want to delete this quote? This action cannot be undone.")) {
                    return false;
                }

                $btn.addClass("processing").text("Deleting...");

                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "b2b_delete_quote_ajax",
                        quote_index: index,
                        nonce: "' . wp_create_nonce('b2b_delete_quote_ajax') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            var notice = $("<div class=\"notice notice-success is-dismissible\"><p><strong>Success!</strong> Quote has been deleted.</p></div>");
                            $(".b2b-admin-header").after(notice);

                            // Remove the entire row
                            $btn.closest("tr").fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert("Error: " + response.data);
                        }
                    },
                    error: function() {
                        alert("Error: Failed to delete quote. Please try again.");
                    },
                    complete: function() {
                        $btn.removeClass("processing").text("Delete");
                    }
                });
            });
        });
        </script>';

            $this->render_admin_wrapper('b2b-quotes', $content);
        }

        // Product Inquiries admin list
        public function inquiries_page()
        {
            if (! current_user_can('manage_options')) {
                return;
            }

            $inquiries = get_option('b2b_product_inquiries', []);
            $content   = '<div class="b2b-admin-header"><h1><span class="icon dashicons dashicons-format-chat"></span>Product Inquiries</h1><p>Manage incoming product inquiries.</p></div>';
            $content .= '<div class="b2b-admin-card">';
            if (empty($inquiries)) {
                $content .= '<p>No product inquiries found.</p>';
            } else {
                $content .= '<table class="b2b-admin-table"><thead><tr><th style="width: 120px;">Date</th><th style="width: 180px;">Email</th><th style="width: 150px;">Product</th><th style="width: 400px;">Message</th><th style="width: 100px;">Status</th><th style="width: 120px; text-align: center;">Actions</th></tr></thead><tbody>';
                foreach ($inquiries as $index => $inquiry) {
                    $product = function_exists('wc_get_product') ? wc_get_product($inquiry['product_id']) : null;
                    $content .= '<tr>';
                    $content .= '<td>' . esc_html($inquiry['date'] ?? '') . '</td>';
                    $content .= '<td>' . esc_html($inquiry['email'] ?? '') . '</td>';
                    $content .= '<td>' . esc_html($product ? $product->get_name() : ('#' . ($inquiry['product_id'] ?? ''))) . '</td>';
                    $content .= '<td><div class="message-content">' . esc_html($inquiry['message'] ?? '') . '</div></td>';
                    $status       = $inquiry['status'] ?? 'pending';
                    $status_class = 'b2b-status-' . $status;
                    $content .= '<td><span class="b2b-status-badge ' . $status_class . '">' . esc_html(ucfirst($status)) . '</span></td>';
                    $content .= '<td class="actions-cell">';
                    if (($inquiry['status'] ?? 'pending') === 'pending') {
                        $content .= '<button type="button" class="b2b-respond-btn" data-index="' . $index . '">' . __('Respond', 'b2b-commerce') . '</button>';
                        $content .= '<button type="button" class="b2b-close-btn" data-index="' . $index . '">' . __('Close', 'b2b-commerce') . '</button>';
                    } elseif (($inquiry['status'] ?? 'pending') === 'responded') {
                        $content .= '<button type="button" class="b2b-view-response-btn" data-index="' . $index . '" data-response="' . esc_attr($inquiry['admin_response'] ?? '') . '" data-responded-date="' . esc_attr($inquiry['responded_date'] ?? '') . '" data-responded-by="' . esc_attr(get_userdata($inquiry['responded_by'] ?? 0)->display_name ?? 'Admin') . '">' . __('View Response', 'b2b-commerce') . '</button>';
                        $content .= '<button type="button" class="b2b-close-btn" data-index="' . $index . '">' . __('Close', 'b2b-commerce') . '</button>';
                    } elseif (($inquiry['status'] ?? 'pending') === 'closed') {
                        $content .= '<button type="button" class="b2b-view-response-btn" data-index="' . $index . '" data-response="' . esc_attr($inquiry['admin_response'] ?? '') . '" data-responded-date="' . esc_attr($inquiry['responded_date'] ?? '') . '" data-responded-by="' . esc_attr(get_userdata($inquiry['responded_by'] ?? 0)->display_name ?? 'Admin') . '">' . __('View Response', 'b2b-commerce') . '</button>';
                        $content .= '<span class="b2b-status-badge b2b-status-closed">' . __('Closed', 'b2b-commerce') . '</span>';
                    }

                    // Add delete button for all inquiries
                    $content .= '<button type="button" class="b2b-delete-btn" data-index="' . $index . '">' . __('Delete', 'b2b-commerce') . '</button>';
                    $content .= '</td>';
                    $content .= '</tr>';
                }
                $content .= '</tbody></table>';
            }
            $content .= '</div>';

            // Add response modal
            $content .= '<div id="b2b-response-modal" style="display:none;" class="b2b-modal">';
            $content .= '<div class="b2b-modal-content">';
            $content .= '<span class="b2b-modal-close">&times;</span>';
            $content .= '<h3>' . __('Respond to Inquiry', 'b2b-commerce') . '</h3>';
            $content .= '<form method="post" action="' . admin_url('admin-post.php') . '">';
            $content .= '<input type="hidden" name="action" value="b2b_update_inquiry">';
            $content .= wp_nonce_field('b2b_update_inquiry', '_wpnonce', true, false);
            $content .= '<input type="hidden" name="inquiry_index" id="inquiry_index">';
            $content .= '<input type="hidden" name="status" id="inquiry_status" value="responded">';
            $content .= '<p><label>' . __('Your Response:', 'b2b-commerce') . '<br><textarea class="b2b-admin-textarea" name="admin_response" rows="5" style="width:100%;" required></textarea></label></p>';
            $content .= '<p><small style="color: #666; font-style: italic;">' . __('Note: Email notifications are disabled on localhost. Response will be saved but not sent via email.', 'b2b-commerce') . '</small></p>';
            $content .= '<p><button type="submit" class="b2b-admin-btn">' . __('Save Response', 'b2b-commerce') . '</button></p>';
            $content .= '</form>';
            $content .= '</div>';
            $content .= '</div>';

            // Add view response modal
            $content .= '<div id="b2b-view-response-modal" style="display:none;" class="b2b-modal">';
            $content .= '<div class="b2b-modal-content">';
            $content .= '<span class="b2b-modal-close">&times;</span>';
            $content .= '<h3>' . __('View Response', 'b2b-commerce') . '</h3>';
            $content .= '<div id="response-content" style="background: #f8f9fa; padding: 24px; border-radius: 4px; margin-bottom: 20px;"></div>';
            $content .= '<p><button type="button" class="b2b-admin-btn" onclick="$(\'#b2b-view-response-modal\').hide();">' . __('Close', 'b2b-commerce') . '</button></p>';
            $content .= '</div>';
            $content .= '</div>';

            // Add delete confirmation modal
            $content .= '<div id="b2b-delete-modal" style="display:none;" class="b2b-modal">';
            $content .= '<div class="b2b-modal-content">';
            $content .= '<span class="b2b-modal-close">&times;</span>';
            $content .= '<h3>' . __('Delete Inquiry', 'b2b-commerce') . '</h3>';
            $content .= '<p>' . __('Are you sure you want to delete this inquiry? This action cannot be undone.', 'b2b-commerce') . '</p>';
            $content .= '<form method="post" action="' . admin_url('admin-post.php') . '">';
            $content .= '<input type="hidden" name="action" value="b2b_delete_inquiry">';
            $content .= wp_nonce_field('b2b_delete_inquiry', '_wpnonce', true, false);
            $content .= '<input type="hidden" name="inquiry_index" id="delete_inquiry_index">';
            $content .= '<p><button type="submit" class="b2b-admin-btn b2b-admin-btn-danger">' . __('Delete', 'b2b-commerce') . '</button> <button type="button" class="b2b-admin-btn" onclick="$(\'#b2b-delete-modal\').hide();">' . __('Cancel', 'b2b-commerce') . '</button></p>';
            $content .= '</form>';
            $content .= '</div>';
            $content .= '</div>';

            // Add JavaScript for modal functionality
            $content .= '<script>
        jQuery(function($){
            $(".b2b-respond-btn").on("click", function(){
                var index = $(this).data("index");
                $("#inquiry_index").val(index);
                $("#inquiry_status").val("responded");
                $("#b2b-response-modal").show();
            });

            $(".b2b-view-response-btn").on("click", function(){
                var index = $(this).data("index");
                var response = $(this).data("response");
                var respondedDate = $(this).data("responded-date");
                var respondedBy = $(this).data("responded-by");

                var content = "<strong>' . esc_js(__('Response:', 'b2b-commerce')) . '</strong><br>" + response + "<br><br>";
                content += "<strong>' . esc_js(__('Responded on:', 'b2b-commerce')) . '</strong> " + respondedDate + "<br>";
                content += "<strong>' . esc_js(__('Responded by:', 'b2b-commerce')) . '</strong> " + respondedBy;

                $("#response-content").html(content);
                $("#b2b-view-response-modal").show();
            });

            $(".b2b-close-btn").on("click", function(){
                var index = $(this).data("index");
                $("#inquiry_index").val(index);
                $("#inquiry_status").val("closed");
                $("input[name=admin_response]").val("' . esc_js(__('Inquiry closed by admin.', 'b2b-commerce')) . '");
                $("form").submit();
            });

            $(".b2b-delete-btn").on("click", function(){
                var index = $(this).data("index");
                $("#delete_inquiry_index").val(index);
                $("#b2b-delete-modal").show();
            });

            $(".b2b-modal-close").on("click", function(){
                $(".b2b-modal").hide();
            });

            $(window).on("click", function(e){
                if($(e.target).hasClass("b2b-modal")){
                    $(".b2b-modal").hide();
                }
            });
        });
        </script>';

            $this->render_admin_wrapper('b2b-inquiries', $content);
        }

        // Handle quote status updates
        public function handle_update_quote()
        {
            try {
                if (! current_user_can('manage_options')) {
                    wp_die(__('Unauthorized access.', 'b2b-commerce'));
                }

                $index  = isset($_GET['quote']) ? intval($_GET['quote']) : -1;
                $status = sanitize_text_field($_GET['status'] ?? '');

                if ($index < 0 || ! in_array($status, ['approved', 'declined'], true)) {
                    wp_die(__('Invalid request parameters.', 'b2b-commerce'));
                }

                if (! wp_verify_nonce($_GET['_wpnonce'] ?? '', 'b2b_update_quote_' . $index)) {
                    wp_die(__('Security check failed.', 'b2b-commerce'));
                }

                $quotes = get_option('b2b_quote_requests', []);
                if (! isset($quotes[$index])) {
                    wp_die(__('Quote not found.', 'b2b-commerce'));
                }

                $quotes[$index]['status']     = $status;
                $quotes[$index]['updated_at'] = current_time('mysql');
                $quotes[$index]['updated_by'] = get_current_user_id();

                $result = update_option('b2b_quote_requests', $quotes);

                if (! $result) {
                    wp_die(__('Failed to update quote status.', 'b2b-commerce'));
                }

                // Send email notification to customer
                $this->send_quote_status_email($quotes[$index], $status);

                wp_redirect(admin_url('admin.php?page=b2b-quotes&updated=1&status=' . $status));
                exit;

            } catch (Exception $e) {
                error_log('B2B Quote Update Error: ' . $e->getMessage());
                wp_die(__('An error occurred while updating the quote.', 'b2b-commerce'));
            }
        }

        // AJAX handler for quote updates
        public function handle_update_quote_ajax()
        {
            try {
                if (! current_user_can('manage_options')) {
                    wp_send_json_error(__('Unauthorized access.', 'b2b-commerce'));
                }

                // Validate and sanitize input
                $index  = isset($_POST['quote_index']) ? absint($_POST['quote_index']) : -1;
                $status = sanitize_text_field($_POST['status'] ?? '');

                if ($index < 0 || ! in_array($status, ['approved', 'declined'], true)) {
                    wp_send_json_error(__('Invalid request parameters.', 'b2b-commerce'));
                }

                if (! wp_verify_nonce($_POST['nonce'] ?? '', 'b2b_update_quote_ajax')) {
                    wp_send_json_error(__('Security check failed.', 'b2b-commerce'));
                }

                $quotes = get_option('b2b_quote_requests', []);
                if (! isset($quotes[$index])) {
                    wp_send_json_error(__('Quote not found.', 'b2b-commerce'));
                }

                $quotes[$index]['status']     = $status;
                $quotes[$index]['updated_at'] = current_time('mysql');
                $quotes[$index]['updated_by'] = get_current_user_id();

                $result = update_option('b2b_quote_requests', $quotes);

                if (! $result) {
                    wp_send_json_error(__('Failed to update quote status.', 'b2b-commerce'));
                }

                // Send email notification
                $this->send_quote_status_email($quotes[$index], $status);

                wp_send_json_success([
                    'message' => sprintf(__('Quote has been %s.', 'b2b-commerce'), ucfirst($status)),
                    'status'  => $status,
                ]);

            } catch (Exception $e) {
                error_log('B2B Quote AJAX Error: ' . $e->getMessage());
                wp_send_json_error(__('An error occurred while updating the quote.', 'b2b-commerce'));
            }
        }

        // Handle inquiry status updates
        public function handle_update_inquiry()
        {
            if (! current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
            }

            check_admin_referer('b2b_update_inquiry');

            $inquiry_index  = intval($_POST['inquiry_index']);
            $new_status     = sanitize_text_field($_POST['status']);
            $admin_response = sanitize_textarea_field($_POST['admin_response']);

            $inquiries = get_option('b2b_product_inquiries', []);

            if (isset($inquiries[$inquiry_index])) {
                $inquiries[$inquiry_index]['status']         = $new_status;
                $inquiries[$inquiry_index]['admin_response'] = $admin_response;
                $inquiries[$inquiry_index]['responded_date'] = current_time('mysql');
                $inquiries[$inquiry_index]['responded_by']   = get_current_user_id();

                update_option('b2b_product_inquiries', $inquiries);

                // Email functionality removed for localhost compatibility
                // If you need email functionality, configure SMTP settings

                wp_redirect(admin_url('admin.php?page=b2b-inquiries&updated=1'));
                exit;
            }

            wp_redirect(admin_url('admin.php?page=b2b-inquiries&error=1'));
            exit;
        }

        // Email function kept for future use but commented out

        // Handle inquiry deletion
        public function handle_delete_inquiry()
        {
            if (! current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
            }

            check_admin_referer('b2b_delete_inquiry');

            $inquiry_index = intval($_POST['inquiry_index']);
            $inquiries     = get_option('b2b_product_inquiries', []);

            if (isset($inquiries[$inquiry_index])) {
                // Remove the inquiry from the array
                unset($inquiries[$inquiry_index]);
                // Reindex the array
                $inquiries = array_values($inquiries);
                update_option('b2b_product_inquiries', $inquiries);

                wp_redirect(admin_url('admin.php?page=b2b-inquiries&deleted=1'));
                exit;
            }

            wp_redirect(admin_url('admin.php?page=b2b-inquiries&error=1'));
            exit;
        }

        // Handle quote data migration
        public function handle_migrate_quotes()
        {
            if (! current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
            }

            check_admin_referer('b2b_migrate_quotes');

            $quotes         = get_option('b2b_quote_requests', []);
            $migrated_count = 0;

            foreach ($quotes as $index => $quote) {
                if (! isset($quote['user_email']) && isset($quote['user_id'])) {
                    $user                         = get_userdata($quote['user_id']);
                    $quotes[$index]['user_email'] = $user ? $user->user_email : __('User not found', 'b2b-commerce');
                    $migrated_count++;
                }
            }

            if ($migrated_count > 0) {
                update_option('b2b_quote_requests', $quotes);
                wp_redirect(admin_url('admin.php?page=b2b-quotes&migrated=' . $migrated_count));
            } else {
                wp_redirect(admin_url('admin.php?page=b2b-quotes&no_migration=1'));
            }
            exit;
        }

        public function register_settings()
        {
            // Settings are now handled directly in settings_page()
            // No need for WordPress settings API registration
        }

        public function field_company_required()
        {
            // This method is no longer needed
            // Settings are handled directly in settings_page()
        }

        public function validate_settings($input)
        {
            // This method is no longer needed
            // Settings validation is handled directly in settings_page()
            return $input;
        }

        // Analytics page
        public function analytics_page()
        {
            // Get analytics data
            $total_users   = count(get_users(['role__in' => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']]));
            $pending_users = count(get_users([
                'role__in'   => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'],
                'meta_key'   => 'b2b_approval_status',
                'meta_value' => 'pending',
            ]));
            $approved_users = count(get_users([
                'role__in'   => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'],
                'meta_key'   => 'b2b_approval_status',
                'meta_value' => 'approved',
            ]));

            // Get WooCommerce analytics
            $total_revenue   = 0;
            $total_orders    = 0;
            $monthly_revenue = 0;
            $recent_orders   = [];

            if (class_exists('WooCommerce') && function_exists('wc_get_orders')) {
                try {
                    $all_orders   = wc_get_orders(['limit' => -1, 'status' => 'completed']);
                    $total_orders = count($all_orders);

                    foreach ($all_orders as $order) {
                        $total_revenue += $order->get_total();
                    }

                    // Get monthly revenue
                    $current_month  = date('Y-m');
                    $monthly_orders = wc_get_orders([
                        'limit'        => -1,
                        'status'       => 'completed',
                        'date_created' => '>=' . $current_month . '-01',
                    ]);

                    foreach ($monthly_orders as $order) {
                        $monthly_revenue += $order->get_total();
                    }

                    // Get recent orders
                    $recent_orders = wc_get_orders(['limit' => 5, 'orderby' => 'date', 'order' => 'DESC']);
                } catch (Exception $e) {
                    error_log('B2B Analytics Error: ' . $e->getMessage());
                }
            }

            // Get pricing rules count
            global $wpdb;
            $pricing_rules_count = 0;
            $table               = $wpdb->prefix . 'b2b_pricing_rules';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $pricing_rules_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            }

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-chart-line"></span>Analytics</h1>
    <p>Track B2B performance metrics, sales analytics, and business insights.</p>
</div>

<!-- Key Metrics Overview -->
<div class="b2b-stats-grid">
    <div class="b2b-stat-card">
        <div class="stat-icon dashicons dashicons-groups"></div>
        <div class="stat-value">' . number_format($total_users) . '</div>
        <div class="stat-label">' . __('Total B2B Users', 'b2b-commerce') . '</div>
    </div>
    <div class="b2b-stat-card">
        <div class="stat-icon dashicons dashicons-yes-alt"></div>
        <div class="stat-value">' . number_format($approved_users) . '</div>
        <div class="stat-label">' . __('Approved Users', 'b2b-commerce') . '</div>
    </div>
    <div class="b2b-stat-card">
        <div class="stat-icon dashicons dashicons-clock"></div>
        <div class="stat-value">' . number_format($pending_users) . '</div>
        <div class="stat-label">' . __('Pending Approvals', 'b2b-commerce') . '</div>
    </div>
    <div class="b2b-stat-card">
        <div class="stat-icon dashicons dashicons-cart"></div>
        <div class="stat-value">' . number_format($total_orders) . '</div>
        <div class="stat-label">' . __('Total Orders', 'b2b-commerce') . '</div>
    </div>
    <div class="b2b-stat-card">
        <div class="stat-icon dashicons dashicons-money-alt"></div>
        <div class="stat-value">$' . number_format($total_revenue, 2) . '</div>
        <div class="stat-label">' . __('Total Revenue', 'b2b-commerce') . '</div>
    </div>
    <div class="b2b-stat-card">
        <div class="stat-icon dashicons dashicons-tag"></div>
        <div class="stat-value">' . number_format($pricing_rules_count) . '</div>
        <div class="stat-label">' . __('Pricing Rules', 'b2b-commerce') . '</div>
    </div>
</div>

<!-- Revenue Analytics -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-chart-area"></span>' . __('Revenue Analytics', 'b2b-commerce') . '</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
        <div style="padding: 20px; background: linear-gradient(135deg, #4caf50, #388e3c); color: white; border-radius: 8px;">
            <h3 class="analytics-title" style="margin: 0 0 20px 0; font-size: 1.2em;">' . __('Total Revenue', 'b2b-commerce') . '</h3>
            <div style="font-size: 2em; font-weight: bold;">$' . number_format($total_revenue, 2) . '</div>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">' . __('All-time revenue from B2B orders', 'b2b-commerce') . '</p>
        </div>
        <div  style="padding: 20px; background: linear-gradient(135deg, #2196f3, #1976d2); color: white; border-radius: 8px;">
            <h3 class="analytics-title" style="margin: 0 0 20px 0; font-size: 1.2em;">' . __('Monthly Revenue', 'b2b-commerce') . '</h3>
            <div style="font-size: 2em; font-weight: bold;">$' . number_format($monthly_revenue, 2) . '</div>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">' . sprintf(__('Revenue for %s', 'b2b-commerce'), date('F Y')) . '</p>
        </div>
        <div  style="padding: 20px; background: linear-gradient(135deg, #ff9800, #f57c00); color: white; border-radius: 8px;">
            <h3 class="analytics-title" style="margin: 0 0 20px 0; font-size: 1.2em;">' . __('Average Order Value', 'b2b-commerce') . '</h3>
            <div style="font-size: 2em; font-weight: bold;">$' . ($total_orders > 0 ? number_format($total_revenue / $total_orders, 2) : '0.00') . '</div>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Average revenue per order</p>
        </div>
    </div>
</div>

<!-- User Analytics -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-groups"></span>' . __('User Analytics', 'b2b-commerce') . '</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
        <div style="padding: 24px; background: #f8f9fa; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; color: #2196f3; font-weight: bold; margin-bottom: 10px;">' . number_format($total_users) . '</div>
            <div style="color: #666; font-size: 0.9em;">' . __('Total B2B Users', 'b2b-commerce') . '</div>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; color: #4caf50; font-weight: bold; margin-bottom: 10px;">' . number_format($approved_users) . '</div>
            <div style="color: #666; font-size: 0.9em;">' . __('Approved Users', 'b2b-commerce') . '</div>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; color: #ff9800; font-weight: bold; margin-bottom: 10px;">' . number_format($pending_users) . '</div>
            <div style="color: #666; font-size: 0.9em;">' . __('Pending Approvals', 'b2b-commerce') . '</div>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 8px; text-align: center;">
            <div style="font-size: 2em; color: #9c27b0; font-weight: bold; margin-bottom: 10px;">' . ($total_users > 0 ? round(($approved_users / $total_users) * 100, 1) : '0') . '%</div>
            <div style="color: #666; font-size: 0.9em;">' . __('Approval Rate', 'b2b-commerce') . '</div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-cart"></span>' . __('Recent Orders', 'b2b-commerce') . '</div>';

            if (! empty($recent_orders)) {
                $content .= '
    <table class="b2b-admin-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>';

                foreach ($recent_orders as $order) {
                    $customer      = $order->get_customer_id() ? get_userdata($order->get_customer_id()) : null;
                    $customer_name = $customer ? $customer->display_name : 'Guest';

                    $content .= '
            <tr>
                <td>#' . $order->get_id() . '</td>
                <td>' . esc_html($customer_name) . '</td>
                <td>' . $order->get_date_created()->date('M j, Y') . '</td>
                <td><span class="b2b-badge b2b-badge-' . ($order->get_status() === 'completed' ? 'success' : 'warning') . '">' . ucfirst($order->get_status()) . '</span></td>
                <td>$' . number_format($order->get_total(), 2) . '</td>
            </tr>';
                }

                $content .= '
        </tbody>
    </table>';
            } else {
                $content .= '
    <div style="text-align: center; padding: 40px; color: #666;">
        <div class="dashicons dashicons-cart" style="font-size: 3em; color: #ddd; margin-bottom: 15px;"></div>
        <p>No orders found. Orders will appear here once customers start placing orders.</p>
    </div>';
            }

            $content .= '
</div>

<!-- Performance Metrics -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-chart-bar"></span>Performance Metrics</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #23272f;">User Growth</h4>
            <div style="font-size: 1.5em; font-weight: bold; color: #2196f3;">' . number_format($total_users) . '</div>
            <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">Total registered B2B users</p>
        </div>
        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #23272f;">Order Volume</h4>
            <div style="font-size: 1.5em; font-weight: bold; color: #4caf50;">' . number_format($total_orders) . '</div>
            <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">Total completed orders</p>
        </div>
        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #23272f;">Revenue Growth</h4>
            <div style="font-size: 1.5em; font-weight: bold; color: #ff9800;">$' . number_format($total_revenue, 2) . '</div>
            <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">Total revenue generated</p>
        </div>
        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #23272f;">Pricing Rules</h4>
            <div style="font-size: 1.5em; font-weight: bold; color: #9c27b0;">' . number_format($pricing_rules_count) . '</div>
            <p style="margin: 15px 0 5px 0; color: #666; font-size: 0.9em;">Active pricing rules</p>
        </div>
    </div>
</div>';

            $this->render_admin_wrapper('b2b-analytics', $content);
        }

        // Test page
        public function test_page()
        {
            // Run comprehensive tests
            $tests = $this->run_system_tests();

            $content = '
<div class="b2b-admin-header">
    <h1><span class="icon dashicons dashicons-admin-tools"></span>' . __('System Test', 'b2b-commerce') . '</h1>
    <p>' . __('Run various checks to ensure B2B Commerce is functioning correctly.', 'b2b-commerce') . '</p>
</div>

<!-- System Status Overview -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-admin-tools"></span>' . __('System Status Overview', 'b2b-commerce') . '</div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="padding: 24px; background: #f8f9fa; border-radius: 6px;">
            <h4 style="margin: 0 0 5px 0; color: #23272f;">' . __('WooCommerce', 'b2b-commerce') . '</h4>
            <p style="margin: 0; color: ' . ($tests['woocommerce']['status'] === 'OK' ? '#4caf50' : '#f44336') . ';">
                <span class="dashicons dashicons-' . ($tests['woocommerce']['status'] === 'OK' ? 'yes-alt' : 'no-alt') . '"></span>
                ' . $tests['woocommerce']['message'] . '
            </p>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 6px;">
            <h4 style="margin: 0 0 5px 0; color: #23272f;">' . __('Database', 'b2b-commerce') . '</h4>
            <p style="margin: 0; color: ' . ($tests['database']['status'] === 'OK' ? '#4caf50' : '#f44336') . ';">
                <span class="dashicons dashicons-' . ($tests['database']['status'] === 'OK' ? 'yes-alt' : 'no-alt') . '"></span>
                ' . $tests['database']['message'] . '
            </p>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 6px;">
            <h4 style="margin: 0 0 5px 0; color: #23272f;">' . __('Database Tables', 'b2b-commerce') . '</h4>
            <p style="margin: 0; color: ' . ($tests['tables']['status'] === 'OK' ? '#4caf50' : '#f44336') . ';">
                <span class="dashicons dashicons-' . ($tests['tables']['status'] === 'OK' ? 'yes-alt' : 'no-alt') . '"></span>
                ' . $tests['tables']['message'] . '
            </p>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 6px;">
            <h4 style="margin: 0 0 5px 0; color: #23272f;">' . __('Email', 'b2b-commerce') . '</h4>
            <p style="margin: 0; color: ' . ($tests['email']['status'] === 'OK' ? '#4caf50' : '#f44336') . ';">
                <span class="dashicons dashicons-' . ($tests['email']['status'] === 'OK' ? 'yes-alt' : 'no-alt') . '"></span>
                ' . $tests['email']['message'] . '
            </p>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 6px;">
            <h4 style="margin: 0 0 5px 0; color: #23272f;">' . __('Permissions', 'b2b-commerce') . '</h4>
            <p style="margin: 0; color: ' . ($tests['permissions']['status'] === 'OK' ? '#4caf50' : '#f44336') . ';">
                <span class="dashicons dashicons-' . ($tests['permissions']['status'] === 'OK' ? 'yes-alt' : 'no-alt') . '"></span>
                ' . $tests['permissions']['message'] . '
            </p>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 6px;">
            <h4 style="margin: 0 0 5px 0; color: #23272f;">' . __('User Roles', 'b2b-commerce') . '</h4>
            <p style="margin: 0; color: ' . ($tests['user_roles']['status'] === 'OK' ? '#4caf50' : '#f44336') . ';">
                <span class="dashicons dashicons-' . ($tests['user_roles']['status'] === 'OK' ? 'yes-alt' : 'no-alt') . '"></span>
                ' . $tests['user_roles']['message'] . '
            </p>
        </div>
        <div style="padding: 24px; background: #f8f9fa; border-radius: 6px;">
            <h4 style="margin: 0 0 5px 0; color: #23272f;">' . __('Plugin Version', 'b2b-commerce') . '</h4>
            <p style="margin: 0; color: #2196f3;">
                <span class="dashicons dashicons-info"></span>
                v' . B2B_COMMERCE_VERSION . '
            </p>
        </div>
    </div>
</div>

<!-- Detailed Test Results -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-list-view"></span>' . __('Detailed Test Results', 'b2b-commerce') . '</div>
    <table class="b2b-admin-table">
        <thead>
            <tr>
                <th>' . __('Component', 'b2b-commerce') . '</th>
                <th>' . __('Status', 'b2b-commerce') . '</th>
                <th>' . __('Details', 'b2b-commerce') . '</th>
            </tr>
        </thead>
        <tbody>';

            foreach ($tests['detailed'] as $component => $test) {
                $status_color = $test['status'] === 'OK' ? '#4caf50' : '#f44336';
                $status_icon  = $test['status'] === 'OK' ? 'yes-alt' : 'no-alt';

                $content .= '
            <tr>
                <td><strong>' . esc_html($component) . '</strong></td>
                <td><span style="color: ' . $status_color . ';"><span class="dashicons dashicons-' . $status_icon . '"></span> ' . $test['status'] . '</span></td>
                <td>' . esc_html($test['message']) . '</td>
            </tr>';
            }

            $content .= '
        </tbody>
    </table>
</div>

<!-- Quick Actions -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-admin-links"></span>' . __('Quick Actions', 'b2b-commerce') . '</div>
    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
        <a href="' . admin_url('admin.php?page=b2b-dashboard') . '" class="b2b-admin-btn"><span class="icon dashicons dashicons-chart-area"></span>' . __('Dashboard', 'b2b-commerce') . '</a>
        <a href="' . admin_url('admin.php?page=b2b-users') . '" class="b2b-admin-btn"><span class="icon dashicons dashicons-groups"></span>' . __('Manage Users', 'b2b-commerce') . '</a>
        <a href="' . admin_url('admin.php?page=b2b-add-user') . '" class="b2b-admin-btn b2b-admin-btn-success"><span class="icon dashicons dashicons-plus"></span>' . __('Add New User', 'b2b-commerce') . '</a>
        <a href="' . admin_url('admin.php?page=b2b-pricing') . '" class="b2b-admin-btn"><span class="icon dashicons dashicons-tag"></span>' . __('B2B Pricing', 'b2b-commerce') . '</a>
        <a href="' . admin_url('admin.php?page=b2b-settings') . '" class="b2b-admin-btn"><span class="icon dashicons dashicons-admin-generic"></span>' . __('Settings', 'b2b-commerce') . '</a>
    </div>
</div>

<!-- Troubleshooting Guide -->
<div class="b2b-admin-card">
    <div class="b2b-admin-card-title"><span class="icon dashicons dashicons-info"></span>' . __('Troubleshooting Guide', 'b2b-commerce') . '</div>
    <div style="padding: 20px; background: #f8f9fa; border-radius: 6px;">
        <h4 style="margin: 0 0 15px 0; color: #23272f;">' . __('Common Issues & Solutions', 'b2b-commerce') . '</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div>
                <h5 style="margin: 0 0 10px 0; color: #f44336;">❌ ' . __('WooCommerce Not Active', 'b2b-commerce') . '</h5>
                <p style="margin: 0; font-size: 0.9em; color: #666;">' . __('Solution: Install and activate WooCommerce plugin', 'b2b-commerce') . '</p>
            </div>
            <div>
                <h5 style="margin: 0 0 10px 0; color: #ff9800;">⚠️ ' . __('Database Table Missing', 'b2b-commerce') . '</h5>
                <p style="margin: 0; font-size: 0.9em; color: #666;">' . __('Solution: Deactivate and reactivate the plugin', 'b2b-commerce') . '</p>
            </div>
            <div>
                <h5 style="margin: 0 0 10px 0; color: #2196f3;">ℹ️ ' . __('User Roles Not Created', 'b2b-commerce') . '</h5>
                <p style="margin: 0; font-size: 0.9em; color: #666;">' . __('Solution: Check plugin activation and permissions', 'b2b-commerce') . '</p>
            </div>
        </div>
    </div>
</div>';

            $this->render_admin_wrapper('b2b-test', $content);
        }

        // Run comprehensive system tests
        private function run_system_tests()
        {
            $tests = [];

            // Database connectivity test
            global $wpdb;
            $db_connected      = $wpdb->check_connection();
            $tests['database'] = [
                'status'  => $db_connected ? 'OK' : 'FAILED',
                'message' => $db_connected ? __('Connected', 'b2b-commerce') : __('Connection Failed', 'b2b-commerce'),
            ];

            // WooCommerce integration test
            $wc_active            = class_exists('WooCommerce');
            $tests['woocommerce'] = [
                'status'  => $wc_active ? 'OK' : 'FAILED',
                'message' => $wc_active ? __('Active', 'b2b-commerce') : __('Not Active', 'b2b-commerce'),
            ];

            // Email functionality test
            $email_working  = wp_mail('test@example.com', 'Test', 'Test message') !== false;
            $tests['email'] = [
                'status'  => $email_working ? 'OK' : 'FAILED',
                'message' => $email_working ? __('Working', 'b2b-commerce') : __('Not Working', 'b2b-commerce'),
            ];

            // File permissions test
            $permissions_ok       = is_writable(WP_CONTENT_DIR);
            $tests['permissions'] = [
                'status'  => $permissions_ok ? 'OK' : 'FAILED',
                'message' => $permissions_ok ? __('Writable', 'b2b-commerce') : __('Not Writable', 'b2b-commerce'),
            ];

            // Plugin tables test
            $pricing_table   = $wpdb->prefix . 'b2b_pricing_rules';
            $table_exists    = $wpdb->get_var("SHOW TABLES LIKE '$pricing_table'") === $pricing_table;
            $tests['tables'] = [
                'status'  => $table_exists ? 'OK' : 'FAILED',
                'message' => $table_exists ? __('Exists', 'b2b-commerce') : __('Missing', 'b2b-commerce'),
            ];

            // User roles test
            $roles_exist    = true;
            $required_roles = ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'];
            foreach ($required_roles as $role) {
                if (! get_role($role)) {
                    $roles_exist = false;
                    break;
                }
            }
            $tests['user_roles'] = [
                'status'  => $roles_exist ? 'OK' : 'FAILED',
                'message' => $roles_exist ? __('Configured', 'b2b-commerce') : __('Missing Roles', 'b2b-commerce'),
            ];

            // Detailed tests
            $tests['detailed'] = [
                __('Plugin Files', 'b2b-commerce')         => [
                    'status'  => file_exists(B2B_COMMERCE_PATH . 'b2b-commerce.php') ? 'OK' : 'FAILED',
                    'message' => __('Main plugin file check', 'b2b-commerce'),
                ],
                __('Admin Panel', 'b2b-commerce')          => [
                    'status'  => class_exists('B2B\\AdminPanel') ? 'OK' : 'FAILED',
                    'message' => __('Admin panel class loaded', 'b2b-commerce'),
                ],
                __('CSS Files', 'b2b-commerce')            => [
                    'status'  => file_exists(B2B_COMMERCE_PATH . 'assets/css/b2b-admin-standalone-demo.css') ? 'OK' : 'FAILED',
                    'message' => __('Admin CSS files present', 'b2b-commerce'),
                ],
                __('JS Files', 'b2b-commerce')             => [
                    'status'  => file_exists(B2B_COMMERCE_PATH . 'assets/js/b2b-commerce.js') ? 'OK' : 'FAILED',
                    'message' => __('Admin JavaScript files present', 'b2b-commerce'),
                ],
                __('Database Permissions', 'b2b-commerce') => [
                    'status'  => current_user_can('manage_options') ? 'OK' : 'FAILED',
                    'message' => __('Admin permissions check', 'b2b-commerce'),
                ],
                __('AJAX Endpoints', 'b2b-commerce')       => [
                    'status'  => has_action('wp_ajax_b2b_approve_user') ? 'OK' : 'FAILED',
                    'message' => __('AJAX handlers registered', 'b2b-commerce'),
                ],
            ];

            return $tests;
        }

        // Import/Export page
        public function import_export_page()
        {
            // Handle import form submission
            if (isset($_POST['b2b_import']) && isset($_FILES['import_file'])) {
                $this->handle_import_data();
            }

            $this->render_admin_wrapper('b2b-import-export', $this->get_import_export_content());
        }

        private function handle_import_data()
        {
            if (! wp_verify_nonce($_POST['b2b_import_nonce'], 'b2b_import_export')) {
                wp_die(__('Security check failed.', 'b2b-commerce'));
            }

            if (! isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                wp_die(__('File upload failed.', 'b2b-commerce'));
            }

            $import_type = sanitize_text_field($_POST['import_type']);
            $file        = $_FILES['import_file']['tmp_name'];

            switch ($import_type) {
                case 'users':
                    $this->import_users_from_csv($file);
                    break;
                case 'pricing':
                    $this->import_pricing_from_csv($file);
                    break;
                default:
                    wp_die(__('Invalid import type.', 'b2b-commerce'));
            }
        }

        private function import_users_from_csv($file)
        {
            $handle = fopen($file, 'r');
            if (! $handle) {
                wp_die(__('Cannot open file.', 'b2b-commerce'));
            }

            $headers  = fgetcsv($handle);
            $imported = 0;
            $errors   = [];

            while (($data = fgetcsv($handle)) !== false) {
                $user_data = array_combine($headers, $data);

                try {
                    $user_id = $this->create_user_from_import($user_data);
                    if ($user_id) {
                        $imported++;
                    }
                } catch (Exception $e) {
                    $errors[] = 'Row ' . ($imported + 1) . ': ' . $e->getMessage();
                }
            }

            fclose($handle);

            $message = "Imported $imported users successfully.";
            if (! empty($errors)) {
                $message .= " Errors: " . implode(', ', $errors);
            }

            wp_redirect(admin_url('admin.php?page=b2b-import-export&imported=' . $imported . '&errors=' . count($errors)));
            exit;
        }

        private function import_pricing_from_csv($file)
        {
            $handle = fopen($file, 'r');
            if (! $handle) {
                wp_die(__('Cannot open file.', 'b2b-commerce'));
            }

            $headers  = fgetcsv($handle);
            $imported = 0;
            $errors   = [];

            global $wpdb;
            $table = $wpdb->prefix . 'b2b_pricing_rules';

            while (($data = fgetcsv($handle)) !== false) {
                $pricing_data = array_combine($headers, $data);

                try {
                    $result = $wpdb->insert($table, [
                        'role'       => sanitize_text_field($pricing_data['Role'] ?? ''),
                        'type'       => sanitize_text_field($pricing_data['Type'] ?? ''),
                        'price'      => floatval($pricing_data['Price'] ?? 0),
                        'min_qty'    => intval($pricing_data['Min Quantity'] ?? 0),
                        'max_qty'    => intval($pricing_data['Max Quantity'] ?? 0),
                        'product_id' => intval($pricing_data['Product ID'] ?? 0),
                        'user_id'    => 0,
                    ]);

                    if ($result) {
                        $imported++;
                    }
                } catch (Exception $e) {
                    $errors[] = 'Row ' . ($imported + 1) . ': ' . $e->getMessage();
                }
            }

            fclose($handle);

            $message = "Imported $imported pricing rules successfully.";
            if (! empty($errors)) {
                $message .= " Errors: " . implode(', ', $errors);
            }

            wp_redirect(admin_url('admin.php?page=b2b-import-export&imported=' . $imported . '&errors=' . count($errors)));
            exit;
        }

        private function create_user_from_import($user_data)
        {
            $username   = sanitize_user($user_data['Username'] ?? '');
            $email      = sanitize_email($user_data['Email'] ?? '');
            $first_name = sanitize_text_field($user_data['First Name'] ?? '');
            $last_name  = sanitize_text_field($user_data['Last Name'] ?? '');
            $company    = sanitize_text_field($user_data['Company Name'] ?? '');
            $role       = sanitize_text_field($user_data['Role'] ?? 'b2b_customer');

            if (empty($email)) {
                throw new Exception('Email is required');
            }

            if (empty($username)) {
                $username = $email;
            }

            $user = get_user_by('email', $email);
            if ($user) {
                // Update existing user
                $user_id = $user->ID;
                wp_update_user([
                    'ID'         => $user_id,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'user_login' => $username,
                ]);
            } else {
                // Create new user
                $user_id = wp_create_user($username, wp_generate_password(), $email);
                if (is_wp_error($user_id)) {
                    throw new Exception($user_id->get_error_message());
                }

                wp_update_user([
                    'ID'         => $user_id,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                ]);
            }

            // Set role
            $user_obj = get_userdata($user_id);
            $user_obj->set_role($role);

            // Set company name
            if (! empty($company)) {
                update_user_meta($user_id, 'company_name', $company);
            }

            // Set approval status
            update_user_meta($user_id, 'b2b_approval_status', 'approved');

            return $user_id;
        }

        private function get_import_export_content()
        {
            ob_start();
        ?>
        <div class="b2b-import-export-container">
            <h2><?php echo __('Bulk Import/Export', 'b2b-commerce'); ?></h2>
            <p style="color: #666; margin-bottom: 20px;"><?php echo __('Export your B2B data to CSV format for backup or external processing.', 'b2b-commerce'); ?></p>

            <div class="b2b-import-export-section">
                <h3><?php echo __('Export Data', 'b2b-commerce'); ?></h3>
                <p style="color: #666; margin-bottom: 15px;"><?php echo __('Click any button below to export the corresponding data:', 'b2b-commerce'); ?></p>
                <div class="b2b-export-options">
                    <button class="button button-primary" onclick="exportB2BData('users')"><?php echo __('Export Users', 'b2b-commerce'); ?></button>
                    <span style="margin-left: 10px; color: #666; font-size: 0.9em;"><?php echo __('Exports all B2B users with their details', 'b2b-commerce'); ?></span><br><br>
                    <button class="button button-primary" onclick="exportB2BData('pricing')"><?php echo __('Export Pricing Rules', 'b2b-commerce'); ?></button>
                    <span style="margin-left: 10px; color: #666; font-size: 0.9em;"><?php echo __('Exports all B2B pricing rules', 'b2b-commerce'); ?></span><br><br>
                    <button class="button button-primary" onclick="exportB2BData('orders')"><?php echo __('Export Orders', 'b2b-commerce'); ?></button>
                    <span style="margin-left: 10px; color: #666; font-size: 0.9em;"><?php echo __('Exports all WooCommerce orders (if any exist)', 'b2b-commerce'); ?></span>
                </div>
                <p style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-left: 4px solid #2196f3; color: #666;">
                    <strong><?php echo __('Note:', 'b2b-commerce'); ?></strong> <?php echo __('If no data exists for a particular export type, the CSV will contain a message indicating "No data found".', 'b2b-commerce'); ?>
                </p>
            </div>

            <div class="b2b-import-export-section">
                <h3><?php echo __('Import Data', 'b2b-commerce'); ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('b2b_import_export', 'b2b_import_nonce'); ?>
                    <p>
                        <label><?php echo __('Select File Type:', 'b2b-commerce'); ?></label>
                        <select name="import_type">
                            <option value="users"><?php echo __('Users', 'b2b-commerce'); ?></option>
                            <option value="pricing"><?php echo __('Pricing Rules', 'b2b-commerce'); ?></option>
                        </select>
                    </p>
                    <p>
                        <label><?php echo __('CSV File:', 'b2b-commerce'); ?></label>
                        <input type="file" name="import_file" accept=".csv" required>
                    </p>
                    <p>
                        <input type="submit" name="b2b_import" value="<?php echo __('Import Data', 'b2b-commerce'); ?>" class="button button-primary">
                    </p>
                </form>
            </div>

            <div class="b2b-import-export-section">
                <h3><?php echo __('Template Downloads', 'b2b-commerce'); ?></h3>
                <p><?php echo __('Download CSV templates for importing data:', 'b2b-commerce'); ?></p>
                <a href="<?php echo admin_url('admin-ajax.php?action=b2b_download_template&type=users&nonce=' . wp_create_nonce('b2b_template_nonce')); ?>" class="button"><?php echo __('Users Template', 'b2b-commerce'); ?></a>
                <a href="<?php echo admin_url('admin-ajax.php?action=b2b_download_template&type=pricing&nonce=' . wp_create_nonce('b2b_template_nonce')); ?>" class="button"><?php echo __('Pricing Template', 'b2b-commerce'); ?></a>
            </div>
        </div>

        <script>
        function exportB2BData(type) {
            jQuery.post(ajaxurl, {
                action: 'b2b_export_data',
                type: type,
                nonce: '<?php echo wp_create_nonce("b2b_ajax_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    // Create and download CSV file
                    var blob = new Blob([response.data], {type: 'text/csv'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'b2b_' + type + '_' + new Date().toISOString().slice(0,10) + '.csv';
                    a.click();
                } else {
                    alert('Export failed: ' + response.data);
                }
            });
        }
        </script>
        <?php
            return ob_get_clean();
        }

                // Helper function to process email templates
                private function process_email_template($template_key, $variables = [])
                {
                    $templates = get_option('b2b_email_templates', []);

                    $subject_key = $template_key . '_subject';
                    $message_key = $template_key . '_message';

                    $subject = $templates[$subject_key] ?? '';
                    $message = $templates[$message_key] ?? '';

                    // Replace variables
                    foreach ($variables as $key => $value) {
                        $subject = str_replace('{' . $key . '}', $value, $subject);
                        $message = str_replace('{' . $key . '}', $value, $message);
                    }

                    return [
                        'subject' => $subject,
                        'message' => $message,
                    ];
                }

                // Show admin notifications
                public function show_admin_notifications()
                {
                    $opts = get_option('b2b_general_settings', []);
                    if (! isset($opts['enable_notifications']) || ! $opts['enable_notifications']) {
                        return;
                    }

                    $dismissed_notifications = get_option('b2b_dismissed_notifications', []);

                    // Check for pending approvals
                    $pending_users = count(get_users([
                        'role__in'   => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'],
                        'meta_key'   => 'b2b_approval_status',
                        'meta_value' => 'pending',
                    ]));

                    if ($pending_users > 0 && ! in_array('pending_approvals', $dismissed_notifications)) {
                        echo '<div class="notice notice-warning is-dismissible" data-notification="pending_approvals">
                <p><strong>B2B Commerce:</strong> You have <strong>' . $pending_users . '</strong> pending B2B user approval(s).
                <a href="' . admin_url('admin.php?page=b2b-users') . '">Review now</a></p>
            </div>';
                    }

                    // Check for recent orders
                    if (class_exists('WooCommerce') && function_exists('wc_get_orders')) {
                        $recent_orders = wc_get_orders([
                            'limit'        => 5,
                            'orderby'      => 'date',
                            'order'        => 'DESC',
                            'date_created' => '>=' . date('Y-m-d', strtotime('-24 hours')),
                        ]);

                        if (count($recent_orders) > 0 && ! in_array('recent_orders', $dismissed_notifications)) {
                            echo '<div class="notice notice-info is-dismissible" data-notification="recent_orders">
                    <p><strong>B2B Commerce:</strong> You have <strong>' . count($recent_orders) . '</strong> new order(s) in the last 24 hours.
                    <a href="' . admin_url('admin.php?page=b2b-orders') . '">View orders</a></p>
                </div>';
                        }
                    }

                    // Add JavaScript for dismissible notifications
                    echo '<script>
        jQuery(document).ready(function($) {
            $(".notice[data-notification]").on("click", ".notice-dismiss", function() {
                var notification = $(this).closest(".notice").data("notification");
                $.post(ajaxurl, {
                    action: "b2b_dismiss_notification",
                    notification: notification,
                    nonce: "' . wp_create_nonce('b2b_dismiss_notification') . '"
                });
            });
        });
        </script>';
                }

                // Dismiss notification
                public function dismiss_notification()
                {
                    if (! current_user_can('manage_options')) {
                        wp_die(__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
                    }

                    if (! wp_verify_nonce($_POST['nonce'], 'b2b_dismiss_notification')) {
                        wp_die(__('Security check failed.', 'b2b-commerce'));
                    }

                    $notification = sanitize_text_field($_POST['notification']);
                    $dismissed    = get_option('b2b_dismissed_notifications', []);
                    $dismissed[]  = $notification;
                    update_option('b2b_dismissed_notifications', array_unique($dismissed));

                    wp_send_json_success('Notification dismissed');
                }

                // AJAX handler for quote deletion
                public function handle_delete_quote_ajax()
                {
                    try {
                        if (! current_user_can('manage_options')) {
                            wp_send_json_error(__('Unauthorized access.', 'b2b-commerce'));
                        }

                        // Validate and sanitize input
                        $index = isset($_POST['quote_index']) ? absint($_POST['quote_index']) : -1;

                        if ($index < 0) {
                            wp_send_json_error(__('Invalid request parameters.', 'b2b-commerce'));
                        }

                        if (! wp_verify_nonce($_POST['nonce'] ?? '', 'b2b_delete_quote_ajax')) {
                            wp_send_json_error(__('Security check failed.', 'b2b-commerce'));
                        }

                        $quotes = get_option('b2b_quote_requests', []);
                        if (! isset($quotes[$index])) {
                            wp_send_json_error(__('Quote not found.', 'b2b-commerce'));
                        }

                        // Remove the quote from the array
                        unset($quotes[$index]);

                        // Reindex the array to maintain sequential keys
                        $quotes = array_values($quotes);

                        $result = update_option('b2b_quote_requests', $quotes);

                        if (! $result) {
                            wp_send_json_error(__('Failed to delete quote.', 'b2b-commerce'));
                        }

                        wp_send_json_success([
                            'message' => __('Quote has been deleted successfully.', 'b2b-commerce'),
                        ]);

                    } catch (Exception $e) {
                        error_log('B2B Quote Delete AJAX Error: ' . $e->getMessage());
                        wp_send_json_error(__('An error occurred while deleting the quote.', 'b2b-commerce'));
                    }
                }

                // Send email notification for quote status changes
                private function send_quote_status_email($quote, $status)
                {
                    if (empty($quote['user_email'])) {
                        return false;
                    }

                    $product      = wc_get_product($quote['product_id']);
                    $product_name = $product ? $product->get_name() : __('Product', 'b2b-commerce');

                    $subject = sprintf(__('Your quote request for %s has been %s', 'b2b-commerce'), $product_name, $status);

                    $message = sprintf(
                        __('Dear Customer,

Your quote request for %s (Quantity: %d) has been %s.

Quote Details:
- Product: %s
- Quantity: %d
- Your Message: %s
- Status: %s

%s

Best regards,
%s', 'b2b-commerce'),
                        $product_name,
                        $quote['quantity'],
                        $status,
                        $product_name,
                        $quote['quantity'],
                        $quote['message'] ?? '',
                        ucfirst($status),
                        $status === 'approved' ? __('We will contact you shortly with pricing information.', 'b2b-commerce') : __('We are unable to fulfill this request at this time.', 'b2b-commerce'),
                        get_bloginfo('name')
                    );

                    $headers = ['Content-Type: text/plain; charset=UTF-8'];

                    return wp_mail($quote['user_email'], $subject, $message, $headers);
            }
        }