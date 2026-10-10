<?php
/** Native provider boundaries. All HTTP, options, credentials and logs are disposable fixtures. */
namespace NoobRocket\Logger {
	class Logger {
		public static $errors = [];
		public static function error( $message, $context = [] ) { self::$errors[] = [ $message, $context ]; }
		public static function info( $message, $context = [] ) {}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'WEEK_IN_SECONDS', 604800 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'WPMEDIA_IS_TESTING', true );
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
	class ProviderActionStopped extends \RuntimeException {}
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function __( $value, $domain = null ) { return $value; }
	function _x( $value, $context, $domain = null ) { return $value; }
	function apply_filters( $hook, $value, ...$args ) { return $value; }
	function noobrocket_get_constant( $key, $default = '' ) { return $default; }
	function noobrocket_has_constant( $key ) { return false; }
	function get_noobrocket_documentation_url() { return 'https://docs.example.test/'; }
	function esc_url( $value ) { return $value; }
	function esc_url_raw( $value ) { return $value; }
	function wp_parse_url( $value, $component = -1 ) { return parse_url( $value, $component ); }
	function get_site_url() { return $GLOBALS['site_url']; }
	function get_noobrocket_i18n_home_url( $lang ) { return $GLOBALS['site_url'] . '/'; }
	function get_feed_link( $type = '' ) { return $GLOBALS['site_url'] . '/' . $type . 'feed/'; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function current_user_can( $capability ) { return $GLOBALS['authorized']; }
	function get_current_user_id() { return $GLOBALS['fixture_user_id']; }
	function get_option( $name, $default = null ) { return $GLOBALS['provider_options'][ $name ] ?? $default; }
	function update_option( $name, $value ) {
		$previous = get_option( $name, [] );
		if ( $GLOBALS['refuse_metadata_write'] && '' === ( $value['cloudflare_old_settings'] ?? '' ) && '' !== ( $previous['cloudflare_old_settings'] ?? '' ) ) { return false; }
		$subscriber = $GLOBALS['active_subscriber'];
		$value = $subscriber->change_auth( $value, $previous );
		$value = $subscriber->delete_connection_transient( $value, $previous );
		$value = $subscriber->save_cloudflare_old_settings( $value, $previous );
		$value = $subscriber->display_settings_notice( $value, $previous );
		$GLOBALS['provider_options'][ $name ] = $value;
		$subscriber->save_cloudflare_options( $previous, $value );
		$subscriber->update_dev_mode( $previous, $value );
		return $previous !== $value;
	}
	function get_transient( $name ) { return $GLOBALS['provider_transients'][ $name ] ?? false; }
	function set_transient( $name, $value, $ttl = 0 ) { $GLOBALS['provider_transients'][ $name ] = $value; return true; }
	function delete_transient( $name ) { unset( $GLOBALS['provider_transients'][ $name ] ); return true; }
	function add_settings_error( $setting, $code, $message, $type = 'error' ) { $GLOBALS['settings_errors'][] = compact( 'setting', 'code', 'message', 'type' ); }
	function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
	function wp_remote_retrieve_response_code( $response ) { return $response['response']['code'] ?? 0; }
	function wp_schedule_single_event( $time, $hook ) {}
	function wp_next_scheduled( $hook ) { return false; }
	function wp_unschedule_event( $time, $hook ) {}
	function wp_verify_nonce( $nonce, $action ) { return $GLOBALS['valid_nonce']; }
	function sanitize_key( $value ) { return $value; }
	function wp_nonce_ays( $action ) { throw new ProviderActionStopped( 'Denied' ); }
	function wp_get_referer() { return '/wp-admin/options-general.php?page=noobrocket'; }
	function wp_safe_redirect( $url ) {}
	function wp_die() { throw new ProviderActionStopped( 'Finished' ); }
	function wp_sprintf_l( $format, $messages ) { return implode( ', ', $messages ); }

	function fixture_response( $result ) { return [ 'body' => json_encode( [ 'success' => true, 'result' => $result ] ), 'response' => [ 'code' => 200 ] ]; }
	function wp_remote_request( $url, $args ) {
		if ( 0 !== strpos( $url, 'https://api.cloudflare.com/client/v4/zones/' ) ) { throw new \RuntimeException( 'Unexpected HTTP target' ); }
		$path = substr( $url, strlen( 'https://api.cloudflare.com/client/v4/' ) );
		$GLOBALS['provider_calls'][] = [ 'path' => $path, 'method' => $args['method'], 'headers' => $args['headers'], 'body' => $args['body'] ];
		if ( $GLOBALS['connection_failure'] ) { return new WP_Error( 'intercepted_connection', 'Synthetic invalid connection.' ); }
		if ( ! preg_match( '#^zones/([^/]+)(.*)$#', $path, $match ) ) { throw new \RuntimeException( 'Unexpected Cloudflare path' ); }
		$zone = $match[1];
		$tail = $match[2];
		if ( '' === $tail ) { return fixture_response( (object) [ 'name' => $GLOBALS['zone_name'] ] ); }
		if ( '/settings' === $tail ) {
			if ( $GLOBALS['settings_failure'] ) { return new WP_Error( 'intercepted_backup', 'Synthetic failed backup.' ); }
			$result = [];
			foreach ( $GLOBALS['remote_settings'][ $zone ] as $id => $value ) { $result[] = (object) compact( 'id', 'value' ); }
			return fixture_response( $result );
		}
		if ( '/pagerules?status=active' === $tail ) { return fixture_response( $GLOBALS['cache_everything'] ? [ [ 'action' => 'cache_everything' ] ] : [] ); }
		if ( '/purge_cache' === $tail ) { return fixture_response( (object) [ 'id' => 'synthetic-purge' ] ); }
		if ( 0 === strpos( $tail, '/settings/' ) && 'PATCH' === $args['method'] ) {
			$setting = substr( $tail, strlen( '/settings/' ) );
			if ( $setting === $GLOBALS['patch_failure'] ) { return new WP_Error( 'intercepted_patch', 'Synthetic partial application.' ); }
			$data = json_decode( $args['body'], true );
			$GLOBALS['remote_settings'][ $zone ][ $setting ] = $data['value'];
			if ( 'browser_cache_ttl' === $setting && $GLOBALS['interleave_settings'] ) { $GLOBALS['provider_options']['noobrocket_settings']['newer_settings'] = 'preserved'; }
			return fixture_response( (object) $data );
		}
		throw new \RuntimeException( 'Unexpected Cloudflare operation' );
	}
	function wp_remote_get( $url, $args ) {
		if ( 0 !== strpos( $url, 'https://waf.sucuri.net/api?' ) ) { throw new \RuntimeException( 'Unexpected HTTP target' ); }
		$GLOBALS['sucuri_calls']++;
		$secret = str_repeat( 'a', 32 ) . ' ' . str_repeat( 'b', 32 );
		switch ( $GLOBALS['sucuri_case'] ) {
			case 'transport': return new WP_Error( 'transport', $url . ' ' . $secret );
			case 'empty_response': return [ 'body' => '', 'response' => [ 'code' => 502 ] ];
			case 'invalid_json': return [ 'body' => $secret, 'response' => [ 'code' => 200 ] ];
			case 'provider_failure': return [ 'body' => json_encode( [ 'status' => 0, 'messages' => [ $secret, $url ] ] ), 'response' => [ 'code' => 403 ] ];
			default: return [ 'body' => '{"status":1}', 'response' => [ 'code' => 200 ] ];
		}
	}
	function provider_check( $condition, $message ) {
		if ( ! $condition ) { throw new \RuntimeException( $message ); }
		$GLOBALS['provider_checks']++;
	}
	function provider_mutations() { return array_values( array_filter( $GLOBALS['provider_calls'], function ( $call ) { return 'GET' !== $call['method']; } ) ); }
	function provider_reset_calls() { $GLOBALS['provider_calls'] = []; $GLOBALS['settings_errors'] = []; $GLOBALS['provider_transients'] = []; }
	function provider_fixture( array $saved ) {
		$options = new \NoobRocket\Admin\Options_Data( $saved );
		$factory = new \NoobRocket\Addon\Cloudflare\Auth\APIKeyFactory( $options );
		$client = new \NoobRocket\Addon\Cloudflare\API\Client( $factory->create() );
		$cloudflare = new \NoobRocket\Addon\Cloudflare\Cloudflare( $options, new \NoobRocket\Addon\Cloudflare\API\Endpoints( $client ) );
		$subscriber = new \NoobRocket\Addon\Cloudflare\Subscriber( $cloudflare, $options, new \NoobRocket\Admin\Options( 'noobrocket_' ), $factory );
		return [ $cloudflare, $subscriber ];
	}
	function save_provider_options( $subscriber, array $previous, array $submitted ) {
		$GLOBALS['active_subscriber'] = $subscriber;
		$GLOBALS['provider_options']['noobrocket_settings'] = $previous;
		update_option( 'noobrocket_settings', $submitted );
		return get_option( 'noobrocket_settings' );
	}

	$plugin = dirname( __DIR__ ) . '/noobrocket/';
	foreach ( [
		'inc/classes/event-management/subscriber-interface.php',
		'inc/classes/admin/class-abstract-options.php',
		'inc/classes/admin/class-options.php',
		'inc/classes/admin/class-options-data.php',
		'inc/Addon/Cloudflare/Auth/AuthInterface.php',
		'inc/Addon/Cloudflare/Auth/AuthFactoryInterface.php',
		'inc/Addon/Cloudflare/Auth/APIKey.php',
		'inc/Addon/Cloudflare/Auth/APIKeyFactory.php',
		'inc/Addon/Cloudflare/API/Client.php',
		'inc/Addon/Cloudflare/API/Endpoints.php',
		'inc/Addon/Cloudflare/Cloudflare.php',
		'inc/Addon/Cloudflare/Subscriber.php',
		'inc/Addon/Sucuri/Subscriber.php',
	] as $file ) { require $plugin . $file; }

	$provider_checks = 0;
	$provider_options = [];
	$provider_transients = [];
	$provider_calls = [];
	$settings_errors = [];
	$authorized = true;
	$fixture_user_id = 17;
	$valid_nonce = true;
	$site_url = 'https://www.example.test';
	$zone_name = 'example.test';
	$connection_failure = false;
	$settings_failure = false;
	$patch_failure = '';
	$cache_everything = true;
	$remote_settings = [];
	$sucuri_calls = 0;
	$refuse_metadata_write = false;
	$interleave_settings = false;
	$saved = [ 'do_cloudflare' => 1, 'cloudflare_email' => 'a@example.test', 'cloudflare_api_key' => 'synthetic-a', 'cloudflare_zone_id' => 'zone-a', 'cloudflare_auto_settings' => 0, 'cloudflare_devmode' => 0, 'cloudflare_old_settings' => '' ];
	list( $cloudflare, $subscriber ) = provider_fixture( $saved );
	set_error_handler( function ( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); }, E_WARNING | E_NOTICE );

	// PROVIDER-05: native validation rejects substring and wrong suffix targets.
	foreach ( [
		[ 'example.test', 'example.test', true ], [ 'www.example.test', 'example.test', true ],
		[ 'WWW.Example.Test.', 'EXAMPLE.TEST.', true ], [ 'notexample.test', 'example.test', false ],
		[ 'example.test.other.net', 'example.test', false ], [ 'noobunbox.net', 'box.net', false ],
		[ 'example.test', '', false ], [ 'example.test', 'example.test/invalid', false ],
	] as $case ) {
		$site_url = 'https://' . $case[0]; $zone_name = $case[1];
		provider_check( $case[2] === ( true === $cloudflare->is_auth_valid( 'zone-a' ) ), 'Zone boundary: ' . $case[0] . '/' . $case[1] );
	}
	$site_url = 'https://www.example.test'; $zone_name = 'example.test';
	set_transient( 'noobrocket_cloudflare_is_api_keys_valid', true );
	$connection_failure = true;
	provider_check( is_wp_error( $cloudflare->check_connection( 'zone-b' ) ), 'Explicit target never trusts cached connection success' );
	$connection_failure = false;

	// PROVIDER-02: internal synchronization has configured ownership, independent of a current user.
	$authorized = false;
	$fixture_user_id = 0;
	$provider_options['noobrocket_settings'] = array_merge( $saved, [ 'cloudflare_zone_id' => 'zone-b', 'cloudflare_api_key' => 'synthetic-b' ] );
	foreach ( [ 'domain', 'post', 'home', 'files' ] as $operation ) {
		provider_reset_calls();
		switch ( $operation ) {
			case 'domain': $subscriber->auto_purge(); break;
			case 'post': $subscriber->auto_purge_by_url( null, [ $site_url . '/post/' ], '' ); break;
			case 'home': $subscriber->purge_url( '/synthetic/cache/root', '' ); break;
			case 'files': $subscriber->purge_url( [ $site_url . '/post/' ] ); break;
		}
		$mutations = provider_mutations();
		provider_check( 1 === count( $mutations ) && 'zones/zone-b/purge_cache' === $mutations[0]['path'], 'Internal ' . $operation . ' purges intended saved zone once without a user' );
		provider_check( 'synthetic-b' === $mutations[0]['headers']['X-Auth-Key'], 'Internal ' . $operation . ' uses current saved credentials' );
	}
	foreach ( [ 'disabled', 'invalid', 'unsupported' ] as $gate ) {
		provider_reset_calls();
		$provider_options['noobrocket_settings']['do_cloudflare'] = 'disabled' !== $gate;
		$connection_failure = 'invalid' === $gate; $cache_everything = 'unsupported' !== $gate;
		$subscriber->auto_purge();
		provider_check( [] === provider_mutations(), 'Internal synchronization skips ' . $gate );
	}
	$connection_failure = false; $cache_everything = true;
	provider_reset_calls(); $subscriber->purge_cache_no_die();
	provider_check( [] === $provider_calls, 'Unauthorized manual purge sends no requests' );
	$_GET['_wpnonce'] = 'synthetic'; $valid_nonce = false;
	try { $subscriber->purge_cache(); } catch ( ProviderActionStopped $e ) {}
	provider_check( [] === $provider_calls, 'Invalid manual Cloudflare nonce sends no requests' );
	$valid_nonce = true; $authorized = true; $fixture_user_id = 17;

	// PROVIDER-03/04: snapshot and mutations share the validated submitted zone despite stale injected options.
	foreach ( [ 0, 86400 ] as $previous_ttl ) {
		provider_reset_calls();
		$previous_settings = [ 'cache_level' => 'basic', 'rocket_loader' => 'on', 'browser_cache_ttl' => $previous_ttl ];
		$remote_settings['zone-b'] = $previous_settings;
		$input = array_merge( $saved, [ 'cloudflare_zone_id' => 'zone-b', 'cloudflare_email' => 'b@example.test', 'cloudflare_api_key' => 'synthetic-b', 'cloudflare_auto_settings' => 1 ] );
		$enabled = save_provider_options( $subscriber, $saved, $input );
		$backup = json_decode( $enabled['cloudflare_old_settings'], true );
		provider_check( 'zone-b' === $backup['zone_id'] && $previous_ttl === $backup['settings']['browser_cache_ttl'], 'Backup keeps zero/nonzero TTL and submitted zone' );
		provider_check( 3 === count( provider_mutations() ), 'Enable has three explicit provider setting intents' );
		foreach ( $provider_calls as $call ) {
			provider_check( 0 === strpos( $call['path'], 'zones/zone-b' ) && 'synthetic-b' === $call['headers']['X-Auth-Key'], 'Validation, backup and patch use the submitted connection' );
		}
		provider_reset_calls();
		$disabled = save_provider_options( $subscriber, $enabled, array_merge( $enabled, [ 'cloudflare_auto_settings' => 0, 'cloudflare_old_settings' => 'tampered-hidden-field' ] ) );
		provider_check( $previous_settings === $remote_settings['zone-b'], 'Disable restores the exact previous values, including TTL zero' );
		provider_check( '' === $disabled['cloudflare_old_settings'], 'Verified restoration clears only its owned backup after the three patches succeed' );
	}
	foreach ( [ '', 'basic,on,on,0', '{broken', json_encode( [ 'zone_id' => 'zone-a', 'settings' => $previous_settings ] ), json_encode( [ 'zone_id' => 'zone-b', 'settings' => [ 'cache_level' => 'basic', 'rocket_loader' => 'on' ] ] ), json_encode( [ 'zone_id' => 'zone-b', 'settings' => [ 'cache_level' => 'basic', 'rocket_loader' => 'on', 'browser_cache_ttl' => -1 ] ] ) ] as $bad_backup ) {
		provider_reset_calls();
		$previous = array_merge( $enabled, [ 'cloudflare_old_settings' => $bad_backup ] );
		$result = save_provider_options( $subscriber, $previous, array_merge( $previous, [ 'cloudflare_auto_settings' => 0 ] ) );
		provider_check( [] === provider_mutations(), 'Missing, legacy, corrupt or cross-zone backup never invents restore values' );
		provider_check( $bad_backup === $result['cloudflare_old_settings'] && 'error' === get_transient( '17_cloudflare_update_settings' )[0]['result'], 'Rejected restoration retains backup and reports failure' );
	}
	provider_reset_calls(); $settings_failure = true;
	$result = save_provider_options( $subscriber, $saved, $input );
	provider_check( 0 === $result['cloudflare_auto_settings'] && '' === $result['cloudflare_old_settings'] && [] === provider_mutations(), 'Failed fresh backup cancels enable before remote mutation' );
	provider_reset_calls();
	$with_backup = array_merge( $saved, [ 'cloudflare_old_settings' => json_encode( [ 'zone_id' => 'zone-a', 'settings' => $previous_settings ] ) ] );
	$result = save_provider_options( $subscriber, $with_backup, $input );
	provider_check( 0 === $result['cloudflare_auto_settings'] && $with_backup['cloudflare_old_settings'] === $result['cloudflare_old_settings'] && [] === provider_mutations(), 'Unresolved backup blocks another zone from replacing existing recovery values' );
	provider_check( 1 === count( $settings_errors ), 'Unresolved backup has native visible feedback' );
	$settings_failure = false;
	provider_reset_calls(); $connection_failure = true; set_transient( 'noobrocket_cloudflare_is_api_keys_valid', true );
	$result = save_provider_options( $subscriber, $saved, $input );
	provider_check( [] === provider_mutations() && 0 === $result['cloudflare_auto_settings'], 'Invalid submitted connection cannot apply settings using cached success' );
	$connection_failure = false;
	provider_reset_calls(); $patch_failure = 'rocket_loader'; $remote_settings['zone-b'] = $previous_settings;
	$result = save_provider_options( $subscriber, $saved, $input );
	$outcomes = get_transient( '17_cloudflare_update_settings' ); unset( $outcomes['pre'] );
	provider_check( [ 'success', 'error', 'success' ] === array_column( $outcomes, 'result' ), 'Partial provider application retains each failure and success' );
	provider_check( $previous_settings === json_decode( $result['cloudflare_old_settings'], true )['settings'], 'Partial application retains recoverable original settings' );
	$patch_failure = '';
	provider_reset_calls(); $patch_failure = 'rocket_loader';
	$partial_restore = save_provider_options( $subscriber, $result, array_merge( $result, [ 'cloudflare_auto_settings' => 0 ] ) );
	provider_check( $result['cloudflare_old_settings'] === $partial_restore['cloudflare_old_settings'], 'Incomplete restoration keeps the original recovery backup' );
	provider_reset_calls();
	$re_enabled = save_provider_options( $subscriber, $partial_restore, array_merge( $partial_restore, [ 'cloudflare_auto_settings' => 1 ] ) );
	provider_check( $result['cloudflare_old_settings'] === $re_enabled['cloudflare_old_settings'], 'Re-enable after an incomplete restore cannot overwrite original recovery values' );
	provider_check( [] === array_filter( $provider_calls, function ( $call ) { return 'zones/zone-b/settings' === $call['path']; } ), 'Pending recovery is reused instead of recording partially applied state' );
	$patch_failure = '';
	provider_reset_calls();
	$fully_restored = save_provider_options( $subscriber, $re_enabled, array_merge( $re_enabled, [ 'cloudflare_auto_settings' => 0 ] ) );
	provider_check( '' === $fully_restored['cloudflare_old_settings'] && $previous_settings === $remote_settings['zone-b'] && 3 === count( provider_mutations() ), 'Successful retry restores original values, clears backup and does not replay remote changes during metadata write' );
	provider_reset_calls();
	$remote_settings['zone-b'] = $previous_settings;
	$enabled_for_race = save_provider_options( $subscriber, $saved, $input );
	$interleave_settings = true;
	$race_result = save_provider_options( $subscriber, $enabled_for_race, array_merge( $enabled_for_race, [ 'cloudflare_auto_settings' => 0 ] ) );
	provider_check( 'preserved' === $race_result['newer_settings'] && $enabled_for_race['cloudflare_old_settings'] === $race_result['cloudflare_old_settings'], 'Metadata cleanup never overwrites intervening full settings or their backup ownership' );
	$interleave_settings = false;
	provider_reset_calls(); $refuse_metadata_write = true;
	$failed_clear = save_provider_options( $subscriber, $enabled_for_race, array_merge( $enabled_for_race, [ 'cloudflare_auto_settings' => 0 ] ) );
	$clear_outcomes = get_transient( '17_cloudflare_update_settings' ); unset( $clear_outcomes['pre'] );
	provider_check( $enabled_for_race['cloudflare_old_settings'] === $failed_clear['cloudflare_old_settings'] && in_array( 'error', array_column( $clear_outcomes, 'result' ), true ), 'Refused metadata write preserves backup and distinguishes restored remote values from incomplete local bookkeeping' );
	$refuse_metadata_write = false;
	provider_reset_calls();
	$active_a = array_merge( $saved, [ 'cloudflare_auto_settings' => 1, 'cloudflare_old_settings' => json_encode( [ 'zone_id' => 'zone-a', 'settings' => $previous_settings ] ) ] );
	$result = save_provider_options( $subscriber, $active_a, array_merge( $active_a, [ 'cloudflare_zone_id' => 'zone-b' ] ) );
	provider_check( [] === provider_mutations() && $active_a['cloudflare_old_settings'] === $result['cloudflare_old_settings'] && 'zone-a' === $result['cloudflare_zone_id'] && 1 === count( $settings_errors ), 'Active zone migration retains the previous connection/recovery target and refuses silent optimal migration' );

	// PROVIDER-01: maliciously echoed credentials never reach log context or returned feedback.
	$sucuri = new \NoobRocket\Addon\Sucuri\Subscriber( new \NoobRocket\Admin\Options_Data( [ 'sucury_waf_api_key' => str_repeat( 'a', 32 ) . '/' . str_repeat( 'b', 32 ) ] ) );
	$clean = new \ReflectionMethod( $sucuri, 'clean_firewall_cache' ); $clean->setAccessible( true );
	foreach ( [ 'transport', 'empty_response', 'invalid_json', 'provider_failure' ] as $sucuri_case ) {
		\NoobRocket\Logger\Logger::$errors = [];
		$result = $clean->invoke( $sucuri );
		$visible = json_encode( \NoobRocket\Logger\Logger::$errors ) . $result->get_error_message();
		provider_check( is_wp_error( $result ), 'Sucuri ' . $sucuri_case . ' remains a failure' );
		provider_check( false === strpos( $visible, str_repeat( 'a', 32 ) ) && false === strpos( $visible, str_repeat( 'b', 32 ) ) && false === strpos( $visible, 'waf.sucuri.net/api?' ), 'Sucuri ' . $sucuri_case . ' never copies credential-bearing context/payload into logs or feedback' );
		provider_check( $sucuri_case === \NoobRocket\Logger\Logger::$errors[0][1]['category'], 'Sucuri ' . $sucuri_case . ' retains useful error category' );
	}
	$sucuri_case = 'success'; provider_check( true === $clean->invoke( $sucuri ), 'Sucuri accepted purge still succeeds' );
	$authorized = false; $count = $sucuri_calls;
	try { $sucuri->do_admin_post_noobrocket_purge_sucuri(); } catch ( ProviderActionStopped $e ) {}
	provider_check( $count === $sucuri_calls, 'Unauthorized Sucuri manual action sends no request' );
	$authorized = true; $valid_nonce = false;
	try { $sucuri->do_admin_post_noobrocket_purge_sucuri(); } catch ( ProviderActionStopped $e ) {}
	provider_check( $count === $sucuri_calls, 'Invalid Sucuri nonce sends no request' );
	restore_error_handler();
	echo 'PASS: ', $provider_checks, ' native provider boundary assertions; zero external requests.', PHP_EOL;
}
