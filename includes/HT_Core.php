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
     * Lead REST API (PR11 - Smart Lead Conversion)
     */
    public ?HT_Lead_REST_API $lead_api = null;

    /**
     * Order Tracker (PR12 - Post-Purchase Automation)
     */
    public ?HT_Order_Tracker $order_tracker = null;

    /**
     * Shipping API Bridge (PR12 - Post & Tipax Integration)
     */
    public ?HT_Shipping_API_Bridge $shipping_bridge = null;

    /**
     * Support Ticketing (PR12 - Conversation-based Support)
     */
    public ?HT_Support_Ticketing $support_ticketing = null;

    /**
     * Retention Engine (PR12 - Customer Retention)
     */
    public ?HT_Retention_Engine $retention_engine = null;

    /**
     * Plugin Scanner (PR12 - Global Inspector)
     */
    public ?HT_Plugin_Scanner $plugin_scanner = null;

    /**
     * Metadata Mining Engine (PR12 - Plugin Data Extraction)
     */
    public ?HT_Metadata_Mining_Engine $metadata_engine = null;

    /**
     * Hook Observer Service (PR12 - Action Listeners)
     */
    public ?HT_Hook_Observer_Service $hook_observer = null;

    /**
     * Dynamic Context Generator (PR12 - AI Context Builder)
     */
    public ?HT_Dynamic_Context_Generator $context_generator = null;

    /**
     * Post-Purchase REST API (PR12 - Order & Support APIs)
     */
    public ?HT_PostPurchase_REST_API $postpurchase_api = null;

    /**
     * Global Observer Core (PR13 - Central Plugin Monitoring)
     */
    public ?HT_Global_Observer_Core $global_observer = null;

    /**
     * Global Observer API (PR13 - Observer REST API)
     */
    public ?HT_Global_Observer_API $observer_api = null;

    /**
     * GeoLocation Service (PR14 - IP-based Country Detection)
     */
    public ?HT_GeoLocation_Service $geo_service = null;

    /**
     * Translation Cache Manager (PR14 - Smart Translation Caching)
     */
    public ?HT_Translation_Cache_Manager $translation_cache = null;

    /**
     * Render Buffer Filter (PR14 - Output Translation)
     */
    public ?Homa_Render_Buffer_Filter $render_buffer_filter = null;

    /**
     * Diplomacy Frontend (PR14 - Translation UI)
     */
    public ?HT_Diplomacy_Frontend $diplomacy_frontend = null;

    /**
     * Diplomacy Test Handlers (PR14 - Validation AJAX)
     */
    public ?HT_Diplomacy_Test_Handlers $diplomacy_test_handlers = null;

    /**
     * User Role Resolver (PR15 - Multi-Role Intelligence)
     */
    public ?HT_User_Role_Resolver $role_resolver = null;

    /**
     * Intruder Pattern Matcher (PR15 - Security Detection)
     */
    public ?HT_Intruder_Pattern_Matcher $intruder_detector = null;

    /**
     * Dynamic Chat Capabilities (PR15 - Role-Based UI)
     */
    public ?HT_Dynamic_Chat_Capabilities $chat_capabilities = null;

    /**
     * Admin Security Alerts (PR15 - Intruder Notification)
     */
    public ?HT_Admin_Security_Alerts $security_alerts = null;

    /**
     * WAF Core Engine (PR16 - Web Application Firewall)
     */
    public ?HT_WAF_Core_Engine $waf_engine = null;

    /**
     * LLM Shield Layer (PR16 - Prompt & Output Firewall)
     */
    public ?HT_LLM_Shield_Layer $llm_shield = null;

    /**
     * User Behavior Tracker (PR16 - Security Scoring)
     */
    public ?HT_User_Behavior_Tracker $behavior_tracker = null;

    /**
     * Access Control Manager (PR16 - Team Access Management)
     */
    public ?HT_Access_Control_Manager $access_control = null;

    /**
     * Authority Manager (PR17 - Knowledge Conflict Resolution)
     */
    public ?HT_Authority_Manager $authority_manager = null;

    /**
     * Action Orchestrator (PR17 - Multi-Step Operations)
     */
    public ?HT_Action_Orchestrator $action_orchestrator = null;

    /**
     * Feedback System (PR17 - User Feedback & Review Queue)
     */
    public ?HT_Feedback_System $feedback_system = null;

    /**
     * Feedback REST API (PR17 - Feedback Endpoints)
     */
    public ?HT_Feedback_REST_API $feedback_api = null;

    /**
     * BlackBox Logger (PR18 - Advanced Logging)
     */
    public ?HT_BlackBox_Logger $blackbox_logger = null;

    /**
     * Fallback Engine (PR18 - Offline Mode & Resilience)
     */
    public ?HT_Fallback_Engine $fallback_engine = null;

    /**
     * Query Optimizer (PR18 - Database Caching)
     */
    public ?HT_Query_Optimizer $query_optimizer = null;

    /**
     * Data Exporter (PR18 - Import/Export System)
     */
    public ?HT_Data_Exporter $data_exporter = null;

    /**
     * Background Processor (PR18 - Heavy Task Handler)
     */
    public ?HT_Background_Processor $background_processor = null;

    /**
     * Numerical Formatter (PR18 - Anti-Hallucination Shield)
     */
    public ?HT_Numerical_Formatter $numerical_formatter = null;

    /**
     * Auto Cleanup (PR18 - Self-Optimization)
     */
    public ?HT_Auto_Cleanup $auto_cleanup = null;

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

        // Initialize Lead REST API (PR11 - Smart Lead Conversion & OTP)
        $this->lead_api = new HT_Lead_REST_API();

        // Initialize PR12 - Post-Purchase Automation & Plugin Inspector
        $this->order_tracker = new HT_Order_Tracker();
        $this->shipping_bridge = new HT_Shipping_API_Bridge();
        $this->support_ticketing = new HT_Support_Ticketing();
        $this->retention_engine = new HT_Retention_Engine();
        $this->plugin_scanner = new HT_Plugin_Scanner();
        $this->metadata_engine = new HT_Metadata_Mining_Engine();
        $this->hook_observer = new HT_Hook_Observer_Service();
        $this->context_generator = new HT_Dynamic_Context_Generator();
        $this->postpurchase_api = new HT_PostPurchase_REST_API();

        // Initialize hook observers (PR12)
        $this->hook_observer->init_observers();

        // Initialize PR13 - Global Observer Core
        $this->global_observer = HT_Global_Observer_Core::instance();
        $this->observer_api = new HT_Global_Observer_API();

        // Initialize PR14 - Smart Diplomacy (GeoLocation & Translation)
        $this->geo_service = new HT_GeoLocation_Service();
        $this->translation_cache = new HT_Translation_Cache_Manager();
        $this->render_buffer_filter = new Homa_Render_Buffer_Filter();
        $this->diplomacy_frontend = new HT_Diplomacy_Frontend();
        $this->diplomacy_test_handlers = new HT_Diplomacy_Test_Handlers();

        // Initialize PR15 - Multi-Role Intelligence & Intruder Detection
        $this->role_resolver = new HT_User_Role_Resolver();
        $this->intruder_detector = new HT_Intruder_Pattern_Matcher();
        $this->chat_capabilities = new HT_Dynamic_Chat_Capabilities();
        $this->security_alerts = new HT_Admin_Security_Alerts();

        // Initialize PR16 - Homa Guardian (Security System)
        $this->waf_engine = new HT_WAF_Core_Engine();
        $this->llm_shield = new HT_LLM_Shield_Layer();
        $this->behavior_tracker = new HT_User_Behavior_Tracker();
        $this->access_control = new HT_Access_Control_Manager();

        // Initialize PR17 - Core Orchestrator Upgrade
        $this->authority_manager = new HT_Authority_Manager();
        $this->action_orchestrator = new HT_Action_Orchestrator($this);
        $this->feedback_system = new HT_Feedback_System();
        $this->feedback_api = new HT_Feedback_REST_API();

        // Initialize PR18 - Resilience & Knowledge Transfer
        $this->blackbox_logger = new HT_BlackBox_Logger();
        $this->fallback_engine = new HT_Fallback_Engine();
        $this->query_optimizer = new HT_Query_Optimizer();
        $this->data_exporter = new HT_Data_Exporter();
        $this->background_processor = new HT_Background_Processor();
        $this->numerical_formatter = new HT_Numerical_Formatter();
        $this->auto_cleanup = new HT_Auto_Cleanup();

        // Initialize default knowledge base on first load
        add_action('init', [$this->knowledge, 'init_default_knowledge_base']);
        
        // Schedule cleanup cron job (PR7)
        if (!wp_next_scheduled('homa_cleanup_expired_sessions')) {
            wp_schedule_event(time(), 'daily', 'homa_cleanup_expired_sessions');
        }
        add_action('homa_cleanup_expired_sessions', [HT_Vault_Manager::class, 'cleanup_expired_sessions']);

        // Schedule OTP cleanup cron job (PR11)
        if (!wp_next_scheduled('homa_cleanup_expired_otps')) {
            wp_schedule_event(time(), 'hourly', 'homa_cleanup_expired_otps');
        }
        add_action('homa_cleanup_expired_otps', [Homa_OTP_Core_Engine::class, 'cleanup_expired_otps']);

        // Schedule retention campaign cron job (PR12)
        HT_Retention_Engine::schedule_retention_cron();
        add_action('homa_run_retention_campaign', [HT_Retention_Engine::class, 'run_retention_campaign_cron']);

        // Schedule metadata refresh cron job (PR12)
        HT_Metadata_Mining_Engine::schedule_metadata_refresh();
        add_action('homa_refresh_plugin_metadata', [HT_Metadata_Mining_Engine::class, 'metadata_refresh_cron']);

        // Schedule translation cache cleanup (PR14)
        if (!wp_next_scheduled('homa_cleanup_translation_cache')) {
            wp_schedule_event(time(), 'weekly', 'homa_cleanup_translation_cache');
        }
        add_action('homa_cleanup_translation_cache', function() {
            $cache_manager = new HT_Translation_Cache_Manager();
            $cache_manager->cleanup_old_cache(90); // Clean translations older than 90 days
        });

        // Schedule knowledge base auto-sync (PR13)
        if (!wp_next_scheduled('homa_auto_sync_kb')) {
            wp_schedule_event(time(), 'twicedaily', 'homa_auto_sync_kb');
        }
        add_action('homa_auto_sync_kb', [HT_Knowledge_Base::class, 'auto_sync_metadata']);

        // Schedule feedback SMS on order completion (PR12)
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed']);

        // Hook observer cleanup (PR12)
        if (!wp_next_scheduled('homa_cleanup_hook_events')) {
            wp_schedule_event(time(), 'weekly', 'homa_cleanup_hook_events');
        }
        add_action('homa_cleanup_hook_events', [HT_Hook_Observer_Service::class, 'cleanup_old_events']);

        // Schedule security log cleanup (PR15)
        if (!wp_next_scheduled('homa_cleanup_security_logs')) {
            wp_schedule_event(time(), 'weekly', 'homa_cleanup_security_logs');
        }
        add_action('homa_cleanup_security_logs', function() {
            $security_alerts = new HT_Admin_Security_Alerts();
            $security_alerts->cleanup_old_logs(90); // Clean logs older than 90 days
        });

        // Schedule WAF blacklist cleanup (PR16)
        if (!wp_next_scheduled('homa_cleanup_waf_blacklist')) {
            wp_schedule_event(time(), 'daily', 'homa_cleanup_waf_blacklist');
        }
        add_action('homa_cleanup_waf_blacklist', function() {
            $waf = new HT_WAF_Core_Engine();
            $waf->cleanup_expired_blocks();
        });

        // Schedule behavior tracking cleanup (PR16)
        if (!wp_next_scheduled('homa_cleanup_behavior_logs')) {
            wp_schedule_event(time(), 'weekly', 'homa_cleanup_behavior_logs');
        }
        add_action('homa_cleanup_behavior_logs', function() {
            $behavior_tracker = new HT_User_Behavior_Tracker();
            $behavior_tracker->cleanup_old_records(90); // Clean records older than 90 days
        });

        // Schedule feedback cleanup (PR17)
        if (!wp_next_scheduled('homa_cleanup_feedback')) {
            wp_schedule_event(time(), 'monthly', 'homa_cleanup_feedback');
        }
        add_action('homa_cleanup_feedback', function() {
            $feedback_system = new HT_Feedback_System();
            $feedback_system->cleanup_old_feedback(90); // Clean resolved feedback older than 90 days
        });

        // Schedule BlackBox log cleanup (PR18)
        $this->blackbox_logger->schedule_cleanup();
        add_action('ht_blackbox_cleanup', function() {
            $logger = new HT_BlackBox_Logger();
            $logger->clean_old_logs();
        });

        // Schedule query cache warmup (PR18)
        $this->query_optimizer->schedule_warmup();
        add_action('ht_cache_warmup', function() {
            $optimizer = new HT_Query_Optimizer();
            $optimizer->warmup_cache();
        });

        // Schedule background job processing (PR18)
        add_action('ht_process_background_jobs', function() {
            $processor = new HT_Background_Processor();
            $processor->process_jobs();
        });

        // Schedule auto-cleanup analysis (PR18)
        $this->auto_cleanup->schedule_analysis();
        add_action('ht_auto_cleanup_analysis', function() {
            $cleanup = new HT_Auto_Cleanup();
            $cleanup->run_analysis();
        });

        // Hook 404 tracking for behavior analysis (PR16)
        add_action('template_redirect', [$this, 'track_404_errors']);
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
        add_action('rest_api_init', [$this->lead_api, 'register_endpoints']); // PR11
        add_action('rest_api_init', [$this->postpurchase_api, 'register_endpoints']); // PR12
        add_action('rest_api_init', [$this->observer_api, 'register_endpoints']); // PR13
        add_action('rest_api_init', [$this->chat_capabilities, 'register_endpoints']); // PR15
        add_action('rest_api_init', [$this->security_alerts, 'register_endpoints']); // PR15
        add_action('rest_api_init', [$this->access_control, 'register_endpoints']); // PR16
        
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
     * Handle order completion hook (PR12)
     * 
     * @param int $order_id Order ID
     * @return void
     */
    public function handle_order_completed(int $order_id): void
    {
        $this->retention_engine->schedule_feedback_sms($order_id);
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
     * Track 404 errors for security analysis (PR16)
     *
     * @return void
     */
    public function track_404_errors(): void
    {
        if (is_404() && $this->behavior_tracker) {
            $this->behavior_tracker->track_404_error();
        }
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
