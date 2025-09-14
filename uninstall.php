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
global $wpdb;
$meta_keys = [
    'company_name', 'business_type', 'tax_id', 'b2b_approval_status',
    'b2b_credit_limit', 'b2b_payment_terms', 'b2b_tax_exempt', 'b2b_tax_exempt_number',
];
foreach ( $meta_keys as $key ) {
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $key ) );
}

// Remove term meta for groups and categories
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key IN ('b2b_cat_roles','b2b_cat_groups')" );

// Drop custom pricing rules table
$table = $wpdb->prefix . 'b2b_pricing_rules';
$wpdb->query( "DROP TABLE IF EXISTS $table" ); 