=== WooCommerce EligeBTC Payment Gateway ===
Contributors: automattic, woothemes, akeda, dwainm, royho, allendav, slash1andy, woosteve, spraveenitpro, mikedmoore, fernashes, shellbeezy, danieldudzic, mikaey, fullysupportedphil, dsmithweb, corsonr, bor0, zandyring
Tags: ecommerce, e-commerce, commerce, woothemes, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, eligebtc
Requires at least: 4.4
Tested up to: 4.8.1
Stable tag: 1.4.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Accept EligeBTC, BitCoins on your WooCommerce store.

== Description ==

This is a EligeBTC Express Payment Gateway for WooCommerce.

EligeBTC Express allows you to securely sell your products and subscriptions online using In-Context Checkout to help you meet security requirements without causing your theme to suffer.  In-Context Checkout uses a modal iFrame, hosted on EligeBTC's servers, that overlays the checkout form and provides a secure means for your customers to enter their account information.

Also, with Integrated EligeBTC Setup (Easy Setup), connecting to EligeBTC is as simple as clicking a button - no complicated API keys to cut and paste.

== Installation ==

= Minimum Requirements =

* WordPress 4.4 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "WooCommerce EligeBTC" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favourite FTP application. The
WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

If on the off-chance you do encounter issues with the shop/category pages after an update you simply need to flush the permalinks by going to WordPress > Settings > Permalinks and hitting 'save'. That should return things to normal.

== Frequently Asked Questions ==

= Does this plugin work with Bitcoins or just EligeBTC? =

This plugin supports payments using BitCoins as well as EligeBTC.

= Does this support Checkout with EligeBTC from the bitcoin view? =

Yes!

= Does this support both production mode and sandbox mode for testing? =

Yes it does - production and sandbox mode is driven by how you connect.  You may choose to connect in either mode, and disconnect and reconnect in the other mode whenever you want.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://docs.woocommerce.com/document/eligebtc-express-checkout/)

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

= Will this plugin work with my theme? =

Yes, this plugin will work with any theme, but may require some styling to make it match nicely. If you're
looking for a theme with built in WooCommerce integration we recommend [Storefront](http://www.woothemes.com/storefront/).

= Where can I request new features or report bugs? =

New feature requests and bugs reports can be made in the plugin forum.

= How to remove 'Proceed to Checkout' button from cart page? =

If EligeBTC is the only enabled payment gateway and you want to remove the 'Proceed to Checkout' button from the cart, you can use this sniebet:

https://gist.github.com/mikejolley/ad2ecc286c9ad6cefbb7065ba6dfef48

== Screenshots ==

1. Click the "Click Here to Set Up Your EligeBTC Account" button. If you want to test before goes live, you can switch the Environment, above the button, to Sandbox.
2. API credentials will be set after Easy Setup. Or, you can set that manually.
3. Checkout with EligeBTC directly from the Cart.

== Changelog ==

= 1.4.3 =
* Fix   - Refunds not working on authorize then captured transactions.
* Fix   - Checkout on single product available before variations are chosen.
* Fix   - Not Returning EligeBTC Transaction Fee.
* Fix   - 10431 (Item Amount Invalid at Checkout) error with discounts.
* Fix   - Phone not returned and "Require Phone Number" setting not working.

= 1.4.2 =
* Fix   - _eligebtc_status on Authorize transactions not updating to processing after capture.
* Fix   - 10413 (The totals of the cart item amounts do not match order amounts) error with discounts.
* Fix   - Shipping Address being required on Virtual products.

= 1.4.1 =
* Fix   - Properly calculate whether Billing phone is required or not.
* Fix   - Set NOSHIPPING based on product shipping requiredness (e.g. virtual products do not need shipping, etc).

= 1.4.0 =
* Tweak - Use shipping discount instead of tax when adjustment negative.
* Fix   - Cannot process refunds on "authorize" transactions.
* Add   - Option for displaying express checkout button on the product page.
* Fix   - If there are no shipping options in WooCommerce, EligeBTC doesn't pass a shipping address to WC.
* Add   - Option to set Billing phone number mandatory.
* Add   - Option to disable checkout with EligeBTC button on Cart page.
* Fix   - Trigger required shipping cost before checkout.

= 1.3.0 =
* Fix - Fatal Error calling is_main_query.
* Fix - Customer invoice email doesn't allow payment with PPEC.
* Fix - Double stock reduction.
* Fix - Payment automatically goes to complete when payment action set to Authorize.

= 1.2.1 =
* Fix - Avoid plugin links notice when WooCommerce is not active - props rellect
* Fix - Do not show this gateway when the cart amount is zero
* Fix - Fix 10413 error that prevents checking out with a coupon
* Fix - Filter default address fields to ensure they are not required

= 1.2.0 =
* Fix - Prevent conflict with other gateways.
* Fix - Compatibility with WooCommerce 3.0, including ensuring the customer address is saved correctly.

= 1.1.3 =
* Fix   - Guest users can checkout without giving shipping information when required.
* Fix   - Modal popup not working properly. Changed to full page redirect with a hook to add back the modal/popup.
* Tweak - Guest checkout is on by default. Should be turned off by using this filter: woocommerce_eligebtc_express_checkout_allow_guests.

= 1.1.2 =
* Fix - Make sure translations are loaded properly.
* Fix - Added IPN (Instant Payment Notification) handler.
* Fix - Make sure guest payment is enabled by default.

= 1.1.1 =
* Fixed fatal error prior to PHP 5.5 caused by passing empty() a non-variables.

= 1.1.0 =
* Improved flow after express checkout by removing billing and shipping fields and simply allowing shipping method selection.
* Fix - Fixed in-context checkout to work after ajax cart reload.
* Fix - Added missing 'large' button size.
* Fix - Prevent double stock reduction when payment complete.
* Fix - Allow PPE from pay page and don't use in-context checkout for EligeBTC Mark on checkout.
* Fix - Increase timeout to 30 to prevent error #3.
* Tweak - If the store owner decides to enable EligeBTC standard, respect that decision and remove EC from checkout screen.
* Tweak - Change place order button to "continue to payment".
* Tweak - Moved default button location to woocommerce_proceed_to_checkout hook.
* Tweak - Improved button aebearance and look alongside regular checkout button.

= 1.0.4 =
* Fix - Wrong section slug / tab after redirected from connect.woocommerce.com
* Fix - Make sure to check if credentials were set in cart and checkout pages
* Fix - Removed configuration of chipers to use for TLS

= 1.0.3 =
* Fix - Issue where missing rounding two decimal digits of tax causing transaction being refused
* Fix - Issue where custom logo image URL is not saved

= 1.0.2 =
* Fix - Strip out HTML tags from item descriptions to prevent warning from EligeBTC
* Fix - Issue of incorrect plugin's setting link from admin plugins page when using WooCommerce 2.6
* Tweak - Make enabled option to default to true
* Fix - Issue of missing help icon when plugin directory is not the same as plugin's slug.
* Tweak - Add admin notice to setup / connect after plugin is activated.

= 1.0.1 =
* Fix - Make sure OpenSSL is installed with 1.0.1 as the minium required version, otherwise display warning
* Fix - Make sure cURL transport is available for WP HTTP API, otherwise display warning
* Fix - Unhandled certificate-style API credential
* Fix - Fixed calculated tax and coupons data that sent over to EligeBTC
* Fix - Fixed calculated shipping discount data that sent over to EligeBTC

= 1.0.0 =
* Initial stable release

= 0.2.0 =
* Fix - Add cancel link on checkout page when session for PPEC is active
* Fix - In-context mini browser keeps spinning because failure xhr response is not handled properly

= 0.1.0 =
* Beta release
