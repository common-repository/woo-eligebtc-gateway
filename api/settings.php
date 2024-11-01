<?php

/* Lo comentamos porque se mezclan los avisos con las respuestas. Solo se deberia usar en modo debug
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
*/

	/* Probado con exito en woocommerce en version 2.6.9 y superiores */

	require_once('../../../../wp-config.php'); //Carga las funciones basicas
	
	$handle = fopen('php://input','r');
	$jsonInput = fgets($handle);

	$myfile = fopen("./debug_api.log", "a") or die("Unable to open file!");
	fwrite($myfile, "\r\n-------------------- Change Settings -----------------------\r\n");
	fwrite($myfile, 'Datos recibidos: ' .$jsonInput);
	
	if(function_exists('json_decode'))
  	{	
		$decoded = json_decode($jsonInput, true);

  	}else{
		$decoded = rmJSONdecode($jsonInput);
  	}
	fclose($handle);

	// Save credentials to settings API
	$settings_array = (array) get_option( 'woocommerce_EBEC_eligebtc_settings', array() );
	$tokenS=$settings_array['token'];
	$tokenR=$decoded['token'];
	if ($tokenS==$tokenR){
  	
	  	if (isset($decoded['color'])) $settings_array[ 'color' ] = $decoded['color'];
	  	if (isset($decoded['emulation']))
	  		if ($decoded['emulation'] =='0'){
	  		 	$settings_array[ 'environment' ] =  'live';
	  		}
			else {
				$settings_array[ 'environment' ] =  'sandbox';
			}

		update_option( 'woocommerce_EBEC_eligebtc_settings', $settings_array );

	  	header('Content-Type: application/json');
	  	$json =  '{"result" : "ok"}';
	  	echo $json;

	}else{
		header('Content-Type: application/json');
	  	$json =  '{"error" : "invalid token"}'; //'{"jsonError": "unknown"}';
	  	echo $json;
	}

	fclose($myfile);
