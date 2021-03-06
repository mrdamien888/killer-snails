<?php
if (PHP_SAPI == 'cli-server') {
	// To help the built-in PHP dev server, check if the request was actually for
	// something which should probably be served as a static file
	$url  = parse_url($_SERVER['REQUEST_URI']);
	$file = __DIR__ . $url['path'];
	if (is_file($file)) {
		return false;
	}
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/classes/ksaPDO.php';
require __DIR__ . '/src/classes/ksaRequest.php';
require __DIR__ . '/src/classes/Emailer.php';
require __DIR__ . '/src/middleware/VerifyOrRenewToken.php';

session_start();

// Instantiate the app
if($_SERVER['REMOTE_ADDR']=="127.0.0.1" || $_SERVER['REMOTE_ADDR']=="::1") {
	$settings = require __DIR__ . '/src/settings.php';
} else {
	$settings = require __DIR__ . '/src/settings-prod.php';
}
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/src/dependencies.php';

// Register middleware
require __DIR__ . '/src/middleware.php';

// Register routes
require __DIR__ . '/src/routes.php';

// Run app
$app->run();
?>