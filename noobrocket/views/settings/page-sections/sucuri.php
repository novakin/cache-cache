<?php
/**
 * Sucuri Cache section template.
 *
 * @since  3.2
 * @author Grégory Viguier
 *
 * @param array {
 *     Section arguments.
 *
 *     @type string $id    Page section identifier.
 *     @type string $title Page section title.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_sucuri_enabled = (bool) get_noobrocket_option( 'sucury_waf_cache_sync', 0 );
$noobrocket_sucuri_ready   = $noobrocket_sucuri_enabled
	&& false !== NoobRocket\Addon\Sucuri\Subscriber::is_api_key_valid( trim( (string) get_noobrocket_option( 'sucury_waf_api_key', '' ) ) );

?>

<div id="<?php echo esc_attr( $data['id'] ); ?>" class="noobrocket-Page">
	<div class="noobrocket-sectionHeader noobrocket-integration-heading">
		<h1 class="noobrocket-title1" tabindex="-1"><?php echo esc_html( $data['title'] ); ?></h1>
		<span class="noobrocket-integration-brand" aria-hidden="true">
			<img src="<?php echo esc_url( NOOBROCKET_ASSETS_IMG_URL . 'logo-sucuri.png' ); ?>" width="99" height="35" alt="">
		</span>
	</div>
	<?php require __DIR__ . '/../partials/provider-prerequisite.php'; ?>
	<div data-noobrocket-provider-settings>
	<?php $this->render_settings_sections( $data['id'] ); ?>
	<?php if ( current_user_can( 'noobrocket_purge_sucuri_cache' ) ) : ?>
	<section class="noobrocket-settings-card" aria-labelledby="noobrocket-sucuri-cache-title">
	<header class="noobrocket-optionHeader noobrocket-provider-cache-heading">
		<h2 class="noobrocket-title2" id="noobrocket-sucuri-cache-title"><?php esc_html_e( 'Sucuri cache', 'noobrocket' ); ?></h2>
		<div class="noobrocket-action-group">
		<?php if ( $noobrocket_sucuri_ready ) : ?>
		<?php
			$this->render_action_button(
				'link',
				'noobrocket_purge_sucuri',
				[
					'label'      => __( 'Clear Sucuri cache', 'noobrocket' ),
					'attributes' => [
						'class'                         => 'noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-icon-trash',
						'aria-describedby'              => 'noobrocket-sucuri-cache-prerequisite',
						'data-noobrocket-operation-name' => __( 'Clear Sucuri cache', 'noobrocket' ),
					],
				]
			);
		?>
		<?php else : ?>
		<button type="button" class="noobrocket-button noobrocket-button--small" aria-describedby="noobrocket-sucuri-cache-prerequisite" disabled><?php esc_html_e( 'Clear Sucuri cache', 'noobrocket' ); ?></button>
		<?php endif; ?>
		</div>
	</header>
	<p id="noobrocket-sucuri-cache-prerequisite" class="noobrocket-field-description">
	<?php
	if ( ! $noobrocket_sucuri_enabled ) {
		esc_html_e( 'Enable Sucuri in Integrations and save its firewall API key before clearing its cache.', 'noobrocket' );
	} elseif ( ! $noobrocket_sucuri_ready ) {
		esc_html_e( 'Save a firewall API key in the required format before clearing the Sucuri cache.', 'noobrocket' );
	} else {
		esc_html_e( 'Uses the saved Sucuri firewall API key.', 'noobrocket' );
	}
	?>
	<a href="https://kb.sucuri.net/firewall/Performance/clearing-cache" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Help', 'noobrocket' ); ?></a>
	</p>
	</section>
	<?php endif; ?>
	</div>
</div>
