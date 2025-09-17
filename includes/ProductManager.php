<?php
namespace B2B;

if ( ! defined( 'ABSPATH' ) ) exit;

class ProductManager {
    public function __construct() {
        // Product meta for visibility and wholesale-only
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_product_visibility_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_visibility_fields' ] );
        add_filter( 'woocommerce_product_is_visible', [ $this, 'filter_product_visibility' ], 10, 2 );
        add_filter( 'woocommerce_is_purchasable', [ $this, 'filter_is_purchasable' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'maybe_block_product_page' ] );
        // Admin assets for nicer UI (Select2/SelectWoo if available)
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        // Prevent adding restricted products to cart
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart_visibility' ], 10, 5 );
        // De-duplicate noisy removal notices across the site (after cart session loads and on cart checks)
        add_action( 'woocommerce_check_cart_items', [ $this, 'dedupe_removed_notices' ], 1000 );
        add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'dedupe_removed_notices' ], 1000 );
        add_filter( 'woocommerce_product_query_meta_query', [ $this, 'filter_product_query_visibility' ] );
        // Category restrictions
        add_action( 'product_cat_add_form_fields', [ $this, 'add_category_restriction_fields' ] );
        add_action( 'product_cat_edit_form_fields', [ $this, 'edit_category_restriction_fields' ], 10, 2 );
        add_action( 'edited_product_cat', [ $this, 'save_category_restriction_fields' ] );
        add_action( 'create_product_cat', [ $this, 'save_category_restriction_fields' ] );
        add_filter( 'woocommerce_product_query_tax_query', [ $this, 'filter_category_restrictions' ] );
        // Note: b2b_bulk_order shortcode is handled by Frontend.php
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_bulk_order_scripts' ] );
        // Note: b2b_bulk_product_search AJAX handlers are in main plugin file
        add_action( 'wp_loaded', [ $this, 'handle_bulk_order_form' ] );

        // Suppress WooCommerce's per-item removal message for non-purchasable items;
        // we show a single consolidated notice instead.
        add_filter( 'woocommerce_cart_item_removed_message', [ $this, 'suppress_wc_removed_message' ], 10, 2 );
    }

    // Add product visibility and wholesale-only fields
    public function add_product_visibility_fields() {
        global $post;
        echo '<div class="options_group">';

        $saved_roles = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post->ID, '_b2b_visible_roles', true ) ) ) );
        $saved_groups = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post->ID, '_b2b_visible_groups', true ) ) ) );
        $wholesale_only = get_post_meta( $post->ID, '_b2b_wholesale_only', true ) === 'yes';
        $has_restrictions = ! empty( $saved_roles ) || ! empty( $saved_groups ) || $wholesale_only;

        // Toggle
        $css_styles = '#b2b_visibility_fields .form-field{margin:12px 0}#b2b_visibility_box{border:1px solid #e2e8f0;padding:12px 14px;border-radius:6px;background:#fafbfc}#b2b_visibility_fields select{min-width:280px;min-height:120px;width:100%}#b2b_visibility_summary{margin-left:8px;color:#666}';
        echo '<style>' . esc_html($css_styles) . '</style>';
        echo '<p class="form-field"><label for="_b2b_restrict_visibility">' . esc_html__('Restrict visibility', 'b2b-commerce') . '</label>';
        echo '<input type="checkbox" id="_b2b_restrict_visibility" name="_b2b_restrict_visibility" value="' . esc_attr(apply_filters('b2b_restrict_visibility_value', 'yes')) . '" ' . checked( $has_restrictions, true, false ) . ' />';
        echo ' <span class="description">' . esc_html__('Enable to limit who can see/purchase this product.', 'b2b-commerce') . '</span><span id="b2b_visibility_summary"></span></p>';

        echo '<div id="b2b_visibility_box"><div id="b2b_visibility_fields">';

        // User roles (multi-select)
        $all_roles = function_exists('wp_roles') ? wp_roles()->roles : [];
        echo '<p class="form-field"><label for="_b2b_visible_roles">' . __('Visible to Roles', 'b2b-commerce') . '</label>';
        echo '<select id="_b2b_visible_roles" name="_b2b_visible_roles[]" multiple="multiple">'; 
        foreach ( $all_roles as $role_key => $role_data ) {
            $selected = in_array( $role_key, $saved_roles, true ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $role_key ) . '" ' . $selected . '>' . esc_html( $role_data['name'] ) . '</option>';
        }
        echo '</select><span class="description">' . __('Choose roles allowed to see this product.', 'b2b-commerce') . '</span></p>';

        // User groups (multi-select of taxonomy terms if present)
        echo '<p class="form-field"><label for="_b2b_visible_groups">' . __('Visible to Groups', 'b2b-commerce') . '</label>';
        echo '<select id="_b2b_visible_groups" name="_b2b_visible_groups[]" multiple="multiple">';
        if ( taxonomy_exists( apply_filters('b2b_user_group_taxonomy', 'b2b_user_group') ) ) {
            $terms = get_terms( [ 'taxonomy' => apply_filters('b2b_user_group_taxonomy', 'b2b_user_group'), 'hide_empty' => false ] );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $selected = in_array( (int) $term->term_id, $saved_groups, true ) ? 'selected' : '';
                    echo '<option value="' . esc_attr( $term->term_id ) . '" ' . $selected . '>' . esc_html( $term->name ) . ' (#' . (int) $term->term_id . ')</option>';
                }
            }
        }
        echo '</select><span class="description">' . __('Choose groups (taxonomy: b2b_user_group).', 'b2b-commerce') . '</span></p>';

        // Wholesale-only
        woocommerce_wp_checkbox([
            'id' => '_b2b_wholesale_only',
            'label' => __('Wholesale Only', 'b2b-commerce'),
            'description' => __('Only visible to wholesale users.', 'b2b-commerce')
        ]);

        // Quote/Inquiry buttons control
        $show_quote_buttons = get_post_meta($post->ID, '_b2b_show_quote_buttons', true);
        if ($show_quote_buttons === '') $show_quote_buttons = apply_filters('b2b_default_show_quote_buttons', 'yes'); // Default to yes
        woocommerce_wp_checkbox([
            'id' => '_b2b_show_quote_buttons',
            'label' => __('Show Quote/Inquiry Buttons', 'b2b-commerce'),
            'description' => __('Show "Request Quote" and "Product Inquiry" buttons for B2B customers.', 'b2b-commerce'),
            'value' => $show_quote_buttons
        ]);

        // Bulk Pricing Calculator control - REMOVED (Pro feature)

        echo '</div></div>';

        // JS to toggle
        echo '<script>jQuery(function($){
            var $box=$("#b2b_visibility_box"), $summary=$("#b2b_visibility_summary");
            function toggleBox(){var on=$("#_b2b_restrict_visibility").is(":checked");$box.toggle(on);$summary.toggle(!on);} 
            function initSelect($el, placeholder){
                if ($.fn.selectWoo) { $el.selectWoo({placeholder: placeholder, width:"100%", allowClear:true}); }
                else if ($.fn.select2) { $el.select2({placeholder: placeholder, width:"100%", allowClear:true}); }
            }
            initSelect($("#_b2b_visible_roles"), "' . esc_js(__('Choose roles...', 'b2b-commerce')) . '");
            initSelect($("#_b2b_visible_groups"), "' . esc_js(__('Choose groups...', 'b2b-commerce')) . '");
            function updateSummary(){
                var roles = $("#_b2b_visible_roles option:selected").map(function(){return $(this).text();}).get();
                var groupsCount = $("#_b2b_visible_groups option:selected").length; 
                var text = roles.length ? ("' . esc_js(__('Roles:', 'b2b-commerce')) . ' " + roles.join(", ")) : ""; 
                if (groupsCount>0) text += (text?" | ":"") + ("' . esc_js(__('Groups:', 'b2b-commerce')) . ' " + groupsCount); 
                $summary.text(text || "' . esc_js(__('No restrictions', 'b2b-commerce')) . '");
            }
            updateSummary();
            $("#_b2b_visible_roles, #_b2b_visible_groups").on("change", updateSummary);
            toggleBox();
            $("#_b2b_restrict_visibility").on("change", toggleBox);
        });</script>';
        echo '</div>';
    }

    // Save product visibility fields
    public function save_product_visibility_fields( $post_id ) {
        $restrict = isset( $_POST['_b2b_restrict_visibility'] );
        if ( $restrict ) {
            // Roles can come as array from multi-select
            $roles_post = isset( $_POST['_b2b_visible_roles'] ) ? (array) $_POST['_b2b_visible_roles'] : [];
            $roles_clean = array_filter( array_map( 'sanitize_text_field', $roles_post ) );
            update_post_meta( $post_id, '_b2b_visible_roles', implode( ',', $roles_clean ) );

            // Groups can come as array from multi-select
            $groups_post = isset( $_POST['_b2b_visible_groups'] ) ? (array) $_POST['_b2b_visible_groups'] : [];
            $groups_clean = array_filter( array_map( 'intval', $groups_post ) );
            update_post_meta( $post_id, '_b2b_visible_groups', implode( ',', $groups_clean ) );
            update_post_meta( $post_id, '_b2b_wholesale_only', isset( $_POST['_b2b_wholesale_only'] ) ? apply_filters('b2b_wholesale_only_yes_value', 'yes') : apply_filters('b2b_wholesale_only_no_value', 'no') );
        } else {
            // Clear restrictions when toggle is off
            update_post_meta( $post_id, '_b2b_visible_roles', '' );
            update_post_meta( $post_id, '_b2b_visible_groups', '' );
            update_post_meta( $post_id, '_b2b_wholesale_only', apply_filters('b2b_wholesale_only_no_value', 'no') );
        }
        
        // Always save quote button setting (independent of restrictions)
        update_post_meta( $post_id, '_b2b_show_quote_buttons', isset( $_POST['_b2b_show_quote_buttons'] ) ? apply_filters('b2b_show_buttons_yes_value', 'yes') : apply_filters('b2b_show_buttons_no_value', 'no') );
        
        // Always save bulk calculator setting (independent of restrictions)
        update_post_meta( $post_id, '_b2b_show_bulk_calculator', isset( $_POST['_b2b_show_bulk_calculator'] ) ? apply_filters('b2b_show_calculator_yes_value', 'yes') : apply_filters('b2b_show_calculator_no_value', 'no') );
    }

    // Filter product visibility on frontend
    public function filter_product_visibility( $visible, $product_id ) {
        if ( is_admin() ) return $visible;
        return $this->is_user_allowed_for_product( $product_id ) ? $visible : false;
    }

    // Prevent purchase if not allowed
    public function filter_is_purchasable( $purchasable, $product ) {
        if ( is_admin() ) return $purchasable;
        $product_id = is_object( $product ) ? $product->get_id() : (int) $product;
        return $this->is_user_allowed_for_product( $product_id ) ? $purchasable : false;
    }

    // Block direct access to single product page when not allowed
    public function maybe_block_product_page() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return;
        global $post;
        if ( ! $post ) return;
        if ( ! $this->is_user_allowed_for_product( $post->ID ) ) {
            status_header( 404 );
            nocache_headers();
            include get_404_template();
            exit;
        }
    }

    public function is_user_allowed_for_product( $product_id ) {
        // Check if restrictions are actually enabled for this product
        $restrict_visibility = get_post_meta( $product_id, '_b2b_restrict_visibility', true );
        if ( ! $restrict_visibility ) {
            return true; // No restrictions enabled, allow access
        }
        
        $roles = (array) ( wp_get_current_user()->roles ?? [] );
        $groups = wp_get_object_terms( get_current_user_id(), apply_filters('b2b_user_group_taxonomy', 'b2b_user_group'), [ 'fields' => 'ids' ] );
        $allowed_roles = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $product_id, '_b2b_visible_roles', true ) ) ) );
        $allowed_groups = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $product_id, '_b2b_visible_groups', true ) ) ) );
        $wholesale_only = get_post_meta( $product_id, '_b2b_wholesale_only', true ) === apply_filters('b2b_wholesale_only_yes_value', 'yes');

        // Check wholesale-only restriction
        if ( $wholesale_only && ! in_array( apply_filters('b2b_wholesale_customer_role', 'wholesale_customer'), $roles, true ) ) {
            // Allow admins to see wholesale-only products for management
            if ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_products' ) ) ) {
                return false;
            }
        }
        
        // Check role restrictions
        if ( ! empty( $allowed_roles ) && ! array_intersect( $roles, $allowed_roles ) ) {
            // Debug: Log role checking for troubleshooting
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log( 'B2B Role Check Debug - Product ID: ' . $product_id );
                error_log( 'B2B Role Check Debug - User roles: ' . print_r( $roles, true ) );
                error_log( 'B2B Role Check Debug - Allowed roles: ' . print_r( $allowed_roles, true ) );
                error_log( 'B2B Role Check Debug - Array intersect result: ' . print_r( array_intersect( $roles, $allowed_roles ), true ) );
            }
            
            // Special case: Check if user is administrator but role was saved as "administration"
            $is_admin = in_array( 'administrator', $roles, true );
            $has_admin_role = in_array( 'administrator', $allowed_roles, true ) || in_array( 'administration', $allowed_roles, true );
            
            if ( $is_admin && $has_admin_role ) {
                return true; // Allow administrators when admin role is selected
            }
            
            // Allow admins to see role-restricted products for management
            if ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_products' ) ) ) {
                return false;
            }
        }
        
        // Check group restrictions
        if ( ! empty( $allowed_groups ) && ! array_intersect( $groups, $allowed_groups ) ) {
            // Allow admins to see group-restricted products for management
            if ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_products' ) ) ) {
                return false;
            }
        }
        
        return true;
    }

    public function enqueue_admin_assets( $hook ) {
        // Only on product edit screens
        if ( ! isset( $_GET['post'] ) ) return;

        wp_enqueue_script( 'select2' );
        wp_enqueue_style( 'select2' );
    }

    // Block adding restricted products to cart
    public function validate_add_to_cart_visibility( $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ) {
        if ( ! $this->is_user_allowed_for_product( $product_id ) ) {
            wc_add_notice( __( 'This product is not available for your account.', 'b2b-commerce' ), 'error' );
            return false;
        }
        return $passed;
    }

    // Reduce repeated cart removal notices to a single line
    public function dedupe_removed_notices() {
        if ( ! function_exists( 'wc_get_notices' ) ) return;
        $all = wc_get_notices();
        if ( empty( $all ) || empty( $all['error'] ) ) return;
        $error_notices = $all['error'];
        $pattern = __('has been removed from your cart because it can no longer be purchased', 'b2b-commerce');
        $kept = [];
        $removed_count = 0;
        foreach ( $error_notices as $item ) {
            $text = is_array( $item ) && isset( $item['notice'] ) ? (string) $item['notice'] : (string) $item;
            if ( strpos( $text, $pattern ) !== false ) {
                $removed_count++;
                continue;
            }
            $kept[] = $item;
        }
        if ( $removed_count > 0 ) {
            // Rebuild notices without the spammy ones
            wc_clear_notices();
            // Re-add kept errors
            foreach ( $kept as $item ) {
                $text = is_array( $item ) && isset( $item['notice'] ) ? (string) $item['notice'] : (string) $item;
                wc_add_notice( $text, 'error' );
            }
            // Add a single summary message
            wc_add_notice( __( 'Some items were removed from your cart because they are not purchasable for your account.', 'b2b-commerce' ), 'notice' );
        }
    }

    // Only suppress the stock WC message that matches the non-purchasable pattern
    public function suppress_wc_removed_message( $message, $product ) {
        $pattern = __('has been removed from your cart because it can no longer be purchased', 'b2b-commerce');
        if ( is_string( $message ) && strpos( $message, $pattern ) !== false ) {
            return '';
        }
        return $message;
    }

    // Filter product queries for visibility
    public function filter_product_query_visibility( $meta_query ) {
        if ( is_admin() ) return $meta_query;
        
        $user = wp_get_current_user();
        $roles = (array) ( $user->roles ?? [] );
        $groups = wp_get_object_terms( $user->ID, apply_filters('b2b_user_group_taxonomy', 'b2b_user_group'), [ 'fields' => 'ids' ] );
        
        // Skip filtering for admin users, shop managers, and users with edit_products capability
        if ( current_user_can( 'manage_options' ) || 
             current_user_can( 'manage_woocommerce' ) || 
             current_user_can( 'edit_products' ) ||
             in_array( apply_filters('b2b_administrator_role', 'administrator'), $roles, true ) ||
             in_array( apply_filters('b2b_shop_manager_role', 'shop_manager'), $roles, true ) ) {
            return $meta_query;
        }
        
        // Build the meta query to show only products the user can access
        $visibility_query = [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                // Products with no role restrictions (show to everyone)
                [
                    'key' => '_b2b_visible_roles',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_b2b_visible_roles',
                    'value' => '',
                    'compare' => '='
                ],
                // Products with role restrictions that match current user
                [
                    'key' => '_b2b_visible_roles',
                    'value' => implode( ',', $roles ),
                    'compare' => 'LIKE'
                ]
            ],
            [
                'relation' => 'OR',
                // Products with no group restrictions (show to everyone)
                [
                    'key' => '_b2b_visible_groups',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_b2b_visible_groups',
                    'value' => '',
                    'compare' => '='
                ],
                // Products with group restrictions that match current user
                [
                    'key' => '_b2b_visible_groups',
                    'value' => implode( ',', $groups ),
                    'compare' => 'LIKE'
                ]
            ]
        ];
        
        // Add wholesale-only check for non-wholesale users
        if ( ! in_array( apply_filters('b2b_wholesale_customer_role', 'wholesale_customer'), $roles, true ) ) {
            $visibility_query[] = [
                'relation' => 'OR',
                // Products that are not wholesale-only
                [
                    'key' => '_b2b_wholesale_only',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_b2b_wholesale_only',
                    'value' => 'no',
                    'compare' => '='
                ]
            ];
        }
        
        $meta_query[] = $visibility_query;
        
        // Debug: Log the filtering (remove this after testing)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'B2B Product Filter: User roles: ' . implode( ', ', $roles ) );
            error_log( 'B2B Product Filter: Meta query: ' . print_r( $meta_query, true ) );
        }
        
        return $meta_query;
    }

    // Add category restriction fields
    public function add_category_restriction_fields() {
        ?>
        <div class="form-field">
            <label for="b2b_cat_roles"><?php echo esc_html__('Allowed Roles (comma separated)', 'b2b-commerce'); ?></label>
            <input type="text" name="b2b_cat_roles" id="b2b_cat_roles">
        </div>
        <div class="form-field">
            <label for="b2b_cat_groups"><?php echo esc_html__('Allowed Groups (comma separated IDs)', 'b2b-commerce'); ?></label>
            <input type="text" name="b2b_cat_groups" id="b2b_cat_groups">
        </div>
        <?php
    }
    public function edit_category_restriction_fields( $term, $taxonomy ) {
        $roles = get_term_meta( $term->term_id, 'b2b_cat_roles', true );
        $groups = get_term_meta( $term->term_id, 'b2b_cat_groups', true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="b2b_cat_roles"><?php echo esc_html__('Allowed Roles', 'b2b-commerce'); ?></label></th>
            <td><input type="text" name="b2b_cat_roles" id="b2b_cat_roles" value="<?php echo esc_attr( $roles ); ?>"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="b2b_cat_groups"><?php echo esc_html__('Allowed Groups', 'b2b-commerce'); ?></label></th>
            <td><input type="text" name="b2b_cat_groups" id="b2b_cat_groups" value="<?php echo esc_attr( $groups ); ?>"></td>
        </tr>
        <?php
    }
    public function save_category_restriction_fields( $term_id ) {
        update_term_meta( $term_id, 'b2b_cat_roles', sanitize_text_field( $_POST['b2b_cat_roles'] ?? '' ) );
        update_term_meta( $term_id, 'b2b_cat_groups', sanitize_text_field( $_POST['b2b_cat_groups'] ?? '' ) );
    }
    public function filter_category_restrictions( $tax_query ) {
        if ( is_admin() ) return $tax_query;
        // Placeholder: could add logic to restrict categories by user role/group
        return $tax_query;
    }

    // Bulk order shortcode
    public function bulk_order_shortcode() {
        ob_start();
        ?>
        <form id="b2b-bulk-order-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'b2b_bulk_order', 'b2b_bulk_order_nonce' ); ?>
            <h3><?php echo esc_html__('Bulk Order', 'b2b-commerce'); ?></h3>
            <div id="b2b-bulk-products">
                <div class="b2b-bulk-row">
                    <input type="text" class="b2b-product-search" name="product_search[]" placeholder="<?php echo esc_attr__('Search product by name or SKU', 'b2b-commerce'); ?>" autocomplete="off">
                    <input type="number" name="product_qty[]" min="1" value="1" placeholder="<?php echo esc_attr__('Quantity', 'b2b-commerce'); ?>">
                </div>
            </div>
            <button type="button" id="b2b-add-row" class="button"><?php echo esc_html__('Add Another Product', 'b2b-commerce'); ?></button>
            <hr>
            <h4><?php echo esc_html__('Or Import from CSV', 'b2b-commerce'); ?></h4>
            <input type="file" name="b2b_bulk_csv" accept=".csv">
            <hr>
            <button type="submit" class="button button-primary"><?php echo esc_html__('Add to Cart', 'b2b-commerce'); ?></button>
            <div class="b2b-bulk-order-response"></div>
        </form>
        <script>
        jQuery(function($){
            $('#b2b-add-row').on('click', function(){
                var searchPlaceholder = '<?php echo esc_js(__('Search product by name or SKU', 'b2b-commerce')); ?>';
                var qtyPlaceholder = '<?php echo esc_js(__('Quantity', 'b2b-commerce')); ?>';
                $('#b2b-bulk-products').append('<div class="b2b-bulk-row"><input type="text" class="b2b-product-search" name="product_search[]" placeholder="' + searchPlaceholder + '" autocomplete="off"><input type="number" name="product_qty[]" min="1" value="1" placeholder="' + qtyPlaceholder + '"></div>');
            });
            $(document).on('input', '.b2b-product-search', function(){
                var input = $(this);
                $.get(b2bQuote.ajax_url, {action:'b2b_bulk_product_search', term:input.val()}, function(res){
                    if(res.data && res.data.length){
                        var list = $('<ul class="b2b-search-list"></ul>');
                        $.each(res.data, function(i, p){
                            list.append('<li data-id="'+p.id+'">'+p.text+'</li>');
                        });
                        input.nextAll('.b2b-search-list').remove();
                        input.after(list);
                    }
                });
            });
            $(document).on('click', '.b2b-search-list li', function(){
                var id = $(this).data('id');
                var text = $(this).text();
                var input = $(this).closest('.b2b-bulk-row').find('.b2b-product-search');
                input.val(text);
                input.data('product-id', id);
                $(this).parent().remove();
                $('<input type="hidden" name="product_id[]" value="'+id+'">').insertAfter(input);
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    // Enqueue scripts for bulk order
    public function enqueue_bulk_order_scripts() {
        wp_enqueue_script('b2b-quote-request', B2B_COMMERCE_URL . 'assets/js/b2b-commerce.js', ['jquery'], B2B_COMMERCE_VERSION, true);
        wp_localize_script('b2b-quote-request', 'b2bQuote', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('b2b_quote_request'),
        ]);
        wp_enqueue_style('b2b-quote-style', B2B_COMMERCE_URL . 'assets/css/b2b-commerce.css', [], B2B_COMMERCE_VERSION);
    }

    // AJAX product search
    public function ajax_product_search() {
        $term = sanitize_text_field($_GET['term'] ?? '');
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 10,
            's' => $term,
        ];
        $query = new \WP_Query($args);
        $results = [];
        foreach ($query->posts as $p) {
            $results[] = [ 'id' => $p->ID, 'text' => $p->post_title ];
        }
        wp_send_json_success($results);
    }

    // Handle bulk order form submission
    public function handle_bulk_order_form() {
        if ( isset($_POST['b2b_bulk_order_nonce']) && wp_verify_nonce($_POST['b2b_bulk_order_nonce'], 'b2b_bulk_order') ) {
            $product_ids = $_POST['product_id'] ?? [];
            $qtys = $_POST['product_qty'] ?? [];
            foreach ($product_ids as $i => $pid) {
                $pid = intval($pid);
                $qty = intval($qtys[$i] ?? 1);
                if ($pid && $qty > 0) {
                    \WC()->cart->add_to_cart($pid, $qty);
                }
            }
            // Handle CSV import
            if ( !empty($_FILES['b2b_bulk_csv']['tmp_name']) ) {
                // Validate file type and security
        $allowed_types = apply_filters('b2b_allowed_import_file_types', ['csv', 'txt']);
        $file_extension = strtolower(pathinfo($_FILES['b2b_bulk_csv']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            wp_die(__('Invalid file type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        // Additional security: Check file size (max 2MB)
        if ($_FILES['b2b_bulk_csv']['size'] > 2 * 1024 * 1024) {
            wp_die(__('File too large. Maximum size is 2MB.', 'b2b-commerce'));
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['b2b_bulk_csv']['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['text/csv', 'text/plain', 'application/csv'];
        if (!in_array($mime_type, $allowed_mimes)) {
            wp_die(__('Invalid file MIME type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        $file = fopen($_FILES['b2b_bulk_csv']['tmp_name'], 'r');
                while ( ($row = fgetcsv($file)) !== false ) {
                    $pid = wc_get_product_id_by_sku($row[0]);
                    if ( !$pid ) $pid = intval($row[0]);
                    $qty = intval($row[1] ?? 1);
                    if ($pid && $qty > 0) {
                        \WC()->cart->add_to_cart($pid, $qty);
                    }
                }
                fclose($file);
            }
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }
    }

    // Advanced product management features
    public function bulk_order() {
        if (!is_user_logged_in()) return '';
        
        $output = '<div class="b2b-bulk-order">';
        $output .= '<h3>' . esc_html__('Bulk Order System', 'b2b-commerce') . '</h3>';
        $output .= '<form method="post" action="' . admin_url('admin-post.php') . '">';
        $output .= '<input type="hidden" name="action" value="b2b_process_bulk_order">';
        $output .= wp_nonce_field('b2b_bulk_order', 'b2b_bulk_nonce', true, false);
        
        $output .= '<div class="bulk-order-container">';
        $output .= '<div class="bulk-order-header">';
        $output .= '<h4>' . esc_html__('Add Products to Order', 'b2b-commerce') . '</h4>';
        $output .= '<button type="button" id="b2b-add-row" class="button">' . esc_html__('Add Product', 'b2b-commerce') . '</button>';
        $output .= '</div>';
        
        $output .= '<table class="bulk-order-table">';
        $output .= '<thead><tr><th>' . esc_html__('Product', 'b2b-commerce') . '</th><th>' . esc_html__('SKU', 'b2b-commerce') . '</th><th>' . esc_html__('Quantity', 'b2b-commerce') . '</th><th>' . esc_html__('Price', 'b2b-commerce') . '</th><th>' . esc_html__('Total', 'b2b-commerce') . '</th><th>' . esc_html__('Action', 'b2b-commerce') . '</th></tr></thead>';
        $output .= '<tbody id="b2b-order-rows">';
        $output .= '<tr class="order-row">';
        $output .= '<td><input type="text" class="b2b-product-search" name="product_search[]" placeholder="' . esc_attr__('Search product by name or SKU', 'b2b-commerce') . '" autocomplete="off"></td>';
        $output .= '<td><input type="text" name="product_sku[]" readonly></td>';
        $output .= '<td><input type="number" name="product_qty[]" min="1" value="1" placeholder="' . esc_attr__('Quantity', 'b2b-commerce') . '"></td>';
        $output .= '<td><input type="text" name="product_price[]" readonly></td>';
        $output .= '<td><input type="text" name="product_total[]" readonly></td>';
        $output .= '<td><button type="button" class="button remove-row">' . esc_html__('Remove', 'b2b-commerce') . '</button></td>';
        $output .= '</tr>';
        $output .= '</tbody>';
        $output .= '</table>';
        
        $output .= '<div class="bulk-order-summary">';
        $output .= '<h4>' . esc_html__('Order Summary', 'b2b-commerce') . '</h4>';
        $output .= '<p><strong>' . esc_html__('Total Items:', 'b2b-commerce') . '</strong> <span id="total-items">0</span></p>';
        $output .= '<p><strong>' . esc_html__('Total Amount:', 'b2b-commerce') . '</strong> <span id="total-amount">$0.00</span></p>';
        $output .= '</div>';
        
        $output .= '<div class="bulk-order-actions">';
        $output .= '<button type="submit" class="button button-primary">' . esc_html__('Add to Cart', 'b2b-commerce') . '</button>';
        $output .= '<button type="button" class="button" onclick="window.print()">' . esc_html__('Print Order', 'b2b-commerce') . '</button>';
        $output .= '</div>';
        
        $output .= '</div></form></div>';
        
        return $output;
    }

    public function csv_import() {
        if (!current_user_can('manage_options')) return '';
        
        $action = $_GET['action'] ?? 'import';
        
        if ($action === 'process' && isset($_POST['b2b_csv_nonce'])) {
            $this->handle_csv_import();
        } else {
            $this->render_csv_import_interface();
        }
    }

    private function render_csv_import_interface() {
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html__('CSV Product Import', 'b2b-commerce') . '</h2>';
        
        echo '<h3>' . esc_html__('Import Products', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html__('Import products from CSV file.', 'b2b-commerce') . ' <a href="#" onclick="downloadProductTemplate()">' . esc_html__('Download template', 'b2b-commerce') . '</a></p>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo wp_nonce_field('b2b_csv_import', 'b2b_csv_nonce', true, false);
        echo '<input type="hidden" name="action" value="process">';
        echo '<p><input type="file" name="csv_file" accept=".csv" required></p>';
        echo '<p><label><input type="checkbox" name="update_existing" value="1"> ' . esc_html__('Update existing products', 'b2b-commerce') . '</label></p>';
        echo '<p><label><input type="checkbox" name="publish_products" value="1"> ' . esc_html__('Publish products immediately', 'b2b-commerce') . '</label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Import Products', 'b2b-commerce') . '</button></p>';
        echo '</form>';
        
        echo '<h3>' . esc_html__('Export Products', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html__('Export all products to CSV format.', 'b2b-commerce') . '</p>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="b2b_export_products">';
        echo wp_nonce_field('b2b_export_products', 'b2b_export_nonce', true, false);
        echo '<p><button type="submit" class="button">' . esc_html__('Export Products', 'b2b-commerce') . '</button></p>';
        echo '</form></div>';
        
        echo '<script>
        function downloadProductTemplate() {
            var template = "name,sku,description,short_description,regular_price,sale_price,categories,tags,stock_quantity,weight,length,width,height,image_url,visible_roles,wholesale_only\\n";
            template += "' . esc_js(__('Sample Product', 'b2b-commerce')) . ',SKU001,' . esc_js(__('Product description', 'b2b-commerce')) . ',' . esc_js(__('Short description', 'b2b-commerce')) . ',100.00,80.00,Electronics,Sample,50,1.5,10,5,2,https://example.com/image.jpg,b2b_customer,no\\n";
            var blob = new Blob([template], {type: "text/csv"});
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "' . esc_js(__('products_template.csv', 'b2b-commerce')) . '";
            a.click();
        }
        </script>';
    }

    private function handle_csv_import() {
        if (!wp_verify_nonce($_POST['b2b_csv_nonce'], 'b2b_csv_import')) {
            wp_die(__('Security check failed.', 'b2b-commerce'));
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed.', 'b2b-commerce'));
        }
        
        // Validate file type and security
        $allowed_types = ['csv', 'txt'];
        $file_extension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            wp_die(__('Invalid file type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        // Check file size (max 5MB for product import)
        if ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
            wp_die(__('File too large. Maximum size is 5MB.', 'b2b-commerce'));
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['csv_file']['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['text/csv', 'text/plain', 'application/csv'];
        if (!in_array($mime_type, $allowed_mimes)) {
            wp_die(__('Invalid file MIME type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if (!$handle) {
            wp_die(__('Cannot open file.', 'b2b-commerce'));
        }
        
        $headers = fgetcsv($handle);
        $imported = 0;
        $updated = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== false) {
            $product_data = array_combine($headers, $data);
            
            try {
                $result = $this->create_product_from_import($product_data);
                if ($result['action'] === 'created') {
                    $imported++;
                } elseif ($result['action'] === 'updated') {
                    $updated++;
                }
            } catch (Exception $e) {
                // translators: %1$d is the row number, %2$s is the error message
                $errors[] = sprintf(__('Row %1$d: %2$s', 'b2b-commerce'), ($imported + $updated + 1), $e->getMessage());
            }
        }
        
        fclose($handle);
        
        // translators: %1$d is the number of imported products, %2$d is the number of updated products
        $message = sprintf(__('Imported %1$d new products and updated %2$d existing products.', 'b2b-commerce'), $imported, $updated);
        if (!empty($errors)) {
            $message .= ' ' . __('Errors:', 'b2b-commerce') . ' ' . implode(', ', $errors);
        }
        
        wp_redirect(admin_url('admin.php?page=b2b-products&imported=' . $imported . '&updated=' . $updated . '&errors=' . count($errors)));
        exit;
    }

    private function create_product_from_import($product_data) {
        $sku = sanitize_text_field($product_data['sku']);
        $name = sanitize_text_field($product_data['name']);
        
        // Check if product exists
        $existing_product_id = wc_get_product_id_by_sku($sku);
        
        if ($existing_product_id && !isset($_POST['update_existing'])) {
            // translators: %s is the product SKU
            throw new Exception(sprintf(__('Product with SKU %s already exists', 'b2b-commerce'), $sku));
        }
        
        $product_args = [
            'name' => $name,
            'type' => 'simple',
            'status' => isset($_POST['publish_products']) ? 'publish' : 'draft',
            'catalog_visibility' => 'visible',
            'description' => sanitize_textarea_field($product_data['description']),
            'short_description' => sanitize_textarea_field($product_data['short_description']),
            'sku' => $sku,
            'regular_price' => sanitize_text_field($product_data['regular_price']),
            'sale_price' => sanitize_text_field($product_data['sale_price']),
            'manage_stock' => true,
            'stock_quantity' => intval($product_data['stock_quantity']),
            'weight' => floatval($product_data['weight']),
            'dimensions' => [
                'length' => floatval($product_data['length']),
                'width' => floatval($product_data['width']),
                'height' => floatval($product_data['height'])
            ]
        ];
        
        if ($existing_product_id) {
            // Update existing product
            $product = wc_get_product($existing_product_id);
            $product->set_props($product_args);
            $product->save();
            $product_id = $existing_product_id;
            $action = 'updated';
        } else {
            // Create new product
            $product = new WC_Product_Simple();
            $product->set_props($product_args);
            $product_id = $product->save();
            $action = 'created';
        }
        
        // Set categories
        if (!empty($product_data['categories'])) {
            $categories = array_map('trim', explode(',', $product_data['categories']));
            wp_set_object_terms($product_id, $categories, 'product_cat');
        }
        
        // Set tags
        if (!empty($product_data['tags'])) {
            $tags = array_map('trim', explode(',', $product_data['tags']));
            wp_set_object_terms($product_id, $tags, 'product_tag');
        }
        
        // Set B2B specific fields
        if (!empty($product_data['visible_roles'])) {
            update_post_meta($product_id, '_b2b_visible_roles', sanitize_text_field($product_data['visible_roles']));
        }
        
        if (!empty($product_data['wholesale_only'])) {
            update_post_meta($product_id, '_b2b_wholesale_only', $product_data['wholesale_only'] === 'yes' ? 'yes' : 'no');
        }
        
        // Set featured image
        if (!empty($product_data['image_url'])) {
            $this->set_product_image($product_id, $product_data['image_url']);
        }
        
        return ['product_id' => $product_id, 'action' => $action];
    }

    private function set_product_image($product_id, $image_url) {
        $upload = media_sideload_image($image_url, $product_id, '', 'id');
        
        if (!is_wp_error($upload)) {
            set_post_thumbnail($product_id, $upload);
        }
    }

    // Enhanced bulk order functionality
    public function process_bulk_order() {
        if (!wp_verify_nonce($_POST['b2b_bulk_nonce'], 'b2b_bulk_order')) {
            wp_die(__('Security check failed.', 'b2b-commerce'));
        }
        
        $product_searches = $_POST['product_search'] ?? [];
        $product_skus = $_POST['product_sku'] ?? [];
        $product_qtys = $_POST['product_qty'] ?? [];
        
        $added_to_cart = 0;
        $errors = [];
        
        foreach ($product_searches as $index => $search) {
            if (empty($search)) continue;
            
            $sku = $product_skus[$index] ?? '';
            $qty = intval($product_qtys[$index] ?? 1);
            
            if (empty($sku)) {
                // translators: %s is the product search term
                $errors[] = sprintf(__('Product not found: %s', 'b2b-commerce'), $search);
                continue;
            }
            
            $product_id = wc_get_product_id_by_sku($sku);
            if (!$product_id) {
                // translators: %s is the product SKU
                $errors[] = sprintf(__('Product with SKU %s not found', 'b2b-commerce'), $sku);
                continue;
            }
            
            $product = wc_get_product($product_id);
            if (!$product) {
                // translators: %s is the product SKU
                $errors[] = sprintf(__('Cannot load product: %s', 'b2b-commerce'), $sku);
                continue;
            }
            
            // Check if user can see this product
            if (!$this->can_user_see_product($product_id)) {
                // translators: %s is the product name
                $errors[] = sprintf(__("You don't have permission to order: %s", 'b2b-commerce'), $product->get_name());
                continue;
            }
            
            // Add to cart
            $cart_item_key = WC()->cart->add_to_cart($product_id, $qty);
            if ($cart_item_key) {
                $added_to_cart++;
            } else {
                // translators: %s is the product name
                $errors[] = sprintf(__('Failed to add to cart: %s', 'b2b-commerce'), $product->get_name());
            }
        }
        
        if ($added_to_cart > 0) {
            // translators: %d is the number of products added to cart
            wc_add_notice(sprintf(__('Added %d products to cart successfully.', 'b2b-commerce'), $added_to_cart), 'success');
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                wc_add_notice($error, 'error');
            }
        }
        
        wp_redirect(wc_get_cart_url());
        exit;
    }

    private function can_user_see_product($product_id) {
        $user = wp_get_current_user();
        $roles = $user->roles;
        $groups = wp_get_object_terms($user->ID, apply_filters('b2b_user_group_taxonomy', 'b2b_user_group'), ['fields' => 'ids']);
        
        $allowed_roles = array_map('trim', explode(',', get_post_meta($product_id, '_b2b_visible_roles', true)));
        $allowed_groups = array_map('intval', explode(',', get_post_meta($product_id, '_b2b_visible_groups', true)));
        $wholesale_only = get_post_meta($product_id, '_b2b_wholesale_only', true) === apply_filters('b2b_wholesale_only_yes_value', 'yes');
        
        if ($wholesale_only && !in_array(apply_filters('b2b_wholesale_customer_role', 'wholesale_customer'), $roles)) {
            return false;
        }
        
        if ($allowed_roles && $allowed_roles[0] && !array_intersect($roles, $allowed_roles)) {
            return false;
        }
        
        if ($allowed_groups && $allowed_groups[0] && !array_intersect($groups, $allowed_groups)) {
            return false;
        }
        
        return true;
    }

    // Helper function to check if quote/inquiry buttons should be shown for a product
    public function should_show_b2b_buttons($product_id) {
        // Check if user is logged in
        if (!is_user_logged_in()) return false;
        
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        $b2b_roles = apply_filters('b2b_customer_roles', ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer']);
        
        // Always show buttons to administrators for management purposes
        if (current_user_can('manage_options') || current_user_can('manage_woocommerce') || current_user_can('edit_products')) {
            // Administrators can always see buttons
        } else {
            // For non-admins, check B2B role and product visibility
            if (!array_intersect($user_roles, $b2b_roles)) return false;
            
            // Check if user is allowed to see this product (B2B restrictions)
            if (!$this->is_user_allowed_for_product($product_id)) {
                return false; // Don't show buttons if user can't access this product
            }
        }
        
        // Check if product has specific B2B button settings
        $show_b2b_buttons = get_post_meta($product_id, '_b2b_show_quote_buttons', true);
        if ($show_b2b_buttons === apply_filters('b2b_show_buttons_no_value', 'no')) {
            return false; // Explicitly disabled for this product
        }
        
        return true;
    }
    
    // Helper function to check if bulk calculator should be shown for a product - REMOVED (Pro feature)
} 