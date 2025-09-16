<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Email Configuration for B2B Commerce
 * 
 * This file contains email configuration options for the B2B Commerce plugin.
 * Uncomment and configure the settings below to enable email functionality.
 */

// Email Configuration Options
// Uncomment the lines below and configure them for your email service



/**
 * Instructions for setting up email:
 * 
 * 1. For Gmail:
 *    - Enable 2-factor authentication on your Gmail account
 *    - Generate an App Password (Google Account > Security > App Passwords)
 *    - Use the App Password instead of your regular password
 * 
 * 2. For SendGrid:
 *    - Sign up for a free SendGrid account
 *    - Verify your sender email address
 *    - Use your API key as the password
 * 
 * 3. For Mailgun:
 *    - Sign up for a free Mailgun account
 *    - Verify your domain
 *    - Use your SMTP credentials
 * 
 * 4. After configuring, uncomment the define('B2B_ENABLE_SMTP', true); line
 * 
 * 5. Test the email functionality by responding to an inquiry
 */

// Email Templates
if (!defined('B2B_INQUIRY_RESPONSE_SUBJECT')) {
    define('B2B_INQUIRY_RESPONSE_SUBJECT', __('Response to your inquiry about {product_name}', 'b2b-commerce'));
}

if (!defined('B2B_INQUIRY_RESPONSE_TEMPLATE')) {
    define('B2B_INQUIRY_RESPONSE_TEMPLATE', 
        __("Dear Customer,\n\n", 'b2b-commerce') .
        __("Thank you for your inquiry about {product_name}.\n\n", 'b2b-commerce') .
        __("Our response:\n{admin_response}\n\n", 'b2b-commerce') .
        __("Best regards,\n{site_name}", 'b2b-commerce')
    );
}
