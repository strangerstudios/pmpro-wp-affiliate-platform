=== Paid Memberships Pro - WP Affiliate Platform Add On ===
Contributors: strangerstudios
Tags: paid memberships pro, pmpro, membership, affiliates, wp-affiliate-platform
Requires at least: 4.7
Tested up to: 5.2.2
Stable tag: 1.7.2

Process an affiliate via WP Affiliate Platform after a PMPro checkout.

== Description ==
This plugin requires that both WP Affiliate Platform and Paid Memberships Pro are installed, activated, and configured.

== Installation ==

1. Make sure that WP-Affiliate-Platform is activated and setup correctly.
1. Upload the `pmpro-wp-affiliate-platform` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. That's it! All orders from affiliates will have their affiliate id tracked in the PMPro order and will kick off conversions in WPAP.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-wp-affiliate-platform/issues

== Changelog ==

= 1.7.2 =
* Bug Fix: Check if WP Affiliate Platform is installed and active to avoid fatal errors.

= 1.7.1 =
* BUG: Checking ->subtotal if ->total is not set for an order to make sure the order has a value.

= 1.7 =
* Merged in updates from the WP Affiliate Platform version of this addon v1.6
* Now has support for PayPal Standard and 2Checkout.
* Better handling of recurring orders.

= .3.1.1 =
* Added readme.
* Removed debug code in admin email when non-affiliate checkouts occur.