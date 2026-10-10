<?php

defined( 'ABSPATH' ) || exit;

/**
 * Conflict with Thrive Leads: override the DONOTCACHEPAGE behavior because this plugin add this constant!
 *
 * @since 2.5
 */
function noobrocket_override_donotcachepage_on_thrive_leads() {
	return defined( 'TVE_LEADS_VERSION' ) && TVE_LEADS_VERSION > 0;
}
add_filter( 'noobrocket_override_donotcachepage', 'noobrocket_override_donotcachepage_on_thrive_leads' );
