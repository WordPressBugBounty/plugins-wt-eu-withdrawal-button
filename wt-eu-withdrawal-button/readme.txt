=== WebToffee EU Order Withdrawal Button for WooCommerce ===
Contributors: webtoffee
Tags: woocommerce, EU withdrawal, right of withdrawal, EU compliance, EU directive
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 11.1
Stable tag: 1.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html


Add a legally compliant EU withdrawal button to your WooCommerce store. It allows customers to exercise their 14-day right of withdrawal online.

== Description ==

From June 19, 2026, EU Directive 2023/2673 requires every online store selling to EU consumers to provide a clear, easily accessible online withdrawal function. Customers must be able to cancel a purchase digitally — just as easily as they placed the order.

**EU Withdrawal Button for WooCommerce** by WebToffee makes it straightforward to comply. The plugin adds a withdrawal button to the customer's My Account orders page, a guided withdrawal form, a two-step confirmation flow, and a dedicated admin dashboard to manage all incoming requests.

No coding required. Works right out of the box.

= What the plugin does =

**For your customers:**

* A "Request Withdrawal" button appears directly on the My Account orders list — visible only during the active withdrawal period
* Customers can select which items to withdraw (full order or individual products)
* A simple form collects their name, email, and an optional reason
* A confirmation step prevents accidental submissions ("Are you sure you want to submit this withdrawal request?")
* Customers receive an email confirmation once the request is submitted

**For you as the store admin:**

* A clean Withdrawals dashboard under WooCommerce > Withdrawals shows all requests at a glance
* At-a-glance stats: open requests and approvals over the last 30 days
* Filter requests by status, date range, or search (order # or receipt hash)
* View full request details — order info, customer email, request type, reason, and a complete activity log
* Approve or reject requests with one click
* Configure who receives admin withdrawal notification emails — defaults to your store email, with support for multiple recipients

= Key features =
 
* **Withdrawal button on the orders page** — visible to customers within the configured withdrawal window
* **Partial withdrawal support** — customers can withdraw individual items instead of the entire order
* **Two-step confirmation flow** — meets the directive's requirement to confirm the customer's intent
* **Dedicated admin dashboard** — manage, filter, approve, or reject all withdrawal requests in one place
* **Add footer link** — optionally embed a withdrawal link in your store footer to meet visibility requirements
* **Guest withdrawal support** — guest users can submit their order number and billing email, which is matched against WooCommerce order history, then confirmed via an email verification step before the request is created
* **Withdrawal button shortcode** — use `[wt_eu_order_withdrawal]` to place a withdrawal button on any page or post.
* **Configurable withdrawal period** — set the window to 2, 7, 14, or 30 days (EU standard is 14 days), starting from order created or a chosen WooCommerce order status
* **Product exclusions** — exclude by product type, entire categories, or specific individual products
* **Admin notification recipients** — choose one or more email addresses to receive new withdrawal request alerts (store email added by default)
* **Customer contact email** — set the address shown to customers in rejection emails so they know who to reach out to
* **SHA-256 receipt hash** — every withdrawal request gets a unique SHA-256 receipt hash, included in withdrawal notification emails and shown in the request details on the admin dashboard, for easy reference and record-keeping
* **Order status automation** — automatically update order status when a request is submitted or approved
* **Reason field** — optionally require customers to provide a reason when submitting a request
* **Display scope control** — show the withdrawal form globally or limit it to WooCommerce pages only
* **Data management** — optionally delete all plugin data on uninstall

= Why this matters =

Under [EU Directive 2023/2673](https://eur-lex.europa.eu/eli/dir/2023/2673/oj/eng), any business that lets EU consumers sign up for or purchase something online must also let them cancel online — through the same website or app. Simply hiding a withdrawal form in a footer PDF or asking customers to email you is no longer sufficient.

The directive specifically requires:

* A withdrawal function that is **prominently displayed** and **continuously available** during the withdrawal period
* The function must be **clearly labelled** (e.g. "Withdraw from contract here" or similar)
* A **two-step confirmation** to prevent accidental withdrawals
* An **acknowledgement of receipt** sent to the customer on a durable medium (e.g. email)

This plugin implements all of the above.

= WebToffee EU Order Withdrawal Plugin for WooCommerce - Premium Features =
 
The free version covers the core legal requirements. The premium version adds automation, targeting, and customization options for stores that want to handle withdrawal requests with less manual effort:
 
* **Automatic refund processing**: Auto-approve eligible withdrawal requests, issue the WooCommerce refund, and restock inventory without opening the order manually
* **EU geo-targeting**: Show the withdrawal button only to shoppers in EU countries, detected via billing address for logged-in customers and WooCommerce geolocation for guests
* **Withdrawal Terms & Conditions page generator**: Auto-generate a withdrawal policy page built on EU consumer rights requirements, with shortcodes that stay in sync with your exclusion settings
* **WooCommerce Subscriptions support**: Enable order withdrawal for subscription orders and allow customers to request withdrawal on a subscription within the active window
* **Button style customization**: Set separate button text for footer and My Account placements, choose a plain text link or full button, and customize colors to match your theme
* **Popup modal or dedicated page display** Let the withdrawal form open in an accessible popup modal instead of sending customers to a separate page
* **Google reCAPTCHA v3 support**: Filter spam submissions on the public withdrawal form without adding friction for real customers
* **Refund details collection for offline payments**: Add a bank account/IBAN field to the withdrawal form for orders paid via cash on delivery, bank transfer, or cheque
* **Settings export/import**: Move your full plugin configuration between sites as a JSON file, useful when rolling out the same setup across staging, live, or multiple stores
 
[Get the premium version](https://www.webtoffee.com/product/eu-withdrawal-button/)

= Compatible Plugins =
 
* Sequential Order Number plugins
* Product Bundles by WooCommerce
* WebToffee eCommerce Marketing Automation
* WPML

= Getting started =

For a complete walkthrough, see the [setup guide](https://www.webtoffee.com/docs/woocommerce-eu-withdrawal/how-to-setup-woocommerce-order-withdrawal-button-to-comply-with-eu-directives/) or watch the video below.
[youtube https://www.youtube.com/watch?v=hXjS5bEtIJY]

1. Install and activate the plugin
2. Go to **WooCommerce > Withdrawals > Settings** to configure your preferences
3. Set your withdrawal page, period, and footer link options
4. Publish the withdrawal page (created automatically as a draft on installation)
5. Test the flow by placing a test order and using the Request Withdrawal button

= Support =

For questions or assistance, visit our [support page](https://wordpress.org/support/plugin/wt-eu-withdrawal-button/).

== Installation ==

= Automatic Installation (Recommended) =

1. Log in to your WordPress admin
2. Go to **Plugins > Add New**
3. Search for **EU Withdrawal Button for WooCommerce**
4. Click **Install Now**, then **Activate**
5. Navigate to **WooCommerce > Withdrawals > Settings** to configure the plugin

= Manual Installation =

1. Download the plugin zip file
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin
5. Navigate to **WooCommerce > Withdrawals > Settings** to get started

== Frequently Asked Questions ==
 
= Is this plugin required for all WooCommerce stores? =
 
If your store sells to consumers in the EU, yes — from June 19, 2026, EU Directive 2023/2673 requires an accessible online withdrawal function. This applies regardless of where your business is based.
 
= What is the standard withdrawal period? =
 
The EU standard is 14 calendar days from the date of purchase (or from when the customer receives the pre-contractual information, if later). You can configure the withdrawal period in the plugin settings.
 
= Can customers withdraw only part of an order? =
 
Yes. When partial withdrawals are enabled in settings, customers can select individual items from an order to withdraw rather than cancelling the entire order.
 
= Does the plugin work for guest customers? =
 
The withdrawal button on the My Account page is visible to logged-in customers. For guest order support, you can direct customers to the standalone withdrawal page using the footer link or a shortcode.
 
= Where does the withdrawal button appear? =
 
The "Request Withdrawal" button appears on the customer's My Account > Orders page, next to eligible orders within the active withdrawal window.
 
= How do I add the withdrawal link to my store footer? =
 
Go to **WooCommerce > Withdrawals > Settings** and enable the **Embed footer link** option. You can customise the link text (default: "Request Withdrawal").
 
= Can I exclude certain products from withdrawal? =
 
Yes. Under **Exclusions** in **WooCommerce > Withdrawals > Settings**, you can limit withdrawal eligibility in three ways:
 
* **Product types** — exclude Virtual, Downloadable, Grouped, and External/Affiliate products.
* **Product categories** — exclude one or more WooCommerce categories so every product in those categories is ineligible.
* **Specific products** — search and select individual products to exclude, regardless of type or category.
 
= Does the plugin send a confirmation email to customers? =
 
Yes. Customers receive an email confirmation once their withdrawal request is submitted. This satisfies the directive's requirement to acknowledge receipt on a durable medium.
 
= Who receives the admin notification when a withdrawal request is submitted? =
 
By default, the store email address receives the admin notification. You can change this under **WooCommerce > Withdrawals > Settings > Email notifications**. Add multiple recipients as removable chips, remove the default store email if needed, and add custom addresses with the **Add** button. At least one recipient is required before settings can be saved.
 
= What email address do customers see if their request is rejected? =
 
Set this under **WooCommerce > Withdrawals > Settings > Email notifications > Customer contact email**. This is the address shown to customers in the rejection email so they know where to follow up. It defaults to your WooCommerce store email but can be changed to any address.
 
= What happens to the data if I uninstall the plugin? =
 
By default, plugin data is retained when you uninstall. If you want all data (settings, withdrawal requests, database tables) removed on uninstall, enable the **Delete data on uninstall** option in settings. Note: this action is irreversible.
 
= Does this plugin guarantee legal compliance? =
 
The plugin implements the technical requirements of EU Directive 2023/2673 as understood at the time of release. However, it does not constitute legal advice. We recommend consulting a legal professional to ensure your store meets all applicable requirements in your jurisdiction.
 
= Can I show the footer link or withdrawal button only for certain countries? =
 
Yes. Developers can use WordPress filters to control visibility based on the visitor's country. The plugin resolves the country from the order (when available), the WooCommerce customer session, or IP geolocation.
 
Available filters:
 
* `wbte_ewb_customer_country_code` — override the detected ISO country code.
* `wbte_ewb_show_footer_withdrawal_link` — show or hide the automatic footer withdrawal link.
* `wbte_ewb_show_withdrawal_button_shortcode` — show or hide the `[wt_eu_order_withdrawal]` shortcode output.


== Screenshots ==
1. Withdrawal request form
2. Withdrawal request details
3. Admin withdrawals dashboard
4. EU Withdrawal Button plugin general settings
5. Product Exclusions
6. Order Status and Email Notifications
7. EU Withdrawal advanced settings


== Changelog ==

= 1.1.0 15-09-2026 =

* [Compatibility]: Tested up to Plugin checker 2.1.0
* [Compatibility]: Tested up to WooCommerce 11.1.0

= 1.0.9 11-08-2026 =

* [Add]: Portuguese (pt_PT) and Czech (cs_CZ) translation files.
* [Add]: Bundle product compatibility for withdrawal requests.
* [Add]: WCAG accessibility compatibility.
* [Add]: Withdrawal ID based searching in the admin dashboard.
* [Improvement]: Guest order withdrawal workflow.
* [Fix]: Showing wrong line total in withdrawal requests.
* [Compatibility]: Tested up to WooCommerce 11.0.0
* [Compatibility]: Tested up to WordPress 7.1

= 1.0.8 23-07-2026 =

* [Fix]: Corrected translation issues in withdrawal emails sent to guest users.
* [Fix]: Resolved inconsistency in the email template override path.
* [Tweak]: Refined the admin screen interface.
* [Compatibility]: WPML Multilingual Plugin.
* [Compatibility]: Tested up to WooCommerce 10.9.4

= 1.0.7 13-07-2026 =

* [Fix]: Footer withdrawal link now inherits the theme's default link color instead of a hardcoded gray.
* [Fix]: Withdrawal timestamps in customer emails, admin dashboard, and My Account now display in the WordPress site timezone with an explicit UTC offset.
* [Add]: My Account order view lists all withdrawal requests (including rejected) in a timeline layout with submitted/processed dates and rejection reason.
* [Add]: Swedish (sv_SE), Belgian Dutch (nl_BE) and Greek (el) translation files.
* [Compatibility]: Tested up to WooCommerce 10.9.3

= 1.0.6 02-07-2026 =

* [Fix]: Email preview fatal error on WooCommerce versions before 9.9.0 when viewing withdrawal email templates in WooCommerce settings.
* [Add]: Bulgarian (bg_BG) and Polish (pl_PL) translation files.
* [Compatibility]: Tested up to WooCommerce 10.9.1

= 1.0.5 25-06-2026 =

* [Add]: Two-step withdrawal confirmation modal with a read-only request review and an explicit, translatable "Confirm withdrawal" button — replaces the native browser confirm dialog for clearer EU withdrawal labelling.
* [Add]: `wbte_ewb_show_my_account_order_withdrawal_link` filter to show or hide the withdrawal action on My Account order rows.
* [Add]: Admin review banner on the withdrawals dashboard and order edit screen.
* [Fix]: Partial withdrawal requests now show the correct type and item quantity in the admin dashboard and order meta box, including when multiple requests exist on the same order.
* [Fix]: Orders remain eligible for further partial withdrawals after a partial approval when quantity is still available.

= 1.0.4 24-06-2026 =

* [Add]: Setting to choose one or more WooCommerce order statuses that start the withdrawal period countdown (default: order created).
* [Add]: SHA-256 verification code on withdrawal requests that reflects request data and is included in customer/admin emails and the admin dashboard.
* [Add]: Customer contact email setting for rejected withdrawal emails — defaults to the WooCommerce store email.
* [Add]: Filters to control footer link and `[wt_eu_order_withdrawal]` shortcode visibility based on customer country.
* [Fix]: Rejected withdrawal customer email now uses the configured customer contact email instead of the WordPress admin email.
* [Fix]: Server-side validation now rejects ineligible line items and limits full withdrawals to eligible remaining quantities only.
* [Fix]: Guest eligible-items lookup now requires the submitted order number to match the requested order, not just the billing email.
* [Fix]: Withdrawal page links (My Account order actions, shortcode, footer link, and guest verification emails) now resolve the translated page URL via WPML when a withdrawal page translation exists.

= 1.0.3 22-06-2026 =

* [Add]: Approved and rejected customer emails now include Date Submitted and Date Processed timestamps (date and time).
* [Add]: Dutch (nl_NL), Danish (da_DK), German (de_DE), Italian (it_IT), and Romanian (ro_RO) translation files.
* [Add]: Partial withdrawal quantity selection and support for multiple partial requests on the same order.
* [Fix]: Withdrawal requests for trashed orders are hidden from the admin dashboard and statistics.
* [Fix]: Withdrawal requests, activity logs, and pending guest verifications are permanently deleted when the linked order is permanently deleted.
* [Fix]: Withdrawal form broken under jQuery 4.x — submitting the form threw a JavaScript error.
* [Fix]: Withdrawal acknowledgement emails now display the full date and time of submission, meeting EU Directive 2023/2673 / art. 54-bis Codice del Consumo requirements for recording the exact moment of the withdrawal declaration.

= 1.0.2 17-06-2026 =

* [Add]: Option to configure multiple recipients for admin notifications related to withdrawal requests.
* [Add]: Product category and specific product exclusion options in the Withdrawals settings dashboard.
* [Fix]: Error in the email preview section for withdrawal-related settings.

= 1.0.1 15-06-2026 =

* [Add]: `[wt_eu_order_withdrawal]` shortcode to place the withdrawal request button anywhere on your site.
* [Add]: WPML and multilingual support for configurable button labels.
* [Add]: Support and Review links to the plugin row on the Plugins screen.
* [Add]: Uninstall feedback modal when deactivating the plugin.
* [Add]: Guest email verification workflow — simplified guest form (order number, billing email, and reason), verification email notification, duplicate request check, and queueing before a withdrawal request is created.
* [Fix]: Logged-in withdrawal form resetting item selection when clicking other form fields.
* [Fix]: WooCommerce order status emails no longer sent when restoring order status after rejection; orders with pending or approved withdrawal requests no longer appear in the logged-in withdrawal form.
* [Update]: Rejection workflow — required note on the WooCommerce order screen, rejection reason shown in the customer email Request Details table, and note included when rejecting from the dashboard or order screen.

= 1.0.0 =

* [Add]: Initial release.
* [Add]: Customer withdrawal form for logged-in and guest customers.
* [Add]: React admin dashboard for request management.
* [Add]: Custom order statuses — Pending Withdrawal and Withdrawn.
* [Add]: WooCommerce Settings tab integration.
* [Add]: Product type based exclusions.
* [Add]: Configurable withdrawal period.
* [Add]: WooCommerce email notifications.
* [Add]: HPOS compatibility.
* [Add]: REST API.
* [Add]: My Account integration.

== Upgrade Notice ==

= 1.1.0 =

* [Compatibility]: Tested up to Plugin checker 2.1.0
* [Compatibility]: Tested up to WooCommerce 11.1.0