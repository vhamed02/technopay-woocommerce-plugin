=== TechnoPay for WooCommerce ===
Contributors: technopay
Tags: woocommerce, payment, gateway, iran, technopay
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

TechnoPay for WooCommerce with full support for WooCommerce Blocks and modern themes

== Description ==

TechnoPay for WooCommerce is a professional payment gateway for WooCommerce stores that provides installment payment capabilities for customers.

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

= 1.0.0 =
* Initial release

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
