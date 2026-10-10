<?php
/** Restorable settings history, separate from private Asset Manager drafts. */
defined( 'ABSPATH' ) || exit;
?>
<div id="history" class="noobrocket-Page noobrocket-history">
	<div class="noobrocket-sectionHeader"><h1 class="noobrocket-title1" tabindex="-1"><?php esc_html_e( 'Settings history', 'noobrocket' ); ?></h1></div>
	<?php if ( $notice ) : ?>
	<div class="notice notice-error" data-noobrocket-toast><p><?php echo esc_html( $notice ); ?></p></div>
	<?php elseif ( isset( $_GET['asset_updated'], $_GET['asset_action'] ) && '1' === $_GET['asset_updated'] && 'restore' === $_GET['asset_action'] ) : ?>
	<div class="notice notice-success" data-noobrocket-toast><p><?php esc_html_e( 'Previous settings and published asset rules restored. The state before restoration is retained in history.', 'noobrocket' ); ?></p></div>
	<?php endif; ?>
	<p><?php esc_html_e( 'Compare the last five saved states and restore supported settings and published asset rules. Restore is separate from saving pending settings.', 'noobrocket' ); ?></p>
	<details class="noobrocket-field-disclosure noobrocket-history-scope">
		<summary><?php esc_html_e( 'Restore scope and limits', 'noobrocket' ); ?></summary>
		<p><?php esc_html_e( 'The last five changes retain the previous supported optimization settings and published asset rules. Restore keeps current credentials, site cache keys and plugin versions. It does not undo database cleanup, image conversion, content edits or server configuration.', 'noobrocket' ); ?></p>
		<p class="noobrocket-helper"><?php esc_html_e( 'Reviews compare each snapshot with the saved state when this page loaded. Unsubmitted settings and private asset drafts are not restored. The current saved state is retained before restoration, so you can undo a restore.', 'noobrocket' ); ?></p>
	</details>
	<?php if ( ! $snapshots ) : ?>
	<p class="noobrocket-helper"><?php esc_html_e( 'No history yet. Saving changed optimization settings or publishing changed asset rules records the previous state here.', 'noobrocket' ); ?></p>
	<?php endif; ?>
	<div class="noobrocket-history-list">
	<?php foreach ( $snapshots as $snapshot ) : ?>
		<?php
		$changes = $this->snapshots->changes( $snapshot );
		$setting_count = count( $changes['settings'] );
		$rule_count = count( $changes['removed_rules'] ) + count( $changes['added_rules'] );
		/* translators: %d: number of settings changed by restoring a snapshot. */
		$setting_summary = sprintf( _n( '%d setting to change', '%d settings to change', $setting_count, 'noobrocket' ), $setting_count );
		/* translators: %d: number of published asset rules changed by restoring a snapshot. */
		$rule_summary = sprintf( _n( '%d asset rule change', '%d asset rule changes', $rule_count, 'noobrocket' ), $rule_count );
		$setting_names = array_map( [ $this, 'setting_label' ], array_keys( $changes['settings'] ) );
		?>
		<article class="noobrocket-history-entry">
			<details class="noobrocket-history-review noobrocket-field-disclosure">
				<summary class="noobrocket-history-summary">
					<span class="noobrocket-history-summary-main">
						<strong class="noobrocket-history-date"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $snapshot['time'] ) ); ?></strong>
			<?php if ( $changes['settings'] || $rule_count ) : ?>
						<span><?php echo esc_html( $setting_summary . ' · ' . $rule_summary ); ?></span>
			<?php elseif ( $changes['rules_changed'] ) : ?>
						<span><?php esc_html_e( 'Rule order changed.', 'noobrocket' ); ?></span>
			<?php else : ?>
						<span><?php esc_html_e( 'Matches saved state', 'noobrocket' ); ?></span>
			<?php endif; ?>
			<?php if ( $setting_names ) : ?>
						<span class="noobrocket-helper"><?php echo esc_html( implode( ', ', array_slice( $setting_names, 0, 3 ) ) ); ?><?php if ( count( $setting_names ) > 3 ) : ?> <?php echo esc_html( sprintf( _n( 'and %d other setting', 'and %d other settings', count( $setting_names ) - 3, 'noobrocket' ), count( $setting_names ) - 3 ) ); ?><?php endif; ?></span>
			<?php endif; ?>
					</span>
					<span class="noobrocket-history-summary-action"><?php esc_html_e( 'Review changes before restoring', 'noobrocket' ); ?></span>
				</summary>
			<?php if ( ! $changes['settings'] && ! $changes['rules_changed'] ) : ?>
				<p><?php esc_html_e( 'This snapshot matches the current saved settings and published rules.', 'noobrocket' ); ?></p>
			<?php else : ?>
				<?php if ( $changes['rules_changed'] && ! $rule_count ) : ?><p><?php esc_html_e( 'Rule order changed.', 'noobrocket' ); ?></p><?php endif; ?>
				<?php if ( $changes['settings'] ) : ?>
				<table class="widefat striped noobrocket-history-table">
					<thead><tr><th><?php esc_html_e( 'Setting', 'noobrocket' ); ?></th><th><?php esc_html_e( 'Current saved value', 'noobrocket' ); ?></th><th><?php esc_html_e( 'Value after restore', 'noobrocket' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $changes['settings'] as $key => $values ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $this->setting_label( $key ) ); ?></th>
						<td data-label="<?php esc_attr_e( 'Current saved value', 'noobrocket' ); ?>"><pre class="noobrocket-history-value" tabindex="0" role="region" aria-label="<?php echo esc_attr( $this->setting_label( $key ) . ' — ' . __( 'Current saved value', 'noobrocket' ) ); ?>"><?php echo esc_html( $this->setting_value( $key, $values['current'] ) ); ?></pre></td>
						<td data-label="<?php esc_attr_e( 'Value after restore', 'noobrocket' ); ?>"><pre class="noobrocket-history-value" tabindex="0" role="region" aria-label="<?php echo esc_attr( $this->setting_label( $key ) . ' — ' . __( 'Value after restore', 'noobrocket' ) ); ?>"><?php echo esc_html( $this->setting_value( $key, $values['snapshot'] ) ); ?></pre></td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
				<?php foreach ( [ 'removed_rules' => __( 'Remove from published rules', 'noobrocket' ), 'added_rules' => __( 'Restore to published rules', 'noobrocket' ) ] as $kind => $label ) : ?>
				<?php if ( $changes[ $kind ] ) : ?>
				<h3><?php echo esc_html( $label ); ?></h3>
				<ul class="noobrocket-history-rule-list">
					<?php foreach ( $changes[ $kind ] as $rule ) : ?>
					<li><code><?php echo esc_html( ( 'style' === $rule['type'] ? 'CSS' : 'JavaScript' ) . ' / ' . $rule['handle'] ); ?></code> — <?php echo esc_html( $this->scope_label( $rule ) ); ?><?php if ( $rule['exceptions'] ) : ?><br><?php echo esc_html( sprintf( __( 'Keep loaded on: %s', 'noobrocket' ), implode( ', ', array_map( [ $this, 'post_label' ], $rule['exceptions'] ) ) ) ); ?><?php endif; ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
				<?php endforeach; ?>
				<p><?php esc_html_e( 'Restoring replaces these saved optimization values and the published asset rule list, then clears the affected cache. Credentials and private drafts stay current.', 'noobrocket' ); ?></p>
				<?php $this->open_form( 'restore', $snapshot ); ?>
					<input type="hidden" name="snapshot" value="<?php echo esc_attr( $snapshot['id'] ); ?>">
					<label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I have reviewed these changes and want to restore this snapshot.', 'noobrocket' ); ?></label>
					<?php submit_button( __( 'Restore snapshot', 'noobrocket' ), 'secondary' ); ?>
				</form>
			<?php endif; ?>
			</details>
		</article>
	<?php endforeach; ?>
	</div>
</div>
