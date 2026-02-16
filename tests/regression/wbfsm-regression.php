<?php
/**
 * WB FSM regression checks (WP-CLI: wp eval-file ...).
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$results = array();
$errors  = array();

$original_user_id  = get_current_user_id();
$original_settings = get_option( 'wbfsm_settings', array() );
$cleanup_posts     = array();

$record = static function ( string $name, bool $ok, string $details = '' ) use ( &$results, &$errors ): void {
	$results[] = array(
		'name'    => $name,
		'ok'      => $ok,
		'details' => $details,
	);

	if ( ! $ok ) {
		$errors[] = $name . ( $details ? ' - ' . $details : '' );
	}
};

try {
	if ( ! class_exists( 'WB_FSM_Permissions' ) || ! class_exists( 'WB_FSM_Products' ) || ! class_exists( 'WB_FSM_Orders' ) ) {
		throw new RuntimeException( 'WB FSM classes are not loaded. Ensure plugin is active.' );
	}

	$staff_id    = (int) username_exists( 'wbpq_staff' );
	$customer_id = (int) username_exists( 'wbpq_customer' );

	if ( $staff_id <= 0 || $customer_id <= 0 ) {
		throw new RuntimeException( 'Required users (wbpq_staff, wbpq_customer) are missing.' );
	}

	update_option(
		'wbfsm_settings',
		array(
			'enabled'                   => 1,
			'allowed_roles'             => array( 'shop_manager' ),
			'whitelisted_users'         => array(),
			'block_wp_admin'            => 1,
			'editable_fields'           => array( 'name', 'sku', 'regular_price', 'sale_price', 'stock_quantity', 'status', 'description' ),
			'allow_order_status_update' => 1,
			'ownership_mode'            => 'restricted',
		)
	);

	wp_set_current_user( 0 );
	$record( 'anonymous_blocked', ! WB_FSM_Permissions::current_user_can_access_dashboard() );

	wp_set_current_user( $customer_id );
	$record( 'disallowed_role_blocked', ! WB_FSM_Permissions::current_user_can_access_dashboard() );

	wp_set_current_user( $staff_id );
	$record( 'allowed_partner_access', WB_FSM_Permissions::current_user_can_access_dashboard() );

	$product_allowed_id = (int) wp_insert_post(
		array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'post_title'  => 'FSM Regression Allowed ' . time(),
			'post_author' => 1,
		)
	);
	$cleanup_posts[] = $product_allowed_id;
	update_post_meta( $product_allowed_id, '_regular_price', '11' );
	update_post_meta( $product_allowed_id, '_price', '11' );
	update_post_meta( $product_allowed_id, '_wb_fsm_assigned_user_id', $staff_id );

	$product_blocked_id = (int) wp_insert_post(
		array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'post_title'  => 'FSM Regression Blocked ' . time(),
			'post_author' => 1,
		)
	);
	$cleanup_posts[] = $product_blocked_id;
	update_post_meta( $product_blocked_id, '_regular_price', '12' );
	update_post_meta( $product_blocked_id, '_price', '12' );
	update_post_meta( $product_blocked_id, '_wb_fsm_assigned_user_id', $customer_id );

	$product_owned_id = (int) wp_insert_post(
		array(
			'post_type'   => 'product',
			'post_status' => 'draft',
			'post_title'  => 'FSM Regression Own ' . time(),
			'post_author' => $staff_id,
		)
	);
	$cleanup_posts[] = $product_owned_id;

	$p_allowed = wc_get_product( $product_allowed_id );
	$p_blocked = wc_get_product( $product_blocked_id );

	if ( ! $p_allowed || ! $p_blocked ) {
		throw new RuntimeException( 'Failed to create temporary products.' );
	}

	$mix_order = wc_create_order();
	$mix_order->add_product( $p_allowed, 1 );
	$mix_order->add_product( $p_blocked, 1 );
	$mix_order->calculate_totals();
	$mix_order->update_status( 'processing', 'FSM regression mixed order', true );
	$mix_order_id    = (int) $mix_order->get_id();
	$cleanup_posts[] = $mix_order_id;

	$blocked_order = wc_create_order();
	$blocked_order->add_product( $p_blocked, 1 );
	$blocked_order->calculate_totals();
	$blocked_order->update_status( 'processing', 'FSM regression blocked order', true );
	$blocked_order_id = (int) $blocked_order->get_id();
	$cleanup_posts[]  = $blocked_order_id;

	$_GET      = array();
	$products  = ( new WB_FSM_Products() )->get_products_for_current_user();
	$product_ids = array_map(
		static fn( $p ): int => (int) $p->get_id(),
		(array) ( $products['products'] ?? array() )
	);

	$record( 'restricted_products_include_allowed', in_array( $product_allowed_id, $product_ids, true ) );
	$record( 'restricted_products_include_own', in_array( $product_owned_id, $product_ids, true ) );
	$record( 'restricted_products_exclude_blocked', ! in_array( $product_blocked_id, $product_ids, true ) );

	$_GET       = array();
	$order_data = ( new WB_FSM_Orders() )->get_orders_for_current_user();
	$order_ids  = array_map(
		static fn( $o ): int => (int) $o->get_id(),
		(array) ( $order_data['orders'] ?? array() )
	);

	$record( 'restricted_orders_include_mixed', in_array( $mix_order_id, $order_ids, true ) );
	$record( 'restricted_orders_exclude_blocked_only', ! in_array( $blocked_order_id, $order_ids, true ) );

	$record( 'can_view_mixed_order', WB_FSM_Permissions::current_user_can_view_order( wc_get_order( $mix_order_id ) ) );
	$record( 'cannot_view_blocked_order', ! WB_FSM_Permissions::current_user_can_view_order( wc_get_order( $blocked_order_id ) ) );

	$settings_shared                     = get_option( 'wbfsm_settings', array() );
	$settings_shared['ownership_mode']   = 'shared';
	update_option( 'wbfsm_settings', $settings_shared );

	$_GET       = array();
	$shared_products = ( new WB_FSM_Products() )->get_products_for_current_user();
	$shared_ids = array_map(
		static fn( $p ): int => (int) $p->get_id(),
		(array) ( $shared_products['products'] ?? array() )
	);
	$record( 'shared_mode_sees_blocked_product', in_array( $product_blocked_id, $shared_ids, true ) );
	$record( 'shared_mode_can_manage_blocked_product', WB_FSM_Permissions::current_user_can_manage_product( $product_blocked_id ) );
} catch ( Throwable $e ) {
	$errors[] = 'Fatal: ' . $e->getMessage();
}

foreach ( $cleanup_posts as $post_id ) {
	if ( $post_id > 0 && get_post( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
}

update_option( 'wbfsm_settings', $original_settings );
wp_set_current_user( $original_user_id );

echo wp_json_encode(
	array(
		'passed'  => count( $errors ) === 0,
		'results' => $results,
		'errors'  => $errors,
	),
	JSON_PRETTY_PRINT
);

if ( count( $errors ) > 0 ) {
	exit( 1 );
}

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
