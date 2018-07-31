Alexandria Framework
--------------------

``` php
<?php

$loader = require_once(__DIR__.'/alexandria/lib/loader');
$loader->register($namespace = "", __DIR__ . "/app");

$output = alexandria\cms::start([
	'db'       => [
		'driver'   => 'pdo',
		'dsn'      => 'mysql:dbname=test;host=localhost',
		'username' => 'user',
		'password' => 'password',
	],

	'router'   => [
		'autoroute'     => true,           // pass routes to controllers that are matched with the namespace
		'default_route' => 'index',        // pass / queries to this controller
		'fail_route'    => 'pagenotfound', // pass not matched routes to this controller
	],

	'firewall' => [
		'allow' => [
			'192.168.0.0/16',
			'remote.example.com',
		],
		'deny'  => [
			'all',
		],
	],
]);

echo $output;

```

Controller
==========

In app/index.php or app/index/controller.php (use namespace `index` and the class name `controller` in the second case):

``` php
<?php

use alexandria\cms\controller;

class index extends controller
{
	public static function __widget()
	{
		return "Hi, I'm index controller widget";
	}

	public function some_action($id) // called with http://yourhost.example.com/some_action/some_id
	{
		$ret = $id; // "some_id"

		cms::theme()->set('new_theme');
		cms::theme()->show_form('index/main_form', [
			'id' => $ret,
		]);
	}

	// ...
}

```

Form (View)
===========

```
Hi, I'm an form.
I can write some value: <code>{$value}</code> if it's passed to cms::theme()->show_form() or cms::theme()->load_form().
Also I can display values from configuration: {[config_name]}.
And some widgets {{controller_name}} from the application controllers.

```

Good luck!
