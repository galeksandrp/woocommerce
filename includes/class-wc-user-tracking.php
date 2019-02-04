<?php
/**
 * Nosara Tracks for Woo
 */

require_once( dirname( __FILE__ ) . '/libraries/tracks/client.php' );

function action_woocommerce_update_product( $product_id, $post ) {
	if ( $post->post_type !== 'product' ) {
		return;
	};
	$properties = array(
		'product_id' => $product_id,
	);

	WooTracks::record_event( 'wca_test_update_product', $properties );
}

function track_woo_usage() {
	add_action( 'edit_post', 'action_woocommerce_update_product', 10, 2 );
}

add_action( 'init',  'track_woo_usage' );
