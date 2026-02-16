<?php
/**
 * Frontend dashboard renderer.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_FSM_Dashboard {

	/**
	 * Product service.
	 *
	 * @var WB_FSM_Products
	 */
	private WB_FSM_Products $products;

	/**
	 * Order service.
	 *
	 * @var WB_FSM_Orders
	 */
	private WB_FSM_Orders $orders;

	/**
	 * Constructor.
	 *
	 * @param WB_FSM_Products $products Products service.
	 * @param WB_FSM_Orders   $orders   Orders service.
	 */
	public function __construct( WB_FSM_Products $products, WB_FSM_Orders $orders ) {
		$this->products = $products;
		$this->orders   = $orders;
	}

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'wb_fsm_dashboard', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_style( 'wbfsm-frontend', WBFSM_URL . 'assets/css/frontend.css', array(), WBFSM_VERSION );
		wp_register_script( 'wbfsm-frontend', WBFSM_URL . 'assets/js/frontend.js', array( 'jquery' ), WBFSM_VERSION, true );
	}

	/**
	 * Register Gutenberg block for dashboard embedding.
	 *
	 * @return void
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'wbfsm-dashboard-block-editor',
			WBFSM_URL . 'assets/js/dashboard-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor' ),
			WBFSM_VERSION,
			true
		);

		register_block_type(
			'wbcom/wbfsm-dashboard',
			array(
				'editor_script'   => 'wbfsm-dashboard-block-editor',
				'render_callback' => array( $this, 'render_dashboard_block' ),
			)
		);
	}

	/**
	 * Server-side renderer for Gutenberg block.
	 *
	 * @return string
	 */
	public function render_dashboard_block(): string {
		return $this->render_shortcode();
	}

	/**
	 * Render shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to access the shop dashboard.', 'wb-frontend-shop-manager-for-woocommerce' ) . '</p>';
		}

		if ( ! WB_FSM_Permissions::current_user_can_access_dashboard() ) {
			return '<p>' . esc_html__( 'You are not allowed to access this dashboard.', 'wb-frontend-shop-manager-for-woocommerce' ) . '</p>';
		}

		wp_enqueue_style( 'wbfsm-frontend' );
		wp_enqueue_script( 'wbfsm-frontend' );

		$tab      = sanitize_key( self::get_query_arg( 'wbfsm_tab', 'dashboard' ) );
		$tabs     = array( 'dashboard', 'products', 'orders', 'profile' );
		$tab      = in_array( $tab, $tabs, true ) ? $tab : 'dashboard';
		$user     = wp_get_current_user();
		$view     = '';
		$messages = array(
			'product_created' => __( 'Product created successfully.', 'wb-frontend-shop-manager-for-woocommerce' ),
			'product_updated' => __( 'Product updated successfully.', 'wb-frontend-shop-manager-for-woocommerce' ),
			'order_updated'   => __( 'Order updated successfully.', 'wb-frontend-shop-manager-for-woocommerce' ),
			'product_submitted' => __( 'Product changes submitted for admin approval.', 'wb-frontend-shop-manager-for-woocommerce' ),
		);
		if ( 'bulk_updated' === sanitize_key( self::get_query_arg( 'wbfsm_msg' ) ) ) {
			$bulk_count               = max( 0, absint( self::get_query_arg( 'wbfsm_bulk_count', '0' ) ) );
			$messages['bulk_updated'] = sprintf(
				/* translators: %d: updated products count. */
				_n( '%d product updated.', '%d products updated.', $bulk_count, 'wb-frontend-shop-manager-for-woocommerce' ),
				$bulk_count
			);
		}

		switch ( $tab ) {
			case 'products':
					$product_id = absint( self::get_query_arg( 'product_id', '0' ) );
					if ( $product_id > 0 || '' !== self::get_query_arg( 'new_product' ) ) {
					$product = $product_id ? wc_get_product( $product_id ) : null;
					if ( $product_id > 0 && ( ! $product || ! WB_FSM_Permissions::current_user_can_manage_product( $product_id ) ) ) {
						return '<p>' . esc_html__( 'Product not available.', 'wb-frontend-shop-manager-for-woocommerce' ) . '</p>';
					}
					$view = WB_FSM_Helpers::load_template(
						'product-edit.php',
						array(
							'product'  => $product,
							'settings' => WB_FSM_Helpers::get_settings(),
						)
					);
				} else {
					$view = WB_FSM_Helpers::load_template( 'products-list.php', $this->products->get_products_for_current_user() );
				}
				break;
			case 'orders':
					$order_id = absint( self::get_query_arg( 'order_id', '0' ) );
				if ( $order_id > 0 ) {
					$order = wc_get_order( $order_id );
					if ( ! $order || ! WB_FSM_Permissions::current_user_can_view_order( $order ) ) {
						return '<p>' . esc_html__( 'Order not available.', 'wb-frontend-shop-manager-for-woocommerce' ) . '</p>';
					}

					$view = WB_FSM_Helpers::load_template(
						'order-view.php',
						array(
							'order'    => $order,
							'settings' => WB_FSM_Helpers::get_settings(),
						)
					);
				} else {
					$view = WB_FSM_Helpers::load_template( 'orders-list.php', $this->orders->get_orders_for_current_user() );
				}
				break;
			case 'profile':
				$view = '<div class="wbfsm-card"><h2>' . esc_html__( 'Profile', 'wb-frontend-shop-manager-for-woocommerce' ) . '</h2><p>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</p></div>';
				break;
			case 'dashboard':
			default:
				$view = '<div class="wbfsm-card"><h2>' . esc_html__( 'Overview', 'wb-frontend-shop-manager-for-woocommerce' ) . '</h2><p>' . esc_html__( 'Use the sidebar to manage products, orders, and your profile from the frontend.', 'wb-frontend-shop-manager-for-woocommerce' ) . '</p></div>';
				break;
		}

			$notice_key = sanitize_key( self::get_query_arg( 'wbfsm_msg' ) );
			$notice     = $messages[ $notice_key ] ?? '';

		return WB_FSM_Helpers::load_template(
			'dashboard.php',
			array(
				'tab'    => $tab,
				'view'   => $view,
				'user'   => $user,
				'notice' => $notice,
			)
		);
	}

	/**
	 * Safe GET reader for frontend filters and UI state.
	 *
	 * @param string $key Query key.
	 * @param string $default Default fallback.
	 * @return string
	 */
	private static function get_query_arg( string $key, string $default = '' ): string {
		$value = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );
		return null !== $value ? (string) wp_unslash( $value ) : $default;
	}
}
