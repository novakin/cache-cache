<?php

namespace NoobRocket\ThirdParty;

use NoobRocket\Event_Management\Subscriber_Interface;

/**
 * Interface for Subscriber Factories
 *
 * @since 3.6.3
 */
interface SubscriberFactoryInterface {

	/**
	 * Get a Subscriber Interface object.
	 *
	 * @since 3.6.3
	 *
	 * @return Subscriber_Interface
	 */
	public function get_subscriber();
}
