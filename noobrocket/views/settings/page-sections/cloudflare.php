<?php
/**
 * Cloudflare section template.
 *
 * @since 3.0
 *
 * @param array {
 *     Section arguments.
 *
 *     @type string $id    Page section identifier.
 *     @type string $title Page section title.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_cf_enabled = (bool) get_noobrocket_option( 'do_cloudflare', 0 );
$noobrocket_cf_api_key = defined( 'NOOBROCKET_CF_API_KEY' ) ? noobrocket_get_constant( 'NOOBROCKET_CF_API_KEY', '' ) : get_noobrocket_option( 'cloudflare_api_key', '' );
$noobrocket_cf_ready   = $noobrocket_cf_enabled
	&& '' !== trim( (string) $noobrocket_cf_api_key )
	&& false !== filter_var( get_noobrocket_option( 'cloudflare_email', '' ), FILTER_VALIDATE_EMAIL )
	&& '' !== trim( (string) get_noobrocket_option( 'cloudflare_zone_id', '' ) );

?>

<div id="<?php echo esc_attr( $data['id'] ); ?>" class="noobrocket-Page">
	<div class="noobrocket-sectionHeader noobrocket-integration-heading">
		<h1 class="noobrocket-title1" tabindex="-1"><?php echo esc_html( $data['title'] ); ?></h1>
		<span class="noobrocket-integration-brand" aria-hidden="true">
			<img src="<?php echo esc_url( NOOBROCKET_ASSETS_IMG_URL . 'logo-cloudflare.svg' ); ?>" width="246" height="35" alt="">
		</span>
	</div>
	<?php require __DIR__ . '/../partials/provider-prerequisite.php'; ?>
	<div data-noobrocket-provider-settings>
	<?php $this->render_settings_sections( $data['id'] ); ?>
	<?php if ( current_user_can( 'noobrocket_purge_cloudflare_cache' ) ) : ?>
	<section class="noobrocket-settings-card" aria-labelledby="noobrocket-cloudflare-cache-title">
	<header class="noobrocket-optionHeader noobrocket-provider-cache-heading">
		<h2 class="noobrocket-title2" id="noobrocket-cloudflare-cache-title"><?php esc_html_e( 'Cloudflare cache', 'noobrocket' ); ?></h2>
		<div class="noobrocket-action-group">
		<?php if ( $noobrocket_cf_ready ) : ?>
		<?php
		$this->render_action_button(
			'link',
			'noobrocket_purge_cloudflare',
			[
				'label'      => __( 'Clear Cloudflare cache', 'noobrocket' ),
				'attributes' => [
					'class'                         => 'noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-icon-trash',
					'aria-describedby'              => 'noobrocket-cloudflare-cache-prerequisite',
					'data-noobrocket-operation-name' => __( 'Clear Cloudflare cache', 'noobrocket' ),
				],
			]
		);
		?>
		<?php else : ?>
		<button type="button" class="noobrocket-button noobrocket-button--small" aria-describedby="noobrocket-cloudflare-cache-prerequisite" disabled><?php esc_html_e( 'Clear Cloudflare cache', 'noobrocket' ); ?></button>
		<?php endif; ?>
		</div>
	</header>
	<p id="noobrocket-cloudflare-cache-prerequisite" class="noobrocket-field-description">
	<?php
	if ( ! $noobrocket_cf_enabled ) {
		esc_html_e( 'Enable Cloudflare in Integrations and save your account email, global API key and Zone ID before clearing its cache.', 'noobrocket' );
	} elseif ( ! $noobrocket_cf_ready ) {
		esc_html_e( 'Complete and save the account email, global API key and Zone ID before clearing the Cloudflare cache.', 'noobrocket' );
	} else {
		esc_html_e( 'Uses the saved Cloudflare connection settings.', 'noobrocket' );
	}
	?>
	<a href="<?php echo esc_url( __( 'https://support.cloudflare.com/hc/en-us/articles/200169246', 'noobrocket' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Help', 'noobrocket' ); ?></a>
	</p>
	</section>
	<?php endif; ?>
	</div>
</div>
