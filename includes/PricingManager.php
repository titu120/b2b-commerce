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
    
            error_log(__('B2B Commerce Pro: Using fallback table creation', 'b2b-commerce'));
        }
        

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) {
            error_log(sprintf(__('B2B Commerce Pro: Failed to create pricing table: %s', 'b2b-commerce'), $table));
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
                error_log(__('B2B Commerce Pro: Failed to create pricing table during self-healing', 'b2b-commerce'));
            } else {
                delete_option( self::PRICING_TABLE_ERROR_OPTION );
                error_log(__('B2B Commerce Pro: Successfully created pricing table during self-healing', 'b2b-commerce'));
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
        
        
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", $table));
        error_log(sprintf(__('B2B Pricing: Table exists with %d rules', 'b2b-commerce'), $count));
        return true;
    }

    public function admin_notice_table_error() {
        if ( get_option( self::PRICING_TABLE_ERROR_OPTION ) ) {
            echo '<div class="notice notice-error"><p><strong>' . __('B2B Commerce Pro:', 'b2b-commerce') . '</strong> ' . __('Could not create the pricing rules table. Please check your database permissions or contact your host.', 'b2b-commerce') . '</p></div>';
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
        
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $roles = $user->roles;
        $product_id = $product->get_id();
        
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
            error_log("B2B Pricing: Product $product_id, User $user_id, Original: $price, New: $best_price, Rule ID: " . $matched_rule->id);
        }
        
        return $best_price;
    }

    // Apply pricing rules to price HTML display
    public function apply_pricing_to_price_html( $price_html, $product ) {
        if ( ! is_user_logged_in() ) return $price_html;
        
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $roles = $user->roles;
        $product_id = $product->get_id();
        
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
                $new_price_html .= '<del><span class="woocommerce-Price-amount amount">' . wc_price($b2b_regular_price) . '</span></del> ';
                $new_price_html .= '<ins><span class="woocommerce-Price-amount amount">' . wc_price($b2b_sale_price) . '</span></ins>';
            } else {
                // No sale price - just show the B2B price without strikethrough
                $new_price_html .= '<span class="woocommerce-Price-amount amount">' . wc_price($product_level_price) . '</span>';
            }
            
            $new_price_html .= '</span>';
            return $new_price_html;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        
        // Query product-specific rules AND global rules (product_id = 0)
        $rules = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE product_id = %d OR product_id = 0",
                $table, $product_id
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
            $new_price_html .= '<del><span class="woocommerce-Price-amount amount">' . wc_price($original_price) . '</span></del> ';
            $new_price_html .= '<ins><span class="woocommerce-Price-amount amount">' . wc_price($best_price) . '</span></ins>';
            if ( $discount_percentage > 0 ) {
                $new_price_html .= ' <span class="b2b-discount-badge">(' . $discount_percentage . '% ' . __('off', 'b2b-commerce') . ')</span>';
            }
            $new_price_html .= '</span>';
            
            return $new_price_html;
        }
        
        return $price_html;
    }

    // Enforce min/max quantity in cart
    public function enforce_min_max_quantity( $cart ) {
        if ( is_admin() ) return;
        
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
                            $notices[] = sprintf(__('Minimum quantity for wholesale pricing is %d items.', 'b2b-commerce'), $rule->min_qty);
                        } elseif ($enforce_min_qty && $min_qty_behavior === 'warning') {
                            $notices[] = sprintf(__('Note: Minimum quantity for wholesale pricing is %d items. You may not receive wholesale pricing for quantities below this threshold.', 'b2b-commerce'), $rule->min_qty);
                        }
                        // If behavior is 'ignore', don't add any notice
                    }
                }
                if ( $rule->max_qty && $quantity > $rule->max_qty ) {
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
        if (!current_user_can('manage_woocommerce') || !isset($_POST['b2b_nonce']) || !wp_verify_nonce($_POST['b2b_nonce'], self::SAVE_PRICING_RULE_NONCE)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $data = [
            'product_id' => intval($_POST['product_id']),
            'role' => sanitize_text_field($_POST['role']),
            'user_id' => intval($_POST['user_id']),
            'group_id' => intval($_POST['group_id']),
            'geo_zone' => sanitize_text_field($_POST['geo_zone']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'min_qty' => intval($_POST['min_qty']),
            'max_qty' => intval($_POST['max_qty']),
            'price' => floatval($_POST['price']),
            'type' => sanitize_text_field($_POST['type']),
        ];
        if (!empty($_POST['id'])) {
            $wpdb->update($table, $data, ['id' => intval($_POST['id'])]);
        } else {
            $wpdb->insert($table, $data);
        }
        wp_redirect(admin_url('admin.php?page=' . self::ADMIN_PAGE_SLUG));
        exit;
    }

    // Delete pricing rule
    public function delete_pricing_rule() {
        if (!current_user_can('manage_woocommerce') || !isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], self::DELETE_PRICING_RULE_NONCE)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $wpdb->delete($table, ['id' => intval($_GET['id'])]);
        wp_redirect(admin_url('admin.php?page=' . self::ADMIN_PAGE_SLUG));
        exit;
    }

    // Scripts are now handled by the main plugin file
    
    // Enqueue B2B styles and scripts
    public function enqueue_b2b_assets() {
        wp_enqueue_style('b2b-commerce', B2B_COMMERCE_URL . 'assets/css/b2b-commerce.css', [], B2B_COMMERCE_VERSION);
        wp_enqueue_script('b2b-commerce', B2B_COMMERCE_URL . 'assets/js/b2b-commerce.js', ['jquery'], B2B_COMMERCE_VERSION, true);
        wp_localize_script('b2b-commerce', 'b2b_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::AJAX_NONCE)
        ]);
    }

    // Advanced pricing features implementation
    public function tiered_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $product_id = get_the_ID();
        $user_id = get_current_user_id();
        $user_roles = is_user_logged_in() ? (array) wp_get_current_user()->roles : [];
        
        // Get tiered pricing rules for this product
        // Show all role-based rules for the product, regardless of current user
        $rules = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d ORDER BY role, min_qty ASC",
                $product_id
            )
        );
        
        // If no product-specific rules, check for global rules
        if (empty($rules)) {
            $rules = $wpdb->get_results(
                "SELECT * FROM $table WHERE product_id = 0 ORDER BY role, min_qty ASC"
            );
        }
        
        if (empty($rules)) return '';
        
        $output = '<div class="b2b-tiered-pricing">';
        $output .= '<h4>' . __('Tiered Pricing', 'b2b-commerce') . '</h4>';
        $output .= '<table class="tiered-pricing-table">';
        $output .= '<thead><tr><th>' . __('Quantity', 'b2b-commerce') . '</th><th>' . __('Price', 'b2b-commerce') . '</th><th>' . __('Savings', 'b2b-commerce') . '</th></tr></thead><tbody>';
        
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
                    $savings_display = wc_price($savings) . ' ' . __('off', 'b2b-commerce');
                } elseif ($savings < 0) {
                    $savings_display = wc_price(abs($savings)) . ' ' . __('more', 'b2b-commerce');
                } else {
                    $savings_display = __('Same price', 'b2b-commerce');
                }
            }
            
            $output .= '<tr>';
            $output .= '<td>' . esc_html($rule->min_qty) . '+' . '</td>';
            $output .= '<td>' . wc_price($display_price) . '</td>';
            $output .= '<td>' . $savings_display . '</td>';
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
        
        if (empty($user_roles)) return '';
        
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE (product_id = %d OR product_id = 0) AND role IN (" . implode(',', array_fill(0, count($user_roles), '%s')) . ") ORDER BY product_id DESC",
            array_merge([$product_id], $user_roles)
        ));
        
        if (empty($rules)) return '';
        
        $output = '<div class="b2b-role-pricing">';
        $output .= '<h4>' . __('Your Pricing', 'b2b-commerce') . '</h4>';
        $output .= '<ul>';
        
        foreach ($rules as $rule) {
            if ($rule->type === 'percentage') {
                $label = abs($rule->price) . '% ' . __('discount','b2b-commerce');
            } else {
                $label = wc_price($rule->price) . ' ' . __('per unit', 'b2b-commerce');
            }
            $output .= '<li>' . esc_html(ucfirst(str_replace('_', ' ', $rule->role))) . ': ' . $label . '</li>';
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
        $output .= '<h4>' . __('Your Special Price', 'b2b-commerce') . '</h4>';
        $output .= '<p class="special-price">' . wc_price($rule->price) . '</p>';
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
        $output .= '<h4>' . __('Regional Pricing', 'b2b-commerce') . '</h4>';
        $output .= '<p>' . sprintf(__('Price for %s: %s', 'b2b-commerce'), esc_html($user_country), wc_price($rule->price)) . '</p>';
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
        $output .= '<h4>' . __('Limited Time Offer', 'b2b-commerce') . '</h4>';
        $output .= '<p>' . sprintf(__('Special price: %s', 'b2b-commerce'), wc_price($rule->price)) . '</p>';
        $output .= '<p>' . sprintf(__('Valid until: %s', 'b2b-commerce'), esc_html($rule->end_date)) . '</p>';
        $output .= '</div>';
        
        return $output;
    }

    // Render pricing-related widgets on product page
    public function render_pricing_widgets() {
        echo $this->tiered_pricing();
        echo $this->role_based_pricing();
        echo $this->min_max_quantity();
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
            $output .= '<p><strong>' . __('Minimum Order:', 'b2b-commerce') . '</strong> ' . sprintf(__('%s units', 'b2b-commerce'), esc_html($rule->min_qty)) . '</p>';
        }
        if ($rule->max_qty > 0) {
            $output .= '<p><strong>' . __('Maximum Order:', 'b2b-commerce') . '</strong> ' . sprintf(__('%s units', 'b2b-commerce'), esc_html($rule->max_qty)) . '</p>';
        }
        $output .= '</div>';
        
        return $output;
    }

    public function price_request() {
        if (!is_user_logged_in()) return '';
        
        $output = '<div class="b2b-price-request">';
        $output .= '<h4>' . __('Need a Quote?', 'b2b-commerce') . '</h4>';
        $output .= '<form class="b2b-quote-form" method="post">';
        $output .= wp_nonce_field(self::PRICE_REQUEST_NONCE, 'b2b_nonce', true, false);
        $output .= '<input type="hidden" name="product_id" value="' . get_the_ID() . '">';
        $output .= '<p><label>' . __('Quantity:', 'b2b-commerce') . ' <input type="number" name="quantity" min="1" value="1" required></label></p>';
        $output .= '<p><label>' . __('Special Requirements:', 'b2b-commerce') . ' <textarea name="requirements" rows="3"></textarea></label></p>';
        $output .= '<button type="submit" class="button">' . __('Request Quote', 'b2b-commerce') . '</button>';
        $output .= '</form></div>';
        
        return $output;
    }
} 