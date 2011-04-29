<?php

require_once dirname(__FILE__).'/../lib/test.php';
require_once dirname(__FILE__).'/../lib/hash.php';

class HashTester extends Tester {
	
	public static $array;
	
	public static function test_construct() {
		self::$array = null;
		
		$data = array(
				'numeric' => 123,
				'string' => 'something',
				'object' => new stdClass,
				'array' => array('one', 'two', 3),
				'hash' => array(
						'one' => 1,
						'two' => 2,
						'three' => 3,
					)
			);
		
		$array = Hash::create($data);
		
		if (!$array instanceof Hash)
			throw new TestError('Hash not created correctly.');
		
		self::$array = $array;
	}
	
	public static function test_numeric() {
		self::depends_on('construct');
		$array = self::$array;
		
		if (!$array instanceof Hash)
			throw new TestError('Hash statically stored not readable.');
		
		if ($array->numeric != 123)
			throw new TestError('Numeric data failure.'."\n".var_export($array->numeric, true));
	}

	public static function test_string() {
		self::depends_on('construct');
		$array = self::$array;
		
		if (!$array instanceof Hash)
			throw new TestError('Hash statically stored not readable.');
		
		if ($array->string != 'something')
			throw new TestError('String data failure.'."\n".var_export($array->string, true));
	}

	public static function test_object() {
		self::depends_on('construct');
		$array = self::$array;
		
		if (!$array instanceof Hash)
			throw new TestError('Hash statically stored not readable.');
		
		if (!$array->object instanceof stdClass)
			throw new TestError('Object data failure.'."\n".var_export($array->object, true));
	}

	public static function test_array() {
		self::depends_on('construct');
		$array = self::$array;
		
		if (!$array instanceof Hash)
			throw new TestError('Hash statically stored not readable.');
		
		if (!is_array($array->array))
			throw new TestError('Array member is not array.'."\n".var_export($array->array, true));

		if (!is_array($array->array))
			throw new TestError('Array data failure.');
	}
	
	public static function test_hash() {
		self::depends_on('construct');
		$array = self::$array;
		
		if (!$array instanceof Hash)
			throw new TestError('Hash statically stored not readable.');
		
		if (!$array->hash instanceof Hash)
			throw new TestError('Hash member is not hash.'."\n".var_export($array->hash, true));

		if ($array->hash->one != 1)
			throw new TestError('Hash member content not as expected.'."\n".var_export($array->hash, true));
	}
}