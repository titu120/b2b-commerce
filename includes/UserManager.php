<?php
namespace B2B;

if ( ! defined( 'ABSPATH' ) ) exit;

class UserManager {
    public function __construct() {
        // Register roles and taxonomies
        add_action( 'init', [ $this, 'register_roles' ] );
        add_action( 'init', [ $this, 'register_group_taxonomy' ] );

        // Registration form hooks
        add_action( 'register_form', [ $this, 'registration_form' ] );
        add_action( 'user_register', [ $this, 'save_registration_fields' ] );
        // User profile fields
        add_action( 'show_user_profile', [ $this, 'user_group_field' ] );
        add_action( 'edit_user_profile', [ $this, 'user_group_field' ] );
        add_action( 'personal_options_update', [ $this, 'save_user_group_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_user_group_field' ] );
        add_action( 'admin_post_b2b_save_group', [ $this, 'save_group' ] );
        
        // Disable problematic WordPress terms list table for our taxonomy
        add_action('admin_init', [$this, 'disable_terms_list_table']);
        
        // User approval actions
        add_action( 'admin_post_b2b_approve_user', [ $this, 'approve_user' ] );
        add_action( 'admin_post_b2b_reject_user', [ $this, 'reject_user' ] );
    }

    // Disable WordPress terms list table for our custom taxonomy
    public function disable_terms_list_table() {
        global $pagenow;
        
        // Verify nonce for security
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'b2b_admin_nonce')) {
            return;
        }
        
        // Sanitize and validate GET parameters
        $taxonomy = sanitize_text_field(wp_unslash($_GET['taxonomy'] ?? ''));
        
        // Redirect any attempts to access edit-tags.php for our taxonomy
        if ($pagenow === 'edit-tags.php' && !empty($taxonomy) && $taxonomy === 'b2b_user_group') {
            wp_redirect(admin_url('admin.php?page=b2b-customer-groups'));
            exit;
        }
        
        // Also redirect edit-tag-form.php for our taxonomy
        if ($pagenow === 'edit-tag-form.php' && !empty($taxonomy) && $taxonomy === 'b2b_user_group') {
            wp_redirect(admin_url('admin.php?page=b2b-customer-groups'));
            exit;
        }
    }




    // Register activation and deactivation hooks
    public static function activate() {
        self::add_roles();
    }

    public static function deactivate() {
        self::remove_roles();
    }

    // Add B2B user roles
    public static function add_roles() {
        add_role( 'b2b_customer', __('B2B Customer', 'b2b-commerce'), [
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ] );
        add_role( 'wholesale_customer', __('Wholesale Customer', 'b2b-commerce'), [
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ] );
        add_role( 'distributor', __('Distributor', 'b2b-commerce'), [
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ] );
        add_role( 'retailer', __('Retailer', 'b2b-commerce'), [
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ] );
    }

    // Remove B2B user roles
    public static function remove_roles() {
        remove_role( 'b2b_customer' );
        remove_role( 'wholesale_customer' );
        remove_role( 'distributor' );
        remove_role( 'retailer' );
    }



    // Ensure roles exist on init
    public function register_roles() {
        self::add_roles();
    }

    // Register custom taxonomy for user groups
    public function register_group_taxonomy() {
        register_taxonomy(
            'b2b_user_group',
            'user',
            [
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'show_in_nav_menus' => false,
                'show_tagcloud' => false,
                'show_in_rest' => false,
                'hierarchical' => false,
                'labels' => [
                    'name' => __('Customer Groups', 'b2b-commerce'),
                    'singular_name' => __('Customer Group', 'b2b-commerce'),
                    'add_new_item' => __('Add New Group', 'b2b-commerce'),
                    'edit_item' => __('Edit Group', 'b2b-commerce'),
                    'search_items' => __('Search Groups', 'b2b-commerce'),
                    'not_found' => __('No groups found', 'b2b-commerce'),
                    'not_found_in_trash' => __('No groups found in trash', 'b2b-commerce'),
                ],
                'rewrite' => false,
                'capabilities' => [
                    'manage_terms' => 'manage_options',
                    'edit_terms' => 'manage_options',
                    'delete_terms' => 'manage_options',
                    'assign_terms' => 'edit_users',
                ],
            ]
        );
    }

    // Add group management to admin menu
    public function add_group_menu() {
        add_users_page( __('Customer Groups', 'b2b-commerce'), __('Customer Groups', 'b2b-commerce'), 'manage_options', 'b2b-customer-groups', [ $this, 'customer_groups_page' ] );
    }

    // Custom customer groups page to avoid WordPress terms table issues
    public function customer_groups_page() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Sanitize and validate GET parameters
        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? ''));
        $group_id = intval($_GET['group_id'] ?? 0);
        $saved = sanitize_text_field(wp_unslash($_GET['saved'] ?? ''));
        $deleted = sanitize_text_field(wp_unslash($_GET['deleted'] ?? ''));
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        
        // Display success messages
        if (!empty($saved)) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Group saved successfully!', 'b2b-commerce') . '</p></div>';
        }
        if (!empty($deleted)) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Group deleted successfully!', 'b2b-commerce') . '</p></div>';
        }
        
        if (!empty($action)) {
            // Verify nonce for action-based operations
            if ( ! wp_verify_nonce( $nonce, 'b2b_groups_action' ) ) {
                wp_die( esc_html__('Security check failed.', 'b2b-commerce') );
            }
            
            switch ($action) {
                case 'add':
                    $this->render_group_form();
                    break;
                case 'edit':
                    if ( $group_id <= 0 ) {
                        wp_die( esc_html__('Invalid group ID.', 'b2b-commerce') );
                    }
                    $this->render_group_form($group_id);
                    break;
                case 'delete':
                    if ( $group_id <= 0 ) {
                        wp_die( esc_html__('Invalid group ID.', 'b2b-commerce') );
                    }
                    $this->delete_group($group_id);
                    break;
                default:
                    $this->list_groups();
            }
        } else {
            $this->list_groups();
        }
    }

    // Handle saving groups
    public function save_group() {
        // Check user permissions first
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        
        // Verify nonce for security
        $nonce = sanitize_text_field(wp_unslash($_POST['b2b_group_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'b2b_save_group')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        // Sanitize and validate POST data
        $group_id = intval($_POST['group_id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['group_name'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['group_description'] ?? ''));
        
        // Validate required fields
        if (empty($name)) {
            wp_die(esc_html__('Group name is required.', 'b2b-commerce'));
        }
        
        // Validate group name length
        if (strlen($name) > 200) {
            wp_die(esc_html__('Group name is too long. Maximum 200 characters allowed.', 'b2b-commerce'));
        }
        
        if ($group_id) {
            // Update existing group
            $result = wp_update_term($group_id, 'b2b_user_group', [
                'name' => $name,
                'description' => $description
            ]);
            
            if (is_wp_error($result)) {
                wp_die(esc_html__('Error updating group: ', 'b2b-commerce') . esc_html($result->get_error_message()));
            }
        } else {
            // Create new group
            $result = wp_insert_term($name, 'b2b_user_group', [
                'description' => $description
            ]);
            
            if (is_wp_error($result)) {
                wp_die(esc_html__('Error creating group: ', 'b2b-commerce') . esc_html($result->get_error_message()));
            }
        }
        
        wp_redirect(admin_url('admin.php?page=b2b-customer-groups&saved=1'));
        exit;
    }

    // Add advanced registration fields
    public function registration_form() {
        // Check if this is a form submission with valid nonce for pre-filling values
        $is_form_submission = false;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'user_registration')) {
            $is_form_submission = true;
        }
        
        // Get sanitized values for form persistence
        $company_name = $is_form_submission ? sanitize_text_field(wp_unslash($_POST['company_name'] ?? '')) : '';
        $business_type = $is_form_submission ? sanitize_text_field(wp_unslash($_POST['business_type'] ?? '')) : '';
        $tax_id = $is_form_submission ? sanitize_text_field(wp_unslash($_POST['tax_id'] ?? '')) : '';
        ?>
        <?php wp_nonce_field('user_registration', '_wpnonce', true, true); ?>
        <p>
            <label for="company_name"><?php esc_html_e('Company Name', 'b2b-commerce'); ?><br/>
                <input type="text" name="company_name" id="company_name" class="input" value="<?php echo esc_attr($company_name); ?>" size="25" /></label>
        </p>
        <p>
            <label for="business_type"><?php esc_html_e('Business Type', 'b2b-commerce'); ?><br/>
                <input type="text" name="business_type" id="business_type" class="input" value="<?php echo esc_attr($business_type); ?>" size="25" /></label>
        </p>
        <p>
            <label for="tax_id"><?php esc_html_e('Tax ID', 'b2b-commerce'); ?><br/>
                <input type="text" name="tax_id" id="tax_id" class="input" value="<?php echo esc_attr($tax_id); ?>" size="25" /></label>
        </p>
        <p>
            <label for="user_role"><?php esc_html_e('Register as', 'b2b-commerce'); ?><br/>
                <select name="user_role" id="user_role">
                    <option value="b2b_customer"><?php esc_html_e('B2B Customer', 'b2b-commerce'); ?></option>
                    <option value="wholesale_customer"><?php esc_html_e('Wholesale Customer', 'b2b-commerce'); ?></option>
                    <option value="distributor"><?php esc_html_e('Distributor', 'b2b-commerce'); ?></option>
                    <option value="retailer"><?php esc_html_e('Retailer', 'b2b-commerce'); ?></option>
                </select>
            </label>
        </p>
        <?php
    }

    // Save registration fields
    public function save_registration_fields( $user_id ) {
        // Validate user_id
        if ( $user_id <= 0 ) {
            return;
        }
        
        // Verify nonce for security
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if ( ! wp_verify_nonce( $nonce, 'user_registration' ) ) {
            return;
        }
        
        // Sanitize and validate POST data
        if ( isset( $_POST['company_name'] ) ) {
            $company_name = sanitize_text_field( wp_unslash( $_POST['company_name'] ) );
            if ( ! empty( $company_name ) && strlen( $company_name ) <= 200 ) {
                update_user_meta( $user_id, 'company_name', $company_name );
            }
        }
        if ( isset( $_POST['business_type'] ) ) {
            $business_type = sanitize_text_field( wp_unslash( $_POST['business_type'] ) );
            if ( ! empty( $business_type ) && strlen( $business_type ) <= 100 ) {
                update_user_meta( $user_id, 'business_type', $business_type );
            }
        }
        if ( isset( $_POST['tax_id'] ) ) {
            $tax_id = sanitize_text_field( wp_unslash( $_POST['tax_id'] ) );
            if ( ! empty( $tax_id ) && strlen( $tax_id ) <= 50 ) {
                update_user_meta( $user_id, 'tax_id', $tax_id );
            }
        }
        if ( isset( $_POST['user_role'] ) ) {
            $role = sanitize_text_field( wp_unslash( $_POST['user_role'] ) );
            // Validate role against allowed roles
            $allowed_roles = ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'];
            if ( in_array( $role, $allowed_roles ) ) {
                $user = get_userdata( $user_id );
                if ( $user ) {
                    $user->set_role( $role );
                    // Set approval status to pending
                    update_user_meta( $user_id, 'b2b_approval_status', 'pending' );
                }
            }
        }
    }

    // Add approval menu in admin
    public function add_approval_menu() {
        add_users_page( __('B2B Approvals', 'b2b-commerce'), __('B2B Approvals', 'b2b-commerce'), 'manage_options', 'b2b-approvals', [ $this, 'approval_page' ] );
    }

    // Render approval page
    public function approval_page() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $pending_users = get_users( [
            'meta_key' => 'b2b_approval_status',
            'meta_value' => 'pending',
        ] );
        echo '<div class="wrap"><h1>' . esc_html__('B2B User Approvals', 'b2b-commerce') . '</h1>';
        if ( empty( $pending_users ) ) {
            echo '<p>' . esc_html__('No pending users.', 'b2b-commerce') . '</p></div>';
            return;
        }
        echo '<table class="widefat"><thead><tr><th>' . esc_html__('User', 'b2b-commerce') . '</th><th>' . esc_html__('Company', 'b2b-commerce') . '</th><th>' . esc_html__('Role', 'b2b-commerce') . '</th><th>' . esc_html__('Actions', 'b2b-commerce') . '</th></tr></thead><tbody>';
        foreach ( $pending_users as $user ) {
            echo '<tr>';
            echo '<td>' . esc_html( $user->user_login ) . '</td>';
            echo '<td>' . esc_html( get_user_meta( $user->ID, 'company_name', true ) ) . '</td>';
            echo '<td>' . esc_html( implode( ', ', $user->roles ) ) . '</td>';
            echo '<td>';
            $approve_nonce = wp_create_nonce( 'b2b_approve_user_' . $user->ID );
            $reject_nonce = wp_create_nonce( 'b2b_reject_user_' . $user->ID );
            echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=b2b_approve_user&user_id=' . $user->ID . '&_wpnonce=' . $approve_nonce ) ) . '" class="button">' . esc_html__('Approve', 'b2b-commerce') . '</a> ';
            echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=b2b_reject_user&user_id=' . $user->ID . '&_wpnonce=' . $reject_nonce ) ) . '" class="button">' . esc_html__('Reject', 'b2b-commerce') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    // Approve user
    public function approve_user() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Sanitize and validate GET parameters
        $user_id = intval($_GET['user_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        
        // Verify nonce for security
        if ( ! wp_verify_nonce( $nonce, 'b2b_approve_user_' . $user_id ) ) {
            wp_die( esc_html__('Security check failed.', 'b2b-commerce') );
        }
        
        // Validate user_id after nonce check
        if ( $user_id <= 0 ) {
            wp_die( esc_html__('Invalid user ID.', 'b2b-commerce') );
        }
        
        // Verify user exists
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_die( esc_html__('User not found.', 'b2b-commerce') );
        }
        
        update_user_meta( $user_id, 'b2b_approval_status', 'approved' );
        
        // Send approval email
        $subject = __('Your B2B Account Approved', 'b2b-commerce');
        $message = __('Congratulations! Your account has been approved.', 'b2b-commerce');
        wp_mail( $user->user_email, $subject, $message );
        
        wp_redirect( admin_url( 'admin.php?page=b2b-users&approved=1' ) );
        exit;
    }

    // Reject user
    public function reject_user() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Sanitize and validate GET parameters
        $user_id = intval($_GET['user_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        
        // Verify nonce for security
        if ( ! wp_verify_nonce( $nonce, 'b2b_reject_user_' . $user_id ) ) {
            wp_die( esc_html__('Security check failed.', 'b2b-commerce') );
        }
        
        // Validate user_id after nonce check
        if ( $user_id <= 0 ) {
            wp_die( esc_html__('Invalid user ID.', 'b2b-commerce') );
        }
        
        // Verify user exists
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_die( esc_html__('User not found.', 'b2b-commerce') );
        }
        
        update_user_meta( $user_id, 'b2b_approval_status', 'rejected' );
        
        // Send rejection email
        $subject = __('Your B2B Account Rejected', 'b2b-commerce');
        $message = __('Sorry, your account has been rejected.', 'b2b-commerce');
        wp_mail( $user->user_email, $subject, $message );
        
        wp_redirect( admin_url( 'admin.php?page=b2b-users&rejected=1' ) );
        exit;
    }

    // Show group field on user profile
    public function user_group_field( $user ) {
        $groups = get_terms( [ 'taxonomy' => 'b2b_user_group', 'hide_empty' => false ] );
        $user_groups = wp_get_object_terms( $user->ID, 'b2b_user_group', [ 'fields' => 'ids' ] );
        ?>
        <h3><?php esc_html_e('Customer Group', 'b2b-commerce'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="b2b_user_group"><?php esc_html_e('Group', 'b2b-commerce'); ?></label></th>
                <td>
                    <select name="b2b_user_group" id="b2b_user_group">
                        <option value=""><?php esc_html_e('None', 'b2b-commerce'); ?></option>
                        <?php foreach ( $groups as $group ) : ?>
                            <option value="<?php echo esc_attr( $group->term_id ); ?>" <?php selected( in_array( $group->term_id, $user_groups ) ); ?>><?php echo esc_html( $group->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    // Save group field from user profile
    public function save_user_group_field( $user_id ) {
        // Check user permissions first
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }
        
        // Validate user_id
        if ( $user_id <= 0 ) {
            return;
        }
        
        // Verify nonce for security
        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'update-user_' . $user_id ) ) {
            return;
        }
        
        // Sanitize and validate POST data
        $group = isset( $_POST['b2b_user_group'] ) ? intval( $_POST['b2b_user_group'] ) : 0;
        
        // Validate group exists if provided
        if ( $group > 0 ) {
            $term = get_term( $group, 'b2b_user_group' );
            if ( is_wp_error( $term ) || ! $term ) {
                return;
            }
        }
        
        wp_set_object_terms( $user_id, $group ? [ $group ] : [], 'b2b_user_group', false );
    }

    // Add import/export menu
    public function add_import_export_menu() {
        add_users_page( __('Import/Export Users', 'b2b-commerce'), __('Import/Export Users', 'b2b-commerce'), 'manage_options', 'b2b-import-export', [ $this, 'import_export_page' ] );
    }

    // Render import/export page
    public function import_export_page() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        echo '<div class="wrap"><h1>' . esc_html__('Bulk User Import/Export', 'b2b-commerce') . '</h1>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo wp_kses(wp_nonce_field('b2b_export_users', 'b2b_export_nonce', true, false), ['input' => ['type' => [], 'name' => [], 'value' => []]]);
        echo '<h2>' . esc_html__('Export Users', 'b2b-commerce') . '</h2>';
        echo '<input type="submit" name="b2b_export_users" class="button button-primary" value="' . esc_attr__('Export CSV', 'b2b-commerce') . '">';
        echo '</form>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo wp_kses(wp_nonce_field('b2b_import_users', 'b2b_import_nonce', true, false), ['input' => ['type' => [], 'name' => [], 'value' => []]]);
        echo '<h2>' . esc_html__('Import Users', 'b2b-commerce') . '</h2>';
        echo '<input type="file" name="b2b_import_file" accept=".csv">';
        echo '<input type="submit" name="b2b_import_users" class="button button-primary" value="' . esc_attr__('Import CSV', 'b2b-commerce') . '">';
        echo '</form></div>';

        // Handle export
        if ( isset( $_POST['b2b_export_users'] ) ) {
            $export_nonce = sanitize_text_field(wp_unslash($_POST['b2b_export_nonce'] ?? ''));
            if (!wp_verify_nonce($export_nonce, 'b2b_export_users')) {
                wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
            }
            $this->export_users_csv();
        }
        // Handle import
        if ( isset( $_POST['b2b_import_users'] ) && ! empty( $_FILES['b2b_import_file']['tmp_name'] ) ) {
            $import_nonce = sanitize_text_field(wp_unslash($_POST['b2b_import_nonce'] ?? ''));
            if (!wp_verify_nonce($import_nonce, 'b2b_import_users')) {
                wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
            }
            $file_path = sanitize_text_field($_FILES['b2b_import_file']['tmp_name']);
            $this->import_users_csv( $file_path );
        }
    }

    // Export users to CSV
    public function export_users_csv() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Initialize WP_Filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        WP_Filesystem();
        global $wp_filesystem;
        
        $users = get_users();
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="b2b-users-export.csv"' );
        
        // Create CSV content
        $csv_content = '';
        $csv_content .= 'user_email,user_role,company,group,approved' . "\n";
        
        foreach ( $users as $user ) {
            $group = wp_get_object_terms( $user->ID, 'b2b_user_group', [ 'fields' => 'names' ] );
            $csv_content .= sprintf( '%s,%s,%s,%s,%s' . "\n",
                $user->user_email,
                implode( ',', $user->roles ),
                get_user_meta( $user->ID, 'company_name', true ),
                implode( ',', $group ),
                get_user_meta( $user->ID, 'b2b_approval_status', true )
            );
        }
        
        // Output CSV content directly without escaping as it's already sanitized data
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $csv_content;
        exit;
    }

    // Import users from CSV
    public function import_users_csv( $file ) {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Validate file exists and is readable
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            wp_die(esc_html__('File not found or not readable.', 'b2b-commerce'));
        }
        
        // Validate file type and security
        $allowed_types = ['csv', 'txt'];
        $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            wp_die(esc_html__('Invalid file type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        // Check file size (max 5MB for user import)
        if (filesize($file) > 5 * 1024 * 1024) {
            wp_die(esc_html__('File too large. Maximum size is 5MB.', 'b2b-commerce'));
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file);
        finfo_close($finfo);
        $allowed_mimes = ['text/csv', 'text/plain', 'application/csv'];
        if (!in_array($mime_type, $allowed_mimes)) {
            wp_die(esc_html__('Invalid file MIME type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        // Initialize WP_Filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        WP_Filesystem();
        global $wp_filesystem;
        
        $file_content = $wp_filesystem->get_contents( $file );
        if ( ! $file_content ) {
            wp_die(esc_html__('Cannot read file.', 'b2b-commerce'));
        }
        
        $lines = explode( "\n", $file_content );
        if ( empty( $lines ) ) {
            wp_die(esc_html__('Invalid CSV file format.', 'b2b-commerce'));
        }
        
        $header = str_getcsv( $lines[0] );
        if ( empty( $header ) ) {
            wp_die(esc_html__('Invalid CSV file format.', 'b2b-commerce'));
        }
        
        $imported_count = 0;
        $errors = [];
        
        for ( $i = 1; $i < count( $lines ); $i++ ) {
            if ( empty( trim( $lines[$i] ) ) ) {
                continue; // Skip empty lines
            }
            
            $row = str_getcsv( $lines[$i] );
            if ( count( $row ) !== count( $header ) ) {
                $errors[] = esc_html__('Row has incorrect number of columns.', 'b2b-commerce');
                continue;
            }
            
            $data = array_combine( $header, $row );
            
            // Sanitize and validate data
            $email = sanitize_email( $data['user_email'] ?? '' );
            if ( ! is_email( $email ) ) {
                $errors[] = esc_html__('Invalid email address: ', 'b2b-commerce') . esc_html( $data['user_email'] ?? '' );
                continue;
            }
            
            $user = get_user_by( 'email', $email );
            if ( ! $user ) {
                $user_id = wp_create_user( $email, wp_generate_password(), $email );
                if ( is_wp_error( $user_id ) ) {
                    $errors[] = esc_html__('Error creating user: ', 'b2b-commerce') . esc_html($user_id->get_error_message());
                    continue;
                }
            } else {
                $user_id = $user->ID;
            }
            
            // Sanitize and validate role
            if ( ! empty( $data['user_role'] ) ) {
                $role = sanitize_text_field( $data['user_role'] );
                $allowed_roles = ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'];
                if ( in_array( $role, $allowed_roles ) ) {
                    $user_obj = get_userdata( $user_id );
                    if ( $user_obj ) {
                        $user_obj->set_role( $role );
                    }
                }
            }
            
            // Sanitize and validate company name
            if ( ! empty( $data['company'] ) ) {
                $company = sanitize_text_field( $data['company'] );
                if ( strlen( $company ) <= 200 ) {
                    update_user_meta( $user_id, 'company_name', $company );
                }
            }
            
            // Sanitize and validate group
            if ( ! empty( $data['group'] ) ) {
                $group_name = sanitize_text_field( $data['group'] );
                $group = get_term_by( 'name', $group_name, 'b2b_user_group' );
                if ( $group ) {
                    wp_set_object_terms( $user_id, [ $group->term_id ], 'b2b_user_group', false );
                }
            }
            
            // Sanitize and validate approval status
            if ( ! empty( $data['approved'] ) ) {
                $status = sanitize_text_field( $data['approved'] );
                $allowed_statuses = ['pending', 'approved', 'rejected'];
                if ( in_array( $status, $allowed_statuses ) ) {
                    update_user_meta( $user_id, 'b2b_approval_status', $status );
                }
            }
            
            $imported_count++;
        }
        
        // File content already processed, no need to close
        
        // translators: %d is the number of users processed
        $message = sprintf( __('Users imported successfully. %d users processed.', 'b2b-commerce'), $imported_count );
        if ( ! empty( $errors ) ) {
            // translators: %d is the number of errors that occurred
            $message .= ' ' . sprintf( __('%d errors occurred.', 'b2b-commerce'), count( $errors ) );
        }
        
        echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
    }

    // Advanced user management features
    public function group_management() {
        // Check user permissions first
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        
        // Sanitize and validate GET parameters
        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? 'list'));
        $group_id = intval($_GET['group_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        
        // Verify nonce for action-based operations
        if ($action !== 'list' && !wp_verify_nonce($nonce, 'b2b_groups_action')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        switch ($action) {
            case 'add':
            case 'edit':
                if ($action === 'edit' && $group_id <= 0) {
                    wp_die(esc_html__('Invalid group ID.', 'b2b-commerce'));
                }
                $this->render_group_form($group_id);
                break;
            case 'delete':
                if ($group_id <= 0) {
                    wp_die(esc_html__('Invalid group ID.', 'b2b-commerce'));
                }
                $this->delete_group($group_id);
                break;
            default:
                $this->list_groups();
                break;
        }
    }

    private function render_group_form($group_id = 0) {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $group = $group_id ? get_term($group_id, 'b2b_user_group') : null;
        $name = $group ? $group->name : '';
        $description = $group ? $group->description : '';
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html($group_id ? __('Edit Group', 'b2b-commerce') : __('Add New Group', 'b2b-commerce')) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="b2b_save_group">';
        echo wp_kses(wp_nonce_field('b2b_save_group', 'b2b_group_nonce', true, false), ['input' => ['type' => [], 'name' => [], 'value' => []]]);
        echo '<input type="hidden" name="group_id" value="' . esc_attr($group_id) . '">';
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html(__('Group Name', 'b2b-commerce')) . '</th><td><input type="text" name="group_name" value="' . esc_attr($name) . '" required></td></tr>';
        echo '<tr><th>' . esc_html(__('Description', 'b2b-commerce')) . '</th><td><textarea name="group_description" rows="3">' . esc_textarea($description) . '</textarea></td></tr>';
        echo '</table>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html(__('Save Group', 'b2b-commerce')) . '</button></p>';
        echo '</form></div>';
    }

    private function list_groups() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $groups = get_terms(['taxonomy' => 'b2b_user_group', 'hide_empty' => false]);
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html(__('Customer Groups', 'b2b-commerce')) . '</h2>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=b2b-customer-groups&action=add')) . '" class="button">' . esc_html(__('Add New Group', 'b2b-commerce')) . '</a></p>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html(__('Group Name', 'b2b-commerce')) . '</th><th>' . esc_html(__('Description', 'b2b-commerce')) . '</th><th>' . esc_html(__('Members', 'b2b-commerce')) . '</th><th>' . esc_html(__('Actions', 'b2b-commerce')) . '</th></tr></thead><tbody>';
        
        foreach ($groups as $group) {
            $member_count = $group->count;
            echo '<tr>';
            echo '<td>' . esc_html($group->name) . '</td>';
            echo '<td>' . esc_html($group->description) . '</td>';
            echo '<td>' . esc_html($member_count) . ' ' . esc_html(__('members', 'b2b-commerce')) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-customer-groups&action=edit&group_id=' . $group->term_id)) . '" class="button">' . esc_html(__('Edit', 'b2b-commerce')) . '</a> ';
            echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-customer-groups&action=delete&group_id=' . $group->term_id)) . '" class="button" onclick="return confirm(\'' . esc_js(__('Delete this group?', 'b2b-commerce')) . '\')">' . esc_html(__('Delete', 'b2b-commerce')) . '</a>';
            echo '</td></tr>';
        }
        
        echo '</tbody></table></div>';
    }

    private function delete_group($group_id) {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Sanitize and validate GET parameters
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        
        if (!wp_verify_nonce($nonce, 'delete_group_' . $group_id)) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        // Validate group exists before deletion
        $term = get_term($group_id, 'b2b_user_group');
        if (is_wp_error($term) || !$term) {
            wp_die(esc_html__('Group not found.', 'b2b-commerce'));
        }
        
        $result = wp_delete_term($group_id, 'b2b_user_group');
        if (is_wp_error($result)) {
            wp_die(esc_html__('Error deleting group: ', 'b2b-commerce') . esc_html($result->get_error_message()));
        }
        
        wp_redirect(admin_url('admin.php?page=b2b-customer-groups&deleted=1'));
        exit;
    }

    public function bulk_import_export() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Sanitize and validate GET parameters
        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? 'export'));
        
        // Verify nonce for security
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'b2b_import_export_action')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        if ($action === 'import' && isset($_POST['b2b_import_nonce'])) {
            // Verify nonce for import action
            $import_nonce = sanitize_text_field(wp_unslash($_POST['b2b_import_nonce'] ?? ''));
            if (!wp_verify_nonce($import_nonce, 'b2b_import_users')) {
                wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
            }
            $this->handle_bulk_import();
        } else {
            $this->render_import_export_interface();
        }
    }

    private function render_import_export_interface() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html(__('Bulk Import/Export Users', 'b2b-commerce')) . '</h2>';
        
        // Export Section
        echo '<h3>' . esc_html(__('Export Users', 'b2b-commerce')) . '</h3>';
        echo '<p>' . esc_html(__('Export all B2B users to CSV format.', 'b2b-commerce')) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="b2b_export_users">';
        echo wp_kses(wp_nonce_field('b2b_export_users', 'b2b_export_nonce', true, false), ['input' => ['type' => [], 'name' => [], 'value' => []]]);
        echo '<p><button type="submit" class="button">' . esc_html(__('Export Users', 'b2b-commerce')) . '</button></p>';
        echo '</form>';
        
        // Import Section
        echo '<h3>' . esc_html(__('Import Users', 'b2b-commerce')) . '</h3>';
        echo '<p>' . esc_html(__('Import users from CSV file.', 'b2b-commerce')) . ' <a href="#" onclick="showImportTemplate()">' . esc_html(__('Download template', 'b2b-commerce')) . '</a></p>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo wp_kses(wp_nonce_field('b2b_import_users', 'b2b_import_nonce', true, false), ['input' => ['type' => [], 'name' => [], 'value' => []]]);
        echo '<p><input type="file" name="csv_file" accept=".csv" required></p>';
        echo '<p><label><input type="checkbox" name="send_welcome_email" value="1"> ' . esc_html(__('Send welcome email to new users', 'b2b-commerce')) . '</label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html(__('Import Users', 'b2b-commerce')) . '</button></p>';
        echo '</form></div>';
        
        echo '<script>
        function showImportTemplate() {
            var template = "username,email,first_name,last_name,company_name,business_type,tax_id,role,groups\\n";
            template += "john_doe,john@company.com,John,Doe,ABC Company,Retail,123456789,b2b_customer,wholesale\\n";
            var blob = new Blob([template], {type: "text/csv"});
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "b2b_users_template.csv";
            a.click();
        }
        </script>';
    }

    private function handle_bulk_import() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Sanitize and validate POST data
        $import_nonce = sanitize_text_field(wp_unslash($_POST['b2b_import_nonce'] ?? ''));
        if (!wp_verify_nonce($import_nonce, 'b2b_import_users')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        // Validate $_FILES array exists and has required keys
        if (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
            wp_die(esc_html__('No file uploaded.', 'b2b-commerce'));
        }
        
        // Validate file upload error
        if (!isset($_FILES['csv_file']['error']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(esc_html__('File upload failed.', 'b2b-commerce'));
        }
        
        // Validate file name exists
        if (!isset($_FILES['csv_file']['name']) || empty($_FILES['csv_file']['name'])) {
            wp_die(esc_html__('Invalid file name.', 'b2b-commerce'));
        }
        
        // Validate file size exists
        if (!isset($_FILES['csv_file']['size'])) {
            wp_die(esc_html__('Invalid file size.', 'b2b-commerce'));
        }
        
        // Validate file temp name exists
        if (!isset($_FILES['csv_file']['tmp_name']) || empty($_FILES['csv_file']['tmp_name'])) {
            wp_die(esc_html__('Invalid file.', 'b2b-commerce'));
        }
        
        // Validate file type and security
        $allowed_types = ['csv', 'txt'];
        $file_extension = strtolower(pathinfo(sanitize_file_name($_FILES['csv_file']['name']), PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            wp_die(esc_html__('Invalid file type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        // Check file size (max 5MB for user import)
        if ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
            wp_die(esc_html__('File too large. Maximum size is 5MB.', 'b2b-commerce'));
        }
        
        // Validate MIME type
        $file_tmp_name = sanitize_text_field($_FILES['csv_file']['tmp_name']);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp_name);
        finfo_close($finfo);
        $allowed_mimes = ['text/csv', 'text/plain', 'application/csv'];
        if (!in_array($mime_type, $allowed_mimes)) {
            wp_die(esc_html__('Invalid file MIME type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        // Initialize WP_Filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        WP_Filesystem();
        global $wp_filesystem;
        
        $file = sanitize_text_field($_FILES['csv_file']['tmp_name']);
        $file_content = $wp_filesystem->get_contents($file);
        
        if (!$file_content) {
            wp_die(esc_html__('Cannot read file.', 'b2b-commerce'));
        }
        
        $lines = explode("\n", $file_content);
        if (empty($lines)) {
            wp_die(esc_html__('Invalid CSV file format.', 'b2b-commerce'));
        }
        
        $headers = str_getcsv($lines[0]);
        if (empty($headers)) {
            wp_die(esc_html__('Invalid CSV file format.', 'b2b-commerce'));
        }
        
        $imported = 0;
        $errors = [];
        
        for ($i = 1; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) {
                continue; // Skip empty lines
            }
            
            $data = str_getcsv($lines[$i]);
            if (count($data) !== count($headers)) {
                $errors[] = esc_html__('Row has incorrect number of columns.', 'b2b-commerce');
                continue;
            }
            
            $user_data = array_combine($headers, $data);
            
            try {
                $user_id = $this->create_user_from_import($user_data);
                if ($user_id) {
                    $imported++;
                    
                    $send_welcome = sanitize_text_field(wp_unslash($_POST['send_welcome_email'] ?? ''));
                    if ($send_welcome === '1') {
                        $this->send_welcome_email($user_id);
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Row ' . ($imported + 1) . ': ' . esc_html($e->getMessage());
            }
        }
        
        // File content already processed, no need to close
        
        // translators: %d is the number of users imported
        $message = sprintf(__('Imported %d users successfully.', 'b2b-commerce'), $imported);
        if (!empty($errors)) {
            // translators: %s is a comma-separated list of error messages
            $message .= ' ' . sprintf(__('Errors: %s', 'b2b-commerce'), esc_html(implode(', ', $errors)));
        }
        
        wp_redirect(admin_url('admin.php?page=b2b-import-export&imported=' . $imported . '&errors=' . count($errors)));
        exit;
    }

    private function create_user_from_import($user_data) {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $username = sanitize_user($user_data['username']);
        $email = sanitize_email($user_data['email']);
        
        if (username_exists($username) || email_exists($email)) {
            // translators: %s is the username that already exists
            throw new Exception(sprintf(esc_html__('User already exists: %s', 'b2b-commerce'), esc_html($username)));
        }
        
        $user_id = wp_create_user($username, wp_generate_password(), $email);
        
        if (is_wp_error($user_id)) {
            throw new Exception(esc_html($user_id->get_error_message()));
        }
        
        // Set user data
        wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($user_data['first_name']),
            'last_name' => sanitize_text_field($user_data['last_name']),
            'display_name' => sanitize_text_field($user_data['first_name'] . ' ' . $user_data['last_name'])
        ]);
        
        // Set role
        $role = sanitize_text_field($user_data['role']);
        if (in_array($role, ['b2b_customer', 'wholesale_customer', 'distributor', 'retailer'])) {
            $user = new WP_User($user_id);
            $user->set_role($role);
        }
        
        // Set meta fields
        update_user_meta($user_id, 'company_name', sanitize_text_field($user_data['company_name']));
        update_user_meta($user_id, 'business_type', sanitize_text_field($user_data['business_type']));
        update_user_meta($user_id, 'tax_id', sanitize_text_field($user_data['tax_id']));
        
        // Set groups
        if (!empty($user_data['groups'])) {
            $groups = array_map('trim', explode(',', $user_data['groups']));
            wp_set_object_terms($user_id, $groups, 'b2b_user_group');
        }
        
        return $user_id;
    }

    public function email_notifications() {
        // Check user permissions first
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        
        // Sanitize and validate GET parameters
        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? 'list'));
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        
        // Verify nonce for action-based operations
        if ($action !== 'list' && !wp_verify_nonce($nonce, 'b2b_email_action')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_email_template_form();
                break;
            case 'test':
                $this->test_email_template();
                break;
            default:
                $this->list_email_templates();
                break;
        }
    }

    private function render_email_template_form() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        // Verify nonce for security
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'b2b_email_action')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        // Sanitize and validate GET parameters
        $template_id = intval($_GET['template_id'] ?? 0);
        
        // Validate template_id if provided
        if ($template_id > 0) {
            $template = get_option("b2b_email_template_$template_id");
            if (!$template) {
                wp_die(esc_html__('Template not found.', 'b2b-commerce'));
            }
        } else {
            $template = null;
        }
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html($template_id ? __('Edit Email Template', 'b2b-commerce') : __('Add Email Template', 'b2b-commerce')) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="b2b_save_email_template">';
        echo wp_kses(wp_nonce_field('b2b_save_email_template', 'b2b_email_nonce', true, false), ['input' => ['type' => [], 'name' => [], 'value' => []]]);
        echo '<input type="hidden" name="template_id" value="' . esc_attr($template_id) . '">';
        
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html(__('Template Name', 'b2b-commerce')) . '</th><td><input type="text" name="template_name" value="' . esc_attr($template['name'] ?? '') . '" required></td></tr>';
        echo '<tr><th>' . esc_html(__('Subject', 'b2b-commerce')) . '</th><td><input type="text" name="subject" value="' . esc_attr($template['subject'] ?? '') . '" required></td></tr>';
        echo '<tr><th>' . esc_html(__('Message', 'b2b-commerce')) . '</th><td><textarea name="message" rows="10" cols="50">' . esc_textarea($template['message'] ?? '') . '</textarea></td></tr>';
        echo '<tr><th>' . esc_html(__('Trigger', 'b2b-commerce')) . '</th><td><select name="trigger">';
        echo '<option value="user_approved"' . selected($template['trigger'] ?? '', 'user_approved', false) . '>' . esc_html(__('User Approved', 'b2b-commerce')) . '</option>';
        echo '<option value="user_rejected"' . selected($template['trigger'] ?? '', 'user_rejected', false) . '>' . esc_html(__('User Rejected', 'b2b-commerce')) . '</option>';
        echo '<option value="welcome_email"' . selected($template['trigger'] ?? '', 'welcome_email', false) . '>' . esc_html(__('Welcome Email', 'b2b-commerce')) . '</option>';
        echo '<option value="order_confirmation"' . selected($template['trigger'] ?? '', 'order_confirmation', false) . '>' . esc_html(__('Order Confirmation', 'b2b-commerce')) . '</option>';
        echo '</select></td></tr>';
        echo '</table>';
        
        echo '<p><button type="submit" class="button button-primary">' . esc_html(__('Save Template', 'b2b-commerce')) . '</button></p>';
        echo '</form></div>';
    }

    private function list_email_templates() {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $templates = [];
        for ($i = 1; $i <= 10; $i++) {
            $template = get_option("b2b_email_template_$i");
            if ($template) {
                $templates[$i] = $template;
            }
        }
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html(__('Email Templates', 'b2b-commerce')) . '</h2>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=b2b-emails&action=add')) . '" class="button">' . esc_html(__('Add New Template', 'b2b-commerce')) . '</a></p>';
        
        if (empty($templates)) {
            echo '<p>' . esc_html(__('No email templates found.', 'b2b-commerce')) . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html(__('Template Name', 'b2b-commerce')) . '</th><th>' . esc_html(__('Trigger', 'b2b-commerce')) . '</th><th>' . esc_html(__('Subject', 'b2b-commerce')) . '</th><th>' . esc_html(__('Actions', 'b2b-commerce')) . '</th></tr></thead><tbody>';
            
            foreach ($templates as $id => $template) {
                echo '<tr>';
                echo '<td>' . esc_html($template['name']) . '</td>';
                echo '<td>' . esc_html($template['trigger']) . '</td>';
                echo '<td>' . esc_html($template['subject']) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-emails&action=edit&template_id=' . $id)) . '" class="button">' . esc_html(__('Edit', 'b2b-commerce')) . '</a> ';
                echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-emails&action=test&template_id=' . $id)) . '" class="button">' . esc_html(__('Test', 'b2b-commerce')) . '</a>';
                echo '</td></tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }

    private function test_email_template() {
        // Check user permissions first
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce'));
        }
        
        // Sanitize and validate GET parameters
        $template_id = intval($_GET['template_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        
        // Verify nonce for security
        if (!wp_verify_nonce($nonce, 'b2b_email_action')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        if ($template_id <= 0) {
            wp_die(esc_html__('Invalid template ID.', 'b2b-commerce'));
        }
        
        $template = get_option("b2b_email_template_$template_id");
        
        if (!$template) {
            wp_die(esc_html__('Template not found.', 'b2b-commerce'));
        }
        
        $current_user = wp_get_current_user();
        $test_message = $this->parse_email_template($template['message'], $current_user);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($current_user->user_email, $template['subject'], $test_message, $headers);
        
        if ($sent) {
            wp_redirect(admin_url('admin.php?page=b2b-emails&test_sent=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=b2b-emails&test_failed=1'));
        }
        exit;
    }

    private function parse_email_template($message, $user) {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $replacements = [
            '{user_name}' => $user->display_name,
            '{user_email}' => $user->user_email,
            '{company_name}' => get_user_meta($user->ID, 'company_name', true),
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url'),
            '{admin_email}' => get_option('admin_email')
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    private function send_welcome_email($user_id) {
        // Check user permissions first
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $user = get_user_by('id', $user_id);
        $template = get_option('b2b_email_template_welcome');
        
        if (!$template) {
            // Default welcome email
            $subject = 'Welcome to ' . get_bloginfo('name');
            $message = "Hello {user_name},\n\nWelcome to our B2B platform! Your account has been created successfully.\n\nBest regards,\n" . get_bloginfo('name');
        } else {
            $subject = $template['subject'];
            $message = $template['message'];
        }
        
        $parsed_message = $this->parse_email_template($message, $user);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        return wp_mail($user->user_email, $subject, $parsed_message, $headers);
    }
} 