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

		if ( $category > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category,
				),
			);
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
		$editable = (array) $settings['editable_fields'];
		$allowed_statuses = array_keys( WB_FSM_Helpers::product_statuses() );
		$posted_status    = sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) );
		if ( ! in_array( $posted_status, $allowed_statuses, true ) ) {
			$posted_status = 'draft';
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

		$before_data = array();
		if ( ! $is_new ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$before_data = $this->snapshot_product( $product );
			}
			$post_data['ID'] = $product_id;
			$product_id      = (int) wp_update_post( $post_data, true );
		} else {
			$post_data['post_author'] = get_current_user_id();
			$product_id               = (int) wp_insert_post( $post_data, true );
		}

		if ( ! $product_id || is_wp_error( $product_id ) ) {
			wp_die( esc_html__( 'Unable to save product.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

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
			$product->set_regular_price( wc_format_decimal( wp_unslash( $_POST['regular_price'] ?? '' ) ) );
		}

		if ( in_array( 'sale_price', $editable, true ) ) {
			$product->set_sale_price( wc_format_decimal( wp_unslash( $_POST['sale_price'] ?? '' ) ) );
		}

		if ( in_array( 'stock_quantity', $editable, true ) ) {
			$qty = wc_stock_amount( wp_unslash( $_POST['stock_quantity'] ?? 0 ) );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $qty );
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
		}

		$product->save();
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
	 * Product snapshot for logs.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	private function snapshot_product( WC_Product $product ): array {
		return array(
			'id'            => $product->get_id(),
			'name'          => $product->get_name(),
			'sku'           => $product->get_sku(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
			'stock_qty'     => $product->get_stock_quantity(),
			'status'        => $product->get_status(),
			'image_id'      => $product->get_image_id(),
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
}
