<?php

use TodoPago\Sdk as Sdk;

require_once(dirname(__FILE__).'/../../../../wp-blog-header.php');
require_once(dirname(__FILE__).'/../lib/Sdk.php');

global $wpdb;
$row = $wpdb -> get_row(
"SELECT option_value FROM wp_options WHERE option_name = 'woocommerce_todopago_settings'"
);
$arrayOptions = unserialize($row -> option_value);

$esProductivo = $arrayOptions['ambiente'] == "prod";

$http_header = $esProductivo ? $arrayOptions['http_header_prod'] : $arrayOptions['http_header_test'];
$http_header = json_decode(html_entity_decode($http_header,TRUE));

$connector = new Sdk($http_header, $arrayOptions['ambiente']);

//opciones para el método getStatus 
$optionsGS = array('MERCHANT'=>$_GET['merchant'],'OPERATIONID'=>$_GET['order_id']);
$status = $connector->getStatus($optionsGS);

$status_json = json_encode($status);

$rta = '';
foreach ($status['Operations'] as $key => $value) {
	$rta .= "$key: $value \n";
}

echo($rta);