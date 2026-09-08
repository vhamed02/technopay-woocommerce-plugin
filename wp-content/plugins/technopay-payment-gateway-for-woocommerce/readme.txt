=== تکنوپی برای ووکامرس | Technopay ===
Contributors: vhamed32
Tags: technopay, woocommerce, تکنوپی, تکنو پی, تکنولایف
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.3
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

= 1.3.3 =
* Fix: Admin notices from other plugins now render outside the plugin page container

= 1.3.2 =
* Update: Payment date is now read from the ticket status change time
* Fix: Admin notices from other plugins no longer render inside the page header

= 1.3.1 =
* Fix: Payment date falls back to the ticket status change time when the paid date is missing
* Fix: Dates returned without a timezone are read in the site timezone instead of UTC
* Fix: Actions column is no longer clipped when the table is wider than the screen
* Fix: Payment ID filter placeholder is now aligned to the right

= 1.3.0 =
* New: Refund admin page split into two tabs - refundable payments and refund requests
* New: Refundable payments tab lists only payments that can still be refunded and is the only place a refund request is submitted
* New: Refund requests tab loads from the /payment/refunds endpoint and handles refund cancellation
* New: Payment ID filter added to the refund admin page
* Update: Submitting a refund request now opens the refund requests tab
* Fix: Refund reasons list was empty because reasons were matched against the wrong type
* Fix: Description field now appears for every reason that requires one

= 1.2.2 =
* Update: Added one-time refund notice to the refund modal

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
