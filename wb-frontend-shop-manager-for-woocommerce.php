<?php
/**
 * Plugin Name: WB Frontend Shop Manager for WooCommerce
 * Plugin URI:  https://wbcomdesigns.com/
 * Description: Frontend dashboard for WooCommerce partners to manage products and orders without wp-admin access.
 * Version:     1.0.0
 * Author:      Wbcom Designs
 * Author URI:  https://wbcomdesigns.com/
 * Text Domain: wb-frontend-shop-manager-for-woocommerce
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WBFSM_FILE' ) ) {
	define( 'WBFSM_FILE', __FILE__ );
}

if ( ! defined( 'WBFSM_PATH' ) ) {
	define( 'WBFSM_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WBFSM_URL' ) ) {
	define( 'WBFSM_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WBFSM_VERSION' ) ) {
	define( 'WBFSM_VERSION', '1.0.0' );
}

require_once WBFSM_PATH . 'includes/class-wb-fsm-loader.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		WB_FSM_Loader::instance();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html__( 'WB Frontend Shop Manager for WooCommerce requires WooCommerce to be active.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		require_once WBFSM_PATH . 'includes/class-wb-fsm-audit-logs.php';
		WB_FSM_Audit_Logs::create_table();
		update_option( 'wbfsm_version', WBFSM_VERSION );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wbfsm-settings' ) ) . '">' . esc_html__( 'Settings', 'wb-frontend-shop-manager-for-woocommerce' ) . '</a>';
		$links         = array_merge( array( $settings_link ), $links );

		if ( class_exists( 'WB_FSM_Helpers' ) ) {
			$dashboard_url = WB_FSM_Helpers::get_dashboard_url();
			if ( $dashboard_url ) {
				$links[] = '<a href="' . esc_url( $dashboard_url ) . '">' . esc_html__( 'Dashboard Page', 'wb-frontend-shop-manager-for-woocommerce' ) . '</a>';
			}
		}

		return $links;
	}
);
