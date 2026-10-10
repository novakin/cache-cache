<?php
/** Native Asset Manager tools screen. Loaded by Assets\Admin. */
defined( 'ABSPATH' ) || exit;
$preview_status_labels = [
	'loaded'              => __( 'Loaded', 'noobrocket' ),
	'removed'             => __( 'Removed', 'noobrocket' ),
	'required dependency' => __( 'Required dependency', 'noobrocket' ),
];
$assets = $inventory['assets'] ?? [];
$counts = [ 'style' => 0, 'script' => 0, 'loaded' => 0, 'removed' => 0, 'required dependency' => 0 ];
foreach ( $assets as $asset ) {
	++$counts[ $asset['type'] ];
	if ( isset( $counts[ $asset['status'] ] ) ) {
		++$counts[ $asset['status'] ];
	}
}
$has_entry = ! empty( $entry );
$entry = array_merge( [ 'type' => 'style', 'handle' => '', 'scope' => 'post', 'value' => '', 'exceptions' => '', 'expert' => false ], $entry );
$page_value = 'post' === $entry['scope'] ? ( $entry['value'] ?: $inspected_page ) : $inspected_page;
$added_rules = [];
$removed_rules = $published;
foreach ( $draft['rules'] as $rule ) {
	$match = array_search( $rule, $removed_rules, true );
	if ( false === $match ) {
		$added_rules[] = $rule;
	} else {
		unset( $removed_rules[ $match ] );
	}
}
$draft_is_stale = $draft['base'] !== $published;
$has_draft = $draft['rules'] !== $published || $draft_is_stale || $has_entry;
$inspection_is_current = ! empty( $inventory['draft_revision'] ) && hash_equals( \NoobRocket\Engine\Optimization\Assets\Rules::draft_revision( $draft ), $inventory['draft_revision'] );
$success_messages = [
	'add' => __( 'Rule added to your private draft. Visitors still use published rules.', 'noobrocket' ),
	'remove' => __( 'Rule removed from your private draft. Visitors still use published rules.', 'noobrocket' ),
	'discard' => __( 'Private draft discarded. Published rules are unchanged.', 'noobrocket' ),
	'publish' => __( 'Rules published and page cache cleared.', 'noobrocket' ),
	'publish_unchanged' => __( 'These rules are already published. No rules changed and the page cache was kept.', 'noobrocket' ),
];
$action = isset( $_GET['asset_action'] ) && is_string( $_GET['asset_action'] ) ? sanitize_key( $_GET['asset_action'] ) : '';
?>
<div id="assets" class="noobrocket-Page noobrocket-assets">
	<div class="noobrocket-sectionHeader">
		<h1 class="noobrocket-title1" tabindex="-1"><?php esc_html_e( 'Asset Manager', 'noobrocket' ); ?></h1>
		<p><?php esc_html_e( 'Unload unnecessary CSS and JavaScript on selected pages. Required dependencies stay loaded.', 'noobrocket' ); ?></p>
	</div>
	<?php if ( $notice && ! in_array( $action, [ 'add', 'publish' ], true ) ) : ?>
	<div class="notice notice-error" data-noobrocket-toast><p><?php echo esc_html( $notice ); ?></p></div>
	<?php elseif ( isset( $_GET['asset_updated'] ) && '1' === $_GET['asset_updated'] && isset( $success_messages[ $action ] ) ) : ?>
	<div class="notice notice-<?php echo 'publish_unchanged' === $action ? 'info' : 'success'; ?>" data-noobrocket-toast><p><?php echo esc_html( $success_messages[ $action ] ); ?></p></div>
	<?php endif; ?>
	<section class="noobrocket-settings-card noobrocket-asset-stage" aria-labelledby="noobrocket-asset-inspect-title">
		<header class="noobrocket-optionHeader"><h2 class="noobrocket-title2" id="noobrocket-asset-inspect-title"><?php esc_html_e( 'Inspect a page', 'noobrocket' ); ?></h2></header>
		<p><?php esc_html_e( 'Find the page’s CSS and JavaScript using your private draft. Visitors keep using published rules.', 'noobrocket' ); ?></p>
		<?php $this->open_form( 'preview' ); ?>
			<label for="noobrocket-preview-url"><?php esc_html_e( 'Page URL', 'noobrocket' ); ?></label>
			<div class="noobrocket-asset-url-row">
				<input id="noobrocket-preview-url" name="url" type="url" required value="<?php echo esc_attr( $inventory['url'] ?? home_url( '/' ) ); ?>">
				<button type="submit" class="noobrocket-button"><?php esc_html_e( 'Inspect page', 'noobrocket' ); ?></button>
			</div>
		</form>
		<?php if ( ! empty( $inventory['url'] ) ) : ?>
		<div class="noobrocket-asset-inventory-summary">
			<?php
			$css_assets_summary        = sprintf( _n( '%d CSS asset', '%d CSS assets', $counts['style'], 'noobrocket' ), $counts['style'] );
			$javascript_assets_summary = sprintf( _n( '%d JavaScript asset', '%d JavaScript assets', $counts['script'], 'noobrocket' ), $counts['script'] );
			// translators: %1$s and %2$s are localized CSS and JavaScript asset counts.
			?>
			<p><strong><?php echo esc_html( sprintf( __( '%1$s and %2$s', 'noobrocket' ), $css_assets_summary, $javascript_assets_summary ) ); ?></strong></p>
			<?php if ( $assets ) : ?>
			<p><?php echo esc_html( sprintf( __( '%1$d loaded · %2$d removed · %3$d retained as required dependencies', 'noobrocket' ), $counts['loaded'], $counts['removed'], $counts['required dependency'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! $inspection_is_current ) : ?><p class="noobrocket-helper"><?php esc_html_e( 'This inspection belongs to an earlier draft. Inspect the page again to check the current rules.', 'noobrocket' ); ?></p><?php endif; ?>
		</div>
		<?php endif; ?>
		<?php if ( $assets ) : ?>
		<details class="noobrocket-field-disclosure">
			<summary><?php echo esc_html( sprintf( __( 'Choose an inspected asset (%d)', 'noobrocket' ), count( $assets ) ) ); ?></summary>
			<p class="noobrocket-helper"><?php echo esc_html( sprintf( __( 'Last inspection: %1$s — %2$s.', 'noobrocket' ), $inventory['url'], wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $inventory['time'] ) ) ); ?></p>
			<p class="noobrocket-helper"><?php esc_html_e( 'Choose an asset to prepare a private rule. Source and dependency details are available in each row.', 'noobrocket' ); ?></p>
			<p class="noobrocket-helper"><?php esc_html_e( 'Inventory is specific to your signed-in session; visitor-only assets may differ.', 'noobrocket' ); ?></p>
			<details class="noobrocket-asset-row-details"><summary><?php esc_html_e( 'Inventory limits', 'noobrocket' ); ?></summary><p><?php esc_html_e( 'Only WordPress-enqueued assets are listed; hardcoded tags and inline-only assets are not independently managed.', 'noobrocket' ); ?></p></details>
			<div class="noobrocket-asset-inventory" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Inspected assets', 'noobrocket' ); ?>">
				<table class="widefat striped noobrocket-asset-table">
					<thead><tr><th><?php esc_html_e( 'Type / handle', 'noobrocket' ); ?></th><th><?php esc_html_e( 'Preview result', 'noobrocket' ); ?></th><th><?php esc_html_e( 'Action', 'noobrocket' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $assets as $asset ) : ?>
					<tr>
						<th scope="row">
							<code><?php echo esc_html( ( 'style' === $asset['type'] ? 'CSS' : 'JavaScript' ) . ' / ' . $asset['handle'] ); ?></code>
							<details class="noobrocket-asset-row-details"><summary><?php esc_html_e( 'Source and dependencies', 'noobrocket' ); ?></summary>
								<p><strong><?php esc_html_e( 'Source', 'noobrocket' ); ?>:</strong> <?php echo esc_html( $asset['src'] ?: __( 'Alias / inline', 'noobrocket' ) ); ?></p>
								<p><strong><?php esc_html_e( 'Depends on', 'noobrocket' ); ?>:</strong> <?php echo esc_html( $asset['deps'] ? implode( ', ', $asset['deps'] ) : __( 'None', 'noobrocket' ) ); ?></p>
								<?php if ( 'required dependency' === $asset['status'] && ! empty( $asset['retained_by'] ) ) : ?>
								<p><strong><?php esc_html_e( 'Kept because', 'noobrocket' ); ?>:</strong> <?php echo esc_html( sprintf( __( 'Required by %s.', 'noobrocket' ), implode( ', ', $asset['retained_by'] ) ) ); ?></p>
								<?php elseif ( 'required dependency' === $asset['status'] && ! empty( $asset['printed'] ) ) : ?>
								<p><strong><?php esc_html_e( 'Kept because', 'noobrocket' ); ?>:</strong> <?php esc_html_e( 'Already printed on this page.', 'noobrocket' ); ?></p>
								<?php endif; ?>
							</details>
						</th>
						<td data-label="<?php esc_attr_e( 'Preview result', 'noobrocket' ); ?>"><?php echo esc_html( $preview_status_labels[ $asset['status'] ] ?? __( 'Unknown', 'noobrocket' ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Action', 'noobrocket' ); ?>">
							<a class="button noobrocket-asset-create" href="<?php echo esc_url( add_query_arg( [ 'page' => 'noobrocket', 'asset_type' => $asset['type'], 'asset_handle' => $asset['handle'], 'asset_page' => $inspected_page ], admin_url( 'options-general.php' ) ) . '#assets' ); ?>" data-asset-type="<?php echo esc_attr( $asset['type'] ); ?>" data-asset-handle="<?php echo esc_attr( $asset['handle'] ); ?>" data-asset-page="<?php echo (int) $inspected_page; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Prepare rule for %s', 'noobrocket' ), $asset['handle'] ) ); ?>"><?php esc_html_e( 'Prepare rule', 'noobrocket' ); ?></a>
						</td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</details>
		<?php elseif ( ! empty( $inventory['url'] ) ) : ?>
			<details class="noobrocket-field-disclosure"><summary><?php esc_html_e( 'Inspection details', 'noobrocket' ); ?></summary><p class="noobrocket-helper"><?php echo esc_html( sprintf( __( 'Last inspection: %1$s — %2$s.', 'noobrocket' ), $inventory['url'], wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $inventory['time'] ) ) ); ?></p></details>
			<p class="noobrocket-helper"><?php esc_html_e( 'No WordPress-enqueued assets were found on the last inspected page. Inspect another page to find assets you can manage.', 'noobrocket' ); ?></p>
			<?php else : ?>
			<p class="noobrocket-helper"><?php esc_html_e( 'No page inspected yet. Enter a page URL above to see its registered CSS and JavaScript.', 'noobrocket' ); ?></p>
			<?php endif; ?>
	</section>
	<section class="noobrocket-settings-card noobrocket-asset-stage" aria-labelledby="noobrocket-asset-draft-title">
		<header class="noobrocket-optionHeader"><h2 class="noobrocket-title2" id="noobrocket-asset-draft-title"><?php esc_html_e( 'Draft rules', 'noobrocket' ); ?></h2></header>
		<?php if ( $notice && 'add' === $action ) : ?><div class="notice notice-error inline" data-noobrocket-inline-feedback><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
		<?php $this->open_form( 'add' ); ?>
			<p id="noobrocket-rule-context" class="noobrocket-helper" data-inspected-url="<?php echo esc_attr( $inventory['url'] ?? '' ); ?>"><?php esc_html_e( 'Adding a rule saves only your private draft. Preview and publish it separately.', 'noobrocket' ); ?></p>
			<div class="noobrocket-asset-editor-fields">
				<div class="noobrocket-asset-field"><label for="noobrocket-rule-type"><?php esc_html_e( 'Asset type', 'noobrocket' ); ?></label><select id="noobrocket-rule-type" name="rule[type]"><option value="style"<?php selected( $entry['type'], 'style' ); ?>>CSS</option><option value="script"<?php selected( $entry['type'], 'script' ); ?>>JavaScript</option></select></div>
				<div class="noobrocket-asset-field"><label for="noobrocket-rule-handle"><?php esc_html_e( 'Registered handle', 'noobrocket' ); ?></label>
					<input id="noobrocket-rule-handle" name="rule[handle]" class="regular-text" list="noobrocket-handles" maxlength="200" pattern="[a-zA-Z0-9_.:\-]+" required value="<?php echo esc_attr( $entry['handle'] ); ?>">
					<datalist id="noobrocket-handles"><?php foreach ( $assets as $asset ) : ?><option value="<?php echo esc_attr( $asset['handle'] ); ?>"><?php echo esc_html( 'style' === $asset['type'] ? 'CSS' : 'JavaScript' ); ?></option><?php endforeach; ?></datalist>
				</div>
				<div class="noobrocket-asset-field"><label for="noobrocket-rule-scope"><?php esc_html_e( 'Disable on', 'noobrocket' ); ?></label><select id="noobrocket-rule-scope" name="rule[scope]">
					<option value="post"<?php selected( $entry['scope'], 'post' ); ?>><?php esc_html_e( 'One page / post', 'noobrocket' ); ?></option>
					<option value="post_type"<?php selected( $entry['scope'], 'post_type' ); ?>><?php esc_html_e( 'Post type and its archive', 'noobrocket' ); ?></option>
					<option value="template"<?php selected( $entry['scope'], 'template' ); ?>><?php esc_html_e( 'Page template', 'noobrocket' ); ?></option>
					<option value="everywhere"<?php selected( $entry['scope'], 'everywhere' ); ?>><?php esc_html_e( 'Every frontend page', 'noobrocket' ); ?></option>
				</select></div>
				<div class="noobrocket-asset-field noobrocket-rule-target">
					<div data-asset-scope="post"><label for="noobrocket-rule-page"><?php esc_html_e( 'Choose a page', 'noobrocket' ); ?></label>
						<?php
						$page_dropdown = wp_dropdown_pages( [ 'name' => 'rule_page', 'id' => 'noobrocket-rule-page', 'selected' => $page_value, 'show_option_none' => __( 'Choose a page…', 'noobrocket' ), 'option_none_value' => '', 'post_status' => [ 'publish', 'private', 'draft' ], 'echo' => false ] );
						if ( $page_dropdown ) {
							echo str_replace( '<select ', '<select aria-describedby="noobrocket-rule-page-help" ', $page_dropdown ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native WordPress dropdown markup.
						} else {
							?>
							<select id="noobrocket-rule-page" name="rule_page" aria-describedby="noobrocket-rule-page-help"><option value=""><?php esc_html_e( 'Choose a page…', 'noobrocket' ); ?></option></select>
							<?php
						}
						?>
						<p id="noobrocket-rule-page-help" class="description"><?php esc_html_e( 'Only this page or post is affected. For a post or a page absent from the list, use its numeric ID below.', 'noobrocket' ); ?></p>
					</div>
					<div data-asset-scope="post_type"><label for="noobrocket-rule-post-type"><?php esc_html_e( 'Choose a post type', 'noobrocket' ); ?></label><select id="noobrocket-rule-post-type" name="rule_post_type" aria-describedby="noobrocket-rule-post-type-help">
						<option value=""><?php esc_html_e( 'Choose a post type…', 'noobrocket' ); ?></option>
						<?php foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) : ?><option value="<?php echo esc_attr( $type->name ); ?>"<?php selected( 'post_type' === $entry['scope'] ? $entry['value'] : '', $type->name ); ?>><?php echo esc_html( $type->labels->name . ' (' . $type->name . ')' ); ?></option><?php endforeach; ?>
					</select><p id="noobrocket-rule-post-type-help" class="description"><?php esc_html_e( 'Affects all individual entries of this type and its archive, if it has one.', 'noobrocket' ); ?></p></div>
					<div data-asset-scope="template"><label for="noobrocket-rule-template"><?php esc_html_e( 'Choose a template', 'noobrocket' ); ?></label><select id="noobrocket-rule-template" name="rule_template" aria-describedby="noobrocket-rule-template-help">
						<option value="default"<?php selected( 'template' === $entry['scope'] ? $entry['value'] : 'default', 'default' ); ?>><?php esc_html_e( 'Default template', 'noobrocket' ); ?></option>
						<?php foreach ( wp_get_theme()->get_page_templates() as $filename => $title ) : ?><option value="<?php echo esc_attr( $filename ); ?>"<?php selected( 'template' === $entry['scope'] ? $entry['value'] : '', $filename ); ?>><?php echo esc_html( $title ); ?></option><?php endforeach; ?>
					</select><p id="noobrocket-rule-template-help" class="description"><?php esc_html_e( 'Affects individual pages or posts using this template. Default includes entries without a custom template.', 'noobrocket' ); ?></p></div>
					<div data-asset-scope="everywhere"><p class="description"><?php esc_html_e( 'Affects every frontend page, including archives. Add exceptions where the asset must remain loaded and preview representative pages before publishing.', 'noobrocket' ); ?></p></div>
					<p data-asset-expert-toggle><label><input id="noobrocket-rule-expert" name="rule_expert" type="checkbox" value="1"<?php checked( $entry['expert'] ); ?>> <?php esc_html_e( 'Use an ID or registered value', 'noobrocket' ); ?></label></p>
					<div data-asset-raw><label for="noobrocket-rule-value"><?php esc_html_e( 'Post ID, post type or template filename', 'noobrocket' ); ?></label><input id="noobrocket-rule-value" name="rule[value]" class="regular-text" maxlength="200" value="<?php echo esc_attr( $entry['value'] ); ?>" aria-describedby="noobrocket-rule-value-help" data-label-post="<?php esc_attr_e( 'Post ID', 'noobrocket' ); ?>" data-label-post-type="<?php esc_attr_e( 'Registered post type', 'noobrocket' ); ?>" data-label-template="<?php esc_attr_e( 'Template filename', 'noobrocket' ); ?>"><p id="noobrocket-rule-value-help" class="description"><?php esc_html_e( 'Use a positive numeric post ID, a registered post type such as post, or a template filename such as templates/contact.php or default, matching the selected scope.', 'noobrocket' ); ?></p></div>
				</div>
				<details class="noobrocket-asset-field noobrocket-asset-field--full noobrocket-field-disclosure noobrocket-asset-rule-options"<?php echo '' !== trim( (string) $entry['exceptions'] ) || ( $has_entry && $notice ) ? ' open' : ''; ?>>
					<summary><?php esc_html_e( 'Exceptions (optional)', 'noobrocket' ); ?></summary>
					<label for="noobrocket-rule-exceptions"><?php esc_html_e( 'Exception post IDs', 'noobrocket' ); ?></label><input id="noobrocket-rule-exceptions" name="rule[exceptions]" class="regular-text" value="<?php echo esc_attr( $entry['exceptions'] ); ?>" pattern="[0-9,\s]*" inputmode="numeric" aria-describedby="noobrocket-rule-exceptions-help"><p id="noobrocket-rule-exceptions-help" class="description"><?php esc_html_e( 'Comma-separated post IDs where this rule must keep the asset loaded.', 'noobrocket' ); ?></p>
				</details>
			</div>
			<div class="noobrocket-action-group"><button type="submit" class="noobrocket-button"><?php esc_html_e( 'Add rule to draft', 'noobrocket' ); ?></button></div>
		</form>
		<div class="noobrocket-asset-saved-draft">
			<h3><?php esc_html_e( 'Saved draft rules', 'noobrocket' ); ?></h3>
			<?php
			$published_rules_summary = sprintf( _n( '%d published rule', '%d published rules', count( $published ), 'noobrocket' ), count( $published ) );
			$draft_rules_summary     = sprintf( _n( '%d draft rule', '%d draft rules', count( $draft['rules'] ), 'noobrocket' ), count( $draft['rules'] ) );
			// translators: %1$s and %2$s are localized published and draft rule counts.
			?>
			<p><?php echo esc_html( sprintf( __( '%1$s; %2$s.', 'noobrocket' ), $published_rules_summary, $draft_rules_summary ) ); ?></p>
		<?php if ( $draft['rules'] ) : ?>
		<div class="noobrocket-asset-inventory<?php echo count( $draft['rules'] ) <= 5 ? ' noobrocket-asset-inventory--short' : ''; ?>">
			<table class="widefat striped noobrocket-asset-table">
				<thead><tr><th><?php esc_html_e( 'Asset', 'noobrocket' ); ?></th><th><?php esc_html_e( 'Disable on', 'noobrocket' ); ?></th><th><?php esc_html_e( 'Exceptions', 'noobrocket' ); ?></th><th><?php esc_html_e( 'Action', 'noobrocket' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $draft['rules'] as $index => $rule ) : ?>
				<tr>
					<th scope="row"><code><?php echo esc_html( ( 'style' === $rule['type'] ? 'CSS' : 'JavaScript' ) . ' / ' . $rule['handle'] ); ?></code></th>
					<td data-label="<?php esc_attr_e( 'Disable on', 'noobrocket' ); ?>"><?php echo esc_html( $this->scope_label( $rule ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Exceptions', 'noobrocket' ); ?>"><?php echo esc_html( $rule['exceptions'] ? implode( ', ', array_map( [ $this, 'post_label' ], $rule['exceptions'] ) ) : __( 'None', 'noobrocket' ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Action', 'noobrocket' ); ?>">
						<?php $this->open_form( 'remove' ); ?><input type="hidden" name="index" value="<?php echo (int) $index; ?>"><button class="button" type="submit" aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s', 'noobrocket' ), $rule['handle'] ) ); ?>"><?php esc_html_e( 'Remove', 'noobrocket' ); ?></button></form>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
		<?php if ( ! $draft['rules'] ) : ?>
		<?php if ( $published ) : ?>
		<p><?php esc_html_e( 'No rules in this draft. Publishing it removes any published asset rules.', 'noobrocket' ); ?></p>
		<?php else : ?>
		<p><?php esc_html_e( 'No asset rules yet. Inspect a page and add a rule to your private draft.', 'noobrocket' ); ?></p>
		<?php endif; ?>
		<?php endif; ?>
		</div>
		<?php if ( $has_draft ) : ?>
		<?php $this->open_form( 'discard' ); ?>
			<button type="submit" class="noobrocket-button" data-noobrocket-confirm-discard="<?php esc_attr_e( 'Discard your saved private draft and rejected entry? Published rules will stay unchanged.', 'noobrocket' ); ?>"><?php esc_html_e( 'Discard draft', 'noobrocket' ); ?></button>
		</form>
		<?php endif; ?>
	</section>
	<section class="noobrocket-settings-card noobrocket-asset-stage" aria-labelledby="noobrocket-asset-publish-title">
		<header class="noobrocket-optionHeader"><h2 class="noobrocket-title2" id="noobrocket-asset-publish-title"><?php esc_html_e( 'Preview and publish', 'noobrocket' ); ?></h2></header>
		<?php if ( $notice && 'publish' === $action ) : ?><div class="notice notice-error inline" data-noobrocket-inline-feedback><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
		<?php if ( $draft_is_stale || $draft['rules'] !== $published ) : ?>
		<p><?php esc_html_e( 'Preview your saved draft and test key interactions. Publication replaces the rules used by visitors.', 'noobrocket' ); ?></p>
		<?php if ( $draft_is_stale ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'Published rules changed while you edited. Discard your draft and start from the current rules.', 'noobrocket' ); ?></p></div>
		<?php endif; ?>
		<?php $this->open_form( 'preview' ); ?>
			<label for="noobrocket-publish-preview-url"><?php esc_html_e( 'Page URL', 'noobrocket' ); ?></label>
			<div class="noobrocket-asset-url-row">
				<input id="noobrocket-publish-preview-url" name="url" type="url" required value="<?php echo esc_attr( $inventory['url'] ?? home_url( '/' ) ); ?>">
				<button type="submit" class="noobrocket-button" formtarget="_blank" data-noobrocket-preview-link><?php esc_html_e( 'Preview saved draft', 'noobrocket' ); ?></button>
			</div>
			<p class="noobrocket-helper"><?php esc_html_e( 'Opens in a new tab using your saved draft. Unsaved settings and rule edits stay here.', 'noobrocket' ); ?></p>
		</form>
		<?php if ( $added_rules || $removed_rules ) : ?>
		<?php
		$added_rules_summary   = sprintf( _n( '%d rule added', '%d rules added', count( $added_rules ), 'noobrocket' ), count( $added_rules ) );
		$removed_rules_summary = sprintf( _n( '%d rule removed', '%d rules removed', count( $removed_rules ), 'noobrocket' ), count( $removed_rules ) );
		// translators: %1$s and %2$s are localized added and removed rule counts.
		?>
		<p class="noobrocket-asset-publication-summary"><?php echo esc_html( sprintf( __( '%1$s; %2$s.', 'noobrocket' ), $added_rules_summary, $removed_rules_summary ) ); ?></p>
		<details class="noobrocket-field-disclosure">
			<summary><?php esc_html_e( 'Review publication changes', 'noobrocket' ); ?></summary>
			<?php foreach ( [ 'add' => $added_rules, 'remove' => $removed_rules ] as $change => $changes ) : ?>
			<?php
			if ( ! $changes ) {
				continue;
			}
			?>
			<h3 class="noobrocket-title3"><?php echo esc_html( 'add' === $change ? __( 'Rules to add', 'noobrocket' ) : __( 'Rules to remove', 'noobrocket' ) ); ?></h3>
			<ul class="noobrocket-asset-change-list">
				<?php foreach ( $changes as $rule ) : ?>
				<li><code><?php echo esc_html( ( 'style' === $rule['type'] ? 'CSS' : 'JavaScript' ) . ' / ' . $rule['handle'] ); ?></code> — <?php echo esc_html( $this->scope_label( $rule ) ); ?><?php if ( $rule['exceptions'] ) : ?><span><?php echo esc_html( sprintf( __( 'Exceptions: %s', 'noobrocket' ), implode( ', ', array_map( [ $this, 'post_label' ], $rule['exceptions'] ) ) ) ); ?></span><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
			<?php endforeach; ?>
		</details>
		<?php elseif ( $draft['rules'] !== $published ) : ?>
		<p><?php esc_html_e( 'Rule order changed.', 'noobrocket' ); ?></p>
		<?php elseif ( ! $draft_is_stale ) : ?>
		<p><?php esc_html_e( 'No unpublished rule changes.', 'noobrocket' ); ?></p>
		<?php endif; ?>
		<?php $this->open_form( 'publish' ); ?>
			<label><input type="checkbox" name="confirm" value="1" required<?php disabled( ! $draft_is_stale && $draft['rules'] === $published ); ?>> <?php esc_html_e( 'I have tested the preview and want these rules applied to visitors.', 'noobrocket' ); ?></label>
			<div class="noobrocket-action-group"><button type="submit" class="noobrocket-button<?php echo $draft['rules'] !== $published ? ' noobrocket-button-primary' : ''; ?>"<?php disabled( $draft_is_stale || $draft['rules'] === $published ); ?>><?php esc_html_e( 'Publish rules', 'noobrocket' ); ?></button></div>
		</form>
		<?php else : ?>
		<p><?php esc_html_e( 'No unpublished rule changes. Inspect a page and edit your draft before publishing.', 'noobrocket' ); ?></p>
		<button type="button" class="noobrocket-button" disabled><?php esc_html_e( 'Publish rules', 'noobrocket' ); ?></button>
		<?php endif; ?>
		<nav class="noobrocket-status-navigation" aria-label="<?php esc_attr_e( 'Related settings', 'noobrocket' ); ?>"><a href="#history"><?php esc_html_e( 'Settings history', 'noobrocket' ); ?></a></nav>
	</section>
</div>
