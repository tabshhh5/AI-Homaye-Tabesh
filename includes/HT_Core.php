<?php
/**
 * Core Class - Main Plugin Orchestrator
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * کلید اصلی افزونه هما
 * سازگار با Divi و PHP 8.2
 */
final class HT_Core
{
    /**
     * Single instance of the class
     */
    private static ?HT_Core $instance = null;

    /**
     * Gemini AI client
     */
    public HT_Gemini_Client $brain;

    /**
     * Telemetry tracking system
     */
    public HT_Telemetry $eyes;

    /**
     * Persona management system
     */
    public HT_Persona_Manager $memory;

    /**
     * Knowledge base controller
     */
    public HT_Knowledge_Base $knowledge;
    
    /**
     * WooCommerce context provider
     */
    public HT_WooCommerce_Context $woo_context;
    
    /**
     * Divi bridge controller
     */
    public HT_Divi_Bridge $divi_bridge;
    
    /**
     * Decision trigger system
     */
    public HT_Decision_Trigger $decision_trigger;

    /**
     * Inference engine
     */
    public HT_Inference_Engine $inference_engine;

    /**
     * AI Controller
     */
    public HT_AI_Controller $ai_controller;

    /**
     * Perception Bridge (Core Intelligence Layer)
     */
    public HT_Perception_Bridge $perception_bridge;

    /**
     * Admin interface
     */
    public ?HT_Admin $admin = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->init_services();
        $this->register_hooks();
    }

    /**
     * Initialize all plugin services
     *
     * @return void
     */
    private function init_services(): void
    {
        $this->brain            = new HT_Gemini_Client();
        $this->eyes             = new HT_Telemetry();
        $this->memory           = new HT_Persona_Manager();
        $this->knowledge        = new HT_Knowledge_Base();
        $this->woo_context      = new HT_WooCommerce_Context();
        $this->divi_bridge      = new HT_Divi_Bridge();
        $this->decision_trigger = new HT_Decision_Trigger();
        $this->inference_engine = new HT_Inference_Engine();
        $this->ai_controller    = new HT_AI_Controller();
        $this->perception_bridge = new HT_Perception_Bridge($this);
        
        // Initialize admin only in admin area
        if (is_admin()) {
            $this->admin = new HT_Admin();
        }

        // Initialize default knowledge base on first load
        add_action('init', [$this->knowledge, 'init_default_knowledge_base']);
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // اتصال به REST API وردپرس
        add_action('rest_api_init', [$this->eyes, 'register_endpoints']);
        add_action('rest_api_init', [$this->ai_controller, 'register_endpoints']);

        // تزریق اسکریپتهای ردیاب به فرانتئند (سازگار با Divi)
        add_action('wp_enqueue_scripts', [$this->eyes, 'enqueue_tracker']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_ui_executor']);

        // Load admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Initialize persona tracking
        add_action('init', [$this->memory, 'init_session']);
    }

    /**
     * Enqueue admin assets
     *
     * @return void
     */
    public function enqueue_admin_assets(): void
    {
        wp_enqueue_style(
            'homaye-tabesh-admin',
            HT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            HT_VERSION
        );
    }

    /**
     * Enqueue UI executor script for frontend
     *
     * @return void
     */
    public function enqueue_ui_executor(): void
    {
        wp_enqueue_script(
            'homaye-tabesh-ui-executor',
            HT_PLUGIN_URL . 'assets/js/ui-executor.js',
            ['jquery'],
            HT_VERSION,
            true
        );
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     *
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new \Exception('Unserialization of singleton HT_Core is not allowed');
    }
}
