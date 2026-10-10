<?php
/** Keep unavailable provider destinations discoverable without exposing inactive operations. */
defined( 'ABSPATH' ) || exit;
$noobrocket_toggle = 'cloudflare' === $data['id'] ? 'do_cloudflare' : 'sucury_waf_cache_sync';
?>
<div class="noobrocket-provider-prerequisite noobrocket-settings-card" data-noobrocket-provider-prerequisite hidden>
	<h2 class="noobrocket-title2"><?php esc_html_e( 'Enable this integration first', 'noobrocket' ); ?></h2>
	<p><?php printf( esc_html__( '%s is off in the current form. Enable it in Integrations to configure its connection. Save the changes before using cache actions.', 'noobrocket' ), esc_html( $data['title'] ) ); ?></p>
	<a class="noobrocket-button" href="#addons=<?php echo esc_attr( $noobrocket_toggle ); ?>"><?php esc_html_e( 'Open integration settings', 'noobrocket' ); ?></a>
</div>
