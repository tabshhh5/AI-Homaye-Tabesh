<?php
/**
 * WooCommerce Context Provider
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

declare(strict_types=1);

namespace HomayeTabesh;

/**
 * استخراج متادیتاهای محصول و وضعیت سبد خرید
 * Context Provider برای Gemini API
 */
class HT_WooCommerce_Context
{
    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    public function is_woocommerce_active(): bool
    {
        static $is_active = null;
        
        if ($is_active === null) {
            $is_active = class_exists('WooCommerce');
        }
        
        return $is_active;
    }

    /**
     * Get current cart status
     *
     * @return array Cart status data
     */
    public function get_cart_status(): array
    {
        if (!$this->is_woocommerce_active()) {
            return [
                'status' => 'unavailable',
                'message' => 'WooCommerce is not active',
            ];
        }

        // Ensure WooCommerce is fully loaded
        if (!function_exists('WC') || !WC()->cart) {
            return [
                'status' => 'unavailable',
                'message' => 'Cart not initialized',
            ];
        }
        
        $cart = WC()->cart;

        if ($cart->is_empty()) {
            return [
                'status' => 'empty',
                'item_count' => 0,
                'total' => 0,
                'currency' => get_woocommerce_currency(),
            ];
        }

        return [
            'status' => 'has_items',
            'item_count' => $cart->get_cart_contents_count(),
            'total' => $cart->get_cart_contents_total(),
            'total_formatted' => wc_price($cart->get_cart_contents_total()),
            'currency' => get_woocommerce_currency(),
            'items' => $this->get_cart_items_summary(),
        ];
    }

    /**
     * Get cart items summary
     *
     * @return array Cart items summary
     */
    private function get_cart_items_summary(): array
    {
        if (!$this->is_woocommerce_active()) {
            return [];
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return [];
        }

        $items = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            
            $items[] = [
                'product_id' => $cart_item['product_id'],
                'name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => $product->get_price(),
                'subtotal' => $cart_item['line_subtotal'],
            ];
        }

        return $items;
    }

    /**
     * Get current product context
     *
     * @param int|null $product_id Product ID (null for current product)
     * @return array Product context data
     */
    public function get_product_context(?int $product_id = null): array
    {
        if (!$this->is_woocommerce_active()) {
            return [
                'status' => 'unavailable',
                'message' => 'WooCommerce is not active',
            ];
        }

        // Get product ID from current page if not provided
        if ($product_id === null) {
            if (is_product()) {
                $product_id = get_the_ID();
            } else {
                return [
                    'status' => 'not_product_page',
                    'message' => 'Not on a product page',
                ];
            }
        }

        $product = wc_get_product($product_id);
        
        if (!$product) {
            return [
                'status' => 'not_found',
                'message' => 'Product not found',
            ];
        }

        return [
            'status' => 'available',
            'product_id' => $product_id,
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'price' => $product->get_price(),
            'price_formatted' => wc_price($product->get_price()),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'on_sale' => $product->is_on_sale(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'categories' => $this->get_product_categories($product_id),
            'tags' => $this->get_product_tags($product_id),
            'attributes' => $this->get_product_attributes($product),
            'meta_data' => $this->get_product_custom_meta($product_id),
        ];
    }

    /**
     * Get product categories
     *
     * @param int $product_id Product ID
     * @return array Category names
     */
    private function get_product_categories(int $product_id): array
    {
        $terms = get_the_terms($product_id, 'product_cat');
        
        if (!$terms || is_wp_error($terms)) {
            return [];
        }

        return array_map(function($term) {
            return $term->name;
        }, $terms);
    }

    /**
     * Get product tags
     *
     * @param int $product_id Product ID
     * @return array Tag names
     */
    private function get_product_tags(int $product_id): array
    {
        $terms = get_the_terms($product_id, 'product_tag');
        
        if (!$terms || is_wp_error($terms)) {
            return [];
        }

        return array_map(function($term) {
            return $term->name;
        }, $terms);
    }

    /**
     * Get product attributes
     *
     * @param \WC_Product $product Product object
     * @return array Product attributes
     */
    private function get_product_attributes(\WC_Product $product): array
    {
        $attributes = [];
        
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name());
                $values = array_map(function($term) {
                    return $term->name;
                }, $terms);
                
                $attributes[$attribute->get_name()] = $values;
            } else {
                $attributes[$attribute->get_name()] = $attribute->get_options();
            }
        }

        return $attributes;
    }

    /**
     * Get custom product metadata (paper type, tirage, etc.)
     *
     * @param int $product_id Product ID
     * @return array Custom metadata
     */
    private function get_product_custom_meta(int $product_id): array
    {
        // Common printing-related meta keys
        $meta_keys = [
            '_paper_type',
            '_paper_weight',
            '_print_quality',
            '_tirage',
            '_binding_type',
            '_cover_type',
            '_page_count',
            '_color_mode',
            '_finish_type',
        ];

        $meta_data = [];
        
        foreach ($meta_keys as $key) {
            $value = get_post_meta($product_id, $key, true);
            if (!empty($value)) {
                $meta_data[str_replace('_', '', $key)] = $value;
            }
        }

        return $meta_data;
    }

    /**
     * Get full context for Gemini API
     *
     * @param int|null $product_id Optional product ID
     * @return array Full context data
     */
    public function get_full_context(?int $product_id = null): array
    {
        return [
            'cart' => $this->get_cart_status(),
            'current_product' => $this->get_product_context($product_id),
            'page_type' => $this->get_page_type(),
            'currency' => get_woocommerce_currency(),
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Get current page type
     *
     * @return string Page type
     */
    private function get_page_type(): string
    {
        if (is_product()) {
            return 'product';
        } elseif (is_shop()) {
            return 'shop';
        } elseif (is_cart()) {
            return 'cart';
        } elseif (is_checkout()) {
            return 'checkout';
        } elseif (is_account_page()) {
            return 'account';
        } elseif (is_product_category()) {
            return 'category';
        } elseif (is_product_tag()) {
            return 'tag';
        }

        return 'other';
    }

    /**
     * Format context for AI prompt
     *
     * @param array $context Context data
     * @return string Formatted context string
     */
    public function format_for_ai(array $context): string
    {
        $output = "وضعیت فعلی WooCommerce:\n\n";

        // Cart status
        if ($context['cart']['status'] === 'empty') {
            $output .= "- سبد خرید: خالی\n";
        } elseif ($context['cart']['status'] === 'has_items') {
            $output .= sprintf(
                "- سبد خرید: %d محصول (جمع: %s)\n",
                $context['cart']['item_count'],
                $context['cart']['total_formatted']
            );
            
            if (!empty($context['cart']['items'])) {
                $output .= "- محصولات در سبد:\n";
                foreach ($context['cart']['items'] as $item) {
                    $output .= sprintf("  * %s (تعداد: %d)\n", $item['name'], $item['quantity']);
                }
            }
        }

        // Current product
        if ($context['current_product']['status'] === 'available') {
            $product = $context['current_product'];
            $output .= sprintf("\n- محصول در حال مشاهده: %s\n", $product['name']);
            $output .= sprintf("- قیمت: %s\n", $product['price_formatted']);
            
            if ($product['on_sale']) {
                $output .= "- در حال تخفیف: بله\n";
            }
            
            if (!empty($product['categories'])) {
                $output .= sprintf("- دسته‌بندی: %s\n", implode(', ', $product['categories']));
            }
            
            if (!empty($product['meta_data'])) {
                $output .= "- مشخصات فنی:\n";
                foreach ($product['meta_data'] as $key => $value) {
                    $output .= sprintf("  * %s: %s\n", $key, $value);
                }
            }
        }

        $output .= sprintf("\n- نوع صفحه: %s\n", $context['page_type']);

        return $output;
    }
}
