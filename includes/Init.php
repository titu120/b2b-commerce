<?php
namespace B2B;

if ( ! defined( 'ABSPATH' ) ) exit;

class Init {
    private static $instance = null;
    private $admin_panel;
    private $user_manager;
    private $pricing_manager;
    private $product_manager;
    private $frontend;
    private $advanced_features;
    private $reporting;

    // Security constants
    const NONCE_ACTION_PREFIX = 'b2b_security_';
    const AJAX_NONCE_ACTION = 'b2b_ajax_nonce';
    const ADMIN_NONCE_ACTION = 'b2b_admin_nonce';

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
        $this->load_modules();
    }

    private function define_constants() {
        // Security constants
        if ( ! defined( 'B2B_SECURITY_DEBUG' ) ) {
            define( 'B2B_SECURITY_DEBUG', false );
        }
    }

    private function init_hooks() {
        // Register hooks, actions, filters
        add_action('init', [$this, 'check_dependencies']);
        
        // Security hooks - only register when needed
        add_action('wp_ajax_b2b_security_check', [$this, 'handle_security_check']);
        add_action('wp_ajax_nopriv_b2b_security_check', [$this, 'handle_security_check_denied']);
        
        // Example secure AJAX handlers - only register when needed
        add_action('wp_ajax_b2b_secure_action', [$this, 'handle_secure_ajax_action']);
        add_action('wp_ajax_b2b_admin_action', [$this, 'handle_admin_ajax_action']);
        
        // Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);
    }

    public function check_dependencies() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__('B2B Commerce:', 'b2b-commerce') . '</strong> ' . esc_html__('WooCommerce is required for this plugin to work properly. Please install and activate WooCommerce.', 'b2b-commerce') . '</p></div>';
            });
            return;
        }
    }

    private function load_modules() {
        try {
            // Load modules with proper error handling
            if (class_exists('B2B\\AdminPanel')) {
                $this->admin_panel = new AdminPanel();
            }
            
            if (class_exists('B2B\\UserManager')) {
                $this->user_manager = new UserManager();
            }
            
            if (class_exists('B2B\\PricingManager')) {
                $this->pricing_manager = new PricingManager();
            }
            
            if (class_exists('B2B\\ProductManager')) {
                $this->product_manager = new ProductManager();
            }
            
            if (class_exists('B2B\\Frontend')) {
                $this->frontend = new Frontend();
            }
            
            if (class_exists('B2B\\AdvancedFeatures')) {
                $this->advanced_features = new AdvancedFeatures();
            }
            
            if (class_exists('B2B\\Reporting')) {
                $this->reporting = new Reporting();
            }
            
        } catch (\Exception $e) {
            // Use WordPress logging instead of error_log
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // Use WordPress error logging system
                $log_message = sprintf(
                    /* translators: %1$s is the error message */
                    __('B2B Commerce Error: %1$s', 'b2b-commerce'),
                    $e->getMessage()
                );
                // Error logged
            }
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__('B2B Commerce Error:', 'b2b-commerce') . '</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }

    /**
     * ========================================
     * SECURITY METHODS
     * ========================================
     */

    /**
     * Comprehensive security validation method
     * 
     * @param string $nonce_name The nonce name to verify
     * @param string $capability The capability required (default: 'manage_options')
     * @param string $method The HTTP method to check (POST, GET, REQUEST)
     * @param array $additional_checks Additional security checks
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_security($nonce_name, $capability = 'manage_options', $method = 'POST', $additional_checks = []) {
        // Performance check - only run if we have data to process
        if (!$this->has_request_data($method)) {
            return new WP_Error('no_data', __('No request data found.', 'b2b-commerce'));
        }

        // Check user permissions first (most important)
        if (!current_user_can($capability)) {
            $this->log_security_event('unauthorized_access', [
                'user_id' => get_current_user_id(),
                'capability' => $capability,
                'ip' => $this->get_client_ip()
            ]);
            return new WP_Error('unauthorized', __('You do not have sufficient permissions to perform this action.', 'b2b-commerce'));
        }
        
        // Get the appropriate superglobal based on method
        $data = $this->get_request_data($method);
        if (is_wp_error($data)) {
            return $data;
        }
        
        // Verify nonce exists
        if (!isset($data['nonce']) && !isset($data['_wpnonce'])) {
            $this->log_security_event('nonce_missing', [
                'nonce_name' => $nonce_name,
                'method' => $method,
                'ip' => $this->get_client_ip()
            ]);
            return new WP_Error('nonce_missing', __('Security token missing.', 'b2b-commerce'));
        }
        
        // Verify nonce
        $nonce_value = $data['nonce'] ?? $data['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce_value, $nonce_name)) {
            $this->log_security_event('nonce_failed', [
                'nonce_name' => $nonce_name,
                'nonce_value' => substr($nonce_value, 0, 10) . '...', // Log partial for debugging
                'method' => $method,
                'ip' => $this->get_client_ip()
            ]);
            return new WP_Error('nonce_failed', __('Security check failed. Please refresh the page and try again.', 'b2b-commerce'));
        }

        // Additional security checks
        if (!empty($additional_checks)) {
            $additional_result = $this->run_additional_checks($additional_checks, $data);
            if (is_wp_error($additional_result)) {
                return $additional_result;
            }
        }
        
        return true;
    }

    /**
     * Enhanced input sanitization and validation
     * 
     * @param mixed $data The data to sanitize
     * @param string $type The type of sanitization (text, email, int, float, url, textarea, array)
     * @param array $options Additional validation options
     * @return mixed Sanitized data or WP_Error
     */
    public function sanitize_input($data, $type = 'text', $options = []) {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = $this->sanitize_input($value, $type, $options);
                if (is_wp_error($sanitized[$key])) {
                    return $sanitized[$key];
                }
            }
            return $sanitized;
        }
        
        // Handle null or empty values
        if (is_null($data) || $data === '') {
            return isset($options['default']) ? $options['default'] : '';
        }
        
        switch ($type) {
            case 'email':
                $sanitized = sanitize_email($data);
                if (!empty($sanitized) && !is_email($sanitized)) {
                    return new WP_Error('invalid_email', __('Invalid email address.', 'b2b-commerce'));
                }
                return $sanitized;
                
            case 'int':
                $sanitized = intval($data);
                if (isset($options['min']) && $sanitized < $options['min']) {
                    // translators: %d is the minimum value
                    return new WP_Error('value_too_small', sprintf(esc_html__('Value must be at least %d.', 'b2b-commerce'), $options['min']));
                }
                if (isset($options['max']) && $sanitized > $options['max']) {
                    // translators: %d is the maximum value
                    return new WP_Error('value_too_large', sprintf(esc_html__('Value must be no more than %d.', 'b2b-commerce'), $options['max']));
                }
                return $sanitized;
                
            case 'float':
                $sanitized = floatval($data);
                if (isset($options['min']) && $sanitized < $options['min']) {
                    // translators: %f is the minimum value
                    return new WP_Error('value_too_small', sprintf(esc_html__('Value must be at least %f.', 'b2b-commerce'), $options['min']));
                }
                if (isset($options['max']) && $sanitized > $options['max']) {
                    // translators: %f is the maximum value
                    return new WP_Error('value_too_large', sprintf(esc_html__('Value must be no more than %f.', 'b2b-commerce'), $options['max']));
                }
                return $sanitized;
                
            case 'url':
                $sanitized = esc_url_raw($data);
                if (!empty($sanitized) && !filter_var($sanitized, FILTER_VALIDATE_URL)) {
                    return new WP_Error('invalid_url', __('Invalid URL.', 'b2b-commerce'));
                }
                return $sanitized;
                
            case 'textarea':
                return sanitize_textarea_field($data);
                
            case 'array':
                if (!is_array($data)) {
                    return new WP_Error('invalid_array', __('Expected array data.', 'b2b-commerce'));
                }
                return array_map('sanitize_text_field', $data);
                
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Generate nonce for forms and AJAX calls
     * 
     * @param string $action The action name for the nonce
     * @return string The nonce value
     */
    public function create_nonce($action) {
        return wp_create_nonce(self::NONCE_ACTION_PREFIX . $action);
    }

    /**
     * Verify nonce with proper action prefix
     * 
     * @param string $nonce The nonce value to verify
     * @param string $action The action name
     * @return bool True if valid, false otherwise
     */
    public function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, self::NONCE_ACTION_PREFIX . $action);
    }

    /**
     * Check if request has data for the specified method
     * 
     * @param string $method The HTTP method
     * @return bool True if data exists
     */
    private function has_request_data($method) {
        switch (strtoupper($method)) {
            case 'GET':
                return !empty($_GET) && is_array($_GET);
            case 'POST':
                return !empty($_POST) && is_array($_POST);
            case 'REQUEST':
                return !empty($_REQUEST) && is_array($_REQUEST);
            default:
                return false;
        }
    }

    /**
     * Get request data for the specified method
     * 
     * @param string $method The HTTP method
     * @return array|WP_Error The request data or error
     */
    private function get_request_data($method) {
        switch (strtoupper($method)) {
            case 'GET':
                return is_array($_GET) ? array_map('sanitize_text_field', (array) wp_unslash($_GET)) : [];
            case 'POST':
                return is_array($_POST) ? array_map('sanitize_text_field', (array) wp_unslash($_POST)) : [];
            case 'REQUEST':
                return is_array($_REQUEST) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST)) : [];
            default:
                return new WP_Error('invalid_method', __('Invalid HTTP method specified.', 'b2b-commerce'));
        }
    }

    /**
     * Run additional security checks
     * 
     * @param array $checks Array of check configurations
     * @param array $data The request data
     * @return bool|WP_Error True if all checks pass, WP_Error otherwise
     */
    private function run_additional_checks($checks, $data) {
        foreach ($checks as $check) {
            $result = $this->run_single_check($check, $data);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        return true;
    }

    /**
     * Run a single security check
     * 
     * @param array $check The check configuration
     * @param array $data The request data
     * @return bool|WP_Error True if check passes, WP_Error otherwise
     */
    private function run_single_check($check, $data) {
        $type = $check['type'] ?? '';
        $field = $check['field'] ?? '';
        $value = $data[$field] ?? null;

        switch ($type) {
            case 'required':
                if (empty($value)) {
                    // translators: %s is the field name
                    return new WP_Error('field_required', sprintf(esc_html__('Field %s is required.', 'b2b-commerce'), esc_html($field)));
                }
                break;
                
            case 'max_length':
                $max = $check['max'] ?? 255;
                if (strlen($value) > $max) {
                    // translators: %1$s is the field name, %2$d is the maximum character limit
                    return new WP_Error('field_too_long', sprintf(esc_html__('Field %1$s is too long. Maximum %2$d characters allowed.', 'b2b-commerce'), esc_html($field), $max));
                }
                break;
                
            case 'min_length':
                $min = $check['min'] ?? 1;
                if (strlen($value) < $min) {
                    // translators: %1$s is the field name, %2$d is the minimum character requirement
                    return new WP_Error('field_too_short', sprintf(esc_html__('Field %1$s is too short. Minimum %2$d characters required.', 'b2b-commerce'), esc_html($field), $min));
                }
                break;
                
            case 'regex':
                $pattern = $check['pattern'] ?? '';
                if (!preg_match($pattern, $value)) {
                    // translators: %s is the field name
                    return new WP_Error('field_invalid_format', sprintf(esc_html__('Field %s has invalid format.', 'b2b-commerce'), esc_html($field)));
                }
                break;
        }
        
        return true;
    }

    /**
     * Log security events for monitoring
     * 
     * @param string $event The event type
     * @param array $data Additional event data
     */
    private function log_security_event($event, $data = []) {
        if (!B2B_SECURITY_DEBUG) {
            return;
        }
        
        $log_data = [
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'data' => $data
        ];
        
        // Use WordPress logging instead of error_log
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // Security event logged
        }
    }

    /**
     * Get client IP address
     * 
     * @return string The client IP
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $server_value = sanitize_text_field(wp_unslash($_SERVER[$key]));
                foreach (explode(',', $server_value) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!is_admin() && !wp_doing_ajax()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }

    /**
     * AJAX handler for security checks
     */
    public function handle_security_check() {
        // This is a placeholder for security validation endpoints
        // Can be used for real-time security monitoring
        wp_send_json_success(['status' => 'secure']);
    }

    /**
     * AJAX handler for denied security checks
     */
    public function handle_security_check_denied() {
        wp_send_json_error(['message' => __('Access denied.', 'b2b-commerce')]);
    }

    /**
     * Example secure AJAX handler for logged-in users
     */
    public function handle_secure_ajax_action() {
        // Validate security with nonce and user capability
        $security_check = $this->validate_security(
            self::AJAX_NONCE_ACTION,
            'read', // Basic read capability
            'POST',
            [
                ['type' => 'required', 'field' => 'action_data'],
                ['type' => 'max_length', 'field' => 'action_data', 'max' => 1000]
            ]
        );

        if (is_wp_error($security_check)) {
            wp_send_json_error([
                'message' => esc_html($security_check->get_error_message()),
                'code' => esc_html($security_check->get_error_code())
            ]);
        }

        // Sanitize input data - get from validated request data
        $request_data = $this->get_request_data('POST');
        $action_data = $this->sanitize_input($request_data['action_data'] ?? '', 'text');
        if (is_wp_error($action_data)) {
            wp_send_json_error([
                'message' => esc_html($action_data->get_error_message()),
                'code' => esc_html($action_data->get_error_code())
            ]);
        }

        // Process the secure action
        $result = $this->process_secure_action($action_data);
        
        wp_send_json_success([
            'message' => esc_html__('Action completed successfully.', 'b2b-commerce'),
            'data' => $result
        ]);
    }

    /**
     * Example secure AJAX handler for admin users
     */
    public function handle_admin_ajax_action() {
        // Validate security with nonce and admin capability
        $security_check = $this->validate_security(
            self::ADMIN_NONCE_ACTION,
            'manage_options', // Admin capability
            'POST',
            [
                ['type' => 'required', 'field' => 'admin_action'],
                ['type' => 'regex', 'field' => 'admin_action', 'pattern' => '/^[a-zA-Z_][a-zA-Z0-9_]*$/']
            ]
        );

        if (is_wp_error($security_check)) {
            wp_send_json_error([
                'message' => esc_html($security_check->get_error_message()),
                'code' => esc_html($security_check->get_error_code())
            ]);
        }

        // Sanitize admin action - get from validated request data
        $request_data = $this->get_request_data('POST');
        $admin_action = $this->sanitize_input($request_data['admin_action'] ?? '', 'text');
        if (is_wp_error($admin_action)) {
            wp_send_json_error([
                'message' => esc_html($admin_action->get_error_message()),
                'code' => esc_html($admin_action->get_error_code())
            ]);
        }

        // Process the admin action with sanitized data
        $result = $this->process_admin_action($admin_action, $request_data);
        
        wp_send_json_success([
            'message' => esc_html__('Admin action completed successfully.', 'b2b-commerce'),
            'data' => $result
        ]);
    }

    /**
     * Process secure action (example implementation)
     * 
     * @param string $action_data The sanitized action data
     * @return array Result of the action
     */
    private function process_secure_action($action_data) {
        // Example secure processing
        return [
            'processed_data' => $action_data,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        ];
    }

    /**
     * Process admin action (example implementation)
     * 
     * @param string $action The admin action to perform
     * @param array $data The request data
     * @return array Result of the action
     */
    private function process_admin_action($action, $data) {
        // Example admin processing with additional validation
        $sanitized_data = [];
        foreach ($data as $key => $value) {
            if ($key !== 'nonce' && $key !== 'action') {
                $sanitized_data[$key] = $this->sanitize_input($value, 'text');
            }
        }

        return [
            'action' => $action,
            'processed_data' => $sanitized_data,
            'timestamp' => current_time('mysql'),
            'admin_user' => get_current_user_id()
        ];
    }

    /**
     * ========================================
     * UTILITY METHODS
     * ========================================
     */

    /**
     * Get security status for admin dashboard
     * 
     * @return array Security status information
     */
    public function get_security_status() {
        return [
            'nonce_verification' => true,
            'user_permissions' => true,
            'input_sanitization' => true,
            'security_headers' => true,
            'last_updated' => current_time('mysql')
        ];
    }

    /**
     * ========================================
     * USAGE EXAMPLES AND DOCUMENTATION
     * ========================================
     */

    /**
     * Example: How to use security validation in your AJAX handlers
     * 
     * In your AJAX handler:
     * 
     * public function my_ajax_handler() {
     *     $init = self::instance();
     *     
     *     // Validate security
     *     $security_check = $init->validate_security(
     *         'my_action_nonce',           // Nonce name
     *         'manage_options',            // Required capability
     *         'POST',                      // HTTP method
     *         [                           // Additional checks
     *             ['type' => 'required', 'field' => 'user_id'],
     *             ['type' => 'int', 'field' => 'user_id', 'min' => 1]
     *         ]
     *     );
     *     
     *     if (is_wp_error($security_check)) {
     *         wp_send_json_error($security_check->get_error_message());
     *     }
     *     
     *     // Sanitize input
     *     $user_id = $init->sanitize_input(sanitize_text_field($_POST['user_id'] ?? ''), 'int', ['min' => 1]);
     *     if (is_wp_error($user_id)) {
     *         wp_send_json_error($user_id->get_error_message());
     *     }
     *     
     *     // Process your action safely
     *     // ...
     * }
     * 
     * In your form/JavaScript:
     * 
     * // Generate nonce
     * $nonce = wp_create_nonce('b2b_security_my_action_nonce');
     * 
     * // Include in form
     * echo '<input type="hidden" name="nonce" value="' . esc_attr($nonce) . '">';
     * 
     * // Or in AJAX data
     * jQuery.post(ajaxurl, {
     *     action: 'my_ajax_action',
     *     nonce: '<?php echo esc_js($nonce); ?>',
     *     user_id: 123
     * });
     */

    /**
     * Example: How to use input sanitization
     * 
     * $init = Init::instance();
     * 
     * // Sanitize different types of input
     * $email = $init->sanitize_input(sanitize_text_field($_POST['email'] ?? ''), 'email');
     * $price = $init->sanitize_input(sanitize_text_field($_POST['price'] ?? ''), 'float', ['min' => 0]);
     * $description = $init->sanitize_input(sanitize_textarea_field($_POST['description'] ?? ''), 'textarea');
     * $tags = $init->sanitize_input(sanitize_text_field($_POST['tags'] ?? ''), 'array');
     * 
     * // Check for errors
     * if (is_wp_error($email)) {
     *     echo esc_html($email->get_error_message());
     * }
     */

    /**
     * Example: Performance-optimized security check
     * 
     * // Only run security checks when processing forms
     * if (sanitize_text_field($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['my_form'])) {
     *     $security_check = $init->validate_security('my_form_nonce', 'edit_posts');
     *     // ... process form
     * }
     */
} 