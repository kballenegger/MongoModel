<?php

$mongo_host = 'localhost';
$mongo_port = 27017;
$mongo_database = 'test';
$mongo = new Mongo($mongo_host.':'.$mongo_port);
$GLOBALS['db'] = $mongo->{$mongo_database};

require_once dirname(__FILE__).'/lib/test.php';
$GLOBALS['tests_path'] = dirname(__FILE__).'/tests/';

Tester::run_all_tests();