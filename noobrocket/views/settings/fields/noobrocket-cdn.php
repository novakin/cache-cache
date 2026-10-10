<?php
/**
 * HostedCDN template.
 *
 * @since 3.5
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_cnames      = get_noobrocket_option( 'cdn_cnames' );
$noobrocket_cnames_zone = get_noobrocket_option( 'cdn_zone' );
?>
<div class="noobrocket-fieldsContainer-fieldset">
	<div class="noobrocket-field">
		<div class="noobrocket-field-description-label">
			<?php echo $data['label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
		<?php if ( ! empty( $data['description'] ) ) : ?>
			<div class="noobrocket-field-description">
				<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
			</div>
		<?php endif; ?>
		<div id="noobrocket-cnames-list">
		<?php
		if ( $noobrocket_cnames ) :
			foreach ( $noobrocket_cnames as $key => $url ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				?>
				<div class="noobrocket-text">
					<label for="cdn_cnames_<?php echo esc_attr( $key ); ?>" class="screen-reader-text"><?php esc_html_e( 'CDN CNAME', 'noobrocket' ); ?></label>
					<input type="text" id="cdn_cnames_<?php echo esc_attr( $key ); ?>" name="noobrocket_settings[cdn_cnames][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $url ); ?>" placeholder="cdn.example.com" />
					<input type="hidden" name="noobrocket_settings[cdn_zone][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $noobrocket_cnames_zone[ $key ] ); ?>" />
					<?php if ( ! empty( $data['helper'] ) ) : ?>
					<div class="noobrocket-field-description noobrocket-field-description-helper">
						<?php echo $data['helper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
					</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="noobrocket-text">
				<label for="cdn_cnames" class="screen-reader-text"><?php esc_html_e( 'CDN CNAME', 'noobrocket' ); ?></label>
				<input type="text" id="cdn_cnames" name="noobrocket_settings[cdn_cnames][]" value="" placeholder="xxxxxx.noobrocketcdn.me" />
				<input type="hidden" name="noobrocket_settings[cdn_zone][]" value="all" />
				<?php if ( ! empty( $data['helper'] ) ) : ?>
				<div class="noobrocket-field-description noobrocket-field-description-helper">
					<?php echo $data['helper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
				</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		</div>
	</div>
</div>
<div class="noobrocket-optionHeader">
	<h3 class="noobrocket-title2"><?php esc_html_e( 'Purge HostedCDN cache', 'noobrocket' ); ?></h3>
</div>
<div class="noobrocket-fieldsContainer">
	<div class="noobrocket-fieldsContainer-description">
		<?php
		printf(
			// translators: %s is a "Learn more" link.
			esc_html__( 'Purges HostedCDN cached resources for your website. %s', 'noobrocket' ),
			'<a href="' . esc_url( $data['beacon']['url'] ) . '" data-beacon-article="' . esc_attr( $data['beacon']['id'] ) . '" rel="noopener noreferrer" target="_blank">' . esc_html__( 'Learn more', 'noobrocket' ) . '</a>'
		);
		?>
	</div><br>
	<?php
	$this->render_action_button(
		'link',
		'noobrocket_purge_noobrocketcdn',
		[
			'label'      => __( 'Clear all HostedCDN cache files', 'noobrocket' ),
			'attributes' => [
				'class' => 'noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-icon-trash',
			],
		]
	);
	?>
</div>
