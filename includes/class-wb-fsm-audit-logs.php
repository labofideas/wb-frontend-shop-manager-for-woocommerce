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

		$wpdb->insert(
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

		$offset = max( 0, ( $page - 1 ) * $per_page );
		$table  = self::table_name();
		$where_data = self::build_where_clause( $filters );
		$where_sql  = $where_data['sql'];
		$where_args = $where_data['args'];
		$order_sql  = self::build_order_clause( $sort_by, $sort_order );

		$total_sql      = "SELECT COUNT(*) FROM {$table}{$where_sql}";
		$prepared_total = ! empty( $where_args ) ? $wpdb->prepare( $total_sql, $where_args ) : $total_sql;
		$total          = (int) $wpdb->get_var( $prepared_total );

		$list_args      = $where_args;
		$list_args[]    = $per_page;
		$list_args[]    = $offset;
		$list_sql       = "SELECT * FROM {$table}{$where_sql} {$order_sql} LIMIT %d OFFSET %d";
		$prepared_list  = $wpdb->prepare( $list_sql, $list_args );
		$rows           = $wpdb->get_results( $prepared_list, ARRAY_A );

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
		$rows  = $wpdb->get_col( "SELECT DISTINCT action_type FROM {$table} ORDER BY action_type ASC" );
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
		$where_data = self::build_where_clause( $filters );
		$where_sql  = $where_data['sql'];
		$where_args = $where_data['args'];
		$order_sql  = self::build_order_clause( $sort_by, $sort_order );
		$sql        = "SELECT created_at, user_id, action_type, object_type, object_id FROM {$table}{$where_sql} {$order_sql}";
		$prepared   = ! empty( $where_args ) ? $wpdb->prepare( $sql, $where_args ) : $sql;
		$rows       = $wpdb->get_results( $prepared, ARRAY_A );

		$handle = fopen( 'php://temp', 'r+' );
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
		fclose( $handle );
		return false === $content ? '' : $content;
	}

	/**
	 * Build SQL WHERE clause and args for filters.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array{sql: string, args: array<int,mixed>}
	 */
	private static function build_where_clause( array $filters ): array {
		$where_parts = array();
		$args        = array();

		$search = sanitize_text_field( (string) ( $filters['search'] ?? '' ) );
		if ( '' !== $search ) {
			$where_parts[] = '( action_type LIKE %s OR object_type LIKE %s OR CAST(object_id AS CHAR) LIKE %s )';
			$like          = '%' . self::esc_like( $search ) . '%';
			$args[]        = $like;
			$args[]        = $like;
			$args[]        = $like;
		}

		$action_type = sanitize_key( (string) ( $filters['action_type'] ?? '' ) );
		if ( '' !== $action_type ) {
			$where_parts[] = 'action_type = %s';
			$args[]        = $action_type;
		}

		$user_id = absint( $filters['user_id'] ?? 0 );
		if ( $user_id > 0 ) {
			$where_parts[] = 'user_id = %d';
			$args[]        = $user_id;
		}

		$date_from = sanitize_text_field( (string) ( $filters['date_from'] ?? '' ) );
		if ( '' !== $date_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$where_parts[] = 'created_at >= %s';
			$args[]        = $date_from . ' 00:00:00';
		}

		$date_to = sanitize_text_field( (string) ( $filters['date_to'] ?? '' ) );
		if ( '' !== $date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$where_parts[] = 'created_at <= %s';
			$args[]        = $date_to . ' 23:59:59';
		}

		$sql = '';
		if ( ! empty( $where_parts ) ) {
			$sql = ' WHERE ' . implode( ' AND ', $where_parts );
		}

		return array(
			'sql'  => $sql,
			'args' => $args,
		);
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
	 * Build ORDER BY clause using whitelisted columns.
	 *
	 * @param string $sort_by Sort key.
	 * @param string $sort_order Sort order.
	 * @return string
	 */
	private static function build_order_clause( string $sort_by, string $sort_order ): string {
		$column_map = array(
			'date'   => 'created_at',
			'user'   => 'user_id',
			'action' => 'action_type',
			'object' => 'object_id',
		);

		$sort_by    = sanitize_key( $sort_by );
		$sort_order = 'asc' === strtolower( $sort_order ) ? 'ASC' : 'DESC';
		$column     = $column_map[ $sort_by ] ?? 'created_at';

		return "ORDER BY {$column} {$sort_order}";
	}
}
