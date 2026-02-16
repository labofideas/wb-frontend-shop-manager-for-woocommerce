<?php
/**
 * Plugin loader.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WBFSM_PATH . 'includes/class-wb-fsm-helpers.php';
require_once WBFSM_PATH . 'includes/class-wb-fsm-permissions.php';
require_once WBFSM_PATH . 'includes/class-wb-fsm-audit-logs.php';
require_once WBFSM_PATH . 'includes/class-wb-fsm-products.php';
require_once WBFSM_PATH . 'includes/class-wb-fsm-orders.php';
require_once WBFSM_PATH . 'includes/class-wb-fsm-settings.php';
require_once WBFSM_PATH . 'includes/class-wb-fsm-dashboard.php';
require_once WBFSM_PATH . 'includes/class-wb-fsm-approvals.php';

class WB_FSM_Loader {

	/**
	 * Singleton.
	 *
	 * @var WB_FSM_Loader|null
	 */
	private static ?WB_FSM_Loader $instance = null;

	/**
	 * Products.
	 *
	 * @var WB_FSM_Products
	 */
	private WB_FSM_Products $products;

	/**
	 * Orders.
	 *
	 * @var WB_FSM_Orders
	 */
	private WB_FSM_Orders $orders;

	/**
	 * Settings.
	 *
	 * @var WB_FSM_Settings
	 */
	private WB_FSM_Settings $settings;

	/**
	 * Dashboard.
	 *
	 * @var WB_FSM_Dashboard
	 */
	private WB_FSM_Dashboard $dashboard;

	/**
	 * Approvals.
	 *
	 * @var WB_FSM_Approvals
	 */
	private WB_FSM_Approvals $approvals;

	/**
	 * Get singleton.
	 *
	 * @return WB_FSM_Loader
	 */
	public static function instance(): WB_FSM_Loader {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct.
	 */
	private function __construct() {
		$this->products  = new WB_FSM_Products();
		$this->orders    = new WB_FSM_Orders();
		$this->settings  = new WB_FSM_Settings();
		$this->dashboard = new WB_FSM_Dashboard( $this->products, $this->orders );
		$this->approvals = new WB_FSM_Approvals();

		$this->products->init();
		$this->orders->init();
		$this->settings->init();
		$this->dashboard->init();
		$this->approvals->init();

		add_action( 'admin_init', array( $this, 'maybe_block_wp_admin' ), 1 );
		add_action( 'init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Ensure db updates.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed = (string) get_option( 'wbfsm_version', '' );
		if ( version_compare( $installed, WBFSM_VERSION, '<' ) ) {
			WB_FSM_Audit_Logs::create_table();
			update_option( 'wbfsm_version', WBFSM_VERSION );
		}
	}

	/**
	 * Redirect partner users away from wp-admin.
	 *
	 * @return void
	 */
	public function maybe_block_wp_admin(): void {
		if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
			return;
		}

		// Allow frontend form handlers to post through admin-post.php.
			$pagenow = $GLOBALS['pagenow'] ?? '';
			if ( 'admin-post.php' === $pagenow ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Action routing only, nonce validated by handlers.
				$action = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) );
				if ( 0 === strpos( $action, 'wbfsm_' ) ) {
					return;
				}
			}

		$settings = WB_FSM_Helpers::get_settings();
		if ( empty( $settings['block_wp_admin'] ) ) {
			return;
		}

		if ( ! WB_FSM_Permissions::is_partner_user() ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen detection.
			$screen_page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		if ( 'wbfsm-settings' === $screen_page && current_user_can( 'manage_options' ) ) {
			return;
		}

		$dashboard_url = $this->get_dashboard_url();
		if ( $dashboard_url ) {
			wp_safe_redirect( $dashboard_url );
			exit;
		}

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Find published dashboard page by shortcode.
	 *
	 * @return string
	 */
	private function get_dashboard_url(): string {
		return WB_FSM_Helpers::get_dashboard_url();
	}
}
