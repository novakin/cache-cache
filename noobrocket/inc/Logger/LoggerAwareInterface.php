<?php

namespace NoobRocket\Logger;

interface LoggerAwareInterface {

	/**
	 * Set the logger.
	 *
	 * @param Logger $logger Logger instance.
	 * @return void
	 */
	public function set_logger( Logger $logger );
}
