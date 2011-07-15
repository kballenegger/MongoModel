<?php

define('TEST_PATH', dirname(__FILE__).'/');

/*
 *
 * ------------ DO NOT EDIT ANYTHING BELOW THIS LINE ------------
 *
 */

spl_autoload_register(function($class_name) {
	$class_name = preg_replace('/(?<=\\w)(?=[A-Z])/',"_$1", $class_name);
	$class_name = strtolower($class_name);
	$path = TEST_PATH.$class_name.'.php';

	if (file_exists($path)) {
		require_once $path;
		return true;
	} else {
		return false;
	}
});


class TestError extends Exception {}

class TestDependencyError extends Exception {
	public $test;
	public function __construct($test) {
		$this->test = $test;
	}
}

abstract class Tester {
	
	public static $_tests_history = array();
	
	final public static function depends_on($test) {
		$class = get_called_class();
		
		if (!isset($class::$_tests_history[$test])) {
			echo 'Test required by dependecy not run: `'.$test.'`'."\n";
			$class::_test_single($test);
			echo "\n";
			return $class::depends_on($test);
		} else if (isset($class::$_tests_history[$test]['running']) && $class::$_tests_history[$test]['running']) {
			TerminalColor::out('yellow', 'Test required by dependecy is already running. This should not happen: Make sure tests are not depending on each other recursively.'."\n");
		} else if (isset($class::$_tests_history[$test]['success']) && $class::$_tests_history[$test]['success'] == false) {
			TerminalColor::out('red', 'Dependency failed: `'.$test.'`'."\n");
			throw new TestDependencyError($test);
		} else if (isset($class::$_tests_history[$test]['success']) && $class::$_tests_history[$test]['success'] == true) {
			echo 'Dependency passed: `'.$test.'`'."\n";
		} else {
			TerminalColor::out('yellow', 'Unexpected result.'."\n");
		}
	}
	
	final public static function _test_single($test) {
		$class = get_called_class();
		
		$prefix = 'test_';
		$actual_test = true;
		
		// run pre / post normally
		if ($test == 'pre_test' || $test == 'post_test') {
			$prefix = '';
			$actual_test = false;
		}
		
		
		if ($actual_test && isset($class::$_tests_history[$test])) {
			TerminalColor::out('yellow', 'Test already run: `'.$test.'`'."\n");
			return false;
		}

		$class::$_tests_history[$test] = array(
				'running' => true
			);

		$success = true; // assume success
		
		// run pre
		if ($actual_test && method_exists($class, 'pre_test')) {
			$success *= self::_test_single('pre_test');
		}

		if (method_exists($class, $prefix.$test) && $success) {
			
			if ($actual_test) echo "\n".'Testing: `'.$test.'`'."\n";
			try {
				TerminalColor::yellow();
				call_user_func($class.'::'.$prefix.$test);
				TerminalColor::reset();
				$description = 'Test passed: `'.$test.'`';
				$class::$_tests_history[$test]['description'] = $description;
				if ($actual_test) TerminalColor::out('green', $description."\n");
				$success *= true;
			} catch (TestDependencyError $e) {
				$dependency = $e->test;
				$description = 'Test `'.$test.'` failed due to dependency on: `'.$dependency.'`'."\n";
				$class::$_tests_history[$test]['description'] = $description;
				TerminalColor::out('red', $description."\n");
				$success *= false;
			} catch (TestError $e) {
				$description = 'Test failed: `'.$test.'`'."\n".$e->getMessage();
				$class::$_tests_history[$test]['description'] = $description;
				TerminalColor::out('red', $description."\n");
				$success *= false;
			}
		} else if($success == false) {
			$description =  'Looks like pre_test failed...';
			$class::$_tests_history[$test]['description'] = $description;
			TerminalColor::out('red', $description."\n");
			$success *= false;
		} else {
			$description =  'Can\'t find test `'.$test.'`.';
			$class::$_tests_history[$test]['description'] = $description;
			TerminalColor::out('red', $description."\n");
			$success *= false;
		}

		// run post
		if ($actual_test && method_exists($class, 'post_test')) {
			$success *= self::_test_single('post_test');
		}

		$class::$_tests_history[$test]['success'] = $success;
		$class::$_tests_history[$test]['running'] = false;
		return $success;
	}
	
	final public static function test($tests = 'all') {
		$class = get_called_class();
		$class::$_tests_history = array();
		
		$success = true;
		
		echo 'Testing `'.$class."`\n";
		
		if ($tests == 'all') {
			$tests = $class::list_tests();
		}
		
		if (is_string($tests))
			$tests = array($tests);
		
		foreach($tests as $test) {
			$success *= $class::_test_single($test);
		}
		
		return $success;
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
	
	final public static function run_tests($tests = array()) {
		
		$success = true;
		
		foreach ($tests as $test) {
			require_once TEST_PATH.'../tests/'.$test.'.php';
			$class = CaseConversion::underscore_to_camel_case($test).'Tester';
			$success *= $class::test();
			echo "\n".'---'."\n\n";
		}
		
		if ($success) {
			TerminalColor::out('green', 'TESTS PASSED'."\n");
		} else {
			TerminalColor::out('red', 'TESTS FAILED'."\n");
		}
		return $success;
	}
	
	final public static function run_all_tests() {
		$success = true;
		
		if ($handle = opendir(TEST_PATH.'../tests')) {
			$tests = array();
			while (false !== ($file = readdir($handle))) {
				if (preg_match('/^([a-z_]+)\.php$/', $file, $matches)) {
					$tests[] = $matches[1];
				}
			}
			closedir($handle);
			$success *= self::run_tests($tests);
		} else {
			TerminalColor::out('red', 'Couldn\'t open test directory'."\n");
		}
		return $success;
	}
}