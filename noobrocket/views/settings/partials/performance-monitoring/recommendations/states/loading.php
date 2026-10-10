<?php
/**
 * Recommendations Loading State template.
 *
 * Shown when recommendations are being fetched.
 *
 * @since 3.21
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="noobrocket-recommendations__loading">
	<div class="noobrocket-recommendations__loading-spinner"></div>
	<p class="noobrocket-recommendations__loading-text">
		<?php esc_html_e( 'Loading recommendations...', 'noobrocket' ); ?>
		<br />
		<?php esc_html_e( 'Almost there!', 'noobrocket' ); ?>
	</p>
</div>
