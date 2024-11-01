<?php

/* Lo comentamos porque se mezclan los avisos con las respuestas. Solo se deberia usar en modo debug
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
*/

	/* Probado con exito en woocommerce version 2.6.9 y superiores */

	require_once('../../../../wp-config.php'); //Carga las funciones basicas

	function woocommerce_version_check( ) {
      if ( function_exists( 'is_woocommerce_active' ) && is_woocommerce_active() ) {
        global $woocommerce;
        return true;
      }else return false;
      
    }
	
	$handle = fopen('php://input','r');
	$jsonInput = fgets($handle);

	$myfile = fopen("./debug_api.log", "a") or die("Unable to open file!");
	fwrite($myfile, "\r\n-------------------- On-Hold Order -----------------------\r\n");
	fwrite($myfile, 'Datos recibidos: ' .$jsonInput ."\r\n");
	
	if(function_exists('json_decode'))
  	{	
		$decoded = json_decode($jsonInput, true);

  	}else{
		$decoded = rmJSONdecode($jsonInput);
  	}

	$settings_array = (array) get_option( 'woocommerce_EBEC_eligebtc_settings', array() );
	$tokenS=$settings_array['token'];
	$tokenR=$decoded['token'];
	if ($tokenS==$tokenR){

		//Para wopcommerce:
		$order      = wc_get_order( $decoded['cartId'] );

		if(woocommerce_version_check()){
			$order_data = $order->get_data();
 			$order_status  = $order_data['status'];
		}else{
			$order_status = $order->post->post_status;
			$order_data = $order_meta;
		}

		if (isset($decoded['orderID'])) fwrite($myfile, 'OrderID: ' .$decoded['orderID'] ."\r\n");
		if (isset($decoded['cartId'])) fwrite($myfile, 'cartId: ' .$decoded['cartId']  ."\r\n");
		if (isset($decoded['price'])) fwrite($myfile, 'price: ' .$decoded['price']  ."\r\n");
		if (isset($decoded['currency'])) fwrite($myfile, 'currency: ' .$decoded['currency']  ."\r\n");
		if (isset($decoded['clientID'])) fwrite($myfile, 'clientID: ' .$decoded['clientID']  ."\r\n");
		if (isset($decoded['status'])) fwrite($myfile, 'status: ' .$decoded['status']  ."\r\n");
		if (isset($decoded['status'])) fwrite($myfile, 'status in woo: ' .$order_status  ."\r\n");
	  	
	  	fwrite($myfile, 'Order By CartID: ' .$order_data ."\r\n");

		//$eligebtc = new eligebtc();			
		if (in_array($decoded['status'], array('on-hold')))
		{
          if ($order_status == 'pending' || $order_status == 'wc-pending'){ //Si es 0 es que no esta creada
          	
	    	fwrite($myfile, 'El cartId ' .$decoded['cartId'] .' no tiene orden asociada. La actualizamos a on-hold.' ."\r\n");

	    	$order_toconfirm = new WC_Order($decoded['cartId']);
			$order_toconfirm->update_status('on-hold');

	    	fwrite($myfile, 'El cartId ' .$decoded['cartId'] .' se ha actualizado la orden correctamente a on-hold.' ."\r\n");
             
            header('Content-Type: application/json');
  	     	$json =  '{"result" : "ok"}';
  	     	echo $json;

          }else{ 
	    	//echo 'El cartId ' .$decoded['cartId'] .' esta procesada. No hacemos nada. El estado actual es ' .$order_status ."\r\n";
	    	fwrite($myfile, 'El cartId ' .$decoded['cartId'] .' esta procesada. No hacemos nada. El estado actual es ' .$order_status);
          	header('Content-Type: application/json');
		  	$json =  '{"error" : "order already procesed or canceled"}';
		  	echo $json;
         }
	    }else{
	    	//echo 'El cartId ' .$decoded['cartId'] .' no esta en status confirmado por el backoffice de eligebtc' ."\r\n";
	    	fwrite($myfile, 'El cartId ' .$decoded['cartId'] .' no esta en status on-hold por el backoffice de eligebtc' ."\r\n");
	    	header('Content-Type: application/json');
		  	$json =  '{"error" : "order not procesed in platform"}'; 
		  	echo $json;
	    }

		
	}else{
		header('Content-Type: application/json');
	  	$json =  '{"error" : "invalid token"}';
	  	echo $json;
	}

fclose($myfile);