<?php

require_once dirname(__FILE__).'/case_conversion.php';
require_once dirname(__FILE__).'/colorize_output.php';

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
		
		if (isset($class::$_tests_history[$test])) {
			TerminalColor::out('yellow', 'Test already run: `'.$test.'`'."\n");
			return;
		}

		$class::$_tests_history[$test] = array(
				'running' => true
			);

		$success = false;

		if (method_exists($class, 'test_'.$test)) {
			
			echo "\n".'Testing: `'.$test.'`'."\n";
			try {
				call_user_func($class.'::test_'.$test);
				$description = 'Test passed: `'.$test.'`';
				$class::$_tests_history[$test]['description'] = $description;
				TerminalColor::out('green', $description."\n");
				$success = true;
			} catch (TestDependencyError $e) {
				$dependency = $e->test;
				$description = 'Test `'.$test.'` failed due to dependency on: `'.$dependency.'`'."\n";
				$class::$_tests_history[$test]['description'] = $description;
				TerminalColor::out('red', $description."\n");
				$success = false;
			} catch (TestError $e) {
				$description = 'Test failed: `'.$test.'`'."\n".$e->getMessage();
				$class::$_tests_history[$test]['description'] = $description;
				TerminalColor::out('red', $description."\n");
				$success = false;
			}
		} else {
			$description =  'Can\'t find test `'.$test.'`.';
			$class::$_tests_history[$test]['description'] = $description;
			TerminalColor::out('yellow', $description."\n");
			$success = false;
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
	
	final public static function run_all_tests() {
		
		$success = true;
		
		if ($handle = opendir(dirname(__FILE__).'/../tests')) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match('/^([a-z]+)\.php$/', $file, $matches)) {
					require_once dirname(__FILE__).'/../tests/'.$file;
					$class = underscore_to_camel_case($matches[1]).'Tester';
					$success *= $class::test();
					echo "\n".'---'."\n\n";
				}
			}
			closedir($handle);
		} else {
			$success = false;
		}
		
		if ($success) {
			TerminalColor::out('green', 'TESTS PASSED'."\n");
		} else {
			TerminalColor::out('red', 'TESTS FAILED'."\n");
		}
		return $success;
	}
}