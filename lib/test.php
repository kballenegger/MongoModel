<?php

require_once dirname(__FILE__).'/case_conversion.php';

class TestError extends Exception {}

abstract class Tester {
	
	final public static function test($tests = 'all') {
		$class = get_called_class();
		
		$failure = false;
		
		echo 'Testing `'.$class."`\n";
		
		if ($tests == 'all') {
			$tests = $class::list_tests();
		}
		
		if (is_string($tests))
			$tests = array($tests);
		
		foreach($tests as $test) {
			if (method_exists($class, 'test_'.$test)) {
				try {
					call_user_func($class.'::test_'.$test);
					echo "\n".'Test passed: `'.$test.'`'."\n";
				} catch (TestError $e) {
					echo "\n".'Test failed: `'.$test.'`'."\n".$e->getMessage()."\n";
					$failure = true;
				}
			} else {
				echo "\n".'Can\'t find test `'.$test.'`.'."\n";
				$failure = true;
			}
		}
		
		if ($failure) {
			return false;
		} else {
			return true;
		}
	}
	
	final public static function list_tests() {
		$class = get_called_class();
		$methods = get_class_methods($class);
		
		$tests = array();
		foreach($methods as $method) {
			if (preg_match('/^test_([a-z0-9_]+)$/', $method, $matches)) {
				$tests[] = $matches[1];
			}
		}
		return $tests;
	}
	
	final public static function run_all_tests() {
		
		if ($handle = opendir(dirname(__FILE__).'/../tests')) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match('/^([a-z]+)\.php$/', $file, $matches)) {
					require_once dirname(__FILE__).'/../tests/'.$file;
					$class = underscore_to_camel_case($matches[1]).'Tester';
					$class::test();
				}
			}
			closedir($handle);
		}
	}
}