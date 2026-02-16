<?php
/**
 * Settings page.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_FSM_Settings {

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wbfsm_create_dashboard_page', array( $this, 'handle_create_dashboard_page' ) );
		add_action( 'admin_post_wbfsm_export_logs', array( $this, 'handle_export_logs' ) );
	}

	/**
	 * Enqueue admin styles for settings page.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_wbfsm-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wbfsm-admin-settings',
			WBFSM_URL . 'assets/css/admin-settings.css',
			array(),
			WBFSM_VERSION
		);
	}

	/**
	 * Register menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Frontend Shop Manager', 'wb-frontend-shop-manager-for-woocommerce' ),
			__( 'Frontend Shop Manager', 'wb-frontend-shop-manager-for-woocommerce' ),
			'manage_options',
			'wbfsm-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register option.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wbfsm_settings_group',
			'wbfsm_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$allowed_roles = array_map( 'sanitize_key', (array) ( $input['allowed_roles'] ?? array() ) );
		$editable      = array_map( 'sanitize_key', (array) ( $input['editable_fields'] ?? array() ) );
		$whitelist_raw = sanitize_text_field( (string) ( $input['whitelisted_users_raw'] ?? '' ) );
		$whitelist     = array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', $whitelist_raw ) ) );

		return array(
			'enabled'                   => ! empty( $input['enabled'] ) ? 1 : 0,
			'allowed_roles'             => $allowed_roles,
			'whitelisted_users'         => $whitelist,
			'block_wp_admin'            => ! empty( $input['block_wp_admin'] ) ? 1 : 0,
			'editable_fields'           => $editable,
			'allow_order_status_update' => ! empty( $input['allow_order_status_update'] ) ? 1 : 0,
			'ownership_mode'            => in_array( $input['ownership_mode'] ?? 'shared', array( 'shared', 'restricted' ), true ) ? $input['ownership_mode'] : 'shared',
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		$settings    = WB_FSM_Helpers::get_settings();
		$roles       = wp_roles()->roles;
		$all_fields  = array(
			'name'           => __( 'Name', 'wb-frontend-shop-manager-for-woocommerce' ),
			'sku'            => __( 'SKU', 'wb-frontend-shop-manager-for-woocommerce' ),
			'regular_price'  => __( 'Regular Price', 'wb-frontend-shop-manager-for-woocommerce' ),
			'sale_price'     => __( 'Sale Price', 'wb-frontend-shop-manager-for-woocommerce' ),
			'stock_quantity' => __( 'Stock Quantity', 'wb-frontend-shop-manager-for-woocommerce' ),
			'status'         => __( 'Status', 'wb-frontend-shop-manager-for-woocommerce' ),
			'description'    => __( 'Description', 'wb-frontend-shop-manager-for-woocommerce' ),
		);

		$page      = max( 1, absint( wp_unslash( $_GET['log_page'] ?? 1 ) ) );
		$log_filters = array(
			'search'      => sanitize_text_field( wp_unslash( $_GET['log_search'] ?? '' ) ),
			'action_type' => sanitize_key( wp_unslash( $_GET['log_action'] ?? '' ) ),
			'user_id'     => absint( wp_unslash( $_GET['log_user'] ?? 0 ) ),
			'date_from'   => sanitize_text_field( wp_unslash( $_GET['log_from'] ?? '' ) ),
			'date_to'     => sanitize_text_field( wp_unslash( $_GET['log_to'] ?? '' ) ),
		);
		$log_sort_by    = sanitize_key( wp_unslash( $_GET['log_sort'] ?? 'date' ) );
		$log_sort_order = 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['log_order'] ?? 'desc' ) ) ) ? 'asc' : 'desc';
		$log_data  = WB_FSM_Audit_Logs::get_logs( $page, 20, $log_filters, $log_sort_by, $log_sort_order );
		$log_pages = max( 1, (int) ceil( $log_data['total'] / 20 ) );
		$dashboard_page_id = WB_FSM_Helpers::get_dashboard_page_id();
		$dashboard_url     = WB_FSM_Helpers::get_dashboard_url();
		$setup_state       = sanitize_key( wp_unslash( $_GET['wbfsm_setup'] ?? '' ) );
		$available_actions = WB_FSM_Audit_Logs::get_distinct_action_types();
		$active_filter_chips = array();
		if ( '' !== $log_filters['search'] ) {
			$active_filter_chips[] = sprintf( 'Search: %s', $log_filters['search'] );
		}
		if ( '' !== $log_filters['action_type'] ) {
			$active_filter_chips[] = sprintf( 'Action: %s', $log_filters['action_type'] );
		}
		if ( $log_filters['user_id'] > 0 ) {
			$active_filter_chips[] = sprintf( 'User ID: %d', $log_filters['user_id'] );
		}
		if ( '' !== $log_filters['date_from'] ) {
			$active_filter_chips[] = sprintf( 'From: %s', $log_filters['date_from'] );
		}
		if ( '' !== $log_filters['date_to'] ) {
			$active_filter_chips[] = sprintf( 'To: %s', $log_filters['date_to'] );
		}
		?>
		<div class="wrap wbfsm-admin-wrap">
			<div class="wbfsm-admin-hero">
				<div>
					<h1><?php esc_html_e( 'WB Frontend Shop Manager', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h1>
					<p><?php esc_html_e( 'Configure a premium frontend operations panel for your store partners.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p>
				</div>
				<div class="wbfsm-admin-hero-actions">
					<?php if ( $dashboard_url ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Dashboard Page', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
					<?php endif; ?>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Back to Plugins', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
				</div>
			</div>

			<?php if ( 'created' === $setup_state ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Dashboard page created successfully.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p></div>
			<?php endif; ?>

			<?php if ( 'exists' === $setup_state ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'A dashboard page already exists. Reusing existing page.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p></div>
			<?php endif; ?>

			<?php if ( 'failed' === $setup_state ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Dashboard page could not be created automatically. Please create a page and add [wb_fsm_dashboard] manually.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $dashboard_page_id <= 0 ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Dashboard page is missing. Create a page containing [wb_fsm_dashboard] so partners can access the frontend manager.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wbfsm_create_dashboard_page' ), 'wbfsm_create_dashboard_page' ) ); ?>">
							<?php esc_html_e( 'Create Dashboard Page', 'wb-frontend-shop-manager-for-woocommerce' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="wbfsm-admin-form">
				<?php settings_fields( 'wbfsm_settings_group' ); ?>
				<div class="wbfsm-admin-grid">
					<section class="wbfsm-admin-card">
						<h2><?php esc_html_e( 'Access & Security', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Control who can use the frontend manager and whether wp-admin remains blocked for partner roles.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p>

						<label class="wbfsm-toggle">
							<input type="checkbox" name="wbfsm_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
							<span><?php esc_html_e( 'Enable Frontend Dashboard', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						</label>

						<label class="wbfsm-toggle">
							<input type="checkbox" name="wbfsm_settings[block_wp_admin]" value="1" <?php checked( ! empty( $settings['block_wp_admin'] ) ); ?> />
							<span><?php esc_html_e( 'Block wp-admin for partner roles', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						</label>

						<div class="wbfsm-field">
							<strong><?php esc_html_e( 'Allowed Roles', 'wb-frontend-shop-manager-for-woocommerce' ); ?></strong>
							<div class="wbfsm-check-grid">
								<?php foreach ( $roles as $role_key => $role ) : ?>
									<label><input type="checkbox" name="wbfsm_settings[allowed_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, (array) $settings['allowed_roles'], true ) ); ?> /> <?php echo esc_html( $role['name'] ); ?></label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="wbfsm-field">
							<label for="wbfsm-whitelist"><strong><?php esc_html_e( 'Whitelisted User IDs', 'wb-frontend-shop-manager-for-woocommerce' ); ?></strong></label>
							<input id="wbfsm-whitelist" type="text" class="regular-text" name="wbfsm_settings[whitelisted_users_raw]" value="<?php echo esc_attr( implode( ',', (array) $settings['whitelisted_users'] ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated user IDs with forced dashboard access.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p>
						</div>
					</section>

					<section class="wbfsm-admin-card">
						<h2><?php esc_html_e( 'Ownership Mode', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Choose whether partners work on all products/orders or only their assigned/owned items.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p>
						<div class="wbfsm-radio-stack">
							<label><input type="radio" name="wbfsm_settings[ownership_mode]" value="shared" <?php checked( 'shared', $settings['ownership_mode'] ); ?> /> <?php esc_html_e( 'Shared Store', 'wb-frontend-shop-manager-for-woocommerce' ); ?></label>
							<label><input type="radio" name="wbfsm_settings[ownership_mode]" value="restricted" <?php checked( 'restricted', $settings['ownership_mode'] ); ?> /> <?php esc_html_e( 'Restricted Partner Mode', 'wb-frontend-shop-manager-for-woocommerce' ); ?></label>
						</div>
					</section>

					<section class="wbfsm-admin-card">
						<h2><?php esc_html_e( 'Product Field Permissions', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Select exactly which product fields partners can edit from the frontend.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p>
						<div class="wbfsm-check-grid">
							<?php foreach ( $all_fields as $field_key => $label ) : ?>
								<label><input type="checkbox" name="wbfsm_settings[editable_fields][]" value="<?php echo esc_attr( $field_key ); ?>" <?php checked( in_array( $field_key, (array) $settings['editable_fields'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="wbfsm-admin-card">
						<h2><?php esc_html_e( 'Order Controls', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Allow or restrict whether partner users can change order status from frontend.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></p>
						<label class="wbfsm-toggle">
							<input type="checkbox" name="wbfsm_settings[allow_order_status_update]" value="1" <?php checked( ! empty( $settings['allow_order_status_update'] ) ); ?> />
							<span><?php esc_html_e( 'Allow Order Status Update', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						</label>
					</section>
				</div>
				<div class="wbfsm-admin-submit">
					<?php submit_button( __( 'Save Settings', 'wb-frontend-shop-manager-for-woocommerce' ), 'primary', 'submit', false ); ?>
				</div>
			</form>

			<section class="wbfsm-admin-card wbfsm-admin-card-logs">
				<div class="wbfsm-admin-card-head">
					<h2><?php esc_html_e( 'Audit Logs', 'wb-frontend-shop-manager-for-woocommerce' ); ?></h2>
					<span class="wbfsm-chip"><?php echo esc_html( (string) $log_data['total'] ); ?> <?php esc_html_e( 'entries', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
				</div>
				<form method="get" class="wbfsm-log-toolbar">
					<input type="hidden" name="page" value="wbfsm-settings" />
					<label>
						<span><?php esc_html_e( 'Search', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						<input type="search" name="log_search" value="<?php echo esc_attr( $log_filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Action, object type or ID', 'wb-frontend-shop-manager-for-woocommerce' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Action', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						<select name="log_action">
							<option value=""><?php esc_html_e( 'All Actions', 'wb-frontend-shop-manager-for-woocommerce' ); ?></option>
							<?php foreach ( $available_actions as $action ) : ?>
								<option value="<?php echo esc_attr( $action ); ?>" <?php selected( $log_filters['action_type'], $action ); ?>><?php echo esc_html( $action ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'User ID', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						<input type="number" min="1" name="log_user" value="<?php echo esc_attr( $log_filters['user_id'] > 0 ? (string) $log_filters['user_id'] : '' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'From', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						<input type="date" name="log_from" value="<?php echo esc_attr( $log_filters['date_from'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'To', 'wb-frontend-shop-manager-for-woocommerce' ); ?></span>
						<input type="date" name="log_to" value="<?php echo esc_attr( $log_filters['date_to'] ); ?>" />
					</label>
					<div class="wbfsm-log-toolbar-actions">
						<button type="submit" class="button button-secondary"><?php esc_html_e( 'Apply Filters', 'wb-frontend-shop-manager-for-woocommerce' ); ?></button>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wbfsm-settings' ) ); ?>"><?php esc_html_e( 'Reset', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wbfsm_export_logs', 'log_search' => $log_filters['search'], 'log_action' => $log_filters['action_type'], 'log_user' => $log_filters['user_id'], 'log_from' => $log_filters['date_from'], 'log_to' => $log_filters['date_to'], 'log_sort' => $log_sort_by, 'log_order' => $log_sort_order ), admin_url( 'admin-post.php' ) ), 'wbfsm_export_logs' ) ); ?>"><?php esc_html_e( 'Export CSV', 'wb-frontend-shop-manager-for-woocommerce' ); ?></a>
					</div>
				</form>
				<?php if ( ! empty( $active_filter_chips ) ) : ?>
					<p class="wbfsm-log-chips">
						<?php foreach ( $active_filter_chips as $chip ) : ?>
							<span class="wbfsm-chip"><?php echo esc_html( $chip ); ?></span>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo wp_kses_post( $this->sortable_header_link( __( 'Date', 'wb-frontend-shop-manager-for-woocommerce' ), 'date', $log_sort_by, $log_sort_order, $log_filters ) ); ?></th>
							<th><?php echo wp_kses_post( $this->sortable_header_link( __( 'User', 'wb-frontend-shop-manager-for-woocommerce' ), 'user', $log_sort_by, $log_sort_order, $log_filters ) ); ?></th>
							<th><?php echo wp_kses_post( $this->sortable_header_link( __( 'Action', 'wb-frontend-shop-manager-for-woocommerce' ), 'action', $log_sort_by, $log_sort_order, $log_filters ) ); ?></th>
							<th><?php echo wp_kses_post( $this->sortable_header_link( __( 'Object', 'wb-frontend-shop-manager-for-woocommerce' ), 'object', $log_sort_by, $log_sort_order, $log_filters ) ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $log_data['rows'] ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No logs found.', 'wb-frontend-shop-manager-for-woocommerce' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $log_data['rows'] as $row ) : ?>
								<tr>
									<td><?php echo esc_html( get_date_from_gmt( $row['created_at'], 'Y-m-d H:i:s' ) ); ?></td>
									<td><?php echo esc_html( (string) $row['user_id'] ); ?></td>
									<td><?php echo esc_html( (string) $row['action_type'] ); ?></td>
									<td><?php echo esc_html( (string) $row['object_type'] . ' #' . (string) $row['object_id'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				<?php if ( $log_pages > 1 ) : ?>
					<p class="wbfsm-pagination-links">
						<?php for ( $i = 1; $i <= $log_pages; $i++ ) : ?>
							<a class="<?php echo $i === $page ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'log_page' => $i, 'log_search' => $log_filters['search'], 'log_action' => $log_filters['action_type'], 'log_user' => $log_filters['user_id'], 'log_from' => $log_filters['date_from'], 'log_to' => $log_filters['date_to'], 'log_sort' => $log_sort_by, 'log_order' => $log_sort_order ) ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
						<?php endfor; ?>
					</p>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Create dashboard page with shortcode if missing.
	 *
	 * @return void
	 */
	public function handle_create_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		check_admin_referer( 'wbfsm_create_dashboard_page' );

		$existing_page_id = WB_FSM_Helpers::get_dashboard_page_id();
		if ( $existing_page_id > 0 ) {
			wp_safe_redirect( add_query_arg( 'wbfsm_setup', 'exists', admin_url( 'admin.php?page=wbfsm-settings' ) ) );
			exit;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Shop Manager Dashboard', 'wb-frontend-shop-manager-for-woocommerce' ),
				'post_content' => '[wb_fsm_dashboard]',
			)
		);

		$state = ( ! is_wp_error( $page_id ) && (int) $page_id > 0 ) ? 'created' : 'failed';
		wp_safe_redirect( add_query_arg( 'wbfsm_setup', $state, admin_url( 'admin.php?page=wbfsm-settings' ) ) );
		exit;
	}

	/**
	 * Export audit logs CSV based on current filter values.
	 *
	 * @return void
	 */
	public function handle_export_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		check_admin_referer( 'wbfsm_export_logs' );

		$filters = array(
			'search'      => sanitize_text_field( wp_unslash( $_GET['log_search'] ?? '' ) ),
			'action_type' => sanitize_key( wp_unslash( $_GET['log_action'] ?? '' ) ),
			'user_id'     => absint( wp_unslash( $_GET['log_user'] ?? 0 ) ),
			'date_from'   => sanitize_text_field( wp_unslash( $_GET['log_from'] ?? '' ) ),
			'date_to'     => sanitize_text_field( wp_unslash( $_GET['log_to'] ?? '' ) ),
		);
		$sort_by    = sanitize_key( wp_unslash( $_GET['log_sort'] ?? 'date' ) );
		$sort_order = 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['log_order'] ?? 'desc' ) ) ) ? 'asc' : 'desc';

		$csv = WB_FSM_Audit_Logs::export_logs_csv( $filters, $sort_by, $sort_order );
		if ( '' === $csv ) {
			wp_die( esc_html__( 'Unable to export logs.', 'wb-frontend-shop-manager-for-woocommerce' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wbfsm-audit-logs-' . gmdate( 'Ymd-His' ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Render sortable table header link.
	 *
	 * @param string              $label Label.
	 * @param string              $column Column key.
	 * @param string              $current_sort Current sort key.
	 * @param string              $current_order Current sort order.
	 * @param array<string,mixed> $filters Filters.
	 * @return string
	 */
	private function sortable_header_link( string $label, string $column, string $current_sort, string $current_order, array $filters ): string {
		$is_active  = $current_sort === $column;
		$next_order = ( $is_active && 'asc' === $current_order ) ? 'desc' : 'asc';
		$arrow      = '';

		if ( $is_active ) {
			$arrow = 'asc' === $current_order ? ' ↑' : ' ↓';
		}

		$url = add_query_arg(
			array(
				'page'       => 'wbfsm-settings',
				'log_page'   => 1,
				'log_search' => $filters['search'] ?? '',
				'log_action' => $filters['action_type'] ?? '',
				'log_user'   => $filters['user_id'] ?? 0,
				'log_from'   => $filters['date_from'] ?? '',
				'log_to'     => $filters['date_to'] ?? '',
				'log_sort'   => $column,
				'log_order'  => $next_order,
			),
			admin_url( 'admin.php' )
		);

		$class = $is_active ? ' class="is-active"' : '';
		return sprintf(
			'<a%s href="%s">%s%s</a>',
			$class,
			esc_url( $url ),
			esc_html( $label ),
			esc_html( $arrow )
		);
	}
}
