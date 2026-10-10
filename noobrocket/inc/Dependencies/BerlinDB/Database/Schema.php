<?php
/**
 * Base Custom Database Table Schema Class.
 *
 * @package     Database
 * @subpackage  Schema
 * @copyright   Copyright (c) 2021
 * @license     https://opensource.org/licenses/MIT MIT
 * @since       1.0.0
 */
namespace NoobRocket\Dependencies\BerlinDB\Database;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * A base database table schema class, which houses the collection of columns
 * that a table is made out of.
 *
 * This class is intended to be extended for each unique database table,
 * including global tables for multisite, and users tables.
 *
 * @since 1.0.0
 */
class Schema extends Base {

	/**
	 * Array of database column objects to turn into Column.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	protected $columns = array();

	/**
	 * Request-local templates. Every schema receives its own column objects.
	 *
	 * @var array
	 */
	private static $column_templates = array();

	/**
	 * Invoke new column objects based on array of column data.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Bail if no columns
		if ( empty( $this->columns ) || ! is_array( $this->columns ) ) {
			return;
		}

		// Juggle original columns array
		$columns = $this->columns;
		$this->columns = array();
		$template_key = $this->get_column_template_key( $columns );

		if ( false !== $template_key && isset( self::$column_templates[ $template_key ] ) ) {
			$this->columns = $this->clone_columns( self::$column_templates[ $template_key ] );
			return;
		}

		// Loop through columns and create objects from them
		foreach ( $columns as $column ) {
			if ( is_array( $column ) ) {
				$this->columns[] = new Column( $column );
			} elseif ( $column instanceof Column ) {
				$this->columns[] = $column;
			}
		}

		if ( false !== $template_key ) {
			self::$column_templates[ $template_key ] = $this->clone_columns( $this->columns );
		}
	}

	/**
	 * Reuse only owned value definitions with native, deterministic sanitizers.
	 * Custom filters retain every original Column construction and invocation.
	 *
	 * @param array $columns Raw column definitions.
	 * @return string|false
	 */
	private function get_column_template_key( $columns ) {
		global $wp_filter;

		$owned_schemas = array(
			'NoobRocket\\Engine\\Preload\\Database\\Schemas\\Cache',
			'NoobRocket\\Engine\\Optimization\\RUCSS\\Database\\Schemas\\UsedCSS',
			'NoobRocket\\Engine\\CDN\\HostedCDN\\Database\\Schemas\\HostedCDN',
			'NoobRocket\\Engine\\Admin\\PerformanceMonitoring\\Database\\Schemas\\PerformanceMonitoring',
			'NoobRocket\\Engine\\Media\\AboveTheFold\\Database\\Schemas\\AboveTheFold',
		);
		if ( ! in_array( get_class( $this ), $owned_schemas, true ) || 'wpdb' !== $this->db_global ) {
			return false;
		}

		foreach ( $columns as $column ) {
			if ( ! is_array( $column ) || ! empty( $column['validate'] ) ) {
				return false;
			}
		}
		$values_only = true;
		array_walk_recursive( $columns, static function ( $value ) use ( &$values_only ) {
			if ( ( ! is_scalar( $value ) && ! is_null( $value ) ) || ( is_string( $value ) && false !== strpbrk( $value, '<&' ) ) ) {
				$values_only = false;
			}
		} );
		if ( ! $values_only ) {
			return false;
		}

		$native_callbacks = array(
			'pre_kses' => array( 'wp_pre_kses_less_than' => 1, 'wp_pre_kses_block_attributes' => 3 ),
			'wp_kses_allowed_html' => array( '_wp_kses_allow_note_mention_span' => 2 ),
			'kses_allowed_protocols' => array(),
			'block_parser_class' => array(),
			'sanitize_key' => array(),
			'all' => array(),
		);
		$callbacks = array();
		foreach ( $native_callbacks as $hook => $allowed ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $registered ) {
				foreach ( $registered as $callback ) {
					$function = $callback['function'];
					if ( 10 !== $priority || ! is_string( $function ) || ! isset( $allowed[ $function ] ) || $allowed[ $function ] !== $callback['accepted_args'] ) {
						return false;
					}
					$callbacks[ $hook ][] = $function;
				}
			}
		}

		$db = $this->get_db();
		if ( false !== strpbrk( $db->charset . $db->collate, '<&' ) ) {
			return false;
		}
		return serialize( array( get_class( $this ), $columns, current_filter(), $db->charset, $db->collate, $callbacks ) );
	}

	/**
	 * Copy templates without retaining validators bound to another column.
	 *
	 * @param array $columns Columns to copy.
	 * @return array
	 */
	private function clone_columns( $columns ) {
		$copies = array();
		foreach ( $columns as $column ) {
			$copy = clone $column;
			if ( is_array( $copy->validate ) && isset( $copy->validate[0] ) && $column === $copy->validate[0] ) {
				$copy->validate[0] = $copy;
			}
			$copies[] = $copy;
		}
		return $copies;
	}

	/**
	 * Return the schema in string form.
	 *
	 * @since 1.0.0
	 *
	 * @return string Calls get_create_string() on every column.
	 */
	protected function to_string() {

		// Default return value
		$retval = '';

		// Bail if no columns to convert
		if ( empty( $this->columns ) ) {
			return $retval;
		}

		// Loop through columns...
		foreach ( $this->columns as $column_info ) {
			if ( method_exists( $column_info, 'get_create_string' ) ) {
				$retval .= '\n' . $column_info->get_create_string() . ', ';
			}
		}

		// Return the string
		return $retval;
	}
}
