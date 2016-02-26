<a name="inicio"></a>
woocommerce-plugin
============

Plug in para la integración con gateway de pago <strong>Todo Pago</strong>
- [Consideraciones Generales](#consideracionesgenerales)
- [Instalación](#instalacion)
- [Configuración plugin](#confplugin)
 - [Activación](#activacion)
 - [Configuración](#configuracion)
- [Datos adiccionales para prevención de fraude](#cybersource) 
- [Consulta de transacciones](#constrans)
- [Devoluciones](#devoluciones)
- [Tablas de referencia](#tablas)

<a name="consideracionesgenerales"></a>
## Consideraciones Generales
El plug in de pagos de <strong>Todo Pago</strong>, provee a las tiendas WooCommerce de un nuevo m&eacute;todo de pago, integrando la tienda al gateway de pago.
La versión de este plug in esta testeada en PHP 5.4-5.3 y WordPress 3.7.5 con WooCommerce 2.3.5.

<a name="instalacion"></a>
## Instalación
1. Descomprimir el archivo woocommerce-plugin-master.zip. 
2. Copiar carpeta woocommerce-plugin-master al directorio de plugins de wordpress ("raíz de wordpress"/wp-content/plugins). 
3. Renombrarla woocommerce-plugin-master por woocommerce-plugin.

Observaci&oacute;n:
Descomentar: <em>extension=php_soap.dll</em> del php.ini, ya que para la conexión al gateway se utiliza la clase <em>SoapClient</em> del API de PHP.
Descomentar: <em>extension=php_openssl.dll</em> del php.ini 
<br />
[<sub>Volver a inicio</sub>](#inicio)

<a name="confplugin"></a>
##Configuración plugin

<a name="activacion"></a>
####Activación
La activación se realiza como cualquier plugin de Wordpress: Desde Plugins -> Plugins instalados -> activar el plugin de nombre <strong>TodoPago para WooCommerce</strong>.<br />

<a name="configuracion"></a>
####Configuración
Para llegar al menu de configuración del plugin ir a: <em>WooCommerce -> Ajustes</em> y seleccionar Finalizar Compra de la solapa de configuraciones que aparece en la parte superior. Entre los medios de pago aparecerá la opción de nombre <strong>Todopago</strong>.<br /> 

![imagen de configuracion](https://raw.githubusercontent.com/TodoPago/imagenes/master/woocommerce/1-%20header%20gateway.png)

El Plug-in nos mostrará las siguientes secciones:<br />
- [Configuración General](#configuraciongeneral)
- [Ambiente de Developers](#ambientetesting)
- [Ambiente de Producción](#ambienteproduccion)
- [Estados del Pedido](#estadospedido)
- [Status de las Operaciones](#statusoperaciones)

<a name="configuraciongeneral"></a>
<sub><em>Configuración General</em></sub><br />
![imagen de configuracion](https://raw.githubusercontent.com/TodoPago/imagenes/master/woocommerce/2-%20configuracion%20general.png)
- La opción Habilitar/Deshabilitar permite la activación o no del medio de pago Todo Pago en el comercio.
- La opción ambiente define si se toman los datos de Ambiente de Developers o de Ambiente de Producción.

<a name="ambientetesting"></a>
<sub><em>Ambiente de Developers</em></sub><br />
![imagen de configuracion](https://raw.githubusercontent.com/TodoPago/imagenes/master/woocommerce/3-%20configuracion%20developers.PNG)

<a name="ambienteproduccion"></a>
<sub><em>Ambiente de Producción</em></sub><br />
![imagen de configuracion](https://raw.githubusercontent.com/TodoPago/imagenes/master/woocommerce/4-%20configuracion%20produccion.png)

<a name="estadospedido"></a>
<sub><em>Estados del Pedido</em></sub><br />
![imagen de configuracion](https://raw.githubusercontent.com/TodoPago/imagenes/master/woocommerce/5-%20configuracion%20estados.png)
- Estado de transacción iniciada: Se setea luego de completar los datos de facturación y presionar el botón "Realizar el pedido".
- Estado de transacción aprobada: Se setea luego de volver del formulario de pago de Todo Pago y se obtiene una confirmación del pago.
- Estado de transacción rechazada: Se setea luego de volver del formulario de pago de Todo Pago y se obtiene un rechazo del pago.

<a name="statusoperaciones"></a>
<sub><em>Status de las Operaciones</em></sub><br />
![imagen de configuracion](https://raw.githubusercontent.com/TodoPago/imagenes/master/woocommerce/6-%20status%20de%20las%20operaciones.png)
<br />

[<sub>Volver a inicio</sub>](#inicio)
<a name="tca"></a>

<a name="cybersource"></a>
## Prevención de Fraude
- [Consideraciones Generales](#cons_generales)

<a name="cons_generales"></a>
####Consideraciones Generales (para todas las verticales, por defecto RETAIL)
El plugin, toma valores est&aacute;ndar del framework para validar los datos del comprador. Principalmente se utiliza una instancia de la clase <em>WC_Order</em>.

```php
   $order = new WC_Order($order_id);
-- Ciudad de Facturación: $order -> billing_city;
-- País de facturación: $order -> billing_country;
-- Identificador de Usuario: $order -> customer_user;
-- Email del usuario al que se le emite la factura: $order -> billing_email;
-- Nombre de usuario el que se le emite la factura: $order -> billing_first_name;
-- Apellido del usuario al que se le emite la factura: $order -> billing_last_name;
-- Teléfono del usuario al que se le emite la factura: $order -> billing_phone;
-- Provincia de la dirección de facturación: $this -> getStateCode($order -> billing_state);
-- Domicilio de facturación: $order -> billing_address_1;
-- Complemento del domicilio. (piso, departamento): $order -> billing_address_2;
-- Moneda: 'ARS'; //Moneda Fija
-- Total:  $order -> order_total;
-- IP de la pc del comprador: $order -> customer_ip_address;
```
<a name="cons_retail"></a> 
####Consideraciones para vertical RETAIL
Las consideración para el caso de empresas del rubro <strong>RETAIL</strong> son similares a las <em>consideraciones generales</em> ya que se obtienen del mismo objeto de clase WC_Orden
```php
-- Ciudad de envío de la orden: $order -> shipping_city;
-- País de envío de la orden: $order -> shipping_country;
-- Mail del destinatario: $order -> shipping_email;
-- Nombre del destinatario: $order -> shipping_first_name;
-- Apellido del destinatario: $order -> shipping_last_name;
-- Número de teléfono del destinatario: $order -> shipping_phone;
-- Código postal del domicio de envío: $order -> shipping_postcode;
-- Provincia de envío: getStateCode($order -> shipping_state);
-- Domicilio de envío: $order -> billing_address_1;
```

<a name="constrans"></a>
## Consulta de Transacciones
Se puede consultar <strong>on line</strong> las características de la transacci&oacute;n en el sistema de Todo Pago al hacer click en el número de orden en la parte de Status de las Operaciones.<br />
![imagen de configuracion](https://raw.githubusercontent.com/TodoPago/imagenes/master/woocommerce/6-%20status%20de%20las%20operaciones.png)
[<sub>Volver a inicio</sub>](#inicio)

<a name="devoluciones"></a>
## Devoluciones
Es posible realizar devoluciones o reembolsos mediante el procedimiento habitual de WooCommerce. Para ello dirigirse en el menú a WooCommerce->Pedidos, "Ver" la orden deseada (Esta debe haber sido realizada con TodoPago) y encontrará una sección con el título **Pedido Productos**, dentro de esta hay un botón *Reembolso* al hacer click ahí nos solicitará el monto a reembolsar y nos dará la opción de *Reembolsar con TodoPago*.<br />
![Devolución](https://raw.githubusercontent.com/TodoPago/imagenes/master/woocommerce/8-%20devoluciones.PNG)<br />
[<sub>Volver a inicio</sub>](#inicio)

<a name="tablas"></a>
## Tablas de Referencia
######[Provincias](#p)

<a name="p"></a>
<p>Provincias</p>
<table>
<tr><th>Provincia</th><th>Código</th></tr>
<tr><td>CABA</td><td>C</td></tr>
<tr><td>Buenos Aires</td><td>B</td></tr>
<tr><td>Catamarca</td><td>K</td></tr>
<tr><td>Chaco</td><td>H</td></tr>
<tr><td>Chubut</td><td>U</td></tr>
<tr><td>Córdoba</td><td>X</td></tr>
<tr><td>Corrientes</td><td>W</td></tr>
<tr><td>Entre Ríos</td><td>R</td></tr>
<tr><td>Formosa</td><td>P</td></tr>
<tr><td>Jujuy</td><td>Y</td></tr>
<tr><td>La Pampa</td><td>L</td></tr>
<tr><td>La Rioja</td><td>F</td></tr>
<tr><td>Mendoza</td><td>M</td></tr>
<tr><td>Misiones</td><td>N</td></tr>
<tr><td>Neuquén</td><td>Q</td></tr>
<tr><td>Río Negro</td><td>R</td></tr>
<tr><td>Salta</td><td>A</td></tr>
<tr><td>San Juan</td><td>J</td></tr>
<tr><td>San Luis</td><td>D</td></tr>
<tr><td>Santa Cruz</td><td>Z</td></tr>
<tr><td>Santa Fe</td><td>S</td></tr>
<tr><td>Santiago del Estero</td><td>G</td></tr>
<tr><td>Tierra del Fuego</td><td>V</td></tr>
<tr><td>Tucumán</td><td>T</td></tr>
</table>
[<sub>Volver a inicio</sub>](#inicio)
