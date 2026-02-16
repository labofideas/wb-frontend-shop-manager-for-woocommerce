<?php
/**
 * Permission checks.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_FSM_Permissions {

	/**
	 * Check dashboard access for current user.
	 *
	 * @return bool
	 */
	public static function current_user_can_access_dashboard(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user     = wp_get_current_user();
		$settings = WB_FSM_Helpers::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		$whitelist = array_map( 'absint', (array) $settings['whitelisted_users'] );
		if ( in_array( (int) $user->ID, $whitelist, true ) ) {
			return user_can( $user, 'read' );
		}

		$allowed_roles = (array) $settings['allowed_roles'];
		if ( array_intersect( $user->roles, $allowed_roles ) ) {
			return user_can( $user, 'read' );
		}

		return false;
	}

	/**
	 * Is user in partner role scope.
	 *
	 * @param WP_User|null $user User object.
	 * @return bool
	 */
	public static function is_partner_user( ?WP_User $user = null ): bool {
		if ( ! $user ) {
			$user = wp_get_current_user();
		}

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}

		$settings = WB_FSM_Helpers::get_settings();

		$whitelist = array_map( 'absint', (array) $settings['whitelisted_users'] );
		if ( in_array( (int) $user->ID, $whitelist, true ) ) {
			return true;
		}

		$allowed_roles = (array) $settings['allowed_roles'];
		return (bool) array_intersect( $user->roles, $allowed_roles );
	}

	/**
	 * Can current user manage product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function current_user_can_manage_product( int $product_id ): bool {
		if ( ! self::current_user_can_access_dashboard() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$settings = WB_FSM_Helpers::get_settings();
		if ( 'shared' === $settings['ownership_mode'] ) {
			return true;
		}

		$current_user_id = get_current_user_id();
		$product         = get_post( $product_id );

		if ( ! $product || 'product' !== $product->post_type ) {
			return false;
		}

		$assigned_user = (int) get_post_meta( $product_id, '_wb_fsm_assigned_user_id', true );
		if ( $assigned_user > 0 ) {
			return $assigned_user === $current_user_id;
		}

		return (int) $product->post_author === $current_user_id;
	}

	/**
	 * Can current user view order.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	public static function current_user_can_view_order( WC_Order $order ): bool {
		if ( ! self::current_user_can_access_dashboard() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$settings = WB_FSM_Helpers::get_settings();
		if ( 'shared' === $settings['ownership_mode'] ) {
			return true;
		}

		$current_user_id = get_current_user_id();
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( ! $product_id ) {
				continue;
			}

			$assigned_user = (int) get_post_meta( $product_id, '_wb_fsm_assigned_user_id', true );
			if ( $assigned_user > 0 && $assigned_user === $current_user_id ) {
				return true;
			}

			$product = get_post( $product_id );
			if ( $product && (int) $product->post_author === $current_user_id ) {
				return true;
			}
		}

		return false;
	}
}
