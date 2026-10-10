<?php
/** Report the configured provider; no provider is installed or changed here. */
defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'noobrocket_manage_options' ) || ! is_array( $data['object_cache'] ?? null ) ) { return; }
$cache = $data['object_cache'];
$badge = ! $cache['health'] ? __( 'Check failed', 'noobrocket' ) : ( $cache['external'] ? __( 'Persistent', 'noobrocket' ) : __( 'Standard', 'noobrocket' ) );
$badge_class = ! $cache['health'] ? 'is-error' : ( $cache['external'] ? 'is-success' : 'is-neutral' );
?>
<section class="noobrocket-surface noobrocket-object-cache" aria-labelledby="noobrocket-object-cache-title">
	<div class="noobrocket-surface-head">
		<h2 id="noobrocket-object-cache-title"><svg class="noobrocket-icon" aria-hidden="true"><use href="#nbr-icon-server"/></svg><?php esc_html_e( 'Object cache', 'noobrocket' ); ?></h2>
		<span class="noobrocket-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge ); ?></span>
	</div>
	<div class="noobrocket-card-body">
		<?php if ( ! $cache['health'] ) : ?>
		<p><?php esc_html_e( 'The object cache failed its check. Review the provider configuration before relying on it.', 'noobrocket' ); ?></p>
		<?php elseif ( $cache['external'] ) : ?>
		<p><?php esc_html_e( 'WordPress reports a persistent object cache. It can reuse database results across requests.', 'noobrocket' ); ?></p>
		<?php else : ?>
		<p><?php esc_html_e( 'WordPress reuses database results during each request. A persistent object cache can reuse them between requests too.', 'noobrocket' ); ?></p>
		<?php endif; ?>
		<details class="noobrocket-status-evidence">
			<summary><?php esc_html_e( 'Provider and check details', 'noobrocket' ); ?></summary>
			<div>
				<p><?php echo esc_html( $cache['message'] ); ?></p>
				<dl class="noobrocket-key-values">
					<div><dt><?php esc_html_e( 'Provider', 'noobrocket' ); ?></dt><dd><?php echo esc_html( $cache['provider'] ); ?></dd></div>
					<?php if ( null !== $cache['connected'] ) : ?>
					<div><dt><?php esc_html_e( 'Connection', 'noobrocket' ); ?></dt><dd><?php echo esc_html( $cache['connected'] ? __( 'Connected', 'noobrocket' ) : __( 'Disconnected', 'noobrocket' ) ); ?></dd></div>
					<?php endif; ?>
				</dl>
				<p class="noobrocket-helper"><?php esc_html_e( 'This check uses one temporary key. It does not measure speed or verify persistence between requests.', 'noobrocket' ); ?></p>
			</div>
		</details>
	</div>
	<?php if ( ! $cache['external'] || ! $cache['health'] ) : ?>
	<div class="noobrocket-status-navigation"><a href="<?php echo esc_url( plugins_url( 'docs/index.html#native-object-cache', NOOBROCKET_FILE ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( ! $cache['health'] ? __( 'Troubleshoot object cache', 'noobrocket' ) : __( 'Set up persistent object cache', 'noobrocket' ) ); ?></a></div>
	<?php endif; ?>
</section>
