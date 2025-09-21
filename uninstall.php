<?php
// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options
$options = [
    'b2b_general_settings',
    'b2b_email_templates',
    'b2b_dismissed_notifications',
    'b2b_catalog_mode',
    'b2b_vat_settings',
    'b2b_role_payment_methods',
    'b2b_role_shipping_methods',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

// Remove user meta
$meta_keys = [
    'company_name', 'business_type', 'tax_id', 'b2b_approval_status',
    'b2b_credit_limit', 'b2b_payment_terms', 'b2b_tax_exempt', 'b2b_tax_exempt_number',
];

// Get all users and delete their meta
$users = get_users( array( 'fields' => 'ID' ) );
foreach ( $users as $user_id ) {
    foreach ( $meta_keys as $key ) {
        delete_user_meta( $user_id, $key );
    }
}

// Remove term meta for groups and categories
$terms = get_terms( array(
    'taxonomy'   => array( 'product_cat', 'product_tag' ),
    'hide_empty' => false,
    'fields'     => 'ids',
) );

if ( ! is_wp_error( $terms ) ) {
    foreach ( $terms as $term_id ) {
        delete_term_meta( $term_id, 'b2b_cat_roles' );
        delete_term_meta( $term_id, 'b2b_cat_groups' );
    }
}

// Drop custom pricing rules table
global $wpdb;
$table = $wpdb->prefix . 'b2b_pricing_rules';

// Check if table exists using WordPress database abstraction
$table_exists = $wpdb->get_var( $wpdb->prepare( 
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s", 
    DB_NAME,
    $table 
) );

if ( $table_exists ) {
    // Use WordPress database abstraction for schema changes
    $wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS `%s`", $table ) );
} 