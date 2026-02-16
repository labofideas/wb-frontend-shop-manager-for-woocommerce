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

echo wp_json_encode(
	array(
		'ok'       => true,
		'settings' => $settings,
	),
	JSON_PRETTY_PRINT
);
echo "\n";
