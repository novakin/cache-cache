<?php
/**
 * Recommendations Success State template.
 *
 * Shown when all recommendations have been applied (no recommendations returned).
 *
 * @since 3.21
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="noobrocket-recommendations__success">
	<p class="noobrocket-recommendations__success-title">
		<?php
		echo esc_html__( 'All done!', 'noobrocket' );
		?>
	</p>
	<p class="noobrocket-recommendations__success-message">
		<?php esc_html_e( 'All recommended Cache-Cache features are now enabled.', 'noobrocket' ); ?>
	</p>
</div>
