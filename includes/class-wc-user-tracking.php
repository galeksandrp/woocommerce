<?php
/**
 * Nosara Tracks for Woo
 */

require_once( dirname( __FILE__ ) . '/libraries/tracks/client.php' );

class WooUserTracking {
	static function track_woo_usage() {
		$properties = array();
		woo_tracks_record_event( 'my_event_test', $properties );
	}
}


add_action( 'init',  array( 'WooUserTracking', 'track_woo_usage' ) );
