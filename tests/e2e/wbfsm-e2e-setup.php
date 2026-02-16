<?php
/**
 * WB FSM E2E setup helper (WP-CLI: wp eval-file ...).
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$settings = WB_FSM_Helpers::get_settings();

$settings['enabled']                   = 1;
$settings['allowed_roles']             = array( 'shop_manager' );
$settings['block_wp_admin']            = 0;
$settings['allow_order_status_update'] = 1;
$settings['ownership_mode']            = 'restricted';
$settings['require_product_approval']  = 1;

update_option( 'wbfsm_settings', $settings );

$admin_login   = 'wbfsm_e2e_admin';
$partner_login = 'wbfsm_e2e_partner';
$password      = 'WbfsmE2e#2026';

$admin_user_id = (int) username_exists( $admin_login );
if ( $admin_user_id <= 0 ) {
	$admin_user_id = (int) wp_create_user( $admin_login, $password, 'wbfsm_e2e_admin@example.com' );
}
if ( $admin_user_id > 0 ) {
	$admin_user = get_user_by( 'id', $admin_user_id );
	if ( $admin_user ) {
		$admin_user->set_role( 'administrator' );
		wp_set_password( $password, $admin_user_id );
	}
}

$partner_user_id = (int) username_exists( $partner_login );
if ( $partner_user_id <= 0 ) {
	$partner_user_id = (int) wp_create_user( $partner_login, $password, 'wbfsm_e2e_partner@example.com' );
}
if ( $partner_user_id > 0 ) {
	$partner_user = get_user_by( 'id', $partner_user_id );
	if ( $partner_user ) {
		$partner_user->set_role( 'shop_manager' );
		wp_set_password( $password, $partner_user_id );
	}
}

$dashboard_id = WB_FSM_Helpers::get_dashboard_page_id();
if ( $dashboard_id <= 0 ) {
	$dashboard_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Shop Manager Dashboard',
			'post_content' => '[wb_fsm_dashboard]',
		)
	);
}

echo wp_json_encode(
	array(
		'ok'       => true,
		'settings' => $settings,
		'users'    => array(
			'admin'   => $admin_login,
			'partner' => $partner_login,
			'pass'    => $password,
		),
		'dashboard_page_id' => (int) $dashboard_id,
	),
	JSON_PRETTY_PRINT
);
echo "\n";
