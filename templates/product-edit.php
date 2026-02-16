<?php
/**
 * Product edit/create form.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$editable = (array) ( $settings['editable_fields'] ?? array() );
$is_new   = ! $product;
$product_type = ( ! $is_new && $product && $product->is_type( 'variable' ) ) ? 'variable' : 'simple';
$variations = array();
if ( ! $is_new && 'variable' === $product_type ) {
	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if ( $variation instanceof WC_Product_Variation ) {
			$variations[] = $variation;
		}
	}
}
?>
<div class="wbfsm-card">
	<div class="wbfsm-card-head">
		<h2><?php echo esc_html( $is_new ? __( 'Add Product', 'wb-frontend-shop-manager-for-woocommerce' ) : __( 'Edit Product', 'wb-frontend-shop-manager-for-woocommerce' ) ); ?></h2>
		<a class="wbfsm-btn wbfsm-btn-secondary" href="<?php echo esc_url( add_query_arg( array( 'wbfsm_tab' => 'products', 'product_id' => false, 'new_product' => false ) ) ); ?>"><?php esc_html_e( 'Back to Products', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
	</div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbfsm-form" enctype="multipart/form-data">
		<input type="hidden" name="action" value="wbfsm_save_product" />
		<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) ( $product ? $product->get_id() : 0 ) ); ?>" />
		<?php wp_nonce_field( 'wbfsm_save_product' ); ?>

		<label>
			<span><?php esc_html_e( 'Product Type', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
			<?php if ( $is_new ) : ?>
				<select name="product_type">
					<option value="simple"><?php esc_html_e( 'Simple Product', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
					<option value="variable"><?php esc_html_e( 'Variable Product', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
				</select>
			<?php else : ?>
				<input type="text" value="<?php echo esc_attr( ucfirst( $product_type ) ); ?>" readonly />
				<input type="hidden" name="product_type" value="<?php echo esc_attr( $product_type ); ?>" />
			<?php endif; ?>
		</label>

		<?php if ( in_array( 'name', $editable, true ) ) : ?>
			<label>
				<span><?php esc_html_e( 'Name', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<input type="text" name="name" value="<?php echo esc_attr( $product ? $product->get_name() : '' ); ?>" required />
			</label>
		<?php endif; ?>

		<?php if ( in_array( 'sku', $editable, true ) ) : ?>
			<label>
				<span><?php esc_html_e( 'SKU', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<input type="text" name="sku" value="<?php echo esc_attr( $product ? $product->get_sku() : '' ); ?>" />
			</label>
		<?php endif; ?>

		<?php if ( 'simple' === $product_type || $is_new ) : ?>
			<div class="wbfsm-grid">
				<?php if ( in_array( 'regular_price', $editable, true ) ) : ?>
					<label>
						<span><?php esc_html_e( 'Regular Price', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						<input type="number" step="0.01" name="regular_price" value="<?php echo esc_attr( $product ? (string) $product->get_regular_price() : '' ); ?>" />
					</label>
				<?php endif; ?>
				<?php if ( in_array( 'sale_price', $editable, true ) ) : ?>
					<label>
						<span><?php esc_html_e( 'Sale Price', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						<input type="number" step="0.01" name="sale_price" value="<?php echo esc_attr( $product ? (string) $product->get_sale_price() : '' ); ?>" />
					</label>
				<?php endif; ?>
				<?php if ( in_array( 'stock_quantity', $editable, true ) ) : ?>
					<label>
						<span><?php esc_html_e( 'Stock Quantity', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						<input type="number" name="stock_quantity" value="<?php echo esc_attr( $product ? (string) $product->get_stock_quantity() : '0' ); ?>" />
					</label>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( in_array( 'status', $editable, true ) ) : ?>
			<label>
				<span><?php esc_html_e( 'Status', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<select name="status">
					<?php foreach ( WB_FSM_Helpers::product_statuses() as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $product ? $product->get_status() : 'draft', $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>

		<?php if ( in_array( 'description', $editable, true ) ) : ?>
			<label>
				<span><?php esc_html_e( 'Description', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<textarea name="description" rows="6"><?php echo esc_textarea( $product ? $product->get_description() : '' ); ?></textarea>
			</label>
		<?php endif; ?>

		<?php if ( ! $is_new && 'variable' === $product_type ) : ?>
			<div class="wbfsm-variation-block">
				<h3><?php esc_html_e( 'Variations', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h3>
				<?php if ( empty( $variations ) ) : ?>
					<p><?php esc_html_e( 'No variations found. Create variations in WooCommerce first, then manage price and stock here.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p>
				<?php else : ?>
					<table class="wbfsm-table wbfsm-variation-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Variation', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'SKU', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Regular Price', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Sale Price', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Stock Qty', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Enabled', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $variations as $variation ) : ?>
								<?php
								$variation_id = (int) $variation->get_id();
								$attributes   = wc_get_formatted_variation( $variation, true, false, true );
								$label        = $attributes ? wp_strip_all_tags( $attributes ) : '#' . $variation_id;
								?>
								<tr>
									<td>
										<input type="hidden" name="variation_ids[]" value="<?php echo esc_attr( (string) $variation_id ); ?>" />
										<?php echo esc_html( $label ); ?>
									</td>
									<td>
										<input type="text" name="variation_sku[<?php echo esc_attr( (string) $variation_id ); ?>]" value="<?php echo esc_attr( (string) $variation->get_sku() ); ?>" />
									</td>
									<td>
										<input type="number" step="0.01" name="variation_regular_price[<?php echo esc_attr( (string) $variation_id ); ?>]" value="<?php echo esc_attr( (string) $variation->get_regular_price() ); ?>" />
									</td>
									<td>
										<input type="number" step="0.01" name="variation_sale_price[<?php echo esc_attr( (string) $variation_id ); ?>]" value="<?php echo esc_attr( (string) $variation->get_sale_price() ); ?>" />
									</td>
									<td>
										<input type="number" name="variation_stock_quantity[<?php echo esc_attr( (string) $variation_id ); ?>]" value="<?php echo esc_attr( (string) ( $variation->get_stock_quantity() ?? 0 ) ); ?>" />
									</td>
									<td>
										<input type="checkbox" name="variation_enabled[<?php echo esc_attr( (string) $variation_id ); ?>]" value="1" <?php checked( 'publish' === $variation->get_status() ); ?> />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<label>
			<span><?php esc_html_e( 'Product Image', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
			<input type="file" name="product_image" accept="image/*" />
			<?php if ( $product && $product->get_image_id() ) : ?>
				<span class="wbfsm-current-image"><?php echo wp_kses_post( wp_get_attachment_image( $product->get_image_id(), array( 120, 120 ) ) ); ?></span>
			<?php endif; ?>
		</label>

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<label>
				<span><?php esc_html_e( 'Assigned User ID', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<input type="number" name="assigned_user_id" value="<?php echo esc_attr( (string) ( $product ? (int) get_post_meta( $product->get_id(), '_wb_fsm_assigned_user_id', true ) : 0 ) ); ?>" />
			</label>
		<?php endif; ?>

		<button type="submit" class="wbfsm-btn wbfsm-btn-primary"><?php esc_html_e( 'Save Product', 'wb-frontend-shop-manager-for-woocommerce' ); ?></button>
	</form>
</div>
