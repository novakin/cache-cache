<?php
/**
 * HostedCDN small CTA template.
 *
 * @since 3.5
 *
 * @param array $data {
 *      @type string $container_class container CSS class.
 *      @type string $promotion_campaign Promotion campaign title.
 *      @type string $promotion_end_date Promotion end date.
 *      @type string $nopromo_variant CSS modifier for the no promotion display.
 *      @type string $regular_price HostedCDN regular price.
 *      @type string $current_price HostedCDN current price.
 *      @type string $regular_price_monthly HostedCDN regular monthly price.
 *      @type string $regular_price_annual HostedCDN regular annual price.
 *      @type string $current_price_monthly HostedCDN current monthly price.
 *      @type string $current_price_annual HostedCDN current annual price.
 *      @type string $cta_heading CTA toggle text with strong-wrapped heading.
 *      @type string $cta_description CTA description text.
 * }
 */

defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

$data = isset( $data ) && is_array( $data ) ? $data : []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$noobrocketcdn_container_classes = [ 'noobrocket-noobrocketcdn-cta' ];

if ( empty( $data['is_visible'] ) ) {
	$noobrocketcdn_container_classes[] = 'noobrocket-isHidden';
}

if ( ! empty( $data['is_visible'] ) && ! empty( $data['is_expanded'] ) ) {
	$noobrocketcdn_container_classes[] = 'noobrocket-noobrocketcdn-cta--expanded';
	$noobrocketcdn_container_classes[] = 'noobrocket-noobrocketcdn-cta---max-limit';
}

if ( ! empty( $data['is_visible'] ) && empty( $data['is_expanded'] ) ) {
	$noobrocketcdn_container_classes[] = 'noobrocket-noobrocketcdn-cta--collapsed';
}

if ( ! empty( $data['container_class'] ) ) {
	$noobrocketcdn_container_classes[] = $data['container_class'];
}

?>

<div class="<?php echo esc_attr( implode( ' ', $noobrocketcdn_container_classes ) ); ?>" id="noobrocket-noobrocketcdn-cta">
	<div class="noobrocket-noobrocketcdn-cta-toggle noobrocket-noobrocketcdn-cta-toggle--default" role="button" tabindex="0" aria-controls="noobrocket-noobrocketcdn-cta-expandable" aria-expanded="true">
		<p class="noobrocket-noobrocketcdn-cta-toggle__text">
			<?php echo wp_kses( $data['cta_heading'], [ 'strong' => [] ] ); ?>
		</p>
		<span class="noobrocket-noobrocketcdn-cta-toggle__icon" aria-hidden="true"></span>
	</div>

	<div class="noobrocket-noobrocketcdn-cta-toggle noobrocket-noobrocketcdn-cta-toggle--max-limit" role="button" tabindex="0" aria-controls="noobrocket-noobrocketcdn-cta-expandable" aria-expanded="true">
		<span class="noobrocket-noobrocketcdn-cta-toggle__check" aria-hidden="true"></span>
		<p class="noobrocket-noobrocketcdn-cta-toggle__text">
			<?php echo wp_kses( $data['cta_heading_max_limit'], [ 'strong' => [] ] ); ?>
			<br />
			<span><?php echo esc_html( $data['cta_description'] ); ?></span>
		</p>
		<span class="noobrocket-noobrocketcdn-cta-toggle__icon" aria-hidden="true"></span>
	</div>

	<div class="noobrocket-noobrocketcdn-cta-separator" aria-hidden="true"></div>

	<div class="noobrocket-noobrocketcdn-cta-expandable" id="noobrocket-noobrocketcdn-cta-expandable">
		<?php if ( ! empty( $data['promotion_campaign'] ) ) : ?>
			<div class="noobrocket-flex noobrocket-noobrocketcdn-promo">
				<h3 class="noobrocket-noobrocketcdn-promo-title"><?php echo esc_html( $data['promotion_campaign'] ); ?></h3>
				<p class="noobrocket-title2 noobrocket-noobrocketcdn-promo-date">
					<?php
					printf(
					// Translators: %s = date formatted using date_i18n() and get_option( 'date_format' ).
						esc_html__( 'Valid until %s only!', 'noobrocket' ),
						esc_html( $data['promotion_end_date'] )
					);
					?>
				</p>
			</div>
		<?php endif; ?>
		<section class="noobrocket-noobrocketcdn-cta-content<?php echo esc_attr( $data['nopromo_variant'] ); ?> noobrocket-flex">
			<div class="noobrocket-noobrocketcdn-content">
				<h3 class="noobrocket-title2">
					<?php echo esc_html( 'HostedCDN' ); ?>
					<span class="noobrocket-badge noobrocket-badge--blue"><?php esc_html_e( 'Pro', 'noobrocket' ); ?></span>
				</h3>
				<ul class="noobrocket-noobrocketcdn-features">
					<li class="noobrocket-noobrocketcdn-feature noobrocket-noobrocketcdn-bandwidth">
						<?php
						// translators: %1$s = opening strong tag, %2$s = closing strong tag.
						printf( esc_html__( 'Faster content delivery across %1$syour entire website%2$s', 'noobrocket' ), '<strong>', '</strong>' );
						?>
					</li>
					<li class="noobrocket-noobrocketcdn-feature noobrocket-noobrocketcdn-configuration">
						<?php
						// translators: %1$s = opening strong tag, %2$s = closing strong tag.
						printf( esc_html__( '%1$sNo bandwidth limits%2$s, no hidden costs', 'noobrocket' ), '<strong>', '</strong>' );
						?>
					</li>
					<li class="noobrocket-noobrocketcdn-feature noobrocket-noobrocketcdn-automatic">
						<?php
						// translators: %1$s = opening strong tag, %2$s = closing strong tag.
						printf( esc_html__( '%1$s100+ edge locations%2$s for wider global coverage', 'noobrocket' ), '<strong>', '</strong>' );
						?>
					</li>
					<li class="noobrocket-noobrocketcdn-cta-footer">
						<a href="https://www.noobunbox.net/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn more about HostedCDN', 'noobrocket' ); ?></a>
					</li>
					<?php if ( ! empty( $data['promotion_campaign'] ) ) : ?>
						<li class="noobrocket-noobrocketcdn-cta-promo-footer">
							<?php
							printf(
							// translators: %1$s = discounted price, %2$s = regular price.
								esc_html__( '*$%1$s/month for 12 months then $%2$s/month. You can cancel your subscription at any time.', 'noobrocket' ),
								esc_html( str_replace( '*', '', $data['current_price'] ) ),
								esc_html( $data['regular_price'] )
							);
							?>
						</li>
					<?php endif; ?>
				</ul>
			</div>
			<div class="noobrocket-noobrocketcdn-pricing">
					<?php if ( ! empty( $data['error'] ) ) : ?>
						<p><?php echo $data['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<?php else : ?>
						<div class="noobrocket-noobrocketcdn-pricing--content">
							<div class="noobrocket-noobrocketcdn-pricing--toggle">
								<input type="checkbox" class="noobrocket-noobrocketcdn-toggle--input" id="noobrocket-noobrocketcdn-toggle-input">
								<label class="noobrocket-noobrocketcdn-toggle" for="noobrocket-noobrocketcdn-toggle-input">
									<span class="noobrocket-noobrocketcdn-toggle--slider"></span>
									<span class="noobrocket-noobrocketcdn-pricing--toggle-label noobrocket-noobrocketcdn-pricing--toggle-label--inactive"><?php esc_html_e( 'Monthly', 'noobrocket' ); ?></span>
									<span class="noobrocket-noobrocketcdn-pricing--toggle-label noobrocket-noobrocketcdn-pricing--toggle-label--active"><?php esc_html_e( 'Yearly', 'noobrocket' ); ?></span>
								</label>
								<div class="noobrocket-noobrocketcdn-pricing--badge"><?php esc_html_e( '2 Months Free!', 'noobrocket' ); ?></div>
							</div>
							<div class="noobrocket-noobrocketcdn-pricing--price-container">
								<?php if ( ! empty( $data['regular_price_monthly'] ) ) : ?>
								<h4 class="noobrocket-title2 noobrocket-noobrocketcdn-pricing-regular">
									<del>
										<span class="noobrocket-noobrocketcdn-pricing-regular-price noobrocket-noobrocketcdn-pricing-regular-price--monthly">$<?php echo esc_html( $data['regular_price_monthly'] ); ?></span>
										<span class="noobrocket-noobrocketcdn-pricing-regular-price noobrocket-noobrocketcdn-pricing-regular-price--yearly noobrocket-isHidden">$<?php echo esc_html( $data['regular_price_annual'] ); ?></span>
									</del>
								</h4>
								<?php endif; ?>

								<div class="noobrocket-noobrocketcdn-pricing--price">
									<span class="noobrocket-noobrocketcdn-pricing--currency">$</span>
									<?php
									// Handle both period and comma as decimal separators for i18n.
									// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable, not a global.
									$monthly_decimal_pos = max( (int) strpos( $data['current_price_monthly'], '.' ), (int) strpos( $data['current_price_monthly'], ',' ) );
									// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variable, not a global.
									$annual_decimal_pos = max( (int) strpos( $data['current_price_annual'], '.' ), (int) strpos( $data['current_price_annual'], ',' ) );
									?>
									<span class="noobrocket-noobrocketcdn-pricing--amount noobrocket-noobrocketcdn-pricing--monthly"><?php echo esc_html( substr( $data['current_price_monthly'], 0, $monthly_decimal_pos ) ); ?></span>
									<span class="noobrocket-noobrocketcdn-pricing--cents noobrocket-noobrocketcdn-pricing--monthly"><?php echo esc_html( substr( $data['current_price_monthly'], $monthly_decimal_pos ) ); ?></span>

									<span class="noobrocket-noobrocketcdn-pricing--amount noobrocket-noobrocketcdn-pricing--annual noobrocket-isHidden"><?php echo esc_html( substr( $data['current_price_annual'], 0, $annual_decimal_pos ) ); ?></span>
									<span class="noobrocket-noobrocketcdn-pricing--cents noobrocket-noobrocketcdn-pricing--annual noobrocket-isHidden"><?php echo esc_html( substr( $data['current_price_annual'], $annual_decimal_pos ) ); ?></span>
								</div>
								<div class="noobrocket-noobrocketcdn-pricing--billing">
									<div class="noobrocket-noobrocketcdn-pricing--billing-period">
										<span class="noobrocket-noobrocketcdn-pricing--billing-period--monthly"><?php esc_html_e( 'Billed Monthly.', 'noobrocket' ); ?></span>
										<span class="noobrocket-noobrocketcdn-pricing--billing-period--yearly noobrocket-isHidden"><?php esc_html_e( 'per month, billed yearly', 'noobrocket' ); ?></span>
									</div>
									<span class="noobrocket-noobrocketcdn-pricing--billing-vat">(<?php esc_html_e( 'excl. VAT', 'noobrocket' ); ?>)</span>
								</div>
							</div>

							<button class="noobrocket-button noobrocket-noobrocketcdn-pricing--cta noobrocket-noobrocketcdn-open"<?php echo empty( $data['button_url'] ) ? ' data-micromodal-trigger="noobrocket-noobrocketcdn-modal"' : ''; ?>><?php esc_html_e( 'Upgrade Now', 'noobrocket' ); ?></button>
						</div>
						
					<?php endif; ?>
				</div>
		</section>
	</div>
</div>
