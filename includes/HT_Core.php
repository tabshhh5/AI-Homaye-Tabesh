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
     * Cart Manager (Action & Conversion Engine)
     */
    public HT_Cart_Manager $cart_manager;

    /**
     * Parallel UI Manager (React Sidebar)
     */
    public HT_Parallel_UI $parallel_ui;

     /**
     * Admin interface
     */
    public ?HT_Admin $admin = null;

    /**
     * Atlas API
     */
    public ?HT_Atlas_API $atlas_api = null;

    /**
     * DOM Action Controller (Visual Guidance)
     */
    public ?HT_DOM_Action_Controller $dom_controller = null;

    /**
     * Admin Intervention (Live Messaging)
     */
    public ?HT_Admin_Intervention $admin_intervention = null;

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
        $this->cart_manager     = new HT_Cart_Manager($this);
        $this->parallel_ui      = new HT_Parallel_UI($this);
        
        // Initialize admin only in admin area
        if (is_admin()) {
            $this->admin = new HT_Admin();
        }

        // Initialize Atlas API (autoloaded via PSR-4 from includes/HT_Atlas_API.php)
        $this->atlas_api = new HT_Atlas_API();

        // Initialize DOM Action Controller (PR10 - Visual Guidance)
        $this->dom_controller = HT_DOM_Action_Controller::instance();

        // Initialize Admin Intervention (PR10 - Live Messaging)
        $this->admin_intervention = HT_Admin_Intervention::instance();

        // Initialize default knowledge base on first load
        add_action('init', [$this->knowledge, 'init_default_knowledge_base']);
        
        // Schedule cleanup cron job (PR7)
        if (!wp_next_scheduled('homa_cleanup_expired_sessions')) {
            wp_schedule_event(time(), 'daily', 'homa_cleanup_expired_sessions');
        }
        add_action('homa_cleanup_expired_sessions', [HT_Vault_Manager::class, 'cleanup_expired_sessions']);
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
        add_action('rest_api_init', [$this->atlas_api, 'register_endpoints']);
        
        // Initialize Vault REST API (PR7)
        HT_Vault_REST_API::init();

        // تزریق اسکریپتهای ردیاب به فرانتئند (سازگار با Divi)
        add_action('wp_enqueue_scripts', [$this->eyes, 'enqueue_tracker']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_ui_executor']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_vault_scripts']);

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

        // Enqueue intervention listener (PR10)
        wp_enqueue_script(
            'homaye-tabesh-intervention-listener',
            HT_PLUGIN_URL . 'assets/js/homa-intervention-listener.js',
            ['homaye-tabesh-event-bus'],
            HT_VERSION,
            true
        );
    }

    /**
     * Enqueue Vault scripts for Omni-Store (PR7)
     *
     * @return void
     */
    public function enqueue_vault_scripts(): void
    {
        // Vault must load after Event Bus
        wp_enqueue_script(
            'homaye-tabesh-vault',
            HT_PLUGIN_URL . 'assets/js/homa-vault.js',
            ['homaye-tabesh-event-bus'],
            HT_VERSION,
            true
        );

        // Localize script with REST API config
        wp_localize_script('homaye-tabesh-vault', 'homaConfig', [
            'restUrl' => rest_url('homaye-tabesh/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => home_url()
        ]);
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
