<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class VerifyOrRenewToken {
	protected $container;

	public function __construct($container) { $this->container = $container; }

	public function __invoke(Request $request, Response $response, callable $next) {
		$accessToken = array();
		$profile = array();
		$tokenCheck = verifyTokenOrRefresh($this->container,KillerSnailsAccounts\ksaRequest::createFromGlobals());
		if($tokenCheck!==true) {
			$error = json_decode($tokenCheck);
			if(isset($error->error_description)) {
				if($error->error_description=="The access token provided is invalid" ) {
					unsetAllSessionInfo($this->container->cookie_domain);
				}
			}
			return $response->withRedirect("login");
		}

		$accessToken = $this->container->oauth_server->getAccessTokenData(KillerSnailsAccounts\ksaRequest::createFromGlobals());
		if($this->container->oauth_storage->isUserVerified($accessToken["user_id"])==false) {
			return $response->withRedirect("need_to_verify");
		}

		$profile = $this->container->oauth_storage->getProfile($accessToken["user_id"]);
		$request = $request->withAttribute("accessToken", $accessToken);
		$request = $request->withAttribute("profile", $profile);

		$response = $next($request, $response);
		return $response;
	}
}

function verifyTokenOrRefresh($base, $oauth_request) {
	$result = "";
	if (!$base->oauth_server->verifyResourceRequest($oauth_request)) {
		$checkError = json_decode($base->oauth_server->getResponse()->getResponseBody());
		if(isset($checkError->error_description) && $checkError->error_description=="The access token provided has expired") {
			$oauth_request = KillerSnailsAccounts\ksaRequest::createFromGlobalsWithGrantType($base->client_id,$base->client_secret,"refresh_token");
			$results = $base->oauth_server->handleTokenRequest($oauth_request);
			if(isset($results->getParameters()["error"])) {
				$result = $base->oauth_server->getResponse()->getResponseBody();
			} else {
				error_log(" *** TOKEN RENEWED!");
				$_SESSION["ksa_token"] = $results->getParameters();
				setCookiesForLogin($base->cookie_domain,$base->client_id,$base->client_secret);
				$result = true;
			}
		} else {
			$result = $base->oauth_server->getResponse()->getResponseBody();
		}
	} else {
		$result = true;
	}
	return $result;
}
?>