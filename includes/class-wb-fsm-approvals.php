<?php
/**
 * Product change approval workflow.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_FSM_Approvals {

	/**
	 * Request post type key.
	 */
	private const POST_TYPE = 'wbfsm_change_req';

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_post_wbfsm_review_request', array( $this, 'handle_review_request' ) );
	}

	/**
	 * Register internal request post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'FSM Change Requests', 'wb-frontend-shop-manager-for-woocommerce' ),
				'public'          => false,
				'show_ui'         => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Check if approval mode is enabled and applies to user.
	 *
	 * @return bool
	 */
	public static function approval_required_for_current_user(): bool {
		$settings = WB_FSM_Helpers::get_settings();
		return ! empty( $settings['require_product_approval'] ) && ! current_user_can( 'manage_options' ) && WB_FSM_Permissions::is_partner_user();
	}

	/**
	 * Create a pending request for admin review.
	 *
	 * @param int                 $product_id Existing product ID or 0 for new.
	 * @param array<string,mixed> $before_data Snapshot before changes.
	 * @param array<string,mixed> $payload Proposed payload.
	 * @return int
	 */
	public static function create_request( int $product_id, array $before_data, array $payload ): int {
		$user_id = get_current_user_id();
		$title   = sprintf(
			/* translators: 1: user id, 2: product id. */
			__( 'Request by user #%1$d for product #%2$d', 'wb-frontend-shop-manager-for-woocommerce' ),
			$user_id,
			$product_id
		);
		if ( $product_id <= 0 ) {
			$title = sprintf(
				/* translators: %d: user id. */
				__( 'New product request by user #%d', 'wb-frontend-shop-manager-for-woocommerce' ),
				$user_id
			);
		}

		$request_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $title,
				'post_author' => $user_id,
			),
			true
		);

		if ( is_wp_error( $request_id ) || $request_id <= 0 ) {
			return 0;
		}

		update_post_meta( $request_id, '_wbfsm_request_status', 'pending' );
		update_post_meta( $request_id, '_wbfsm_target_product_id', $product_id );
		update_post_meta( $request_id, '_wbfsm_before_data', wp_json_encode( $before_data ) );
		update_post_meta( $request_id, '_wbfsm_payload', wp_json_encode( $payload ) );

		WB_FSM_Audit_Logs::add_log(
			'product_change_requested',
			$product_id,
			'product',
			$before_data,
			$payload
		);

		return (int) $request_id;
	}

	/**
	 * Get paginated requests.
	 *
	 * @param int    $page Page number.
	 * @param int    $per_page Rows per page.
	 * @param string $status Request status.
	 * @return array{rows: array<int,array<string,mixed>>, total: int}
	 */
	public static function get_requests( int $page = 1, int $per_page = 10, string $status = 'pending' ): array {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => $per_page,
				'paged'          => max( 1, $page ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required status filter for review queue.
				'meta_query'     => array(
					array(
						'key'   => '_wbfsm_request_status',
						'value' => sanitize_key( $status ),
					),
				),
			)
		);

		$rows = array();
		foreach ( (array) $query->posts as $post ) {
			$request_id = (int) $post->ID;
			$payload    = json_decode( (string) get_post_meta( $request_id, '_wbfsm_payload', true ), true );
			$before     = json_decode( (string) get_post_meta( $request_id, '_wbfsm_before_data', true ), true );
			if ( ! is_array( $payload ) ) {
				$payload = array();
			}
			if ( ! is_array( $before ) ) {
				$before = array();
			}

			$rows[] = array(
				'id'              => $request_id,
				'user_id'         => (int) $post->post_author,
				'product_id'      => (int) get_post_meta( $request_id, '_wbfsm_target_product_id', true ),
				'status'          => (string) get_post_meta( $request_id, '_wbfsm_request_status', true ),
				'payload'         => $payload,
				'before'          => $before,
				'diff'            => self::build_diff( $before, $payload ),
				'created_at_gmt'  => (string) $post->post_date_gmt,
			);
		}

		return array(
			'rows'  => $rows,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Handle admin approve/reject request action.
	 *
	 * @return void
	 */
	public function handle_review_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		check_admin_referer( 'wbfsm_review_request' );

		$request_id = absint( wp_unslash( $_GET['request_id'] ?? 0 ) );
		$decision   = sanitize_key( wp_unslash( $_GET['decision'] ?? '' ) );

		if ( $request_id <= 0 || ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		$ok = self::review_request( $request_id, $decision );
		$state = $ok ? $decision . 'd' : 'failed';

		wp_safe_redirect( add_query_arg( 'wbfsm_request', $state, admin_url( 'admin.php?page=wbfsm-settings' ) ) );
		exit;
	}

	/**
	 * Review and process request.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $decision approve|reject.
	 * @return bool
	 */
	public static function review_request( int $request_id, string $decision ): bool {
		$current_status = (string) get_post_meta( $request_id, '_wbfsm_request_status', true );
		if ( 'pending' !== $current_status ) {
			return false;
		}

		$target_product_id = absint( get_post_meta( $request_id, '_wbfsm_target_product_id', true ) );
		$payload           = json_decode( (string) get_post_meta( $request_id, '_wbfsm_payload', true ), true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		if ( 'reject' === $decision ) {
			update_post_meta( $request_id, '_wbfsm_request_status', 'rejected' );
			WB_FSM_Audit_Logs::add_log( 'product_change_rejected', $target_product_id, 'product', array(), $payload );
			return true;
		}

		$result = self::apply_payload_to_product( $target_product_id, $payload, (int) get_post_field( 'post_author', $request_id ) );
		if ( $result <= 0 ) {
			return false;
		}

		update_post_meta( $request_id, '_wbfsm_target_product_id', $result );
		update_post_meta( $request_id, '_wbfsm_request_status', 'approved' );
		WB_FSM_Audit_Logs::add_log( 'product_change_approved', $result, 'product', array(), $payload );
		return true;
	}

	/**
	 * Apply payload onto product (create or update).
	 *
	 * @param int                 $product_id Target product ID or 0.
	 * @param array<string,mixed> $payload Proposed data.
	 * @param int                 $requester_id Request owner.
	 * @return int
	 */
	private static function apply_payload_to_product( int $product_id, array $payload, int $requester_id ): int {
		$is_new       = $product_id <= 0;
		$product_type = sanitize_key( (string) ( $payload['product_type'] ?? 'simple' ) );
		if ( ! in_array( $product_type, array( 'simple', 'variable' ), true ) ) {
			$product_type = 'simple';
		}

		$post_data = array(
			'post_type'    => 'product',
			'post_title'   => sanitize_text_field( (string) ( $payload['name'] ?? '' ) ),
			'post_content' => wp_kses_post( (string) ( $payload['description'] ?? '' ) ),
			'post_status'  => sanitize_key( (string) ( $payload['status'] ?? 'draft' ) ),
		);
		if ( ! in_array( $post_data['post_status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
			$post_data['post_status'] = 'draft';
		}

		if ( $is_new ) {
			$post_data['post_author'] = max( 1, $requester_id );
			$product_id               = (int) wp_insert_post( $post_data, true );
		} else {
			$post_data['ID'] = $product_id;
			$product_id      = (int) wp_update_post( $post_data, true );
		}

		if ( $product_id <= 0 || is_wp_error( $product_id ) ) {
			return 0;
		}

		wp_set_object_terms( $product_id, $product_type, 'product_type', false );
		wc_delete_product_transients( $product_id );
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0;
		}

		$product->set_sku( sanitize_text_field( (string) ( $payload['sku'] ?? '' ) ) );
		if ( $product->is_type( 'simple' ) ) {
			$product->set_regular_price( wc_format_decimal( $payload['regular_price'] ?? '' ) );
			$product->set_sale_price( wc_format_decimal( $payload['sale_price'] ?? '' ) );
			$qty = wc_stock_amount( $payload['stock_quantity'] ?? 0 );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $qty );
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
		} else {
			$product->set_regular_price( '' );
			$product->set_sale_price( '' );
			$product->set_manage_stock( false );
		}

		$product->save();

		if ( $product->is_type( 'variable' ) && ! empty( $payload['variations'] ) && is_array( $payload['variations'] ) ) {
			foreach ( $payload['variations'] as $row ) {
				$variation_id = absint( $row['id'] ?? 0 );
				$variation    = null;
				if ( $variation_id > 0 ) {
					$variation = wc_get_product( $variation_id );
				}
				if ( ! $variation instanceof WC_Product_Variation || (int) $variation->get_parent_id() !== $product_id ) {
					continue;
				}

				$variation->set_sku( sanitize_text_field( (string) ( $row['sku'] ?? '' ) ) );
				$variation->set_regular_price( wc_format_decimal( $row['regular_price'] ?? '' ) );
				$variation->set_sale_price( wc_format_decimal( $row['sale_price'] ?? '' ) );
				$qty = wc_stock_amount( $row['stock_quantity'] ?? 0 );
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( $qty );
				$variation->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
				$variation->set_status( ! empty( $row['enabled'] ) ? 'publish' : 'private' );
				$variation->save();
			}
		}

		if ( $product->is_type( 'variable' ) && ! empty( $payload['variation_blueprint'] ) && is_array( $payload['variation_blueprint'] ) ) {
			$products_service = new WB_FSM_Products();
			$products_service->apply_variation_blueprint( $product_id, (array) $payload['variation_blueprint'] );
		}

		return $product_id;
	}

	/**
	 * Build compact diff map from before/payload arrays.
	 *
	 * @param array<string,mixed> $before Before.
	 * @param array<string,mixed> $after  Proposed.
	 * @return array<string,array{from:mixed,to:mixed}>
	 */
	private static function build_diff( array $before, array $after ): array {
		$keys = array( 'name', 'description', 'sku', 'regular_price', 'sale_price', 'stock_quantity', 'status', 'product_type', 'variation_blueprint' );
		$diff = array();
		foreach ( $keys as $key ) {
			$from = $before[ $key ] ?? null;
			$to   = $after[ $key ] ?? null;
			if ( (string) $from !== (string) $to ) {
				$diff[ $key ] = array(
					'from' => $from,
					'to'   => $to,
				);
			}
		}

		return $diff;
	}
}
