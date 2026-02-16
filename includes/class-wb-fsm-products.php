<?php
/**
 * Product management.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_FSM_Products {

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_wbfsm_save_product', array( $this, 'handle_save_product' ) );
		add_action( 'admin_post_wbfsm_bulk_update_products', array( $this, 'handle_bulk_update_products' ) );
	}

	/**
	 * Get products list for frontend table.
	 *
	 * @return array<string,mixed>
	 */
	public function get_products_for_current_user(): array {
		$paged        = max( 1, absint( wp_unslash( $_GET['wbfsm_page'] ?? 1 ) ) );
		$search       = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$stock_status = sanitize_key( wp_unslash( $_GET['stock_status'] ?? '' ) );
		$category     = absint( wp_unslash( $_GET['category'] ?? 0 ) );
		$product_type = sanitize_key( wp_unslash( $_GET['product_type'] ?? '' ) );

		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 20,
			'paged'          => $paged,
			's'              => $search,
		);

		$settings = WB_FSM_Helpers::get_settings();
		$restricted_mode = WB_FSM_Permissions::is_partner_user() && 'restricted' === $settings['ownership_mode'];
		$meta_query      = array();

		if ( $stock_status ) {
			$meta_query[] = array(
				'key'   => '_stock_status',
				'value' => $stock_status,
			);
		}

		$tax_query = array();
		if ( $category > 0 ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $category,
			);
		}

		if ( in_array( $product_type, array( 'simple', 'variable' ), true ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $product_type,
			);
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		if ( $restricted_mode ) {
			$accessible_ids = $this->get_accessible_product_ids( get_current_user_id() );
			if ( empty( $accessible_ids ) ) {
				return array(
					'products'     => array(),
					'total_pages'  => 1,
					'current_page' => $paged,
						'search'       => $search,
						'stock_status' => $stock_status,
						'category'     => $category,
						'product_type' => $product_type,
					);
				}

			$args['post__in'] = $accessible_ids;
			$args['orderby']  = 'date';
			$args['order']    = 'DESC';
		}

		$query = new WP_Query( $args );

		$products = array();
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}

			$products[] = $product;
		}

		return array(
			'products'     => $products,
			'total_pages'  => (int) $query->max_num_pages,
			'current_page' => $paged,
			'search'       => $search,
			'stock_status' => $stock_status,
			'category'     => $category,
			'product_type' => $product_type,
		);
	}

	/**
	 * Handle create/update.
	 *
	 * @return void
	 */
	public function handle_save_product(): void {
		if ( ! WB_FSM_Permissions::current_user_can_access_dashboard() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		check_admin_referer( 'wbfsm_save_product' );

		$product_id = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
		$is_new     = $product_id <= 0;

		if ( ! $is_new && ! WB_FSM_Permissions::current_user_can_manage_product( $product_id ) ) {
			wp_die( esc_html__( 'You cannot edit this product.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		$settings = WB_FSM_Helpers::get_settings();
		$editable = array_map( 'sanitize_key', (array) $settings['editable_fields'] );
		$allowed_statuses = array_keys( WB_FSM_Helpers::product_statuses() );
		$posted_status    = sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) );
		if ( ! in_array( $posted_status, $allowed_statuses, true ) ) {
			$posted_status = 'draft';
		}
		$product_type = sanitize_key( wp_unslash( $_POST['product_type'] ?? 'simple' ) );
		if ( ! in_array( $product_type, array( 'simple', 'variable' ), true ) ) {
			$product_type = 'simple';
		}

		$before_data = array();
		if ( ! $is_new ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$before_data = $this->snapshot_product( $product );
				$product_type = $product->is_type( 'variable' ) ? 'variable' : 'simple';
			}
		}

		if ( WB_FSM_Approvals::approval_required_for_current_user() ) {
			$payload    = $this->collect_payload_from_request( $product_id, $product_type, $editable, $posted_status );
			$request_id = WB_FSM_Approvals::create_request( $product_id, $before_data, $payload );
			if ( $request_id <= 0 ) {
				wp_die( esc_html__( 'Unable to submit request for review.', 'wb-frontend-shop-manager-for-woocommerce' ) );
			}

			$redirect = add_query_arg(
				array(
					'wbfsm_tab' => 'products',
					'wbfsm_msg' => 'product_submitted',
				),
				wp_get_referer() ?: home_url( '/' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$post_data = array(
			'post_type'    => 'product',
			'post_title'   => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'post_content' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'post_status'  => $posted_status,
		);

		if ( ! in_array( 'name', $editable, true ) ) {
			unset( $post_data['post_title'] );
		}

		if ( ! in_array( 'description', $editable, true ) ) {
			unset( $post_data['post_content'] );
		}

		if ( ! in_array( 'status', $editable, true ) ) {
			unset( $post_data['post_status'] );
		}

		if ( ! $is_new ) {
			$post_data['ID'] = $product_id;
			$product_id      = (int) wp_update_post( $post_data, true );
		} else {
			$post_data['post_author'] = get_current_user_id();
			$product_id               = (int) wp_insert_post( $post_data, true );
		}

		if ( ! $product_id || is_wp_error( $product_id ) ) {
			wp_die( esc_html__( 'Unable to save product.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		wp_set_object_terms( $product_id, $product_type, 'product_type', false );
		wc_delete_product_transients( $product_id );
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_die( esc_html__( 'Product model unavailable.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		if ( $is_new ) {
			$product->set_props(
				array(
					'name'        => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
					'status'      => $posted_status,
					'catalog_visibility' => 'visible',
				)
			);
		}

		if ( in_array( 'sku', $editable, true ) ) {
			$product->set_sku( sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) ) );
		}

		if ( in_array( 'regular_price', $editable, true ) ) {
			$product->set_regular_price( $product->is_type( 'variable' ) ? '' : wc_format_decimal( wp_unslash( $_POST['regular_price'] ?? '' ) ) );
		}

		if ( in_array( 'sale_price', $editable, true ) ) {
			$product->set_sale_price( $product->is_type( 'variable' ) ? '' : wc_format_decimal( wp_unslash( $_POST['sale_price'] ?? '' ) ) );
		}

		if ( in_array( 'stock_quantity', $editable, true ) ) {
			if ( $product->is_type( 'variable' ) ) {
				$product->set_manage_stock( false );
				$product->set_stock_quantity( null );
				$product->set_stock_status( 'instock' );
			} else {
				$qty = wc_stock_amount( wp_unslash( $_POST['stock_quantity'] ?? 0 ) );
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $qty );
				$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			}
		}

		$product->save();
		if ( $product->is_type( 'variable' ) ) {
			$this->update_variations_from_request( $product_id, $editable );
			$this->maybe_generate_variations_from_blueprint( $product_id );
		}

		$this->handle_image_upload( $product_id, $product );

		if ( current_user_can( 'manage_options' ) && array_key_exists( 'assigned_user_id', $_POST ) ) {
			$assigned_user_id = absint( wp_unslash( $_POST['assigned_user_id'] ) );
			if ( $assigned_user_id > 0 ) {
				update_post_meta( $product_id, '_wb_fsm_assigned_user_id', $assigned_user_id );
			} else {
				delete_post_meta( $product_id, '_wb_fsm_assigned_user_id' );
			}
		}

		$after_data = $this->snapshot_product( $product );
		WB_FSM_Audit_Logs::add_log(
			$is_new ? 'product_create' : 'product_edit',
			$product_id,
			'product',
			$before_data,
			$after_data
		);

		$redirect = add_query_arg(
			array(
				'wbfsm_tab' => 'products',
				'wbfsm_msg' => $is_new ? 'product_created' : 'product_updated',
			),
			wp_get_referer() ?: home_url( '/' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle bulk updates from product table.
	 *
	 * @return void
	 */
	public function handle_bulk_update_products(): void {
		if ( ! WB_FSM_Permissions::current_user_can_access_dashboard() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		check_admin_referer( 'wbfsm_bulk_update_products' );

		$settings       = WB_FSM_Helpers::get_settings();
		$editable       = array_map( 'sanitize_key', (array) $settings['editable_fields'] );
		$product_ids_in = array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ?? array() ) );
		$product_ids    = array_values( array_unique( array_filter( $product_ids_in ) ) );
		$updated_count  = 0;

		$allowed_statuses = array_keys( WB_FSM_Helpers::product_statuses() );
		$bulk_status      = sanitize_key( wp_unslash( $_POST['bulk_status'] ?? '' ) );
		if ( ! in_array( $bulk_status, $allowed_statuses, true ) ) {
			$bulk_status = '';
		}

		$has_stock_input = '' !== trim( (string) wp_unslash( $_POST['bulk_stock_quantity'] ?? '' ) );
		$stock_quantity  = $has_stock_input ? wc_stock_amount( wp_unslash( $_POST['bulk_stock_quantity'] ) ) : null;

		foreach ( $product_ids as $product_id ) {
			if ( ! WB_FSM_Permissions::current_user_can_manage_product( $product_id ) ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$before = $this->snapshot_product( $product );
			$dirty  = false;

			if ( '' !== $bulk_status && in_array( 'status', $editable, true ) ) {
				$product->set_status( $bulk_status );
				$dirty = true;
			}

			if ( $has_stock_input && in_array( 'stock_quantity', $editable, true ) ) {
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $variation_id ) {
						$variation = wc_get_product( $variation_id );
						if ( ! $variation instanceof WC_Product_Variation ) {
							continue;
						}
						$variation->set_manage_stock( true );
						$variation->set_stock_quantity( $stock_quantity );
						$variation->set_stock_status( $stock_quantity > 0 ? 'instock' : 'outofstock' );
						$variation->save();
					}
				} else {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( $stock_quantity );
					$product->set_stock_status( $stock_quantity > 0 ? 'instock' : 'outofstock' );
				}
				$dirty = true;
			}

			if ( ! $dirty ) {
				continue;
			}

			$product->save();
			$updated_count++;

			WB_FSM_Audit_Logs::add_log(
				'product_bulk_update',
				$product_id,
				'product',
				$before,
				$this->snapshot_product( wc_get_product( $product_id ) ?: $product )
			);
		}

		$redirect = add_query_arg(
			array(
				'wbfsm_tab'        => 'products',
				'wbfsm_msg'        => 'bulk_updated',
				'wbfsm_bulk_count' => $updated_count,
			),
			wp_get_referer() ?: home_url( '/' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Product snapshot for logs.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	private function snapshot_product( WC_Product $product ): array {
		$variations = array();
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation instanceof WC_Product_Variation ) {
					continue;
				}
				$variations[] = array(
					'id'            => $variation->get_id(),
					'sku'           => $variation->get_sku(),
					'regular_price' => $variation->get_regular_price(),
					'sale_price'    => $variation->get_sale_price(),
					'stock_qty'     => $variation->get_stock_quantity(),
					'status'        => $variation->get_status(),
				);
			}
		}

		return array(
			'id'            => $product->get_id(),
			'name'          => $product->get_name(),
			'type'          => $product->get_type(),
			'sku'           => $product->get_sku(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
			'stock_qty'     => $product->get_stock_quantity(),
			'status'        => $product->get_status(),
			'image_id'      => $product->get_image_id(),
			'variations'    => $variations,
		);
	}

	/**
	 * Ownership-aware product IDs for restricted mode.
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
	 * Handle featured image upload in frontend form.
	 *
	 * @param int        $product_id Product ID.
	 * @param WC_Product $product Product object.
	 * @return void
	 */
	private function handle_image_upload( int $product_id, WC_Product $product ): void {
		if ( empty( $_FILES['product_image']['name'] ) || ! isset( $_FILES['product_image']['error'] ) ) {
			return;
		}

		if ( UPLOAD_ERR_OK !== (int) $_FILES['product_image']['error'] ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'product_image', $product_id );
		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		$product->set_image_id( absint( $attachment_id ) );
		$product->save();
	}

	/**
	 * Update existing variations from frontend request.
	 *
	 * @param int               $product_id Parent product ID.
	 * @param array<int,string> $editable   Editable field keys.
	 * @return void
	 */
	private function update_variations_from_request( int $product_id, array $editable ): void {
		$variation_ids = array_map( 'absint', (array) wp_unslash( $_POST['variation_ids'] ?? array() ) );
		$variation_ids = array_values( array_unique( array_filter( $variation_ids ) ) );
		if ( empty( $variation_ids ) ) {
			return;
		}

		$variation_sku   = (array) wp_unslash( $_POST['variation_sku'] ?? array() );
		$variation_price = (array) wp_unslash( $_POST['variation_regular_price'] ?? array() );
		$variation_sale  = (array) wp_unslash( $_POST['variation_sale_price'] ?? array() );
		$variation_stock = (array) wp_unslash( $_POST['variation_stock_quantity'] ?? array() );
		$variation_state = (array) wp_unslash( $_POST['variation_enabled'] ?? array() );

		foreach ( $variation_ids as $variation_id ) {
			if ( $variation_id <= 0 || ! WB_FSM_Permissions::current_user_can_manage_product( $variation_id ) ) {
				continue;
			}

			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation || (int) $variation->get_parent_id() !== $product_id ) {
				continue;
			}

			if ( in_array( 'sku', $editable, true ) ) {
				$variation->set_sku( sanitize_text_field( (string) ( $variation_sku[ $variation_id ] ?? '' ) ) );
			}

			if ( in_array( 'regular_price', $editable, true ) ) {
				$variation->set_regular_price( wc_format_decimal( $variation_price[ $variation_id ] ?? '' ) );
			}

			if ( in_array( 'sale_price', $editable, true ) ) {
				$variation->set_sale_price( wc_format_decimal( $variation_sale[ $variation_id ] ?? '' ) );
			}

			if ( in_array( 'stock_quantity', $editable, true ) ) {
				$qty = wc_stock_amount( $variation_stock[ $variation_id ] ?? 0 );
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( $qty );
				$variation->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			}

			if ( in_array( 'status', $editable, true ) ) {
				$variation->set_status( ! empty( $variation_state[ $variation_id ] ) ? 'publish' : 'private' );
			}

			$variation->save();
		}
	}

	/**
	 * Collect sanitized payload for approval request.
	 *
	 * @param int               $product_id Product ID.
	 * @param string            $product_type Product type.
	 * @param array<int,string> $editable Editable fields.
	 * @param string            $posted_status Status.
	 * @return array<string,mixed>
	 */
	private function collect_payload_from_request( int $product_id, string $product_type, array $editable, string $posted_status ): array {
		$payload = array(
			'product_id'     => $product_id,
			'product_type'   => $product_type,
			'name'           => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'description'    => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'status'         => $posted_status,
			'sku'            => sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) ),
			'regular_price'  => wc_format_decimal( wp_unslash( $_POST['regular_price'] ?? '' ) ),
			'sale_price'     => wc_format_decimal( wp_unslash( $_POST['sale_price'] ?? '' ) ),
			'stock_quantity' => wc_stock_amount( wp_unslash( $_POST['stock_quantity'] ?? 0 ) ),
		);

		if ( ! in_array( 'name', $editable, true ) ) {
			unset( $payload['name'] );
		}
		if ( ! in_array( 'description', $editable, true ) ) {
			unset( $payload['description'] );
		}
		if ( ! in_array( 'status', $editable, true ) ) {
			unset( $payload['status'] );
		}
		if ( ! in_array( 'sku', $editable, true ) ) {
			unset( $payload['sku'] );
		}
		if ( ! in_array( 'regular_price', $editable, true ) ) {
			unset( $payload['regular_price'] );
		}
		if ( ! in_array( 'sale_price', $editable, true ) ) {
			unset( $payload['sale_price'] );
		}
		if ( ! in_array( 'stock_quantity', $editable, true ) ) {
			unset( $payload['stock_quantity'] );
		}

		if ( 'variable' === $product_type ) {
			$payload['variations'] = array();
			$variation_ids         = array_map( 'absint', (array) wp_unslash( $_POST['variation_ids'] ?? array() ) );
			$variation_ids         = array_values( array_unique( array_filter( $variation_ids ) ) );
			$variation_sku         = (array) wp_unslash( $_POST['variation_sku'] ?? array() );
			$variation_price       = (array) wp_unslash( $_POST['variation_regular_price'] ?? array() );
			$variation_sale        = (array) wp_unslash( $_POST['variation_sale_price'] ?? array() );
			$variation_stock       = (array) wp_unslash( $_POST['variation_stock_quantity'] ?? array() );
			$variation_state       = (array) wp_unslash( $_POST['variation_enabled'] ?? array() );

			foreach ( $variation_ids as $variation_id ) {
				$payload['variations'][] = array(
					'id'             => $variation_id,
					'sku'            => sanitize_text_field( (string) ( $variation_sku[ $variation_id ] ?? '' ) ),
					'regular_price'  => wc_format_decimal( $variation_price[ $variation_id ] ?? '' ),
					'sale_price'     => wc_format_decimal( $variation_sale[ $variation_id ] ?? '' ),
					'stock_quantity' => wc_stock_amount( $variation_stock[ $variation_id ] ?? 0 ),
					'enabled'        => ! empty( $variation_state[ $variation_id ] ) ? 1 : 0,
				);
			}

			$payload['variation_blueprint'] = $this->collect_variation_blueprint();
		}

		return $payload;
	}

	/**
	 * Parse variation blueprint attributes from request.
	 *
	 * @return array<int,array{name:string,values:array<int,string>}>
	 */
	private function collect_variation_blueprint(): array {
		$names  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['variation_attr_name'] ?? array() ) );
		$values = (array) wp_unslash( $_POST['variation_attr_values'] ?? array() );

		$blueprint = array();
		foreach ( $names as $idx => $name ) {
			$name = trim( $name );
			if ( '' === $name ) {
				continue;
			}

			$row_values_raw = sanitize_text_field( (string) ( $values[ $idx ] ?? '' ) );
			$row_values     = array_values(
				array_filter(
					array_map(
						static fn( string $item ): string => sanitize_title( trim( $item ) ),
						preg_split( '/\s*,\s*/', $row_values_raw )
					)
				)
			);

			if ( empty( $row_values ) ) {
				continue;
			}

			$blueprint[] = array(
				'name'   => $name,
				'values' => array_values( array_unique( $row_values ) ),
			);
		}

		return $blueprint;
	}

	/**
	 * Create missing variations from posted attribute blueprint.
	 *
	 * @param int $product_id Parent product ID.
	 * @return void
	 */
	private function maybe_generate_variations_from_blueprint( int $product_id ): void {
		$blueprint = $this->collect_variation_blueprint();
		if ( empty( $blueprint ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		$this->apply_variation_blueprint( $product_id, $blueprint );
	}

	/**
	 * Apply attribute blueprint to product and generate combinations.
	 *
	 * @param int                                                   $product_id Product ID.
	 * @param array<int,array{name:string,values:array<int,string>}> $blueprint Attribute blueprint.
	 * @return void
	 */
	public function apply_variation_blueprint( int $product_id, array $blueprint ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		$attributes = array();
		foreach ( $blueprint as $position => $row ) {
			$taxonomy_name = sanitize_title( (string) $row['name'] );
			if ( '' === $taxonomy_name ) {
				continue;
			}

			$attribute = new WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_name( $taxonomy_name );
			$attribute->set_options( array_values( array_unique( array_map( 'sanitize_title', (array) $row['values'] ) ) ) );
			$attribute->set_position( $position );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$attributes[] = $attribute;
		}

		if ( empty( $attributes ) ) {
			return;
		}

		$product->set_attributes( $attributes );
		$product->save();

		$option_sets = array();
		foreach ( $attributes as $attribute ) {
			$values = array_values( array_filter( array_map( 'sanitize_title', (array) $attribute->get_options() ) ) );
			if ( empty( $values ) ) {
				return;
			}

			$option_sets[] = array(
				'name'   => 'attribute_' . sanitize_title( $attribute->get_name() ),
				'values' => $values,
			);
		}

		$combinations = $this->build_attribute_combinations( $option_sets );
		if ( empty( $combinations ) ) {
			return;
		}

		$existing = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}
			$key              = wp_json_encode( $variation->get_attributes() );
			$existing[ $key ] = true;
		}

		foreach ( $combinations as $combination ) {
			$key = wp_json_encode( $combination );
			if ( isset( $existing[ $key ] ) ) {
				continue;
			}

			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( $combination );
			$variation->set_status( 'publish' );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( 0 );
			$variation->set_stock_status( 'outofstock' );
			$variation->save();
		}
	}

	/**
	 * Cartesian combinations for attribute/value sets.
	 *
	 * @param array<int,array{name:string,values:array<int,string>}> $sets Sets.
	 * @return array<int,array<string,string>>
	 */
	private function build_attribute_combinations( array $sets ): array {
		$combinations = array( array() );
		foreach ( $sets as $set ) {
			$tmp = array();
			foreach ( $combinations as $base ) {
				foreach ( $set['values'] as $value ) {
					$row                = $base;
					$row[ $set['name'] ] = $value;
					$tmp[]              = $row;
				}
			}
			$combinations = $tmp;
		}

		return $combinations;
	}
}
