<?php
/**
 * Categorized JavaScript exclusion catalog.
 *
 * @param array $data Field label, description, categories and selected item IDs.
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="<?php echo esc_attr( $data['id'] ); ?>" class="noobrocket-field noobrocket-multiple-select noobrocket-field--categorizedmultiselect <?php echo esc_attr( $data['container_class'] ); ?>" data-default="<?php echo ( ! empty( $data['default'] ) ? 'noobrocket-radio-' . esc_attr( $data['default'] ) : '' ); ?>" <?php echo $data['parent']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing escaped parent attributes. ?>>
	<div class="noobrocket-field-description-label"><?php echo esc_html( $data['label'] ); ?></div>
	<div class="noobrocket-field-description">
		<p><?php echo esc_html( $data['description'] ); ?></p>
		<?php if ( ! empty( $data['sub_description'] ) ) : ?>
			<p><?php echo esc_html( $data['sub_description'] ); ?></p>
		<?php endif; ?>
	</div>
	<div class="noobrocket-subsection">
		<?php
		foreach ( [ 'third_parties' => __( '3rd parties', 'noobrocket' ), 'wordpress' => __( 'WordPress', 'noobrocket' ) ] as $noobrocket_group_key => $noobrocket_group_title ) :
			if ( $data['items'][ $noobrocket_group_key ]['has_subcats'] ) :
				?>
				<div class="noobrocket-field-description-label noobrocket-mt-2"><?php echo esc_html( $noobrocket_group_title ); ?></div>
				<?php
			endif;
			foreach ( $data['items'][ $noobrocket_group_key ] as $noobrocket_item_key => $noobrocket_item ) :
				if ( 'has_subcats' === $noobrocket_item_key || empty( $noobrocket_item['items'] ) ) {
					continue;
				}
				$noobrocket_category_id = $data['id'] . '-' . $noobrocket_group_key . '-' . $noobrocket_item_key;
				?>
				<div class="noobrocket-list" id="<?php echo esc_attr( $noobrocket_category_id ); ?>">
					<button type="button" class="noobrocket-list-header" id="<?php echo esc_attr( $noobrocket_category_id . '-toggle' ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $noobrocket_category_id . '-body' ); ?>">
						<span class="noobrocket-list-header-data">
							<img src="<?php echo esc_url( NOOBROCKET_ASSETS_IMG_URL . $noobrocket_item['svg-icon'] . '.svg' ); ?>" alt="" />
							<span class="noobrocket-multiple-select-title"><?php echo esc_html( $noobrocket_item['title'] ); ?></span>
							<span class="noobrocket-badge-counter" aria-live="polite" aria-atomic="true"><span class="screen-reader-text"><?php esc_html_e( 'Selected exclusions:', 'noobrocket' ); ?> </span><span data-noobrocket-selection-count></span></span>
						</span>
						<span class="noobrocket-list-header-arrow" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" height="100%" width="100%"><path d="M233.4 105.4c12.5-12.5 32.8-12.5 45.3 0l192 192c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L256 173.3 86.6 342.6c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l192-192z"/></svg>
						</span>
					</button>
					<div class="noobrocket-list-body" id="<?php echo esc_attr( $noobrocket_category_id . '-body' ); ?>" hidden>
						<ul>
							<li>
								<div class="noobrocket-checkbox noobrocket-select-all">
									<input id="<?php echo esc_attr( $noobrocket_category_id . '-all' ); ?>" class="noobrocket-main-checkbox" type="checkbox" />
									<label for="<?php echo esc_attr( $noobrocket_category_id . '-all' ); ?>"><?php esc_html_e( 'Select all', 'noobrocket' ); ?></label>
								</div>
							</li>
							<?php foreach ( $noobrocket_item['items'] as $noobrocket_oneitem_key => $noobrocket_oneitem ) : ?>
								<li>
									<div class="noobrocket-checkbox">
										<input id="<?php echo esc_attr( $noobrocket_category_id . '-item-' . $noobrocket_oneitem_key ); ?>" type="checkbox" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>][]" value="<?php echo esc_attr( $noobrocket_oneitem['id'] ); ?>" <?php checked( in_array( $noobrocket_oneitem['id'], $data['selected'], true ) ); ?> />
										<label for="<?php echo esc_attr( $noobrocket_category_id . '-item-' . $noobrocket_oneitem_key ); ?>">
											<?php if ( ! empty( $noobrocket_oneitem['icon'] ) ) : ?>
												<img src="<?php echo esc_url( $noobrocket_oneitem['icon'] ); ?>" alt="" />
											<?php endif; ?>
											<?php echo esc_html( $noobrocket_oneitem['title'] ); ?>
										</label>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endforeach; ?>
		<?php unset( $noobrocket_group_key, $noobrocket_group_title, $noobrocket_item_key, $noobrocket_item, $noobrocket_category_id, $noobrocket_oneitem_key, $noobrocket_oneitem ); ?>
	</div>
</div>
