<?php
declare( strict_types=1 );

namespace NoobRocket\Engine\Common\PerformanceHints;

use NoobRocket\Engine\Common\Database\QueryInterface;
use NoobRocket\Engine\Common\Database\TableInterface;
use NoobRocket\Engine\Common\PerformanceHints\AJAX\ControllerInterface as AjaxControllerInterface;
use NoobRocket\Engine\Common\PerformanceHints\Frontend\ControllerInterface as FrontendControllerInterface;
use NoobRocket\Engine\Common\Context\ContextInterface;

interface FactoryInterface {
	/**
	 * Provides an Ajax interface.
	 *
	 * @return AjaxControllerInterface
	 */
	public function get_ajax_controller(): AjaxControllerInterface;

	/**
	 * Provides a Frontend interface.
	 *
	 * @return FrontendControllerInterface
	 */
	public function get_frontend_controller(): FrontendControllerInterface;

	/**
	 * Provides a Table interface.
	 *
	 * @return TableInterface
	 */
	public function table(): TableInterface;

	/**
	 * Provides a Queries interface.
	 *
	 * @return QueryInterface
	 */
	public function queries(): QueryInterface;

	/**
	 * Provides a Context interface
	 *
	 * @return ContextInterface
	 */
	public function get_context(): ContextInterface;
}
