<?php

declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\LazyRenderContent\Activation;

use NoobRocket\Engine\Common\Context\ContextInterface;
use NoobRocket\Engine\Common\PerformanceHints\ActivationFactoryInterface;

class ActivationFactory implements ActivationFactoryInterface {
	/**
	 * Context instance.
	 *
	 * @var ContextInterface
	 */
	protected $context;

	/**
	 * Instantiate the class.
	 *
	 * @param ContextInterface $context LRC Context instance.
	 */
	public function __construct( ContextInterface $context ) {
		$this->context = $context;
	}

	/**
	 * Provides a Context object.
	 *
	 * @return ContextInterface
	 */
	public function get_context(): ContextInterface {
		return $this->context;
	}
}
