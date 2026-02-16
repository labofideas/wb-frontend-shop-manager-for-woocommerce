<?php
/**
 * Helpers.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_FSM_Helpers {

	/**
	 * Get settings with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {
		$defaults = array(
			'enabled'                   => 1,
			'allowed_roles'             => array( 'shop_manager' ),
			'whitelisted_users'         => array(),
			'block_wp_admin'            => 1,
			'editable_fields'           => array( 'name', 'sku', 'regular_price', 'sale_price', 'stock_quantity', 'status', 'description' ),
			'allow_order_status_update' => 1,
			'ownership_mode'            => 'shared',
		);

		$settings = get_option( 'wbfsm_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Load a plugin template.
	 *
	 * @param string               $template Template file.
	 * @param array<string, mixed> $data     Data.
	 * @return string
	 */
	public static function load_template( string $template, array $data = array() ): string {
		$path = WBFSM_PATH . 'templates/' . $template;
		if ( ! file_exists( $path ) ) {
			return '';
		}

		ob_start();
		extract( $data, EXTR_SKIP );
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Available product statuses.
	 *
	 * @return array<string,string>
	 */
	public static function product_statuses(): array {
		return array(
			'draft'   => __( 'Draft', 'wb-frontend-shop-manager-for-woocommerce' ),
			'publish' => __( 'Published', 'wb-frontend-shop-manager-for-woocommerce' ),
			'pending' => __( 'Pending Review', 'wb-frontend-shop-manager-for-woocommerce' ),
		);
	}

	/**
	 * Find published dashboard page ID containing shortcode.
	 *
	 * @return int
	 */
	public static function get_dashboard_page_id(): int {
		$page = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '[wb_fsm_dashboard]',
			)
		);

		if ( ! empty( $page[0]->ID ) ) {
			return (int) $page[0]->ID;
		}

		return 0;
	}

	/**
	 * Get dashboard page URL if available.
	 *
	 * @return string
	 */
	public static function get_dashboard_url(): string {
		$page_id = self::get_dashboard_page_id();
		if ( $page_id > 0 ) {
			return (string) get_permalink( $page_id );
		}

		return '';
	}
}
