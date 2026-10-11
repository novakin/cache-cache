<?php
$rich_media = get_option( 'rich_fixture_media', [] );
$rich_hero = $rich_media[0] ?? 0;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?>
<script id="rich-jsonld" type="application/ld+json"><?php echo wp_json_encode( [ '@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'Local synthetic acceptance', 'description' => 'Inert JSON data, never a network destination.' ], JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
</head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="rich-skip" href="#rich-main">Skip to content</a>
<header class="rich-header"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Field Notes</a><button id="rich-menu-toggle" aria-expanded="false" aria-controls="rich-menu">Menu</button>
<nav id="rich-menu" hidden><a id="rich-article-link" href="<?php echo esc_url( home_url( '/rich-article-01/' ) ); ?>">First article</a><a href="<?php echo esc_url( home_url( '/category/fixture-guides/' ) ); ?>">Guides</a><a href="<?php echo esc_url( home_url( '/rich-form/' ) ); ?>">Contact</a></nav></header>
<main id="rich-main">
<section class="rich-hero"><div><p class="rich-kicker">A local journal about places and projects</p><h1 id="rich-title"><?php echo esc_html( is_404() ? 'Page not found' : ( is_search() ? 'Search results' : ( is_archive() ? single_term_title( '', false ) : get_the_title() ) ) ); ?></h1><p>Long articles, responsive images and working controls exercise actual WordPress rendering.</p></div>
<?php if ( $rich_hero ) { echo wp_get_attachment_image( $rich_hero, 'large', false, [ 'id' => 'rich-hero-image', 'loading' => 'eager', 'fetchpriority' => 'high' ] ); } ?></section>
<p id="rich-module-state">Module waiting</p><p id="rich-classic-state">Classic waiting</p>
<?php if ( is_404() ) : ?><p id="rich-not-found">RICH_NOT_FOUND</p>
<?php else : while ( have_posts() ) : the_post(); ?>
<article class="rich-article"><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_content(); ?>
<?php if ( is_singular() && ! post_password_required() ) : ?>
<?php if ( is_single() ) : ?><section id="rich-comments"><h2>Readers' field notes</h2><ol><?php wp_list_comments( [ 'avatar_size' => 0, 'style' => 'ol' ], get_comments( [ 'post_id' => get_the_ID(), 'status' => 'approve', 'number' => 10 ] ) ); ?></ol></section><?php endif; ?>
<div class="rich-controls"><button id="rich-dialog-open">Open details</button><dialog id="rich-dialog"><h2>Project details</h2><p>RICH_DIALOG_READY</p><button id="rich-dialog-close">Close details</button></dialog>
<div role="tablist" aria-label="Article sections"><button id="rich-tab-one" role="tab" aria-selected="true" aria-controls="rich-panel-one">Overview</button><button id="rich-tab-two" role="tab" aria-selected="false" aria-controls="rich-panel-two">Materials</button></div>
<section id="rich-panel-one" role="tabpanel">RICH_TAB_OVERVIEW</section><section id="rich-panel-two" role="tabpanel" hidden>RICH_TAB_MATERIALS</section>
<section class="rich-carousel" aria-label="Local image gallery"><p id="rich-slide-label">Slide 1 of 3</p><button id="rich-carousel-next">Next slide</button></section></div>
<?php if ( is_page( 'rich-form' ) ) : ?><form id="rich-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="rich_fixture_form"><?php wp_nonce_field( 'rich_fixture_form' ); ?><label>Your name <input id="rich-name" name="rich_name" required maxlength="80"></label><button id="rich-form-submit" type="submit">Send fixture form</button></form><?php endif; ?>
<?php if ( isset( $_COOKIE['wp_woocommerce_session_richfixture'] ) ) : ?><p id="rich-session-state">RICH_SESSION_PRIVATE</p><?php else : ?><p id="rich-session-state">RICH_SESSION_ANONYMOUS</p><?php endif; ?>
<section class="rich-long-content"><h2>Further down the page</h2><p>Scrolling must deliver both native image markup and stylesheet backgrounds.</p></section>
<?php foreach ( array_slice( $rich_media, 1, 4 ) as $position => $rich_image ) { echo wp_get_attachment_image( $rich_image, 'large', false, [ 'class' => 'rich-below-image', 'loading' => 'lazy', 'data-rich-image' => (string) $position ] ); } ?>
<div id="rich-late-background" class="rich-late-background"><p>RICH_BACKGROUND_CONTENT</p></div>
<a id="rich-next-link" href="<?php echo esc_url( home_url( '/rich-next-page/' ) ); ?>">Next page</a>
<?php endif; ?></article>
<?php endwhile; endif; ?>
</main><footer><p>All data, controls and media are synthetic. Every request stays on the disposable site's origin.</p><?php get_search_form(); ?></footer>
<?php wp_footer(); ?></body></html>
