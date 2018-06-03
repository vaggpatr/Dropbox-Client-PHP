<?php
session_start();

require_once 'config.php';

require_once 'client/DropboxClient.php';


$dropbox = new DropboxClient(array(
	'dropbox_key'         => $dropbox_key,
	'dropbox_secret'      => $dropbox_secret
	));

// create url
$protocol = stripos( $_SERVER['SERVER_PROTOCOL'] , 'https' ) === true ? 'https://' : 'http://';
$url = $protocol.$_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
$return_url = $url . "?auth_redirect=true";
	
// if logout clear token and session	
if( isset( $_GET['logout'] ) && isset( $_SESSION['token'] ) ) {
	$dropbox->setToken( $_SESSION['token'] );
	$dropbox->revokeToken();
	session_unset();
	session_destroy();
}


if( !isset( $_SESSION['token'] ) && !isset( $_GET['auth_redirect'] ) ) {
	$auth_url = $dropbox->authorizeUrl( $return_url );
	die( "Authentication required. <a href='$auth_url'>Continue.</a>" );
}

echo "Logout Dropbox. <a href='$url?logout=true'>Logout.</a><br>";
if( isset( $_GET['auth_redirect'] ) && !isset( $_SESSION['token'] ) ) {
	//get token
	$token = $dropbox->getToken( $return_url );
	if( empty( $token ) ) {
		throw new Exception( 'Empty token' );
	}
	$_SESSION['token'] = $token;
	//call file upload
	echo "<a href='$url?upload=true'>Upload File.</a><br>";
}elseif( isset( $_GET['upload'] ) ) {
		//upload file
		$filenameupload = "upload.txt"; //file name

		creaateFile( $filenameupload );

		$dropbox->setToken( $_SESSION['token'] );
		
		$dropboxfolder = '/upload'; //drobpox folder select
		echo "<pre>";
		var_dump( $dropbox->uploadFile( $filenameupload, $dropboxfolder ) ); //upload file to dropbox
		echo "</pre><br>";
		echo 'File uploaded!!!';exit;

}
	
function creaateFile( $filenameupload ){
	$handle = fopen( $filenameupload , 'w') or die( 'Cannot open file:  '.$filenameupload ); //implicitly creates file
	$data = date( "d-m-Y h:i:sa" );
	fwrite( $handle, $data );
	fclose( $handle );
}
?>