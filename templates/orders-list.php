<?php
/**
 * Orders list.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wbfsm-card">
	<h2><?php esc_html_e( 'Orders', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h2>
	<form method="get" class="wbfsm-filters">
		<input type="hidden" name="wbfsm_tab" value="orders" />
		<input type="search" name="order_search" value="<?php echo esc_attr( $search ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Search by order ID', 'wb-frontend-shop-manager-for-woocommerce' ); ?>" />
		<select name="order_status">
			<option value=""><?php esc_html_e( 'All Statuses', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
			<?php foreach ( wc_get_order_statuses() as $wbfsm_status_key => $wbfsm_status_label ) : ?>
				<?php $wbfsm_status_slug = str_replace( 'wc-', '', $wbfsm_status_key ); ?>
				<option value="<?php echo esc_attr( $wbfsm_status_slug ); ?>" <?php selected( (string) ( $status ?? '' ), $wbfsm_status_slug ); ?>><?php echo esc_html( $wbfsm_status_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="wbfsm-btn wbfsm-btn-secondary" type="submit"><?php esc_html_e( 'Filter', 'wb-frontend-shop-manager-for-woocommerce' ); ?></button>
	</form>

	<table class="wbfsm-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order ID', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Date', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Total', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wb-frontend-shop-manager-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $orders ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No orders found.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $orders as $wbfsm_order ) : ?>
					<tr>
						<td>#<?php echo esc_html( (string) $wbfsm_order->get_id() ); ?></td>
						<td><?php echo esc_html( $wbfsm_order->get_date_created() ? $wbfsm_order->get_date_created()->date_i18n( wc_date_format() ) : '' ); ?></td>
						<td><?php echo esc_html( (string) $wbfsm_order->get_formatted_billing_full_name() ); ?></td>
						<td><?php echo esc_html( wc_get_order_status_name( $wbfsm_order->get_status() ) ); ?></td>
						<td><?php echo wp_kses_post( $wbfsm_order->get_formatted_order_total() ); ?></td>
						<td><a class="wbfsm-btn wbfsm-btn-secondary wbfsm-btn-sm" href="<?php echo esc_url( add_query_arg( array( 'wbfsm_tab' => 'orders', 'order_id' => $wbfsm_order->get_id() ) ) ); ?>"><?php esc_html_e( 'View', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( (int) $total_pages > 1 ) : ?>
		<nav class="wbfsm-pagination" aria-label="<?php esc_attr_e( 'Orders pagination', 'wb-frontend-shop-manager-for-woocommerce' ); ?>">
			<?php for ( $wbfsm_page = 1; $wbfsm_page <= (int) $total_pages; $wbfsm_page++ ) : ?>
				<?php
				$wbfsm_url = add_query_arg(
					array(
						'wbfsm_tab'        => 'orders',
						'wbfsm_order_page' => $wbfsm_page,
						'order_search'     => $search ?? '',
						'order_status'     => $status ?? '',
					)
				);
				?>
				<a class="<?php echo (int) $current_page === $wbfsm_page ? 'is-active' : ''; ?>" href="<?php echo esc_url( $wbfsm_url ); ?>"><?php echo esc_html( (string) $wbfsm_page ); ?></a>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>
</div>
