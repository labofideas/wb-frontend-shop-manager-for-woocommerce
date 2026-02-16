=== WB Frontend Shop Manager for WooCommerce ===
Contributors: wbcomdesigns
Tags: woocommerce, frontend dashboard, shop manager, vendor dashboard, products, orders
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WooCommerce products and orders from a secure frontend dashboard without giving partners direct wp-admin access.

== Description ==

WB Frontend Shop Manager for WooCommerce gives store admins a controlled frontend workspace for partners and shop managers.

It includes:

* Frontend dashboard for product and order management
* Role-based dashboard access controls
* Optional wp-admin lockout for partner roles
* Product create/edit support for simple products
* Product field-level permissions (price, stock, SKU, description, image, category, status)
* Order list, order detail, and optional status update control
* Audit log tracking for partner actions
* Settings page with guided setup actions
* Dashboard availability via shortcode and Gutenberg block

== Key Features ==

* **Frontend-only operations:** partners can work without accessing core WooCommerce admin pages.
* **Admin safety controls:** whitelist allowed roles and enforce wp-admin blocking for those roles.
* **Ownership mode support:** shared and restricted partner workflows.
* **Auditability:** action logs for product/order events with filtering, sorting, and CSV export.
* **Editor flexibility:** use either shortcode or the included Gutenberg block.

== Installation ==

1. Upload `wb-frontend-shop-manager-for-woocommerce` to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Ensure WooCommerce is active.
4. Go to **WP Admin > WB Shop Manager**.
5. Enable the dashboard and select allowed roles.
6. Click **Create Dashboard Page** if no dashboard page exists.
7. Share the dashboard page URL with partner users.

== Quick Setup ==

1. Enable `Frontend dashboard`.
2. Select roles allowed to access dashboard (for example: `shop_manager`).
3. Enable `Block wp-admin access for allowed roles` (recommended).
4. Choose ownership mode:
   * `Shared Store` for all products/orders access
   * `Restricted Partner` for assignment-based control
5. Choose editable product fields for partner users.
6. Enable order status updates only if your workflow requires it.

== Usage ==

### Shortcode

Use the shortcode on any page:

`[wb_fsm_dashboard]`

### Gutenberg Block

In the block editor, insert:

`WB FSM Dashboard`

The block is dynamic and renders the same dashboard output as the shortcode.

== Security ==

This plugin follows core WordPress and WooCommerce security practices:

* Capability checks for admin settings and operational actions
* Nonce validation for state-changing requests
* Input sanitization and output escaping
* Optional wp-admin lockout for partner roles

== Audit Logs ==

Audit log entries include:

* User ID
* Action type
* Object ID and object type
* Before/after payload snapshots (JSON)
* Timestamp

Admins can filter logs by search, action type, user ID, and date range, then export CSV.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce must be active.

= Can partners be blocked from wp-admin? =

Yes. Enable `Block wp-admin access for allowed roles` in plugin settings.

= Is there a shortcode? =

Yes. Use `[wb_fsm_dashboard]`.

= Is Gutenberg supported? =

Yes. The plugin includes the `WB FSM Dashboard` block.

= Are variable products supported in this version? =

MVP scope is focused on simple product management.

= Can I track what partners changed? =

Yes. Audit logs capture key product/order actions and can be exported to CSV.

== Screenshots ==

1. Plugin settings page with dashboard, access, and permissions controls.
2. Frontend dashboard home panel with quick stats and navigation.
3. Products list with search, filters, and row actions.
4. Product edit form for partner users.
5. Orders list and order detail view.
6. Admin audit logs with filters, sorting, and export.

== Changelog ==

= 1.0.0 =

* Initial stable release.
* Frontend dashboard for WooCommerce partners/shop managers.
* Product list, create, and edit for simple products.
* Order list and detail view with optional status update control.
* Role-based access controls and optional wp-admin lockout.
* Ownership mode framework (shared/restricted).
* Audit logs with admin UI, filters, sorting, pagination, and CSV export.
* Modernized admin settings experience with quick setup actions.
* Plugin action links on Plugins screen (`Settings`, `Dashboard Page`).
* Dashboard page helper and one-click dashboard page creation.
* Gutenberg block support (`WB FSM Dashboard`).
* Shortcode support (`[wb_fsm_dashboard]`).

== Upgrade Notice ==

= 1.0.0 =

First stable release.
