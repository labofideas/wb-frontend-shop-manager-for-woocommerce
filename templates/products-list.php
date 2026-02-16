<?php
/**
 * Products list.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$terms = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);
?>
<div class="wbfsm-card">
	<div class="wbfsm-card-head">
		<h2><?php esc_html_e( 'Products', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h2>
		<a class="wbfsm-btn wbfsm-btn-primary" href="<?php echo esc_url( add_query_arg( array( 'wbfsm_tab' => 'products', 'new_product' => 1 ) ) ); ?>"><?php esc_html_e( 'Add New Product', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
	</div>

	<form method="get" class="wbfsm-filters">
		<input type="hidden" name="wbfsm_tab" value="products" />
		<input type="search" name="s" value="<?php echo esc_attr( $search ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Search products', 'wb-frontend-shop-manager-for-woocommerce' ); ?>" />
		<select name="category">
			<option value="0"><?php esc_html_e( 'All Categories', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( (int) ( $category ?? 0 ), (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="stock_status">
			<option value=""><?php esc_html_e( 'All Stock', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
			<option value="instock" <?php selected( (string) ( $stock_status ?? '' ), 'instock' ); ?>><?php esc_html_e( 'In Stock', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
			<option value="outofstock" <?php selected( (string) ( $stock_status ?? '' ), 'outofstock' ); ?>><?php esc_html_e( 'Out of Stock', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
		</select>
		<select name="product_type">
			<option value=""><?php esc_html_e( 'All Types', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
			<option value="simple" <?php selected( (string) ( $product_type ?? '' ), 'simple' ); ?>><?php esc_html_e( 'Simple', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
			<option value="variable" <?php selected( (string) ( $product_type ?? '' ), 'variable' ); ?>><?php esc_html_e( 'Variable', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
		</select>
		<button class="wbfsm-btn wbfsm-btn-secondary" type="submit"><?php esc_html_e( 'Filter', 'wb-frontend-shop-manager-for-woocommerce' ); ?></button>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbfsm-bulk-form">
		<input type="hidden" name="action" value="wbfsm_bulk_update_products" />
		<?php wp_nonce_field( 'wbfsm_bulk_update_products' ); ?>
		<div class="wbfsm-bulk-bar">
			<label>
				<span><?php esc_html_e( 'Set Status', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<select name="bulk_status">
					<option value=""><?php esc_html_e( 'No status change', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
					<?php foreach ( WB_FSM_Helpers::product_statuses() as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Set Stock Qty', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<input type="number" name="bulk_stock_quantity" placeholder="<?php esc_attr_e( 'Leave blank to skip', 'wb-frontend-shop-manager-for-woocommerce' ); ?>" />
			</label>
			<button class="wbfsm-btn wbfsm-btn-primary" type="submit"><?php esc_html_e( 'Apply to Selected', 'wb-frontend-shop-manager-for-woocommerce' ); ?></button>
		</div>

		<table class="wbfsm-table">
			<thead>
				<tr>
					<th><input type="checkbox" class="wbfsm-select-all" aria-label="<?php esc_attr_e( 'Select all products', 'wb-frontend-shop-manager-for-woocommerce' ); ?>" /></th>
					<th><?php esc_html_e( 'Image', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Name', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Type', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Price', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Stock', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $products ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No products found.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $products as $product ) : ?>
						<?php
						$status_key   = (string) $product->get_status();
						$status_label = WB_FSM_Helpers::product_statuses()[ $status_key ] ?? ucfirst( $status_key );
						$is_variable  = $product->is_type( 'variable' );
						?>
						<tr>
							<td><input type="checkbox" name="product_ids[]" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" class="wbfsm-select-product" /></td>
							<td><?php echo wp_kses_post( $product->get_image( array( 50, 50 ) ) ); ?></td>
							<td><?php echo esc_html( $product->get_name() ); ?></td>
							<td><?php echo esc_html( $is_variable ? __( 'Variable', 'wb-frontend-shop-manager-for-woocommerce' ) : __( 'Simple', 'wb-frontend-shop-manager-for-woocommerce' ) ); ?></td>
							<td><?php echo esc_html( (string) $product->get_sku() ); ?></td>
							<td><?php echo wp_kses_post( wc_price( (float) $product->get_price() ) ); ?></td>
							<td>
								<?php
								if ( $is_variable ) {
									printf(
										/* translators: %d: variation count. */
										esc_html__( '%d variations', 'wb-frontend-shop-manager-for-woocommerce' ),
										(int) count( $product->get_children() )
									);
								} else {
									echo esc_html( (string) $product->get_stock_quantity() );
								}
								?>
							</td>
							<td><?php echo esc_html( $status_label ); ?></td>
							<td><a class="wbfsm-btn wbfsm-btn-secondary wbfsm-btn-sm" href="<?php echo esc_url( add_query_arg( array( 'wbfsm_tab' => 'products', 'product_id' => $product->get_id() ) ) ); ?>"><?php esc_html_e( 'Edit', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</form>

	<?php if ( (int) $total_pages > 1 ) : ?>
		<nav class="wbfsm-pagination" aria-label="<?php esc_attr_e( 'Products pagination', 'wb-frontend-shop-manager-for-woocommerce' ); ?>">
			<?php for ( $page = 1; $page <= (int) $total_pages; $page++ ) : ?>
				<?php
				$url = add_query_arg(
					array(
						'wbfsm_tab'  => 'products',
						'wbfsm_page' => $page,
						's'          => $search ?? '',
						'category'   => $category ?? 0,
						'stock_status' => $stock_status ?? '',
						'product_type' => $product_type ?? '',
					)
				);
				?>
				<a class="<?php echo (int) $current_page === $page ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) $page ); ?></a>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>
</div>
