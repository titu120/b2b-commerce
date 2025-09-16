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
        
        // Redirect any attempts to access edit-tags.php for our taxonomy
        if ($pagenow === 'edit-tags.php' && isset($_GET['taxonomy']) && sanitize_text_field(wp_unslash($_GET['taxonomy'])) === 'b2b_user_group') {
            wp_redirect(admin_url('admin.php?page=b2b-customer-groups'));
            exit;
        }
        
        // Also redirect edit-tag-form.php for our taxonomy
        if ($pagenow === 'edit-tag-form.php' && isset($_GET['taxonomy']) && sanitize_text_field(wp_unslash($_GET['taxonomy'])) === 'b2b_user_group') {
            wp_redirect(admin_url('admin.php?page=b2b-customer-groups'));
            exit;
        }
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
        // Display success messages
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Group saved successfully!', 'b2b-commerce') . '</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Group deleted successfully!', 'b2b-commerce') . '</p></div>';
        }
        
        if (isset($_GET['action'])) {
            $action = sanitize_text_field(wp_unslash($_GET['action']));
            switch ($action) {
                case 'add':
                    $this->render_group_form();
                    break;
                case 'edit':
                    $group_id = intval($_GET['group_id'] ?? 0);
                    $this->render_group_form($group_id);
                    break;
                case 'delete':
                    $group_id = intval($_GET['group_id'] ?? 0);
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
        if (!wp_verify_nonce(wp_unslash($_POST['b2b_group_nonce'] ?? ''), 'b2b_save_group')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        $group_id = intval($_POST['group_id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['group_name'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['group_description'] ?? ''));
        
        if (empty($name)) {
            wp_die(esc_html__('Group name is required.', 'b2b-commerce'));
        }
        
        if ($group_id) {
            // Update existing group
            wp_update_term($group_id, 'b2b_user_group', [
                'name' => $name,
                'description' => $description
            ]);
        } else {
            // Create new group
            wp_insert_term($name, 'b2b_user_group', [
                'description' => $description
            ]);
        }
        
        wp_redirect(admin_url('admin.php?page=b2b-customer-groups&saved=1'));
        exit;
    }

    // Add advanced registration fields
    public function registration_form() {
        ?>
        <p>
            <label for="company_name"><?php esc_html_e('Company Name', 'b2b-commerce'); ?><br/>
                <input type="text" name="company_name" id="company_name" class="input" value="<?php echo esc_attr( wp_unslash($_POST['company_name'] ?? '') ); ?>" size="25" /></label>
        </p>
        <p>
            <label for="business_type"><?php esc_html_e('Business Type', 'b2b-commerce'); ?><br/>
                <input type="text" name="business_type" id="business_type" class="input" value="<?php echo esc_attr( wp_unslash($_POST['business_type'] ?? '') ); ?>" size="25" /></label>
        </p>
        <p>
            <label for="tax_id"><?php esc_html_e('Tax ID', 'b2b-commerce'); ?><br/>
                <input type="text" name="tax_id" id="tax_id" class="input" value="<?php echo esc_attr( wp_unslash($_POST['tax_id'] ?? '') ); ?>" size="25" /></label>
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
        // Verify nonce for security
        if ( ! wp_verify_nonce( sanitize_text_field(wp_unslash( $_POST['_wpnonce'] ?? '' )), 'register_nonce' ) ) {
            return;
        }
        
        if ( isset( $_POST['company_name'] ) ) {
            update_user_meta( $user_id, 'company_name', sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) );
        }
        if ( isset( $_POST['business_type'] ) ) {
            update_user_meta( $user_id, 'business_type', sanitize_text_field( wp_unslash( $_POST['business_type'] ) ) );
        }
        if ( isset( $_POST['tax_id'] ) ) {
            update_user_meta( $user_id, 'tax_id', sanitize_text_field( wp_unslash( $_POST['tax_id'] ) ) );
        }
        if ( isset( $_POST['user_role'] ) ) {
            $role = sanitize_text_field( wp_unslash( $_POST['user_role'] ) );
            $user = get_userdata( $user_id );
            $user->set_role( $role );
            // Set approval status to pending
            update_user_meta( $user_id, 'b2b_approval_status', 'pending' );
        }
    }

    // Add approval menu in admin
    public function add_approval_menu() {
        add_users_page( __('B2B Approvals', 'b2b-commerce'), __('B2B Approvals', 'b2b-commerce'), 'manage_options', 'b2b-approvals', [ $this, 'approval_page' ] );
    }

    // Render approval page
    public function approval_page() {
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
            echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=b2b_approve_user&user_id=' . $user->ID ) ) . '" class="button">' . esc_html__('Approve', 'b2b-commerce') . '</a> ';
            echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=b2b_reject_user&user_id=' . $user->ID ) ) . '" class="button">' . esc_html__('Reject', 'b2b-commerce') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    // Approve user
    public function approve_user() {
        if ( ! current_user_can( 'manage_options' ) || empty( $_GET['user_id'] ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $user_id = intval( $_GET['user_id'] );
        
        // Verify nonce for security
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'b2b_approve_user_' . $user_id ) ) {
            wp_die( esc_html__('Security check failed.', 'b2b-commerce') );
        }
        
        update_user_meta( $user_id, 'b2b_approval_status', 'approved' );
        // Send approval email
        wp_mail( get_userdata( $user_id )->user_email, esc_html__('Your B2B Account Approved', 'b2b-commerce'), esc_html__('Congratulations! Your account has been approved.', 'b2b-commerce') );
        wp_redirect( admin_url( 'admin.php?page=b2b-users&approved=1' ) );
        exit;
    }

    // Reject user
    public function reject_user() {
        if ( ! current_user_can( 'manage_options' ) || empty( $_GET['user_id'] ) ) {
            wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'b2b-commerce') );
        }
        
        $user_id = intval( $_GET['user_id'] );
        
        // Verify nonce for security
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'b2b_reject_user_' . $user_id ) ) {
            wp_die( esc_html__('Security check failed.', 'b2b-commerce') );
        }
        
        update_user_meta( $user_id, 'b2b_approval_status', 'rejected' );
        // Send rejection email
        wp_mail( get_userdata( $user_id )->user_email, esc_html__('Your B2B Account Rejected', 'b2b-commerce'), esc_html__('Sorry, your account has been rejected.', 'b2b-commerce') );
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
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        
        // Verify nonce for security
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'update-user_' . $user_id ) ) {
            return;
        }
        
        $group = isset( $_POST['b2b_user_group'] ) ? intval( wp_unslash( $_POST['b2b_user_group'] ) ) : '';
        wp_set_object_terms( $user_id, $group ? [ $group ] : [], 'b2b_user_group', false );
    }

    // Add import/export menu
    public function add_import_export_menu() {
        add_users_page( __('Import/Export Users', 'b2b-commerce'), __('Import/Export Users', 'b2b-commerce'), 'manage_options', 'b2b-import-export', [ $this, 'import_export_page' ] );
    }

    // Render import/export page
    public function import_export_page() {
        echo '<div class="wrap"><h1>' . esc_html__('Bulk User Import/Export', 'b2b-commerce') . '</h1>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo '<h2>' . esc_html__('Export Users', 'b2b-commerce') . '</h2>';
        echo '<input type="submit" name="b2b_export_users" class="button button-primary" value="' . esc_attr__('Export CSV', 'b2b-commerce') . '">';
        echo '</form>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo '<h2>' . esc_html__('Import Users', 'b2b-commerce') . '</h2>';
        echo '<input type="file" name="b2b_import_file" accept=".csv">';
        echo '<input type="submit" name="b2b_import_users" class="button button-primary" value="' . esc_attr__('Import CSV', 'b2b-commerce') . '">';
        echo '</form></div>';

        // Handle export
        if ( isset( $_POST['b2b_export_users'] ) ) {
            // Verify nonce for security
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'b2b_export_users' ) ) {
                wp_die( esc_html__('Security check failed.', 'b2b-commerce') );
            }
            $this->export_users_csv();
        }
        // Handle import
        if ( isset( $_POST['b2b_import_users'] ) && ! empty( $_FILES['b2b_import_file']['tmp_name'] ) ) {
            // Verify nonce for security
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'b2b_import_users' ) ) {
                wp_die( esc_html__('Security check failed.', 'b2b-commerce') );
            }
            $this->import_users_csv( sanitize_text_field( wp_unslash( $_FILES['b2b_import_file']['tmp_name'] ) ) );
        }
    }

    // Export users to CSV
    public function export_users_csv() {
        $users = get_users();
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="b2b-users-export.csv"' );
        
        // Use WP_Filesystem for file operations
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;
        
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
        
        echo wp_kses_post($csv_content);
        exit;
    }

    // Import users from CSV
    public function import_users_csv( $file ) {
        // Validate file type
        $allowed_types = ['csv', 'txt'];
        $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            wp_die(esc_html__('Invalid file type. Only CSV and TXT files are allowed.', 'b2b-commerce'));
        }
        
        // Use WP_Filesystem for file operations
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;
        
        $file_content = $wp_filesystem->get_contents( $file );
        if ( ! $file_content ) return;
        
        $lines = explode( "\n", $file_content );
        $header = str_getcsv( array_shift( $lines ) );
        
        foreach ( $lines as $line ) {
            if ( empty( trim( $line ) ) ) continue;
            $row = str_getcsv( $line );
            if ( count( $row ) !== count( $header ) ) continue;
            
            $data = array_combine( $header, $row );
            $user = get_user_by( 'email', $data['user_email'] );
            if ( ! $user ) {
                $user_id = wp_create_user( $data['user_email'], wp_generate_password(), $data['user_email'] );
            } else {
                $user_id = $user->ID;
            }
            if ( ! empty( $data['user_role'] ) ) {
                $user_obj = get_userdata( $user_id );
                $user_obj->set_role( $data['user_role'] );
            }
            if ( ! empty( $data['company'] ) ) {
                update_user_meta( $user_id, 'company_name', $data['company'] );
            }
            if ( ! empty( $data['group'] ) ) {
                $group = get_term_by( 'name', $data['group'], 'b2b_user_group' );
                if ( $group ) {
                    wp_set_object_terms( $user_id, [ $group->term_id ], 'b2b_user_group', false );
                }
            }
            if ( ! empty( $data['approved'] ) ) {
                update_user_meta( $user_id, 'b2b_approval_status', $data['approved'] );
            }
        }
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Users imported successfully.', 'b2b-commerce') . '</p></div>';
    }

    // Advanced user management features
    public function group_management() {
        if (!current_user_can('manage_options')) return;
        
        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? 'list'));
        $group_id = intval($_GET['group_id'] ?? 0);
        
        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_group_form($group_id);
                break;
            case 'delete':
                $this->delete_group($group_id);
                break;
            default:
                $this->list_groups();
                break;
        }
    }

    private function render_group_form($group_id = 0) {
        $group = $group_id ? get_term($group_id, 'b2b_user_group') : null;
        $name = $group ? $group->name : '';
        $description = $group ? $group->description : '';
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . ($group_id ? esc_html__('Edit Group', 'b2b-commerce') : esc_html__('Add New Group', 'b2b-commerce')) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="b2b_save_group">';
        echo wp_kses_post(wp_nonce_field('b2b_save_group', 'b2b_group_nonce', true, false));
        echo '<input type="hidden" name="group_id" value="' . esc_attr($group_id) . '">';
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Group Name', 'b2b-commerce') . '</th><td><input type="text" name="group_name" value="' . esc_attr($name) . '" required></td></tr>';
        echo '<tr><th>' . esc_html__('Description', 'b2b-commerce') . '</th><td><textarea name="group_description" rows="3">' . esc_textarea($description) . '</textarea></td></tr>';
        echo '</table>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Group', 'b2b-commerce') . '</button></p>';
        echo '</form></div>';
    }

    private function list_groups() {
        $groups = get_terms(['taxonomy' => 'b2b_user_group', 'hide_empty' => false]);
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html__('Customer Groups', 'b2b-commerce') . '</h2>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=b2b-customer-groups&action=add')) . '" class="button">' . esc_html__('Add New Group', 'b2b-commerce') . '</a></p>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('Group Name', 'b2b-commerce') . '</th><th>' . esc_html__('Description', 'b2b-commerce') . '</th><th>' . esc_html__('Members', 'b2b-commerce') . '</th><th>' . esc_html__('Actions', 'b2b-commerce') . '</th></tr></thead><tbody>';
        
        foreach ($groups as $group) {
            $member_count = $group->count;
            echo '<tr>';
            echo '<td>' . esc_html($group->name) . '</td>';
            echo '<td>' . esc_html($group->description) . '</td>';
            echo '<td>' . esc_html($member_count) . ' ' . esc_html__('members', 'b2b-commerce') . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-customer-groups&action=edit&group_id=' . $group->term_id)) . '" class="button">' . esc_html__('Edit', 'b2b-commerce') . '</a> ';
            echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-customer-groups&action=delete&group_id=' . $group->term_id)) . '" class="button" onclick="return confirm(\'' . esc_js('Delete this group?') . '\')">' . esc_html__('Delete', 'b2b-commerce') . '</a>';
            echo '</td></tr>';
        }
        
        echo '</tbody></table></div>';
    }

    private function delete_group($group_id) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'delete_group_' . $group_id)) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        wp_delete_term($group_id, 'b2b_user_group');
        wp_redirect(admin_url('admin.php?page=b2b-customer-groups&deleted=1'));
        exit;
    }

    public function bulk_import_export() {
        if (!current_user_can('manage_options')) return;
        
        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? 'export'));
        
        if ($action === 'import' && isset($_POST['b2b_import_nonce'])) {
            $this->handle_bulk_import();
        } else {
            $this->render_import_export_interface();
        }
    }

    private function render_import_export_interface() {
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html__('Bulk Import/Export Users', 'b2b-commerce') . '</h2>';
        
        // Export Section
        echo '<h3>' . esc_html__('Export Users', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html__('Export all B2B users to CSV format.', 'b2b-commerce') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="b2b_export_users">';
        echo wp_kses_post(wp_nonce_field('b2b_export_users', 'b2b_export_nonce', true, false));
        echo '<p><button type="submit" class="button">' . esc_html__('Export Users', 'b2b-commerce') . '</button></p>';
        echo '</form>';
        
        // Import Section
        echo '<h3>' . esc_html__('Import Users', 'b2b-commerce') . '</h3>';
        echo '<p>' . esc_html__('Import users from CSV file.', 'b2b-commerce') . ' <a href="#" onclick="showImportTemplate()">' . esc_html__('Download template', 'b2b-commerce') . '</a></p>';
        echo '<form method="post" enctype="multipart/form-data">';
        echo wp_kses_post(wp_nonce_field('b2b_import_users', 'b2b_import_nonce', true, false));
        echo '<p><input type="file" name="csv_file" accept=".csv" required></p>';
        echo '<p><label><input type="checkbox" name="send_welcome_email" value="1"> ' . esc_html__('Send welcome email to new users', 'b2b-commerce') . '</label></p>';
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Import Users', 'b2b-commerce') . '</button></p>';
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
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['b2b_import_nonce'] ?? '')), 'b2b_import_users')) {
            wp_die(esc_html__('Security check failed.', 'b2b-commerce'));
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(esc_html__('File upload failed.', 'b2b-commerce'));
        }
        
        $file = sanitize_text_field(wp_unslash($_FILES['csv_file']['tmp_name']));
        
        // Use WP_Filesystem for file operations
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
        $headers = str_getcsv( array_shift( $lines ) );
        $imported = 0;
        $errors = [];
        
        foreach ( $lines as $line ) {
            if ( empty( trim( $line ) ) ) continue;
            $data = str_getcsv( $line );
            if ( count( $data ) !== count( $headers ) ) continue;
            
            $user_data = array_combine($headers, $data);
            
            try {
                $user_id = $this->create_user_from_import($user_data);
                if ($user_id) {
                    $imported++;
                    
                    if (isset($_POST['send_welcome_email']) && sanitize_text_field(wp_unslash($_POST['send_welcome_email']))) {
                        $this->send_welcome_email($user_id);
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Row ' . ($imported + 1) . ': ' . $e->getMessage();
            }
        }
        
        $message = "Imported $imported users successfully.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }
        
        wp_redirect(admin_url('admin.php?page=b2b-import-export&imported=' . $imported . '&errors=' . count($errors)));
        exit;
    }

    private function create_user_from_import($user_data) {
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
        if (!current_user_can('manage_options')) return;
        
        $action = sanitize_text_field(wp_unslash($_GET['action'] ?? 'list'));
        
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
        $template_id = intval($_GET['template_id'] ?? 0);
        $template = $template_id ? get_option("b2b_email_template_$template_id") : null;
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . ($template_id ? esc_html__('Edit Email Template', 'b2b-commerce') : esc_html__('Add Email Template', 'b2b-commerce')) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="b2b_save_email_template">';
        echo wp_kses_post(wp_nonce_field('b2b_save_email_template', 'b2b_email_nonce', true, false));
        echo '<input type="hidden" name="template_id" value="' . esc_attr($template_id) . '">';
        
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Template Name', 'b2b-commerce') . '</th><td><input type="text" name="template_name" value="' . esc_attr($template['name'] ?? '') . '" required></td></tr>';
        echo '<tr><th>' . esc_html__('Subject', 'b2b-commerce') . '</th><td><input type="text" name="subject" value="' . esc_attr($template['subject'] ?? '') . '" required></td></tr>';
        echo '<tr><th>' . esc_html__('Message', 'b2b-commerce') . '</th><td><textarea name="message" rows="10" cols="50">' . esc_textarea($template['message'] ?? '') . '</textarea></td></tr>';
        echo '<tr><th>' . esc_html__('Trigger', 'b2b-commerce') . '</th><td><select name="trigger">';
        echo '<option value="user_approved"' . selected($template['trigger'] ?? '', 'user_approved', false) . '>' . esc_html__('User Approved', 'b2b-commerce') . '</option>';
        echo '<option value="user_rejected"' . selected($template['trigger'] ?? '', 'user_rejected', false) . '>' . esc_html__('User Rejected', 'b2b-commerce') . '</option>';
        echo '<option value="welcome_email"' . selected($template['trigger'] ?? '', 'welcome_email', false) . '>' . esc_html__('Welcome Email', 'b2b-commerce') . '</option>';
        echo '<option value="order_confirmation"' . selected($template['trigger'] ?? '', 'order_confirmation', false) . '>' . esc_html__('Order Confirmation', 'b2b-commerce') . '</option>';
        echo '</select></td></tr>';
        echo '</table>';
        
        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Template', 'b2b-commerce') . '</button></p>';
        echo '</form></div>';
    }

    private function list_email_templates() {
        $templates = [];
        for ($i = 1; $i <= 10; $i++) {
            $template = get_option("b2b_email_template_$i");
            if ($template) {
                $templates[$i] = $template;
            }
        }
        
        echo '<div class="b2b-admin-card">';
        echo '<h2>' . esc_html__('Email Templates', 'b2b-commerce') . '</h2>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=b2b-emails&action=add')) . '" class="button">' . esc_html__('Add New Template', 'b2b-commerce') . '</a></p>';
        
        if (empty($templates)) {
            echo '<p>' . esc_html__('No email templates found.', 'b2b-commerce') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__('Template Name', 'b2b-commerce') . '</th><th>' . esc_html__('Trigger', 'b2b-commerce') . '</th><th>' . esc_html__('Subject', 'b2b-commerce') . '</th><th>' . esc_html__('Actions', 'b2b-commerce') . '</th></tr></thead><tbody>';
            
            foreach ($templates as $id => $template) {
                echo '<tr>';
                echo '<td>' . esc_html($template['name']) . '</td>';
                echo '<td>' . esc_html($template['trigger']) . '</td>';
                echo '<td>' . esc_html($template['subject']) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-emails&action=edit&template_id=' . $id)) . '" class="button">' . esc_html__('Edit', 'b2b-commerce') . '</a> ';
                echo '<a href="' . esc_url(admin_url('admin.php?page=b2b-emails&action=test&template_id=' . $id)) . '" class="button">' . esc_html__('Test', 'b2b-commerce') . '</a>';
                echo '</td></tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }

    private function test_email_template() {
        $template_id = intval($_GET['template_id'] ?? 0);
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