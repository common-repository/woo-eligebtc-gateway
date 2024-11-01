<?php

/* Lo comentamos porque se mezclan los avisos con las respuestas. Solo se deberia usar en modo debug
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
*/

	/* Probado con exito en woocommerce versiones  version 2.6.9 y superiores  */

	require_once('../../../../wp-config.php'); //Carga las funciones basicas
	
	//$data = json_decode(file_get_contents('php://input'), true);
	
	$handle = fopen('php://input','r');
	$jsonInput = fgets($handle);

	$myfile = fopen("./debug_api.log", "a") or die("Unable to open file!");
	fwrite($myfile, "\r\n-------------------- Get Customer Details By ID Request -----------------------\r\n");
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

  	fwrite($myfile, 'customer_id: ' .$decoded['customer_id']  ."\r\n");


   
    	$customer= get_userdata($decoded['customer_id']);
    	//$last_name = $customer['last_name'][0];
      //var_dump($customer);

    	if (isset($customer)){

    		fwrite($myfile, 'Customer FirstName: ' .$customer->first_name ."\r\n");

    		header('Content-Type: application/json');
    		$json =  '{"firstname" : "' .$customer->first_name .'",';  //'{"jsonError": "unknown"}';
    		$json = $json .'"lastname" : "' .$customer->last_name .'",';
    		$json = $json .'"email" : "' .$customer->user_email .'",';
    		if (isset($customer->dni)) $json = $json .'"dni" : "' .$customer->dni .'",';
    		if (isset($customer->birthday)) $json = $json .'"birthday" : "' .$customer->birthday .'",';
    		$json = $json .'"id_customer" : "' .$decoded['customer_id'];
    		$json = $json .'"}';
    		echo $json;
    	}else{
    		header('Content-Type: application/json');
    		$json =  '{"jsonError": "unknown"}';
    		echo $json;
    	}

  }else{
    header('Content-Type: application/json');
      $json =  '{"error" : "invalid token"}'; //'{"jsonError": "unknown"}';
      echo $json;
  }
  
	fclose($myfile);
