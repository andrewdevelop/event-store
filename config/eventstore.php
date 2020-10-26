<?php 

return [
	/** Default event store connection */
	'connection' => env('ES_CONNECTION', 'pgsql'),

	/** Database connections */
	'connections' => [
		'pgsql' => [
	        'driver' 	=> 'pgsql',
	        'host' 		=> env('ES_HOST', '127.0.0.1'),
	        'port' 		=> env('ES_PORT', 5432),
	        'database' 	=> env('ES_DATABASE', 'eventstore'),
	        'username' 	=> env('ES_USERNAME', 'homestead'),
	        'password' 	=> env('ES_PASSWORD', 'secret'),
	        'charset' 	=> env('ES_CHARSET', 'utf8'),
	        'prefix' 	=> env('ES_PREFIX', ''),
	        'schema' 	=> env('ES_SCHEMA', 'public'),
	        'sslmode' 	=> env('ES_SSL_MODE', 'prefer'),
	    ],
        'filesystem' => [
            'database' => env('ES_DATABASE', storage_path('.eventstore')),
        ],
        'in_memory' => [
            'database' => null,
        ],
	],
];