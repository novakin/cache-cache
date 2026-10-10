<?php

/**
 * Add "Cache options" metabox
 *
 * @since 3.15 deprecated
 * @since 2.5
 */
function noobrocket_cache_options_meta_boxes() {

	if ( ! noobrocket_can_display_options() ) {
		return;
	}

	if ( current_user_can( 'noobrocket_manage_options' ) ) {
		$cpts = get_post_types(
			[
				'public' => true,
			],
			'objects'
		);
		unset( $cpts['attachment'] );

		$cpts = apply_filters( 'noobrocket_metabox_options_post_types', $cpts );

		foreach ( $cpts as $cpt => $cpt_object ) {
			$label = $cpt_object->labels->singular_name;
			add_meta_box( 'noobrocket_post_exclude', sprintf( __( 'Cache-Cache Options', 'noobrocket' ), $label ), 'noobrocket_display_cache_options_meta_boxes', $cpt, 'side', 'core' );
		}
	}
}

/**
 * Displays some checkbox to de/activate some cache options
 *
 * @since 3.15 deprecated
 * @since 2.5
 */
function noobrocket_display_cache_options_meta_boxes() {
	if ( current_user_can( 'noobrocket_manage_options' ) ) {
		global $post, $pagenow;
		wp_nonce_field( 'noobrocket_box_option', '_noobrocketnonce', false, true );
		?>

		<div class="misc-pub-section">
			<?php
				$reject_current_uri = false;
			if ( 'post-new.php' !== $pagenow ) {
				$rejected_uris = array_flip( get_noobrocket_option( 'cache_reject_uri', [] ) );
				$path          = noobrocket_clean_exclude_file( get_permalink( $post->ID ) );

				if ( isset( $rejected_uris[ $path ] ) ) {
					$reject_current_uri = true;
				}
			}
			?>
			<input name="noobrocket_post_nocache" id="noobrocket_post_nocache" type="checkbox" title="<?php esc_html_e( 'Never cache this page', 'noobrocket' ); ?>" <?php checked( $reject_current_uri, true ); ?>><label for="noobrocket_post_nocache"><?php esc_html_e( 'Never cache this page', 'noobrocket' ); ?></label>
		</div>

		<div class="misc-pub-section">
			<p><?php esc_html_e( 'Activate these options on this post:', 'noobrocket' ); ?></p>
			<?php
			$fields = [];

			$old_fields = $fields;

			/**
			 * NoobRocket Metabox fields on post edit page.
			 *
			 * @param string[] $fields Metaboxes fields.
			 */
			$fields = apply_filters( 'noobrocket_meta_boxes_fields', $fields );

			if ( ! is_array( $fields ) ) {
				$fields = $old_fields;
			}

			foreach ( $fields as $field => $label ) {
				$disabled = disabled( ! get_noobrocket_option( $field ), true, false );
				// translators: %s is the name of the option.
				$title   = $disabled ? ' title="' . esc_attr( sprintf( __( 'Activate first the %s option.', 'noobrocket' ), $label ) ) . '"' : '';
				$class   = $disabled ? ' class="rkt-disabled"' : '';
				$checked = ! $disabled ? checked( ! get_post_meta( $post->ID, '_noobrocket_exclude_' . $field, true ), true, false ) : '';
				?>

				<input name="noobrocket_post_exclude_hidden[<?php echo esc_attr( $field ); ?>]" type="hidden" value="on">
				<input name="noobrocket_post_exclude[<?php echo esc_attr( $field ); ?>]" id="noobrocket_post_exclude_<?php echo esc_attr( $field ); ?>" type="checkbox"<?php echo $title; ?><?php echo $checked; ?><?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>>
				<label for="noobrocket_post_exclude_<?php echo esc_attr( $field ); ?>"<?php echo $title; ?><?php echo $class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>><?php echo esc_html( $label ); ?></label><br>

				<?php
			}
			?>

			<p class="rkt-note">
			<?php
			// translators: %1$s = opening strong tag, %2$s = closing strong tag.
			printf( esc_html__( '%1$sNote:%2$s None of these options will be applied if this post has been excluded from cache in the global cache settings.', 'noobrocket' ), '<strong>', '</strong>' );
			?>
			</p>
		</div>

		<?php
		/**
		 * Fires after NoobRocket’s metabox.
		 *
		 * @since 3.6
		 */
		do_action( 'noobrocket_after_options_metabox' );
	}
}

/**
 * Manage the cache options from the metabox.
 *
 * @since 3.15 deprecated
 * @since 2.5
 */
function noobrocket_save_metabox_options() {
	if ( current_user_can( 'noobrocket_manage_options' ) &&
		isset( $_POST['post_ID'], $_POST['noobrocket_post_exclude_hidden'], $_POST['_noobrocketnonce'] ) ) {

		check_admin_referer( 'noobrocket_box_option', '_noobrocketnonce' );

		// No cache field.
		if ( isset( $_POST['post_status'] ) && 'publish' === $_POST['post_status'] ) {
			$new_cache_reject_uri = $cache_reject_uri = get_noobrocket_option( 'cache_reject_uri', [] ); // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			$rejected_uris        = array_flip( $cache_reject_uri );
			$path                 = noobrocket_clean_exclude_file( get_permalink( (int) $_POST['post_ID'] ) );

			if ( isset( $_POST['noobrocket_post_nocache'] ) ) {
				if ( ! isset( $rejected_uris[ $path ] ) ) {
					array_push( $new_cache_reject_uri, $path );
				}
			} else {
				if ( isset( $rejected_uris[ $path ] ) ) {
					unset( $new_cache_reject_uri[ $rejected_uris[ $path ] ] );
				}
			}

			if ( $new_cache_reject_uri !== $cache_reject_uri ) {
				// Update the "Never cache the following pages" option.
				update_noobrocket_option( 'cache_reject_uri', $new_cache_reject_uri );

				// Update config file.
				noobrocket_generate_config_file();
			}
		}

		// Options fields.
		$fields = [];

		$old_fields = $fields;

		/**
		 * Metaboxes fields.
		 *
		 * @param string[] $fields Metaboxes fields.
		 */
		$fields = apply_filters( 'noobrocket_meta_boxes_fields', $fields );

		if ( ! is_array( $old_fields ) ) {
			$fields = $old_fields;
		}

		$fields = array_keys( $fields );

		foreach ( $fields as $field ) {
			if ( isset( $_POST['noobrocket_post_exclude_hidden'][ $field ] ) ) {
				if ( isset( $_POST['noobrocket_post_exclude'][ $field ] ) ) {
					delete_post_meta( (int) $_POST['post_ID'], '_noobrocket_exclude_' . $field );
				} else {
					if ( get_noobrocket_option( $field ) ) {
						update_post_meta( (int) $_POST['post_ID'], '_noobrocket_exclude_' . $field, true );
					}
				}
			}
		}
	}
}
