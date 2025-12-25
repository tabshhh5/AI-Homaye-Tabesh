<?php
/**
 * Cart Manager - WooCommerce Integration for Fast Cart Operations
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * HT_Cart_Manager Class
 * 
 * Handles fast cart operations, discount applications, and metadata preservation
 * for Homa-assisted conversions.
 */
class HT_Cart_Manager
{
    /**
     * Core instance reference
     */
    private HT_Core $core;

    /**
     * Constructor
     */
    public function __construct(HT_Core $core)
    {
        $this->core = $core;
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Add cart item metadata handling
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_homa_cart_item_data'], 10, 3);
        
        // Display custom metadata in cart
        add_filter('woocommerce_get_item_data', [$this, 'display_homa_cart_item_data'], 10, 2);
        
        // Save custom metadata to order
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_homa_order_item_data'], 10, 4);
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_rest_routes(): void
    {
        // Fast cart addition endpoint
        register_rest_route('homaye/v1', '/cart/add', [
            'methods' => 'POST',
            'callback' => [$this, 'fast_add_to_cart'],
            'permission_callback' => function() {
                return is_user_logged_in() || $this->has_valid_session();
            },
            'args' => [
                'product_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return $param > 0;
                    }
                ],
                'quantity' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'variation_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint'
                ],
                'homa_config' => [
                    'required' => false,
                    'type' => 'object',
                    'sanitize_callback' => [$this, 'sanitize_homa_config']
                ]
            ]
        ]);

        // Apply discount endpoint
        register_rest_route('homaye/v1', '/cart/apply-discount', [
            'methods' => 'POST',
            'callback' => [$this, 'apply_homa_discount'],
            'permission_callback' => function() {
                return is_user_logged_in() || $this->has_valid_session();
            },
            'args' => [
                'discount_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['percentage', 'fixed', 'coupon'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'discount_value' => [
                    'required' => true,
                    'type' => 'number',
                    'sanitize_callback' => 'floatval'
                ],
                'reason' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Get cart status
        register_rest_route('homaye/v1', '/cart/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cart_status'],
            'permission_callback' => '__return_true'
        ]);

        // Clear cart
        register_rest_route('homaye/v1', '/cart/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_cart'],
            'permission_callback' => function() {
                return is_user_logged_in() || $this->has_valid_session();
            }
        ]);

        // Update cart item
        register_rest_route('homaye/v1', '/cart/update', [
            'methods' => 'POST',
            'callback' => [$this, 'update_cart_item'],
            'permission_callback' => function() {
                return is_user_logged_in() || $this->has_valid_session();
            },
            'args' => [
                'cart_item_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'quantity' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }

    /**
     * Fast add to cart with Homa configuration
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function fast_add_to_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'WooCommerce is not active'
            ], 503);
        }

        $product_id = $request->get_param('product_id');
        $quantity = $request->get_param('quantity') ?: 1;
        $variation_id = $request->get_param('variation_id') ?: 0;
        $homa_config = $request->get_param('homa_config') ?: [];

        // Validate product
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_purchasable()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Product not found or not purchasable'
            ], 404);
        }

        // Validate configuration
        if (!$this->validate_homa_config($homa_config)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid Homa configuration'
            ], 400);
        }

        // Add to cart
        try {
            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                $quantity,
                $variation_id,
                [],
                ['homa_data' => $homa_config]
            );

            if ($cart_item_key) {
                // Log conversion event
                $this->log_conversion_event('cart_add', [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'config' => $homa_config
                ]);

                return new \WP_REST_Response([
                    'success' => true,
                    'message' => 'Product added to cart successfully',
                    'cart_item_key' => $cart_item_key,
                    'cart_total' => WC()->cart->get_cart_total(),
                    'cart_count' => WC()->cart->get_cart_contents_count(),
                    'checkout_url' => wc_get_checkout_url()
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Failed to add product to cart'
                ], 500);
            }
        } catch (\Exception $e) {
            error_log('Homa Cart Manager: Error adding to cart - ' . $e->getMessage());
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Error adding product to cart'
            ], 500);
        }
    }

    /**
     * Apply Homa discount
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function apply_homa_discount(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'WooCommerce is not active'
            ], 503);
        }

        $discount_type = $request->get_param('discount_type');
        $discount_value = $request->get_param('discount_value');
        $reason = $request->get_param('reason') ?: 'Homa special offer';

        // Create dynamic coupon code
        $coupon_code = 'homa_' . wp_generate_password(8, false);

        // Create coupon
        $coupon = new \WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_description($reason);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_usage_limit_per_user(1);

        if ($discount_type === 'percentage') {
            $coupon->set_discount_type('percent');
            $coupon->set_amount($discount_value);
        } elseif ($discount_type === 'fixed') {
            $coupon->set_discount_type('fixed_cart');
            $coupon->set_amount($discount_value);
        }

        // Save coupon
        $coupon_id = $coupon->save();

        if ($coupon_id) {
            // Apply coupon to cart
            $applied = WC()->cart->apply_coupon($coupon_code);

            if ($applied) {
                // Log conversion event
                $this->log_conversion_event('discount_applied', [
                    'coupon_code' => $coupon_code,
                    'discount_type' => $discount_type,
                    'discount_value' => $discount_value,
                    'reason' => $reason
                ]);

                return new \WP_REST_Response([
                    'success' => true,
                    'message' => 'Discount applied successfully',
                    'coupon_code' => $coupon_code,
                    'cart_total' => WC()->cart->get_cart_total(),
                    'discount_amount' => WC()->cart->get_discount_total()
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Failed to apply discount'
                ], 500);
            }
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to create discount'
        ], 500);
    }

    /**
     * Get cart status
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_cart_status(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'WooCommerce is not active'
            ], 503);
        }

        $cart = WC()->cart;

        if ($cart->is_empty()) {
            return new \WP_REST_Response([
                'success' => true,
                'status' => 'empty',
                'item_count' => 0,
                'total' => 0
            ], 200);
        }

        $items = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $items[] = [
                'cart_item_key' => $cart_item_key,
                'product_id' => $cart_item['product_id'],
                'product_name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => $product->get_price(),
                'subtotal' => $cart_item['line_subtotal'],
                'homa_data' => $cart_item['homa_data'] ?? null
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'status' => 'has_items',
            'item_count' => $cart->get_cart_contents_count(),
            'total' => $cart->get_cart_total(),
            'subtotal' => $cart->get_cart_subtotal(),
            'items' => $items,
            'checkout_url' => wc_get_checkout_url()
        ], 200);
    }

    /**
     * Clear cart
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function clear_cart(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'WooCommerce is not active'
            ], 503);
        }

        WC()->cart->empty_cart();

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ], 200);
    }

    /**
     * Update cart item
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_cart_item(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->is_woocommerce_active()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'WooCommerce is not active'
            ], 503);
        }

        $cart_item_key = $request->get_param('cart_item_key');
        $quantity = $request->get_param('quantity');

        if ($quantity !== null) {
            WC()->cart->set_quantity($cart_item_key, $quantity);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Cart item updated',
            'cart_total' => WC()->cart->get_cart_total()
        ], 200);
    }

    /**
     * Add Homa cart item data
     *
     * @param array $cart_item_data
     * @param int $product_id
     * @param int $variation_id
     * @return array
     */
    public function add_homa_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array
    {
        if (isset($cart_item_data['homa_data'])) {
            $cart_item_data['homa_data'] = $this->sanitize_homa_config($cart_item_data['homa_data']);
        }
        
        return $cart_item_data;
    }

    /**
     * Display Homa cart item data
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function display_homa_cart_item_data(array $item_data, array $cart_item): array
    {
        if (isset($cart_item['homa_data']) && is_array($cart_item['homa_data'])) {
            foreach ($cart_item['homa_data'] as $key => $value) {
                // Format key for display
                $display_key = ucwords(str_replace('_', ' ', $key));
                
                $item_data[] = [
                    'key' => $display_key,
                    'value' => $value,
                    'display' => $display_key . ': ' . $value
                ];
            }
        }
        
        return $item_data;
    }

    /**
     * Save Homa order item data
     *
     * @param \WC_Order_Item_Product $item
     * @param string $cart_item_key
     * @param array $values
     * @param \WC_Order $order
     * @return void
     */
    public function save_homa_order_item_data(\WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order): void
    {
        if (isset($values['homa_data']) && is_array($values['homa_data'])) {
            foreach ($values['homa_data'] as $key => $value) {
                $item->add_meta_data('_homa_' . $key, $value, true);
            }
        }
    }

    /**
     * Validate Homa configuration
     *
     * @param array $config
     * @return bool
     */
    private function validate_homa_config(array $config): bool
    {
        // Basic validation - can be extended
        if (empty($config)) {
            return true; // Empty config is valid
        }

        // Check for malicious data
        foreach ($config as $key => $value) {
            if (!is_string($key) || (!is_string($value) && !is_numeric($value))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize Homa configuration
     *
     * @param mixed $config
     * @return array
     */
    public function sanitize_homa_config($config): array
    {
        if (!is_array($config)) {
            return [];
        }

        $sanitized = [];
        foreach ($config as $key => $value) {
            $sanitized[sanitize_key($key)] = sanitize_text_field((string)$value);
        }

        return $sanitized;
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce') && function_exists('WC') && WC()->cart !== null;
    }

    /**
     * Check if session is valid
     *
     * @return bool
     */
    private function has_valid_session(): bool
    {
        // Check WordPress session
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        
        // Check WooCommerce session (more secure validation)
        if (function_exists('WC') && WC()->session) {
            $customer_id = WC()->session->get_customer_id();
            return !empty($customer_id);
        }
        
        // Fallback to cookie check with nonce validation
        if (isset($_COOKIE['wp_woocommerce_session_'])) {
            // Verify the nonce in the request
            $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? '';
            if (wp_verify_nonce($nonce, 'wp_rest')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Log conversion event
     *
     * @param string $event_type
     * @param array $data
     * @return void
     */
    private function log_conversion_event(string $event_type, array $data): void
    {
        // Log to telemetry system if available
        if (isset($this->core->eyes)) {
            $this->core->eyes->track_event([
                'event_type' => 'conversion_' . $event_type,
                'event_data' => $data,
                'timestamp' => time()
            ]);
        }
    }
}
