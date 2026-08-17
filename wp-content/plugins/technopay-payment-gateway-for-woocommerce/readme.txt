=== تکنوپی برای ووکامرس | Technopay ===
Contributors: vhamed32
Tags: technopay, woocommerce, تکنوپی, تکنو پی, تکنولایف
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure credit payment gateway plugin for WooCommerce by TechnoPay

== Description ==

TechnoPay Payment Gateway for WooCommerce is a professional payment gateway for WooCommerce stores that provides installment payment capabilities for customers.

= Features =

* Full support for WooCommerce Blocks
* Compatible with modern themes
* HPOS (High-Performance Order Storage) support
* Test mode for developers
* Advanced debug logging
* Iranian mobile number validation
* Support for Rial and Toman currencies
* Multilingual interface

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* Active TechnoPay account

== Installation ==

1. Upload plugin files to `/wp-content/plugins/technopay-for-woocommerce` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Payments > TechnoPay
4. Enter your TechnoPay credentials

== Frequently Asked Questions ==

= Does this plugin work with my theme? =

Yes, this plugin is compatible with all WooCommerce themes, especially modern themes that use WooCommerce Blocks.

= How can I get a TechnoPay account? =

Visit technopay.ir website to create an account.

= Does it have test mode? =

Yes, you can enable test mode from settings to send payments to test server.

== Changelog ==

= 1.2.1 =
* Update: Plugin name updated to تکنوپی برای ووکامرس | Technopay
* Update: Plugin description updated to Persian
* Update: Author name and URI updated

= 1.2.0 =
* New: Refund orders admin page under WooCommerce with full RTL support
* New: Vazirmatn variable font applied to entire admin page
* New: Custom SlimSelect dropdowns replacing native selects in filters and modals
* New: Dynamic refund reasons loaded from TechnoPay API (/payment/reasons)
* New: Refund reason codes sent to API on refund creation (reason_codes + description)
* New: Details modal showing refund_reasons and reject_reasons per order
* New: Info icon shown for rejected orders with reasons
* New: SVN-publishable plugin, mock plugin and request logger as separate toggleable plugins
* Fix: SSL verification disabled in test/staging mode
* Fix: Correct reason label displayed in details modal (Persian text from API)

= 1.1.4 =
* Fix: Improved AJAX checkout compatibility for third-party plugins
* Fix: Better cart handling during payment processing
* Improvement: Enhanced context_needs_payment method for edge cases

= 1.1.3 =
* Fix: Cart emptying issue during checkout process
* Fix: Payment amount calculation now uses order object instead of cart
* New: Configurable mobile number source (Billing Phone / User Meta / Custom Order Meta)
* New: Custom meta key field for flexible mobile number retrieval
* Improvement: Better support for sites with custom user mobile fields
* Improvement: Enhanced compatibility with order-pay (retry payment) flow

= 1.1.2 =
* Fix: Gateway not visible on pay-for-order page when cart is empty

= 1.1.1 =
* Fixed WordPress.org plugin directory compliance issues
* Removed deprecated load_plugin_textdomain() usage
* Improved translation loading mechanism
* Updated plugin name to comply with trademark guidelines
* Enhanced security with proper prefix usage (TPFW_)
* All plugin-check validations now pass

= 1.1.0 =
* Complete Persian (Farsi) translation support
* Fixed WordPress.org class naming convention compliance
* Updated plugin name to comply with trademark guidelines
* Enhanced plugin metadata translation
* Improved Persian localization for admin interface
* Fixed all WordPress.org plugin repository requirements
* Added proper plugin prefix for all classes
* Version display in Persian numerals for Persian locale

= 1.0.1 =
* Security improvements with added sanitization and validation
* Improved error handling
* Added is_available() method
* Enhanced logging system
* Added User-Agent header
* Code improvements and refactoring
* Added debug mode setting
* Improved localization
* Fixed all WordPress.org plugin repository compliance issues
* Enhanced security with proper escaping and nonce verification
* Optimized database queries with caching
* Fixed text domain consistency
