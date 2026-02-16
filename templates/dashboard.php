<?php
/**
 * Dashboard shell.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$clean_query_keys = array(
	'wbfsm_tab',
	'product_id',
	'new_product',
	'order_id',
	'wbfsm_msg',
	'wbfsm_page',
	'wbfsm_order_page',
	's',
	'category',
	'stock_status',
	'order_search',
	'order_status',
);

$dashboard_base_url = remove_query_arg( $clean_query_keys );

$dashboard_tab_url = static function ( string $tab ) use ( $dashboard_base_url ): string {
	return (string) add_query_arg( 'wbfsm_tab', $tab, $dashboard_base_url );
};
?>
<div class="wbfsm-wrap">
	<aside class="wbfsm-sidebar">
		<div class="wbfsm-brand"><?php bloginfo( 'name' ); ?></div>
		<nav>
			<a class="<?php echo 'dashboard' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $dashboard_tab_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'Dashboard', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
			<a class="<?php echo 'products' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $dashboard_tab_url( 'products' ) ); ?>"><?php esc_html_e( 'Products', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
			<a class="<?php echo 'orders' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $dashboard_tab_url( 'orders' ) ); ?>"><?php esc_html_e( 'Orders', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
			<a class="<?php echo 'profile' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $dashboard_tab_url( 'profile' ) ); ?>"><?php esc_html_e( 'Profile', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
		</nav>
	</aside>

	<section class="wbfsm-main">
		<header class="wbfsm-header">
			<div>
				<strong><?php bloginfo( 'name' ); ?></strong>
				<div><?php echo esc_html( $user->display_name ?? '' ); ?></div>
			</div>
			<div><a class="wbfsm-btn wbfsm-btn-secondary" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Logout', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a></div>
		</header>

		<?php if ( ! empty( $notice ) ) : ?>
			<div class="wbfsm-notice"><?php echo esc_html( $notice ); ?></div>
		<?php endif; ?>

		<div class="wbfsm-content">
			<?php echo $view; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</section>
</div>
