<?php
return [
	'settings' => [
		'displayErrorDetails' => true, // set to false in production
		'addContentLengthHeader' => false, // Allow the web server to send the content-length header

		// Renderer settings
		'renderer' => [
			'template_path' => __DIR__ . '/templates/',
		],

		// OAuth 2 Database
		'oauth_db' => [
			'dsn'    => 'mysql',
			'dbname' => 'developer_test',
			'host'   => 'localhost',
			'port'   => '8889',
			'user'   => 'root',
			'pass'   => '', // can't access with password root, leave blank
		],
	],
];
