<?php
/** Bundled local help; no remote tutorial embeds. */
defined( 'ABSPATH' ) || exit;
?>
<div id="tutorials" class="noobrocket-Page">
 <h2><?php esc_html_e( 'Documentation', 'noobrocket' ); ?></h2>
 <p><?php esc_html_e( 'Read the bundled guide for setup, local generation, caching and troubleshooting.', 'noobrocket' ); ?></p>
 <a href="<?php echo esc_url( get_noobrocket_documentation_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read the documentation', 'noobrocket' ); ?></a>
</div>
