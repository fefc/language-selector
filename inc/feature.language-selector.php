<?php # -*- coding: utf-8 -*-

add_action( 'inpsyde_mlp_loaded', 'mlp_feature_language_selector' );

/**
 * @param Inpsyde_Property_List_Interface $data Plugin data.
 *
 * @return void
 */
function mlp_feature_language_selector( Inpsyde_Property_List_Interface $data ) {

	$controller = new Mlp_Language_Selector(
		$data->get( 'module_manager' ),
		$data->get( 'language_api' ),
		$data->get( 'assets' )
	);
	$controller->initialize();
}
