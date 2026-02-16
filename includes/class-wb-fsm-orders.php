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
	 * Cache group.
	 */
	private const CACHE_GROUP = 'wbfsm_orders';

	/**
	 * Cache version option key.
	 */
	private const CACHE_VER_OPTION = 'wbfsm_orders_cache_version';

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_wbfsm_update_order_status', array( $this, 'handle_update_order_status' ) );
		add_action( 'woocommerce_update_order', array( $this, 'bump_cache_version' ) );
		add_action( 'save_post_product', array( $this, 'bump_cache_version' ) );
	}

	/**
	 * Get orders for current user.
	 *
	 * @return array<string,mixed>
	 */
	public function get_orders_for_current_user(): array {
		$paged  = max( 1, absint( self::get_query_arg( 'wbfsm_order_page', '1' ) ) );
		$search = sanitize_text_field( self::get_query_arg( 'order_search' ) );
		$status = sanitize_key( self::get_query_arg( 'order_status' ) );

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
		$product_ids = array_values( array_filter( array_map( 'absint', $product_ids ) ) );
		if ( empty( $product_ids ) ) {
			return array();
		}
		$cache_key = $this->build_restricted_cache_key( get_current_user_id(), $product_ids, $search, $status );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return array_values( array_filter( array_map( 'absint', $cached ) ) );
		}

		$search_term = preg_replace( '/[^0-9]/', '', $search );
		$args        = array(
			'paginate' => false,
			'limit'    => 100,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		if ( '' !== $status ) {
			$args['status'] = array( $status );
		}

		$orders = wc_get_orders( $args );
		$ids    = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$order_id = (int) $order->get_id();
			if ( '' !== $search_term && false === strpos( (string) $order_id, $search_term ) ) {
				continue;
			}

			if ( WB_FSM_Permissions::current_user_can_view_order( $order ) ) {
				$ids[] = $order_id;
			}
		}

		$ids = array_values( array_unique( $ids ) );
		wp_cache_set( $cache_key, $ids, self::CACHE_GROUP, 30 );
		return $ids;
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
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"
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
				",
				$user_id,
				$user_id
			)
		);
		return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	}

	/**
	 * Safe GET reader for listing filters.
	 *
	 * @param string $key Query key.
	 * @param string $default Default fallback.
	 * @return string
	 */
	private static function get_query_arg( string $key, string $default = '' ): string {
		$value = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
		return null !== $value ? (string) wp_unslash( $value ) : $default;
	}

	/**
	 * Build deterministic cache key for restricted query result.
	 *
	 * @param int            $user_id User ID.
	 * @param array<int,int> $product_ids Product IDs.
	 * @param string         $search Search input.
	 * @param string         $status Status.
	 * @return string
	 */
	private function build_restricted_cache_key( int $user_id, array $product_ids, string $search, string $status ): string {
		sort( $product_ids, SORT_NUMERIC );
		$version = (int) get_option( self::CACHE_VER_OPTION, 1 );
		return 'restricted:' . $version . ':' . md5( wp_json_encode( array( $user_id, $product_ids, $search, $status ) ) );
	}

	/**
	 * Bump cache version to invalidate previous entries.
	 *
	 * @return void
	 */
	public function bump_cache_version( ...$unused ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$current = (int) get_option( self::CACHE_VER_OPTION, 1 );
		update_option( self::CACHE_VER_OPTION, $current + 1, false );
	}

	/**
	 * Benchmark helper for restricted-order query path.
	 *
	 * @param int $iterations Number of iterations.
	 * @return array<string,mixed>
	 */
	public function benchmark_restricted_query( int $iterations = 10 ): array {
		$iterations   = max( 1, $iterations );
		$user_id      = get_current_user_id();
		$product_ids  = $this->get_accessible_product_ids( $user_id );
		$total_ms     = 0.0;
		$row_counts   = array();

		for ( $i = 0; $i < $iterations; $i++ ) {
			$start = microtime( true );
			$ids   = $this->get_restricted_order_ids( $product_ids, '', '' );
			$total_ms += ( microtime( true ) - $start ) * 1000;
			$row_counts[] = count( $ids );
		}

		return array(
			'iterations'  => $iterations,
			'avg_ms'      => round( $total_ms / $iterations, 2 ),
			'min_rows'    => empty( $row_counts ) ? 0 : min( $row_counts ),
			'max_rows'    => empty( $row_counts ) ? 0 : max( $row_counts ),
			'product_ids' => count( $product_ids ),
		);
	}
}
