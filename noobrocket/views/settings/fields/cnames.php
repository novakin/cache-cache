<?php
/** CDN hostname rows and the inert template used when adding a row. */
defined( 'ABSPATH' ) || exit;

$noobrocket_cnames = get_noobrocket_option( 'cdn_cnames', [] ) ?: [ '' ];
$noobrocket_zones  = get_noobrocket_option( 'cdn_zone', [] );
/** Filters whether this provider owns the CDN configuration. */
$noobrocket_disabled = wpm_apply_filters_typed( 'boolean', 'noobrocket_disable_cdn_option_change', false );
$noobrocket_choices  = \NoobRocket\Engine\Admin\Settings\Settings::get_cdn_scope_labels();
/** Controls whether this provider serves images. */
if ( ! wpm_apply_filters_typed( 'boolean', 'noobrocket_allow_cdn_images', true ) ) {
	unset( $noobrocket_choices['images'] );
}
?>
<div class="noobrocket-field noobrocket-field--cnames <?php echo $noobrocket_disabled ? 'noobrocket-isDisabled' : ''; ?>">
	<h3 id="cdn_cnames" class="noobrocket-title3" tabindex="-1"><?php echo esc_html( $data['label'] ); ?></h3>
	<?php if ( ! empty( $data['description'] ) ) : ?>
		<p id="cdn_cnames-description" class="noobrocket-field-description"><?php echo esc_html( $data['description'] ); ?></p>
	<?php endif; ?>
	<?php foreach ( [ 'saved' => $noobrocket_cnames, 'model' => [ '' ] ] as $noobrocket_group => $noobrocket_rows ) : ?>
		<?php if ( 'saved' === $noobrocket_group ) : ?>
			<div id="noobrocket-cnames-list">
		<?php else : ?>
			<template id="noobrocket-cname-model">
		<?php endif; ?>
		<?php foreach ( $noobrocket_rows as $noobrocket_key => $noobrocket_url ) :
			$noobrocket_index = 'saved' === $noobrocket_group ? '[' . $noobrocket_key . ']' : '[]';
			$noobrocket_id    = 'noobrocket-cname-' . $noobrocket_group . '-' . $noobrocket_key;
			$noobrocket_scope = 'saved' === $noobrocket_group ? ( $noobrocket_zones[ $noobrocket_key ] ?? 'all' ) : 'all';
			?>
			<div class="noobrocket-multiple">
				<div class="noobrocket-cname-column">
					<label for="<?php echo esc_attr( $noobrocket_id ); ?>"><?php esc_html_e( 'Hostname', 'noobrocket' ); ?></label>
					<input id="<?php echo esc_attr( $noobrocket_id ); ?>" type="text" name="noobrocket_settings[cdn_cnames]<?php echo esc_attr( $noobrocket_index ); ?>" value="<?php echo esc_attr( $noobrocket_url ); ?>" placeholder="cdn.example.com" aria-describedby="cdn_cnames-description <?php echo esc_attr( $noobrocket_id ); ?>-error" data-noobrocket-cname <?php echo $noobrocket_disabled ? 'disabled' : ''; ?> />
					<p id="<?php echo esc_attr( $noobrocket_id ); ?>-error" class="noobrocket-cname-error" data-noobrocket-cname-error hidden><?php esc_html_e( 'Enter a valid hostname or HTTP(S) URL, such as cdn.example.com.', 'noobrocket' ); ?></p>
				</div>
				<div class="noobrocket-cname-column">
					<label for="<?php echo esc_attr( $noobrocket_id ); ?>-scope"><?php esc_html_e( 'Files to serve', 'noobrocket' ); ?></label>
					<select id="<?php echo esc_attr( $noobrocket_id ); ?>-scope" name="noobrocket_settings[cdn_zone]<?php echo esc_attr( $noobrocket_index ); ?>" <?php echo $noobrocket_disabled ? 'disabled' : ''; ?>>
						<?php foreach ( $noobrocket_choices as $noobrocket_choice => $noobrocket_label ) : ?>
							<option value="<?php echo esc_attr( $noobrocket_choice ); ?>" <?php selected( $noobrocket_scope, $noobrocket_choice ); ?>><?php echo esc_html( $noobrocket_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php if ( ! $noobrocket_disabled ) : ?>
					<button type="button" class="noobrocket-multiple-close hide-if-no-js" aria-label="<?php esc_attr_e( 'Remove this CNAME', 'noobrocket' ); ?>"><svg class="noobrocket-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg></button>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<?php if ( 'saved' === $noobrocket_group ) : ?></div><?php else : ?></template><?php endif; ?>
	<?php endforeach; ?>
	<?php if ( ! $noobrocket_disabled ) : ?>
		<button type="button" class="noobrocket-button noobrocket-button--secondary noobrocket-icon-plus noobrocket-button--addMulti hide-if-no-js"><?php esc_html_e( 'Add hostname', 'noobrocket' ); ?></button>
	<?php endif; ?>
</div>
