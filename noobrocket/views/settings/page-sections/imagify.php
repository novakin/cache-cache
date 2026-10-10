<?php
/**
 * Imagify section template.
 *
 * @since 3.2
 */

defined( 'ABSPATH' ) || exit;

?>

<div id="imagify" class="noobrocket-Page">
	<div class="noobrocket-sectionHeader">
		<h2 class="noobrocket-title1 noobrocket-icon-imagify-hover"><?php esc_html_e( 'Image Optimization', 'noobrocket' ); ?></h2>
	</div>
	<pre>
</pre>
	<div class="noobrocket-mt-2">
		<h4 class="noobrocket-title2">
			<?php
			// Translators: %1$s = <strong>, %2$s = </strong>.
			printf( esc_html__( '%1$sImagify can optimize your website’s images for an extra speed boost.%2$s', 'noobrocket' ), '<strong>', '</strong>' );
			?>
		</h4>
	</div>
	
	<p class="noobrocket-fs-md">
		<?php esc_html_e( 'Images can account for 50% of your loading time!', 'noobrocket' ); ?>
	</p>
	<p class="noobrocket-fs-md">
		<?php esc_html_e( 'Imagify automatically optimizes all your images, helping your website gain precious seconds while saving you time. With just one click, it resizes, compresses, and converts your images to WebP and AVIF formats without sacrificing quality.', 'noobrocket' ); ?>
	</p>

	<div class="noobrocket-imagify">
		<div class="noobrocket-imagify-description">
			<ul>
				<li class="noobrocket-imagify-info">
					<span class="text">
						<?php
						// Translators: %1$s = <strong>, %2$s = </strong>.
						printf( esc_html__( '%1$sCompress%2$s all your images in one click', 'noobrocket' ), '<strong>', '</strong>' );
						?>
					</span>
				</li>
				<li class="noobrocket-imagify-info">
					<span class="text">
						<?php
						// Translators: %1$s = <strong>, %2$s = </strong>.
						printf( esc_html__( '%1$sConvert%2$s images to WebP and Avif', 'noobrocket' ), '<strong>', '</strong>' );
						?>
					</span>
				</li>
				<li class="noobrocket-imagify-info">
					<span class="text">
						<?php
						// Translators: %1$s = <strong>, %2$s = </strong>.
						printf( esc_html__( '%1$sResize%2$s your images on the fly', 'noobrocket' ), '<strong>', '</strong>' );
						?>
					</span>
				</li>
				<li class="noobrocket-imagify-info">
					<span class="text">
						<?php
						// Translators: %1$s = <strong>, %2$s = </strong>.
						printf( esc_html__( '%1$sFree plan%2$s includes 20MB/month (around 200 images)', 'noobrocket' ), '<strong>', '</strong>' );
						?>
					</span>
				</li>
			</ul>

		</div>
		<div class="noobrocket-imagify-screenshot">
			<img src="<?php echo esc_attr( NOOBROCKET_ASSETS_IMG_URL . 'imagify-score.png' ); ?>" alt="" width="613" height="394">
		</div>
	</div>
	<?php if ( $data ) : ?>
		<div class="noobrocket-imagify-plugin-tile">
			<?php
			$icon_url = ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			if ( ! empty( $data->icons['svg'] ) ) {
				$icon_url = $data->icons['svg']; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			} elseif ( ! empty( $data->icons['1x'] ) ) {
				$icon_url = $data->icons['1x']; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			}
			if ( ! empty( $icon_url ) ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				?>
			<img src="<?php echo esc_url( $icon_url ); ?>" alt="Imagify logo" width="65" height="65">
			<?php endif; ?>
			<div class="noobrocket-imagify-plugin-tile-info">
				<h4 class="noobrocket-imagify-plugin-tile-title">
					<?php
					// Translators: %1$s = <strong>, %2$s = </strong>.
					printf( esc_html__( '%1$sInstall Imagify, the Easiest WordPress Image Optimizer%2$s', 'noobrocket' ), '<strong>', '</strong>' );
					?>
				</h4>
				<div class="noobrocket-star-rating">
					<?php
						wp_star_rating(
							[
								'rating' => ( $data->rating / 100 ) * 5,
								'type'   => 'rating',
								'number' => $data->num_ratings,
							]
							);
					?>
					<div class="num-ratings" aria-hidden="true">(<?php echo esc_html( number_format_i18n( $data->num_ratings ) ); ?>)</div>					
				</div>
				<div class="noobrocket-fs-sm">
				<?php echo esc_html( number_format_i18n( $data->active_installs ) ); ?>+ Active installations
				</div>
			</div>
			<?php
			if ( ! \Imagify_Partner::is_imagify_activated() ) {
				$imagify = new \Imagify_Partner( 'noobrocket' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

				if ( \Imagify_Partner::is_imagify_installed() ) {
					$button_text = __( 'Activate Imagify', 'noobrocket' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$button_icon = 'noobrocket-icon-chevron-down'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				} else {
					$button_text = __( 'Install Imagify', 'noobrocket' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$button_icon = 'noobrocket-imagify-install'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				}

				echo '<a class="noobrocket-button ' . esc_attr( $button_icon ) . '" href="' . esc_url( $imagify->get_post_install_url() ) . '">' . esc_html( $button_text ) . '</a>';
			}
			?>
		</div>
	<?php endif; ?>
</div>
