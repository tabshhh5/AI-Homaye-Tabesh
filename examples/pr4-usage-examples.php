<?php
/**
 * PR4 Usage Examples - Core Intelligence Layer
 * Examples demonstrating the Environmental Perception capabilities
 *
 * @package HomayeTabesh
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Basic Semantic Indexing
 * 
 * The semantic indexer automatically scans the page and indexes all important elements.
 * Access the indexed map via JavaScript console:
 * 
 * > HomaIndexer.map
 * > HomaIndexer.findBySemanticName('نام_کتاب')
 * > HomaIndexer.findByType('input')
 * > HomaIndexer.findByDiviModule('pricing_table')
 */

/**
 * Example 2: Live Input Monitoring with Intent Detection
 * 
 * Register a callback to respond to user input in real-time:
 * 
 * JavaScript:
 * ```javascript
 * HomaInputObserver.onIntent((eventType, data) => {
 *     if (eventType === 'intent_detected') {
 *         console.log('User typed in:', data.fieldName);
 *         console.log('Detected concepts:', data.concepts);
 *         
 *         // Patterns detected
 *         if (data.concepts.patterns.includes('book_related')) {
 *             console.log('User is interested in books!');
 *             // Show relevant suggestions
 *         }
 *     }
 * });
 * ```
 */

/**
 * Example 3: Spatial Navigation
 * 
 * Navigate to elements programmatically:
 * 
 * JavaScript:
 * ```javascript
 * // Scroll to an element
 * HomaNavigation.scrollTo('.et_pb_pricing', {
 *     offset: 100,
 *     duration: 800,
 *     highlight: true
 * });
 * 
 * // Focus on a specific field by semantic name
 * HomaNavigation.navigateToField('نام_کتاب');
 * 
 * // Center element in viewport
 * HomaNavigation.centerElement('#book_title');
 * 
 * // Navigate back to previous element
 * HomaNavigation.navigateBack();
 * ```
 */

/**
 * Example 4: Interactive Tour
 * 
 * Create an educational tour for users:
 * 
 * JavaScript:
 * ```javascript
 * // Start a predefined tour
 * HomaTour.start({
 *     title: 'راهنمای سفارش چاپ کتاب',
 *     steps: [
 *         {
 *             selector: '#book_title',
 *             title: 'عنوان کتاب',
 *             message: 'ابتدا نام کتاب خود را در این فیلد وارد کنید'
 *         },
 *         {
 *             selector: '#book_pages',
 *             title: 'تعداد صفحات',
 *             message: 'تعداد صفحات کتاب خود را مشخص کنید'
 *         },
 *         {
 *             selector: '.et_pb_pricing',
 *             title: 'جدول قیمت',
 *             message: 'قیمت نهایی در اینجا محاسبه می‌شود'
 *         }
 *     ]
 * });
 * 
 * // Or show a single step
 * startHomaTour({
 *     selector: '.calculate-btn',
 *     title: 'محاسبه قیمت',
 *     message: 'با کلیک روی این دکمه، قیمت محاسبه می‌شود'
 * });
 * ```
 */

/**
 * Example 5: Server-side Intent Analysis
 * 
 * The perception bridge sends input data to the server for AI analysis:
 */
function example_analyze_user_input()
{
    // This is called automatically by the frontend input observer
    // But you can also call it manually via REST API:
    
    $response = wp_remote_post(rest_url('homaye/v1/ai/analyze-intent'), [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ],
        'body' => json_encode([
            'field_name' => 'نام کتاب',
            'field_value' => 'رمان عاشقانه برای نوجوانان',
            'concepts' => [
                'keywords' => ['رمان', 'عاشقانه', 'نوجوانان'],
                'patterns' => ['story_related', 'children_related']
            ],
            'is_final' => false
        ])
    ]);
    
    if (!is_wp_error($response)) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Body contains:
        // - suggestions: Array of actionable suggestions
        // - message: AI-generated message
        // - confidence: Confidence score
        
        return $body;
    }
    
    return null;
}

/**
 * Example 6: Getting Tour Steps
 * 
 * Retrieve predefined tour steps from the server:
 */
function example_get_tour_steps()
{
    $response = wp_remote_get(
        add_query_arg('workflow', 'book_printing', rest_url('homaye/v1/tour/get-steps'))
    );
    
    if (!is_wp_error($response)) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Body contains the complete tour configuration
        return $body['tour'] ?? null;
    }
    
    return null;
}

/**
 * Example 7: Navigation Suggestions
 * 
 * Get contextual navigation suggestions based on user persona:
 */
function example_get_navigation_suggestions()
{
    $response = wp_remote_post(rest_url('homaye/v1/navigation/suggest'), [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ],
        'body' => json_encode([
            'current_location' => '/products/book-printing/',
            'user_context' => [
                'page_type' => 'product',
                'scroll_depth' => 75
            ]
        ])
    ]);
    
    if (!is_wp_error($response)) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Body contains suggestions based on persona:
        // - selector: CSS selector for the element
        // - label: Human-readable label
        // - priority: Suggestion priority (1-10)
        
        return $body['suggestions'] ?? [];
    }
    
    return [];
}

/**
 * Example 8: Complete Integration Example
 * 
 * How everything works together:
 */
function example_complete_workflow()
{
    ?>
    <script>
    jQuery(document).ready(function($) {
        // 1. Wait for all perception modules to load
        console.log('Homa Perception Layer Status:');
        console.log('- Indexer:', typeof HomaIndexer !== 'undefined' ? '✓ Ready' : '✗ Not loaded');
        console.log('- Input Observer:', typeof HomaInputObserver !== 'undefined' ? '✓ Ready' : '✗ Not loaded');
        console.log('- Navigator:', typeof HomaSpatialNavigator !== 'undefined' ? '✓ Ready' : '✗ Not loaded');
        console.log('- Tour Manager:', typeof HomaTourManager !== 'undefined' ? '✓ Ready' : '✗ Not loaded');
        
        // 2. Monitor book title input
        HomaInputObserver.onIntent((eventType, data) => {
            if (eventType === 'intent_detected' && data.fieldName.includes('کتاب')) {
                console.log('Book-related input detected!');
                
                // Check if user mentioned "children" or "کودک"
                const value = data.value.toLowerCase();
                if (value.includes('کودک') || value.includes('children')) {
                    // Navigate to children's book section
                    setTimeout(() => {
                        HomaNavigation.scrollTo('[href*="children-books"]', {
                            highlight: true
                        }).then(() => {
                            // Show tooltip
                            if (window.HomaUIExecutor) {
                                HomaUIExecutor.executeAction({
                                    type: 'show_tooltip',
                                    target: '[href*="children-books"]',
                                    message: 'ما خدمات ویژه‌ای برای چاپ کتاب کودک داریم!'
                                });
                            }
                        });
                    }, 2000);
                }
            }
        });
        
        // 3. Add a help button to start tour
        const helpButton = $('<button>')
            .addClass('homa-help-button')
            .text('📚 راهنما')
            .css({
                position: 'fixed',
                bottom: '20px',
                right: '20px',
                padding: '12px 24px',
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                color: 'white',
                border: 'none',
                borderRadius: '25px',
                cursor: 'pointer',
                fontSize: '16px',
                boxShadow: '0 4px 15px rgba(0,0,0,0.2)',
                zIndex: 99999
            })
            .on('click', function() {
                // Start the book printing tour
                HomaTour.start({
                    title: 'راهنمای سفارش چاپ کتاب',
                    steps: [
                        {
                            selector: '#book_title',
                            title: 'عنوان کتاب',
                            message: 'ابتدا نام کتاب خود را در این فیلد وارد کنید'
                        },
                        {
                            selector: '#book_pages',
                            title: 'تعداد صفحات',
                            message: 'تعداد صفحات کتاب خود را مشخص کنید'
                        },
                        {
                            selector: '#book_quantity',
                            title: 'تیراژ',
                            message: 'تیراژ مورد نیاز خود را وارد کنید. تیراژ بالاتر = قیمت هر نسخه کمتر!'
                        },
                        {
                            selector: '.et_pb_pricing',
                            title: 'جدول قیمت',
                            message: 'بر اساس اطلاعات وارد شده، قیمت نهایی در اینجا نمایش داده می‌شود'
                        }
                    ]
                });
            });
        
        $('body').append(helpButton);
        
        // 4. Log indexed elements for debugging
        setTimeout(() => {
            console.log('Indexed elements:', HomaIndexer.getAll().length);
            console.log('Sample indexed element:', HomaIndexer.getAll()[0]);
        }, 2000);
    });
    </script>
    <?php
}
// add_action('wp_footer', 'example_complete_workflow');

/**
 * Example 9: Dynamic Content Handling (Shortcodes)
 * 
 * The perception layer automatically detects and indexes dynamically loaded content:
 * 
 * - Forms loaded via shortcodes
 * - AJAX-loaded content
 * - Divi Visual Builder changes
 * 
 * No additional configuration needed - the MutationObserver handles this automatically!
 */

/**
 * Example 10: Privacy Protection
 * 
 * Sensitive fields are automatically ignored:
 * 
 * HTML:
 * ```html
 * <!-- This field will NOT be monitored -->
 * <input type="password" name="user_password">
 * 
 * <!-- This field will NOT be monitored (has ignore flag) -->
 * <input type="text" name="credit_card" data-homa-ignore>
 * 
 * <!-- This field WILL be monitored -->
 * <input type="text" name="book_title" placeholder="نام کتاب">
 * ```
 */

/**
 * Testing the Implementation
 * 
 * Open browser console and run:
 * 
 * 1. Check if modules loaded:
 *    console.log(HomaIndexer, HomaInputObserver, HomaSpatialNavigator, HomaTourManager);
 * 
 * 2. View indexed elements:
 *    HomaIndexer.getAll()
 * 
 * 3. Find specific field:
 *    HomaIndexer.findBySemanticName('نام_کتاب')
 * 
 * 4. Test navigation:
 *    HomaNavigation.navigateToField('نام_کتاب')
 * 
 * 5. Start a tour:
 *    startHomaTour({selector: '#book_title', title: 'تست', message: 'این یک تست است'})
 * 
 * 6. Test input monitoring:
 *    - Type in any input field
 *    - Wait 800ms
 *    - Check console for intent detection logs
 */
