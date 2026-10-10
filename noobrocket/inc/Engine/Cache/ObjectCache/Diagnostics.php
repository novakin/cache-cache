<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Cache\ObjectCache;

use NoobRocket\Event_Management\Subscriber_Interface;

/** Detect the installed provider and test only a short-lived, owned cache key. */
class Diagnostics implements Subscriber_Interface {
	public static function get_subscribed_events(): array {
		return [
			'noobrocket_before_dashboard_content' => [ 'render', 15 ],
		];
	}

	public function render(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		$data = [ 'object_cache' => $this->status() ];
		require NOOBROCKET_PATH . 'views/settings/partials/object-cache-diagnostics.php';
	}

	public function status(): array {
		global $wp_object_cache;
		$result = [
			'external' => (bool) wp_using_ext_object_cache(),
			'provider' => __( 'Built-in WordPress object cache', 'noobrocket' ),
			'connected' => null,
			'health' => false,
			'message' => '',
		];
		if ( $result['external'] ) {
			$result['provider'] = __( 'Existing external object-cache provider', 'noobrocket' );
		}
		if ( is_object( $wp_object_cache ) ) {
			$class = new \ReflectionClass( $wp_object_cache );
			if ( ! $class->isAnonymous() ) {
				$result['provider'] = $result['external'] ? $class->getName() : $result['provider'];
			}
			if ( method_exists( $wp_object_cache, 'is_connected' ) && is_callable( [ $wp_object_cache, 'is_connected' ] ) ) {
				$method = new \ReflectionMethod( $wp_object_cache, 'is_connected' );
				if ( 0 === $method->getNumberOfRequiredParameters() ) {
					try {
						$connected = $wp_object_cache->is_connected();
						$result['connected'] = is_bool( $connected ) ? $connected : null;
					} catch ( \Throwable $error ) {
						$result['connected'] = false;
					}
				}
			}
		}
		$key = 'health-' . wp_generate_uuid4();
		$group = 'noobrocket-health';
		$value = wp_generate_uuid4();
		try {
			$written = wp_cache_set( $key, $value, $group, 30 );
			$found = false;
			$read = wp_cache_get( $key, $group, true, $found );
			$result['health'] = $written && $found && $value === $read && false !== $result['connected'];
		} catch ( \Throwable $error ) {
			$result['health'] = false;
		} finally {
			try {
				if ( ! wp_cache_delete( $key, $group ) ) {
					$result['health'] = false;
				}
			} catch ( \Throwable $error ) {
				$result['health'] = false;
			}
		}
		$result['message'] = $result['health']
			? __( 'The write, read and cleanup checks passed in this request.', 'noobrocket' )
			: __( 'The connection, write, read or cleanup check failed.', 'noobrocket' );
		return $result;
	}
}
