=== WooCommerce UPS Shipping – Live Rates and Access Points ===
Contributors: wpdesk
Donate link: https://wordpress.org/plugins/flexible-shipping-ups/
Tags: woocommerce ups, ups, ups woocommerce, ups shipping, ups api, shipping rates, shipping method, flexible shipping, woocommerce shipping, UPS Access Points, access point
Requires at least: 4.5
Tested up to: 5.1.0
Stable tag: 1.4.1
Requires PHP: 5.6
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

UPS WooCommerce plugin lets you offer a full range of UPS shipping options. UPS Access Points support and Live Shipping Rates, integrate in 5 minutes.

== Description ==

= Seamless UPS WooCommerce integration =

UPS WooCommerce plugin lets you offer a full range of UPS shipping options. You’ll integrate the plugin in just 5 minutes. 

Your clients will see every UPS shipping option in the checkout with its real price. The shipping cost is calculated automatically online due to UPS API connection. You can **offer delivery to UPS Access Point**, too. Also, points selected by customers save to WooCommerce order. 

**Give your customers the opportunity to pick up packages when and where it's best for them.** Enable Access Points support to show store customers the option to choose an Access Point location. The plugin suggests the nearest points for the customer's address and saves the selected point to the customer’s order.

UPS is a trusted brand. It is one of the leaders in its category. UPS delivers 18 million parcels and letters worldwide everyday. You’ll integrate UPS services with your WooCommerce store via the UPS API. **You’ll provide your clients with a choice of the brand they trust**.

You’ll integrate UPS services with your store **within a few moments** and will be able to offer dynamic UPS rates to your customers. Your customers will be able to choose Access Points, too. **Give your customers access to more than 27,000 such locations across Europe and North America to pick up their online purchases**. 

This plugin integrates well with WooCommerce. It lets you add UPS shipping methods to your store’s shipping zones in WooCommerce shipping settings.

= Actively developed and supported =

This UPS WooCommerce plugin is developed by WP Desk. Our plugins are used by **over 18.000 WooCommerce stores worldwide**. WP Desk is proven to offer high quality, stable plugins, and astonishing support. Choose the plugin of this trusty developer to avoid problems in the future.

= Automatic UPS shipping cost calculator =

* connects to UPS Rates API to offer online shipping cost calculations for every order
* offer delivery to UPS Access Points and save selected points to WooCommerce order 
* calculates UPS shipping cost based on both the products’ cart weight and the customer’s shipping address
* has no limits of calculations, it doesn’t matter how much you sell, **it’s free**!
* automatically detects and displays only UPS services available for the  customer’s address
* shows only services you allow - you can turn off the services you don’t want to use in your WooCommerce store
* lets you add insurance to shipping cost
* gives you the option to enable negotiated rates


= Debug mode =

Calculations don’t work properly? Just enable the debug mode to see error messages from API, right in the checkout or on the order summary page.

= Customization =

You can set weight and size units and any custom origin address you need. Also, this plugin lets you set fallback cost in case API doesn’t respond. If you don’t set the **fallback cost** and API doesn’t respond, then in such case UPS is not shown in the cart.

= WooCommerce Compatibility =

**WooCommerce 3.4 ready!** Flexible Shipping UPS plugin is compatible with WooCommerce 3.0.0 - 3.4.x.

= Docs =

[View Flexible Shipping UPS WooCommerce Docs](https://www.wpdesk.net/docs/flexible-shipping-ups-woocommerce/)

= Support Policy =

We provide a limited support for the free version in the [plugin Support Forum](https://wordpress.org/support/plugin/flexible-shipping-ups/).

> **Get more WooCommerce plugins from WP Desk**<br />
> We provide premium plugins for customizing checkout, shipping, invoicing and more. Check out our [premium WooCommerce plugins here →](https://www.wpdesk.net/products/)

== Installation	 ==

You can install this plugin like any other WordPress plugin.

1. Download and unzip the latest release zip file.
2. Upload the entire plugin directory to your /wp-content/plugins/ directory.
3. Activate the plugin through the Plugins menu in WordPress Administration.

You can also use WordPress uploader to upload plugin zip file in menu Plugins -> Add New -> Upload Plugin. Then go directly to point 3.

== Frequently Asked Questions ==

= What currencies does the plugin support? =

UPS WooCommerce plugin supports every currency which UPS supports. You can use currency switchers with no worries, they work well. If UPS doesn’t support a given currency, the UPS WooCommerce plugin won’t show it in the checkout.

= Do I need a UPS account? =
Yes. UPS WooCommerce uses your account to make a connection to the UPS API. This plugin shows you API Status in the settings section. You can sign up with UPS quickly and easily [on their site](https://www.ups.com/doapp/SignUp).

= How can I configure UPS services? =

There is an option to enable services custom settings. You can set which services are available for your customers. However, UPS WooCommerce shows only services available for a given customer based on their shipping address.

= What is a fallback cost? =

Sometimes API doesn’t respond or return an error. The UPS shipping method is not shown in the checkout by default in such situations. However, you can set the fallback cost. If it is enabled, then in case of any API errors, UPS is shown in the checkout with the fallback cost you set.

== Screenshots ==

1. UPS plugin settings.
2. Adding a new shipping method in WooCommerce.
3. UPS shipping method on the list.
4. UPS shipping method settings.
5. Services custom settings.
6. UPS shipping method in the checkout.
7. UPS Access Points

== Changelog ==

= 1.4.1 - 2019-03-11 =
* Fixed selected access point saved in order shipping meta data
* Fixed quick link to docs on plugins page

= 1.4.0 - 2019-02-27 =
* Added pickup type parameter

= 1.3.3 - 2019-01-17 =
* Updated library

= 1.3.2 - 2018-12-19 =
* Fixed Drag and Drop icon in custom services

= 1.3.1 - 2018-11-13 =
* Fixed missing tracker data

= 1.3.0 - 2018-11-13 =
* Fixed libraries compatibility problem

= 1.2.1 - 2018-08-20 =
* Added informations about new services

= 1.2 - 2018-08-20 =
* Added ability to show only Access Points Rates
* Fixed custom origin country when country is with state
* Fixed default units (metric/imperial)

= 1.1.3 - 2018-08-06 =
* Added enable custom services by default
* Tweaked display access point fallback rate only when no standard rates added
* Fixed fatal error on countries select box

= 1.1.2 - 2018-06-26 =
* Fixed error with conflict in tracker

= 1.1.1 - 2018-06-25 =
* Tweaked plugin description
* Tweaked tracker to Access Points
* Tweaked tracker data anonymization
* Fixed issue with select2 for Access Points 
* Fixed tracker notice

= 1.1 - 2018-05-23 =
* Added functionality for UPS Access Points
* Added support for WooCommerce 3.4

= 1.0.3 - 2018-05-09 =
* Fixed missing state for negotiated rates

= 1.0.2 - 2018-03-06 =
* Fixed problems with deactivation plugin on multisite

= 1.0.1 - 2018-02-27 =
* Fixed warnings from WP Desk Tracker

= 1.0 - 2018-02-08 =
* First release!
