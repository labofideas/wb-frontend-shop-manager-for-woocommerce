<?php
/**
 * Order view.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$can_update = ! empty( $settings['allow_order_status_update'] ) || current_user_can( 'manage_options' );
?>
<div class="wbfsm-card">
	<div class="wbfsm-card-head">
		<h2><?php echo esc_html( sprintf( __( 'Order #%d', 'wb-frontend-shop-manager-for-woocommerce' ), $order->get_id() ) ); ?></h2>
		<a class="wbfsm-btn wbfsm-btn-secondary" href="<?php echo esc_url( add_query_arg( array( 'wbfsm_tab' => 'orders', 'order_id' => false ) ) ); ?>"><?php esc_html_e( 'Back to Orders', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
	</div>
	<p><strong><?php esc_html_e( 'Date:', 'wb-frontend-shop-manager-for-woocommerce' ); ?></strong> <?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( wc_date_format() ) : '' ); ?></p>
	<p><strong><?php esc_html_e( 'Status:', 'wb-frontend-shop-manager-for-woocommerce' ); ?></strong> <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></p>
	<p><strong><?php esc_html_e( 'Total:', 'wb-frontend-shop-manager-for-woocommerce' ); ?></strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>

	<h3><?php esc_html_e( 'Items', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h3>
	<table class="wbfsm-table">
		<thead><tr><th><?php esc_html_e( 'Product', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Qty', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Total', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $order->get_items() as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item->get_name() ); ?></td>
					<td><?php echo esc_html( (string) $item->get_quantity() ); ?></td>
					<td><?php echo wp_kses_post( wc_price( (float) $item->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $can_update ) : ?>
		<h3><?php esc_html_e( 'Update Order Status', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbfsm-form">
			<input type="hidden" name="action" value="wbfsm_update_order_status" />
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
			<?php wp_nonce_field( 'wbfsm_update_order_status' ); ?>
			<label>
				<span><?php esc_html_e( 'New Status', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<select name="new_status" required>
					<?php foreach ( wc_get_order_statuses() as $status_key => $status_label ) : ?>
						<?php $status_slug = str_replace( 'wc-', '', $status_key ); ?>
						<option value="<?php echo esc_attr( $status_slug ); ?>" <?php selected( $order->get_status(), $status_slug ); ?>><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Order Note (optional)', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				<textarea name="order_note" rows="4"></textarea>
			</label>
			<button type="submit" class="wbfsm-btn wbfsm-btn-primary"><?php esc_html_e( 'Update Order', 'wb-frontend-shop-manager-for-woocommerce' ); ?></button>
		</form>
	<?php endif; ?>
</div>
