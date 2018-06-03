<?php

class DropboxClient {

    const API_URL = "https://api.dropboxapi.com/";
    const API_CONTENT_URL = "https://content.dropboxapi.com/";
    
    private $dropbox_params;
    private $_redirectUri;
    private $accessToken;

    /**
	  * DropboxClient constructor.
	  *
	  * @param array $dropbox_params ['dropbox_key' => ..., 'dropbox_secret' => ...]
	  *
	  * @throws Exception
	  */
    public function __construct($dropbox_params) {

      $this->dropbox_params = $dropbox_params;

      if ( empty( $dropbox_params['dropbox_key'] ) ) {
        throw new Exception( "Drobox Key is empty!" );
      }

      if ( empty( $dropbox_params['dropbox_secret'] ) ) {
        throw new Exception( "Drobox secret is empty!" );
      }
      
      $this->accessToken = null;

      if ( !function_exists( 'curl_init' ) ) {
        throw new Exception( "Curl has no include" );
      }
    }

    /**
	  * Returns the redirected URL to connect the app to user's Dropbox account
	  *
	  * @param string $redirect_uri URL users are redirected after authorization
	  *
    * @return URL
	  */
    public function authorizeUrl( $redirect_uri) {
		  $this->_redirectUri = $redirect_uri;
      return "https://www.dropbox.com/oauth2/authorize"
      . "?response_type=code" 
      . "&client_id={$this->dropbox_params['dropbox_key']}&redirect_uri=" . urlencode( $redirect_uri );
    }

  /**
	 * Get authorization bearer token in order to pass it to header
	 * @param string $redirect_uri 
	 *
	 * @return accessToken
	 * @throws Exception
	 */
    public function getToken( $redirect_uri = '' ) {
		  if ( !empty( $this->accessToken ) ) {
			  return $this->accessToken;
      }
      if( empty( $_GET['code'] ) ) {
        throw new Exception('Empty code');
      }
      $code = $_GET['code'];
      
      $res = $this->apiCall( "oauth2/token" , array(
        'code'          => $code,
        'grant_type'    => 'authorization_code',
        'client_id'     => $this->dropbox_params['dropbox_key'],
        'client_secret' => $this->dropbox_params['dropbox_secret'],
        'redirect_uri'  => $redirect_uri
      ) );

      return !empty( $res ) 
          ? ($this->accessToken = array( 't' => $res->access_token, 'account_id' => $res->account_id )) 
          : '' ;
    }

    /**
     * set authorization bearer Token
     * @param array token
     */
    public function setToken( $token ) {
      if ( empty( $token['t'] ) ) {
        throw new Exception('Token Error');
      }
      $this->accessToken = $token;
    }

    /**
     * Destroy authorization bearer token
     * initiliaze accessToken to null 
     */
    public function revokeToken(){
      $res = $this->apiCall( "2/auth/token/revoke" , null );
      $this->accessToken = null;
    }

    /**
     * upload file to dropbox
     * @param string $src_file path of source file to be uploaded
     * @param string $path dropbox remote path to upload file 
     * @param bool $overwrite option to enable file overwriting
     * @throws Exception
     * @return object Upload response
     */
    public function uploadFile( $src_file, $path = '' , $overwrite = true ) {
      if ( !empty( $path ) ) {
        $path .= '/'.basename( $src_file );
      }
  
      
      $params = array(
        'path'       => $path,
        'mode'       => $overwrite ? 'overwrite' : 'add',
        'autorename' => true
      );
      
      if( !file_exists( $src_file ) ) {
        throw new Exception('No such file or directory');
      }
      $file_size = filesize( $src_file );
      $data = file_get_contents( $src_file );
      $file = (object) array( 'data' => $data , 'size' => $file_size );

      return $this->apiCall( "2/files/upload", $params, true, $file );
    }
  
  /**
   * create http context and call dropbox api
   */
  private function apiCall( $path, $params = [], $content_call = false, $file = false ) {

    $url =  ($content_call ? self::API_CONTENT_URL : self::API_URL ) . $path ;
    
    $http_context = $this->createHeader( $url, $params, $file );

    $resp = $this->call( $url, $http_context );

    return $resp;
  }

  /**
   * create http context params 
   */
  private function createHeader( $url, $params, $file = false ){
    $http_context = array( 'method' => "POST", 'header' => '', 'content' => '' );
    if ( strpos( $url, '/oauth2/token' ) !== false ) {
      $http_context['header']  .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$http_context['content'] = http_build_query( $params );
    }else{
      $http_context['header'] .= "Authorization: Bearer ".$this->accessToken['t']."\r\n";
      if ( $file !== false ) {
        $http_context['header'] .= 'Dropbox-API-Arg: ' . str_replace( '"', '"', json_encode( (object) $params ) ) . "\r\n";
				$http_context['header'] .= "Content-Type: application/octet-stream\r\n";
				if ( ! empty( $file->data ) ) {
					$http_context['content'] = $file->data;
        }
      }else{
        $http_context['header']  .= "Content-Type: application/json\r\n";
				$http_context['content'] = json_encode( $params );
      }
    }

    if ( strpos( $url, self::API_CONTENT_URL ) === false ) {
			$http_context['header'] .= "Content-Length: " . strlen( $http_context['content'] );
		}

		$http_context['header'] = trim( $http_context['header'] );

    $http_context['ignore_errors'] = true;

    return $http_context;
  }

  /**
   * call dropbox api
   * retrieve api response
   * check for errors
   * 
   */
  private function call( $url, $http_context ) {
		$ch = curl_init( $url );

		$curl_opts = array(
			CURLOPT_HEADER         => false, // exclude header from output //CURLOPT_MUTE => true, // no output!
			CURLOPT_RETURNTRANSFER => true, // but return!
			CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_BINARYTRANSFER => true
		);

		$curl_opts[ CURLOPT_CUSTOMREQUEST ] = $http_context['method'];

		if ( ! empty( $http_context['content'] ) ) {
			$curl_opts[ CURLOPT_POSTFIELDS ] = $http_context['content'];
			if ( defined( "CURLOPT_POSTFIELDSIZE" ) ) {
				$curl_opts[ CURLOPT_POSTFIELDSIZE ] = strlen( $http_context['content'] );
			}
		}

		$curl_opts[ CURLOPT_HTTPHEADER ] = array_map( 'trim', explode( "\n", $http_context['header'] ) );

    curl_setopt_array( $ch, $curl_opts );
    
    $result = curl_exec( $ch );
    if( curl_errno( $ch ) ) {
        throw new Exception( curl_error( $ch ) );
    }

    curl_close( $ch );

    $resp = json_decode( $result );

		if ( is_null( $resp ) ) {
			return null;
    }
    if ( is_null( $resp ) && !empty( $json ) ) {
			throw new Exception( "failed: $json (URL was $url)" );
		}

    return $this->checkError( $resp );
  }
  
  /**
   * check api result for possible errors
   */
  private function checkError( $result ){
    if ( !empty( $result->error ) ) {
      echo "Error : " . $result->error . " (" .$result->error_description . ") <br>";
      return null;
    }
    return $result;
  }

}
?>