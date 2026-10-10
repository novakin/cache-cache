<?php
/** Site overview built from live diagnostics and existing administrative actions. */
defined( 'ABSPATH' ) || exit;
?>
<div id="<?php echo esc_attr( $data['id'] ); ?>" class="noobrocket-Page noobrocket-overview">
	<div class="noobrocket-sectionHeader noobrocket-panel-heading">
		<div><h1 class="noobrocket-title1" tabindex="-1"><?php esc_html_e( 'Site overview', 'noobrocket' ); ?></h1><p><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></p></div>
		<div class="noobrocket-heading-actions">
			<?php if ( current_user_can( 'noobrocket_purge_cache' ) ) {
				$this->render_action_button( 'link', 'purge_cache', [
					'label' => __( 'Clear cache', 'noobrocket' ),
					'parameters' => [ 'type' => 'all' ],
					'attributes' => [ 'class' => 'noobrocket-button' ],
				] );
			} ?>
		</div>
	</div>
	<div class="noobrocket-overview-panels">
		<?php do_action( 'noobrocket_before_dashboard_content' ); ?>
	</div>
	<div class="noobrocket-view-footer"><span><?php esc_html_e( 'Local optimization. No account or activation key required.', 'noobrocket' ); ?></span></div>
</div>
