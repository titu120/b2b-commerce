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

        add_filter( 'woocommerce_product_get_price', [ $this, 'apply_pricing_rules' ], 5, 2 );
        add_filter( 'woocommerce_product_get_sale_price', [ $this, 'apply_pricing_rules' ], 5, 2 );
        add_filter( 'woocommerce_get_price_html', [ $this, 'apply_pricing_to_price_html' ], 5, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'enforce_min_max_quantity' ] );
        add_action( 'woocommerce_cart_updated', [ $this, 'clear_notice_flag' ] );
        add_action( 'woocommerce_add_to_cart', [ $this, 'clear_notice_flag' ] );
        add_action( 'woocommerce_remove_cart_item', [ $this, 'clear_notice_flag' ] );
        add_action( 'admin_post_' . self::SAVE_PRICING_RULE_NONCE, [ $this, 'save_pricing_rule' ] );
        add_action( 'admin_post_' . self::DELETE_PRICING_RULE_NONCE, [ $this, 'delete_pricing_rule' ] );
        // Enqueue B2B assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_b2b_assets' ] );

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
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query($sql);
        }
        

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) {
            // translators: %s is the table name that failed to be created
            return false;
        }
        
        return true;
    }

    // Self-healing: check and create table if missing
    public function maybe_create_pricing_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists != $table ) {
            $result = self::create_pricing_table();
            if ( !$result ) {
                update_option( self::PRICING_TABLE_ERROR_OPTION, 1 );
            } else {
                delete_option( self::PRICING_TABLE_ERROR_OPTION );
            }
        } else {
                delete_option( self::PRICING_TABLE_ERROR_OPTION );
        }
    }

    // Check if pricing table exists and has data
    public function check_pricing_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        

        if ($exists != $table) {
            return false;
        }
        
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
        return true;
    }

    public function admin_notice_table_error() {
        if ( get_option( self::PRICING_TABLE_ERROR_OPTION ) ) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'B2B Commerce:', 'b2b-commerce' ) . '</strong> ' . esc_html__( 'Could not create the pricing rules table. Please check your database permissions or contact your host.', 'b2b-commerce' ) . '</p></div>';
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
        
        // Check cache first
        $cache_key = 'b2b_pricing_rules_' . $product_id;
        $rules = wp_cache_get($cache_key, 'b2b_commerce');
        
        if (false === $rules) {
            // Query product-specific rules AND global rules (product_id = 0)
            // Global rules let the admin define role-based pricing that applies to every product
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rules = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE product_id = %d OR product_id = 0",
                    $product_id
                )
            );
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $rules, 'b2b_commerce', HOUR_IN_SECONDS);
        }
        
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
            $now = gmdate( 'Y-m-d' );
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
        
        // Check cache first
        $cache_key = 'b2b_pricing_rules_html_' . $product_id;
        $rules = wp_cache_get($cache_key, 'b2b_commerce');
        
        if (false === $rules) {
            // Query product-specific rules AND global rules (product_id = 0)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rules = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE product_id = %d OR product_id = 0",
                    $product_id
                )
            );
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $rules, 'b2b_commerce', HOUR_IN_SECONDS);
        }
        
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
            $now = gmdate( 'Y-m-d' );
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
            // Check cache first
            $cache_key = 'b2b_cart_rules_' . $product_id;
            $rules = wp_cache_get($cache_key, 'b2b_commerce');
            
            if (false === $rules) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $rules = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `{$table}` WHERE product_id = %d OR product_id = 0",
                        $product_id
                    )
                );
                
                // Cache for 1 hour
                wp_cache_set($cache_key, $rules, 'b2b_commerce', HOUR_IN_SECONDS);
            }

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
                $now = gmdate('Y-m-d');
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
        if (!current_user_can('manage_woocommerce') || !isset($_POST['b2b_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['b2b_nonce'])), self::SAVE_PRICING_RULE_NONCE)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $data = [
            'product_id' => isset($_POST['product_id']) ? intval(wp_unslash($_POST['product_id'])) : 0,
            'role' => isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '',
            'user_id' => isset($_POST['user_id']) ? intval(wp_unslash($_POST['user_id'])) : 0,
            'group_id' => isset($_POST['group_id']) ? intval(wp_unslash($_POST['group_id'])) : 0,
            'geo_zone' => isset($_POST['geo_zone']) ? sanitize_text_field(wp_unslash($_POST['geo_zone'])) : '',
            'start_date' => isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '',
            'end_date' => isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '',
            'min_qty' => isset($_POST['min_qty']) ? intval(wp_unslash($_POST['min_qty'])) : 1,
            'max_qty' => isset($_POST['max_qty']) ? intval(wp_unslash($_POST['max_qty'])) : 0,
            'price' => isset($_POST['price']) ? floatval(wp_unslash($_POST['price'])) : 0,
            'type' => isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'percentage',
        ];
        if (!empty($_POST['id'])) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table, $data, ['id' => intval(wp_unslash($_POST['id']))]);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert($table, $data);
        }
        
        // Clear all pricing-related caches after save/update
        $this->clear_pricing_caches($data['product_id']);
        wp_redirect(admin_url('admin.php?page=' . self::ADMIN_PAGE_SLUG));
        exit;
    }

    // Delete pricing rule
    public function delete_pricing_rule() {
        if (!current_user_can('manage_woocommerce') || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::DELETE_PRICING_RULE_NONCE)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $rule_id = isset($_GET['id']) ? intval(wp_unslash($_GET['id'])) : 0;
        if ($rule_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table, ['id' => $rule_id]);
            
            // Clear all pricing-related caches after delete
            $this->clear_pricing_caches(0); // Clear all caches since we don't know which product was affected
        }
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
        
        // Check if user is logged in and has B2B role
        if (!is_user_logged_in()) {
            return ''; // Don't show tiered pricing to non-logged-in users
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
            // Check cache first
            $cache_key = 'b2b_tiered_rules_admin_' . $product_id;
            $rules = wp_cache_get($cache_key, 'b2b_commerce');
            
            if (false === $rules) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $rules = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `{$table}` WHERE product_id = %d ORDER BY role, min_qty ASC",
                        $product_id
                    )
                );
                
                // If no product-specific rules, check for global rules
                if (empty($rules)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $rules = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM `{$table}` WHERE product_id = 0 ORDER BY role, min_qty ASC"
                        )
                    );
                }
                
                // Cache for 1 hour
                wp_cache_set($cache_key, $rules, 'b2b_commerce', HOUR_IN_SECONDS);
            }
        } else {
            // For non-admins, only show rules for their specific role
            $user_role_placeholders = implode(',', array_fill(0, count($user_roles), '%s'));
            
            // Check cache first
            $cache_key = 'b2b_tiered_rules_user_' . $product_id . '_' . md5(implode(',', $user_roles));
            $rules = wp_cache_get($cache_key, 'b2b_commerce');
            
            if (false === $rules) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $rules = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `{$table}` WHERE product_id = %d AND role IN ($user_role_placeholders) ORDER BY role, min_qty ASC",
                        array_merge([$product_id], $user_roles)
                    )
                );
                
                // If no product-specific rules, check for global rules
                if (empty($rules)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $rules = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM `{$table}` WHERE product_id = 0 AND role IN ($user_role_placeholders) ORDER BY role, min_qty ASC",
                            $user_roles
                        )
                    );
                }
                
                // Cache for 1 hour
                wp_cache_set($cache_key, $rules, 'b2b_commerce', HOUR_IN_SECONDS);
            }
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
        
        // Check cache first
        $cache_key = 'b2b_role_rules_' . $product_id . '_' . md5(implode(',', $user_roles));
        $rules = wp_cache_get($cache_key, 'b2b_commerce');
        
        if (false === $rules) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rules = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE (product_id = %d OR product_id = 0) AND role IN (" . implode(',', array_fill(0, count($user_roles), '%s')) . ") ORDER BY product_id DESC",
                array_merge([$product_id], $user_roles)
            ));
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $rules, 'b2b_commerce', HOUR_IN_SECONDS);
        }
        
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
        
        // Check cache first
        $cache_key = 'b2b_customer_rule_' . $product_id . '_' . $user_id;
        $rule = wp_cache_get($cache_key, 'b2b_commerce');
        
        if (false === $rule) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rule = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE (product_id = %d OR product_id = 0) AND user_id = %d ORDER BY product_id DESC LIMIT 1",
                $product_id, $user_id
            ));
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $rule, 'b2b_commerce', HOUR_IN_SECONDS);
        }
        
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
        
        // Check cache first
        $cache_key = 'b2b_geo_rule_' . $product_id . '_' . $user_country;
        $rule = wp_cache_get($cache_key, 'b2b_commerce');
        
        if (false === $rule) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rule = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE (product_id = %d OR product_id = 0) AND geo_zone = %s ORDER BY product_id DESC LIMIT 1",
                $product_id, $user_country
            ));
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $rule, 'b2b_commerce', HOUR_IN_SECONDS);
        }
        
        if (!$rule) return '';
        
        $output = '<div class="b2b-geo-pricing">';
        $output .= '<h4>' . __('Regional Pricing', 'b2b-commerce') . '</h4>';
        // translators: %1$s is the country/region name, %2$s is the formatted price
        $output .= '<p>' . sprintf(__('Price for %1$s: %2$s', 'b2b-commerce'), esc_html($user_country), wc_price($rule->price)) . '</p>';
        $output .= '</div>';
        
        return $output;
    }

    public function time_based_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . self::PRICING_TABLE_NAME;
        $product_id = get_the_ID();
        $current_date = current_time('Y-m-d');
        
        // Check cache first
        $cache_key = 'b2b_time_rule_' . $product_id . '_' . $current_date;
        $rule = wp_cache_get($cache_key, 'b2b_commerce');
        
        if (false === $rule) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rule = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE (product_id = %d OR product_id = 0) AND start_date <= %s AND end_date >= %s ORDER BY product_id DESC LIMIT 1",
                $product_id, $current_date, $current_date
            ));
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $rule, 'b2b_commerce', HOUR_IN_SECONDS);
        }
        
        if (!$rule) return '';
        
        $output = '<div class="b2b-time-pricing">';
        $output .= '<h4>' . __('Limited Time Offer', 'b2b-commerce') . '</h4>';
        // translators: %s is the formatted special price
        $output .= '<p>' . sprintf(__('Special price: %s', 'b2b-commerce'), wc_price($rule->price)) . '</p>';
        // translators: %s is the end date for the offer
        $output .= '<p>' . sprintf(__('Valid until: %s', 'b2b-commerce'), esc_html($rule->end_date)) . '</p>';
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
        
        // Check cache first
        $cache_key = 'b2b_min_max_' . $product_id . '_' . $user_id . '_' . md5(implode(',', $user_roles));
        $rule = wp_cache_get($cache_key, 'b2b_commerce');
        
        if (false === $rule) {
            if (empty($user_roles)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $rule = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT min_qty, max_qty FROM {$table} WHERE (product_id = %d OR product_id = 0) AND user_id = 0 ORDER BY product_id DESC, min_qty ASC LIMIT 1",
                        $product_id
                    )
                );
            } else {
                $placeholders = implode(',', array_fill(0, count($user_roles), '%s'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $rule = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT min_qty, max_qty FROM {$table} WHERE (product_id = %d OR product_id = 0) AND (user_id = %d OR role IN ($placeholders)) ORDER BY product_id DESC, min_qty ASC LIMIT 1",
                        array_merge([$product_id, $user_id], $user_roles)
                    )
                );
            }
            
            // Cache for 1 hour
            wp_cache_set($cache_key, $rule, 'b2b_commerce', HOUR_IN_SECONDS);
        }
        
        if (!$rule) return '';
        
        $output = '<div class="b2b-quantity-limits">';
        if ($rule->min_qty > 1) {
            // translators: %s is the minimum quantity required
            $output .= '<p><strong>' . __('Minimum Order:', 'b2b-commerce') . '</strong> ' . sprintf(__('%s units', 'b2b-commerce'), esc_html($rule->min_qty)) . '</p>';
        }
        if ($rule->max_qty > 0) {
            // translators: %s is the maximum quantity allowed
            $output .= '<p><strong>' . __('Maximum Order:', 'b2b-commerce') . '</strong> ' . sprintf(__('%s units', 'b2b-commerce'), esc_html($rule->max_qty)) . '</p>';
        }
        $output .= '</div>';
        
        return $output;
    }


    /**
     * Clear all pricing-related caches
     *
     * @param int $product_id Product ID to clear caches for (0 for all products)
     */
    private function clear_pricing_caches($product_id = 0) {
        // Clear general caches
        wp_cache_delete('b2b_pricing_rules_count', 'b2b_commerce');
        wp_cache_delete('b2b_pricing_rules_all', 'b2b_commerce');
        wp_cache_delete('b2b_pricing_rules_count_analytics', 'b2b_commerce');
        
        if ($product_id > 0) {
            // Clear product-specific caches
            wp_cache_delete('b2b_pricing_rules_' . $product_id, 'b2b_commerce');
            wp_cache_delete('b2b_pricing_rules_html_' . $product_id, 'b2b_commerce');
            wp_cache_delete('b2b_cart_rules_' . $product_id, 'b2b_commerce');
            wp_cache_delete('b2b_tiered_rules_admin_' . $product_id, 'b2b_commerce');
            wp_cache_delete('b2b_customer_rule_' . $product_id . '_*', 'b2b_commerce');
            wp_cache_delete('b2b_geo_rule_' . $product_id . '_*', 'b2b_commerce');
            wp_cache_delete('b2b_time_rule_' . $product_id . '_*', 'b2b_commerce');
            wp_cache_delete('b2b_min_max_' . $product_id . '_*', 'b2b_commerce');
        } else {
            // Clear all caches - this is more aggressive but ensures consistency
            // In a production environment, you might want to implement a more sophisticated cache clearing strategy
            wp_cache_flush_group('b2b_commerce');
        }
    }
} 