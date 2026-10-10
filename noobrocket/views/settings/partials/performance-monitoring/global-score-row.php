<?php
/**
 * Global Score row view.
 */

defined( 'ABSPATH' ) || exit;
?>
<tr class="noobrocket-ri-item noobrocket-global-score">
	<td class="noobrocket-ri-item-toggle">
		<div class="icon-frame noobrocket-ri-item-toggle-all noobrocket-ri-item-toggle-all--expanded"></div>
	</td>
	<td class="noobrocket-ri-item-score">
		<?php
		$data['is_dashboard'] = false; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		$this->render_performance_score( $data );
		?>
	</td>
	<td class="noobrocket-ri-item-title">
		<span>
			<?php
			printf(
				// translators: %s is the number of pages tracked/monitored.
				esc_html( $data['status_text'] . ': %s' ),
				esc_html( $data['pages_num'] )
			);
			?>
		</span>
	</td>
	<td class="noobrocket-ri-item-actions"></td>
</tr>
