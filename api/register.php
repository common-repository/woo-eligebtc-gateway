<?php

/* Lo comentamos porque se mezclan los avisos con las respuestas. Solo se deberia usar en modo debug
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
*/

	/* Probado con exito en woocommerce  en version 2.6.9 y superiores  */
	
	require_once('../../../../wp-config.php'); //Carga las funciones basicas

	$handle = fopen('php://input','r');
	$jsonInput = fgets($handle);

	$myfile = fopen("./debug_api.log", "a") or die("Unable to open file!");
	fwrite($myfile, "\r\n-------------------- Register -----------------------\r\n");
	fwrite($myfile, 'Datos recibidos: ' .$jsonInput ."\r\n");
	
	if(function_exists('json_decode'))
  	{	
		$decoded = json_decode($jsonInput, true);

  	}else{
		$decoded = rmJSONdecode($jsonInput);
  	}
	fclose($handle);

	
	fwrite($myfile, 'clientId: ' .$decoded['clientId']  ."\r\n");
	fwrite($myfile, 'token: ' .$decoded['token']  ."\r\n");
	fwrite($myfile, 'email: ' .$decoded['email']  ."\r\n");
 
  	$clientId = $decoded['clientId'];
  	
  	if (isset($decoded['email'])) $email = $decoded['email']; else $email='';
  	
  	if (isset($clientId))
	{
  		$_settings = array();
  		
  		$_settings[ 'client_id' ] = trim($clientId);	
  		$_settings[ 'email' ] = trim($email);	  		
  		$_settings[ 'color' ] = '#1abc9c';	
	    $_settings[ 'environment' ] = 'live';	
	    $option_name = 'woocommerce_EBEC_eligebtc_settings';

	    if ( get_option( $option_name ) !== false ) {
    		$resudp= update_option( $option_name, $_settings );
		} else {
			$deprecated = null;
    		$autoload = 'no';
    		$resudp = add_option( $option_name, $_settings, $deprecated, $autoload );
		}

		fwrite($myfile, 'Setting update with response ' .$resudp  ."\r\n");

  		header('Content-Type: application/json');
  		$json =  '{"result" : "ok"}';
  		echo $json;
  	}else{
  		fwrite($myfile, 'Error al no venir el clientId'  ."\r\n");
  		header('Content-Type: application/json');
  		$json =  '{"jsonError": "Error. No all data informed"}';
  		echo $json;
  	}

	fclose($myfile);
