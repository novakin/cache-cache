<?php
/**
 * Plugins section template.
 *
 * @since 3.17.2
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="plugins" class="noobrocket-Page">
	<div class="noobrocket-sectionHeader">
		<h2 class="noobrocket-title1 noobrocket-icons-plugins-hover"><?php esc_html_e( 'Our Plugins', 'noobrocket' ); ?></h2>
	</div>
	<div class="noobrocket-fieldsContainer">
		<div class="noobrocket-field-description">
			<?php
			esc_html_e( 'Beyond Cache-Cache, there\'s a whole family of plugins designed to help you build better, faster, and safer websites. Each one is crafted with our unique blend of expertise, simplicity, and outstanding support.', 'noobrocket' );
			?>
			<br><br>
			<?php
			esc_html_e( 'Combine our plugins below to build incredible WordPress websites!', 'noobrocket' );
			?>
		</div>
	</div>
		<?php
		if ( ! empty( $data ) ) :
			foreach ( $data as $key => $category ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				?>
				<div class="noobrocket-optionHeader">
					<h3 class="noobrocket-title2"><?php echo esc_html( $category['title'] ); ?></h3>
				</div>
				<div class="noobrocket-Page-row noobrocket-plugins">
					<?php foreach ( $category['plugins'] as $index => $wpm_plugin ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
					<div class="noobrocket-Page-col-half noobrocket-plugins--box">
						<div class="noobrocket-plugins--logo">
							<img src="<?php echo esc_url( NOOBROCKET_ASSETS_IMG_URL . $wpm_plugin['logo']['file'] ); ?>"
								alt="<?php echo esc_attr( $wpm_plugin['title'] ); ?>"
								loading="lazy"
								style="width: <?php echo esc_attr( $wpm_plugin['logo']['width'] ); ?>" />
						</div>
						<div class="noobrocket-title3">
							<?php echo esc_html( $wpm_plugin['title'] ); ?>
						</div>
						<p>
							<?php echo esc_html( $wpm_plugin['desc'] ); ?>
						</p>
						<div class="noobrocket-plugins--meta">
							<?php if ( '#' === $wpm_plugin['cta']['url'] ) : ?>
								<div>
									<span><?php echo esc_html( $wpm_plugin['cta']['text'] ); ?></span>
									<span class="dashicons dashicons-yes"></span>
								</div>
							<?php else : ?>
							<a class="noobrocket-button noobrocket-button--black" href="<?php echo esc_html( $wpm_plugin['cta']['url'] ); ?>">
								<?php echo esc_html( $wpm_plugin['cta']['text'] ); ?>
							</a>

							<a target="_blank" href="<?php echo esc_html( $wpm_plugin['link'] ); ?>">
								<?php esc_html_e( 'Learn More', 'noobrocket' ); ?>
							</a>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<?php
			endforeach;
		endif
		?>
	</div>

