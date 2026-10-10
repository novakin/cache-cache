<?php
/**
 * Generic table list row partial.
 *
 * Renders a single row with any number of columns.
 *
 * @since 3.22
 *
 * @param array $data {
 *     Row data.
 *
 *     @type string $class   Optional. Additional CSS class for the row.
 *     @type array  $columns Array of column data.
 *         @type string $content HTML content for the column.
 *         @type string $class   Optional. Additional CSS class for the column cell.
 *         @type string $type    Optional. Cell type: 'cell' (default), 'actions'. Maps to BEM element.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_row_classes = 'noobrocket-table-list__row';
if ( ! empty( $data['class'] ) ) {
	$noobrocket_row_classes .= ' ' . $data['class'];
}
?>
<div class="<?php echo esc_attr( $noobrocket_row_classes ); ?>">
	<?php
	if ( ! empty( $data['columns'] ) ) :
		foreach ( $data['columns'] as $noobrocket_column ) :
			$noobrocket_cell_type  = ! empty( $noobrocket_column['type'] ) ? $noobrocket_column['type'] : 'cell';
			$noobrocket_cell_class = 'noobrocket-table-list__' . $noobrocket_cell_type;
			if ( ! empty( $noobrocket_column['class'] ) ) {
				$noobrocket_cell_class .= ' ' . $noobrocket_column['class'];
			}
			?>
			<div class="<?php echo esc_attr( $noobrocket_cell_class ); ?>">
				<?php echo $noobrocket_column['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is pre-escaped by the caller. ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
