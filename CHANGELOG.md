# Changelog

All notable changes to the Homaye Tabesh plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-25

### Added

#### Core Architecture
- Implemented PSR-4 autoloading with Composer
- Created `HT_Core` singleton class as main orchestrator
- Added strict type declarations (PHP 8.2+)
- Implemented modular architecture with separate concerns

#### Gemini 2.5 Flash Integration
- `HT_Gemini_Client` class for API communication
- Support for structured JSON outputs via `responseSchema`
- Context injection for WooCommerce products
- Fallback mechanism for API failures
- System instruction building from business rules
- Error handling and logging

#### Telemetry System
- `HT_Telemetry` class for behavioral tracking
- REST API endpoints:
  - `POST /wp-json/homaye/v1/telemetry` - Single event
  - `POST /wp-json/homaye/v1/telemetry/batch` - Batch events
- JavaScript tracker (`tracker.js`) for frontend events
- Divi theme integration with automatic element detection
- Event types: hover, click, long_view, scroll_to
- Batch processing to reduce HTTP requests
- Mutation observer for dynamic content

#### Persona Management
- `HT_Persona_Manager` class for user scoring
- Database tables for persistence:
  - `wp_homaye_persona_scores` - User scores
  - `wp_homaye_telemetry_events` - Event history
- Lead scoring algorithm
- Persona types:
  - Author (نویسنده)
  - Business (کسب‌وکار)
  - Designer (طراح)
  - Student (دانشجو)
  - General (عمومی)
- Session persistence
- Confidence calculation
- Behavior summary generation

#### Knowledge Base
- `HT_Knowledge_Base` class for business rules
- JSON-based rule storage:
  - `personas.json` - Persona indicators and recommendations
  - `products.json` - Product information and pricing
  - `responses.json` - Response tone and guidelines
- Rule-to-prompt conversion
- System instruction generation
- Default knowledge base initialization

#### Admin Interface
- `HT_Admin` class for WordPress admin integration
- Settings page with configuration options:
  - Gemini API key
  - Tracking enable/disable
  - Divi integration toggle
  - Minimum score threshold
- System status dashboard
- Persona statistics page
- Recent events viewer

#### Frontend Tracking
- Lightweight JavaScript tracker
- Divi element detection (`.et_pb_*` classes)
- WooCommerce element tracking
- Custom element tracking via `data-homaye-track` attribute
- Intelligent batching (10 events or 5 seconds)
- No conflict with Divi Visual Builder

#### Documentation
- Comprehensive README in Persian
- Usage examples file
- Inline code documentation
- Knowledge base JSON schemas

#### Database
- Automatic table creation on activation
- Proper indexes for performance
- UTF-8 character support for Persian content

### Technical Details

#### Requirements
- PHP >= 8.2
- WordPress >= 6.0
- Composer for dependency management

#### File Structure
```
homaye-tabesh/
├── homaye-tabesh.php          # Main plugin file
├── includes/                   # PHP classes
│   ├── HT_Core.php
│   ├── HT_Gemini_Client.php
│   ├── HT_Telemetry.php
│   ├── HT_Persona_Manager.php
│   ├── HT_Knowledge_Base.php
│   ├── HT_Admin.php
│   ├── HT_Activator.php
│   └── HT_Deactivator.php
├── assets/
│   ├── js/tracker.js
│   └── css/admin.css
├── knowledge-base/
│   ├── personas.json
│   ├── products.json
│   └── responses.json
├── examples/
│   └── usage-examples.php
└── composer.json
```

#### API Integration
- Google Gemini 2.0 Flash Experimental model
- REST API v2 for WordPress integration
- Nonce-based security for AJAX requests

#### Performance Considerations
- Optimized autoloader
- Event batching to reduce database writes
- Efficient database queries with proper indexes
- Minimal frontend JavaScript footprint
- No tracking for admin users

#### Security
- Input sanitization
- Output escaping
- Nonce verification
- Capability checks
- No sensitive data in code
- API key stored in WordPress options

### Compatibility
- WordPress 6.0+
- PHP 8.2+
- Divi Theme (optional but recommended)
- WooCommerce (optional for product context)

### Known Limitations
- Requires manual API key configuration
- Telemetry requires JavaScript enabled
- Session-based tracking (doesn't persist across devices)

## [Unreleased]

### Planned Features
- ChatGPT alternative support
- Multi-language support (English)
- Analytics dashboard with charts
- Export persona data
- Custom persona creation
- A/B testing integration
- Email notification triggers
- CRM integration hooks

---

**Legend:**
- Added: New features
- Changed: Changes in existing functionality
- Deprecated: Soon-to-be removed features
- Removed: Removed features
- Fixed: Bug fixes
- Security: Security improvements
