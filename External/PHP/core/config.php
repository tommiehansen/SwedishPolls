<?php
# major defaults
error_reporting(E_ALL);
ini_set('display_errors', 1);

# dirs
define('BASE_DIR', './');
define('DATA_DIR', '../Data/');

# conf object
$config = [

	'cache' => '24 months', // false or ie '1 hour', '1 month', '12 months' etc
	'cache_dir' => BASE_DIR . 'cache/',
	'data_dir' => DATA_DIR,

];

$config = (object) $config;