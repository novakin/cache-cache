<?php
/**
 * Performance Monitoring License Banner
 *
 * @package NoobRocket
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
?>

<div class="noobrocket-ri-license-banner">
	<div class="noobrocket-ri-banner-header">
	</div>
	<div class="noobrocket-ri-banner-content">
	<div class="noobrocket-ri-benefits-list-container">
		<div class="noobrocket-ri-banner-header">
			<h2><?php esc_html_e( 'Unlock Your Site’s True Performance!', 'noobrocket' ); ?></h2>
			<p><?php echo esc_html( $data['subtitle'] ); ?></p>
		</div>

		<ul class="noobrocket-ri-benefits-list">
			<?php foreach ( $data['highlights'] as $noobrocket_highlight ) : ?>
			<li>
				<span><?php echo wp_kses_post( $noobrocket_highlight ); ?></span>
			</li>
			<?php endforeach; ?>
		</ul>
		<p class="noobrocket-ri-terms">
			<?php echo esc_html( $data['billing'] ); ?>
			<?php if ( $data['price_before_discount'] ) : ?>
				<?php echo esc_html( $data['promo_billing'] ); ?>
			<?php endif; ?>
		</p>
	</div>
		<div class="noobrocket-ri-price-box">
			<?php if ( $data['price_before_discount'] ) : ?>
			<span class="noobrocket-ri-offer"><?php echo esc_html( $data['promo_name'] ); ?></span>
			<p class="noobrocket-ri-price-before-discount">
				<?php
				printf(
				// translators: %1$s currency symbol, %2$s price before discount.
					esc_html( '%1$s%2$s' ),
					'$',
					esc_html( $data['price_before_discount'] )
				);
				?>
			</p>
			<?php endif; ?>
			<?php $this->render_license_banner_plan_price( $data['price'], '$', $data['period'] ); ?>
			<p class="noobrocket-ri-vat">
				<?php esc_html_e( 'Taxes may apply depending on your country of residence', 'noobrocket' ); ?>
			</p>
			<a href="<?php echo esc_url( $data['btn_url'] ); ?>" class="noobrocket-ri-cta-button" data-nbr_track_button="Get Performance Monitoring" data-nbr_track_context="Addons">
				<?php esc_html_e( 'GET STARTED', 'noobrocket' ); ?>
			</a>
		</div>
	</div>
	<div class="noobrocket-ri-banner-footer">
	</div>
</div>
