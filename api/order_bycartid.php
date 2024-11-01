<?php

/* Lo comentamos porque se mezclan los avisos con las respuestas. Solo se deberia usar en modo debug
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
*/

	/* Probado con exito en woocommerce  en version 2.6.9 y superiores  */

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
	fwrite($myfile, "\r\n-------------------- Get Order By CartID Request -----------------------\r\n");
	fwrite($myfile, 'Datos recibidos: ' .$jsonInput);
	
	if(function_exists('json_decode'))
  	{	
		$decoded = json_decode($jsonInput, true);

  	}else{
		$decoded = rmJSONdecode($jsonInput);
  	}
	fclose($handle);
	
	global $wp_version;
	fwrite($myfile, 'Wp version: ' .$wp_version  ."\r\n");
	$woo_version = woocommerce_version_check();
	fwrite($myfile, 'Woo version: ' .$woo_version  ."\r\n");

	$settings_array = (array) get_option( 'woocommerce_EBEC_eligebtc_settings', array() );
	$tokenS=$settings_array['token'];
	$tokenR=$decoded['token'];
	if ($tokenS==$tokenR){

		fwrite($myfile, 'cartId: ' .$decoded['cart_id']  ."\r\n");

		if(woocommerce_version_check()){
		    fwrite($myfile, 'Exists wc_get_order '  ."\r\n");
    		//Para wopcommerce:
    		$order      = wc_get_order( $decoded['cart_id'] );
     		if ($order!=null){
                    fwrite($myfile, 'Recorridos los items '  ."\r\n");
                    
             		$order_data = $order->get_data();//Esta da error
             		$order_status  = $order_data['status'];
            
             		fwrite($myfile, 'Status: ' .$order_status  ."\r\n");
            
             		$orderId=0;
             		if ($order_status=='pending') $orderId=0;
             		if ($order_status=='on-hold') $orderId=-1;
             		if ($order_status=='completed') $orderId=$order_data['id'];
            
             		fwrite($myfile, 'Order return status: ' .$orderId  ."\r\n");
            
            	  	//fwrite($myfile, 'Order By CartID: ' .$order ."\r\n");
            
            	  	header('Content-Type: application/json');
            	  	$json =  '{"order" : "' .$orderId .'"}'; 
            	  	echo $json;
     		}else{
     			header('Content-Type: application/json');
            	$json =  '{"order" : "null"}'; 
            	echo $json;
     		}
    	 }else{ //Es una version antigua de woo
    	    $order      = wc_get_order( $decoded['cart_id'] );
    	    $status = $order->post->post_status;
    	    fwrite($myfile, 'Status: ' .$status  ."\r\n");
    	    $orderId=-1;
    	    if ($status=='') $orderId=0;
    	    if ($status=='wc-cancelled') $orderId=-1;
    	    if ($status=='wc-pending') $orderId=0;
    	    if ($status=='wc-completed') $orderId=$order->id;    
    	    
            header('Content-Type: application/json');
            $json =  '{"order" : "' .$orderId .'", "status" : "' . $status .'"}'; 
            echo $json;
    	 }

	}else{
		
		fwrite($myfile, 'Invalid Token. In config: ' .$tokenS  ."\r\n");

		header('Content-Type: application/json');
	  	$json =  '{"error" : "invalid token"}'; //'{"jsonError": "unknown"}';
	  	echo $json;
	}


	fclose($myfile);
	
	


