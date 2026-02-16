<?php
/**
 * Orders management.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_FSM_Orders {

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_wbfsm_update_order_status', array( $this, 'handle_update_order_status' ) );
	}

	/**
	 * Get orders for current user.
	 *
	 * @return array<string,mixed>
	 */
	public function get_orders_for_current_user(): array {
		$paged  = max( 1, absint( wp_unslash( $_GET['wbfsm_order_page'] ?? 1 ) ) );
		$search = sanitize_text_field( wp_unslash( $_GET['order_search'] ?? '' ) );
		$status = sanitize_key( wp_unslash( $_GET['order_status'] ?? '' ) );

		$args = array(
			'paginate' => true,
			'page'     => $paged,
			'limit'    => 20,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);
		$settings = WB_FSM_Helpers::get_settings();

		if ( $search ) {
			$args['search'] = '*' . $search . '*';
		}

		if ( $status ) {
			$args['status'] = array( $status );
		}

		if ( WB_FSM_Permissions::is_partner_user() && 'restricted' === $settings['ownership_mode'] ) {
			return $this->get_restricted_orders_for_current_user( $paged, $search, $status );
		}

		$results = wc_get_orders( $args );
		$orders  = array();

		if ( ! empty( $results->orders ) ) {
			foreach ( $results->orders as $order ) {
				if ( WB_FSM_Permissions::current_user_can_view_order( $order ) ) {
					$orders[] = $order;
				}
			}
		}

		return array(
			'orders'       => $orders,
			'total_pages'  => isset( $results->max_num_pages ) ? (int) $results->max_num_pages : 1,
			'current_page' => $paged,
			'search'       => $search,
			'status'       => $status,
		);
	}

	/**
	 * Handle status update.
	 *
	 * @return void
	 */
	public function handle_update_order_status(): void {
		if ( ! WB_FSM_Permissions::current_user_can_access_dashboard() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		check_admin_referer( 'wbfsm_update_order_status' );

		$settings = WB_FSM_Helpers::get_settings();
		if ( empty( $settings['allow_order_status_update'] ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Order status updates are disabled.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		$order_id = absint( wp_unslash( $_POST['order_id'] ?? 0 ) );
		$order    = wc_get_order( $order_id );
		if ( ! $order || ! WB_FSM_Permissions::current_user_can_view_order( $order ) ) {
			wp_die( esc_html__( 'Order not available.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		$new_status = sanitize_key( wp_unslash( $_POST['new_status'] ?? '' ) );
		if ( ! $new_status ) {
			wp_die( esc_html__( 'Invalid status.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		$valid_statuses = array_map(
			static fn( string $status_key ): string => str_replace( 'wc-', '', $status_key ),
			array_keys( wc_get_order_statuses() )
		);
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			wp_die( esc_html__( 'Invalid status.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		$before = array(
			'status' => $order->get_status(),
		);

		$order->update_status( $new_status, __( 'Updated from frontend shop manager.', 'wb-frontend-shop-manager-for-woocommerce' ), true );

		if ( ! empty( $_POST['order_note'] ) ) {
			$order->add_order_note( sanitize_textarea_field( wp_unslash( $_POST['order_note'] ) ) );
		}

		$after = array(
			'status' => $order->get_status(),
		);

		WB_FSM_Audit_Logs::add_log( 'order_status_change', $order_id, 'order', $before, $after );

		$redirect = add_query_arg(
			array(
				'wbfsm_tab' => 'orders',
				'order_id'  => $order_id,
				'wbfsm_msg' => 'order_updated',
			),
			wp_get_referer() ?: home_url( '/' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Optimized restricted-mode order listing.
	 *
	 * @param int    $paged  Current page.
	 * @param string $search Search term.
	 * @param string $status Status slug.
	 * @return array<string,mixed>
	 */
	private function get_restricted_orders_for_current_user( int $paged, string $search, string $status ): array {
		$product_ids = $this->get_accessible_product_ids( get_current_user_id() );
		if ( empty( $product_ids ) ) {
			return array(
				'orders'       => array(),
				'total_pages'  => 1,
				'current_page' => $paged,
				'search'       => $search,
				'status'       => $status,
			);
		}

		$order_ids = $this->get_restricted_order_ids( $product_ids, $search, $status );
		$per_page  = 20;
		$total     = count( $order_ids );

		if ( 0 === $total ) {
			return array(
				'orders'       => array(),
				'total_pages'  => 1,
				'current_page' => $paged,
				'search'       => $search,
				'status'       => $status,
			);
		}

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$offset      = max( 0, ( $paged - 1 ) * $per_page );
		$page_ids    = array_slice( $order_ids, $offset, $per_page );
		$orders      = array();

		foreach ( $page_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$orders[] = $order;
			}
		}

		return array(
			'orders'       => $orders,
			'total_pages'  => $total_pages,
			'current_page' => $paged,
			'search'       => $search,
			'status'       => $status,
		);
	}

	/**
	 * Query restricted order IDs using Woo order-product lookup table.
	 *
	 * @param array<int,int> $product_ids Product IDs.
	 * @param string         $search Search term.
	 * @param string         $status Status slug.
	 * @return array<int,int>
	 */
	private function get_restricted_order_ids( array $product_ids, string $search, string $status ): array {
		global $wpdb;

		$product_ids = array_values( array_filter( array_map( 'absint', $product_ids ) ) );
		if ( empty( $product_ids ) ) {
			return array();
		}

		$lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
		$hpos_table   = $wpdb->prefix . 'wc_orders';
		$post_table   = $wpdb->posts;

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
		$params       = $product_ids;
		$status_key   = $status ? 'wc-' . $status : '';
		$search_like  = '' !== $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		if ( $this->table_exists( $hpos_table ) ) {
			$sql = "
				SELECT DISTINCT o.id
				FROM {$lookup_table} opl
				INNER JOIN {$hpos_table} o ON o.id = opl.order_id
				WHERE opl.product_id IN ({$placeholders})
					AND o.type = 'shop_order'
			";

			if ( $status_key ) {
				$sql      .= ' AND o.status = %s';
				$params[] = $status_key;
			}

			if ( $search_like ) {
				$sql      .= ' AND CAST(o.id AS CHAR) LIKE %s';
				$params[] = $search_like;
			}

			$sql .= ' ORDER BY o.date_created_gmt DESC, o.id DESC';

			$prepared = $wpdb->prepare( $sql, $params );
			if ( ! $prepared ) {
				return array();
			}

			$ids = array_values( array_filter( array_map( 'absint', (array) $wpdb->get_col( $prepared ) ) ) );
			return $this->merge_with_recent_fallback_order_ids( $ids, $search, $status );
		}

		$sql = "
			SELECT DISTINCT p.ID
			FROM {$lookup_table} opl
			INNER JOIN {$post_table} p ON p.ID = opl.order_id
			WHERE opl.product_id IN ({$placeholders})
				AND p.post_type = 'shop_order'
		";

		if ( $status_key ) {
			$sql      .= ' AND p.post_status = %s';
			$params[] = $status_key;
		}

		if ( $search_like ) {
			$sql      .= ' AND CAST(p.ID AS CHAR) LIKE %s';
			$params[] = $search_like;
		}

		$sql .= ' ORDER BY p.post_date_gmt DESC, p.ID DESC';

		$prepared = $wpdb->prepare( $sql, $params );
		if ( ! $prepared ) {
			return array();
		}

		$ids = array_values( array_filter( array_map( 'absint', (array) $wpdb->get_col( $prepared ) ) ) );
		return $this->merge_with_recent_fallback_order_ids( $ids, $search, $status );
	}

	/**
	 * Merge SQL result with small recent-order fallback for lookup-sync edge cases.
	 *
	 * @param array<int,int> $query_ids IDs from SQL query.
	 * @param string         $search Search term.
	 * @param string         $status Status slug.
	 * @return array<int,int>
	 */
	private function merge_with_recent_fallback_order_ids( array $query_ids, string $search, string $status ): array {
		$fallback_ids = $this->get_recent_visible_order_ids_fallback( $search, $status );
		$merged       = array_values( array_unique( array_merge( $query_ids, $fallback_ids ) ) );
		rsort( $merged, SORT_NUMERIC );
		return $merged;
	}

	/**
	 * Fallback check across recent orders to avoid lookup table timing gaps.
	 *
	 * @param string $search Search term.
	 * @param string $status Status slug.
	 * @return array<int,int>
	 */
	private function get_recent_visible_order_ids_fallback( string $search, string $status ): array {
		$args = array(
			'paginate' => false,
			'limit'    => 100,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		if ( '' !== $status ) {
			$args['status'] = array( $status );
		}

		$orders = wc_get_orders( $args );
		if ( ! is_array( $orders ) ) {
			return array();
		}

		$ids = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$order_id = (int) $order->get_id();
			if ( '' !== $search && false === strpos( (string) $order_id, $search ) ) {
				continue;
			}

			if ( WB_FSM_Permissions::current_user_can_view_order( $order ) ) {
				$ids[] = $order_id;
			}
		}

		return $ids;
	}

	/**
	 * Accessible products for restricted partner mode.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,int>
	 */
	private function get_accessible_product_ids( int $user_id ): array {
		global $wpdb;

		$query = "
			SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = '_wb_fsm_assigned_user_id'
			WHERE p.post_type = 'product'
				AND p.post_status IN ('publish', 'draft', 'pending', 'private')
				AND (
					( pm.meta_value <> '' AND CAST(pm.meta_value AS UNSIGNED) = %d )
					OR
					(
						( pm.meta_id IS NULL OR pm.meta_value = '' OR CAST(pm.meta_value AS UNSIGNED) = 0 )
						AND p.post_author = %d
					)
				)
		";

		$ids = $wpdb->get_col( $wpdb->prepare( $query, $user_id, $user_id ) );
		return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	}

	/**
	 * Check whether DB table exists.
	 *
	 * @param string $table_name Table name.
	 * @return bool
	 */
	private function table_exists( string $table_name ): bool {
		global $wpdb;

		$check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		return is_string( $check ) && $check === $table_name;
	}
}
