<?php
/*
    Plugin Name: TodoPago para WooCommerce
    Description: TodoPago para Woocommerce.
    Version: 1.3.4
    Author: Todo Pago
*/

define('TODOPAGO_PLUGIN_VERSION','1.3.4');
define('TP_FORM_EXTERNO', 'ext');
define('TP_FORM_HIBRIDO', 'hib');
define('TODOPAGO_DEVOLUCION_OK', 2011);
define('TODOPAGO_FORMS_PROD','https://forms.todopago.com.ar');

use TodoPago\Sdk as Sdk;

require_once(dirname(__FILE__).'/lib/logger.php');
require_once(dirname(__FILE__).'/lib/Sdk.php');
require_once(dirname(__FILE__).'/lib/ControlFraude/ControlFraudeFactory.php');

//Llama a la función woocommerce_todopago_init cuando se cargan los plugins. 0 es la prioridad.
add_action('plugins_loaded', 'woocommerce_todopago_init', 0);

function woocommerce_todopago_init(){
    if(!class_exists('WC_Payment_Gateway')) return;

    class WC_TodoPago_Gateway extends WC_Payment_Gateway{

        public $tplogger;

        public function __construct(){
            $this -> id             = 'todopago';
            $this -> icon           = apply_filters('woocommerce_todopago_icon', "http://www.todopago.com.ar/sites/todopago.com.ar/files/pluginstarjeta.jpg");
            $this -> medthod_title  = 'Todo Pago';
            $this -> has_fields     = false;
            $this -> supports = array(
                'products',
                'refunds'
            );

            $this -> init_form_fields();
            $this -> init_settings(); //Carga en el array settings los valores de los campos persistidos de la base de datos

            //Datos generales
            $this -> version          = $this -> settings['version'];
            $this -> title            = $this -> settings['title'];
            $this -> description      = $this -> settings['description'];
            $this -> ambiente         = $this -> settings['ambiente'];
            $this -> tipo_segmento    = $this -> settings['tipo_segmento'];
            //$this -> canal_ingreso    = $this -> settings['canal_ingreso'];
            $this -> deadline         = $this -> settings['deadline'];
            $this->tipo_formulario    = $this -> settings['tipo_formulario'];

            //Datos credentials
            $this -> credentials      = $this -> settings['credentials'];
            $this -> user             = $this -> settings['user'];
            $this -> password         = $this -> settings['password'];
            $this -> btnCredentials   = $this -> settings['btnCredentials'];

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
            add_action('after_woocommerce_pay', array($this, 'first_step_todopago'));

            //Llamado al second step
            add_action('woocommerce_thankyou', array($this, 'second_step_todopago'));

            $this->tplogger = new TodoPagoLogger();

        }//End __construct

        function init_form_fields(){
            
            global $woocommerce;
            require_once $woocommerce -> plugin_path() . '/includes/wc-order-functions.php';

            $this -> form_fields = array(
                'version' => array(
                    'title' => 'Version del plugin '.TODOPAGO_PLUGIN_VERSION,
                    'type'=> 'title'),  
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
                    'default' => 'Pagá de manera segura mediante TodoPago<br>Sólo para la República Argentina'),
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
                    'description' => 'Días maximos para la entrega'),
                
                 'tipo_formulario' => array(
                    'title' => 'Elija el fromulario que desea utilizar',
                    'type' => 'select',
                    'description' => 'Puede escojer entre un formulario integrado al comercio o redireccionar al formulario externo',
                    'options' => array(
                        TP_FORM_EXTERNO => 'Externo',
                        TP_FORM_HIBRIDO => 'Integrado'
                    )
                ),
                
                'credentials' => array(
                    'title' => 'Credenciales',
                    'type'=> 'title'),
                
                'user' => array(
                    'title' => 'User',
                    'type'=> 'text',
                    'description' => 'User Todo Pago'),
                
                'password' => array(
                    'title' => 'Password',
                    'type'=> 'text',
                    'description' => 'Password Todo Pago'),
          
                'btnCredentials' => array(
                    'type'=> 'button',
                    'value' => 'Obtener Credenciales',
                    'class' => 'button-primary'),
                
                'titulo_testing' => array( 
                    'title' => 'Ambiente de Developers', 
                    'type' => 'title', 
                    'description' => 'Datos correspondientes al ambiente de developers', 
                    'id' => 'testing_options' ),

                'http_header_test' => array(
                    'title' => 'HTTP Header',
                    'type' => 'text',
                    'description' => "Authorization para el hearder. Ejemplo: <b>PRISMA 912EC803B2CE49E4A541068D12345678</b>"),
                'security_test' => array(
                    'title' => 'Security',
                    'type' => 'text',
                    'description' => 'Código provisto por Todo Pago'),
                'merchant_id_test' => array(
                    'title' => 'Merchant ID',
                    'type' => 'text',
                    'description' => 'N&uacute;mero de comercio provisto por Todo Pago'),

                'titulo_produccion' => array( 'title' => 'Ambiente de Producción', 'type' => 'title', 'description' => 'Datos correspondientes al ambiente de producción', 'id' => 'produccion_options' ),

                'http_header_prod' => array(
                    'title' => 'HTTP Header',
                    'type' => 'text',
                    'description' => 'Authorization para el hearder. Ejemplo: <b>PRISMA 912EC803B2CE49E4A541068D12345678</b>'),
                'security_prod' => array(
                    'title' => 'Security',
                    'type' => 'text',
                    'description' => 'Código provisto por Todo Pago'),
                'merchant_id_prod' => array(
                    'title' => 'Merchant ID',
                    'type' => 'text',
                    'description' => 'N&uacute;mero de comercio provisto por Todo Pago'),

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
           
            $urlCredentials = plugins_url('js/credentials.js', __FILE__);            
            echo '<script type="text/javascript" src="' . $urlCredentials . '"></script>';

            include_once dirname(__FILE__)."/view/status.php";
        }

        //Se ejecuta luego de Finalizar compra -> Realizar el pago
        function first_step_todopago($order_id){
            global $wpdb;
            if(isset($_GET["second_step"])){
                //Second Step
                $this -> second_step_todopago();
            }else{
                global $woocommerce;
                $order_id = $woocommerce->session->__get('order_awaiting_payment');
                $order = new WC_Order($order_id);
                //var_dump($order);
                //var_dump($order->get_user());
                if($order->payment_method == 'todopago'){
                    global $woocommerce;
                    $logger = $this->_obtain_logger(phpversion(), $woocommerce->version, TODOPAGO_PLUGIN_VERSION, $this->ambiente, $order->customer_user, $order_id, true);
                    $this->prepare_order($order, $logger);
                    $paramsSAR = $this->get_paydata($order, $logger);
                    $response_sar = $this->call_sar($paramsSAR, $logger);
                    $this->custom_commerce($wpdb, $order, $paramsSAR, $response_sar);
                }
            }

        }

        //Persiste el RequestKey en la DB
        private function _persistRequestKey($order_id, $request_key){
            update_post_meta( $order_id, 'request_key', $request_key);
        }

        private function _obtain_logger($php_version, $woocommerce_version, $todopago_plugin_version, $endpoint, $customer_id, $order_id, $is_payment){
            $this->tplogger->setPhpVersion($php_version);
            global $woocommerce;
            $this->tplogger->setCommerceVersion($woocommerce_version);
            $this->tplogger->setPluginVersion($todopago_plugin_version);
            $this->tplogger->setEndPoint($endpoint);
            $this->tplogger->setCustomer($customer_id);
            $this->tplogger->setOrder($order_id);

            return  $this->tplogger->getLogger(true);
        }

        function prepare_order($order, $logger){
            $logger->info('first step');
            $this->setOrderStatus($order,'estado_inicio');
        }

        function get_paydata($order, $logger){
            $controlFraude = ControlFraudeFactory::get_ControlFraude_extractor('Retail', $order, $order->get_user());
            $datosCs = $controlFraude->getDataCF();

            $returnURL = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'."{$_SERVER['HTTP_HOST']}/{$_SERVER['REQUEST_URI']}".'&second_step=true';

            $esProductivo = $this->ambiente == "prod";
            $optionsSAR_comercio = $this->getOptionsSARComercio($esProductivo, $returnURL);

            $optionsSAR_operacion = $this->getOptionsSAROperacion($esProductivo, $order);
            $optionsSAR_operacion = array_merge_recursive($optionsSAR_operacion, $datosCs);

            $paramsSAR['comercio'] = $optionsSAR_comercio;
            $paramsSAR['operacion'] = $optionsSAR_operacion;

            $logger->info('params SAR '.json_encode($paramsSAR));

            return $paramsSAR;
        }

        function call_sar($paramsSAR, $logger){
            $logger->debug(call_sar);
            $esProductivo = $this->ambiente == "prod";
            $http_header = $this->getHttpHeader();
            $logger->debug("http header: ".json_encode($http_header));
            $connector = new Sdk($http_header, $this->ambiente);
            $logger->debug("Connector: ".json_encode($connector));
            $response_sar = $connector->sendAuthorizeRequest($paramsSAR['comercio'], $paramsSAR['operacion']);
            $logger->info('response SAR '.json_encode($response_sar));

            if($response_sar["StatusCode"] == 702 && !empty($http_header) && !empty($paramsSAR['comercio']['Merchant']) && !empty($paramsSAR['comercio']['Security'])){
                $response_sar = $connector->sendAuthorizeRequest($paramsSAR['comercio'], $paramsSAR['operacion']);
                $logger->info('reintento');
                $logger->info('response SAR '.json_encode($response_sar));
            }

            return $response_sar;
        }

        function custom_commerce($wpdb, $order, $paramsSAR, $response_sar){
            $this->_persistRequestKey($order->id, $response_sar["RequestKey"]);

            $wpdb->insert(
                $wpdb->prefix.'todopago_transaccion', 
                array('id_orden'=>$order->id,
                      'params_SAR'=>json_encode($paramsSAR),
                      'first_step'=>date("Y-m-d H:i:s"),
                      'response_SAR'=>json_encode($response_sar),
                      'request_key'=>$response_sar["RequestKey"],
                      'public_request_key'=>$response_sar['PublicRequestKey']
                     ),
                array('%d','%s','%s','%s','%s')
            );
debugger;
            if($response_sar["StatusCode"] == -1){
                if ($this->tipo_formulario == TP_FORM_EXTERNO) {
                    echo '<p> Gracias por su órden, click en el botón de abajo para pagar con TodoPago </p>';
                    echo $this->generate_form($order, $response_sar["URL_Request"]);
                }
                else {
                    $basename = plugin_basename(dirname(__FILE__));
                    $baseurl = plugins_url();
                    $form_dir = "$baseurl/$basename/view/formulario-hibrido";
                    $firstname = $paramsSAR['operacion']['CSSTFIRSTNAME'];
                    $lastname = $paramsSAR['operacion']['CSSTLASTNAME'];
                    $email = $paramsSAR['operacion']['CSSTEMAIL'];
                    $merchant = $paramsSAR['operacion']['MERCHANT'];
                    $amount = $paramsSAR['operacion']['CSPTGRANDTOTALAMOUNT'];
                    $prk = $response_sar['PublicRequestKey'];
                $returnURL = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'."{$_SERVER['HTTP_HOST']}/{$_SERVER['REQUEST_URI']}".'&second_step=true';
                    $env_url = ($this->ambiente == "prod" ? TODOPAGO_FORMS_PROD : TODOPAGO_ENDPOINT_TEST)."../../..";

                    require 'view/formulario-hibrido/formulario.php';
                }
            }else{
                $this->_printErrorMsg();
            }
        }

        //Se ejecuta luego de pagar con el formulario
        function second_step_todopago(){

            if(isset($_GET['order'])){
                $order_id = intval($_GET['order']);
                $order = new WC_Order($order_id);

                if($order->payment_method == 'todopago'){
                    global $woocommerce;
                    $logger = $this->_obtain_logger(phpversion(), $woocommerce->version, TODOPAGO_PLUGIN_VERSION, $this->ambiente, $order->customer_user, $order_id, true);
                    $data_GAA = $this->call_GAA($order_id, $logger);
                    $this->take_action($order, $data_GAA, $logger);
                }
            }

        }

        function call_GAA($order_id, $logger){
            $logger->info('second step _ ORDER ID: '.$order_id);
            $row = get_post_meta($order_id, 'request_key', true);
            $esProductivo = $this->ambiente == "prod"; 

            $params_GAA = array (     
                'Security'   => $esProductivo ? $this -> security_prod : $this -> security_test,      
                'Merchant'   => strval($esProductivo ? $this -> merchant_id_prod : $this -> merchant_id_test),     
                'RequestKey' => $row,     
                'AnswerKey'  => $_GET['Answer']
            );
            $logger->info('params GAA '.json_encode($params_GAA));

            $esProductivo = $this->ambiente == "prod"; 
            $http_header = $this->getHttpHeader();
            $logger->info("HTTP_HEADER: ".json_encode($http_header));
            $connector = new Sdk($http_header, $this -> ambiente);
            
            $logger->info("PARAMETROS GAA: ".json_encode($params_GAA));
            $response_GAA = $connector->getAuthorizeAnswer($params_GAA);
            $logger->info('response GAA '.json_encode($response_GAA));

            $data_GAA['params_GAA'] = $params_GAA;
            $data_GAA['response_GAA'] = $response_GAA;

            return $data_GAA;    
        }

        function take_action($order, $data_GAA, $logger){
            global $wpdb;

            $wpdb->update( 
                $wpdb->prefix.'todopago_transaccion',
                array(
                    'second_step'=>date("Y-m-d H:i:s"), // string
                    'params_GAA'=>json_encode($data_GAA['params_GAA']), // string
                    'response_GAA'=>json_encode($data_GAA['response_GAA']), // string
                    'answer_key'=>$_GET['Answer'] //string
                ),
                array('id_orden'=>$order->id), // int
                array(
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                ),
                array('%d')
            );

            if ($data_GAA['response_GAA']['StatusCode']== -1){
                $this -> setOrderStatus($order,'estado_aprobacion');
                $logger->info('estado de orden '.$order->post_status);

                if($order -> post_status == "wc-completed"){
                    //Reducir stock
                    $order->reduce_order_stock();
                    //Vaciar carrito
                    global $woocommerce;
                    $woocommerce->cart->empty_cart();
                }

                echo "<h2>Operación " . $order->id . " exitosa</h2>";
                echo "<script>
                jQuery('.entry-title').html('Compra finalizada');
              </script>";
            }else{
                $this -> setOrderStatus($order,'estado_rechazo');
                $this -> _printErrorMsg();
            }

        }

        function _printErrorMsg(){
            echo '<div class="woocommerce-error">Lo sentimos, ha ocurrido un error. <a href="' . home_url() . '" class="wc-backward">Volver a la página de inicio</a></div>';
        }

        private function setOrderStatus($order, $statusName){
            global $wpdb;
            $row = $wpdb -> get_row(
                "SELECT option_value FROM " . $wpdb-> options . " WHERE option_name = 'woocommerce_todopago_settings'"
            );

            $arrayOptions = unserialize($row -> option_value);
            //var_dump($a rrayOptions);

            $estado = substr($arrayOptions[$statusName], 3);
            $order -> update_status($estado, "Cambio a estado: " . $estado);
            //var_dump($order);
        }

        private function getWsdl($esProductivo){
            $wsdl = $esProductivo ? $this -> wsdls_prod : $this -> wsdls_test;
            return json_decode(html_entity_decode($wsdl,TRUE),TRUE);
        }

        private function getHttpHeader(){
            $esProductivo = $this->ambiente == "prod";
            $http_header = $esProductivo ? $this -> http_header_prod : $this -> http_header_test;
            $header_decoded = json_decode(html_entity_decode($http_header,TRUE));
            return (!empty($header_decoded)) ? $header_decoded : array("authorization" => $http_header);
        }

        private function getSecurity() {
            return $this->ambiente == "prod"? $this->security_prod : $this->security_test;
        }

        private function getMerchant() {
            return $this->ambiente == "prod"? $this->merchant_id_prod : $this->merchant_id_test;
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
            ); 
        }

        private function generate_form($order, $URL_Request){
            return '<form action="' . $URL_Request . '" method="post" id="todopago_payment_form">' . 
                '<input type="submit" class="button-alt" id="submit_todopago_payment_form" value="' . 'Pagar con TodoPago' . '" /> 
              <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . ' Cancelar orden ' . '</a>
              </form>';
        }

        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            global $woocommerce;
            //IMPORTANTE EXCEPTIONS: WooCommerce las capturará y las mostrará en un alert, esta es la herramienta que se dipone para comunicarse con el usuario, he probado con echo y no lo he logrado.

            $logger = $this->_obtain_logger(phpversion(), $woocommerce->version, TODOPAGO_PLUGIN_VERSION, $this->ambiente, $this->getMerchant(), $order_id, true);
            //configuración común a ambos servicios.
            $options_return = array(
                    "Security" => $this->getSecurity(),
                    "Merchant" => $this->getMerchant(),
                    "RequestKey" => get_post_meta($order_id, 'request_key', true)
            );

            //Intento instanciar la Sdk, si la configuración está mal, le avisará al usuario.
            try {
                $connector = new Sdk($this->getHttpHeader(), $this->ambiente);
            }
            catch (Exception $e) {
                $logger->warn("Error al crear el connector, ", $e);
                throw new Exception("Revise la configuarción de TodoPago");
            }

            //throw new exception("Conector creado");

            if(empty($amount)) { //Si el amount vieniera vacío hace la devolución total
                $logger->info("Pedido de devolución total pesos de la orden $order_id");
                $logger->debug("Params devolución: ".json_encode($options_return));

                //Intento realizar la devolución
                try {
                    $return_response = $connector->voidRequest($options_return);
                }
                catch (Exception $e) {
                    $logger->error("Falló al consultar el servicio: ", $e);
                    throw new Exception("Falló al consultar el servicio");
                }
            }
            else {
                $logger->info("Pedido de devolución por $amount pesos de la orden $order_id");
                $options_return['AMOUNT'] = $amount;
                $logger->debug("Params devolución: ".json_encode($options_return));

                //Intento realizar la devolución
                try {
                    $return_response = $connector->returnRequest($options_return);
                }
                catch (Exception $e) {
                    $logger->error("Falló al consultar el servicio: ", $e);
                    throw new Exception("Falló al consultar el servicio");
                }
            }
            $logger->debug("return Response: ".json_encode($return_response));

            //Si el servicio no responde según lo esperado, se interrumpe la devolución
            if (!is_array($return_response) || !array_key_exists('StatusCode', $return_response) || !array_key_exists('StatusMessage', $return_response)) {
                throw new Exception("El servicio no responde correctamente");
            }
            if ($return_response['StatusCode'] == TODOPAGO_DEVOLUCION_OK) {
                //retorno true para que Woo tome la devolución
                return true;
            }
            else {
                throw new Exception($return_response["StatusMessage"]);
                //return false;
            }
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
    function todopago_custom_override_checkout_fields($fields) {
        $fields['shipping']['shipping_phone'] = array(
            'label'     => 'Teléfono',
            'required'  => true,
            'class'     => array('form-row-wide'),
            'clear'     => true
        );

        return $fields;
    }

    add_filter( 'woocommerce_checkout_fields' , 'todopago_custom_override_checkout_fields' );

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
    first_step TEXT NULL,
    params_SAR TEXT NULL,
    response_SAR TEXT NULL,
    second_step TEXT NULL,
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

function my_init() {
    
        // comment out the next two lines to load the local copy of jQuery
        wp_deregister_script('jquery'); 
        wp_register_script('jquery', 'http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js', false, '1.3.2'); 
        wp_enqueue_script('jquery');
}

add_action('init', 'my_init');

