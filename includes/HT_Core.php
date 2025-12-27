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
    public ?HT_Gemini_Client $brain = null;

    /**
     * Telemetry tracking system
     */
    public ?HT_Telemetry $eyes = null;

    /**
     * Persona management system
     */
    public ?HT_Persona_Manager $memory = null;

    /**
     * Knowledge base controller
     */
    public ?HT_Knowledge_Base $knowledge = null;
    
    /**
     * WooCommerce context provider
     */
    public ?HT_WooCommerce_Context $woo_context = null;
    
    /**
     * Divi bridge controller
     */
    public ?HT_Divi_Bridge $divi_bridge = null;
    
    /**
     * Decision trigger system
     */
    public ?HT_Decision_Trigger $decision_trigger = null;

    /**
     * Inference engine
     */
    public ?HT_Inference_Engine $inference_engine = null;

    /**
     * AI Controller
     */
    public ?HT_AI_Controller $ai_controller = null;

    /**
     * Perception Bridge (Core Intelligence Layer)
     */
    public ?HT_Perception_Bridge $perception_bridge = null;

    /**
     * Cart Manager (Action & Conversion Engine)
     */
    public ?HT_Cart_Manager $cart_manager = null;

    /**
     * Parallel UI Manager (React Sidebar)
     */
    public ?HT_Parallel_UI $parallel_ui = null;

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
     * Resilience REST API (PR18 - Resilience Endpoints)
     */
    public ?HT_Resilience_REST_API $resilience_api = null;

    /**
     * System Diagnostics (PR19 - Health Checker)
     */
    public ?HT_System_Diagnostics $system_diagnostics = null;

    /**
     * Console Analytics API (PR19 - Super Console Endpoints)
     */
    public ?HT_Console_Analytics_API $console_api = null;

    /**
     * Health Check API - REST API health monitoring
     */
    public ?HT_Health_Check_API $health_api = null;

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
        try {
            $this->init_services();
            $this->register_hooks();
        } catch (\Throwable $e) {
            HT_Error_Handler::log_exception($e, 'core_init');
            
            // Display admin notice
            HT_Error_Handler::admin_notice(
                sprintf(
                    __('خطا در راه‌اندازی هسته افزونه: %s', 'homaye-tabesh'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Initialize all plugin services
     *
     * @return void
     */
    private function init_services(): void
    {
        // Wrap each service initialization in try/catch to prevent cascade failures
        $this->brain            = $this->safe_init(fn() => new HT_Gemini_Client(), 'HT_Gemini_Client');
        $this->eyes             = $this->safe_init(fn() => new HT_Telemetry(), 'HT_Telemetry');
        $this->memory           = $this->safe_init(fn() => new HT_Persona_Manager(), 'HT_Persona_Manager');
        $this->knowledge        = $this->safe_init(fn() => new HT_Knowledge_Base(), 'HT_Knowledge_Base');
        $this->woo_context      = $this->safe_init(fn() => new HT_WooCommerce_Context(), 'HT_WooCommerce_Context');
        $this->divi_bridge      = $this->safe_init(fn() => new HT_Divi_Bridge(), 'HT_Divi_Bridge');
        $this->decision_trigger = $this->safe_init(fn() => new HT_Decision_Trigger(), 'HT_Decision_Trigger');
        
        // Initialize inference engine with required dependencies to avoid circular dependency
        $this->inference_engine = $this->safe_init(function() {
            // Only create if all dependencies are available
            if ($this->brain && $this->knowledge && $this->memory && $this->woo_context) {
                return new HT_Inference_Engine($this->brain, $this->knowledge, $this->memory, $this->woo_context);
            }
            return null;
        }, 'HT_Inference_Engine');
        
        // Initialize AI controller with dependencies after inference engine is created
        $this->ai_controller = $this->safe_init(function() {
            // Only create if dependencies are available
            if ($this->inference_engine && $this->knowledge && $this->memory && $this->woo_context) {
                $prompt_builder = new HT_Prompt_Builder_Service($this->knowledge, $this->memory, $this->woo_context);
                return new HT_AI_Controller($this->inference_engine, $prompt_builder);
            }
            return null;
        }, 'HT_AI_Controller');
        
        $this->perception_bridge = $this->safe_init(fn() => new HT_Perception_Bridge($this), 'HT_Perception_Bridge');
        $this->cart_manager     = $this->safe_init(fn() => new HT_Cart_Manager($this), 'HT_Cart_Manager');
        $this->parallel_ui      = $this->safe_init(fn() => new HT_Parallel_UI($this), 'HT_Parallel_UI');
        
        // Initialize admin only in admin area
        if (is_admin()) {
            $this->admin = $this->safe_init(fn() => new HT_Admin(), 'HT_Admin');
        }

        // Initialize Atlas API (autoloaded via PSR-4 from includes/HT_Atlas_API.php)
        $this->atlas_api = $this->safe_init(fn() => new HT_Atlas_API(), 'HT_Atlas_API');

        // Initialize DOM Action Controller (PR10 - Visual Guidance)
        $this->dom_controller = $this->safe_init(fn() => HT_DOM_Action_Controller::instance(), 'HT_DOM_Action_Controller');

        // Initialize Admin Intervention (PR10 - Live Messaging)
        $this->admin_intervention = $this->safe_init(fn() => HT_Admin_Intervention::instance(), 'HT_Admin_Intervention');

        // Initialize Lead REST API (PR11 - Smart Lead Conversion & OTP)
        $this->lead_api = $this->safe_init(fn() => new HT_Lead_REST_API(), 'HT_Lead_REST_API');

        // Initialize PR12 - Post-Purchase Automation & Plugin Inspector
        $this->order_tracker = $this->safe_init(fn() => new HT_Order_Tracker(), 'HT_Order_Tracker');
        $this->shipping_bridge = $this->safe_init(fn() => new HT_Shipping_API_Bridge(), 'HT_Shipping_API_Bridge');
        $this->support_ticketing = $this->safe_init(fn() => new HT_Support_Ticketing(), 'HT_Support_Ticketing');
        $this->retention_engine = $this->safe_init(fn() => new HT_Retention_Engine(), 'HT_Retention_Engine');
        $this->plugin_scanner = $this->safe_init(fn() => new HT_Plugin_Scanner(), 'HT_Plugin_Scanner');
        $this->metadata_engine = $this->safe_init(fn() => new HT_Metadata_Mining_Engine(), 'HT_Metadata_Mining_Engine');
        $this->hook_observer = $this->safe_init(fn() => new HT_Hook_Observer_Service(), 'HT_Hook_Observer_Service');
        $this->context_generator = $this->safe_init(fn() => new HT_Dynamic_Context_Generator(), 'HT_Dynamic_Context_Generator');
        $this->postpurchase_api = $this->safe_init(fn() => new HT_PostPurchase_REST_API(), 'HT_PostPurchase_REST_API');

        // Initialize hook observers (PR12)
        $this->safe_call(function() {
            if ($this->hook_observer !== null) {
                $this->hook_observer->init_observers();
            }
        }, 'hook_observer_init');

        // Initialize PR13 - Global Observer Core
        $this->global_observer = $this->safe_init(fn() => HT_Global_Observer_Core::instance(), 'HT_Global_Observer_Core');
        $this->observer_api = $this->safe_init(fn() => new HT_Global_Observer_API(), 'HT_Global_Observer_API');

        // Initialize PR14 - Smart Diplomacy (GeoLocation & Translation)
        $this->geo_service = $this->safe_init(fn() => new HT_GeoLocation_Service(), 'HT_GeoLocation_Service');
        $this->translation_cache = $this->safe_init(fn() => new HT_Translation_Cache_Manager(), 'HT_Translation_Cache_Manager');
        $this->render_buffer_filter = $this->safe_init(fn() => new Homa_Render_Buffer_Filter(), 'Homa_Render_Buffer_Filter');
        $this->diplomacy_frontend = $this->safe_init(fn() => new HT_Diplomacy_Frontend(), 'HT_Diplomacy_Frontend');
        $this->diplomacy_test_handlers = $this->safe_init(fn() => new HT_Diplomacy_Test_Handlers(), 'HT_Diplomacy_Test_Handlers');

        // Initialize PR15 - Multi-Role Intelligence & Intruder Detection
        $this->role_resolver = $this->safe_init(fn() => new HT_User_Role_Resolver(), 'HT_User_Role_Resolver');
        $this->intruder_detector = $this->safe_init(fn() => new HT_Intruder_Pattern_Matcher(), 'HT_Intruder_Pattern_Matcher');
        $this->chat_capabilities = $this->safe_init(fn() => new HT_Dynamic_Chat_Capabilities(), 'HT_Dynamic_Chat_Capabilities');
        $this->security_alerts = $this->safe_init(fn() => new HT_Admin_Security_Alerts(), 'HT_Admin_Security_Alerts');

        // Initialize PR16 - Homa Guardian (Security System)
        $this->waf_engine = $this->safe_init(fn() => new HT_WAF_Core_Engine(), 'HT_WAF_Core_Engine');
        $this->llm_shield = $this->safe_init(fn() => new HT_LLM_Shield_Layer(), 'HT_LLM_Shield_Layer');
        $this->behavior_tracker = $this->safe_init(fn() => new HT_User_Behavior_Tracker(), 'HT_User_Behavior_Tracker');
        $this->access_control = $this->safe_init(fn() => new HT_Access_Control_Manager(), 'HT_Access_Control_Manager');

        // Initialize PR17 - Core Orchestrator Upgrade
        $this->authority_manager = $this->safe_init(fn() => new HT_Authority_Manager(), 'HT_Authority_Manager');
        $this->action_orchestrator = $this->safe_init(fn() => new HT_Action_Orchestrator($this), 'HT_Action_Orchestrator');
        $this->feedback_system = $this->safe_init(fn() => new HT_Feedback_System(), 'HT_Feedback_System');
        $this->feedback_api = $this->safe_init(fn() => new HT_Feedback_REST_API(), 'HT_Feedback_REST_API');

        // Initialize PR18 - Resilience & Knowledge Transfer
        $this->blackbox_logger = $this->safe_init(fn() => new HT_BlackBox_Logger(), 'HT_BlackBox_Logger');
        $this->fallback_engine = $this->safe_init(fn() => new HT_Fallback_Engine(), 'HT_Fallback_Engine');
        $this->query_optimizer = $this->safe_init(fn() => new HT_Query_Optimizer(), 'HT_Query_Optimizer');
        $this->data_exporter = $this->safe_init(fn() => new HT_Data_Exporter(), 'HT_Data_Exporter');
        $this->background_processor = $this->safe_init(fn() => new HT_Background_Processor(), 'HT_Background_Processor');
        $this->numerical_formatter = $this->safe_init(fn() => new HT_Numerical_Formatter(), 'HT_Numerical_Formatter');
        $this->auto_cleanup = $this->safe_init(fn() => new HT_Auto_Cleanup(), 'HT_Auto_Cleanup');
        $this->resilience_api = $this->safe_init(fn() => new HT_Resilience_REST_API(), 'HT_Resilience_REST_API');

        // Initialize PR19 - Super Console (Homa Control Center)
        $this->system_diagnostics = $this->safe_init(fn() => new HT_System_Diagnostics(), 'HT_System_Diagnostics');
        $this->console_api = $this->safe_init(fn() => new HT_Console_Analytics_API(), 'HT_Console_Analytics_API');

        // Initialize Health Check API for system monitoring
        $this->health_api = $this->safe_init(fn() => new HT_Health_Check_API(), 'HT_Health_Check_API');

        // Initialize default knowledge base on first load
        $this->safe_call(fn() => add_action('init', [$this->knowledge, 'init_default_knowledge_base']), 'kb_init_hook');
        
        // Schedule cleanup cron job (PR7)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_cleanup_expired_sessions')) {
                wp_schedule_event(time(), 'daily', 'homa_cleanup_expired_sessions');
            }
            add_action('homa_cleanup_expired_sessions', [HT_Vault_Manager::class, 'cleanup_expired_sessions']);
        }, 'cron_vault_cleanup');

        // Schedule OTP cleanup cron job (PR11)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_cleanup_expired_otps')) {
                wp_schedule_event(time(), 'hourly', 'homa_cleanup_expired_otps');
            }
            add_action('homa_cleanup_expired_otps', [Homa_OTP_Core_Engine::class, 'cleanup_expired_otps']);
        }, 'cron_otp_cleanup');

        // Schedule retention campaign cron job (PR12)
        $this->safe_call(fn() => HT_Retention_Engine::schedule_retention_cron(), 'retention_cron_schedule');
        $this->safe_call(fn() => add_action('homa_run_retention_campaign', [HT_Retention_Engine::class, 'run_retention_campaign_cron']), 'retention_cron_hook');

        // Schedule metadata refresh cron job (PR12)
        $this->safe_call(fn() => HT_Metadata_Mining_Engine::schedule_metadata_refresh(), 'metadata_cron_schedule');
        $this->safe_call(fn() => add_action('homa_refresh_plugin_metadata', [HT_Metadata_Mining_Engine::class, 'metadata_refresh_cron']), 'metadata_cron_hook');

        // Schedule translation cache cleanup (PR14)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_cleanup_translation_cache')) {
                wp_schedule_event(time(), 'weekly', 'homa_cleanup_translation_cache');
            }
            add_action('homa_cleanup_translation_cache', function() {
                $cache_manager = new HT_Translation_Cache_Manager();
                $cache_manager->cleanup_old_cache(90); // Clean translations older than 90 days
            });
        }, 'cron_translation_cleanup');

        // Schedule knowledge base auto-sync (PR13)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_auto_sync_kb')) {
                wp_schedule_event(time(), 'twicedaily', 'homa_auto_sync_kb');
            }
            add_action('homa_auto_sync_kb', [HT_Knowledge_Base::class, 'auto_sync_metadata']);
        }, 'cron_kb_sync');

        // Schedule feedback SMS on order completion (PR12)
        $this->safe_call(fn() => add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed']), 'order_completed_hook');

        // Hook observer cleanup (PR12)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_cleanup_hook_events')) {
                wp_schedule_event(time(), 'weekly', 'homa_cleanup_hook_events');
            }
            add_action('homa_cleanup_hook_events', [HT_Hook_Observer_Service::class, 'cleanup_old_events']);
        }, 'cron_hook_cleanup');

        // Schedule security log cleanup (PR15)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_cleanup_security_logs')) {
                wp_schedule_event(time(), 'weekly', 'homa_cleanup_security_logs');
            }
            add_action('homa_cleanup_security_logs', function() {
                $security_alerts = new HT_Admin_Security_Alerts();
                $security_alerts->cleanup_old_logs(90); // Clean logs older than 90 days
            });
        }, 'cron_security_cleanup');

        // Schedule WAF blacklist cleanup (PR16)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_cleanup_waf_blacklist')) {
                wp_schedule_event(time(), 'daily', 'homa_cleanup_waf_blacklist');
            }
            add_action('homa_cleanup_waf_blacklist', function() {
                $waf = new HT_WAF_Core_Engine();
                $waf->cleanup_expired_blocks();
            });
        }, 'cron_waf_cleanup');

        // Schedule behavior tracking cleanup (PR16)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_cleanup_behavior_logs')) {
                wp_schedule_event(time(), 'weekly', 'homa_cleanup_behavior_logs');
            }
            add_action('homa_cleanup_behavior_logs', function() {
                $behavior_tracker = new HT_User_Behavior_Tracker();
                $behavior_tracker->cleanup_old_records(90); // Clean records older than 90 days
            });
        }, 'cron_behavior_cleanup');

        // Schedule feedback cleanup (PR17)
        $this->safe_call(function() {
            if (!wp_next_scheduled('homa_cleanup_feedback')) {
                wp_schedule_event(time(), 'monthly', 'homa_cleanup_feedback');
            }
            add_action('homa_cleanup_feedback', function() {
                $feedback_system = new HT_Feedback_System();
                $feedback_system->cleanup_old_feedback(90); // Clean resolved feedback older than 90 days
            });
        }, 'cron_feedback_cleanup');

        // Schedule BlackBox log cleanup (PR18)
        $this->safe_call(function() {
            if ($this->blackbox_logger !== null) {
                $this->blackbox_logger->schedule_cleanup();
            }
        }, 'blackbox_cron_schedule');
        $this->safe_call(fn() => add_action('ht_blackbox_cleanup', function() {
            $logger = new HT_BlackBox_Logger();
            $logger->clean_old_logs();
        }), 'blackbox_cron_hook');

        // Schedule query cache warmup (PR18)
        $this->safe_call(function() {
            if ($this->query_optimizer !== null) {
                $this->query_optimizer->schedule_warmup();
            }
        }, 'query_optimizer_schedule');
        $this->safe_call(fn() => add_action('ht_cache_warmup', function() {
            $optimizer = new HT_Query_Optimizer();
            $optimizer->warmup_cache();
        }), 'cache_warmup_hook');

        // Schedule background job processing (PR18)
        $this->safe_call(fn() => add_action('ht_process_background_jobs', function() {
            $processor = new HT_Background_Processor();
            $processor->process_jobs();
        }), 'background_jobs_hook');

        // Schedule auto-cleanup analysis (PR18)
        $this->safe_call(function() {
            if ($this->auto_cleanup !== null) {
                $this->auto_cleanup->schedule_analysis();
            }
        }, 'auto_cleanup_schedule');
        $this->safe_call(fn() => add_action('ht_auto_cleanup_analysis', function() {
            $cleanup = new HT_Auto_Cleanup();
            $cleanup->run_analysis();
        }), 'auto_cleanup_hook');

        // Hook 404 tracking for behavior analysis (PR16)
        $this->safe_call(fn() => add_action('template_redirect', [$this, 'track_404_errors']), '404_tracking_hook');
    }

    /**
     * Safely initialize a service
     *
     * @param callable $initializer Function to initialize the service
     * @param string $service_name Service name for logging
     * @return mixed Initialized service or null on error
     */
    private function safe_init(callable $initializer, string $service_name)
    {
        try {
            return $initializer();
        } catch (\Throwable $e) {
            HT_Error_Handler::log_exception($e, "init_service_{$service_name}");
            return null;
        }
    }

    /**
     * Safely execute a function
     *
     * @param callable $callback Function to execute
     * @param string $context Context for logging
     * @return void
     */
    private function safe_call(callable $callback, string $context): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            HT_Error_Handler::log_exception($e, "safe_call_{$context}");
        }
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Add CSP header support for GapGPT API
        $this->safe_call(function() {
            add_action('send_headers', [$this, 'modify_csp_headers']);
        }, 'csp_headers');

        // Database self-healing check (admin_init hook)
        $this->safe_call(function() {
            add_action('admin_init', function() {
                // Only check once per day to avoid performance impact
                $last_check = get_option('homa_db_last_check', 0);
                $check_interval = 24 * HOUR_IN_SECONDS; // 24 hours
                
                if ((time() - $last_check) > $check_interval) {
                    if (class_exists('\HomayeTabesh\HT_Activator')) {
                        $repaired = \HomayeTabesh\HT_Activator::check_and_repair_database();
                        // Only update timestamp if check was performed (whether repairs were needed or not)
                        if ($repaired !== false) {
                            update_option('homa_db_last_check', time());
                        }
                        
                        // Show admin notice if repairs were made
                        $db_repairs = get_transient('homa_db_repairs_made');
                        if ($db_repairs && is_array($db_repairs)) {
                            HT_Error_Handler::admin_notice(
                                sprintf(
                                    __('سیستم خودترمیمی فعال شد. %d جدول و %d ستون بازیابی شد.', 'homaye-tabesh'),
                                    count($db_repairs['tables'] ?? []),
                                    count($db_repairs['columns'] ?? [])
                                ),
                                'success'
                            );
                            delete_transient('homa_db_repairs_made');
                        }
                    }
                }
                
                // Display activation health report if available
                $health_report = get_transient('homa_activation_health_report');
                if ($health_report && is_array($health_report)) {
                    add_action('admin_notices', function() use ($health_report) {
                        if (class_exists('\HomayeTabesh\HT_Activator')) {
                            \HomayeTabesh\HT_Activator::display_health_report($health_report);
                        }
                    });
                    delete_transient('homa_activation_health_report');
                }
                
                // Check if API key is configured (show notice once per user)
                $user_id = get_current_user_id();
                if ($user_id && !get_user_meta($user_id, 'homa_api_key_notice_dismissed', true)) {
                    $api_key = get_option('ht_gemini_api_key', '');
                    if (empty($api_key)) {
                        HT_Error_Handler::admin_notice(
                            __('کلید API همای تابش تنظیم نشده است. لطفاً به تنظیمات بروید و کلید API را وارد کنید.', 'homaye-tabesh'),
                            'warning'
                        );
                        // Mark as shown for this user for 7 days
                        update_user_meta($user_id, 'homa_api_key_notice_dismissed', time() + (7 * DAY_IN_SECONDS));
                    } else {
                        // API key is configured, clear the notice flag
                        delete_user_meta($user_id, 'homa_api_key_notice_dismissed');
                    }
                }
            }, 5); // Early priority
        }, 'database_self_healing');

        // Wrap hook registrations to prevent cascade failures
        $this->safe_call(function() {
            // اتصال به REST API وردپرس
            if ($this->eyes) add_action('rest_api_init', [$this->eyes, 'register_endpoints']);
            if ($this->ai_controller) add_action('rest_api_init', [$this->ai_controller, 'register_endpoints']);
            if ($this->atlas_api) add_action('rest_api_init', [$this->atlas_api, 'register_endpoints']);
            if ($this->lead_api) add_action('rest_api_init', [$this->lead_api, 'register_endpoints']); // PR11
            if ($this->postpurchase_api) add_action('rest_api_init', [$this->postpurchase_api, 'register_endpoints']); // PR12
            if ($this->observer_api) add_action('rest_api_init', [$this->observer_api, 'register_endpoints']); // PR13
            if ($this->chat_capabilities) add_action('rest_api_init', [$this->chat_capabilities, 'register_endpoints']); // PR15
            if ($this->security_alerts) add_action('rest_api_init', [$this->security_alerts, 'register_endpoints']); // PR15
            if ($this->access_control) add_action('rest_api_init', [$this->access_control, 'register_endpoints']); // PR16
            if ($this->resilience_api) add_action('rest_api_init', [$this->resilience_api, 'register_endpoints']); // PR18
            if ($this->health_api) add_action('rest_api_init', [$this->health_api, 'register_endpoints']); // Health Check API
        }, 'rest_api_hooks');
        
        // Initialize Vault REST API (PR7)
        $this->safe_call(fn() => HT_Vault_REST_API::init(), 'vault_rest_init');

        // تزریق اسکریپتهای ردیاب به فرانتئند (سازگار با Divi)
        $this->safe_call(function() {
            // Enqueue error handler first (highest priority)
            add_action('wp_enqueue_scripts', [$this, 'enqueue_error_handler'], 1);
            
            if ($this->eyes) add_action('wp_enqueue_scripts', [$this->eyes, 'enqueue_tracker']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_ui_executor']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_vault_scripts']);
        }, 'frontend_scripts');

        // Load admin assets
        $this->safe_call(fn() => add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']), 'admin_scripts');

        // Initialize persona tracking
        $this->safe_call(function() {
            if ($this->memory) add_action('init', [$this->memory, 'init_session']);
        }, 'persona_tracking');
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
     * Enqueue error handler (loads first to catch all errors)
     *
     * @return void
     */
    public function enqueue_error_handler(): void
    {
        wp_enqueue_script(
            'homaye-tabesh-error-handler',
            HT_PLUGIN_URL . 'assets/js/homa-error-handler.js',
            [], // No dependencies - must load first
            HT_VERSION,
            false // Load in head, not footer
        );

        // Provide configuration
        wp_localize_script('homaye-tabesh-error-handler', 'homaConfig', [
            'restUrl' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ]);
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
     * Modify CSP headers to allow GapGPT API domain
     *
     * @return void
     */
    public function modify_csp_headers(): void
    {
        // Only modify if GapGPT provider is selected
        $provider = get_option('ht_ai_provider', 'gemini_direct');
        if ($provider === 'gapgpt') {
            $base_url = get_option('ht_gapgpt_base_url', 'https://api.gapgpt.app/v1');
            $parsed_url = parse_url($base_url);
            
            // Whitelist of allowed domains for GapGPT
            $allowed_domains = [
                'https://api.gapgpt.app',
                'https://api.gapapi.com',
            ];
            
            // Validate parse_url result
            if ($parsed_url && isset($parsed_url['scheme']) && isset($parsed_url['host'])) {
                $domain = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                
                // Only allow whitelisted domains to prevent header injection
                if (in_array($domain, $allowed_domains, true)) {
                    // Add CSP header to allow connection to GapGPT
                    // This allows wp_remote_post() to work with the API
                    header("Content-Security-Policy: connect-src 'self' " . $domain . " https://generativelanguage.googleapis.com", false);
                }
            }
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
