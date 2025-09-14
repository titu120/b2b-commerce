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
        // Additional constants can be defined here
    }

    private function init_hooks() {
        // Register hooks, actions, filters
        add_action('init', [$this, 'check_dependencies']);
    }

    public function check_dependencies() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>' . __('B2B Commerce Pro:', 'b2b-commerce') . '</strong> ' . __('WooCommerce is required for this plugin to work properly. Please install and activate WooCommerce.', 'b2b-commerce') . '</p></div>';
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
            error_log(__('B2B Commerce Pro Error:', 'b2b-commerce') . ' ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>' . __('B2B Commerce Pro Error:', 'b2b-commerce') . '</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
} 