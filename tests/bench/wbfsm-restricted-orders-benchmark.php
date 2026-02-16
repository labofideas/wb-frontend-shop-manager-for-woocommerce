<?php
/**
 * WB FSM restricted-order benchmark (WP-CLI: wp eval-file ...).
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! class_exists( 'WB_FSM_Orders' ) ) {
	echo "WB_FSM_Orders class not found.\n";
	exit( 1 );
}

$user_login = (string) ( $argv[1] ?? 'wbpq_staff' );
$iterations = max( 1, (int) ( $argv[2] ?? 30 ) );
$user       = get_user_by( 'login', $user_login );

if ( ! $user ) {
	echo esc_html( 'User not found: ' . $user_login ) . "\n";
	exit( 1 );
}

$settings                   = WB_FSM_Helpers::get_settings();
$settings['enabled']        = 1;
$settings['ownership_mode'] = 'restricted';
update_option( 'wbfsm_settings', $settings );

wp_set_current_user( (int) $user->ID );

$orders = new WB_FSM_Orders();
$cold   = $orders->benchmark_restricted_query( 1 );
$warm   = $orders->benchmark_restricted_query( $iterations );

echo wp_json_encode(
	array(
		'user'       => $user_login,
		'iterations' => $iterations,
		'cold'       => $cold,
		'warm'       => $warm,
	),
	JSON_PRETTY_PRINT
);
echo "\n";

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
