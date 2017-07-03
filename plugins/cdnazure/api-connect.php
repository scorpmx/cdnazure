<?php
/**
* Plugin Name: CDN Azure Connection
* Plugin URI: 
* Description: Plugin para poder conectarse al Azure de Microsoft
* Version: 1.0.0
* Author: Diego Damian
* License: GPL2
*/

require_once ABSPATH.'/vendor/autoload.php';
use WindowsAzure\Common\ServicesBuilder;
use MicrosoftAzure\Storage\Common\ServiceException;

//Agregamos el menu de CND Azure al Administrador de WP

add_action('admin_menu', 'menu_azure');

function menu_azure() {

	add_menu_page( 
	         __( 'Configuracion CDN Azure', 'cndazure-wp' ),
	         __( 'CDN Azure', 'cdnazure-wp' ), 
	         'administrator', 
	         'cdnazurewp',
	         'cdnazure_dashboard'
	);

}

//Funcion para imprimir el formulario cuando dan clic en el menu CND Azure
//Aqui mismo se guarda la informacion de la cuenta de CND Azure

function cdnazure_dashboard() {

	if($_POST['cdnAction'] == 'saveData') {

		$data = array(
			'accountName' => $_POST['accountName'],
			'accountKey' => $_POST['accountKey'],
			'contenedor' => $_POST['contenedor']
		);
		save_config($data);
		$mensaje = 'Los datos fueron guardados correctamente';

	}//if

	$info = obtener_config();

?>
 	<div class="wrap">
 		<h2><?php _e( 'Configuracion CND Azure', 'cdnazure-wp' ); ?></h2>
 		<p> Ingrese los siguientes datos: </p>

 		<?php if($mensaje != '') { ?>
 		<div style="background-color:green; color: #fff; font-weight:bold; padding:10px;" align="center">
 			<?= $mensaje; ?>
 		</div>
 		<?php }//if ?>

		<form method="post">
			<input type="hidden" name="cdnAction" value="saveData">
 
			<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="accountName">Account Name:</label></th>
					<td><input name="accountName" type="text" value="<?= $info['accountName']; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="accountKey">Account Key:</label></th>
					<td><input name="accountKey" type="text" value="<?= $info['accountKey']; ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="contenedor">Contenedor:</label></th>
					<td><input name="contenedor" type="text" value="<?= $info['contenedor']; ?>" class="regular-text"></td>
				</tr>
			</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Guardar Datos">
			</p>
		</form>
 	</div>
<?php
}//cdnazure_dashboard

//Guardamos los datos en la tabla options

function save_config($data) {	
	update_option('cdnazure_options',$data);
}

//Obtenemos los datos de la tabla options

function obtener_config() {
	return get_option('cdnazure_options');
}

//Agregamos la accion para que detecte cuando se agregue una imagen desde wordpress
// y asi guardarlo también en el CDN Azure

add_action('add_attachment','update_cdnazure');

function update_cdnazure($attachment_ID) {

	$attachment = get_post($attachment_ID);
	$guid = $attachment->guid;
	$exguid = explode('/',$guid);
	$i = count($exguid) - 1;
	$fileName = $exguid[$i];
	$mes = $exguid[$i-1];
	$anio = $exguid[$i-2];

	$inf = obtener_config();
	$accountName = $inf['accountName'];
	$accountKey = $inf['accountKey'];
	$contenedor = $inf['contenedor'];

	$connectionString = "DefaultEndpointsProtocol=https;AccountName=".$accountName.";AccountKey=".$accountKey;
	$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($connectionString);

	$content = fopen($guid, "r");	
	$blob_name = "$anio/$mes/".$fileName;

	try    {
	    //Upload blob
	    $blobRestProxy->createBlockBlob($contenedor, $blob_name, $content);
	    $message = "Archivo insertado ".$attachment_ID.' :: '.$fileName."\r\n";	

	}
	catch(ServiceException $e){
	    // Handle exception based on error codes and messages.
	    // Error codes and messages are here:
	    // http://msdn.microsoft.com/library/azure/dd179439.aspx
	    $code = $e->getCode();
	    $error_message = $e->getMessage();
	    $message = $code.": ".$error_message."\n\r";
	}

	//log_files($message);

}//update_cdnazure

function wp_get_attachment( $attachment_id ) {

	$attachment = get_post( $attachment_id );
	return array(
		'alt' => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
		'caption' => $attachment->post_excerpt,
		'description' => $attachment->post_content,
		'href' => get_permalink( $attachment->ID ),
		'src' => $attachment->guid,
		'title' => $attachment->post_title
	);
}

//Use esta funcion para debuguear cualquier error al insertar una imagen

function log_files($contenido) {

	$ruta = $_SERVER['DOCUMENT_ROOT'].'/azure/';
	$archivo = fopen($ruta."/log_files.txt" , "w+");
	
	if ($archivo) { 
		fputs ($archivo, $contenido); 
	}
		
	fclose ($archivo);

}//log_files

//Agregamos el filtro para cuando se cargue un post, se verifique la imagen
//y se enlace al CDN Azure para cargarlo.

add_filter( 'post_thumbnail_html', 'my_post_image_html', 10, 5 );

function my_post_image_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
	
	$inf = obtener_config();
	$accountName = $inf['accountName'];
	$contenedor = $inf['contenedor'];
	
	$url = get_option( 'siteurl' ).'/wp-content/uploads';
	$html = str_replace($url,'https://'.$accountName.'.blob.core.windows.net/'.$contenedor, $html);

    return $html;
}

?>