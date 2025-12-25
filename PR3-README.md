# PR3: Inference Engine - Quick Reference

## 🚀 What's New

This PR implements the complete AI inference system for Homaye Tabesh:

- **Inference Engine**: Combines all knowledge sources for intelligent decisions
- **Knowledge Injection**: Dynamic business rules integration
- **Action Dispatcher**: UI commands from AI responses
- **Enhanced Security**: Anti-prompt injection filters
- **REST API**: 4 endpoints for frontend integration

## 📁 New Files

### Backend (PHP)
```
includes/HT_Inference_Engine.php        - Main inference engine
includes/HT_Prompt_Builder_Service.php  - Prompt builder with knowledge injection
includes/HT_Action_Parser.php           - Parse AI responses
includes/HT_AI_Controller.php           - REST API endpoints
```

### Frontend (JavaScript)
```
assets/js/ui-executor.js                - Execute UI commands
```

### Knowledge Base
```
knowledge-base/pricing.json             - Pricing rules & calculations
knowledge-base/faq.json                 - Frequently asked questions
```

### Documentation
```
PR3-IMPLEMENTATION.md                   - Complete technical documentation
PR3-QUICKSTART.md                       - Quick start guide
PR3-SUMMARY.md                          - Implementation summary
examples/pr3-usage-examples.php         - Usage examples
validate-pr3.php                        - Validation script
```

## 🔧 Quick Start

### 1. Test the system
```bash
php validate-pr3.php
```

### 2. Check API health
```bash
curl http://your-site.com/wp-json/homaye/v1/ai/health
```

### 3. Send a query
```javascript
fetch('/wp-json/homaye/v1/ai/query', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        user_id: 'guest_' + Date.now(),
        message: 'می‌خواهم کتاب چاپ کنم'
    })
})
.then(res => res.json())
.then(data => console.log(data));
```

### 4. Use the shortcode
```
[homa_chat]
```

## 📊 API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/homaye/v1/ai/query` | POST | Ask Homa a question |
| `/homaye/v1/ai/suggestion` | POST | Get contextual suggestion |
| `/homaye/v1/ai/intent` | POST | Analyze user intent |
| `/homaye/v1/ai/health` | GET | Check system health |

## 🎯 UI Actions

The system supports 9 action types:

1. `highlight_element` - Highlight an element
2. `show_tooltip` - Show a tooltip
3. `scroll_to` - Scroll to element
4. `open_modal` - Open a modal
5. `update_calculator` - Update calculator field
6. `suggest_product` - Suggest a product
7. `show_discount` - Show discount code
8. `change_css` - Change CSS property
9. `redirect` - Redirect to URL

## 🔒 Security Features

- ✅ Prompt injection filters
- ✅ Input sanitization
- ✅ Rate limiting ready
- ✅ Nonce validation support
- ✅ Anti-hallucination measures

## 📚 Documentation

**For Developers:**
- [PR3-IMPLEMENTATION.md](PR3-IMPLEMENTATION.md) - Full technical docs
- [PR3-QUICKSTART.md](PR3-QUICKSTART.md) - Quick start guide
- [examples/pr3-usage-examples.php](examples/pr3-usage-examples.php) - Code examples

**For Testing:**
- [validate-pr3.php](validate-pr3.php) - Automated validation

## ✅ Validation

Run the validation script to ensure everything works:

```bash
php validate-pr3.php
```

Expected output:
```
✓ PHP Version >= 8.2
✓ Core files exist
✓ PHP syntax validation
✓ JSON files validation
✓ Class structure validation
✓ JavaScript syntax validation
✓ Knowledge base content validation
✓ Security: Prompt injection filter
✓ Documentation completeness
✓ REST API structure validation

All tests passed! ✓
```

## 🌟 Key Features

### Inference Engine
```php
$engine = HT_Core::instance()->inference_engine;
$result = $engine->generate_decision([
    'user_identifier' => 'user_123',
    'message' => 'سوال کاربر',
]);
```

### Prompt Builder
```php
$builder = new HT_Prompt_Builder_Service();
$instruction = $builder->build_system_instruction('user_123');
$prompt = $builder->build_user_prompt('سوال', 'user_123');
```

### Action Parser
```php
$parser = new HT_Action_Parser();
$parsed = $parser->parse_response($ai_response);
$frontend_format = $parser->to_frontend_format($parsed);
```

### UI Executor
```javascript
window.HomaUIExecutor.executeAction({
    type: 'show_tooltip',
    target: '.element',
    message: 'پیام راهنما'
});
```

## 🎨 Example Usage

### Simple Chat
```html
<button onclick="askHoma('کمک می‌خواهم')">Ask Homa</button>

<script>
async function askHoma(message) {
    const response = await fetch('/wp-json/homaye/v1/ai/query', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: 'guest_' + Date.now(),
            message: message
        })
    });
    const data = await response.json();
    alert('Homa: ' + data.message);
}
</script>
```

## 📈 Performance

- **Caching**: Persona cached for 1 hour
- **Lazy Loading**: Knowledge loaded on-demand
- **Async**: Non-blocking REST API
- **Optimized**: Smart context selection

## 🔥 Anti-Hallucination

- **Temperature**: 0.1 (very low)
- **Structured Output**: Enforced JSON schema
- **Exact Knowledge**: Direct injection of pricing data
- **Constraints**: Clear boundaries and rules

## 📦 Commits

1. `feat: Add core inference engine components`
2. `docs: Add comprehensive documentation and usage examples`
3. `test: Add comprehensive validation script and quick start guide`
4. `docs: Add final PR3 implementation summary`

## 🎯 Testing Checklist

- [x] PHP syntax validation
- [x] JSON validation
- [x] Class structure
- [x] JavaScript syntax
- [x] Security filters
- [x] Documentation completeness
- [x] API endpoints
- [x] Knowledge base content
- [x] All 10 tests passing

## 🚀 Deployment

1. Merge this PR
2. Update dependencies: `composer install`
3. Activate plugin
4. Set Gemini API key
5. Test with validation script
6. Deploy to production

## 📞 Support

- **Issues**: GitHub Issues
- **Docs**: See documentation files
- **Examples**: Check examples directory

---

**Status**: ✅ Ready for Production  
**Version**: 1.0.0  
**Tests**: 10/10 Passing  
**Last Updated**: 2024-12-25
