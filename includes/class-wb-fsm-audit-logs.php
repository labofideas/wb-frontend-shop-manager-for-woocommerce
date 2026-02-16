<?php
/**
 * Audit logs storage.
 *
 * @package WB_FSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_FSM_Audit_Logs {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'wb_fsm_logs';
	}

	/**
	 * Create table.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			action_type VARCHAR(64) NOT NULL,
			object_id BIGINT(20) UNSIGNED NOT NULL,
			object_type VARCHAR(32) NOT NULL,
			before_data LONGTEXT NULL,
			after_data LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY action_type (action_type),
			KEY object_id (object_id),
			KEY object_type (object_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert log.
	 *
	 * @param string $action_type Action type.
	 * @param int    $object_id   Object ID.
	 * @param string $object_type Object type.
	 * @param array  $before_data Before payload.
	 * @param array  $after_data  After payload.
	 * @return void
	 */
	public static function add_log( string $action_type, int $object_id, string $object_type, array $before_data = array(), array $after_data = array() ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert into plugin-owned audit table.
			self::table_name(),
			array(
				'user_id'     => get_current_user_id(),
				'action_type' => sanitize_key( $action_type ),
				'object_id'   => absint( $object_id ),
				'object_type' => sanitize_key( $object_type ),
				'before_data' => wp_json_encode( $before_data ),
				'after_data'  => wp_json_encode( $after_data ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Fetch logs.
	 *
	 * @param int                 $page    Page.
	 * @param int                 $per_page Limit.
	 * @param array<string,mixed> $filters Filters.
	 * @param string              $sort_by Sort column.
	 * @param string              $sort_order Sort order.
	 * @return array{rows: array<int,array<string,mixed>>, total: int}
	 */
	public static function get_logs( int $page = 1, int $per_page = 20, array $filters = array(), string $sort_by = 'created_at', string $sort_order = 'desc' ): array {
		global $wpdb;

		$table      = self::table_name();
		$offset     = max( 0, ( $page - 1 ) * $per_page );
		$normalized = self::normalize_filters( $filters );
		$order_by   = self::normalize_sort_key( $sort_by );
		$order      = self::normalize_sort_order( $sort_order );

		$search       = $normalized['search'];
		$search_like  = '' !== $search ? '%' . self::esc_like( $search ) . '%' : '';
		$action_type  = $normalized['action_type'];
		$user_id      = $normalized['user_id'];
		$date_from    = $normalized['date_from'];
		$date_to      = $normalized['date_to'];
		$date_from_gmt = '' !== $date_from ? $date_from . ' 00:00:00' : '';
		$date_to_gmt   = '' !== $date_to ? $date_to . ' 23:59:59' : '';

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i
				WHERE ( %s = "" OR action_type LIKE %s OR object_type LIKE %s OR CAST(object_id AS CHAR) LIKE %s )
					AND ( %s = "" OR action_type = %s )
					AND ( %d = 0 OR user_id = %d )
					AND ( %s = "" OR created_at >= %s )
					AND ( %s = "" OR created_at <= %s )',
				$table,
				$search,
				$search_like,
				$search_like,
				$search_like,
				$action_type,
				$action_type,
				$user_id,
				$user_id,
				$date_from_gmt,
				$date_from_gmt,
				$date_to_gmt,
				$date_to_gmt
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare(
				'SELECT * FROM %i
				WHERE ( %s = "" OR action_type LIKE %s OR object_type LIKE %s OR CAST(object_id AS CHAR) LIKE %s )
					AND ( %s = "" OR action_type = %s )
					AND ( %d = 0 OR user_id = %d )
					AND ( %s = "" OR created_at >= %s )
					AND ( %s = "" OR created_at <= %s ) '
				. self::build_safe_order_clause( $order_by, $order ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				. ' LIMIT %d OFFSET %d',
				$table,
				$search,
				$search_like,
				$search_like,
				$search_like,
				$action_type,
				$action_type,
				$user_id,
				$user_id,
				$date_from_gmt,
				$date_from_gmt,
				$date_to_gmt,
				$date_to_gmt,
				$per_page,
				$offset
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Distinct action types for log filter dropdown.
	 *
	 * @return array<int,string>
	 */
	public static function get_distinct_action_types(): array {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT action_type FROM %i ORDER BY action_type ASC', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_values( array_filter( array_map( 'sanitize_key', (array) $rows ) ) );
	}

	/**
	 * Export filtered logs as CSV.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @param string              $sort_by Sort column.
	 * @param string              $sort_order Sort order.
	 * @return string
	 */
	public static function export_logs_csv( array $filters = array(), string $sort_by = 'created_at', string $sort_order = 'desc' ): string {
		global $wpdb;
		$table      = self::table_name();
		$normalized = self::normalize_filters( $filters );
		$order_by   = self::normalize_sort_key( $sort_by );
		$order      = self::normalize_sort_order( $sort_order );

		$search        = $normalized['search'];
		$search_like   = '' !== $search ? '%' . self::esc_like( $search ) . '%' : '';
		$action_type   = $normalized['action_type'];
		$user_id       = $normalized['user_id'];
		$date_from     = $normalized['date_from'];
		$date_to       = $normalized['date_to'];
		$date_from_gmt = '' !== $date_from ? $date_from . ' 00:00:00' : '';
		$date_to_gmt   = '' !== $date_to ? $date_to . ' 23:59:59' : '';

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->prepare(
				'SELECT created_at, user_id, action_type, object_type, object_id FROM %i
				WHERE ( %s = "" OR action_type LIKE %s OR object_type LIKE %s OR CAST(object_id AS CHAR) LIKE %s )
					AND ( %s = "" OR action_type = %s )
					AND ( %d = 0 OR user_id = %d )
					AND ( %s = "" OR created_at >= %s )
					AND ( %s = "" OR created_at <= %s ) '
				. self::build_safe_order_clause( $order_by, $order ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$table,
				$search,
				$search_like,
				$search_like,
				$search_like,
				$action_type,
				$action_type,
				$user_id,
				$user_id,
				$date_from_gmt,
				$date_from_gmt,
				$date_to_gmt,
				$date_to_gmt
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Temporary in-memory stream for CSV output.
		if ( false === $handle ) {
			return '';
		}

		fputcsv( $handle, array( 'Date (GMT)', 'User ID', 'Action', 'Object Type', 'Object ID' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv(
				$handle,
				array(
					(string) ( $row['created_at'] ?? '' ),
					(string) ( $row['user_id'] ?? '' ),
					(string) ( $row['action_type'] ?? '' ),
					(string) ( $row['object_type'] ?? '' ),
					(string) ( $row['object_id'] ?? '' ),
				)
			);
		}

		rewind( $handle );
		$content = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temporary in-memory stream.
		return false === $content ? '' : $content;
	}

	/**
	 * Escape SQL LIKE wildcards.
	 *
	 * @param string $value Input.
	 * @return string
	 */
	private static function esc_like( string $value ): string {
		global $wpdb;
		return $wpdb->esc_like( $value );
	}

	/**
	 * Normalize filter input.
	 *
	 * @param array<string,mixed> $filters Raw filters.
	 * @return array{search:string,action_type:string,user_id:int,date_from:string,date_to:string}
	 */
	private static function normalize_filters( array $filters ): array {
		$date_from = sanitize_text_field( (string) ( $filters['date_from'] ?? '' ) );
		$date_to   = sanitize_text_field( (string) ( $filters['date_to'] ?? '' ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = '';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = '';
		}

		return array(
			'search'      => sanitize_text_field( (string) ( $filters['search'] ?? '' ) ),
			'action_type' => sanitize_key( (string) ( $filters['action_type'] ?? '' ) ),
			'user_id'     => absint( $filters['user_id'] ?? 0 ),
			'date_from'   => $date_from,
			'date_to'     => $date_to,
		);
	}

	/**
	 * Normalize sort key to known values.
	 *
	 * @param string $sort_by Sort key.
	 * @return string
	 */
	private static function normalize_sort_key( string $sort_by ): string {
		$key = sanitize_key( $sort_by );
		return in_array( $key, array( 'date', 'user', 'action', 'object' ), true ) ? $key : 'date';
	}

	/**
	 * Normalize sort order.
	 *
	 * @param string $sort_order Sort order.
	 * @return string
	 */
	private static function normalize_sort_order( string $sort_order ): string {
		return 'asc' === strtolower( $sort_order ) ? 'ASC' : 'DESC';
	}

	/**
	 * Build a deterministic ORDER BY clause from validated inputs.
	 *
	 * @param string $order_by Sort key.
	 * @param string $order Sort direction.
	 * @return string
	 */
	private static function build_safe_order_clause( string $order_by, string $order ): string {
		$column = 'created_at';
		if ( 'user' === $order_by ) {
			$column = 'user_id';
		} elseif ( 'action' === $order_by ) {
			$column = 'action_type';
		} elseif ( 'object' === $order_by ) {
			$column = 'object_id';
		}

		return "ORDER BY {$column} {$order}, id {$order}";
	}
}
