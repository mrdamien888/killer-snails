<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
	$settings = $c->get('settings')['renderer'];
	return new Slim\Views\PhpRenderer($settings['template_path']);
};

// PDO Database Library
$container['db'] = function ($c) {
	$settings = $c->get('settings')['db'];
	$pdo = new PDO('mysql:host=' . $settings['host'] . ":" . $settings['port'] . ";dbname=" . $settings['dbname'],$settings['user'],$settings['pass']);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	// Fix for number being returned as strings. Thanks https://stackoverflow.com/questions/1197005/how-to-get-numeric-types-from-mysql-using-pdo
	$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	return $pdo;
};

// OAuth2 Server
$container['oauth_storage'] = function ($c) {
	$settings = $c->get('settings')['oauth_db'];
	$storage = new KillerSnailsAccounts\ksaPDO(array('dsn' => $settings['dsn'] . ':dbname=' . $settings['dbname'] . ';host=' . $settings['host'], 'username' => $settings['user'], 'password' => $settings['pass']));
	return $storage;
};

$container['oauth_server'] = function ($c) {
	$storage = $c->get('oauth_storage');

	$server = new OAuth2\Server($storage);
	$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
	$server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
	$server->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
	$server->addGrantType(new OAuth2\GrantType\RefreshToken($storage,array('always_issue_new_refresh_token' => true)));

	return $server;
};

// Cookie Domain
$container['cookie_domain'] = function ($c) {
	if($_SERVER['REMOTE_ADDR']=="127.0.0.1" || $_SERVER['REMOTE_ADDR']=="::1") {
		$cookieDomain = "localhost";
	} else {
		if(substr_count($_SERVER["host_name"], ".")>1) {
			$cookieDomain = substr($_SERVER["host_name"], strpos($_SERVER["host_name"],".")+1);
		} else {
			$cookieDomain = $_SERVER["host_name"];
		}
	}
	return $cookieDomain;
};

// Client ID/Secret for Accounts pages
$container['client_id'] = function ($c) {
	return "testclient";
};

$container['client_secret'] = function ($c) {
	return "testpass";
};

// Custom Errors, 404 and 405 handlers
$container['errorHandler'] = function ($c) {
	return function ($request, $response, $exception) use ($c) {
		return $c['renderer']->render($response->withStatus(500)->withHeader('Content-Type', 'text/html'), "handlers/error.phtml", [ "exception" => $exception ]);
	};
};

$container['phpErrorHandler'] = function ($c) {
	return function ($request, $response, $exception) use ($c) {
		return $c['renderer']->render($response->withStatus(500)->withHeader('Content-Type', 'text/html'), "handlers/error.phtml", [ "exception" => $exception ]);
	};
};

$container['notFoundHandler'] = function ($c) {
	return function ($request, $response) use ($c) {
		return $c['renderer']->render($response->withStatus(404)->withHeader('Content-Type', 'text/html'), "handlers/not_found.phtml");
	};
};

$container['notAllowedHandler'] = function ($c) {
	return function ($request, $response, $methods) use ($c) {
		return $c['renderer']->render(
			$response->withStatus(405)
			->withHeader('Allow', implode(', ', $methods))
			->withHeader('Content-type', 'text/html'), "handlers/not_allowed.phtml", [ "methods" => $methods ]);
	};
};
