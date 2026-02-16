=== VC Nexudus ===
Contributors: zabuuq
Tags: nexudus, coworking, memberships, bookings
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/license/mit

Connect WordPress to Nexudus memberships and room booking products using OAuth.

== Description ==

VC Nexudus lets administrators configure Nexudus OAuth credentials, fetch product data, and display selected products with shortcodes or a dynamic Gutenberg block.

This plugin includes:

* OAuth settings and connection test
* Product browser with shortcode helper
* `[vc_nexudus_products]` shortcode
* `[vc_nexudus_product]` shortcode
* Dynamic block: **VC Nexudus Products**
* 24-hour transient caching and clear-cache button

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **Settings > VC Nexudus**.
4. Enter your API base URL, OAuth token URL, client credentials, and endpoint paths.
5. Save settings and test connection.

== Frequently Asked Questions ==

= Does this plugin process checkout? =

No. It only displays product information and links out to Nexudus URLs.

== Changelog ==

= 0.1.0 =
* Initial release.
