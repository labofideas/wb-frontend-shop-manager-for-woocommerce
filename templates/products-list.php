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
		<button class="wbfsm-btn wbfsm-btn-secondary" type="submit"><?php esc_html_e( 'Filter', 'wb-frontend-shop-manager-for-woocommerce' ); ?></button>
	</form>

	<table class="wbfsm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Image', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Name', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'SKU', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Price', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Stock', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $products ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No products found.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $products as $product ) : ?>
					<?php
					$status_key   = (string) $product->get_status();
					$status_label = WB_FSM_Helpers::product_statuses()[ $status_key ] ?? ucfirst( $status_key );
					?>
					<tr>
						<td><?php echo wp_kses_post( $product->get_image( array( 50, 50 ) ) ); ?></td>
						<td><?php echo esc_html( $product->get_name() ); ?></td>
						<td><?php echo esc_html( (string) $product->get_sku() ); ?></td>
						<td><?php echo wp_kses_post( wc_price( (float) $product->get_price() ) ); ?></td>
						<td><?php echo esc_html( (string) $product->get_stock_quantity() ); ?></td>
						<td><?php echo esc_html( $status_label ); ?></td>
						<td><a class="wbfsm-btn wbfsm-btn-secondary wbfsm-btn-sm" href="<?php echo esc_url( add_query_arg( array( 'wbfsm_tab' => 'products', 'product_id' => $product->get_id() ) ) ); ?>"><?php esc_html_e( 'Edit', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

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
					)
				);
				?>
				<a class="<?php echo (int) $current_page === $page ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) $page ); ?></a>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>
</div>
