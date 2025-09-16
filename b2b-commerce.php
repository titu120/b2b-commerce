<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*
Plugin Name: B2B Commerce
Description: Free WooCommerce B2B & Wholesale Plugin with user management, pricing, and product control. Upgrade to B2B Commerce for quotes, product inquiries, and bulk calculator features.
Version: 1.0.0
Author:      softivus
Author URI:  https://softivus.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: b2b-commerce
Domain Path: /languages
Tested up to: 6.8
Requires at least: 5.0
Requires PHP: 7.4
*/


// Define plugin constant
define( 'B2B_COMMERCE_VERSION', '1.0.0' );
define( 'B2B_COMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'B2B_COMMERCE_URL', plugin_dir_url( __FILE__ ) );
define( 'B2B_COMMERCE_BASENAME', plugin_basename( __FILE__ ) );

function autoload_b2b_commerce() {
    spl_autoload_register( function ( $class ) {
        $prefix = 'B2B\\';
        $base_dir = __DIR__ . '/includes/';
        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }
        $relative_class = substr( $class, $len );
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
        if ( file_exists( $file ) ) {
            require $file;
        }
    } );
}

// Register the autoloader
autoload_b2b_commerce();

// Text domain is automatically loaded by WordPress.org for hosted plugins

// Bootstrap the plugin
add_action( 'plugins_loaded', function() {
    if ( !class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('B2B Commerce:', 'b2b-commerce') . '</strong> ' . esc_html__('WooCommerce is required for this plugin to work.', 'b2b-commerce') . '</p></div>';
        });
        return;
    }
    
    // Check if required classes exist before initializing
    if ( class_exists( 'B2B\\Init' ) ) {
        try {
            B2B\Init::instance();
        } catch ( Exception $e ) {
            // Log error but don't break the site
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                error_log( 'B2B Commerce Error: ' . $e->getMessage() );
            }
        }
    } else {
        // Show admin notice if Init class is missing
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('B2B Commerce:', 'b2b-commerce') . '</strong> ' . esc_html__('Required classes not found. Please reinstall the plugin.', 'b2b-commerce') . '</p></div>';
        });
    }
    
    // Ensure pricing table exists
    if ( class_exists( 'B2B\\PricingManager' ) ) {
        B2B\PricingManager::create_pricing_table();
    }
} );

// Register activation and deactivation hooks
register_activation_hook( __FILE__, function() {
    try {
        // Load autoloader first
        autoload_b2b_commerce();
        
        // Create pricing table
        if ( class_exists( 'B2B\\PricingManager' ) ) {
            B2B\PricingManager::create_pricing_table();
        }
        // Add roles
        if ( class_exists( 'B2B\\UserManager' ) ) {
            B2B\UserManager::add_roles();
        }
        
        // Auto-create essential B2B pages
        create_b2b_pages();
        
    } catch ( Exception $e ) {
        // Log activation error
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( 'B2B Commerce Activation Error: ' . $e->getMessage() );
        }
    }
} );

// Function to create essential B2B pages
function create_b2b_pages() {
    // Check if pages already exist to avoid duplicates
    $existing_pages = get_posts([
        'post_type' => 'page',
        'meta_query' => [
            [
                'key' => '_b2b_page_type',
                'value' => ['registration', 'dashboard', 'account'],
                'compare' => 'IN'
            ]
        ],
        'posts_per_page' => -1
    ]);
    
    $existing_page_types = wp_list_pluck($existing_pages, 'post_name');
    
    // B2B Registration Page
    if (!in_array('b2b-registration', $existing_page_types)) {
        $registration_page = wp_insert_post([
            'post_title' => __('B2B Registration', 'b2b-commerce'),
            'post_name' => 'b2b-registration',
            'post_content' => '[b2b_registration]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => 1,
            'meta_input' => [
                '_b2b_page_type' => 'registration'
            ]
        ]);
        
        if ($registration_page) {
            update_option('b2b_registration_page_id', $registration_page);
        }
    }
    
    // B2B Dashboard Page
    if (!in_array('b2b-dashboard', $existing_page_types)) {
        $dashboard_page = wp_insert_post([
            'post_title' => __('B2B Dashboard', 'b2b-commerce'),
            'post_name' => 'b2b-dashboard',
            'post_content' => '[b2b_dashboard]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => 1,
            'meta_input' => [
                '_b2b_page_type' => 'dashboard'
            ]
        ]);
        
        if ($dashboard_page) {
            update_option('b2b_dashboard_page_id', $dashboard_page);
        }
    }
    
    // Account Settings Page
    if (!in_array('account-settings', $existing_page_types)) {
        $account_page = wp_insert_post([
            'post_title' => __('Account Settings', 'b2b-commerce'),
            'post_name' => 'account-settings',
            'post_content' => '[b2b_account]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => 1,
            'meta_input' => [
                '_b2b_page_type' => 'account'
            ]
        ]);
        
        if ($account_page) {
            update_option('b2b_account_page_id', $account_page);
        }
    }
    
    // Add admin notice about created pages
    add_option('b2b_pages_created_notice', true);
    
    // Try to add B2B Registration to main menu
    add_b2b_registration_to_menu();
}

// Function to add B2B Registration to main menu
function add_b2b_registration_to_menu() {
    $registration_page_id = get_option('b2b_registration_page_id');
    if (!$registration_page_id) return;
    
    // Get the primary menu
    $primary_menu = get_nav_menu_locations();
    $primary_menu_id = $primary_menu['primary'] ?? null;
    
    if (!$primary_menu_id) {
        // Try to find any menu
        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
            $primary_menu_id = $menus[0]->term_id;
        }
    }
    
    if ($primary_menu_id) {
        // Check if menu item already exists
        $menu_items = wp_get_nav_menu_items($primary_menu_id);
        $registration_exists = false;
        
        foreach ($menu_items as $item) {
            if ($item->object_id == $registration_page_id) {
                $registration_exists = true;
                break;
            }
        }
        
        if (!$registration_exists) {
            wp_update_nav_menu_item($primary_menu_id, 0, [
                'menu-item-title' => __('B2B Registration', 'b2b-commerce'),
                'menu-item-object-id' => $registration_page_id,
                'menu-item-object' => 'page',
                'menu-item-status' => 'publish',
                'menu-item-type' => 'post_type'
            ]);
        }
    }
}

register_deactivation_hook( __FILE__, function() {
    try {
        // Load autoloader first
        autoload_b2b_commerce();
        
        if ( class_exists( 'B2B\\UserManager' ) ) {
            B2B\UserManager::remove_roles();
        }
    } catch ( Exception $e ) {
        // Log deactivation error
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( 'B2B Commerce Deactivation Error: ' . $e->getMessage() );
        }
    }
} );

// AJAX handlers for B2B Commerce with comprehensive error handling
add_action('wp_ajax_b2b_approve_user', function() {
    try {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'b2b-commerce'));
            return;
        }
        
        if (!isset($_POST['user_id']) || !isset($_POST['nonce'])) {
            wp_send_json_error(__('Missing required parameters', 'b2b-commerce'));
            return;
        }
        
        $user_id = intval( sanitize_text_field( wp_unslash( $_POST['user_id'] ) ) );
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        
        // Accept either per-user nonce or a generic approve nonce for flexibility
        if (!wp_verify_nonce($nonce, 'b2b_approve_user_' . $user_id) && !wp_verify_nonce($nonce, 'b2b_approve_user_nonce')) {
            wp_send_json_error(__('Security check failed', 'b2b-commerce'));
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(__('User not found', 'b2b-commerce'));
            return;
        }
        
        update_user_meta($user_id, 'b2b_approval_status', 'approved');
        
        // Send email notification using custom template
        $user = get_userdata($user_id);
        $templates = get_option('b2b_email_templates', []);
        
        $subject = $templates['user_approval_subject'] ?? __('Your B2B Account Approved', 'b2b-commerce');
        $message = $templates['user_approval_message'] ?? __('Congratulations! Your B2B account has been approved. You can now log in and access wholesale pricing.', 'b2b-commerce');
        
        // Replace variables with proper escaping
        $subject = str_replace(['{user_name}', '{login_url}', '{site_name}'], 
            [esc_html($user->display_name), esc_url(wp_login_url()), esc_html(get_bloginfo('name'))], $subject);
        $message = str_replace(['{user_name}', '{login_url}', '{site_name}'], 
            [esc_html($user->display_name), esc_url(wp_login_url()), esc_html(get_bloginfo('name'))], $message);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
        
        wp_send_json_success(__('User approved successfully', 'b2b-commerce'));
        
    } catch (Exception $e) {
        wp_send_json_error(__('Error: ', 'b2b-commerce') . $e->getMessage());
    }
});

add_action('wp_ajax_b2b_reject_user', function() {
    try {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'b2b-commerce'));
            return;
        }
        
        if (!isset($_POST['user_id']) || !isset($_POST['nonce'])) {
            wp_send_json_error(__('Missing required parameters', 'b2b-commerce'));
            return;
        }
        
        $user_id = intval( sanitize_text_field( wp_unslash( $_POST['user_id'] ) ) );
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        
        if (!wp_verify_nonce($nonce, 'b2b_reject_user_' . $user_id) && !wp_verify_nonce($nonce, 'b2b_reject_user_nonce')) {
            wp_send_json_error(__('Security check failed', 'b2b-commerce'));
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(__('User not found', 'b2b-commerce'));
            return;
        }
        
        update_user_meta($user_id, 'b2b_approval_status', 'rejected');
        
        // Send email notification using custom template
        $user = get_userdata($user_id);
        $templates = get_option('b2b_email_templates', []);
        
        $subject = $templates['user_rejection_subject'] ?? __('B2B Account Application Status', 'b2b-commerce');
        $message = $templates['user_rejection_message'] ?? __('We regret to inform you that your B2B account application has been rejected. Please contact us for more information.', 'b2b-commerce');
        
        // Replace variables with proper escaping
        $subject = str_replace(['{user_name}', '{site_name}'], 
            [esc_html($user->display_name), esc_html(get_bloginfo('name'))], $subject);
        $message = str_replace(['{user_name}', '{site_name}'], 
            [esc_html($user->display_name), esc_html(get_bloginfo('name'))], $message);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
        
        wp_send_json_success(__('User rejected successfully', 'b2b-commerce'));
        
    } catch (Exception $e) {
        wp_send_json_error(__('Error: ', 'b2b-commerce') . $e->getMessage());
    }
});



add_action('wp_ajax_b2b_export_data', function() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
    }
    
    $type = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    
    if (!wp_verify_nonce($nonce, 'b2b_ajax_nonce')) {
        wp_send_json_error(__('Security check failed', 'b2b-commerce'));
    }
    
    switch ($type) {
        case 'users':
            $users = get_users(['role__in' => ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']]);
            $csv_data = __("User,Email,Role,Company,Approval Status", 'b2b-commerce') . "\n";
            
            if (empty($users)) {
                $csv_data .= __("No B2B users found", 'b2b-commerce') . "\n";
            } else {
                foreach ($users as $user) {
                    $csv_data .= sprintf(
                        "%s,%s,%s,%s,%s\n",
                        $user->user_login,
                        $user->user_email,
                        implode(',', $user->roles),
                        get_user_meta($user->ID, 'company_name', true),
                        get_user_meta($user->ID, 'b2b_approval_status', true) ?: 'pending'
                    );
                }
            }
            break;
            
        case 'pricing':
            global $wpdb;
            $table = $wpdb->prefix . 'b2b_pricing_rules';
            
            // Check cache first
            $cache_key = 'b2b_pricing_rules_export';
            $rules = wp_cache_get($cache_key, 'b2b_commerce');
            
            if (false === $rules) {
                $rules = $wpdb->get_results( "SELECT * FROM `{$table}`" );
                wp_cache_set($cache_key, $rules, 'b2b_commerce', HOUR_IN_SECONDS);
            }
            
            $csv_data = __("Customer Type,Pricing Type,Value,Min Quantity", 'b2b-commerce') . "\n";
            
            if (empty($rules)) {
                $csv_data .= __("No pricing rules found", 'b2b-commerce') . "\n";
            } else {
                foreach ($rules as $rule) {
                    $csv_data .= sprintf(
                        "%s,%s,%s,%s\n",
                        $rule->role,
                        $rule->type,
                        $rule->price,
                        $rule->min_qty
                    );
                }
            }
            break;
            
        case 'orders':
            if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
                wp_send_json_error(__('WooCommerce is required for order export', 'b2b-commerce'));
                return;
            }
            
            $orders = wc_get_orders(['limit' => -1]);
            $csv_data = __("Order ID,Date,Status,Customer,Total,Payment Method", 'b2b-commerce') . "\n";
            
            if (empty($orders)) {
                $csv_data .= __("No orders found", 'b2b-commerce') . "\n";
            } else {
                foreach ($orders as $order) {
                    $customer = $order->get_customer_id() ? get_userdata($order->get_customer_id()) : null;
                    $customer_name = $customer ? $customer->display_name : 'Guest';
                    
                    $csv_data .= sprintf(
                        "%s,%s,%s,%s,%s,%s\n",
                        $order->get_id(),
                        $order->get_date_created()->date('Y-m-d H:i:s'),
                        $order->get_status(),
                        $customer_name,
                        $order->get_total(),
                        $order->get_payment_method_title()
                    );
                }
            }
            break;
            
        default:
            wp_send_json_error(__('Invalid export type', 'b2b-commerce'));
    }
    
    wp_send_json_success($csv_data);
});

// Import/Export AJAX handlers
add_action('wp_ajax_b2b_download_template', function() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
    }
    
    $type = sanitize_text_field( wp_unslash( $_GET['type'] ?? '' ) );
    $nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
    
    if (!wp_verify_nonce($nonce, 'b2b_template_nonce')) {
        wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
    }
    
    switch ($type) {
        case 'users':
            $csv_data = __("Username,Email,First Name,Last Name,Company Name,Business Type,Phone,Role,Approval Status", 'b2b-commerce') . "\n";
            $csv_data .= "john_doe,john@example.com,John,Doe,ABC Company,Retail,555-0123,wholesale_customer,approved\n";
            break;
            
        case 'pricing':
            $csv_data = __("Role,Type,Price,Min Quantity,Max Quantity,Product ID", 'b2b-commerce') . "\n";
            $csv_data .= "wholesale_customer,percentage,10,10,0,0\n";
            $csv_data .= "distributor,fixed,5,50,0,0\n";
            break;
            
        default:
            wp_die(esc_html__('Invalid template type.', 'b2b-commerce'));
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="b2b_' . esc_attr($type) . '_template.csv"');
    echo esc_html($csv_data);
    exit;
});

// Demo data import handler
add_action('wp_ajax_b2b_import_demo_data', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized', 'b2b-commerce'));
        return;
    }
    
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if (!wp_verify_nonce($nonce, 'b2b_import_demo')) {
        wp_send_json_error(__('Security check failed', 'b2b-commerce'));
        return;
    }
    
    try {
        // Create demo B2B users
        $demo_users = [
            [
                'user_login' => 'wholesale_demo',
                'user_email' => 'wholesale@demo.com',
                'user_pass' => 'demo123',
                'first_name' => 'John',
                'last_name' => 'Wholesale',
                'role' => 'wholesale_customer',
                'company' => __('Demo Wholesale Co.', 'b2b-commerce'),
                'phone' => '555-0101'
            ],
            [
                'user_login' => 'distributor_demo',
                'user_email' => 'distributor@demo.com',
                'user_pass' => 'demo123',
                'first_name' => 'Jane',
                'last_name' => 'Distributor',
                'role' => 'distributor',
                'company' => __('Demo Distributor Inc.', 'b2b-commerce'),
                'phone' => '555-0102'
            ],
            [
                'user_login' => 'retailer_demo',
                'user_email' => 'retailer@demo.com',
                'user_pass' => 'demo123',
                'first_name' => 'Mike',
                'last_name' => 'Retailer',
                'role' => 'retailer',
                'company' => __('Demo Retail Store', 'b2b-commerce'),
                'phone' => '555-0103'
            ]
        ];
        
        foreach ($demo_users as $user_data) {
            $user_id = wp_create_user($user_data['user_login'], $user_data['user_pass'], $user_data['user_email']);
            if (!is_wp_error($user_id)) {
                wp_update_user([
                    'ID' => $user_id,
                    'first_name' => $user_data['first_name'],
                    'last_name' => $user_data['last_name'],
                    'role' => $user_data['role']
                ]);
                
                update_user_meta($user_id, 'company_name', $user_data['company']);
                update_user_meta($user_id, 'phone', $user_data['phone']);
                update_user_meta($user_id, 'b2b_approval_status', 'approved');
            }
        }
        
        // Create demo pricing rules with more flexible minimums
        global $wpdb;
        $table = $wpdb->prefix . 'b2b_pricing_rules';
        
        // Check if table exists with caching
        $cache_key = 'b2b_table_exists_' . md5($table);
        $table_exists = wp_cache_get($cache_key, 'b2b_commerce');
        
        if (false === $table_exists) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
            wp_cache_set($cache_key, $table_exists, 'b2b_commerce', HOUR_IN_SECONDS);
        }
        
        if ($table_exists === $table) {
            $demo_rules = [
                [
                    'role' => 'wholesale_customer',
                    'type' => 'percentage',
                    'price' => 15,
                    'min_qty' => 5  // Reduced from 10 to 5
                ],
                [
                    'role' => 'distributor',
                    'type' => 'percentage',
                    'price' => 25,
                    'min_qty' => 20  // Reduced from 50 to 20
                ],
                [
                    'role' => 'retailer',
                    'type' => 'fixed',
                    'price' => 5,
                    'min_qty' => 1   // Reduced from 5 to 1
                ]
            ];
            
            foreach ($demo_rules as $rule) {
                $result = $wpdb->insert($table, [
                    'product_id' => 0,
                    'role' => $rule['role'],
                    'user_id' => 0,
                    'group_id' => 0,
                    'geo_zone' => '',
                    'start_date' => '',
                    'end_date' => '',
                    'min_qty' => $rule['min_qty'],
                    'max_qty' => 0,
                    'price' => $rule['price'],
                    'type' => $rule['type']
                ]);
                
                // Clear cache after each insert
                if ($result !== false) {
                    wp_cache_delete('b2b_pricing_rules', 'b2b_commerce');
                    wp_cache_delete('b2b_pricing_rules_export', 'b2b_commerce');
                }
            }
        }
        
        // translators: %1$d is the number of users created, %2$d is the number of pricing rules created
        wp_send_json_success(sprintf(__('Demo data imported successfully! Created %1$d users and %2$d pricing rules.', 'b2b-commerce'), count($demo_users), count($demo_rules)));
        
    } catch (Exception $e) {
        // translators: %s is the error message
        wp_send_json_error(sprintf(__('Error importing demo data: %s', 'b2b-commerce'), $e->getMessage()));
    }
});

// Enqueue modern admin CSS and JS for all B2B Commerce admin pages
add_action('admin_enqueue_scripts', function($hook) {
    // Verify nonce for admin page access
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
    if ($page && strpos($page, 'b2b-') === 0) {
        wp_enqueue_style(
            'b2b-admin-standalone-demo',
            B2B_COMMERCE_URL . 'assets/css/b2b-admin-standalone-demo.css',
            [],
            B2B_COMMERCE_VERSION
        );
        
        wp_enqueue_script(
            'b2b-commerce-admin',
            B2B_COMMERCE_URL . 'assets/js/b2b-commerce.js',
            ['jquery'],
            B2B_COMMERCE_VERSION,
            true
        );
        
        // Localize script for AJAX with proper nonce and translated strings
        wp_localize_script('b2b-commerce-admin', 'b2b_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('b2b_ajax_nonce'),
            'approve_nonce' => wp_create_nonce('b2b_approve_user_nonce'),
            'reject_nonce' => wp_create_nonce('b2b_reject_user_nonce'),
            'pricing_nonce' => wp_create_nonce('b2b_pricing_nonce'),
            'strings' => array(
                'confirm_approve_user' => esc_js(__('Are you sure you want to approve this user?', 'b2b-commerce')),
                'confirm_reject_user' => esc_js(__('Are you sure you want to reject this user?', 'b2b-commerce')),
                'confirm_delete_pricing' => esc_js(__('Are you sure you want to delete this pricing rule?', 'b2b-commerce')),
                'apply' => esc_js(__('Apply', 'b2b-commerce')),
                'search' => esc_js(__('Search:', 'b2b-commerce')),
                'show_entries' => esc_js(__('Show _MENU_ entries', 'b2b-commerce')),
                'showing_entries' => esc_js(__('Showing _START_ to _END_ of _TOTAL_ entries', 'b2b-commerce')),
                'showing_empty' => esc_js(__('Showing 0 to 0 of 0 entries', 'b2b-commerce')),
                'filtered_entries' => esc_js(__('(filtered from _MAX_ total entries)', 'b2b-commerce')),
                'unknown_error' => esc_js(__('Unknown error', 'b2b-commerce')),
                'request_failed' => esc_js(__('Request failed', 'b2b-commerce')),
                'error' => esc_js(__('Error', 'b2b-commerce')),
                'error_occurred' => esc_js(__('An error occurred. Please try again.', 'b2b-commerce'))
            )
        ));
    }
});

// Also localize for frontend
add_action('wp_enqueue_scripts', function() {
    wp_localize_script('jquery', 'b2b_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('b2b_ajax_nonce')
    ));
}); 


// Add admin notice about created pages
add_action('admin_notices', function() {
    if (get_option('b2b_pages_created_notice')) {
        $registration_page_id = get_option('b2b_registration_page_id');
        $dashboard_page_id = get_option('b2b_dashboard_page_id');
        $account_page_id = get_option('b2b_account_page_id');
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<h3>🎉 ' . esc_html__('B2B Commerce - Pages Created Successfully!', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html__('The following B2B pages have been automatically created:', 'b2b-commerce') . '</p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        
        if ($registration_page_id) {
            echo '<li><strong>' . esc_html__('B2B Registration:', 'b2b-commerce') . '</strong> <a href="' . esc_url(get_edit_post_link($registration_page_id)) . '">' . esc_html__('Edit Page', 'b2b-commerce') . '</a> | <a href="' . esc_url(get_permalink($registration_page_id)) . '" target="_blank">' . esc_html__('View Page', 'b2b-commerce') . '</a></li>';
        }
        
        if ($dashboard_page_id) {
            echo '<li><strong>' . esc_html__('B2B Dashboard:', 'b2b-commerce') . '</strong> <a href="' . esc_url(get_edit_post_link($dashboard_page_id)) . '">' . esc_html__('Edit Page', 'b2b-commerce') . '</a> | <a href="' . esc_url(get_permalink($dashboard_page_id)) . '" target="_blank">' . esc_html__('View Page', 'b2b-commerce') . '</a></li>';
        }
        
        if ($account_page_id) {
            echo '<li><strong>' . esc_html__('Account Settings:', 'b2b-commerce') . '</strong> <a href="' . esc_url(get_edit_post_link($account_page_id)) . '">' . esc_html__('Edit Page', 'b2b-commerce') . '</a> | <a href="' . esc_url(get_permalink($account_page_id)) . '" target="_blank">' . esc_html__('View Page', 'b2b-commerce') . '</a></li>';
        }
        
        echo '</ul>';
        echo '<p><strong>' . esc_html__('Next Steps:', 'b2b-commerce') . '</strong></p>';
        echo '<ol style="list-style: decimal; margin-left: 20px;">';
        echo '<li>✅ ' . esc_html__('"B2B Registration" has been automatically added to your main navigation menu', 'b2b-commerce') . '</li>';
        echo '<li>' . esc_html__('Add "B2B Dashboard" to your user menu (after login)', 'b2b-commerce') . '</li>';
        // translators: %s is the link to B2B Commerce Settings
        echo '<li>' . sprintf(esc_html__('Configure B2B settings in %s', 'b2b-commerce'), '<a href="' . esc_url(admin_url('admin.php?page=b2b-commerce')) . '">' . esc_html__('B2B Commerce Settings', 'b2b-commerce') . '</a>') . '</li>';
        echo '<li>' . esc_html__('Upgrade to B2B Commerce for advanced features like quotes, product inquiries, and bulk calculator', 'b2b-commerce') . '</li>';
        echo '</ol>';
        echo '<p><em>' . esc_html__('All pages are ready to use with the appropriate shortcodes already added!', 'b2b-commerce') . '</em></p>';
        echo '</div>';
        
        // Remove the notice flag
        delete_option('b2b_pages_created_notice');
    }
});
