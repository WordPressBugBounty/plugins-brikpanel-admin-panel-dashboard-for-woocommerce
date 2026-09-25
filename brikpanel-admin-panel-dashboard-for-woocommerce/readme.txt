=== BrikPanel: WooCommerce Dashboard, Abandoned Cart Recovery, Google Sheets Sync, Inventory Management & Bulk Editor ===
Contributors: brksoft
Donate link: https://donate.stripe.com/14AdR9ghJcxKaAqdzbc3m00
Tags: woocommerce dashboard, woocommerce inventory management, google sheets, woocommerce bulk editor, roas
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 3.3.23
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Free WooCommerce dashboard & sales report: abandoned cart recovery, Google Sheets sync, ROAS, bulk editor & inventory management

== Description ==

**Live demo (no install needed):** [Explore the full BrikPanel admin on a real WooCommerce store](https://code.brksoft.com/wp-admin/)

https://www.youtube.com/watch?v=pmtmVQifZME&t

**BrikPanel turns the default WooCommerce admin panel into a clean, fast, all-in-one cockpit**: a modern WooCommerce dashboard, a real-time WooCommerce sales report, a powerful WooCommerce bulk editor, an inventory management workspace, an order management center, a coupon manager, a custom WP login page, and a real-time conversion tracking suite. Everything is free. Forever. No premium tier, no feature locks, no monthly subscriptions. A self-hosted **Shopify alternative for WooCommerce**: own your data, your products, and your customer list, with no monthly platform fee and no transaction fee.

= Who is BrikPanel for? =

* Store owners who want a **modern WooCommerce dashboard** with real numbers, not the slow built-in reports, and a **self-hosted WooCommerce analytics** solution instead of paying monthly fees to external SaaS tools
* Stores that want a lighter **woocommerce inventory management** workspace built into a complete admin redesign
* Anyone who needs to **bulk edit WooCommerce products**, including variations, without a premium plugin
* Agencies handing off stores to non-technical clients who need a **simplified WooCommerce admin**
* Shop owners migrating from Shopify who want a familiar, modern admin for their WooCommerce store, a free, self-hosted **Shopify alternative**

== What you get (all free) ==

= Modern WooCommerce Dashboard & Sales Report with Real-Time Analytics =

The heart of BrikPanel is a **modern WooCommerce dashboard**, a true **woocommerce admin panel plugin**, not a styling layer.

* **Total Sales, Total Orders, Average Order Value (AOV)**: today, yesterday, last 7/30 days, or any custom range, with **±% period-over-period delta** on every metric
* **Visitors** counted from your own database (admins excluded), and **Conversion Rate** computed live from real visitors and real orders
* **Beautiful sales chart** powered by Chart.js, plus an **order status donut** (Completed, Processing, Cancelled, Refunded, Failed)
* **WooCommerce conversion funnel**: Visitors → Add to Cart → Checkout → Orders, with the conversion percentage at every step

This is a complete **WooCommerce sales report** and **reporting** layer: real-time **sales reports**, charts and KPIs inside a **modern WooCommerce admin**, with no external analytics service.

= Customer Analytics: LTV, RFM Segmentation & Cohort Retention =

BrikPanel ships a complete **WooCommerce customer analytics** suite, calculated from your store data and visualized in the dashboard, no external service.

* **Customer Lifetime Value (LTV)**: total customers, average and top LTV, full LTV distribution histogram, and a sortable top-customers table
* **RFM segmentation**: every customer scored on Recency, Frequency, and Monetary, then bucketed into Champions, Loyal, At Risk, About to Sleep, Hibernating, and Lost, with revenue per segment
* **Cohort retention**: month-by-month cohort retention grid plus an average retention by month-offset trend line
* **Advanced filtering and segmentation**: combine spend range, product, location, date and more to build saved segments for both customers and orders

= Live Visitors & Real-Time Conversion Tracking =

BrikPanel ships a built-in **WooCommerce live visitors** widget, see who is on your store right now, what page they are on, and whether they have items in the cart. Refreshes every 30 seconds by default (configurable). No external service, no Hotjar, no monthly fee.

* **WooCommerce real time visitors** widget with cart status (*Browsing / Has items in cart / On thank-you page*), current page, and customer info
* **WooCommerce conversion tracking** in the same database that powers the dashboard
* Visitor IPs are never stored, only a salted SHA-256 hash, and live visitor data stays in a short-lived cache, never in the database
* Privacy switches: make tracking wait for cookie consent (WordPress Consent API or your own banner), turn front-end tracking off entirely, or keep it on while excluding logged-in customer details from the Live view
* Most-viewed pages and most added-to-cart products reports

A free **woocommerce statistics plugin** and **woocommerce sales tracker** without any external SaaS.

= Geographic Analytics: WooCommerce Sales by Country =

A 3D rotating globe (Cobe.js) plots every order on its real location, see **WooCommerce sales by country** and city without exporting a CSV, with **Top 10 Countries** and **Top 10 Cities** tables. Works with both HPOS and legacy order storage.

= Lightning-Fast Order Search: Cmd/Ctrl + K from Anywhere =

Hit `Ctrl + K` (or `Cmd + K` on Mac) anywhere in wp-admin and an order search overlay opens, the free **woocommerce order search plugin**. Searches order ID, customer name, email, phone and product SKU inside line items at once. True **woocommerce quick search**, with results as you type, status badges, totals and dates.

= Modern WooCommerce Order Management =

BrikPanel replaces the cluttered default orders page with a clean **woocommerce order list plugin** screen.

* **30-day overview bar**: total orders, completed, refunded, cancelled, revenue
* **Inline status change** without opening the edit page
* HPOS (`wc_get_orders`) and legacy storage (`WP_Query`) both supported
* Two new statuses: **Return Draft** and **Change**
* Reskinned order edit page with copy-to-clipboard for billing/shipping
* **Sold downloadable products column** on the order edit page
* Optional BrikMarket marketplace stats integration

A real **woocommerce order management plugin**, not a reskin. Disable from settings anytime.

= WooCommerce Product List Plugin: Built for People Who Actually Edit Products =

The default **WooCommerce product list** is fine for browsing, painful for editing. BrikPanel ships a complete **woocommerce product list plugin** that fixes it.

* Thumbnail, name, SKU, regular/sale price, stock badge, category
* **Publish status toggle**: flip draft ↔ published with one click, no reload
* Edit, Duplicate, Delete actions; bulk publish, draft, delete
* Status tabs (All / Published / Draft / Trash), live search by name or SKU
* Configurable per-page (5–100, default 20), AJAX pagination
* **Per-user toggles for any third-party / SEO column** added by Yoast, Rank Math, ASE and other plugins
* **Admin and Site Enhancements (ASE) custom columns** are respected in the BrikPanel product, order and customer lists

= Quick Edit Sidebar: Edit Without Leaving the List =

A slide-in panel from any product row to edit name, SKU, regular/sale price, stock and category, saved without leaving the list. The **woocommerce quick edit** WooCommerce should have shipped years ago: update **woocommerce quick edit price**, stock or category in two clicks.

= Bulk Edit WooCommerce Products with the Variation Editor: Full Variation Support =

This is where BrikPanel pulls ahead of every other free **woocommerce bulk editor**. Most free plugins only handle simple products and only "increase price by X%". BrikPanel does far more, on variable products too.

* **WooCommerce bulk price update** (regular and sale): percentage, fixed amount, or absolute value, across the whole catalog or filtered by category
* **Bulk update WooCommerce products** stock quantities (in/out of stock, set quantity, add/subtract)
* **WooCommerce bulk price by category**: pick a category, set a rule, every product updates
* **WooCommerce bulk sale price** updates with a date range
* Confirmation dialog on every bulk action

Now the part nobody else does for free: **variation support**.

* **WooCommerce variation editor**: open any variable product and edit every variation in one modal (regular price, sale price, stock, SKU)
* **Bulk edit variation prices WooCommerce**: set the same price for all variations of an attribute (every "Red" variation, every "L" size), or apply a percentage rule
* **Bulk update variation stock**: set or adjust the stock of every variation in one click
* Attribute filter to narrow visible variations when a product has 50+ combinations

**How to bulk edit WooCommerce products** including variations without buying a $79/year plugin? BrikPanel handles both simple and variable products for free.

= Simplified WooCommerce Product Editor =

The default WooCommerce add-product screen has 11 metaboxes, 3 tabs and 40+ fields. BrikPanel ships a complete **woocommerce product editor plugin** with the noise removed.

* **Featured image + product gallery** with drag-and-drop upload, unlimited images, drag-to-reorder
* Regular price, sale price with decimal validation
* **Searchable category picker** with multi-select + **quick create category** without leaving the page
* **Brand field**: the WooCommerce `product_brand` taxonomy is now first-class alongside categories and tags
* Short description + full rich-text description (wp_editor)
* **SEO fields**: custom slug, meta title, meta description, live Google SERP preview
* **Full SEO plugin compatibility**: Yoast SEO, Rank Math, All in One SEO and SEOPress metaboxes (including the SEO score panel) render and save inside the BrikPanel product editor
* Product type (Simple, Variable), **attribute management** with inline create
* **Auto-generate variations** from attribute combinations, per-variation price/sale/SKU/stock
* Duplicate any product in one click

Opt-in. Keep the default WooCommerce product page if you prefer.

= WooCommerce Variation Gallery =

Attach a separate image gallery to each product variation, the frontend swaps gallery automatically when a customer picks a variation. Image metadata (srcset, sizes, alt text) is fully preserved.

= WooCommerce Categories Page: Drag-and-Drop Parent/Child Management =

BrikPanel rebuilds the dated WooCommerce category screen with per-page settings (5–200) and **drag-and-drop parent/child nesting** with circular reference prevention, for both `product_cat` and `product_tag`.

= Best WooCommerce Coupon Plugin: Free Coupon Manager =

A complete **WooCommerce coupon manager** that makes coupons first-class in the admin, and we think the **best WooCommerce coupon plugin** in the free repository.

* Coupon table with code copy-to-clipboard, discount type icon, amount, usage count, expiry highlighting, and status
* Status tabs, AJAX pagination, **slide-over coupon panel**: create/edit without a reload
* Auto-generate random coupon codes; one-click duplicate
* Discount types: percentage, fixed cart, fixed product + free shipping toggle
* Expiry date picker, total + per-customer usage limits, min/max spend, individual use toggle, product/category include/exclude rules

= WooCommerce Cart Abandonment & Cart Recovery =

A built-in **WooCommerce cart abandonment** and **cart recovery** system, with no external email SaaS. A dedicated **Abandoned Carts** screen captures the checkout email of shoppers who do not finish (classic and block checkout, plus logged-in add-to-cart) and snapshots each cart down to the exact variation. Carts move Active to Abandoned to Recovered automatically, and an optional popup hands each subscriber a single-use **cart recovery coupon**. Search and date filters, plus CSV / Excel export.

= Custom WordPress Login Page: Custom WP Login Page for WooCommerce =

A **custom WP login page** that fully replaces the default `wp-login.php` look, a real **WordPress login customizer** for WooCommerce stores.

* Centered card layout with your site name as logo
* Minimal, distraction-free fields, AJAX submission (no reload)
* Toast notification on errors, footer site branding
* Default WordPress login styles fully hidden

= WooCommerce Inventory Management =

A complete **woocommerce inventory management** workspace: the product list, bulk editor, variation editor and quick edit sidebar work together as one inventory workflow.

* Current stock for every product and variation in one place, with stock badges in the product list (in stock / low stock / out of stock)
* Update stock inline from the quick edit sidebar, or bulk update across categories and variations
* HPOS-enabled stores supported

A free **woocommerce inventory management plugin** that covers the daily workflow, no heavy stock control plugin needed.

= Custom Top Admin Bar & Notifications =

A **Custom BrikPanel-styled top admin bar** replaces the default WordPress toolbar with an e-commerce notification bell and quick links, toggleable from settings. Sound, confetti and a popup the moment a completed order arrives.

= Google Sheets Sync: Real-Time WooCommerce Google Sheets Integration =

BrikPanel ships a free **WooCommerce Google Sheets sync**, a fully native **WooCommerce to Google Sheets** integration that streams orders, customers and analytics into a Google Sheet you control. The free **GSheetConnector alternative** with no Zapier, no Make, no monthly fee.

* **Real-time order sync**: every new WooCommerce order is appended within seconds, one row per line item so variations get their own columns. Free **woocommerce order sync to google sheets** with no external automation tool
* **Scheduled WooCommerce Google Sheets export**: hourly, every 4h or daily catch-up; idempotent so re-runs never duplicate rows
* **Analytics report snapshots**: Sales Summary, Daily KPIs, Top Products and Funnel tabs refreshed on an interval for pivots and dashboards in Sheets
* **Customer + RFM snapshot**: chained to the nightly RFM recompute

HPOS-compatible: a real **google sheets woocommerce sync**, free.

= WooCommerce ROAS, Net Profit & Ad Spend: Google Ads + Meta Ads =

BrikPanel pulls daily spend from **Google Ads** and **Meta Ads** (Facebook / Instagram) so you see real **WooCommerce ROAS**, **Net Profit** and **ad spend** next to revenue. Multi-currency aware. A free **Triple Whale alternative** and **woocommerce profit tracking** dashboard with no monthly fee.

= BrikMarket Marketplace Analytics =

When BrikMarket is active, marketplace orders are excluded from the storefront conversion rate, and a dashboard block breaks down orders, share and top categories per marketplace.

= Subscription & Membership Plugin Compatibility =

Subscription products and member orders (WooCommerce Subscriptions, MemberPress, Paid Memberships Pro and more) show up in the same product list, order screens and customer analytics.

= Developer Hooks & Filters =

A **developer hooks and filters system** for agencies, actions and filters like `brikpanel_after_product_save`, plus a built-in docs popup in settings with one-click copy buttons.

= Navigation & Admin UI Cleanup =

* BrikPanel dashboard becomes the first WordPress admin menu item; admin bar gains quick links, footer rebranded
* Optional **simplified mode** hides the full WordPress menu, showing only BrikPanel + WooCommerce for non-technical clients

== A Free, Self-Hosted WooCommerce Analytics & Inventory Suite ==

Store owners pay monthly SaaS fees for parts of what BrikPanel does free:

* **Self-hosted WooCommerce analytics**: sales, AOV, conversion, funnels, geo data, customer LTV, RFM, cohort retention, no third-party
* A free Metorik and Triple Whale alternative: analytics, ROAS and profit on your own server
* **Shopify alternative for WooCommerce**: the clean admin experience of Shopify with your storefront, customer data and orders on your own server

== Why BrikPanel and not the default WooCommerce admin? ==

WooCommerce's built-in analytics are slow, refresh hourly, and have no live visitor tracking, conversion funnel, geographic data, customer LTV / RFM / cohort reports, Cmd+K order search, quick edit sidebar, variation bulk editor, custom login or coupon manager. BrikPanel fixes every one of those gaps inside a single **free WooCommerce admin plugin**.

== WooCommerce HPOS Compatibility & Performance ==

* **Light on your storefront**: admin screens load only inside wp-admin; shoppers only get the scripts of the storefront features you keep on
* **Hardened performance for low-resource hosting**: heavy queries are batched, cached and run through Action Scheduler so the dashboard, customer analytics and bulk editor stay responsive on shared hosting
* **HPOS (High-Performance Order Storage)** fully supported with dual code paths
* WooCommerce 7.x, 8.x, and newer; works alongside Admin Menu Editor, Slider Revolution, Yoast SEO, RankMath, WPML, Polylang
* Translation-ready (`.pot` file included), with all JavaScript / jQuery strings routed through `wp_localize_script`
* DB writes use prepared statements; visitor IPs stored only as truncated salted SHA-256 hashes; admin activity excluded from analytics; front-end tracking can be disabled entirely from settings

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/brikpanel`, or install via **Plugins → Add New → Upload Plugin**.
2. Activate through the **Plugins** menu.
3. Open **BrikPanel** in the admin sidebar, the dashboard loads immediately.
4. (Optional) Visit **WooCommerce → Settings → BrikPanel** to enable or disable specific modules.

That is it. No license key, no email signup, no external account.

== Frequently Asked Questions ==

= Is BrikPanel really 100% free? =

Yes. Every feature on this page is in the free version. There is no premium tier, no feature lock and no trial period. We also make a separate paid plugin, BrikMentor, and BrikPanel shows a small notice about it, which you can switch off under WooCommerce → Settings → BrikPanel → General. We built this because we needed it for our own 1000+ WooCommerce stores and decided to release it.

= Does BrikPanel hide WooCommerce's own ads? =

Yes, by default. WooCommerce.com places ads in your admin, like the promo card above the Orders list, the sale badge on the Extensions menu and extension suggestions. BrikPanel turns them off with WooCommerce's own switches. To show them again, untick "Hide WooCommerce ads" under WooCommerce → Settings → BrikPanel → General.

= Is BrikPanel a self-hosted WooCommerce analytics solution? =

Yes. BrikPanel gives you a complete WooCommerce analytics suite that runs entirely on your own server with no external dependencies. Sales analytics, product reports, conversion tracking, customer LTV, RFM segmentation, cohort retention and customer data are all included, nothing is sent to any third-party SaaS.

= Does BrikPanel include a WooCommerce sales report? =

Yes. The BrikPanel dashboard ships a complete **WooCommerce sales report** out of the box, total sales, total orders, average order value (AOV), refunds, and net revenue, each with a ±% period-over-period delta. Filter the sales report by today, yesterday, last 7 days, last 30 days, or any custom date range. The sales chart is rendered with Chart.js and pairs with the order status donut and conversion funnel for a full sales report you can read at a glance, without ever leaving wp-admin and without paying for an external analytics service.

= Does BrikPanel offer custom WooCommerce reports, KPIs and a profit report? =

Yes. The dashboard goes far beyond the built-in screens with a complete set of **WooCommerce reports** and **WooCommerce sales analytics** computed live from your own store data: sales, orders, AOV, conversion rate, customer LTV, RFM segments and cohort retention. Every headline metric is shown as a **WooCommerce KPI** card with a period-over-period delta, and a real **profit report** (revenue minus COGS, ad spend and manual expenses) sits right next to revenue. Because the LTV, RFM, cohort and geographic views are not part of core, BrikPanel effectively ships **advanced reports** for **WooCommerce** and **custom WooCommerce reports** as a free, self-hosted **WooCommerce reporting** layer, with no external SaaS and nothing sent off your server.

= Can I customize the dashboard widgets, sales charts and graphs? =

Yes. The BrikPanel **admin dashboard** is built from modular **dashboard widgets** (sales, orders, AOV, the conversion funnel, live visitors, the geographic globe, customer analytics and more), and the modules you do not need can be turned off from **WooCommerce → Settings → BrikPanel**. The **sales charts** and **sales graphs** are rendered with Chart.js and redraw for any date range you pick, so your **custom dashboard** shows exactly the **sales charts**, KPIs and reports you care about and nothing you do not.

= Does BrikPanel work with multi-currency stores (CURCY, WCML)? =

Yes. When your store takes orders in more than one currency, BrikPanel converts every order to your store's base currency before summing, so Revenue, AOV and the sales chart are never a meaningless mix of currencies. With **CURCY (WooCommerce Multi Currency)** the exact day-of-sale rate is read from the snapshot CURCY stores on each order. With **WCML (WooCommerce Multilingual & Multicurrency)** the current WCML rate is applied and snapshotted onto the order the moment it is placed, which captures the day-of-sale rate for every order going forward. For any other multi-currency setup you can enter flat fallback rates under **WooCommerce → Settings → BrikPanel → Currency**, or supply a rate programmatically through the `brikpanel_order_base_factor` filter (parameters: current factor, `WC_Order`, order currency, base currency — return the multiplier that converts one unit of the order currency into the base currency).

= Where does BrikPanel read Cost of Goods (COGS) from? Can I use my own cost field? =

BrikPanel reads product cost from **WooCommerce's own native Cost of Goods Sold field** (`_cogs_total_value`, WooCommerce 9.5+) — the same field the WooCommerce product screen edits — so any plugin or import pipeline that writes the native cost is picked up automatically, including direct database writes. Costs saved by older BrikPanel versions are migrated into the native field automatically. Variation costs follow WooCommerce's semantics, including the "additive" flag that adds a variation's cost on top of the parent's. If you keep cost somewhere else entirely, hook the `brikpanel_product_cogs` filter (parameters: resolved cost or null, product id, variation id) to point BrikPanel's per-product cost reads at your own source.

= Can I turn off BrikPanel's front-end visitor tracking? =

Yes. If you already run a dedicated analytics tool, disable **Visitor tracking** under **WooCommerce → Settings → BrikPanel → Analytics** and BrikPanel stops adding its tracking script, tracking cookies and tracking requests to your storefront. This switch covers analytics only: the shopper-facing features (checkout email capture, the Share cart button, the variation gallery) have their own switches, listed under "Does BrikPanel slow down my WooCommerce store?". You can also keep tracking on but raise the live-visitor refresh interval to reduce server load, or exclude logged-in customer details from the Live view for a fully anonymous setup. If you only want tracking to wait for cookie consent rather than switching it off, see the next question.

= Does BrikPanel work with a cookie consent banner? (GDPR / consent mode) =

Yes. Tick **Wait for cookie consent** under **WooCommerce → Settings → BrikPanel → Analytics** and BrikPanel's visitor tracking creates no cookie, no browser storage and no request at all until the visitor allows analytics. Consent is accepted from any of three sources, so any consent platform can drive it:

* the **WordPress Consent API** (the `statistics` category), which BrikPanel also listens to for live changes;
* a banner calling **`brikpanel_start_tracking()`** in JavaScript, with **`brikpanel_stop_tracking()`** on withdrawal;
* the **`brikpanel_frontend_tracking_allowed`** PHP filter, for agencies wiring up a CMP without touching plugin files.

Consent takes effect immediately, with no page reload. When it is withdrawn, tracking stops at once and BrikPanel deletes its own cookies and browser storage for that visitor and drops them from the Live view. The anonymous daily totals already recorded are untouched, because they contain no visitor identifier to erase.

Tested against the most-installed consent banners. Working with no setup at all: **Complianz**, **CookieYes**, **GDPR Cookie Compliance (Moove)**, **WPConsent**, **Cookiebot**, **iubenda** and **Beautiful Cookie Consent Banner** — they all speak the WordPress Consent API, so ticking the setting is the only step. **Cookie Notice / Compliance by Hu-manity** needs its Compliance mode connected, because its free unconnected mode never reports a category decision. **CookieAdmin**, **Real Cookie Banner** and **Termly** do not use the WordPress Consent API at all; bridge them with a few lines, using the pattern below (this exact snippet was tested against CookieAdmin, swap the cookie name and button ids for another banner):

`add_filter( 'brikpanel_frontend_tracking_allowed', function ( $allowed ) {`
`    return isset( $_COOKIE['my_banner_cookie'] ) && $_COOKIE['my_banner_cookie'] === 'accepted';`
`} );`

and in your theme's footer, so a click takes effect without a reload:

`document.addEventListener('click', function (e) {`
`    if (e.target.closest('#my-banner-accept') && window.brikpanel_start_tracking) window.brikpanel_start_tracking();`
`    if (e.target.closest('#my-banner-reject') && window.brikpanel_stop_tracking) window.brikpanel_stop_tracking();`
`}, true);`

What visitor tracking stores in the browser, and only after consent when the setting is on: `brikpanel_vid` (a random id, 1 year, so a visit is counted once instead of once per page), `brikpanel_consent` (the value `1`, 30 days, remembering the choice), `brikpanel_add_to_cart_count_cookie` and `brikpanel_checkout_count_cookie` (until midnight, one funnel count per day), and the local storage keys `brikpanel_visitor_viewed_<date>` and `brikpanel_product_viewed_<date>`. All of it is first-party and stays on your own site.

This setting governs analytics. Abandoned-cart email capture is a separate feature with its own switch under **Cart abandonment**. For a guest it saves no cart and sets no cookie until they enter their email address; when they do, it reuses the same `brikpanel_vid` id to tie the cart to that address. A logged-in customer's email is already on their account, so their cart is saved as soon as it has items. The optional signup popup, if you turn it on, only keeps a few small entries in browser storage (that it was closed or used, the coupon it gave, whether the cookie banner was answered), so it does not keep reappearing.

= Does the signup popup appear on top of my cookie banner? =

No. **Wait for cookie banner** under **WooCommerce → Settings → BrikPanel → Cart abandonment** is on by default, and the popup then holds back until the visitor has answered the banner. Accepting and declining both release it, because signing up for an offer is not tracking. On a store with no cookie banner nothing changes at all: the popup opens after its normal delay, exactly as before.

It cannot be lost. Whatever happens with the banner, the popup opens no later than 30 seconds after the page loads, and the small floating tab that holds a visitor's own coupon code is never held back.

Banners that speak the WordPress Consent API (**Complianz**, **CookieYes**, **GDPR Cookie Compliance (Moove)**, **WPConsent**, **Cookiebot**, **iubenda**, **Beautiful Cookie Consent Banner**) need no setup. For a banner that does not, such as **CookieAdmin**, **Real Cookie Banner** or **Termly**, tell the popup when your banner was answered:

`document.addEventListener('click', function (e) {`
`    var answered = e.target.closest('#my-banner-accept') || e.target.closest('#my-banner-reject');`
`    if (answered && window.brikpanel_popup_consent_answered) window.brikpanel_popup_consent_answered();`
`}, true);`

= Does BrikPanel support WordPress multisite? =

Yes, both ways: network-activate it to run on every store in the network, or activate it on individual subsites only. Each site gets its own tables and settings either way. When network-activated, super admins additionally get network-wide access rules under **Network Admin → Settings → BrikPanel Access**.

= Does BrikPanel show customer LTV, RFM segments and cohort retention? =

Yes. BrikPanel ships a full **WooCommerce customer analytics** suite directly in the dashboard. Customer Lifetime Value (LTV) is calculated for every customer with average, top, and full distribution histogram. RFM segmentation scores every customer on Recency, Frequency and Monetary and groups them into Champions, Loyal, At Risk, About to Sleep, Hibernating and Lost. Cohort retention shows a month-by-month grid plus an average retention trend line. All three are computed from your own store data, no external service involved.

= Is BrikPanel a free Shopify alternative for WooCommerce? =

Yes, for store owners who want to stay self-hosted. BrikPanel gives your WooCommerce store the clean, modern admin experience of Shopify: product list with inline editing, bulk price and stock updates, live visitors, conversion tracking, geographic analytics, customer LTV / RFM / cohort reports, a branded login page, but your storefront, your customer data, and your orders stay on your own server. No monthly platform fee, no transaction fee, no vendor lock-in. If you were evaluating Shopify but want to own your stack, this is the **Shopify alternative for WooCommerce** we built for that exact use case.

= Is BrikPanel an ATUM alternative for inventory management? =

For most stores, yes. BrikPanel includes complete **woocommerce inventory management**: stock levels, low stock badges, bulk stock updates, variation stock updates, all integrated into the same dashboard you use for sales and orders. If you only need daily stock work without advanced supplier or purchase order features, BrikPanel is a much lighter **ATUM alternative**.

= How do I get a faster WooCommerce product list with bulk actions and quick edit? =

The default **WooCommerce product list** is built for browsing, searching, sorting and editing it is slow. BrikPanel ships a complete **woocommerce product list plugin** with thumbnail, SKU, regular and sale price, stock badge, category, AJAX pagination, live search, status tabs, one-click publish toggle and a slide-in quick edit panel for every row. Works on both simple and variable products, and the same **woocommerce product list** screen powers the bulk price and bulk stock updates so you never leave the page to edit your catalog.

= Can I search products by my own SKU field, like a supplier or manufacturer code? =

Yes. The product list search matches the product title, the description and the WooCommerce SKU out of the box, including a variation SKU, which returns the parent product. If your warehouse also stamps a supplier code, a manufacturer part number or an EAN onto each product in its own custom field, point BrikPanel at it with the `brikpanel_product_search_meta_keys` filter, which receives the list of meta keys (just `_sku` by default) and the search term. Add your own key to the list and staff can find a product by typing it, on simple and variable products alike, because a match on a variation returns the parent. Up to ten keys are scanned. The filter is documented with a copyable example under WooCommerce, Settings, BrikPanel, Developers.

= How do I bulk edit WooCommerce products including variations? =

Open **BrikPanel → Products** and click the **Bulk Update** button in the toolbar. You can update prices, sale prices, and stock for all products, by category, or for selected products. For variable products, open any product, click **Edit Variations**, and bulk update prices and stock across every variation in one modal. This is the part most free **WooCommerce bulk editor** plugins do not handle, BrikPanel does.

= Can I bulk edit variation prices in WooCommerce with the free version? =

Yes. **Bulk edit variation prices WooCommerce** is a core BrikPanel feature, and it is free. Set a percentage rule, set a fixed price, or update by attribute (every "Red" variation, every "Large" size). The same modal handles **bulk update variation stock** for the same products.

= Does BrikPanel slow down my WooCommerce store? =

BrikPanel is built to stay light on your storefront. Everything you use in the admin (dashboard, reports, product list, product and bulk editors) loads only inside wp-admin, so none of it reaches your shoppers. On the storefront, BrikPanel adds only what its shopper-facing features need, and only on the pages that use them:

* **Visitor tracking**: a small script at the end of every page that sends its data in the background once the page is ready. On by default, switch: **Analytics → Visitor tracking**.
* **Abandoned-cart email capture**: a script on the checkout page only. On by default, switch: **Cart abandonment → Email collection**.
* **Share cart button**: a script and a small stylesheet on the cart page only. On by default, switch: **Cart share → Storefront share button**.
* **Variation gallery**: a small script on product pages. On by default, switch: **Products → Multiple images per variation**.
* **Cart recovery popup**: its scripts and stylesheet on your other pages, only if you turn the popup on. Off by default.
* **Description image lightbox**: a tiny script and stylesheet, only on products where you set a description image to open in a lightbox.

All switches are under **WooCommerce → Settings → BrikPanel**. Turn them off and BrikPanel adds no scripts, styles or requests to your storefront, apart from the lightbox on products where you used it.

= Is BrikPanel compatible with HPOS (High-Performance Order Storage)? =

Yes. Every order query has dual code paths, `wc_get_orders()` for HPOS, `WP_Query` for legacy. BrikPanel declares HPOS compatibility via `FeaturesUtil::declare_compatibility('custom_order_tables', ...)` and is tested on stores running both modes.

= How do I see WooCommerce sales by country? =

Open the BrikPanel dashboard. Scroll to the geographic analytics section. The 3D globe shows every order on its real geographic location, and the **Top 10 Countries** and **Top 10 Cities** tables update in real time. BrikPanel extracts country and city from the billing or shipping address of every order, so this works with no extra setup.

= How do I customize the WordPress login page for my WooCommerce store? =

BrikPanel includes a built-in **wordpress login customizer**. Enable the **custom wp login page** module from BrikPanel settings and the default `wp-login.php` is replaced with a clean, branded login form that matches the rest of the BrikPanel admin. No CSS knowledge required.

= How do I search WooCommerce orders by customer name or phone number? =

Press `Ctrl + K` (or `Cmd + K` on Mac) anywhere inside wp-admin. The BrikPanel quick search overlay opens and searches across order ID, customer name, email, phone, and product SKU at the same time. This is the **woocommerce search orders** experience the WooCommerce admin should ship with by default.

= Can I see who is on my WooCommerce store right now? =

Yes. BrikPanel includes a **woocommerce live visitors** widget on the dashboard that updates every 30 seconds. You can see what page each visitor is on, whether they have items in the cart, and whether they are an existing customer. This is real **woocommerce real time visitors** tracking, not estimates. A page nobody has touched for 30 minutes drops off the list, so a tab left open on a desk is not counted as someone on your store, and it comes back as soon as the visitor scrolls, clicks, taps or types.

= Does BrikPanel track WooCommerce conversion rate and conversion funnel? =

Yes. BrikPanel includes a complete **woocommerce conversion tracking** system that records visitors, add-to-cart events, checkout starts, and completed orders. The dashboard shows your **woocommerce conversion funnel** as a four-step visual: Visitors → Add to Cart → Checkout → Orders, with the conversion percentage at every step.

= Is there a free WooCommerce conversion tracking plugin built into BrikPanel? =

Yes. BrikPanel ships a free **WooCommerce conversion tracking plugin** that records every visitor, add-to-cart, checkout start and completed order in your own database, no Google Analytics setup, no Hotjar, no monthly fee. The funnel and conversion-rate widgets on the dashboard are computed from this same dataset in real time.

= Does BrikPanel recover abandoned carts? =

Yes. BrikPanel includes a built-in **WooCommerce cart abandonment** and **cart recovery** system in the free version: no Klaviyo, Mailchimp or external email SaaS. It captures the email of shoppers who begin checkout but do not complete the order (from both the classic shortcode checkout and the newer block checkout, and from logged-in customers the moment they add to cart) and lists every one on a dedicated **Abandoned Carts** screen. Each entry keeps a full snapshot of the cart, including the exact variation, quantity and total, and moves through Active, Abandoned and Recovered automatically, even if the shopper later checks out with a different email. The screen has search, status, source and date filters, per-row product details, CSV and Excel export, and statistics cards. It works on both simple and variable products.

= How does the WooCommerce cart recovery coupon popup work? =

Switch on the optional email popup and BrikPanel shows a clean, on-brand sign-up offer to your visitors. Anyone who subscribes is issued their own single-use percentage **cart recovery coupon** (10% by default, and you set the rate), restricted to their email and valid for 30 days, shown right there with a one-click Copy button. You control the heading, message, button and success text, the delay before it appears, the cooldown after it is dismissed, and which of six animated reveal styles the coupon uses (Sealed envelope, Pocket card, Scratch card, Slot machine, Magnetic assembly or Classic ticket), all of which respect a visitor's reduced-motion preference. Close the popup and it folds into a small floating tab, one click from reopening.

= How do I sync WooCommerce orders to Google Sheets for free? =

Open **WooCommerce → BrikPanel → Google Sheets**, click "Connect Google account", pick or create a target spreadsheet, and toggle "Real-time order sync" on. Every new WooCommerce order is then appended to your Sheet within seconds, with one row per line item so variations land in their own columns. Status changes update the existing row in place. No Zapier, no Make, no monthly fee, a real **woocommerce google sheets sync** built into BrikPanel.

= Does BrikPanel work as a free GSheetConnector or WPSyncSheets alternative? =

Yes. BrikPanel includes a complete **WooCommerce to Google Sheets** integration in the free version: real-time order sync, scheduled bulk export, analytics snapshot tabs (Sales Summary, Daily KPIs, Top Products, Funnel) and a customer + RFM snapshot. All four flows ship free with no row limit, no premium tier, and OAuth-based authentication that requests minimum scopes only (`drive.file`, never full Drive access).

= How do I see real ROAS and net profit in WooCommerce? =

Connect **Google Ads** and/or **Meta Ads** from the BrikPanel Ad Platforms page. BrikPanel then pulls your daily ad spend and shows three new dashboard cards: **Ad Spend** (summed across every connected platform for the active date range), **WooCommerce ROAS** (store revenue ÷ ad spend), and **Net Profit** (revenue − COGS − ad spend − manual expenses). COGS comes from WooCommerce's native order cost meta and expenses from the BrikPanel expenses table, so the **woocommerce roas** and net profit numbers are real, not estimates. The cards are multi-currency aware, if an ad account reports in a different currency than the store, spend is shown split and ROAS / Net Profit are omitted instead of printing a misleading converted number.

= Is BrikPanel a free Triple Whale alternative for WooCommerce? =

For self-hosted stores, yes. BrikPanel gives you the **WooCommerce ROAS** and **net profit** view store owners buy Triple Whale, TrueProfit or BeProfit for: daily **Google Ads** and **Meta Ads** spend pulled in next to store revenue, COGS and expenses, but it runs entirely on your own server with no monthly fee and no data sent to a third party. If you only need true ROAS and profit (not full multi-touch ad attribution), this is the free **Triple Whale alternative** built for that exact use case.

= Does BrikPanel connect to Google Ads and Meta (Facebook / Instagram) Ads? =

Yes. BrikPanel connects to both **Google Ads** and **Meta Ads** through a secure OAuth proxy (the plugin only ever stores encrypted tokens, never your password). It pulls daily spend per platform, backfills history, and re-syncs recent days automatically so the dashboard ROAS and net profit stay accurate. The integration is spend-and-profit focused, it does not install a Facebook pixel or do multi-touch attribution; it gives you true **woocommerce roas** and net profit without a paid SaaS.

= Is there a free WooCommerce variation editor for bulk price and stock updates? =

Yes. BrikPanel includes a complete **WooCommerce variation editor** in the free version. Open any variable product, click "Edit Variations", and you can bulk update every variation's price, sale price, stock and SKU in one modal, with attribute filtering when a product has 50+ combinations. The same **woocommerce variation editor** also supports per-attribute rules ("set every Red variation to $X").

= What makes BrikPanel different from the built-in WooCommerce analytics? =

The built-in WooCommerce analytics are slow, refresh on a delay, only show historical data, and have no live visitor tracking, no conversion funnel, no geographic globe, no customer LTV / RFM / cohort reports, no Cmd+K order search, no quick edit sidebar, no variation bulk editor, no custom login page, and no coupon manager. BrikPanel adds every one of those features inside a single free plugin.

= Is BrikPanel just a CSS reskin of the WooCommerce admin? =

No. BrikPanel is a real **woocommerce admin dashboard plugin** with custom database tables for visitor tracking, custom AJAX endpoints for every interaction, real conversion analytics, a working bulk editor, a real product editor, a real coupon manager, and a real custom login system. Other plugins (Dashify, UiPress) only restyle the admin. BrikPanel rebuilds the parts of WooCommerce that needed to be rebuilt.

= Can I use BrikPanel as a WordPress admin theme or admin skin for my store? =

In practice, yes. BrikPanel is built specifically for WooCommerce, but for store owners it behaves like a focused **WordPress admin theme**: it reskins the WooCommerce parts of wp-admin into a clean, Shopify-style **custom admin panel**, replaces the default toolbar, and restyles the product, order, customer and coupon screens. If you have been looking for a **wp admin theme** or an **admin skin** that makes the WooCommerce admin genuinely pleasant to work in (rather than a generic restyle that breaks on the next WooCommerce update), this is built for exactly that. You can also **hide admin menu** items for non-technical clients with the optional simplified mode, leaving only BrikPanel and WooCommerce in the sidebar.

= Does BrikPanel work with Yoast SEO, RankMath, Elementor, WPML, and Polylang? =

Yes. BrikPanel does not interfere with frontend rendering, so it works with every page builder and SEO plugin we have tested. Yoast SEO, Rank Math, All in One SEO and SEOPress metaboxes (including their SEO score panels) render and save inside the BrikPanel product editor. It also has its own translation files and is fully compatible with WPML and Polylang for multilingual stores.

= Does BrikPanel work with WooCommerce Subscriptions and membership plugins? =

Yes. BrikPanel is compatible with WooCommerce Subscriptions, Subscriptions for WooCommerce (WP Swings), MemberPress, Paid Memberships Pro, WooCommerce Memberships, YITH WooCommerce Subscription, SUMO Subscriptions, WebToffee Subscriptions for WooCommerce and Restrict Content Pro. Subscription products and member orders show up in the same product list, order screens and customer analytics as the rest of your catalog.

= Where does BrikPanel store data? =

Everything stays in your WordPress database. Visitor tracking writes to `wp_brikpanel_visitors` (daily totals), `wp_brikpanel_visited_pages`, `wp_brikpanel_referrers` and `wp_brikpanel_cart_tracking` — all anonymous counters with no visitor identifier in them. Other features add their own tables as you use them (expenses, suppliers, customer metrics, abandoned carts). Live visitor data is stored in a transient that auto-expires every 2 minutes and is never written to the database permanently. Your store, order, customer and visitor data is never sent anywhere. BrikPanel only contacts an external service for optional features you switch on yourself, described in the next question.

= What data does BrikPanel send outside my site? =

By default, nothing. BrikPanel only contacts an external service for features you explicitly opt into:

* **Newsletter (optional).** From a dismissible card on the dashboard, or from the Newsletter row in WooCommerce > Settings > BrikPanel, BrikPanel offers to email you occasionally about new features, WooCommerce tips and ideas for growing your store. Only if you type your email address and tick the consent box is that address sent to our server at brksoft.com, together with your site address, site language and BrikPanel version, so we can add you to the list. Nothing is sent unless you fill in the form and consent, and you can unsubscribe from any email we send. Privacy policy: https://brksoft.com/privacy-policy/ . Terms: https://brksoft.com/terms-and-conditions/
* **Google Sheets sync and Google / Meta Ads (optional).** If you connect these, BrikPanel exchanges data with Google, Meta and our authentication helper at brksoft.com to run the sync and read your ad spend. They only run after you connect the relevant account.

= Will BrikPanel always be free? =

Yes. The dashboard, the bulk editor, the inventory tools, the order management, the coupon manager, the custom login, the conversion tracking, the customer analytics suite, and every other feature listed above will remain free forever. We also sell a separate paid product (BrikMentor) on top of BrikPanel, but it is additive, BrikPanel itself stays 100% free.

= Is it BrikPanel or BrickPanel? =

BrikPanel, written as one word and without a "c". It is pronounced like "brick panel", so it is often searched for as BrickPanel, Brick Panel or Brik Panel. All of these point to this plugin, made by Brksoft.

== Screenshots ==

1. Dashboard
2. Cart Recovery
3. Ads ROAS
4. Sheets Sync
5. Product List
6. Quick Edit
7. Bulk Edit
8. Product Editor
9. Customer LTV
10. RFM Segments
11. Cohort Retention
12. Geo Analytics
13. Live Visitors
14. Order Search
15. Orders Explorer
16. Customers Explorer
17. Order Management
18. Categories
19. Coupons
20. Add Coupon
21. Login Page
22. Order Page

== Changelog ==
The full release history of every version is in changelog.txt, included with the plugin. The most recent releases are listed below.

= 3.3.23 (2026-09-25) =
* New: **Items sold on the dashboard.** The Orders and Order Rates cards show how many items were sold, each Recent Orders row shows its item count, and the Excel report has an "Items sold" row.
* New: **Order dates in Recent Orders.** Each order on the dashboard shows its date, and its status in WooCommerce's translated wording.
* New: **See where each live visitor came from.** Live visitors shows the source under the page, such as "Organic Search · google.com" or "Paid · bing.com". Hover for the campaign, search term and landing page. It respects cookie consent. Setting: Analytics → "Traffic source in Live view".
* New: **Revenue and Expenses without tax.** Dashboard → "Exclude tax from Revenue and Expenses" shows Revenue without tax in the Profit section and leaves tax out of Expenses. Net profit stays the same. Off by default.
* New: **Product videos for popular themes.** The product editor saves videos where WoodMart, Blocksy (with Companion Pro), Minimog, Shoptimizer / CommerceKit, Flatsome and Porto read them, and shows videos added from the theme.
* New: **WooCommerce ads are hidden.** Promo cards (such as the one WooCommerce 11 puts above the Orders list), the "Sale" badge on Extensions and extension suggestions are switched off with WooCommerce's own switches. Setting: General → "Hide WooCommerce ads".
* Fix: **Saving a product no longer erases Flatsome, Porto or CommerceKit data,** such as custom tabs, labels, layouts, custom CSS and videos.
* Fix: **Saving before the gallery finished loading no longer removes product images** (a 3.3.22 regression). Changing a variation image or the gallery now warns about unsaved changes.
* Fix: **Saving with a section closed no longer clears** a variation's sale dates and supplier, or a simple product's weight and dimensions.
* Fix: **Names with "&" no longer show as "&amp;"** in lists, the product editor, pickers, search, emails, CSV exports and Google Sheets. Sheets writes a variation's option name instead of its slug, and tags like "<5kg" and ">10kg" no longer merge into one.
* Fix: **Forgotten tabs no longer stay in Live visitors for days.** A page untouched for 30 minutes drops off the Live list and comes back as soon as the visitor is active. Idle tabs stop pinging the server.
* Fix: **Tapping a status tab on a phone no longer selects every order.** An invisible "Select all" label covered the orders list.
* Fix: **The customer name stays in the orders list.** When the Customer column is hidden or removed by another plugin, the name shows next to the order number. Long names no longer widen the list on phones.
* Fix: **The order status badge works with plugins that replace the status column,** such as Flexible Refund. Clicking it opens the status menu, and other plugins' status colours now show.
* Fix: **Better compatibility.** WP Bulk Delete's menu items can be clicked in the BrikPanel sidebar again, and PeproDev Ultimate Invoice no longer prints `var CURRENT_ORDER_MAIL = [];` in the orders list.
* Tweak: **Tables fit their cards.** The variation table shows Variation, Price, Sale price, Stock and COGS, and the other fields open under each row's ▾ arrow. Wide tables (Scheduled Tasks, Segments, Abandoned Carts, Expenses and more) turn rows into cards instead of being cut off.
* Fix: **Header bars keep the title and the Save button in view.** In the product editor, the order page and Google Sheets, extra buttons move into a "..." menu first, then the bar wraps, then labels turn into icons. The schedule date picker no longer closes at once or overflows on phones.
* Fix: **Notices appear under the page title,** not inside the title row or the product editor's sticky header, and the review box is readable on phones.
* Fix: **The "Write a review" button is readable on WordPress 7,** which colours links inside notices. Notices in Settings, the category screens and four other screens are fixed for the same reason.
* Fix: **Other plugins' notices look right on BrikPanel pages.** BrikPanel no longer removes other plugins' stylesheets there, only their scripts, so their dismiss links, bell notices and dashboard widgets keep their styling. WordPress 7 "Dismiss" links no longer spill out of notices.
* Fix: **The "Save changes" bar no longer covers settings.** It is a solid bar inside the settings column, stays at the bottom of the screen on phones and keeps clear of the side menu in right-to-left languages. Empty Save buttons are gone, and the Orders status bar no longer covers the bulk actions bar.
* Fix: **No red "0" on the bell when nothing is waiting.** The same bug showed a "1 / 1" pager in Customer Analytics, a stuck "Counting…" box in Google Sheets, "Edit email" in the cart popup and a "Supplier SKU" row with no supplier. Zero counts like "Updates 0" are hidden in the side menu.
* Fix: **The BrikMentor corner button no longer covers content.** It is now a labelled button, hidden on phones. Pages leave room for it, and the space beside its panel no longer blocks clicks.
* Security: **Quote marks in variation SKUs, GTINs and names are now escaped in the product editor.**

= 3.3.22 (2026-09-23) =
* Fix: **BrikPanel no longer switches itself off when WooCommerce sits in a differently named folder.** WooCommerce is now recognised by its main file, the way WordPress itself loads it, so stores that keep it in a folder such as `wc-core/` get BrikPanel back. The same assumption also removed WooCommerce's own files from BrikPanel pages on such stores and hid Admin Menu Editor Pro; both are fixed.
* Fix: **No more links to pages a user is not allowed to open.** Editors, authors and contributors no longer see an empty "More" row that led to a "not allowed" page. The same check now covers the WordPress toolbar, Cmd+K search, the top bar's Create menu, bell and logo, the product list's Import and Export buttons, the settings shortcuts, and users for whom a multisite network has switched BrikPanel off.
* Fix: **The side menu no longer overlaps on older WooCommerce versions.** On stores whose Orders screen is the classic list (WooCommerce 4.0, and stores without HPOS), Orders and Customers could spill out beside the WooCommerce heading. The bell's links, the "back" links in order merge, the toolbar Analytics shortcut and the redirect after switching a module off were corrected for the same reason.
* Fix: **The Navigation settings screen shows the menu in the same order as the sidebar.** It listed BrikMentor under "Site management", so saving without a change moved it there. Menus you already saved stay as they are.
* Fix: **"Email" and "popup" no longer overlap in the Abandoned Carts header.** The row arrow shared a CSS class with the header switch, so its sizing hit the switch's label. Three similar clashes are fixed too: italic empty cells in the products list, the order screen's status menu taking styles from the orders list, and generic class names in styles loaded on every admin page, which could restyle other plugins and put a magnifier on BrikPanel's power switch in the toolbar.
* Tweak: **Clearer wording.** The "Wait for cookie consent" setting now says it also covers signed-in customers, and the FAQ describes exactly which scripts load on the storefront, when, and where to switch each one off.
* Developer: **The Abandoned Carts contact cells are filled through a filter.** The phone, WhatsApp and envelope cells now take their content from `brikpanel_cartab_outreach_rows`; BrikPanel itself only draws them. With BrikMentor 1.15.8 or later nothing changes on screen; an older BrikMentor shows a padlock asking to be updated.

= 3.3.20 (2026-09-22) =
* Fix: **The product editor, both product lists and the search box no longer break a store running an older WooCommerce.** The GTIN / barcode field uses a WooCommerce feature that arrived in WooCommerce 9.2. On anything older the call had nothing to answer it, and the page stopped dead with a critical error instead of simply leaving the field out. That hit the product editor, the WordPress products list, BrikPanel's own products list and any search that matched a product, which between them is most of a working day. The GTIN field now works on older WooCommerce as well, reading and writing the same place WooCommerce itself keeps it, so the barcodes entered there appear by themselves once the store updates WooCommerce, with nothing to move across.
* Fix: **Coupons can be created and duplicated on an older WooCommerce again.** Saving a new coupon, or duplicating an existing one, called a WooCommerce feature added in WooCommerce 6.2. On an older store both buttons answered with a critical error and nothing was saved. A duplicated coupon is still created as a draft, so a copy never goes live on its own.
* Fix: **The Google Sheets order sync no longer fails in the background on an older WooCommerce.** The part of the sync that keeps track of which orders are already in the sheet used a WooCommerce class added in WooCommerce 6.9. There was no visible error because the work runs in the background, the sync simply stopped.
* Tweak: **The products list draws the GTIN column with one database query instead of one per variation.** A variable product with twenty variations used to cost twenty separate product lookups on every page of the list, for a single column.
* Change: **BrikPanel now states that it supports WooCommerce 9.2 and later, and says so on stores below that.** Nothing is switched off and nothing behaves differently below it. Everything above keeps working on older stores; administrators simply see a dismissible notice recommending a WooCommerce update.
* Developer: `includes/brikpanel-wc-compat.php` is now the single place that wraps any WooCommerce API newer than the oldest supported store, and `tools/wc-floor-audit.php` fails the build when one is called anywhere else or without a guard. It also treats `catch ( \Exception )` as no guard at all, because a missing method raises `Error`, which is what made this class of bug reach production three separate times.

= 3.3.19 (2026-09-22) =
* Change: **The abandoned carts list opens a cart's contents in place, from a small arrow at the start of the row.** The "Details" button used to sit in the last column, which on a table this wide was usually past the right edge of the screen, so reaching it meant scrolling sideways first. The arrow takes one narrow column and hands the whole actions column back, which is enough to bring another column of data into view. Clicking it slides the cart's items open underneath the row, lined up with the first column, and more than one row can be open at a time.
* Fix: **"Admins only" now really means administrators.** Hiding a top bar control (the hidden-notices bell, the order notifications bell, the search box, any of them) or a sidebar menu item from everyone but administrators had no effect on a store manager whose role had been given the "manage options" permission with a role editor, which is a common setup. Those staff accounts kept seeing everything the rule was meant to hide. BrikPanel now checks for the real administrator role, and on a network for a network administrator, exactly as the dashboard widget rules and the settings lock already did. Real administrators and network administrators are unaffected and can never hide a control from themselves. Reported on the support forum.
* Tweak: **Abandoned Carts and Customer Analytics now use the full width of the screen.** Both pages were held to a narrow centred column, so the Abandoned Carts table never had room for its columns and customer names wrapped onto three and four lines. It was worse after turning BrikMentor on, which adds a Phone and a Follow-ups column to the same narrow space. Both pages now run edge to edge like the Orders, Products and Segments lists, and on a wide screen the table finally fits without scrolling sideways. The search box and the explanatory lines stop growing past a comfortable reading width; everything else lines up with the table so the page reads as one block. Nothing changes on phones or tablets. Reported on the support forum.
* Fix: **Dates across BrikPanel now use your store's timezone.** They were showing UTC, so on a store set to Istanbul every time was three hours early and any order placed late in the evening was stamped with the previous day. Reported on the support forum for the Segments page, where it was most visible, but the same fault ran through Customer Analytics, the Store Summary report and the daily rows exported to Google Sheets. Segments now matches the WooCommerce orders screen to the minute.
* Fix: **Date filters now mean your store's day.** Picking "10 August", or the Today / Last 7 / 30 / 90 day shortcuts, used to select a London day. On a store three hours ahead that quietly pulled in the last three hours of the previous day and dropped the last three hours of the one you asked for, so the count looked plausible while the rows were wrong. Expect the numbers on these screens to shift a little after updating: they were wrong before.
* Fix: **The "Yesterday" row of Sales by Period covered 45 hours instead of 24.** It started at London midnight of the day before yesterday and ran to your midnight, so it overlapped the Today row and counted some orders twice. Yesterday's revenue will drop after this update, and both rows now add up without double counting.
* Fix: **The conversion funnel and cart abandonment rate were measured on two different clocks.** Visitor and checkout counts are recorded on your store's day while orders are stored in UTC, and the two windows were not lined up, so the percentages themselves were off rather than just their labels.
* Fix: **Best and worst sales times are now your hours, not London's.** The day of week table was silently shifted and the peak hour was labelled "(UTC)". Both now use the store clock, which is the one you would schedule staff or ads around.
* Fix: **The Segments CSV export matches what is on screen.** Three of its four date columns carried a raw UTC timestamp and the fourth a formatted date, so a file downloaded after filtering for a given day could contain rows stamped with the day before. Every date column is now your store's time in `2026-09-19 14:56` form, which sorts correctly in Excel, and each heading names the timezone it is using.
* Fix: **Monthly tables and customer cohorts are grouped by your months.** Orders placed in the first hours of a month were credited to the previous one. Cohort retention is rebuilt once after updating so old and new rows cannot be mixed.
* Fix: **Adding an expense just after midnight no longer files it under yesterday.** The date box was pre-filled with the London day while expenses are saved against your own day. Existing expenses are untouched.
* Fix: **A coupon set to expire on a date now expires at the end of that day in your timezone.** It used to survive a few hours into the next day, and the WooCommerce coupon screen showed the wrong expiry date as a result.
* Fix: **Twelve month tables no longer skip a month when viewed on the 31st.** Stepping back a month from the 31st landed in the same month again, which produced a duplicate column and a missing one on any screen with a monthly axis.

= 3.3.17 (2026-09-21) =
* Fix: **The order page keeps its two columns on smaller screens.** The right column dropped to the bottom of the page as soon as the window went below about 1355px, on a 17 inch laptop with plenty of room left. It now stays beside the items down to roughly a 1200px window, and only moves below once the items would no longer fit as a table. Reported on the support forum.
* New: **"Ad cost per order" on the dashboard.** Open the small arrow on the ROAS card and it divides your ad spend for the selected period by the orders placed in it, so the question "what did each order cost me in advertising?" is answered on the same screen as the rest of your numbers. It is tucked behind the arrow so the ROAS card stays exactly the same height as the cards beside it, the way the Revenue and Expenses breakdowns already work. The "?" beside it says exactly what is counted: orders placed by store administrators are left out, and on a store using BrikMarket so are marketplace orders, because ad spend drives your own store rather than a marketplace listing. Like ROAS, it shows a dash rather than a misleading figure when your ad account bills in a different currency from the store.
* Fix: **A "?" explanation on the dashboard is no longer cut off.** On a row of six or seven KPI cards the cards clip their contents to keep a long figure inside its column, which also swallowed the little box explaining how a figure is worked out, leaving a sliver of it showing. It now opens over the cards beside it.
* New: **A refresh button on the ROAS card pulls today's ad spend on demand.** Spend used to arrive once a day, so anyone looking at the dashboard in the afternoon saw a figure from that morning's sync. The small refresh icon in the corner of the ROAS card now fetches the last seven days from every connected ad platform on the spot, and the dashboard updates without a page reload. One person pulling refreshes it for everyone: the button is limited to one pull every ninety seconds across the whole store, so a team all watching the same dashboard cannot hammer the ad platforms. If one platform is unreachable the other still updates and the message says which one failed. Note that Meta and Google both keep revising the current day's figure for hours afterwards, so today's number is the freshest they will give rather than the final one.
* Fix: **New ad spend now appears on the dashboard immediately instead of up to ten minutes later.** The dashboard keeps its figures in a short-lived cache, and finishing an ad sync did not clear it, so freshly imported spend stayed invisible until the cache expired on its own. It is cleared as soon as spend lands.
* Fix: **Switching to a period with no ad spend no longer leaves the previous period's ROAS on the card.** Picking a date range without any advertising in it kept the last figure on screen as though it belonged to the new dates. The card now shows a dash, which is the true answer for a period with no spend.
* New: **You can choose what the login page says above the sign-in box.** It always read "Welcome back", in English, with no way to change it. Under WooCommerce → Settings → BrikPanel → Login page there is now a "Login page heading" choice: keep the default wording, show your site's name, write your own line, or show nothing at all. Your own line can be up to 100 characters.
* Fix: **The login page heading is translatable, and the password and registration screens no longer say "Welcome back".** The heading was painted by the stylesheet rather than written as text, so no translation could reach it and screen readers could not read it at all. It is now real text, translated into all nine shipped languages, and each screen says what it is for: "Reset your password", "Choose a new password", "Create an account", "Check your email".
* Fix: **The font and accent colour you pick under Appearance now apply to the login page.** They were meant to, and the font was even being downloaded there, but the rule pointed at something that does not exist on the page, so the login screen kept the default typeface and the near-black buttons no matter what you chose. The sign-in button, the field you are typing in, the "remember me" tick and the logo tile now all follow your accent colour. Nothing changes if the modern login page is switched off.
* Fix: **The login page is laid out right-to-left on Arabic, Hebrew, Persian and Urdu sites.** The adjustments for those languages were written but were only ever loaded inside the admin area, never on the login screen itself, so the show-password button and the "remember me" tick stayed on the wrong side.
* New: **The Login page settings now say where the login logo lives.** The logo on the login page has always been the brand logo set under Appearance, but nothing on the Login page screen said so, which made it look like a missing feature. There is now a line pointing at it.

= 3.3.16 (2026-09-20) =
* Fix: **Google Ads no longer says "Connected" when the permission was in fact declined.** Google shows the Ads permission as a checkbox that is not ticked in advance, so the connection could finish with it left off: the card reported success and no spend ever arrived, with nothing on screen to explain why. BrikPanel now checks what Google actually granted and, when the Ads permission is missing, says so and asks you to connect again with it ticked. Meta Ads has had the same check since it was added, and nothing about the Meta connection changes.
* Tweak: **The Google Ads card now explains Google's permission screen instead of contradicting it.** The card promised "read-only access" while Google asked for "See, edit, create, and delete your Google Ads accounts and data", which looked like BrikPanel asking for far more than it admitted. It was not: the Google Ads API has exactly one permission and no read-only version, so there is nothing narrower to request, and BrikPanel still only reads daily spend, impressions and clicks and never creates, edits or deletes anything in your account. The card says that plainly, and for anyone who wants the permission itself narrowed it names the way to do it: connect with a Google account that has Read-only access to the Ads account.
* Fix: **Hiding columns in the order list through Screen Options works properly again.** Turning off the "Order" column left the compact list unusable: the arrow that opens the order details sat in a hidden cell, so the panel could not be opened at all, and several columns at once were pinned to the left edge with a white background that cut across the row lines and the hover highlight. The arrow now moves to the Customer column, or the first column still visible; nothing is pinned when there is nothing to pin; the phone layout follows the columns you hide; and the "No items found" row spans the columns actually on screen. Hiding Payment method or Shipping was already safe, and both stay in the order panel as a "via ..." line.

= 3.3.15 (2026-09-19) =
* Fix: **The changelog on the WordPress.org plugin page is complete again.** The release history had grown longer than WordPress.org accepts, so the page cut it off part way through and the newest entries were the only ones anybody could read in full. The page now carries the most recent releases, and the complete history of every version ships with the plugin in `changelog.txt`.

= 3.3.14 (2026-09-19) =
* New: **Invoice, shipping label and tracking boxes are back in the right column of the order page.** Since the order page moved to tabs, every box another plugin adds went under "More", so printing an invoice or entering a tracking number cost a click on every single order. BrikPanel now recognises more than forty boxes from the widely used invoice, packing slip, courier and payment plugins and opens them in the narrow right column, where they were before. The test a box has to pass is whether you do something with it on the order, so a box that only reports something stays under "More". Everything it does not recognise stays under "More" exactly as now, and the "More" tab disappears when nothing is left in it. Screen Options has a new "Show in the sidebar" list: tick any box to move it to the right column, untick one to send it back, with a "Reset to defaults" link. The choice is saved per person, so each member of staff can arrange the screen their own way. Developers can add their own box with the new `brikpanel_order_sidebar_boxes` filter.
* Fix: **Google Ads, Meta Ads and Google Sheets connections no longer disconnect themselves.** A connection could vanish seconds after it was made, and the log blamed a "corrupted" stored credential that was in fact perfectly intact. The cause was the site address: WordPress reports it as `https` or `http` depending on how each individual request arrived, and BrikPanel locked its stored credentials to that value — so a connection made in your browser over HTTPS could not be read by the scheduled task that ran moments later, and BrikPanel deleted it. It took both ad platforms at once, because they share one stored record. Credentials are now locked to the site's own security keys instead, which do not change between requests, and **nothing is ever deleted because it could not be read**: existing connections are upgraded automatically the first time they are opened, and a credential that genuinely cannot be read is kept and reported rather than thrown away.
* Fix: **A connection you have just re-made is no longer deleted by a renewal already in progress.** A background renewal that was still using the previous credential could get a rejection back from Meta and act on it, removing the connection you had created seconds earlier. The renewal now checks that the credential it was rejected for is still the stored one.
* Fix: **A background renewal no longer reverts the ad account you just picked.** Choosing a different ad account while a scheduled sync was running could silently put the old choice back.
* Fix: **The Ad Platforms and Google Sheets cards now say when stored credentials cannot be read**, instead of simply showing "Not connected" with no explanation, and name the usual causes: a changed site address, a move to a new server, or new security keys in wp-config.php.
= 3.3.13 (2026-09-18) =
* New: **The signup popup waits for your cookie banner.** On a store with a cookie banner, visitors used to meet two things at once: the banner and, seconds later, the signup popup on top of it. The popup now holds back until the visitor has answered the banner, and accepting or declining both release it, because signing up for an offer is not tracking. Nothing changes on a store without a banner, the floating coupon tab is never held back, and the popup opens after 30 seconds whatever the banner does, so no signup can be lost. Turn it off under WooCommerce → Settings → BrikPanel → Cart abandonment → "Wait for cookie banner".
* Fix: **Orders renumbered by a sequential order number plugin now show that number everywhere, not just in the orders list.** Opening an order showed WooCommerce's internal ID in the header instead of the number the list had just shown. The dashboard's recent orders, the Segments table, the recovered-order link on Abandoned Carts and the status-change bar on the orders list had the same problem.
* Fix: **Cmd/Ctrl + K finds an order by phone number however it was typed.** A number kept as "+44 7911 123456" was not found by typing "07911 123456", or the other way round. Spaces, dashes, brackets and a leading 0 or 00 no longer matter, and the last digits of a number are enough to find it. Names now match inside a word and across spellings, so "yilmaz" finds "Yılmaz", and an exact name comes before a partial one. Trashed and draft orders no longer take up result slots, an order that matches twice is listed once, the closest match is first again, and a search beginning with "-" no longer fails. On a store with 700,000 orders a search by customer name went from 761 ms to 1 ms.
* Dev: **New filters `brikpanel_search_terms`** (add your own spellings of what was typed) **and `brikpanel_search_order_ids`** (add or drop the orders the palette found), and **`window.brikpanel_popup_consent_answered()`**, which tells the signup popup that a banner not speaking the WordPress Consent API has been answered. All three are documented on the Developer page.
