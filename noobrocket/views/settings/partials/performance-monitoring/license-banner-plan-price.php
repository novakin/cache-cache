<?php
/**
 * License banner plan price partial.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p class="noobrocket-ri-price">
	<span class="noobrocket-currency"><strong>$</strong></span>
	<span class="noobrocket-price-number"><?php echo esc_html( $data['price_number'] ); ?></span>
	<span class="noobrocket-price-decimal"><?php echo esc_html( $data['price_decimal'] ); ?></span>
	<span class="noobrocket-period">/<strong><?php echo esc_html( $data['period'] ); ?>*</strong></span>
</p>
