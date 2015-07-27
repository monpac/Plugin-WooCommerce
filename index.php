<?php
/*
Plugin Name: TodoPago para WooCommerce
Description: TodoPago para Woocommerce.
Version: 1.0
Author: Todo Pago
*/

use TodoPago\Sdk as Sdk;

//Llama a la función woocommerce_todopago_init cuando se cargan los plugins. 0 es la prioridad.
add_action('plugins_loaded', 'woocommerce_todopago_init', 0);

function woocommerce_todopago_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_TodoPago_Gateway extends WC_Payment_Gateway{
    
    public function __construct(){
      $this -> id             = 'todopago';
      $this -> icon           = apply_filters('woocommerce_todopago_icon', plugins_url('images/todopago.jpg',__FILE__));
      $this -> medthod_title  = 'Todo Pago';
      $this -> has_fields     = false;

      $this -> init_form_fields();
      $this -> init_settings(); //Carga en el array settings los valores de los campos persistidos de la base de datos

      //Datos generales
      $this -> title            = $this -> settings['title'];
      $this -> description      = $this -> settings['description'];
      $this -> ambiente         = $this -> settings['ambiente'];
      $this -> tipo_segmento    = $this -> settings['tipo_segmento'];
      //$this -> canal_ingreso    = $this -> settings['canal_ingreso'];
      $this -> deadline         = $this -> settings['deadline'];

      //Datos ambiente de test
      $this -> http_header_test = $this -> settings['http_header_test'];
      $this -> security_test    = $this -> settings['security_test'];
      $this -> merchant_id_test = $this -> settings['merchant_id_test'];

      //Datos ambiente de producción
      $this -> http_header_prod = $this -> settings['http_header_prod'];
      $this -> security_prod    = $this -> settings['security_prod'];
      $this -> merchant_id_prod = $this -> settings['merchant_id_prod'];

      //Datos estado de pedidos
      $this -> estado_inicio    = $this -> settings['estado_inicio'];
      $this -> estado_aprobacion= $this -> settings['estado_aprobacion'];
      $this -> estado_rechazo   = $this -> settings['estado_rechazo'];
      $this -> estado_offline   = $this -> settings['estado_offline'];

      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

      //Llama a la función admin_options definida más abajo
      if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')){
        add_action('woocommerce_update_options_payment_gateways_' . $this -> id, array(&$this, 'process_admin_options'));
      } else {
        add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
      }

      //Llamado al first step
      add_action('woocommerce_receipt_' . $this->id, array($this, 'first_step_todopago'));

      //Llamado al second step
      add_action('woocommerce_thankyou', array($this, 'second_step_todopago'));
      
      
    }//End __construct

    function init_form_fields(){
      global $woocommerce;
      require_once $woocommerce -> plugin_path() . '/includes/wc-order-functions.php';

      $this -> form_fields = array(
        'enabled' => array(
            'title' => 'Habilitar/Deshabilitar',
            'type' => 'checkbox',
            'label' => 'Habilitar modulo de pago TodoPago',
            'default' => 'no'),
        'title' => array(
            'title' => 'Título',
            'type'=> 'text',
            'description' => 'Título que el usuario ve durante el checkout',
            'default' => 'TodoPago'),
        'description' => array(
            'title' => 'Descripción',
            'type' => 'textarea',
            'description' => 'Descripción que el usuario ve durante el checkout',
            'default' => 'Paga de manera segura mediante TodoPago<br>Solo para la república argentina'),
        'ambiente' => array(
            'title' => 'Ambiente',
            'type' => 'select',
            'description' => 'Seleccione el ambiente con el que desea trabajar',
            'options' => array(
                'test' => 'developers',
                'prod' => 'produccion')),
        'tipo_segmento' => array(
            'title' => 'Tipo de Segmento',
            'type' => 'select',
            'description' => 'Seleccione el tipo de segmento con el que desea trabajar',
            'options' => array(
                /*'retail' => 'Retail',
                'servicios' => 'Servicios',
                'digital_goods' => 'Digital Goods',
                'ticketing' => 'Ticketing')),*/
                'retail' => 'Retail')),
        /*'canal_ingreso' => array(
            'title' => 'Canal de ingreso del pedido',
            'type' => 'select',
            'options' => array(
                'Web' => 'Web',
                'Mobile' => 'Mobile',
                'Telefonica' => 'Telefonica')),*/
        'deadline' => array(
            'title' => 'Deadline',
            'type'=> 'text',
            'description' => 'Dias maximos para la entrega'),

        'titulo_testing' => array( 'title' => 'Ambiente de Testing', 'type' => 'title', 'description' => 'Datos correspondientes al ambiente de testing', 'id' => 'testing_options' ),

        'http_header_test' => array(
            'title' => 'HTTP Header',
            'type' => 'text',
            'description' => 'Header en formato JSON. Ejemplo: <br>
                              {"Authorization":"PRISMA 912EC803B2CE49E4A541068D12345678"}'),
        'security_test' => array(
            'title' => 'Security',
            'type' => 'text',
            'description' => 'Código provisto por Todo Pago'),
        'merchant_id_test' => array(
            'title' => 'Merchant ID',
            'type' => 'text',
            'description' => 'Nombre de comercio provisto por Todo Pago'),

        'titulo_produccion' => array( 'title' => 'Ambiente de Producción', 'type' => 'title', 'description' => 'Datos correspondientes al ambiente de producción', 'id' => 'produccion_options' ),

        'http_header_prod' => array(
            'title' => 'HTTP Header',
            'type' => 'text',
            'description' => 'Header en formato JSON. Ejemplo: <br>
                              {"Authorization":"PRISMA 912EC803B2CE49E4A541068D12345678"}'),
        'security_prod' => array(
            'title' => 'Security',
            'type' => 'text',
            'description' => 'Código provisto por Todo Pago'),
        'merchant_id_prod' => array(
            'title' => 'Merchant ID',
            'type' => 'text',
            'description' => 'Nombre de comercio provisto por Todo Pago'),

        'titulo_estados_pedidos' => array( 'title' => 'Estados del Pedido', 'type' => 'title', 'description' => 'Datos correspondientes al estado de los pedidos', 'id' => 'estados_pedido_options' ),

        'estado_inicio' => array(
            'title' => 'Estado cuando la transacción ha<br>sido iniciada',
            'type' => 'select',
            'options' => wc_get_order_statuses(),
            'default' => 'wc-pending',
            'description' => 'Valor por defecto: Pendiente de pago'),
        'estado_aprobacion' => array(
            'title' => 'Estado cuando la transacción ha<br>sido aprobada',
            'type' => 'select',
            'options' => wc_get_order_statuses(),
            'default' => 'wc-completed',
            'description' => 'Valor por defecto: Completado'),
        'estado_rechazo' => array(
            'title' => 'Estado cuando la transacción ha<br>sido rechazada',
            'type' => 'select',
            'options' => wc_get_order_statuses(),
            'default' => 'wc-failed',
            'description' => 'Valor por defecto: Falló'),
        'estado_offline' => array(
            'title' => 'Estado cuando la transacción ha<br>sido offline',
            'type' => 'select',
            'options' => wc_get_order_statuses())
      );
    }

    //Muestra el título e imprime el formulario de configuración del plugin en la página de ajustes
    public function admin_options(){
      echo '<h3> TodoPago </h3>';
      echo '<p> Medio de pago TodoPago </p>';
      echo '<table class="form-table">';
      $this -> generate_settings_html(); //Generate the HTML For the settings form.
      echo '</table><br>';

      $urlDataTables = plugins_url('js/jquery.dataTables.min.js', __FILE__);
      echo '<script type="text/javascript" src="' . $urlDataTables . '"></script>';

      include_once dirname(__FILE__)."/view/status.php";
    }

    //Se ejecuta luego de Finalizar compra -> Realizar el pago
    function first_step_todopago($order_id){

      if(isset($_GET["second_step"])){
        //Second Step
        $this -> second_step_todopago();
      
      } else {

        $order = new WC_Order($order_id);
        //var_dump($order);

        if($order->payment_method == 'todopago'){

          $this -> _logTodoPago($order_id,'first step a '.$this->ambiente);

          require_once(dirname(__FILE__).'/lib/Sdk.php');
          
          $esProductivo = $this -> ambiente == "prod"; 
          $http_header = $this -> getHttpHeader($esProductivo);

          $connector = new Sdk($http_header, $this -> ambiente);

          $this -> setOrderStatus($order,'estado_inicio');

          $returnURL = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}/{$_SERVER['REQUEST_URI']}" . '&second_step=true';

          $optionsSAR_comercio = $this -> getOptionsSARComercio($esProductivo, $returnURL);
          $optionsSAR_operacion = $this -> getOptionsSAROperacion($esProductivo, $order);
          $optionsSAR_operacion = array_merge_recursive($optionsSAR_operacion, $this -> getParamsCybersource($order));

          $paramsSAR['comercio'] = $optionsSAR_comercio;
          $paramsSAR['operacion'] = $optionsSAR_operacion;
          $this -> _logTodoPago($order_id, "params SAR", json_encode($paramsSAR));

          $rta_SAR = $connector->sendAuthorizeRequest($optionsSAR_comercio, $optionsSAR_operacion);
          $this -> _logTodoPago($order_id, "responseSAR", json_encode($rta_SAR));

          $requestKey = $rta_SAR["RequestKey"];
          $publicRequestKey = $rta_SAR['PublicRequestKey'];
          $URL_Request = $rta_SAR["URL_Request"];
          $StatusCode = $rta_SAR["StatusCode"];

          global $wpdb;
          $wpdb->delete($wpdb->postmeta, 
                        array('post_id' => $order_id, 'meta_key' => "request_key"), 
                        array('%d','%s') 
          );
          
          $wpdb->insert($wpdb->postmeta, 
                        array('post_id' => $order_id, 'meta_key' => "request_key", 'meta_value' => $requestKey), 
                        array('%d','%s','%s') 
          );

          $fechaActual = new DateTime();
          $wpdb->insert(
            $wpdb->prefix . 'todopago_transaccion', 
            array('id_orden'=>$order_id,
              'params_SAR'=>json_encode($paramsSAR),
              'response_SAR'=>json_encode($rta_SAR),
              'request_key'=>$requestKey,
              'public_request_key'=>$publicRequestKey
            ),
            array('%d','%s','%s','%s') 
          );

          if($StatusCode == -1){
            echo '<p> Gracias por su órden, click en el botón de abajo para pagar con TodoPago </p>';
            echo $this -> generate_form($order, $URL_Request);
          }else{
            $this -> _printErrorMsg();
          }

        }//End $order->payment_method == 'todopago'  

      }//End isset($_GET["second_step"])

    }

    //Se ejecuta luego de pagar con el formulario
    function second_step_todopago(){
      
      if(isset($_GET['order'])){

        $order_id = intval($_GET['order']);
        $order = new WC_Order($order_id);
        //var_dump($order);

        if($order->payment_method == 'todopago'){

          $this -> _logTodoPago($order_id,'second step');

          global $wpdb;
          $row = $wpdb -> get_row(
            "SELECT meta_value FROM " . $wpdb -> postmeta . " WHERE meta_key = 'request_key' AND post_id = $order_id"
          );
          $esProductivo = $this -> ambiente == "prod"; 
          
          $optionsQuery = array (     
            'Security'   => $esProductivo ? $this -> security_prod : $this -> security_test,      
            'Merchant'   => strval($esProductivo ? $this -> merchant_id_prod : $this -> merchant_id_test),     
            'RequestKey' => $row -> meta_value,     
            'AnswerKey'  => $_GET['Answer']
          );
          $this -> _logTodoPago($order_id,'params GAA', json_encode($optionsQuery));

          $esProductivo = $this -> ambiente == "prod"; 
          $http_header = $this -> getHttpHeader($esProductivo);

          require_once(dirname(__FILE__).'/lib/Sdk.php');
          $connector = new Sdk($http_header, $this -> ambiente);

          $rta_GAA = $connector->getAuthorizeAnswer($optionsQuery);
          $this -> _logTodoPago($order_id,'response GAA',json_encode($rta_GAA));

          // Log todopago_transaccion
          $wpdb->update( 
            $wpdb->prefix.'todopago_transaccion',
            array(
              'params_GAA' => json_encode($optionsQuery), // string
              'response_GAA' => json_encode($rta_GAA), // string
              'answer_key' => $_GET['Answer'] //string
            ),
            array('id_orden' => $order_id), // int
            array(
              '%s',
              '%s',
              '%s'
            ),
            array('%d')
          );

          if ($rta_GAA['StatusCode']== -1){
            $this -> setOrderStatus($order,'estado_aprobacion');
            $this -> _logTodoPago($order_id, 'estado de orden', $order -> post_status);

            if($order -> post_status == "wc-completed"){
              //Reducir stock
              $order->reduce_order_stock();

              //Vaciar carrito
              global $woocommerce;
              $woocommerce->cart->empty_cart();
            }

            echo "<h2>Operación " . $order_id . " exitosa</h2>";
            echo "<script>
                    jQuery('.entry-title').html('Compra finalizada');
                  </script>";
          }else{
            $this -> setOrderStatus($order,'estado_rechazo');
            $this -> _printErrorMsg();
          }

        }//End $order->payment_method == 'todopago'

      }//End isset($_GET['order'])

    }

    function _printErrorMsg(){
      echo '<div class="woocommerce-error">Lo sentimos, ha ocurrido un error. <a href="' . home_url() . '" class="wc-backward">Volver a la página de inicio</a></div>';
    }

    /**
     * Add a log entry
     */
    function _logTodoPago($order_id, $action, $params = false) {
      $logMessage = "todopago - orden ".$order_id." : ".$action;
      $logMessage .= $params ? " - parametros: ".$params : '';
      if (!isset($this->log)) {
          $this->log = new WC_Logger();
      }
      $this->log->add('todopago_payment', $logMessage);
    }

    private function setOrderStatus($order, $statusName){
      global $wpdb;
      $row = $wpdb -> get_row(
        "SELECT option_value FROM " . $wpdb-> options . " WHERE option_name = 'woocommerce_todopago_settings'"
      );
      
      $arrayOptions = unserialize($row -> option_value);
      //var_dump($arrayOptions);

      $estado = substr($arrayOptions[$statusName], 3);
      $order -> update_status($estado, "Cambio a estado: " . $estado);
      //var_dump($order);
    }

    private function getWsdl($esProductivo){
      $wsdl = $esProductivo ? $this -> wsdls_prod : $this -> wsdls_test;
      return json_decode(html_entity_decode($wsdl,TRUE),TRUE);
    }

    private function getHttpHeader($esProductivo){
      $http_header = $esProductivo ? $this -> http_header_prod : $this -> http_header_test;
      return json_decode(html_entity_decode($http_header,TRUE));
    }

    private function getOptionsSARComercio($esProductivo, $returnUrl){
      return array (
        'Security'      => $esProductivo ? $this -> security_prod : $this -> security_test,
        'EncodingMethod'=> 'XML',
        'Merchant'      => strval($esProductivo ? $this -> merchant_id_prod : $this -> merchant_id_test),
        'URL_OK'        => $returnUrl,
        'URL_ERROR'     => $returnUrl
      ); 
    }

    private function getOptionsSAROperacion($esProductivo, $order){
      return array (
        'MERCHANT'    => strval($esProductivo ? $this -> merchant_id_prod : $this -> merchant_id_test),
        'OPERATIONID' => strval($order -> id),
        'CURRENCYCODE'=> '032', //Por el momento es el único tipo de moneda aceptada
        'AMOUNT'      => $order -> order_total,
        'EMAILCLIENTE'=> $order -> billing_email,
      ); 
    }

    private function cleanDescription($descripcion){
      $result = htmlspecialchars_decode($descripcion);

      $re = "/\\[(.*?)\\]|<(.*?)\\>/i"; 
      $subst = " ";
      $result = preg_replace($re, $subst, $result);

      $replace = array("!","'","\'","\"","  ","$","#","\\","\n","\r",'\n','\r','\t',"\t","\n\r",'\n\r','&nbsp;','&ntilde;',".,",",.");        
      $result = str_replace($replace, '', $result);

      $susts = array('Á','á','É','é','Í','í','Ó','ó','Ú','ú','Ü','ü','Ṅ','ñ');
      $cods = array('\u00c1','\u00e1','\u00c9','\u00e9','\u00cd','\u00ed','\u00d3','\u00f3','\u00da','\u00fa','\u00dc','\u00fc','\u00d1','\u00f1');
      $result = str_replace($cods, $susts, $result);
      return substr(trim($result),0,50);
    }

    private function getParamsCybersource($order){
      $cs['CSBTCITY'] = $order -> billing_city;     
      $cs['CSBTCOUNTRY'] = $order -> billing_country;        
      $cs['CSBTCUSTOMERID'] = $order -> customer_user;
      $cs['CSBTIPADDRESS'] = $order -> customer_ip_address;      
      $cs['CSBTEMAIL'] = $order -> billing_email;
      $cs['CSBTFIRSTNAME'] = $order -> billing_first_name;
      $cs['CSBTLASTNAME'] = $order -> billing_last_name;
      $cs['CSBTPHONENUMBER'] = $this->_phoneSanitize(strval($order -> billing_phone));
      $cs['CSBTPOSTALCODE'] = $order -> billing_postcode;
      $cs['CSBTSTATE'] = $this -> getStateCode($order -> billing_state); //Provincia de la dirección de facturación. MANDATORIO. Ver tabla anexa de provincias.      
      $cs['CSBTSTREET1'] = $order -> billing_address_1;
      //$cs['CSBTSTREET2'] = $order -> billing_address_2;
      $cs['CSPTCURRENCY'] = 'ARS'; //Moneda Fija        
      $cs['CSPTGRANDTOTALAMOUNT'] = number_format($order -> order_total,2,".","");
      //$cs['CSMDD6'] = $this -> canal_ingreso;   // Canal de venta. NO MANDATORIO. (Valores posibles: Web, Mobile, Telefonica)       
      $cs['CSMDD7'] = '';   // Fecha registro comprador(num Dias). NO MANDATORIO.     
      $cs['CSMDD8'] = 'S';  //Usuario Guest? (Y/N). En caso de ser Y, el campo CSMDD9 no deberá enviarse. NO MANDATORIO.        
      $cs['CSMDD9'] = '';   //Customer password Hash: criptograma asociado al password del comprador final. NO MANDATORIO.        
      $cs['CSMDD10'] = '';  //Histórica de compras del comprador (Num transacciones). NO MANDATORIO.        
      $cs['CSMDD11'] = '';  //Customer Cell Phone. NO MANDATORIO.
      $cs['CSMDD12'] = $this -> deadline; //Shipping DeadLine (Num Dias). NO MANDATORIO.

      //if($this -> tipo_segmento == 'retail'){
        $cs['CSSTCITY'] = $order->shipping_city;
        $cs['CSSTCOUNTRY'] = $order->shipping_country; 
        $cs['CSSTEMAIL'] = $order->billing_email;
        $cs['CSSTFIRSTNAME'] = $order->shipping_first_name;
        $cs['CSSTLASTNAME'] = $order->shipping_last_name;
        $cs['CSSTPHONENUMBER'] = $this->_phoneSanitize(strval($order->shipping_phone));
        $cs['CSSTPOSTALCODE'] = $order->shipping_postcode;
        $cs['CSSTSTATE'] = $this->getStateCode($order->shipping_state); 
        $cs['CSSTSTREET1'] = $order->billing_address_1;
      //}

      $csCSITPRODUCTCODE = array();
      $csCSITPRODUCTDESCRIPTION = array();
      $csCSITPRODUCTNAME = array();
      $csCSITPRODUCTSKU = array();
      $csCSITTOTALAMOUNT = array();
      $csCSITQUANTITY = array();
      $csCSITUNITPRICE = array();

      $replace = array("\n","\r",'\n','\r','&nbsp;');
      global $woocommerce;
      foreach ($woocommerce->cart->cart_contents as $cart_key => $cart_item_array) {
        $csCSITPRODUCTCODE[] = 'default';
        $descripcion = ($cart_item_array['data']->post->post_content == null) ? '' : $this -> cleanDescription($cart_item_array['data'] -> post -> post_content);
        $csCSITPRODUCTDESCRIPTION[] = $descripcion;         
        $csCSITPRODUCTNAME[] = str_replace('#', '', $cart_item_array['data']->post->post_title);
        $csCSITPRODUCTSKU[] = str_replace('#', '', $cart_item_array['product_id']);
        $csCSITTOTALAMOUNT[] = number_format($cart_item_array['line_total'],2,".","");
        $csCSITQUANTITY[] = $cart_item_array['quantity'];
        $csCSITUNITPRICE[] = number_format($cart_item_array['data']->price,2,".","");
      }

      $cs['CSITPRODUCTCODE'] = join($csCSITPRODUCTCODE, "#");
      $cs['CSITPRODUCTDESCRIPTION'] = join($csCSITPRODUCTDESCRIPTION, "#");
      $cs['CSITPRODUCTNAME'] = join($csCSITPRODUCTNAME, "#");
      $cs['CSITPRODUCTSKU'] = join($csCSITPRODUCTSKU, "#");
      $cs['CSITTOTALAMOUNT'] = join($csCSITTOTALAMOUNT, "#");
      $cs['CSITQUANTITY'] = join($csCSITQUANTITY, "#");
      $cs['CSITUNITPRICE'] = join($csCSITUNITPRICE, "#");

      return $cs;     
    }

    private function _phoneSanitize($number){
      $number = str_replace(array(" ","(",")","-","+"),"",$number);
      
      if(substr($number,0,2)=="54") return $number;
      
      if(substr($number,0,2)=="15"){
       $number = substr($number,2,strlen($number));
      }
      if(strlen($number)==8) return "5411".$number;
      
      if(substr($number,0,1)=="0") return "54".substr($number,1,strlen($number));
      return $number;
    }

    private function getStateCode($stateName){
      $array = array(
        "caba" => "C",
        "capital" => "C",
        "ciudad autonoma de buenos aires" => "C",
        "buenos aires" => "B",
        "bs as" => "B",
        "catamarca" => "K",
        "chaco" => "H",
        "chubut" => "U",
        "cordoba" => "X",
        "corrientes" => "W",
        "entre rios" => "R",
        "formosa" => "P",
        "jujuy" => "Y",
        "la pampa" => "L",
        "la rioja" => "F",
        "mendoza" => "M",
        "misiones" => "N",
        "neuquen" => "Q",
        "rio negro" => "R",
        "salta" => "A",
        "san juan" => "J",
        "san luis" => "D",
        "santa cruz" => "Z",
        "santa fe" => "S",
        "santiago del estero" => "G",
        "tierra del fuego" => "V",
        "tucuman" => "T"
      );

      $name = strtolower($stateName);
      
      $no_permitidas = array("á","é","í","ó","ú");
      $permitidas = array("a","e","i","o","u");
      $name = str_replace($no_permitidas, $permitidas ,$name);

      return isset($array[$name]) ? $array[$name] : 'C';
    }

    private function generate_form($order, $URL_Request){
      return '<form action="' . $URL_Request . '" method="post" id="todopago_payment_form">' . 
             '<input type="submit" class="button-alt" id="submit_todopago_payment_form" value="' . 'Pagar con TodoPago' . '" /> 
              <a class="button cancel" href="' . $order -> get_cancel_order_url() . '">' . ' Cancelar orden ' . '</a>
              </form>';
    }

    function process_payment($order_id){
      global $woocommerce;
    	$order = new WC_Order( $order_id );
   
      return array(
        'result' => 'success', 
        'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
      );
    }
 
  }//End WC_TodoPago_Gateway

  //Agrego el campo teléfono de envío para cybersource
  function custom_override_checkout_fields($fields) {
    $fields['shipping']['shipping_phone'] = array(
      'label'     => 'Teléfono',
      'required'  => true,
      'class'     => array('form-row-wide'),
      'clear'     => true
    );

    return $fields;
  }

  add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );

  //Añado el medio de pago TodoPago a WooCommerce
  function woocommerce_add_todopago_gateway($methods) {
      $methods[] = 'WC_TodoPago_Gateway';
      return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_todopago_gateway' );

}//End woocommerce_todopago_init


//Actualización de versión

global $todopago_db_version;
$todopago_db_version = '1.0';

function todopago_install(){
  global $wpdb;

  $table_name = $wpdb->prefix . "todopago_transaccion";
  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE IF NOT  EXISTS $table_name (
    id INT NOT NULL AUTO_INCREMENT,
    id_orden INT NULL,
    first_step TIMESTAMP NULL,
    params_SAR TEXT NULL,
    response_SAR TEXT NULL,
    second_step TIMESTAMP NULL,
    params_GAA TEXT NULL,
    response_GAA TEXT NULL,
    request_key TEXT NULL,
    public_request_key TEXT NULL,
    answer_key TEXT NULL,
    PRIMARY KEY (id)
  ) $charset_collate;";

  require_once(ABSPATH.'wp-admin/includes/upgrade.php');
  dbDelta($sql);

  global $todopago_db_version;
  add_option('todopago_db_version', $todopago_db_version);

}


function todopago_update_db_check() {
  global $todopago_db_version;
  $installed_ver = get_option('todopago_db_version');

  if ($installed_ver == null || $installed_ver != $todopago_db_version) {
    todopago_install();
    update_option('todopago_db_version', $todopago_db_version);
  }

}

add_action('plugins_loaded', 'todopago_update_db_check');