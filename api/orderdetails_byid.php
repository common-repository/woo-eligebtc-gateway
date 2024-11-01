<?php

/* Lo comentamos porque se mezclan los avisos con las respuestas. Solo se deberia usar en modo debug
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
*/

	/* Probado con exito en woocommerce  en version 2.6.9 y superiores  */
	
	require_once('../../../../wp-config.php'); //Carga las funciones basicas
	
	//$data = json_decode(file_get_contents('php://input'), true);

	function woocommerce_version_check( ) {
      if ( function_exists( 'is_woocommerce_active' ) && is_woocommerce_active() ) {
        global $woocommerce;
        return true;
      }else return false;
      
    }
	
	$handle = fopen('php://input','r');
	$jsonInput = fgets($handle);

	$myfile = fopen("./debug_api.log", "a") or die("Unable to open file!");
	fwrite($myfile, "\r\n-------------------- Get Order By ID Request -----------------------\r\n");
	fwrite($myfile, 'Datos recibidos: ' .$jsonInput);
	
	if(function_exists('json_decode'))
  	{	
		$decoded = json_decode($jsonInput, true);

  	}else{
		$decoded = rmJSONdecode($jsonInput);
  	}
	fclose($handle);

	$settings_array = (array) get_option( 'woocommerce_EBEC_eligebtc_settings', array() );
	$tokenS=$settings_array['token'];
	$tokenR=$decoded['token'];
	if ($tokenS==$tokenR){

		fwrite($myfile, 'Order Id: ' .$decoded['order_id']  ."\r\n");
	 
	  	//Para wopcommerce:
		$order      = wc_get_order( $decoded['order_id'] );

		if(woocommerce_version_check()){

	 		$order_data = $order->get_data();
	 		$order_status  = $order_data['status'];		  

		  	if (isset($order_data)){
			  	header('Content-Type: application/json');
			  	$json =  '{"id_cart" : "' .$decoded['order_id'] .'", '; //En woocommerce la orden es la misma que el carrito.
			  	$json = $json .'"id_customer" : "' .$order_data['customer_id'] .'", ';
			  	$json = $json .'"module" : "' .$order_data['payment_method'] .'", ';
			  	$json = $json .'"shipping_number" : "' .$order_data['transaction_id'] .'", ';
			  	$json = $json .'"invoice_number" : "' .$order_data['transaction_id']  .'", ';
			  	$json = $json .'"delivery_number" : "' .$order_data['transaction_id']  .'", ';
			  	$json = $json .'"status" : "' .$$order_data['status']  .'", ';
			  	$json = $json .'"payment" : ' .$order;
			  	$json = $json .'}'; 
			  	echo $json;
			}else{
				header('Content-Type: application/json');
		  		$json =  '{"jsonError": "unknown"}';
		  		echo $json;
			}
		}else{
			header('Content-Type: application/json');
			$order_meta = get_post_meta($decoded['cart_id']);

			$json =  '{"id_cart" : "' .$decoded['order_id'] .'", '; //En woocommerce la orden es la misma que el carrito.			
			$json = $json .'"id_customer" : "' .$order_meta[_customer_user][0] .'", ';
			$json = $json .'"module" : "' .$order_meta[_payment_method_title][0] .'", ';
			$json = $json .'"shipping_number" : "' .$order->post->guid .'", ';
			$json = $json .'"invoice_number" : "' .$order->post->guid  .'", ';
			$json = $json .'"delivery_number" : "' .$order->post->guid  .'", ';
			$json = $json .'"status" : "' .$order->post->post_status  .'", ';
			if (isset($order)) $json = $json .'"payment" : ' .json_encode($order);
			$json = $json .'}'; 
			echo $json;
		}

	}else{
		header('Content-Type: application/json');
	  	$json =  '{"error" : "invalid token"}'; 
	  	echo $json;
	}

	fclose($myfile);
