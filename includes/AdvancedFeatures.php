<?php
namespace B2B;

if ( ! defined( 'ABSPATH' ) ) exit;

class AdvancedFeatures {
    public function __construct() {
        // Credit limit and payment terms admin UI
        add_action( 'show_user_profile', [ $this, 'user_advanced_fields' ] );
        add_action( 'edit_user_profile', [ $this, 'user_advanced_fields' ] );
        add_action( 'personal_options_update', [ $this, 'save_user_advanced_fields' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_user_advanced_fields' ] );
        // Credit limit enforcement
        add_action( 'woocommerce_checkout_process', [ $this, 'enforce_credit_limit' ] );
        // Payment terms display
        add_action( 'woocommerce_review_order_after_payment', [ $this, 'show_payment_terms' ] );
        // Tax exemption
        add_filter( 'woocommerce_customer_is_vat_exempt', [ $this, 'handle_tax_exemption' ], 5, 2 );
        // Multi-currency support (integration hook)
        add_filter( 'woocommerce_currency', [ $this, 'handle_multi_currency' ], 5 );
        // REST API endpoints
        add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );
        // Quote request system - now handled by b2b_buttons_container
        add_action( 'woocommerce_single_product_summary', [ $this, 'b2b_buttons_container' ], 35 );
        add_action( 'wp_ajax_b2b_quote_request', [ $this, 'handle_quote_request' ] );
        add_action( 'wp_ajax_nopriv_b2b_quote_request', [ $this, 'handle_quote_request' ] );
        add_action( 'wp_ajax_b2b_product_inquiry', [ $this, 'handle_product_inquiry' ] );
        add_action( 'wp_ajax_nopriv_b2b_product_inquiry', [ $this, 'handle_product_inquiry' ] );
        // JavaScript is handled by b2b-commerce.js file
        // Bulk pricing calculator - REMOVED (Pro feature)
        // Catalog mode & checkout controls
        add_filter( 'woocommerce_is_purchasable', [ $this, 'maybe_disable_purchasable' ], 5, 2 );
        add_filter( 'woocommerce_get_price_html', [ $this, 'maybe_hide_price_html' ], 5, 2 );
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_payment_gateways_by_role' ], 5 );
        add_filter( 'woocommerce_package_rates', [ $this, 'filter_package_rates_by_role' ], 5, 2 );
        add_action( 'woocommerce_checkout_process', [ $this, 'block_checkout_when_forced_quote' ] );


        
        // Product edit page B2B pricing integration
        add_action( 'woocommerce_product_options_pricing', [ $this, 'add_b2b_pricing_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_b2b_pricing_fields' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_product_edit_scripts' ] );
    }

    

    // Admin UI for credit, payment terms, tax exemption
    public function user_advanced_fields( $user ) {
        ?>
        <h3><?php _e('B2B Advanced Features', 'b2b-commerce'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="b2b_credit_limit"><?php _e('Credit Limit', 'b2b-commerce'); ?></label></th>
                <td><input type="number" name="b2b_credit_limit" id="b2b_credit_limit" value="<?php echo esc_attr( get_user_meta( $user->ID, 'b2b_credit_limit', true ) ); ?>" step="0.01"></td>
            </tr>
            <tr>
                <th><label for="b2b_payment_terms"><?php _e('Payment Terms', 'b2b-commerce'); ?></label></th>
                <td><input type="text" name="b2b_payment_terms" id="b2b_payment_terms" value="<?php echo esc_attr( get_user_meta( $user->ID, 'b2b_payment_terms', true ) ); ?>" placeholder="<?php _e('Net 30, Net 60, etc.', 'b2b-commerce'); ?>"></td>
            </tr>
            <tr>
                <th><label for="b2b_tax_exempt"><?php _e('Tax Exempt', 'b2b-commerce'); ?></label></th>
                <td><input type="checkbox" name="b2b_tax_exempt" value="1" <?php checked( get_user_meta( $user->ID, 'b2b_tax_exempt', true ), 1 ); ?>> <?php _e('Yes', 'b2b-commerce'); ?><br>
                <input type="text" name="b2b_tax_exempt_number" value="<?php echo esc_attr( get_user_meta( $user->ID, 'b2b_tax_exempt_number', true ) ); ?>" placeholder="<?php _e('Tax Exempt Number', 'b2b-commerce'); ?>"></td>
            </tr>
        </table>
        <?php
    }
    public function save_user_advanced_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        update_user_meta( $user_id, 'b2b_credit_limit', floatval( $_POST['b2b_credit_limit'] ?? 0 ) );
        update_user_meta( $user_id, 'b2b_payment_terms', sanitize_text_field( $_POST['b2b_payment_terms'] ?? '' ) );
        update_user_meta( $user_id, 'b2b_tax_exempt', isset( $_POST['b2b_tax_exempt'] ) ? 1 : 0 );
        update_user_meta( $user_id, 'b2b_tax_exempt_number', sanitize_text_field( $_POST['b2b_tax_exempt_number'] ?? '' ) );
    }

    // Enhanced credit limit enforcement
    public function enforce_credit_limit() {
        $user_id = get_current_user_id();
        $limit = floatval(get_user_meta($user_id, 'b2b_credit_limit', true));
        
        if ($limit <= 0) return;
        
        $current_balance = $this->get_user_credit_balance($user_id);
        $cart_total = WC()->cart->get_total('raw');
        
        if ($cart_total + $current_balance > $limit) {
            $remaining = $limit - $current_balance;
            wc_add_notice(
                sprintf(
                    // translators: %1$s is the maximum order amount, %2$s is the current balance, %3$s is the remaining credit
                    __('Your order exceeds your credit limit. Maximum order amount: %1$s. Current balance: %2$s. Remaining credit: %3$s.', 'b2b-commerce'),
                    wc_price($limit),
                    wc_price($current_balance),
                    wc_price($remaining)
                ),
                'error'
            );
        }
    }

    // Enhanced credit balance calculation
    public function get_user_credit_balance($user_id) {
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['processing', 'completed'],
            'limit' => -1
        ]);
        
        $total_credit_used = 0;
        
        foreach ($orders as $order) {
            $payment_method = $order->get_payment_method();
            
            // Only count orders with credit terms (not immediate payment)
            if (in_array($payment_method, ['b2b_credit', 'b2b_net30', 'b2b_net60'])) {
                $total_credit_used += $order->get_total();
            }
        }
        
        return $total_credit_used;
    }

    // Show payment terms at checkout
    public function show_payment_terms() {
        $user_id = get_current_user_id();
        $terms = get_user_meta( $user_id, 'b2b_payment_terms', true );
        if ( $terms ) {
            echo '<tr class="b2b-payment-terms"><td colspan="2">' . __('Payment Terms:', 'b2b-commerce') . ' ' . esc_html( $terms ) . '</td></tr>';
        }
    }

    // Tax exemption
    public function handle_tax_exemption( $is_exempt, $customer ) {
        $user_id = is_object( $customer ) ? $customer->get_id() : get_current_user_id();
        if ( get_user_meta( $user_id, 'b2b_tax_exempt', true ) ) {
            return true;
        }
        return $is_exempt;
    }

    // Multi-currency support (integration hook)
    public function handle_multi_currency( $currency ) {
        // Integrate with WooCommerce multi-currency plugins if available

        return $currency;
    }

    // REST API endpoints
    public function register_rest_endpoints() {
        register_rest_route( 'b2b/v1', '/users', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_get_users' ],
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        ] );
        register_rest_route( 'b2b/v1', '/orders', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_get_orders' ],
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        ] );
        register_rest_route( 'b2b/v1', '/pricing', [
            'methods' => 'GET',
            'callback' => [ $this, 'rest_get_pricing' ],
            'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        ] );
    }
    public function rest_get_users() {
        $users = get_users( [ 'role__in' => [ 'b2b_customer', 'wholesale_customer', 'distributor', 'retailer' ] ] );
        $data = [];
        foreach ( $users as $user ) {
            $data[] = [
                'id' => $user->ID,
                'email' => $user->user_email,
                'role' => $user->roles,
                'company' => get_user_meta( $user->ID, 'company_name', true ),
                'group' => wp_get_object_terms( $user->ID, 'b2b_user_group', [ 'fields' => 'names' ] ),
                'credit_limit' => get_user_meta( $user->ID, 'b2b_credit_limit', true ),
                'payment_terms' => get_user_meta( $user->ID, 'b2b_payment_terms', true ),
                'tax_exempt' => get_user_meta( $user->ID, 'b2b_tax_exempt', true ),
            ];
        }
        return $data;
    }
    public function rest_get_orders() {
        $orders = wc_get_orders( [ 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC' ] );
        $data = [];
        foreach ( $orders as $order ) {
            $data[] = [
                'id' => $order->get_id(),
                'user_id' => $order->get_user_id(),
                'total' => $order->get_total(),
                'status' => $order->get_status(),
                'date' => $order->get_date_created()->date( 'Y-m-d' ),
            ];
        }
        return $data;
    }
    public function rest_get_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . 'b2b_pricing_rules';
        $rules = $wpdb->get_results( "SELECT * FROM $table" );
        return $rules;
    }

    // Advanced features implementation
    public function plugin_integrations() {
        // WooCommerce Multi-Currency integration
        if (class_exists('WOOCS')) {
            add_filter('woocommerce_currency', [$this, 'handle_woocs_currency'], 5);
        }
        
        // WooCommerce Advanced Shipping integration
        if (class_exists('WooCommerce_Advanced_Shipping')) {
            add_filter('woocommerce_shipping_methods', [$this, 'add_b2b_shipping_methods'], 5);
        }
        
        // WooCommerce PDF Invoices integration
        if (class_exists('WooCommerce_PDF_Invoices')) {
            add_action('woocommerce_order_status_completed', [$this, 'generate_b2b_invoice']);
        }
        
        // WooCommerce Subscriptions integration
        if (class_exists('WC_Subscriptions')) {
            add_filter('woocommerce_subscription_price', [$this, 'apply_b2b_subscription_pricing'], 5);
        }
        
        // WooCommerce Bookings integration
        if (class_exists('WC_Bookings')) {
            add_filter('woocommerce_bookings_cost', [$this, 'apply_b2b_booking_pricing'], 5);
        }
        
        // Advanced Custom Fields integration
        if (class_exists('ACF')) {
            add_action('acf/save_post', [$this, 'save_b2b_acf_fields']);
        }
        
        // Yoast SEO integration
        if (class_exists('WPSEO_Admin')) {
            add_filter('wpseo_title', [$this, 'modify_b2b_seo_title']);
        }
    }

    public function handle_woocs_currency($currency) {
        $user_id = get_current_user_id();
        $user_currency = get_user_meta($user_id, 'b2b_preferred_currency', true);
        
        if ($user_currency && class_exists('WOOCS')) {
            global $WOOCS;
            $WOOCS->set_currency($user_currency);
            return $user_currency;
        }
        
        return $currency;
    }

    public function add_b2b_shipping_methods($methods) {
        $methods['b2b_free_shipping'] = 'B2B_Free_Shipping_Method';
        $methods['b2b_priority_shipping'] = 'B2B_Priority_Shipping_Method';
        return $methods;
    }

    public function generate_b2b_invoice($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        
        if (!$user_id) return;
        
        $user = get_user_by('id', $user_id);
        $user_roles = $user->roles;
        
        // Only generate B2B invoices for B2B customers
        if (!array_intersect($user_roles, ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'])) {
            return;
        }
        
        // Generate custom B2B invoice
        $invoice_data = [
            'order_id' => $order_id,
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'company_name' => get_user_meta($user_id, 'company_name', true),
            'tax_id' => get_user_meta($user_id, 'tax_id', true),
            'payment_terms' => get_user_meta($user_id, 'b2b_payment_terms', true),
            'invoice_number' => __('INV-', 'b2b-commerce') . $order_id,
            'invoice_date' => current_time('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'items' => $order->get_items(),
            'total' => $order->get_total(),
            'tax' => $order->get_total_tax(),
            'shipping' => $order->get_shipping_total()
        ];
        
        // Store invoice data
        update_post_meta($order_id, '_b2b_invoice_data', $invoice_data);
        
        // Send invoice email
        $this->send_b2b_invoice_email($order_id, $invoice_data);
    }

    public function apply_b2b_subscription_pricing($price) {
        $user_id = get_current_user_id();
        if (!$user_id) return $price;
        
        $user_roles = wp_get_current_user()->roles;
        $discount = 0;
        
        if (in_array('wholesale_customer', $user_roles)) {
            $discount = 15; // 15% discount for wholesale
        } elseif (in_array('distributor', $user_roles)) {
            $discount = 20; // 20% discount for distributors
        }
        
        if ($discount > 0) {
            $price = $price * (1 - ($discount / 100));
        }
        
        return $price;
    }

    public function apply_b2b_booking_pricing($cost) {
        $user_id = get_current_user_id();
        if (!$user_id) return $cost;
        
        $user_roles = wp_get_current_user()->roles;
        $discount = 0;
        
        if (in_array('wholesale_customer', $user_roles)) {
            $discount = 10; // 10% discount for wholesale bookings
        }
        
        if ($discount > 0) {
            $cost = $cost * (1 - ($discount / 100));
        }
        
        return $cost;
    }

    public function save_b2b_acf_fields($post_id) {
        if (get_post_type($post_id) !== 'product') return;
        
        // Save B2B-specific ACF fields
        if (function_exists('get_field')) {
            $b2b_visible_roles = get_field('b2b_visible_roles', $post_id);
            $b2b_wholesale_only = get_field('b2b_wholesale_only', $post_id);
            $b2b_min_order_qty = get_field('b2b_min_order_qty', $post_id);
            
            if ($b2b_visible_roles) {
                update_post_meta($post_id, '_b2b_visible_roles', $b2b_visible_roles);
            }
            if ($b2b_wholesale_only) {
                update_post_meta($post_id, '_b2b_wholesale_only', 'yes');
            }
            if ($b2b_min_order_qty) {
                update_post_meta($post_id, '_b2b_min_order_qty', $b2b_min_order_qty);
            }
        }
    }

    public function modify_b2b_seo_title($title) {
        if (!is_user_logged_in()) return $title;
        
        $user_roles = wp_get_current_user()->roles;
        
        if (array_intersect($user_roles, ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'])) {
            $title = '[B2B] ' . $title;
        }
        
        return $title;
    }

    private function send_b2b_invoice_email($order_id, $invoice_data) {
        $order = wc_get_order($order_id);
        $user = get_user_by('id', $order->get_user_id());
        
        $subject = __('Invoice for Order #', 'b2b-commerce') . $order_id . ' - ' . get_bloginfo('name');
        
        $message = $this->generate_invoice_email_content($invoice_data);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    private function generate_invoice_email_content($invoice_data) {
        $content = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $content .= '<h2>' . __('Invoice', 'b2b-commerce') . '</h2>';
        $content .= '<p><strong>' . __('Invoice Number:', 'b2b-commerce') . '</strong> ' . $invoice_data['invoice_number'] . '</p>';
        $content .= '<p><strong>' . __('Date:', 'b2b-commerce') . '</strong> ' . $invoice_data['invoice_date'] . '</p>';
        $content .= '<p><strong>' . __('Due Date:', 'b2b-commerce') . '</strong> ' . $invoice_data['due_date'] . '</p>';
        $content .= '<p><strong>' . __('Customer:', 'b2b-commerce') . '</strong> ' . $invoice_data['customer_name'] . '</p>';
        $content .= '<p><strong>' . __('Company:', 'b2b-commerce') . '</strong> ' . $invoice_data['company_name'] . '</p>';
        $content .= '<p><strong>' . __('Payment Terms:', 'b2b-commerce') . '</strong> ' . $invoice_data['payment_terms'] . '</p>';
        
        $content .= '<h3>' . __('Items', 'b2b-commerce') . '</h3>';
        $content .= '<table style="width: 100%; border-collapse: collapse;">';
        $content .= '<thead><tr><th style="border: 1px solid #ddd; padding: 8px;">' . __('Item', 'b2b-commerce') . '</th><th style="border: 1px solid #ddd; padding: 8px;">' . __('Qty', 'b2b-commerce') . '</th><th style="border: 1px solid #ddd; padding: 8px;">' . __('Price', 'b2b-commerce') . '</th><th style="border: 1px solid #ddd; padding: 8px;">' . __('Total', 'b2b-commerce') . '</th></tr></thead><tbody>';
        
        foreach ($invoice_data['items'] as $item) {
            $content .= '<tr>';
            $content .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $item->get_name() . '</td>';
            $content .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $item->get_quantity() . '</td>';
            $content .= '<td style="border: 1px solid #ddd; padding: 8px;">' . wc_price($item->get_total() / $item->get_quantity()) . '</td>';
            $content .= '<td style="border: 1px solid #ddd; padding: 8px;">' . wc_price($item->get_total()) . '</td>';
            $content .= '</tr>';
        }
        
        $content .= '</tbody></table>';
        
        $content .= '<h3>' . __('Summary', 'b2b-commerce') . '</h3>';
        $content .= '<p><strong>' . __('Subtotal:', 'b2b-commerce') . '</strong> ' . wc_price($invoice_data['total'] - $invoice_data['tax'] - $invoice_data['shipping']) . '</p>';
        $content .= '<p><strong>' . __('Tax:', 'b2b-commerce') . '</strong> ' . wc_price($invoice_data['tax']) . '</p>';
        $content .= '<p><strong>' . __('Shipping:', 'b2b-commerce') . '</strong> ' . wc_price($invoice_data['shipping']) . '</p>';
        $content .= '<p><strong>' . __('Total:', 'b2b-commerce') . '</strong> ' . wc_price($invoice_data['total']) . '</p>';
        
        $content .= '</div>';
        
        return $content;
    }

    public function bulk_order_management() {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return '<p>' . __('WooCommerce is required for bulk order management.', 'b2b-commerce') . '</p>';
        }
        
        $orders = wc_get_orders([
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
    }
    
    // B2B Buttons Container - displays quote and inquiry buttons horizontally
    public function b2b_buttons_container() {
        global $product;
        if (!$product) return;
        
        // Prevent duplicate containers
        static $buttons_container_rendered = false;
        if ($buttons_container_rendered) return;
        $buttons_container_rendered = true;
        
        // Use helper function to check if buttons should be shown
        if (class_exists('B2B\\ProductManager')) {
            $product_manager = new ProductManager();
            if (!$product_manager->should_show_b2b_buttons($product->get_id())) {
                return;
            }
        }
        
        echo '<div class="b2b-buttons-container">';
        $this->quote_request_button();
        $this->product_inquiry_button();
        echo '</div>';
    }
    
    // Quote request button - DISABLED in free version
    public function quote_request_button() {
        // Quote functionality is disabled in the free version
        // This feature is available in the Pro version
        return;
    }
    
    // Product inquiry button - DISABLED in free version
    public function product_inquiry_button() {
        // Product inquiry functionality is disabled in the free version
        // This feature is available in the Pro version
        return;
    }
    
    // JavaScript functionality is handled by b2b-commerce.js file
    
    // Handle quote request - DISABLED in free version
    public function handle_quote_request() {
        // Quote functionality is disabled in the free version
        // This feature is available in the Pro version
        wp_send_json_error( __('Quote functionality is not available in the free version. Please upgrade to B2B Commerce Pro.', 'b2b-commerce') );
    }
    
    // Handle product inquiry
    public function handle_product_inquiry() {
        // CSRF protection
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'b2b_ajax_nonce')) {
            // Debug: Log nonce verification failure
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(__('B2B Product Inquiry Debug - Nonce verification failed. Received nonce:', 'b2b-commerce') . ' ' . $nonce);
            }
            wp_send_json_error(__('Security check failed', 'b2b-commerce'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in to submit inquiries', 'b2b-commerce'));
            return;
        }
        
        $product_id = intval($_POST['product_id']);
        $email = sanitize_email($_POST['email']);
        $message = sanitize_textarea_field($_POST['message']);
        $user_id = get_current_user_id();
        
        if (!$product_id || !$email || !$message) {
            // Debug: Log the received data
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(__('B2B Product Inquiry Debug - Nonce verification failed', 'b2b-commerce'));
            }
            wp_send_json_error(__('Invalid request data - Missing required fields', 'b2b-commerce'));
            return;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Product not found', 'b2b-commerce'));
            return;
        }
        
        // Save inquiry to database
        $inquiry_data = [
            'product_id' => $product_id,
            'email' => $email,
            'message' => $message,
            'status' => 'pending',
            'date' => current_time('mysql'),
            'user_id' => $user_id
        ];
        
        $inquiries = get_option('b2b_product_inquiries', []);
        $inquiries[] = $inquiry_data;
        update_option('b2b_product_inquiries', $inquiries);
        
        // Send email notification to admin
        $admin_email = get_option('admin_email');
        $subject = __('B2B Product Inquiry for ', 'b2b-commerce') . $product->get_name();
        $body = __('Product:', 'b2b-commerce') . " " . $product->get_name() . "\n" . __('Email:', 'b2b-commerce') . " $email\n" . __('Message:', 'b2b-commerce') . " $message";
        wp_mail($admin_email, $subject, $body);
        
        wp_send_json_success(__('Inquiry submitted successfully', 'b2b-commerce'));
    }

    // Catalog Mode: hide prices and disable add-to-cart
    public function maybe_disable_purchasable( $purchasable, $product ) {
        $catalog = get_option('b2b_catalog_mode', []);
        if ( !is_user_logged_in() && !empty($catalog['disable_add_to_cart']) ) {
            return false;
        }
        if ( !empty($catalog['force_quote_mode']) ) {
            return false;
        }
        return $purchasable;
    }

    public function maybe_hide_price_html( $price, $product ) {
        $catalog = get_option('b2b_catalog_mode', []);
        if ( !is_user_logged_in() && !empty($catalog['hide_prices_guests']) ) {
            return '<span class="price">' . esc_html__('Login to see prices', 'b2b-commerce') . '</span>';
        }
        if ( !empty($catalog['force_quote_mode']) ) {
            return '<span class="price">' . esc_html__('Request a quote', 'b2b-commerce') . '</span>';
        }
        return $price;
    }

    // Role-based checkout controls
    public function filter_payment_gateways_by_role( $gateways ) {
        if ( !is_user_logged_in() ) return $gateways;
        $settings = get_option('b2b_role_payment_methods', []);
        if ( empty($settings) ) return $gateways;
        $roles = wp_get_current_user()->roles;
        foreach ( $gateways as $id => $gateway ) {
            $allowed = false;
            foreach ( $roles as $role ) {
                if ( !empty($settings[$role]) && in_array($id, (array) $settings[$role], true) ) {
                    $allowed = true; break;
                }
            }
            if ( !$allowed ) {
                unset($gateways[$id]);
            }
        }
        return $gateways;
    }

    public function filter_package_rates_by_role( $rates, $package ) {
        if ( !is_user_logged_in() ) return $rates;
        $settings = get_option('b2b_role_shipping_methods', []);
        if ( empty($settings) ) return $rates;
        $roles = wp_get_current_user()->roles;
        foreach ( $rates as $rate_id => $rate ) {
            $method_id = isset($rate->method_id) ? $rate->method_id : '';
            $allowed = false;
            foreach ( $roles as $role ) {
                if ( !empty($settings[$role]) && in_array($method_id, (array) $settings[$role], true) ) {
                    $allowed = true; break;
                }
            }
            if ( !$allowed ) {
                unset($rates[$rate_id]);
            }
        }
        return $rates;
    }

    public function block_checkout_when_forced_quote() {
        if ( !function_exists('is_checkout') || !is_checkout() ) return;
        $catalog = get_option('b2b_catalog_mode', []);
        if ( !empty($catalog['force_quote_mode']) ) {
            wc_add_notice( __('Checkout is disabled. Please request a quote.', 'b2b-commerce'), 'error' );
        }
    }


    
    // Bulk pricing calculator - REMOVED (Pro feature)
    

    
    // Advanced reports page
    public function advanced_reports_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('B2B Advanced Reports', 'b2b-commerce') . '</h1>';
        echo '<div class="b2b-admin-card">';
        echo '<h3>' . __('Quote Requests', 'b2b-commerce') . '</h3>';
        
        $quotes = get_option('b2b_quote_requests', []);
        if (empty($quotes)) {
            echo '<p>' . __('No quote requests found.', 'b2b-commerce') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>' . __('Product', 'b2b-commerce') . '</th><th>' . __('Quantity', 'b2b-commerce') . '</th><th>' . __('User', 'b2b-commerce') . '</th><th>' . __('Status', 'b2b-commerce') . '</th><th>' . __('Date', 'b2b-commerce') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($quotes as $quote) {
                $product = wc_get_product($quote['product_id']);
                $user = get_userdata($quote['user_id']);
                echo '<tr>';
                echo '<td>' . ($product ? $product->get_name() : __('Product not found', 'b2b-commerce')) . '</td>';
                echo '<td>' . $quote['quantity'] . '</td>';
                echo '<td>' . ($user ? $user->display_name : __('User not found', 'b2b-commerce')) . '</td>';
                echo '<td>' . ucfirst($quote['status']) . '</td>';
                echo '<td>' . $quote['date'] . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    // Product edit page B2B pricing integration
    public function add_b2b_pricing_fields() {
        global $post;
        
        echo '<div class="options_group b2b-pricing-section">';
        echo '<h4 style="margin: 0 0 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #0073aa;">' . __('B2B Pricing', 'b2b-commerce') . '</h4>';
        
        // Get existing B2B pricing rules for this product
        global $wpdb;
        $table = $wpdb->prefix . 'b2b_pricing_rules';
        $existing_rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d ORDER BY min_qty ASC",
            $post->ID
        ));
        
        // Get B2B user roles
        $b2b_roles = ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'];
        
        foreach ($b2b_roles as $role) {
            $role_display_name = ucwords(str_replace('_', ' ', $role));
            
            echo '<div class="b2b-role-pricing" data-role="' . esc_attr($role) . '">';
            echo '<h5 style="margin: 15px 0 10px 0; color: #0073aa;">' . esc_html($role_display_name) . '</h5>';
            
            // Regular price for this role
            $regular_price = get_post_meta($post->ID, '_b2b_' . $role . '_regular_price', true);
            woocommerce_wp_text_input([
                'id' => '_b2b_' . $role . '_regular_price',
                // translators: %s is the currency symbol
                'label' => sprintf(__('Regular Price (%s)', 'b2b-commerce'), get_woocommerce_currency_symbol()),
                'desc_tip' => true,
                // translators: %s is the role display name
                'description' => sprintf(__('Regular price for %s customers', 'b2b-commerce'), $role_display_name),
                'data_type' => 'price',
                'value' => $regular_price
            ]);
            
            // Sale price for this role
            $sale_price = get_post_meta($post->ID, '_b2b_' . $role . '_sale_price', true);
            woocommerce_wp_text_input([
                'id' => '_b2b_' . $role . '_sale_price',
                // translators: %s is the currency symbol
                'label' => sprintf(__('Sale Price (%s)', 'b2b-commerce'), get_woocommerce_currency_symbol()),
                'desc_tip' => true,
                // translators: %s is the role display name
                'description' => sprintf(__('Sale price for %s customers', 'b2b-commerce'), $role_display_name),
                'data_type' => 'price',
                'value' => $sale_price
            ]);
            
            // Tiered pricing for this role
            echo '<div class="b2b-tiered-pricing" data-role="' . esc_attr($role) . '">';
            echo '<h6 style="margin: 10px 0 5px 0;">' . __('Tiered Pricing', 'b2b-commerce') . '</h6>';
            
            // Get existing tiers for this role
            $role_tiers = array_filter($existing_rules, function($rule) use ($role) {
                return $rule->role === $role;
            });
            
            echo '<div class="b2b-tiers-container" data-role="' . esc_attr($role) . '">';
            if (!empty($role_tiers)) {
                foreach ($role_tiers as $tier) {
                    $placeholder = ($tier->type === 'percentage') ? __('Enter percentage (e.g., 5.55 for 5.55%)', 'b2b-commerce') : __('Enter price (e.g., 25.00)', 'b2b-commerce');
                    $title = ($tier->type === 'percentage') ? __('Enter discount percentage (e.g., 5.55 for 5.55% off)', 'b2b-commerce') : __('Enter fixed price (e.g., 25.00)', 'b2b-commerce');
                    
                    // Format the display value based on type
                    $display_value = '';
                    if ($tier->type === 'percentage') {
                        // For percentage, show with up to 2 decimal places (e.g., 5.55 for 5.55%)
                        $display_value = number_format((float)$tier->price, 2, '.', '');
                        // Remove trailing zeros for whole numbers (e.g., 5.00 becomes 5)
                        $display_value = rtrim(rtrim($display_value, '0'), '.');
                    } else {
                        // For fixed price, format to 2 decimal places
                        $display_value = number_format((float)$tier->price, 2, '.', '');
                    }
                    
                    echo '<div class="b2b-tier-row">';
                    echo '<input type="number" name="b2b_tier_min_qty[' . esc_attr($role) . '][]" value="' . esc_attr($tier->min_qty) . '" placeholder="' . esc_attr(__('Min Qty', 'b2b-commerce')) . '" min="1" style="width: 80px;" title="' . esc_attr(__('Minimum quantity for this tier', 'b2b-commerce')) . '">';
                    echo '<input type="text" name="b2b_tier_price[' . esc_attr($role) . '][]" value="' . esc_attr($display_value) . '" placeholder="' . esc_attr($placeholder) . '" class="wc_input_price tier-price-input' . ($tier->type === 'percentage' ? ' percentage-input' : '') . '" style="width: 100px;" title="' . esc_attr($title) . '">';
                    if ($tier->type === 'percentage') {
                        echo '<span class="percentage-indicator">%</span>';
                    }
                    echo '<select name="b2b_tier_type[' . esc_attr($role) . '][]" class="tier-type-select" style="width: 100px;" title="' . esc_attr(__('Choose pricing type', 'b2b-commerce')) . '">';
                    echo '<option value="fixed"' . selected($tier->type, 'fixed', false) . '>' . __('Fixed Price', 'b2b-commerce') . '</option>';
                    echo '<option value="percentage"' . selected($tier->type, 'percentage', false) . '>' . __('Percentage', 'b2b-commerce') . '</option>';
                    echo '</select>';
                    echo '<button type="button" class="button remove-tier" style="margin-left: 5px;" title="' . esc_attr(__('Remove this tier', 'b2b-commerce')) . '">' . __('Remove', 'b2b-commerce') . '</button>';
                    echo '</div>';
                }
            }
            echo '</div>';
            
            echo '<button type="button" class="button add-tier" data-role="' . esc_attr($role) . '" style="margin-top: 5px;">' . __('Add Tier', 'b2b-commerce') . '</button>';
            echo '</div>'; // .b2b-tiered-pricing
            
            echo '</div>'; // .b2b-role-pricing
        }
        
        echo '</div>'; // .b2b-pricing-section
    }
    
    public function save_b2b_pricing_fields($post_id) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save regular and sale prices for each role
        $b2b_roles = ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'];
        
        foreach ($b2b_roles as $role) {
            // Save regular price
            if (isset($_POST['_b2b_' . $role . '_regular_price'])) {
                $regular_price = wc_format_decimal($_POST['_b2b_' . $role . '_regular_price']);
                update_post_meta($post_id, '_b2b_' . $role . '_regular_price', $regular_price);
            }
            
            // Save sale price
            if (isset($_POST['_b2b_' . $role . '_sale_price'])) {
                $sale_price = wc_format_decimal($_POST['_b2b_' . $role . '_sale_price']);
                update_post_meta($post_id, '_b2b_' . $role . '_sale_price', $sale_price);
            }
        }
        
        // Save tiered pricing rules
        if (isset($_POST['b2b_tier_min_qty']) && is_array($_POST['b2b_tier_min_qty'])) {
            global $wpdb;
            $table = $wpdb->prefix . 'b2b_pricing_rules';
            
            // Debug: Log the POST data
                    error_log(__('B2B Tiered Pricing: Processing tiered pricing for product', 'b2b-commerce') . " $post_id");
            
            // Delete existing rules for this product
            $delete_result = $wpdb->delete($table, ['product_id' => $post_id]);
            error_log(__('B2B Tiered Pricing: Deleted', 'b2b-commerce') . " $delete_result " . __('existing rules for product', 'b2b-commerce') . " $post_id");
            
            // Insert new rules
            $insert_count = 0;
            foreach ($_POST['b2b_tier_min_qty'] as $role => $quantities) {
                error_log(__('B2B Tiered Pricing: Processing role:', 'b2b-commerce') . " $role");
                if (is_array($quantities)) {
                    error_log(__('B2B Tiered Pricing: Processing quantities for role', 'b2b-commerce') . " $role");
                    foreach ($quantities as $index => $quantity) {
                        error_log(__('B2B Tiered Pricing: Processing index', 'b2b-commerce') . " $index, " . __('quantity', 'b2b-commerce') . " $quantity");
                        if (!empty($quantity) && isset($_POST['b2b_tier_price'][$role][$index])) {
                            $price = wc_format_decimal($_POST['b2b_tier_price'][$role][$index]);
                            $type = sanitize_text_field($_POST['b2b_tier_type'][$role][$index] ?? 'fixed');
                            
                            error_log(__('B2B Tiered Pricing: Formatted price:', 'b2b-commerce') . " $price, " . __('type:', 'b2b-commerce') . " $type");
                            
                            $insert_data = [
                                'product_id' => $post_id,
                                'role' => sanitize_text_field($role),
                                'min_qty' => intval($quantity),
                                'price' => $price,
                                'type' => $type
                            ];
                            
                            error_log(__('B2B Tiered Pricing: Inserting rule for product', 'b2b-commerce') . " $post_id, " . __('role', 'b2b-commerce') . " $role");
                            
                            $result = $wpdb->insert($table, $insert_data);
                            
                            // Debug logging
                            if ($result === false) {
                                error_log(__('B2B Tiered Pricing: Failed to insert rule for product', 'b2b-commerce') . " $post_id, " . __('role', 'b2b-commerce') . " $role, " . __('qty', 'b2b-commerce') . " $quantity, " . __('price', 'b2b-commerce') . " $price. " . __('Error:', 'b2b-commerce') . " " . $wpdb->last_error);
                            } else {
                                error_log(__('B2B Tiered Pricing: Successfully inserted rule for product', 'b2b-commerce') . " $post_id, " . __('role', 'b2b-commerce') . " $role, " . __('qty', 'b2b-commerce') . " $quantity, " . __('price', 'b2b-commerce') . " $price. " . __('Insert ID:', 'b2b-commerce') . " " . $wpdb->insert_id);
                                $insert_count++;
                            }
                        } else {
                            error_log(__('B2B Tiered Pricing: Skipping index', 'b2b-commerce') . " $index - " . __('empty quantity or missing price', 'b2b-commerce'));
                        }
                    }
                } else {
                    error_log(__('B2B Tiered Pricing: Quantities is not an array for role', 'b2b-commerce') . " $role");
                }
            }
            error_log(__('B2B Tiered Pricing: Total rules inserted:', 'b2b-commerce') . " $insert_count");
        } else {
            error_log(__('B2B Tiered Pricing: No tiered pricing data found in POST for product', 'b2b-commerce') . " $post_id");
        }
    }
    
    public function enqueue_product_edit_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        global $post_type;
        if ($post_type !== 'product') {
            return;
        }
        
        wp_enqueue_script('b2b-product-edit', B2B_COMMERCE_URL . 'assets/js/b2b-product-edit.js', ['jquery'], B2B_COMMERCE_VERSION, true);
        wp_enqueue_style('b2b-product-edit', B2B_COMMERCE_URL . 'assets/css/b2b-product-edit.css', [], B2B_COMMERCE_VERSION);
        
        // Localize script for internationalization
        wp_localize_script('b2b-product-edit', 'b2b_product_edit', array(
            'strings' => array(
                'min_qty' => __('Min Qty', 'b2b-commerce'),
                'min_qty_tooltip' => __('Minimum quantity for this tier', 'b2b-commerce'),
                'price_percentage' => __('Price/Percentage', 'b2b-commerce'),
                'price_tooltip' => __('Enter price (e.g., 25.00) or percentage (e.g., 5.55)', 'b2b-commerce'),
                'type_tooltip' => __('Choose pricing type', 'b2b-commerce'),
                'fixed_price' => __('Fixed Price', 'b2b-commerce'),
                'percentage' => __('Percentage', 'b2b-commerce'),
                'remove' => __('Remove', 'b2b-commerce'),
                'remove_tooltip' => __('Remove this tier', 'b2b-commerce'),
                'price_example' => __('Enter price (e.g., 25.00)', 'b2b-commerce'),
                'percentage_example' => __('Enter percentage (e.g., 5.55 for 5.55%)', 'b2b-commerce'),
                'percentage_tooltip' => __('Enter discount percentage (e.g., 5.55 for 5.55% off)', 'b2b-commerce'),
                'unsaved_changes_warning' => __('You have unsaved B2B pricing changes. Are you sure you want to leave?', 'b2b-commerce')
            )
        ));
    }


} 