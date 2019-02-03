<?php
/**
 * Nosara Tracks for Woo
 */

require_once( dirname( __FILE__ ) . '/libraries/tracks/client.php' );

function action_woocommerce_update_product( $product_id ) {
	$properties = array(
		'product_id' => $product_id,
		'hook' => 'edit_post'
	);

	WooTracks::record_event( 'wca_test_update_product', $properties );
}

function track_woo_usage() {
	add_action( 'edit_post', 'action_woocommerce_update_product', 10, 1 );
}

add_action( 'init',  'track_woo_usage' );
