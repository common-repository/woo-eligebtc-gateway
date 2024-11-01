<?php

/* Lo comentamos porque se mezclan los avisos con las respuestas. Solo se deberia usar en modo debug
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
*/

/* Probado con exito en woocommerce  versiones  version 2.6.9 y superiores  */
	
	require_once('../../../../wp-config.php'); //Carga las funciones basicas
	
	function woocommerce_version_check( ) {
      if ( function_exists( 'is_woocommerce_active' ) && is_woocommerce_active() ) {
        global $woocommerce;
        return true;
      }else return false;
      
    }


	//$data = json_decode(file_get_contents('php://input'), true);
	
	$handle = fopen('php://input','r');
	$jsonInput = fgets($handle);

	$myfile = fopen("./debug_api.log", "a") or die("Unable to open file!");
	fwrite($myfile, "\r\n-------------------- Get Cart Details By CartID Request -----------------------\r\n");
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

		fwrite($myfile, 'cartId recuperado: ' .$decoded['cart_id']  ."\r\n");

	 
		//Para wopcommerce:
		$order      = wc_get_order( $decoded['cart_id'] );
		
		if(woocommerce_version_check()){
	 		$order_data = $order->get_data();
	 		//$order_status  = $order_data['status'];
	 		//$user_id = get_post_meta($decoded['cart_id'], '_customer_user', true);
	 		$user_id = $order->get_user_id();

		  	
		  	if (isset($order_data))
			{
		  		fwrite($myfile, 'Cart Details By CartID. CustomerID: ' .$user_id ."\r\n");

		  		header('Content-Type: application/json');
		  		$json =  '{"id_customer" : "' .$user_id .'", "id_carrier" : "0", "products" : ' .json_encode($order_data) .'}'; 
		  		echo str_replace("\\","",$json);
		  	}else{
		  		header('Content-Type: application/json');
		  		$json =  '{"jsonError": "unknown"}';
		  		echo $json;
		  	}
		}else{
		    $order_meta = get_post_meta($decoded['cart_id']);
		 	header('Content-Type: application/json');
	  	    $json =  '{"id_customer" : "' .$order_meta[_customer_user][0] .'", "id_carrier" : "0", "products" : ' .json_encode($order_meta) .'}'; 
		  	echo str_replace("\\","",$json);   
		}
	  	
	}else{
		header('Content-Type: application/json');
	  	$json =  '{"error" : "invalid token"}'; //'{"jsonError": "unknown"}';
	  	echo $json;
	}

	fclose($myfile);

