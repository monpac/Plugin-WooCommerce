/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

jQuery(function ($) {
    
        $("#woocommerce_todopago_btnCredentials").val("Obtener Credenciales");  
	$("#woocommerce_todopago_btnCredentials").click(function() {
            
            var user = $("#woocommerce_todopago_user").val();
            var password = $("#woocommerce_todopago_password").val();
            var mode = $("#woocommerce_todopago_ambiente").val();
              
            $.ajax({type: 'POST',
                     url: "../wp-content/plugins/woocommerce-plugin/view/credentials.php",
                     data: { 'user' :  user,
                             'password' :  password,
                             'mode' :  mode
                           },
                     success: function(data) {  
                         setCredentials(data);  
                     },
                     error: function(data) {  
                         alert(data);  
                     },
             });                    
        }); 
        
        function setCredentials (data){
            
           var ambiente = $("#woocommerce_todopago_ambiente").val();
           var response = $.parseJSON(data);
           
           if(response.codigoResultado === undefined){ 
               alert(response.mensajeResultado);     
           }else{
               
             if(ambiente == 'prod'){         
                $("#woocommerce_todopago_http_header_prod").val(response.apikey);
                $("#woocommerce_todopago_security_prod").val(response.security);
                $("#woocommerce_todopago_merchant_id_prod").val(response.merchandid);
                $("#woocommerce_todopago_http_header_test").val("");
                $("#woocommerce_todopago_security_test").val("");
                $("#woocommerce_todopago_merchant_id_test").val("");
            } else{ 
                $("#woocommerce_todopago_http_header_test").val(response.apikey);
                $("#woocommerce_todopago_security_test").val(response.security);
                $("#woocommerce_todopago_merchant_id_test").val(response.merchandid);
                $("#woocommerce_todopago_http_header_prod").val("");
                $("#woocommerce_todopago_security_prod").val("");
                $("#woocommerce_todopago_merchant_id_prod").val("");
            }
                
           }
        } 
        
        
        
});
