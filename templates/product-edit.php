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
