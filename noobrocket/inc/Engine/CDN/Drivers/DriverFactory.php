<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\Drivers;

use NoobRocket\Dependencies\Psr\Container\ContainerInterface;
use NoobRocket\Engine\CDN\Context;

/**
 * Factory for creating CDN drivers based on current context
 */
class DriverFactory {

	/**
	 * Container instance for dependency injection
	 *
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * CDN Context for determining active driver
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Container instance.
	 * @param Context            $context Context instance.
	 */
	public function __construct( ContainerInterface $container, Context $context ) {
		$this->container = $container;
		$this->context   = $context;
	}

	/**
	 * Create appropriate driver based on current context
	 *
	 * @return DriverInterface|null Driver container ID.
	 */
	public function create(): ?DriverInterface {
		$active_driver = $this->context->get_driver();

		switch ( $active_driver ) {
			case Context::HOSTEDCDN_FREE_TYPE:
				return $this->container->get( 'cdn_driver_free' );

			case Context::HOSTEDCDN_PAID_TYPE:
				return $this->container->get( 'cdn_driver_paid' );

			case Context::BYOCDN_TYPE:
				return $this->container->get( 'cdn_driver_byocdn' );

			default:
				return null;
		}
	}
}
