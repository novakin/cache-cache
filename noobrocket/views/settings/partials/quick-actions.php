<?php
/**
 * Quick Actions partial template.
 *
 * @since 3.17
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_manual_preload = (bool) get_noobrocket_option( 'manual_preload', false );
?>
<div class="noobrocket-optionHeader noobrocket-quick-actions-header">
	<h3 class="noobrocket-title2"><?php esc_html_e( 'Quick Actions', 'noobrocket' ); ?></h3>
</div>

<div class="noobrocket-fieldsContainer noobrocket-quick-actions-container">
	<fieldset class="noobrocket-fieldsContainer-fieldset">
		<?php if ( current_user_can( 'noobrocket_purge_cache' ) ) : ?>
		<div class="noobrocket-field">
			<h4 class="noobrocket-title3"><?php esc_html_e( 'Cache Files', 'noobrocket' ); ?></h4>
			<p><?php echo $noobrocket_manual_preload ? esc_html__( 'Clear and preload all the cache files.', 'noobrocket' ) : esc_html__( 'Clear all the cache files.', 'noobrocket' ); ?></p>
			<?php
			$this->render_action_button(
				'link',
				'purge_cache',
				[
					'label'      => $noobrocket_manual_preload ? __( 'Clear and preload', 'noobrocket' ) : __( 'Clear', 'noobrocket' ),
					'parameters' => [
						'type' => 'all',
					],
					'attributes' => [
						'class' => 'noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-icon-trash noobrocket-button--no-min-width',
					],
				]
			);
			?>
		</div>
		<?php endif; ?>
		<?php if ( 'local' !== wp_get_environment_type() && get_noobrocket_option( 'async_css' ) && apply_filters( 'do_noobrocket_critical_css_generation', true ) && current_user_can( 'noobrocket_regenerate_critical_css' ) ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
		<div class="noobrocket-field">
			<h4 class="noobrocket-title3"><?php esc_html_e( 'Regenerate Critical CSS', 'noobrocket' ); ?></h4>
			<?php
			$this->render_action_button(
				'link',
				'noobrocket_generate_critical_css',
				[
					'label'      => __( 'Regenerate Critical CSS', 'noobrocket' ),
					'attributes' => [
						'class' => 'noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-icon-refresh',
					],
				]
			);
			?>
		</div>
		<?php endif; ?>

		<?php
		/**
		 * Fires in the dashboard actions column
		 *
		 * @since 3.16
		 */
		do_action( 'noobrocket_dashboard_actions' );
		?>
	</fieldset>
</div>
