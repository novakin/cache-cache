<?php
/** Optional Google Lighthouse checks; credentials and checks have separate ownership. */
defined( 'ABSPATH' ) || exit;
$configured  = ! empty( $data['configured'] );
$public_url  = ! empty( $data['public_url'] );
$can_run     = $configured && $public_url;
$state       = ! $public_url ? __( 'Unavailable here', 'noobrocket' ) : ( $configured ? __( 'API key saved', 'noobrocket' ) : __( 'API key required', 'noobrocket' ) );
$key_error   = 'error' === ( $data['notice']['type'] ?? '' );
$key_invalid = $key_error && 'invalid_key' === ( $data['notice']['code'] ?? '' );
$categories = [
	'performance'    => __( 'Performance', 'noobrocket' ),
	'accessibility'  => __( 'Accessibility', 'noobrocket' ),
	'best-practices' => __( 'Best practices', 'noobrocket' ),
	'seo'            => __( 'SEO', 'noobrocket' ),
];
$metrics = [
	'fcp' => __( 'First contentful paint', 'noobrocket' ),
	'lcp' => __( 'Largest contentful paint', 'noobrocket' ),
	'tbt' => __( 'Total blocking time', 'noobrocket' ),
	'cls' => __( 'Cumulative layout shift', 'noobrocket' ),
];
$grades = [
	'good'        => __( 'Good', 'noobrocket' ),
	'improve'     => __( 'Needs improvement', 'noobrocket' ),
	'poor'        => __( 'Poor', 'noobrocket' ),
	'unavailable' => __( 'Unavailable', 'noobrocket' ),
];
?>
<section class="noobrocket-surface noobrocket-pagespeed" aria-labelledby="noobrocket-pagespeed-title"
	data-noobrocket-pagespeed data-nonce="<?php echo esc_attr( wp_create_nonce( 'noobrocket_pagespeed_run' ) ); ?>"
	data-running="<?php esc_attr_e( 'Google is checking your homepage. This may take a minute.', 'noobrocket' ); ?>"
	data-complete="<?php esc_attr_e( 'PageSpeed check complete.', 'noobrocket' ); ?>"
	data-error="<?php esc_attr_e( 'The check could not finish. Try again. Previous results are preserved.', 'noobrocket' ); ?>"
	data-good="<?php echo esc_attr( $grades['good'] ); ?>" data-improve="<?php echo esc_attr( $grades['improve'] ); ?>"
	data-poor="<?php echo esc_attr( $grades['poor'] ); ?>" data-unavailable="<?php echo esc_attr( $grades['unavailable'] ); ?>">
	<div class="noobrocket-surface-head">
		<h2 id="noobrocket-pagespeed-title"><svg class="noobrocket-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19a9 9 0 1 1 16 0M12 13l4-5M5 19h14"/><circle cx="12" cy="13" r="1.5"/></svg><?php esc_html_e( 'PageSpeed Insights', 'noobrocket' ); ?></h2>
		<span class="noobrocket-badge is-neutral"><?php echo esc_html( $state ); ?></span>
	</div>
	<div class="noobrocket-card-body">
		<?php if ( ! empty( $data['notice'] ) ) : ?>
			<?php if ( 'success' === $data['notice']['type'] ) : ?>
			<div class="notice notice-success" data-noobrocket-toast><p><?php echo esc_html( $data['notice']['message'] ); ?></p></div>
			<?php else : ?>
			<div class="notice notice-error<?php echo 'invalid_key' === ( $data['notice']['code'] ?? '' ) ? ' inline' : ''; ?>" id="noobrocket-pagespeed-key-error" role="alert" tabindex="-1"<?php echo 'invalid_key' === ( $data['notice']['code'] ?? '' ) ? ' data-noobrocket-inline-feedback' : ' data-noobrocket-toast'; ?>><p><?php echo esc_html( $data['notice']['message'] ); ?></p></div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( ! $public_url ) : ?>
		<p><?php esc_html_e( 'PageSpeed cannot check this local or private address. Use it on a public site.', 'noobrocket' ); ?></p>
		<?php elseif ( ! $configured ) : ?>
		<p><?php esc_html_e( 'Save your own Google API key to check mobile or desktop.', 'noobrocket' ); ?></p>
		<?php else : ?>
		<p><?php esc_html_e( 'Run a mobile or desktop check of your public homepage.', 'noobrocket' ); ?></p>
		<?php endif; ?>
		<p class="noobrocket-pagespeed-url"><?php echo esc_html( $data['url'] ); ?></p>
		<p class="noobrocket-helper"><?php esc_html_e( 'Checks run only when you request them and send this homepage URL to Google.', 'noobrocket' ); ?></p>
		<details class="noobrocket-pagespeed-setup"<?php echo $key_error || ( $public_url && ! $configured ) ? ' open' : ''; ?>>
			<summary><?php echo esc_html( $configured ? __( 'API key saved — manage key', 'noobrocket' ) : __( 'Connect your Google API key', 'noobrocket' ) ); ?></summary>
			<div class="noobrocket-field">
				<label for="noobrocket-pagespeed-api-key"><?php echo esc_html( $configured ? __( 'Replacement API key', 'noobrocket' ) : __( 'PageSpeed Insights API key', 'noobrocket' ) ); ?></label>
				<div class="noobrocket-secret-field">
					<input type="password" id="noobrocket-pagespeed-api-key" name="api_key" form="noobrocket-pagespeed-key" value="" autocomplete="new-password" spellcheck="false" autocapitalize="none" aria-describedby="noobrocket-pagespeed-key-help<?php echo $key_error ? ' noobrocket-pagespeed-key-error' : ''; ?>"<?php echo $key_invalid ? ' aria-invalid="true"' : ''; ?>>
					<button type="button" class="noobrocket-secret-toggle hide-if-no-js" data-noobrocket-secret-toggle aria-controls="noobrocket-pagespeed-api-key" aria-pressed="false"
						data-show="<?php esc_attr_e( 'Show', 'noobrocket' ); ?>" data-hide="<?php esc_attr_e( 'Hide', 'noobrocket' ); ?>"
						data-show-label="<?php esc_attr_e( 'Show PageSpeed API key', 'noobrocket' ); ?>" data-hide-label="<?php esc_attr_e( 'Hide PageSpeed API key', 'noobrocket' ); ?>"
						aria-label="<?php esc_attr_e( 'Show PageSpeed API key', 'noobrocket' ); ?>"><?php esc_html_e( 'Show', 'noobrocket' ); ?></button>
				</div>
				<p class="noobrocket-helper" id="noobrocket-pagespeed-key-help"><?php esc_html_e( 'Use your own key with the PageSpeed Insights API enabled. The saved key is kept on your WordPress server; it is never filled into this field.', 'noobrocket' ); ?></p>
				<div class="noobrocket-status-actions">
					<button type="submit" class="noobrocket-button" form="noobrocket-pagespeed-key" name="operation" value="save_key"><?php echo esc_html( $configured ? __( 'Replace API key', 'noobrocket' ) : __( 'Save API key', 'noobrocket' ) ); ?></button>
					<?php if ( $configured ) : ?><button type="submit" class="noobrocket-button" form="noobrocket-pagespeed-key" name="operation" value="remove_key"><?php esc_html_e( 'Remove API key', 'noobrocket' ); ?></button><?php endif; ?>
				</div>
				<div class="noobrocket-status-navigation">
					<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get an API key', 'noobrocket' ); ?></a>
					<a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Enable API and setup help', 'noobrocket' ); ?></a>
				</div>
			</div>
		</details>
		<p class="noobrocket-helper hide-if-js"><?php esc_html_e( 'PageSpeed checks require JavaScript. You can still review saved results and manage the API key.', 'noobrocket' ); ?></p>
		<p class="noobrocket-pagespeed-status" data-noobrocket-pagespeed-status role="status" aria-live="polite" aria-atomic="true"></p>
	</div>
	<details class="noobrocket-pagespeed-checks"<?php echo $can_run || ! empty( $data['results'] ) ? ' open' : ''; ?>>
	<summary><?php esc_html_e( 'Mobile and desktop checks', 'noobrocket' ); ?></summary>
	<div class="noobrocket-pagespeed-results">
		<?php foreach ( [ 'mobile' => __( 'Mobile', 'noobrocket' ), 'desktop' => __( 'Desktop', 'noobrocket' ) ] as $strategy => $label ) :
			$result     = $data['results'][ $strategy ] ?? null;
			$checked_at = (int) ( $result['checked_at'] ?? 0 );
			$score       = $result['scores']['performance'] ?? null;
			$grade       = null === $score ? 'unavailable' : ( $score >= 90 ? 'good' : ( $score >= 50 ? 'improve' : 'poor' ) );
			$grade_class = [ 'unavailable' => 'is-neutral', 'good' => 'is-success', 'improve' => 'is-warning', 'poor' => 'is-error' ][ $grade ];
			?>
		<section class="noobrocket-pagespeed-result" data-noobrocket-pagespeed-result="<?php echo esc_attr( $strategy ); ?>" aria-labelledby="noobrocket-pagespeed-<?php echo esc_attr( $strategy ); ?>">
			<div class="noobrocket-pagespeed-result-head">
				<h3 id="noobrocket-pagespeed-<?php echo esc_attr( $strategy ); ?>"><?php echo esc_html( $label ); ?></h3>
				<button type="button" class="noobrocket-button hide-if-no-js" data-noobrocket-pagespeed-run="<?php echo esc_attr( $strategy ); ?>"<?php disabled( ! $can_run ); ?>><?php echo esc_html( 'mobile' === $strategy ? __( 'Check mobile', 'noobrocket' ) : __( 'Check desktop', 'noobrocket' ) ); ?></button>
			</div>
			<p class="noobrocket-helper" data-noobrocket-pagespeed-empty<?php echo $result ? ' hidden' : ''; ?>><?php esc_html_e( 'No check recorded yet.', 'noobrocket' ); ?></p>
			<div data-noobrocket-pagespeed-data<?php echo $result ? '' : ' hidden'; ?>>
				<p class="noobrocket-helper"><?php esc_html_e( 'Last check:', 'noobrocket' ); ?> <time data-noobrocket-pagespeed-time<?php echo $checked_at ? ' datetime="' . esc_attr( gmdate( 'c', $checked_at ) ) . '"' : ''; ?>><?php echo esc_html( $checked_at ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $checked_at ) : '' ); ?></time></p>
				<dl class="noobrocket-pagespeed-scores">
					<div><dt><?php echo esc_html( $categories['performance'] ); ?></dt><dd><strong data-noobrocket-pagespeed-score="performance"><?php echo esc_html( null === $score ? '—' : $score ); ?></strong><span class="noobrocket-badge <?php echo esc_attr( $grade_class ); ?>" data-noobrocket-pagespeed-grade="performance"><?php echo esc_html( $grades[ $grade ] ); ?></span></dd></div>
				</dl>
				<dl class="noobrocket-key-values noobrocket-pagespeed-metrics">
					<?php foreach ( [ 'fcp', 'lcp' ] as $metric ) :
						$value = $result['metrics'][ $metric ] ?? null;
						$formatted = null === $value ? $grades['unavailable'] : number_format_i18n( $value / 1000, 2 ) . ' s';
						?>
					<div><dt><?php echo esc_html( $metrics[ $metric ] ); ?></dt><dd data-noobrocket-pagespeed-metric="<?php echo esc_attr( $metric ); ?>"><?php echo esc_html( $formatted ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
				<details class="noobrocket-status-evidence noobrocket-pagespeed-detail">
					<summary><?php esc_html_e( 'More scores and metrics', 'noobrocket' ); ?></summary>
					<div>
					<p class="noobrocket-pagespeed-url" data-noobrocket-pagespeed-url><?php echo esc_html( $result['url'] ?? $data['url'] ); ?></p>
					<dl class="noobrocket-pagespeed-scores">
					<?php foreach ( $categories as $category => $category_label ) :
						if ( 'performance' === $category ) {
							continue;
						}
						$score       = $result['scores'][ $category ] ?? null;
						$grade       = null === $score ? 'unavailable' : ( $score >= 90 ? 'good' : ( $score >= 50 ? 'improve' : 'poor' ) );
						$grade_class = [ 'unavailable' => 'is-neutral', 'good' => 'is-success', 'improve' => 'is-warning', 'poor' => 'is-error' ][ $grade ];
						?>
					<div><dt><?php echo esc_html( $category_label ); ?></dt><dd><strong data-noobrocket-pagespeed-score="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( null === $score ? '—' : $score ); ?></strong><span class="noobrocket-badge <?php echo esc_attr( $grade_class ); ?>" data-noobrocket-pagespeed-grade="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $grades[ $grade ] ); ?></span></dd></div>
					<?php endforeach; ?>
					</dl>
					<dl class="noobrocket-key-values noobrocket-pagespeed-metrics">
					<?php foreach ( [ 'tbt', 'cls' ] as $metric ) :
						$value = $result['metrics'][ $metric ] ?? null;
						if ( null === $value ) {
							$formatted = $grades['unavailable'];
						} elseif ( 'cls' === $metric ) {
							$formatted = number_format_i18n( $value, 3 );
						} elseif ( 'tbt' === $metric ) {
							$formatted = number_format_i18n( $value, 0 ) . ' ms';
						}
						?>
					<div><dt><?php echo esc_html( $metrics[ $metric ] ); ?></dt><dd data-noobrocket-pagespeed-metric="<?php echo esc_attr( $metric ); ?>"><?php echo esc_html( $formatted ); ?></dd></div>
					<?php endforeach; ?>
					</dl>
					</div>
				</details>
			</div>
		</section>
		<?php endforeach; ?>
	</div>
	<div class="noobrocket-panel-bottom"><?php esc_html_e( 'Lighthouse lab scores, not real visitor data. Each score is out of 100: 90–100 good, 50–89 needs improvement, 0–49 poor. Results vary by run.', 'noobrocket' ); ?></div>
	</details>
</section>
