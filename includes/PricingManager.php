<?php
namespace B2B;

if ( ! defined( 'ABSPATH' ) ) exit;

class PricingManager {
    const PRICING_TABLE_NAME = 'b2b_pricing_rules';
    const B2B_META_PREFIX = '_b2b_';
    const QUANTITY_SETTINGS_OPTION = 'b2b_quantity_settings';
    const PRICING_TABLE_ERROR_OPTION = 'b2b_pricing_table_error';
    const ADMIN_PAGE_SLUG = 'b2b-pricing';
    const B2B_USER_GROUP_TAXONOMY = 'b2b_user_group';
    const SAVE_PRICING_RULE_NONCE = 'b2b_save_pricing_rule';
    const DELETE_PRICING_RULE_NONCE = 'b2b_delete_pricing_rule';
    const PRICE_REQUEST_NONCE = 'b2b_price_request';
    const AJAX_NONCE = 'b2b_ajax_nonce';
    
    public function __construct() {
        // Create pricing table on activation
        register_activation_hook( B2B_COMMERCE_BASENAME, [ __CLASS__, 'create_pricing_table' ] );
        // Self-healing: check and create table if missing
        add_action( 'init', [ $this, 'maybe_create_pricing_table' ] );
        add_action( 'init', [ $this, 'check_pricing_table' ] );
        add_action( 'admin_notices', [ $this, 'admin_notice_table_error' ] );
        // Commented out to avoid duplicate menu - using AdminPanel.php instead

        add_filter( 'woocommerce_product_get_price', [ $this, 'apply_pricing_rules' ], 5, 2 );
        add_filter( 'woocommerce_product_get_sale_price', [ $this, 'apply_pricing_rules' ], 5, 2 );
        add_filter( 'woocommerce_get_price_html', [ $this, 'apply_pricing_to_price_html' ], 5, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'enforce_min_max_quantity' ] );
        add_action( 'woocommerce_cart_updated', [ $this, 'clear_notice_flag' ] );
        add_action( 'woocommerce_add_to_cart', [ $this, 'clear_notice_flag' ] );
        add_action( 'woocommerce_remove_cart_item', [ $this, 'clear_notice_flag' ] );
        add_action( 'admin_post_' . self::SAVE_PRICING_RULE_NONCE, [ $this, 'save_pricing_rule' ] );
        add_action( 'admin_post_' . self::DELETE_PRICING_RULE_NONCE, [ $this, 'delete_pricing_rule' ] );
        
        // AJAX handlers with proper security
        add_action( 'wp_ajax_b2b_get_pricing_rules', [ $this, 'ajax_get_pricing_rules' ] );
        add_action( 'wp_ajax_b2b_bulk_operations', [ $this, 'ajax_bulk_operations' ] );
        
        // Scripts are now handled by the main plugin file
        // Enqueue B2B assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_b2b_assets' ] );
        // Price request system - REMOVED: Duplicate of AdvancedFeatures quote_request_button

        // Display pricing widgets on product page
        add_action( 'woocommerce_single_product_summary', [ $this, 'render_pricing_widgets' ], 28 );
    }

    // Create custom table for pricing rules
    public static function create_pricing_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        
        // Get charset collate with fallback
        if (method_exists($wpdb, 'get_charset_collate')) {
            $charset_collate = $wpdb->get_charset_collate();
        } else {
            $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            role VARCHAR(64) DEFAULT '',
            user_id BIGINT UNSIGNED DEFAULT 0,
            group_id BIGINT UNSIGNED DEFAULT 0,
            geo_zone VARCHAR(64) DEFAULT '',
            start_date DATE NULL,
            end_date DATE NULL,
            min_qty INT DEFAULT 1,
            max_qty INT DEFAULT 0,
            price DECIMAL(20,6) NOT NULL,
            type VARCHAR(32) DEFAULT 'percentage',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY role (role),
            KEY user_id (user_id),
            KEY min_qty (min_qty)
        ) $charset_collate;";
        
        // Include upgrade.php if available, otherwise use fallback
        if (file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        } else {
            // Fallback: try direct execution
            $wpdb->query($sql);
            error_log(__('B2B Commerce: Using fallback table creation', 'b2b-commerce'));
        }
        

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) {
        // translators: %s is the table name that failed to be created
        error_log(sprintf(__('B2B Commerce: Failed to create pricing table: %s', 'b2b-commerce'), esc_html($table)));
            return false;
        }
        
        return true;
    }

    // Self-healing: check and create table if missing
    public function maybe_create_pricing_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists != $table ) {
            $result = self::create_pricing_table();
            if ( !$result ) {
                update_option( self::PRICING_TABLE_ERROR_OPTION, 1 );
                error_log(__('B2B Commerce: Failed to create pricing table during self-healing', 'b2b-commerce'));
            } else {
                delete_option( self::PRICING_TABLE_ERROR_OPTION );
                error_log(__('B2B Commerce: Successfully created pricing table during self-healing', 'b2b-commerce'));
            }
        } else {
                delete_option( self::PRICING_TABLE_ERROR_OPTION );
        }
    }

    // Check if pricing table exists and has data
    public function check_pricing_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        

        if ($exists != $table) {
            error_log(__('B2B Pricing: Table does not exist', 'b2b-commerce'));
            return false;
        }
        
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `" . $wpdb->_escape($table) . "`");
        // translators: %d is the number of pricing rules in the table
        error_log(sprintf(__('B2B Pricing: Table exists with %d rules', 'b2b-commerce'), intval($count)));
        return true;
    }

    public function admin_notice_table_error() {
        if ( get_option( self::PRICING_TABLE_ERROR_OPTION ) ) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('B2B Commerce:', 'b2b-commerce') . '</strong> ' . esc_html__('Could not create the pricing rules table. Please check your database permissions or contact your host.', 'b2b-commerce') . '</p></div>';
        }
    }

    // Check for product-level B2B pricing (set in product edit page)
    public function get_product_level_b2b_price($product_id, $user_roles) {
        $b2b_roles = apply_filters('b2b_commerce_pro_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        
        foreach ($user_roles as $role) {
            if (in_array($role, $b2b_roles)) {
                // Check for sale price first
                $sale_price = get_post_meta($product_id, self::B2B_META_PREFIX . $role . '_sale_price', true);
                if (!empty($sale_price) && $sale_price > 0) {
                    return floatval($sale_price);
                }
                
                // Check for regular price
                $regular_price = get_post_meta($product_id, self::B2B_META_PREFIX . $role . '_regular_price', true);
                if (!empty($regular_price) && $regular_price > 0) {
                    return floatval($regular_price);
                }
            }
        }
        
        return false; // No product-level pricing found
    }
    
    // Get B2B regular price for display purposes
    public function get_b2b_regular_price($product_id, $user_roles) {
        $b2b_roles = apply_filters('b2b_commerce_pro_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        
        foreach ($user_roles as $role) {
            if (in_array($role, $b2b_roles)) {
                // Get regular price for this role
                $regular_price = get_post_meta($product_id, self::B2B_META_PREFIX . $role . '_regular_price', true);
                if (!empty($regular_price) && $regular_price > 0) {
                    return floatval($regular_price);
                }
            }
        }
        
        return false; // No B2B regular price found
    }
    
    // Get B2B sale price for display purposes
    public function get_b2b_sale_price($product_id, $user_roles) {
        $b2b_roles = apply_filters('b2b_commerce_pro_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        
        foreach ($user_roles as $role) {
            if (in_array($role, $b2b_roles)) {
                // Get sale price for this role
                $sale_price = get_post_meta($product_id, self::B2B_META_PREFIX . $role . '_sale_price', true);
                if (!empty($sale_price) && $sale_price > 0) {
                    return floatval($sale_price);
                }
            }
        }
        
        return false; // No B2B sale price found
    }

    // Apply pricing rules to WooCommerce product price
    public function apply_pricing_rules( $price, $product ) {
        if ( ! is_user_logged_in() ) return $price;
        
        // Validate user permissions
        if ( ! $this->validate_user_permissions('read') ) {
            return $price;
        }
        
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $roles = $user->roles;
        $product_id = $product->get_id();
        
        // Validate product ID
        if ( ! $product_id || $product_id <= 0 ) {
            return $price;
        }
        
        // First check for product-level B2B pricing (set in product edit page)
        $product_level_price = $this->get_product_level_b2b_price($product_id, $roles);
        if ($product_level_price !== false) {
            return $product_level_price;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        
        // Query product-specific rules AND global rules (product_id = 0)
        // Global rules let the admin define role-based pricing that applies to every product
        $rules = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d OR product_id = 0",
                $product_id
            )
        );
        
        if (empty($rules)) {
            return $price; // No rules found, return original price
        }
        
        $best_price = $price;
        $matched_rule = null;
        
        foreach ( $rules as $rule ) {
            $rule_matches = true;
            
            // Check role
            if ( $rule->role && ! empty($rule->role) && ! in_array( $rule->role, $roles ) ) {
                $rule_matches = false;
            }
            
            // Check user
            if ( $rule->user_id && $rule->user_id != $user_id ) {
                $rule_matches = false;
            }
            
            // Check group
            if ( $rule->group_id ) {
                $user_groups = wp_get_object_terms( $user_id, self::B2B_USER_GROUP_TAXONOMY, [ 'fields' => 'ids' ] );
                if ( ! in_array( $rule->group_id, $user_groups ) ) {
                    $rule_matches = false;
                }
            }
            
            // Check time
            $now = date( 'Y-m-d' );
            if ( $rule->start_date && $now < $rule->start_date ) {
                $rule_matches = false;
            }
            if ( $rule->end_date && $now > $rule->end_date ) {
                $rule_matches = false;
            }
            
            // If rule matches, apply it
            if ( $rule_matches ) {
                $rule_price = floatval($rule->price);

                // Respect minimum quantity: on price display we only know quantity = 1
                // So ignore rules that require a higher minimum
                if ( isset($rule->min_qty) && intval($rule->min_qty) > 1 ) {
                    continue;
                }

                // Handle percentage discounts (treat stored value as discount percent regardless of sign)
                if ($rule->type === 'percentage') {
                    $discount_percentage = abs($rule_price);
                    $rule_price = $price * (1 - ($discount_percentage / 100));
                }
                
                // Use this rule if it's better than current best
                if ( $rule_price < $best_price ) {
                    $best_price = $rule_price;
                    $matched_rule = $rule;
                }
            }
        }
        
        // Debug logging
        if ($matched_rule) {
            error_log("B2B Pricing: Product " . esc_html($product_id) . ", User " . esc_html($user_id) . ", Original: " . esc_html($price) . ", New: " . esc_html($best_price) . ", Rule ID: " . esc_html($matched_rule->id));
        }
        
        return $best_price;
    }

    // Apply pricing rules to price HTML display
    public function apply_pricing_to_price_html( $price_html, $product ) {
        if ( ! is_user_logged_in() ) return $price_html;
        
        // Validate user permissions
        if ( ! $this->validate_user_permissions('read') ) {
            return $price_html;
        }
        
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $roles = $user->roles;
        $product_id = $product->get_id();
        
        // Validate product ID
        if ( ! $product_id || $product_id <= 0 ) {
            return $price_html;
        }
        
        // First check for product-level B2B pricing (set in product edit page)
        $product_level_price = $this->get_product_level_b2b_price($product_id, $roles);
        if ($product_level_price !== false) {
            // Get the B2B regular price for this role to show as strikethrough
            $b2b_regular_price = $this->get_b2b_regular_price($product_id, $roles);
            
            // Check if there's a B2B sale price (different from regular price)
            $b2b_sale_price = $this->get_b2b_sale_price($product_id, $roles);
            
            $new_price_html = '<span class="price">';
            
            if ($b2b_sale_price && $b2b_sale_price < $b2b_regular_price) {
                // There's a sale price - show regular price as strikethrough, sale price as active
            $new_price_html .= '<del><span class="woocommerce-Price-amount amount">' . wp_kses_post(wc_price($b2b_regular_price)) . '</span></del> ';
            $new_price_html .= '<ins><span class="woocommerce-Price-amount amount">' . wp_kses_post(wc_price($b2b_sale_price)) . '</span></ins>';
            } else {
                // No sale price - just show the B2B price without strikethrough
                $new_price_html .= '<span class="woocommerce-Price-amount amount">' . wp_kses_post(wc_price($product_level_price)) . '</span>';
            }
            
            $new_price_html .= '</span>';
            return $new_price_html;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        
        // Query product-specific rules AND global rules (product_id = 0)
        $rules = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `" . $wpdb->_escape($table) . "` WHERE product_id = %d OR product_id = 0",
                $product_id
            )
        );
        
        if (empty($rules)) {
            return $price_html; // No rules found, return original price
        }
        
        $original_price = (float) $product->get_price();
        $best_price = $original_price;
        $matched_rule = null;
        
        foreach ( $rules as $rule ) {
            $rule_matches = true;
            
            // Check role
            if ( $rule->role && ! empty($rule->role) && ! in_array( $rule->role, $roles ) ) {
                $rule_matches = false;
            }
            
            // Check user
            if ( $rule->user_id && $rule->user_id != $user_id ) {
                $rule_matches = false;
            }
            
            // Check group
            if ( $rule->group_id ) {
                $user_groups = wp_get_object_terms( $user_id, self::B2B_USER_GROUP_TAXONOMY, [ 'fields' => 'ids' ] );
                if ( ! in_array( $rule->group_id, $user_groups ) ) {
                    $rule_matches = false;
                }
            }
            
            // Check time
            $now = date( 'Y-m-d' );
            if ( $rule->start_date && $now < $rule->start_date ) {
                $rule_matches = false;
            }
            if ( $rule->end_date && $now > $rule->end_date ) {
                $rule_matches = false;
            }
            
    
            // Higher quantity discounts will be shown in bulk calculator
            if ( isset($rule->min_qty) && intval($rule->min_qty) > 1 ) {
                continue;
            }
            
            // If rule matches, apply it
            if ( $rule_matches ) {
                $rule_price = floatval($rule->price);

                // Handle percentage discounts
                if ($rule->type === 'percentage') {
                    $discount_percentage = abs($rule_price);
                    $rule_price = $original_price * (1 - ($discount_percentage / 100));
                }
                
                // Use this rule if it's better than current best
                if ( $rule_price < $best_price ) {
                    $best_price = $rule_price;
                    $matched_rule = $rule;
                }
            }
        }
        

        

        if ( $matched_rule && $best_price < $original_price ) {
            $discount_percentage = 0;
            if ( $matched_rule->type === 'percentage' ) {
                $discount_percentage = abs((float) $matched_rule->price);
            }
            
            // Create new price HTML with discount
            $new_price_html = '<span class="price">';
            $new_price_html .= '<del><span class="woocommerce-Price-amount amount">' . wp_kses_post(wc_price($original_price)) . '</span></del> ';
            $new_price_html .= '<ins><span class="woocommerce-Price-amount amount">' . wp_kses_post(wc_price($best_price)) . '</span></ins>';
            if ( $discount_percentage > 0 ) {
                $new_price_html .= ' <span class="b2b-discount-badge">(' . esc_html($discount_percentage) . '% ' . esc_html__('off', 'b2b-commerce') . ')</span>';
            }
            $new_price_html .= '</span>';
            
            return $new_price_html;
        }
        
        return $price_html;
    }

    // Enforce min/max quantity in cart
    public function enforce_min_max_quantity( $cart ) {
        if ( is_admin() ) return;
        
        // Validate user permissions
        if ( ! $this->validate_user_permissions('read') ) {
            return;
        }
        
        // Prevent duplicate notices by using a session flag
        if (WC()->session && WC()->session->get('b2b_quantity_notices_processed')) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;

        $user = wp_get_current_user();
        $roles = (array) ($user->roles ?? []);
        $user_id = (int) ($user->ID ?? 0);

        // Collect all notices to avoid duplicates
        $notices = [];
        $processed_products = [];

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product    = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $quantity   = (int) $cart_item['quantity'];

            // Pull both product-specific and global rules
            $rules = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE product_id = %d OR product_id = 0",
                    $product_id
                )
            );

            // Enforce min/max and determine best price for current quantity
            $original_price = $product->get_regular_price();
            if ($original_price === '') {
                $original_price = $product->get_price();
            }
            $original_price = (float) $original_price;
            $best_price = $original_price;

            foreach ( $rules as $rule ) {
                // Get quantity settings
                $quantity_settings = get_option(self::QUANTITY_SETTINGS_OPTION, ['enforce_min_qty' => 1, 'min_qty_behavior' => 'warning']);
                $enforce_min_qty = $quantity_settings['enforce_min_qty'] ?? 1;
                $min_qty_behavior = $quantity_settings['min_qty_behavior'] ?? 'warning';
                
                // Enforce min/max messages based on settings
                if ( $rule->min_qty && $quantity < $rule->min_qty ) {
                    $notice_key = 'min_qty_' . $rule->role . '_' . $rule->min_qty;
                    if (!in_array($notice_key, $processed_products)) {
                        $processed_products[] = $notice_key;
                        
                        if ($enforce_min_qty && $min_qty_behavior === 'error') {
                            // translators: %d is the minimum quantity required for wholesale pricing
                            $notices[] = sprintf(__('Minimum quantity for wholesale pricing is %d items.', 'b2b-commerce'), $rule->min_qty);
                        } elseif ($enforce_min_qty && $min_qty_behavior === 'warning') {
                            // translators: %d is the minimum quantity required for wholesale pricing
                            $notices[] = sprintf(__('Note: Minimum quantity for wholesale pricing is %d items. You may not receive wholesale pricing for quantities below this threshold.', 'b2b-commerce'), $rule->min_qty);
                        }
                        // If behavior is 'ignore', don't add any notice
                    }
                }
                if ( $rule->max_qty && $quantity > $rule->max_qty ) {
                    // translators: %d is the maximum quantity allowed for this product
                    $notices[] = sprintf(__('Maximum quantity for this product is %d items.', 'b2b-commerce'), $rule->max_qty);
                }

                // Check matching conditions
                $matches = true;
                if ( $rule->role && !in_array( $rule->role, $roles, true ) ) {
                    $matches = false;
                }
                if ( $rule->user_id && (int)$rule->user_id !== $user_id ) {
                    $matches = false;
                }
                // Check quantity settings for pricing rule matching
                $quantity_settings = get_option(self::QUANTITY_SETTINGS_OPTION, ['enforce_min_qty' => 1, 'min_qty_behavior' => 'warning']);
                $enforce_min_qty = $quantity_settings['enforce_min_qty'] ?? 1;
                
                if ( $enforce_min_qty && $rule->min_qty && $quantity < (int)$rule->min_qty ) {
                    $matches = false;
                }
                $now = date('Y-m-d');
                if ( $rule->start_date && $now < $rule->start_date ) { $matches = false; }
                if ( $rule->end_date && $now > $rule->end_date ) { $matches = false; }
                if ( ! $matches ) { continue; }

                $candidate = (float)$rule->price;
                if ( $rule->type === 'percentage' ) {
                    $discount_percentage = abs( $candidate );
                    $candidate = $original_price * ( 1 - ( $discount_percentage / 100 ) );
                }
                if ( $candidate < $best_price ) {
                    $best_price = max( 0, $candidate );
                }
            }

            // Apply best price for this cart item and quantity
            if ( $best_price !== $original_price ) {
                $product->set_price( $best_price );
            }
        }
        
        // Display collected notices (only once)
        if (!empty($notices)) {
            foreach ($notices as $notice) {
                wc_add_notice($notice, 'notice');
            }
        }
        
        // Set session flag to prevent duplicate processing
        if (WC()->session) {
            WC()->session->set('b2b_quantity_notices_processed', true);
        }
    }
    
    // Clear notice flag when cart is updated
    public function clear_notice_flag() {
        if (WC()->session) {
            WC()->session->__unset('b2b_quantity_notices_processed');
        }
    }

    // Save pricing rule (add/edit)
    public function save_pricing_rule() {
        // Check if this is a POST request
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(esc_html__('Invalid request method.', 'b2b-commerce'));
        }
        
        // Sanitize all POST data before validation
        $sanitized_post = array_map('sanitize_text_field', $_POST);
        
        // Verify nonce and user permissions
        if (!current_user_can('manage_woocommerce') || 
            !isset($sanitized_post['b2b_nonce']) || 
            !wp_verify_nonce($sanitized_post['b2b_nonce'], self::SAVE_PRICING_RULE_NONCE)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        
        // Validate required fields
        $required_fields = ['product_id', 'role', 'price', 'type'];
        foreach ($required_fields as $field) {
            if (!isset($sanitized_post[$field]) || empty($sanitized_post[$field])) {
                wp_die(sprintf(__('Required field %s is missing.', 'b2b-commerce'), esc_html($field)));
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        
        // Sanitize and validate all input data using helper method
        $validation_result = $this->sanitize_pricing_rule_data($sanitized_post);
        
        if (is_wp_error($validation_result)) {
            $error_messages = $validation_result->get_error_messages();
            wp_die(implode('<br>', array_map('esc_html', $error_messages)));
        }
        
        $data = $validation_result;
        
        // Add additional fields that aren't validated by the helper
        $data['group_id'] = max(0, intval($sanitized_post['group_id'] ?? 0));
        $data['geo_zone'] = sanitize_text_field($sanitized_post['geo_zone'] ?? '');
        $data['start_date'] = $this->sanitize_date($sanitized_post['start_date'] ?? '');
        $data['end_date'] = $this->sanitize_date($sanitized_post['end_date'] ?? '');
        
        // Validate date range if both dates are provided
        if (!empty($data['start_date']) && !empty($data['end_date']) && $data['start_date'] > $data['end_date']) {
            wp_die(esc_html__('Start date cannot be after end date.', 'b2b-commerce'));
        }
        
        // Perform database operation
        if (!empty($sanitized_post['id']) && is_numeric($sanitized_post['id'])) {
            $rule_id = intval($sanitized_post['id']);
            // Verify the rule exists and user has permission to edit it
            $existing_rule = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $rule_id
            ));
            
            if (!$existing_rule) {
                wp_die(esc_html__('Pricing rule not found.', 'b2b-commerce'));
            }
            
            $result = $wpdb->update($table, $data, ['id' => $rule_id]);
            if ($result === false) {
                wp_die(esc_html__('Failed to update pricing rule.', 'b2b-commerce'));
            }
        } else {
            $result = $wpdb->insert($table, $data);
            if ($result === false) {
                wp_die(esc_html__('Failed to create pricing rule.', 'b2b-commerce'));
            }
        }
        
        wp_redirect(admin_url('admin.php?page=' . self::ADMIN_PAGE_SLUG . '&updated=1'));
        exit;
    }

    // Delete pricing rule
    public function delete_pricing_rule() {
        // Check if this is a GET request
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
            wp_die(esc_html__('Invalid request method.', 'b2b-commerce'));
        }
        
        // Sanitize all GET data before validation
        $sanitized_get = array_map('sanitize_text_field', $_GET);
        
        // Verify nonce and user permissions
        if (!current_user_can('manage_woocommerce') || 
            !isset($sanitized_get['_wpnonce']) || 
            !wp_verify_nonce($sanitized_get['_wpnonce'], self::DELETE_PRICING_RULE_NONCE)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        
        // Validate rule ID
        if (!isset($sanitized_get['id']) || !is_numeric($sanitized_get['id'])) {
            wp_die(esc_html__('Invalid pricing rule ID.', 'b2b-commerce'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $rule_id = intval($sanitized_get['id']);
        
        // Verify the rule exists before attempting to delete
        $existing_rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $rule_id
        ));
        
        if (!$existing_rule) {
            wp_die(esc_html__('Pricing rule not found.', 'b2b-commerce'));
        }
        
        $result = $wpdb->delete($table, ['id' => $rule_id]);
        if ($result === false) {
            wp_die(esc_html__('Failed to delete pricing rule.', 'b2b-commerce'));
        }
        
        wp_redirect(admin_url('admin.php?page=' . self::ADMIN_PAGE_SLUG . '&deleted=1'));
        exit;
    }

    // Scripts are now handled by the main plugin file
    
    // Enqueue B2B styles and scripts
    public function enqueue_b2b_assets() {
        wp_enqueue_style('b2b-commerce', B2B_COMMERCE_URL . 'assets/css/b2b-commerce.css', [], B2B_COMMERCE_VERSION);
        wp_enqueue_script('b2b-commerce', B2B_COMMERCE_URL . 'assets/js/b2b-commerce.js', ['jquery'], B2B_COMMERCE_VERSION, true);
        
        // Only localize script for logged-in users with appropriate permissions
        if (is_user_logged_in()) {
            wp_localize_script('b2b-commerce', 'b2b_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::AJAX_NONCE),
                'is_admin' => current_user_can('manage_woocommerce'),
                'user_id' => get_current_user_id()
            ]);
        }
    }

    // Advanced pricing features implementation
    public function tiered_pricing() {
        // Validate user permissions
        if ( ! $this->validate_user_permissions('read') ) {
            return '';
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $product_id = get_the_ID();
        $user_id = get_current_user_id();
        $user_roles = is_user_logged_in() ? (array) wp_get_current_user()->roles : [];
        
        // Validate product ID
        if ( ! $product_id || $product_id <= 0 ) {
            return '';
        }
        
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        $b2b_roles = apply_filters('b2b_customer_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        
        // Always show tiered pricing to administrators for management purposes
        if (current_user_can('manage_options') || current_user_can('manage_woocommerce') || current_user_can('edit_products')) {
            // Administrators can always see tiered pricing
        } else {
            // For non-admins, check B2B role and product visibility
            if (!array_intersect($user_roles, $b2b_roles)) {
                return ''; // Don't show tiered pricing to non-B2B users
            }
            
            // Check if user is allowed to see this product (respects B2B visibility settings)
            if (class_exists('B2B\\ProductManager')) {
                $product_manager = new \B2B\ProductManager();
                if (!$product_manager->is_user_allowed_for_product($product_id)) {
                    return ''; // Don't show tiered pricing if user can't access this product
                }
            }
        }
        
        // Get tiered pricing rules for this product
        // Show only rules relevant to the current user's role
        if (current_user_can('manage_options') || current_user_can('manage_woocommerce') || current_user_can('edit_products')) {
            // Administrators see all rules for management purposes
            $rules = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE product_id = %d ORDER BY role, min_qty ASC",
                    $product_id
                )
            );
            
            // If no product-specific rules, check for global rules
            if (empty($rules)) {
                $rules = $wpdb->get_results(
                    "SELECT * FROM `" . $wpdb->_escape($table) . "` WHERE product_id = 0 ORDER BY role, min_qty ASC"
                );
            }
        } else {
            // For non-admins, only show rules for their specific role
            $user_role_placeholders = implode(',', array_fill(0, count($user_roles), '%s'));
            $rules = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE product_id = %d AND role IN ($user_role_placeholders) ORDER BY role, min_qty ASC",
                    array_merge([$product_id], $user_roles)
                )
            );
            
            // If no product-specific rules, check for global rules
            if (empty($rules)) {
                $rules = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `" . $wpdb->_escape($table) . "` WHERE product_id = 0 AND role IN ($user_role_placeholders) ORDER BY role, min_qty ASC",
                        $user_roles
                    )
                );
            }
        }
        
        if (empty($rules)) return '';
        
        $output = '<div class="b2b-tiered-pricing">';
        $output .= '<h4>' . esc_html__('Tiered Pricing', 'b2b-commerce') . '</h4>';
        $output .= '<table class="tiered-pricing-table">';
        $output .= '<thead><tr><th>' . esc_html__('Quantity', 'b2b-commerce') . '</th><th>' . esc_html__('Price', 'b2b-commerce') . '</th><th>' . esc_html__('Savings', 'b2b-commerce') . '</th></tr></thead><tbody>';
        
        foreach ($rules as $rule) {
            $original_price = (float) get_post_meta($product_id, '_price', true);
            
            if ($rule->type === 'percentage') {
                $display_price = $original_price * (1 - (abs($rule->price)/100));
                $savings = max(0, $original_price - $display_price);
                $savings_percent = $original_price > 0 ? ($savings / $original_price) * 100 : 0;
                $savings_display = sprintf('%.1f%%', $savings_percent);
            } else {
                // Fixed pricing
                $display_price = (float) $rule->price;
                $savings = $original_price - $display_price;
                if ($savings > 0) {
                    $savings_display = wp_kses_post(wc_price($savings)) . ' ' . esc_html__('off', 'b2b-commerce');
                } elseif ($savings < 0) {
                    $savings_display = wp_kses_post(wc_price(abs($savings))) . ' ' . esc_html__('more', 'b2b-commerce');
                } else {
                    $savings_display = esc_html__('Same price', 'b2b-commerce');
                }
            }
            
            $output .= '<tr>';
            $output .= '<td>' . esc_html($rule->min_qty) . '+' . '</td>';
            $output .= '<td>' . wp_kses_post(wc_price($display_price)) . '</td>';
            $output .= '<td>' . esc_html($savings_display) . '</td>';
            $output .= '</tr>';
        }
        
        $output .= '</tbody></table></div>';
        return $output;
    }

    public function role_based_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $product_id = get_the_ID();
        $user_roles = wp_get_current_user()->roles;
        
        // Check if user is logged in and has B2B role
        if (!is_user_logged_in()) {
            return ''; // Don't show role-based pricing to non-logged-in users
        }
        
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        $b2b_roles = apply_filters('b2b_customer_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        
        // Always show role-based pricing to administrators for management purposes
        if (current_user_can('manage_options') || current_user_can('manage_woocommerce') || current_user_can('edit_products')) {
            // Administrators can always see role-based pricing
        } else {
            // For non-admins, check B2B role and product visibility
            if (!array_intersect($user_roles, $b2b_roles)) {
                return ''; // Don't show role-based pricing to non-B2B users
            }
            
            // Check if user is allowed to see this product (respects B2B visibility settings)
            if (class_exists('B2B\\ProductManager')) {
                $product_manager = new \B2B\ProductManager();
                if (!$product_manager->is_user_allowed_for_product($product_id)) {
                    return ''; // Don't show role-based pricing if user can't access this product
                }
            }
        }
        
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE (product_id = %d OR product_id = 0) AND role IN (" . implode(',', array_fill(0, count($user_roles), '%s')) . ") ORDER BY product_id DESC",
            array_merge([$product_id], $user_roles)
        ));
        
        if (empty($rules)) return '';
        
        $output = '<div class="b2b-role-pricing">';
        $output .= '<h4>' . esc_html__('Your Pricing', 'b2b-commerce') . '</h4>';
        $output .= '<ul>';
        
        foreach ($rules as $rule) {
            if ($rule->type === 'percentage') {
                $label = abs($rule->price) . '% ' . esc_html__('discount','b2b-commerce');
            } else {
                $label = wp_kses_post(wc_price($rule->price)) . ' ' . esc_html__('per unit', 'b2b-commerce');
            }
            $output .= '<li>' . esc_html(ucfirst(str_replace('_', ' ', $rule->role))) . ': ' . esc_html($label) . '</li>';
        }
        
        $output .= '</ul></div>';
        return $output;
    }

    public function customer_specific_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $product_id = get_the_ID();
        $user_id = get_current_user_id();
        
        if (!$user_id) return '';
        
        $rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE (product_id = %d OR product_id = 0) AND user_id = %d ORDER BY product_id DESC LIMIT 1",
            $product_id, $user_id
        ));
        
        if (!$rule) return '';
        
        $output = '<div class="b2b-customer-pricing">';
        $output .= '<h4>' . esc_html__('Your Special Price', 'b2b-commerce') . '</h4>';
        $output .= '<p class="special-price">' . wp_kses_post(wc_price($rule->price)) . '</p>';
        $output .= '</div>';
        
        return $output;
    }

    public function geographic_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $product_id = get_the_ID();
        
        // Get user's location (simplified - in production, use geolocation)
        $user_country = WC()->customer ? WC()->customer->get_billing_country() : '';
        if (!$user_country) return '';
        
        $rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE (product_id = %d OR product_id = 0) AND geo_zone = %s ORDER BY product_id DESC LIMIT 1",
            $product_id, $user_country
        ));
        
        if (!$rule) return '';
        
        $output = '<div class="b2b-geo-pricing">';
        $output .= '<h4>' . esc_html__('Regional Pricing', 'b2b-commerce') . '</h4>';
        // translators: %1$s is the country/region name, %2$s is the formatted price
        $output .= '<p>' . sprintf(esc_html__('Price for %1$s: %2$s', 'b2b-commerce'), esc_html($user_country), wp_kses_post(wc_price($rule->price))) . '</p>';
        $output .= '</div>';
        
        return $output;
    }

    public function time_based_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $product_id = get_the_ID();
        $current_date = current_time('Y-m-d');
        
        $rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE (product_id = %d OR product_id = 0) AND start_date <= %s AND end_date >= %s ORDER BY product_id DESC LIMIT 1",
            $product_id, $current_date, $current_date
        ));
        
        if (!$rule) return '';
        
        $output = '<div class="b2b-time-pricing">';
        $output .= '<h4>' . esc_html__('Limited Time Offer', 'b2b-commerce') . '</h4>';
        // translators: %s is the formatted special price
        $output .= '<p>' . sprintf(esc_html__('Special price: %s', 'b2b-commerce'), wp_kses_post(wc_price($rule->price))) . '</p>';
        // translators: %s is the end date for the offer
        $output .= '<p>' . sprintf(esc_html__('Valid until: %s', 'b2b-commerce'), esc_html($rule->end_date)) . '</p>';
        $output .= '</div>';
        
        return $output;
    }

    // Render pricing-related widgets on product page
    public function render_pricing_widgets() {
        echo wp_kses_post($this->tiered_pricing());
        echo wp_kses_post($this->role_based_pricing());
        echo wp_kses_post($this->min_max_quantity());
    }

    public function min_max_quantity() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $product_id = get_the_ID();
        $user_id = get_current_user_id();
        $user_roles = is_user_logged_in() ? (array) wp_get_current_user()->roles : [];
        
        if (empty($user_roles)) {
            $rule = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT min_qty, max_qty FROM $table WHERE (product_id = %d OR product_id = 0) AND user_id = 0 ORDER BY product_id DESC, min_qty ASC LIMIT 1",
                    $product_id
                )
            );
        } else {
            $placeholders = implode(',', array_fill(0, count($user_roles), '%s'));
            $rule = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT min_qty, max_qty FROM $table WHERE (product_id = %d OR product_id = 0) AND (user_id = %d OR role IN ($placeholders)) ORDER BY product_id DESC, min_qty ASC LIMIT 1",
                    array_merge([$product_id, $user_id], $user_roles)
                )
            );
        }
        
        if (!$rule) return '';
        
        $output = '<div class="b2b-quantity-limits">';
        if ($rule->min_qty > 1) {
            // translators: %s is the minimum quantity required
            $output .= '<p><strong>' . esc_html__('Minimum Order:', 'b2b-commerce') . '</strong> ' . sprintf(esc_html__('%s units', 'b2b-commerce'), esc_html($rule->min_qty)) . '</p>';
        }
        if ($rule->max_qty > 0) {
            // translators: %s is the maximum quantity allowed
            $output .= '<p><strong>' . esc_html__('Maximum Order:', 'b2b-commerce') . '</strong> ' . sprintf(esc_html__('%s units', 'b2b-commerce'), esc_html($rule->max_qty)) . '</p>';
        }
        $output .= '</div>';
        
        return $output;
    }

    public function price_request() {
        // Quote functionality is disabled in the free version
        // This feature is available in the Pro version
        return '';
    }
    
    /**
     * Sanitize date input
     * 
     * @param string $date
     * @return string
     */
    private function sanitize_date($date) {
        if (empty($date)) {
            return '';
        }
        
        // Validate date format (YYYY-MM-DD)
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if ($date_obj && $date_obj->format('Y-m-d') === $date) {
            return sanitize_text_field($date);
        }
        
        return '';
    }
    
    /**
     * Validate user permissions for pricing operations
     * 
     * @param string $operation
     * @return bool
     */
    private function validate_user_permissions($operation = 'read') {
        switch ($operation) {
            case 'create':
            case 'update':
            case 'delete':
                return current_user_can('manage_woocommerce');
            case 'read':
            default:
                return is_user_logged_in();
        }
    }
    
    /**
     * Sanitize and validate pricing rule data
     * 
     * @param array $data
     * @return array|WP_Error
     */
    private function sanitize_pricing_rule_data($data) {
        $sanitized = [];
        $errors = new WP_Error();
        
        // Product ID validation
        if (isset($data['product_id'])) {
            $product_id = intval($data['product_id']);
            if ($product_id < 0) {
                $errors->add('invalid_product_id', __('Product ID must be non-negative.', 'b2b-commerce'));
            } else {
                $sanitized['product_id'] = $product_id;
            }
        }
        
        // Role validation
        if (isset($data['role'])) {
            $role = sanitize_text_field($data['role']);
            $allowed_roles = apply_filters('b2b_commerce_pro_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
            if (!empty($role) && !in_array($role, $allowed_roles)) {
                $errors->add('invalid_role', __('Invalid user role.', 'b2b-commerce'));
            } else {
                $sanitized['role'] = $role;
            }
        }
        
        // User ID validation
        if (isset($data['user_id'])) {
            $user_id = intval($data['user_id']);
            if ($user_id < 0) {
                $errors->add('invalid_user_id', __('User ID must be non-negative.', 'b2b-commerce'));
            } else {
                $sanitized['user_id'] = $user_id;
            }
        }
        
        // Price validation
        if (isset($data['price'])) {
            $price = floatval($data['price']);
            if ($price < 0) {
                $errors->add('invalid_price', __('Price cannot be negative.', 'b2b-commerce'));
            } else {
                $sanitized['price'] = $price;
            }
        }
        
        // Type validation
        if (isset($data['type'])) {
            $type = sanitize_text_field($data['type']);
            if (!in_array($type, ['percentage', 'fixed'])) {
                $errors->add('invalid_type', __('Invalid pricing type.', 'b2b-commerce'));
            } else {
                $sanitized['type'] = $type;
            }
        }
        
        // Quantity validation
        if (isset($data['min_qty'])) {
            $min_qty = intval($data['min_qty']);
            if ($min_qty < 1) {
                $errors->add('invalid_min_qty', __('Minimum quantity must be at least 1.', 'b2b-commerce'));
            } else {
                $sanitized['min_qty'] = $min_qty;
            }
        }
        
        if (isset($data['max_qty'])) {
            $max_qty = intval($data['max_qty']);
            if ($max_qty < 0) {
                $errors->add('invalid_max_qty', __('Maximum quantity must be non-negative.', 'b2b-commerce'));
            } else {
                $sanitized['max_qty'] = $max_qty;
            }
        }
        
        // Validate quantity range
        if (isset($sanitized['min_qty']) && isset($sanitized['max_qty']) && 
            $sanitized['max_qty'] > 0 && $sanitized['min_qty'] > $sanitized['max_qty']) {
            $errors->add('invalid_quantity_range', __('Minimum quantity cannot be greater than maximum quantity.', 'b2b-commerce'));
        }
        
        if (!empty($errors->get_error_messages())) {
            return $errors;
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX handler for getting pricing rules
     */
    public function ajax_get_pricing_rules() {
        // Rate limiting check
        if (!$this->check_rate_limit('ajax_get_pricing_rules')) {
            wp_send_json_error(esc_html__('Too many requests. Please try again later.', 'b2b-commerce'));
        }
        
        // Sanitize all POST data before validation
        $sanitized_post = array_map('sanitize_text_field', $_POST);
        
        // Verify nonce
        if (!isset($sanitized_post['nonce']) || !wp_verify_nonce($sanitized_post['nonce'], self::AJAX_NONCE)) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        // Check user permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'b2b-commerce'));
        }
        
        // Validate product ID
        if (!isset($sanitized_post['product_id']) || !is_numeric($sanitized_post['product_id'])) {
            wp_send_json_error(esc_html__('Invalid product ID.', 'b2b-commerce'));
        }
        
        $product_id = intval($sanitized_post['product_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d OR product_id = 0 ORDER BY product_id DESC, min_qty ASC",
            $product_id
        ));
        
        wp_send_json_success($rules);
    }
    
    /**
     * AJAX handler for bulk operations
     */
    public function ajax_bulk_operations() {
        // Rate limiting check
        if (!$this->check_rate_limit('ajax_bulk_operations')) {
            wp_send_json_error(esc_html__('Too many requests. Please try again later.', 'b2b-commerce'));
        }
        
        // Sanitize all POST data before validation
        $sanitized_post = array_map('sanitize_text_field', $_POST);
        
        // Verify nonce
        if (!isset($sanitized_post['nonce']) || !wp_verify_nonce($sanitized_post['nonce'], self::AJAX_NONCE)) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        // Check user permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'b2b-commerce'));
        }
        
        // Validate action
        if (!isset($sanitized_post['action_type']) || !in_array($sanitized_post['action_type'], ['delete', 'update_status'])) {
            wp_send_json_error(esc_html__('Invalid action type.', 'b2b-commerce'));
        }
        
        // Validate rule IDs
        if (!isset($_POST['rule_ids']) || !is_array($_POST['rule_ids'])) {
            wp_send_json_error(esc_html__('Invalid rule IDs.', 'b2b-commerce'));
        }
        
        $rule_ids = array_map('intval', $_POST['rule_ids']);
        $rule_ids = array_filter($rule_ids, function($id) { return $id > 0; });
        
        if (empty($rule_ids)) {
            wp_send_json_error(esc_html__('No valid rule IDs provided.', 'b2b-commerce'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        
        $placeholders = implode(',', array_fill(0, count($rule_ids), '%d'));
        
        if ($sanitized_post['action_type'] === 'delete') {
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE id IN ($placeholders)",
                $rule_ids
            ));
            
            if ($result === false) {
                wp_send_json_error(esc_html__('Failed to delete rules.', 'b2b-commerce'));
            }
            
            wp_send_json_success(sprintf(esc_html__('Successfully deleted %d rules.', 'b2b-commerce'), intval($result)));
        }
        
        wp_send_json_error(esc_html__('Action not implemented.', 'b2b-commerce'));
    }
    
    /**
     * Check rate limiting for AJAX requests
     * 
     * @param string $action
     * @return bool
     */
    private function check_rate_limit($action) {
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $key = 'b2b_rate_limit_' . $action . '_' . $user_id . '_' . $ip_address;
        
        $requests = get_transient($key);
        if ($requests === false) {
            $requests = 0;
        }
        
        // Allow 10 requests per minute per user/IP combination
        if ($requests >= 10) {
            return false;
        }
        
        set_transient($key, $requests + 1, 60); // 60 seconds
        return true;
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
} 