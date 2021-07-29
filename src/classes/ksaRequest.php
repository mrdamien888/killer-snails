<?php
namespace KillerSnailsAccounts;

use OAuth2\Request;

class ksaRequest extends Request {
	/**
	 * Creates a new request with values from PHP's super globals.
	 *
	 * @return Request - A new request
	 *
	 * @api
	 */
	public static function createFromGlobals() 	{
		$class = get_called_class();

		/** @var Request $request */
		$request = new $class($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);

		$contentType = $request->server('CONTENT_TYPE', '');
		$requestMethod = $request->server('REQUEST_METHOD', 'GET');
		if (0 === strpos($contentType, 'application/x-www-form-urlencoded') && in_array(strtoupper($requestMethod), array('PUT', 'DELETE')) ) {
			parse_str($request->getContent(), $data);
			$request->request = $data;
		} elseif (0 === strpos($contentType, 'application/json') && in_array(strtoupper($requestMethod), array('POST', 'PUT', 'DELETE')) ) {
			$data = json_decode($request->getContent(), true);
			$request->request = $data;
		}

		/** @author CPollati */
		if(isset($_SESSION["ksa_token"])) {
			if($request->query("access_token")=="") {
				$request->query = array_merge($request->query, array( "access_token" => $_SESSION["ksa_token"]["access_token"] ));
				$request->query = array_merge($request->query, array( "refresh_token" => $_SESSION["ksa_token"]["refresh_token"] ));
				$request->query = array_merge($request->query, array( "scope" => $_SESSION["ksa_token"]["scope"] ));
			}
		} else {
			if(isset($_COOKIE["ksa_at"])) {
				$request->query = array_merge($request->query, array( "access_token" => $_COOKIE["ksa_at"] ));
				$request->query = array_merge($request->query, array( "refresh_token" => $_COOKIE["ksa_rt"] ));
				$request->query = array_merge($request->query, array( "scope" => $_COOKIE["ksa_s"] ));
			}
		}

		return $request;
	}

	public static function createFromGlobalsWithGrantType($client_id,$client_secret,$grant_type = "password") 	{
		$class = get_called_class();

		/** @var Request $request */
		$request = new $class($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);

		$contentType = $request->server('CONTENT_TYPE', '');
		$requestMethod = $request->server('REQUEST_METHOD', 'GET');
		if (0 === strpos($contentType, 'application/x-www-form-urlencoded') && in_array(strtoupper($requestMethod), array('PUT', 'DELETE')) ) {
			parse_str($request->getContent(), $data);
			$request->request = $data;
		} elseif (0 === strpos($contentType, 'application/json') && in_array(strtoupper($requestMethod), array('POST', 'PUT', 'DELETE')) ) {
			$data = json_decode($request->getContent(), true);
			$request->request = $data;
		}

		if($client_id=="") {
			$client_id = $_COOKIE["ksa_cid"];
			$client_secret = $_COOKIE["ksa_cs"];
		}

		// Authenticate with Client ID and Secret
		$request->headers = array_merge($request->headers, array( "PHP_AUTH_USER" => $client_id ));
		$request->headers = array_merge($request->headers, array( "PHP_AUTH_PW" => $client_secret ));
		$request->headers = array_merge($request->headers, array( "AUTHENTICATION" => "Basic " . base64_encode($client_id.":".$client_secret) ));

		$request->request = array_merge($request->request, array( "grant_type" => $grant_type ));
		if($grant_type=="refresh_token") {
			$request->request = array_merge($request->request, array( "refresh_token" => $_SESSION["ksa_token"]["refresh_token"] ));
			$request->server = array_merge($request->server, array( "REQUEST_METHOD" => "POST" ));
			$requestMethod = $request->server('REQUEST_METHOD', 'POST');
		}

		return $request;
	}
}
?>
