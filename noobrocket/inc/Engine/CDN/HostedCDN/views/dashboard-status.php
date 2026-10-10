<?php
/**
 * HostedCDN status on dashboard tab template.
 *
 * @since 3.5
 *
 * @param array $data {
 *    @type bool   $is_live_site    Identifies if the current website is a live or local/staging one.
 *    @type string $container_class Flex container CSS class.
 *    @type bool   $is_active       Boolean identifying the activation status.
 *    @type array  $items           List of plan info rows, each with 'label', 'value', and 'class'.
 * }
 */

$data = isset( $data ) && is_array( $data ) ? $data : []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="noobrocket-optionHeader">
	<h3 class="noobrocket-title2">HostedCDN</h3>
</div>
<div class="noobrocket-field noobrocket-field-account">
	<?php if ( ! $data['is_live_site'] ) : ?>
	<span class="noobrocket-infoAccount noobrocket-isInvalid"><?php esc_html_e( 'HostedCDN is unavailable on local domains and staging sites.', 'noobrocket' ); ?></span>
	<?php else : ?>
	<div class="noobrocket-flex<?php echo esc_attr( $data['container_class'] ); ?>">
		<div class="noobrocket-dashboard-plans">
		<?php foreach ( $data['items'] ?? [] as $noobrocket_plan_item ) : ?>
		<div>
			<span class="noobrocket-title3"><?php echo esc_html( $noobrocket_plan_item['label'] ); ?></span>
			<span class="noobrocket-infoAccount<?php echo esc_attr( $noobrocket_plan_item['class'] ); ?>"><?php echo esc_html( $noobrocket_plan_item['value'] ); ?></span>
		</div>
		<?php endforeach; ?>
		</div>
		<?php if ( ! $data['is_active'] ) : ?>
		<div>
			<a href="#page_cdn" class="noobrocket-button"><?php esc_html_e( 'Get HostedCDN Pro', 'noobrocket' ); ?></a>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
