<?php

/*

	Written by Kenneth Ballenegger in late 2010, early 2011

	Copyright © Kenneth Ballenegger. All rights reserved.

*/

define('MONGOMODEL_PATH', dirname(__FILE__).'/');
define('MONGOMODEL_SAVE_IMPLICITLY', false);
define('MONGOMODEL_CACHE_EXPIRATION', 60); // 10 minutes

/*
 *
 * ------------ DO NOT EDIT ANYTHING BELOW THIS LINE ------------
 *
 */

spl_autoload_register(function($class_name) {
	$class_name = preg_replace('/(?<=\\w)(?=[A-Z])/',"_$1", $class_name);
	$class_name = strtolower($class_name);
	$path = MONGOMODEL_PATH.$class_name.'.php';

	if (file_exists($path)) {
		require_once $path;
		return true;
	} else {
		return false;
	}
});



class MongoModel_OneToManyRelationship implements Iterator {
	public $ids = array();

	protected $key = null;
	protected $from = null;
	protected $to = null;
	protected $valid = false;

	public function __construct($ids, $from, $key, $to) {
		if (is_array($ids)) {
    		$this->ids = $ids;
			$this->from = $from; // is an instance
			$this->key = $key; // the key of the relationship in $from
			$this->to = $to; // is a class name

			$this->valid = true;
		} else {
			return false;
		}
	}
	
	public function contains($object) {
		return in_array($object->id, $this->ids);
	}
	
	public function add($object) {
		if ($object instanceof $this->to) {
			$this->from->array_push($this->key, $object->id);
			return $this->from->save();
		} else {
			return false;
		}
	}
	
	public function delete($object) {
		if ($object instanceof $this->to) {
			$this->from->array_unset_value($this->key, $object->id);
			$this->from->save();
		} else {
			return false;
		}
	}
	
	public function count() {
		return count($this->ids);
	}
	
	// Iterator Interface
	
	public function current() {
		$id = current($this->ids);
		$class = $this->to;
		return $class::find_by_id($id);
	}

	// Boilerplate code

	public function rewind() {
		reset($this->ids);
	}

	public function key() {
		return key($this->ids);
	}

	public function next() {
		return next($this->ids);
	}

	public function valid() {
		$key = key($this->ids);
		return ($key !== null && $key !== false);
	}
}

class MongoModel_ManyToManyRelationship extends MongoModel_OneToManyRelationship {
	
	protected $foreign_key = null;
	
	public function __construct($ids, $from, $key, $to, $foreign_key) {
		$this->foreign_key = $foreign_key;
		return parent::__construct($ids, $from, $key, $to);
	}
	
	public function add($object, $non_reciprocal = false) {
		if (!$non_reciprocal) {
			$foreign_key = $this->foreign_key;
			$object->__get($foreign_key)->add($this->from, true);
			$object->save();
		}
		return parent::add($object);
	}

	public function delete($object, $non_reciprocal = false) {
		if (!$non_reciprocal) {
			$foreign_key = $this->foreign_key;
			$object->__get($foreign_key)->delete($this->from, true);
			$object->save();
		}
		return parent::delete($object);
	}
}

abstract class MongoModel {
	
	// static
	public static $_collection = null;

	protected static $_has_many = array();
	protected static $_has_many_to_many = array();
	protected static $_has_one = array();
	protected static $_has_one_to_one = array();
	protected static $_defined = false;
	
	// non-static
	protected $_data = array();
	private $date_modified_override = null;
	public $_errors = array();

	private $_relationship_object_cache = array();
	
	// static
	final protected static function _replace_mongo_id_recursively(&$array) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				MongoModel::_replace_mongo_id_recursively($value);
			} else if ($key == 'id') {
				$array['_id'] = new MongoID($value);
				unset($array['id']);
			}
		}
	}
	
	protected static function _prepare_query($query) {
		MongoModel::_replace_mongo_id_recursively($query);
		return $query;
	}
	
	protected static function _get_collection_name() {
		$class = get_called_class();
		if (isset($class::$_collection) && $class::$_collection!=null) {
			$_collection = $class::$_collection;
		} else {
			$_collection = CaseConversion::camel_case_to_underscore($class).'s';
		}
		return $_collection;
	}
	
	protected static function _get_collection() {
		$class = get_called_class();
		$db = $GLOBALS['db'];
		$_collection = $class::_get_collection_name();
		return $db->{$_collection};
	}
	
	protected static function has_one($key, $target) {
		$class = get_called_class();
		if (!isset(self::$_has_one[$class])) self::$_has_one[$class] = array();
		self::$_has_one[$class][$key] = $target;
	}
	protected static function has_one_to_one($key, $target, $foreign_key) {
		$class = get_called_class();
		if (!isset(self::$_has_one_to_one[$class])) self::$_has_one_to_one[$class] = array();
		self::$_has_one_to_one[$class][$key] = Hash::create(array('key' => $foreign_key, 'target' => $target));
	}
	protected static function has_many($key, $target) {
		$class = get_called_class();
		if (!isset(self::$_has_many[$class])) self::$_has_many[$class] = array();
		self::$_has_many[$class][$key] = $target;
	}
	protected static function has_many_to_many($key, $target, $foreign_key) {
		$class = get_called_class();
		if (!isset(self::$_has_many_to_many[$class])) self::$_has_many_to_many[$class] = array();
		self::$_has_many_to_many[$class][$key] = Hash::create(array('key' => $foreign_key, 'target' => $target));
	}
	
	public static function define() {
		// Override this funciton to declare relationships.
		// Always call: parent::define();
		$class = get_called_class();
		$class::$_defined = true;
	}
	
	public static function add($data = array()) {
		$class = get_called_class();
		$doc = new $class;
		$doc->_init_data($data);
		$doc->save();
		return $doc;
	}
	
	public static function find_one($query = array()) {
		$class = get_called_class();
		$collection = $class::_get_collection();
		$doc_data = $collection->findOne($class::_prepare_query($query));
		if ($doc_data) {
			$doc = new $class;
			$doc->_init_data($doc_data);
			return $doc;
		} else
			return null;
	}
	
	public static function find_many($query = array(), $sort = null, $limit = 0, $skip = 0) {
		$class = get_called_class();
		$collection = $class::_get_collection();
		$cursor = $collection->find($class::_prepare_query($query));
		if ($sort) {
			$cursor->sort($sort);
		}
		if ($skip) {
			$cursor->skip($skip);
		}
		if ($limit) {
			$cursor->limit($limit);
		}
		$docs = array();
		while($cursor->hasNext()) {
			$current_doc_data = $cursor->getNext();
			if ($current_doc_data) {
				$current_doc = new $class;
				$current_doc->_init_data($current_doc_data);
				$docs[] = $current_doc;
			}
		}
		return $docs;
	}
			
	public static function find_by_id($id) {
		if ($id) {
			$class = get_called_class();
			$collection = $class::_get_collection();
			$doc_data = $collection->findOne(array('_id' => new MongoID($id)));
			if ($doc_data) {
				$doc = new $class();
				$doc->_init_data($doc_data);
				return $doc;
			} else
				return null;
		}
	}
	
	public static function count($query = null) {
		$class = get_called_class();
		$collection = $class::_get_collection();
		$count = $collection->count($query);
		return $count;
	}

	public static function map_reduce($map, $reduce, $query = null, $options = array()) {
		$db = $GLOBALS['db'];
		$class = get_called_class();
		
		$command = array(
		    'mapreduce' => $class::_get_collection_name(),
		    'map' => $map,
		    'reduce' => $reduce
		);
		if ($query)
			$command['query'] = $query;
		
		foreach ($options as $name => $option)
			$command[$name] = $option;

		if (!isset($command['out']))
			$command['out'] = array('inline' => 1);
			
		$reduced = $db->command($command);
		
		if (isset($options['out']) && isset($options['out']['merge']))
			$collection = $options['out']['merge'];
		else if (isset($options['out']) && isset($options['out']['replace']))
			$collection = $options['out']['replace'];
		else if (isset($options['out']) && isset($options['out']['reduce']))
			$collection = $options['out']['reduce'];
		
		if (isset($collection) && is_string($collection))
			$results_collection = $collection;
		else if (isset($reduced['results']))
			$results = $reduced['results'];
		else
			return false;
		
		if (isset($results_collection))
			$results = $db->selectCollection($results_collection)->find();
		
		$data = array();
		foreach($results as $result) {
			$data[$result['_id']] = $result['value'];
		}
		return $data;
	}
	
	// non-static

	public function __construct() {
		$this->_data['date_created'] = time();
	}
	
	public function override_date_modified($date_modified) {
		$this->date_modified_override = (int)$date_modified;
	}
	
	public function _init_data($data) {
		foreach($data as $key => $value) {
			$this->_data[$key] = $value;
		}
	}
	
	
	// Relationship stuff
	
	protected function _relationship_get_one($key) {
		if (!isset($this->_data[$key]))
			return null;

		$class = get_called_class();
		$target = self::$_has_one[$class][$key];
		$id = $this->_data[$key];
		if (isset($this->_relationship_object_cache[$key])) {
			return $this->_relationship_object_cache[$key];
		} else {
			$response = $target::find_by_id($id);
			$this->_relationship_object_cache[$key] = $response;
			return $response;
		}
	}

	protected function _relationship_get_one_to_one($key) {
		if (!isset($this->_data[$key]))
			return null;
		
		$class = get_called_class();
		$info = self::$_has_one_to_one[$class][$key];
		$target = $info->target;
		$id = $this->_data[$key];
		if (isset($this->_relationship_object_cache[$key])) {
			return $this->_relationship_object_cache[$key];
		} else {
			$response = $target::find_by_id($id);
			$this->_relationship_object_cache[$key] = $response;
			return $response;
		}
	}

	protected function _relationship_get_many($key) {
		$class = get_called_class();
		$target = self::$_has_many[$class][$key];
		$array = array();
		if (isset($this->_data[$key]))
			$array = $this->_data[$key];
		return new MongoModel_OneToManyRelationship($array, $this, $key, $target);
	}

	protected function _relationship_get_many_to_many($key) {
		$class = get_called_class();
		$info = self::$_has_many_to_many[$class][$key];
		$array = array();
		if (isset($this->_data[$key]))
			$array = $this->_data[$key];
		return new MongoModel_ManyToManyRelationship($array, $this, $key, $info->target, $info->key);
	}
	
	public function _relationship_set_one($key, $value) {
		if (is_string($value) || is_bool($value) || is_numeric($value) || is_null($value))
			$this->_data[$key] = $value;
		else if ($value instanceof MongoModel)
			$this->_data[$key] = $value->__get('id');
		else
			return false;
	}
	
	public function _relationship_set_one_to_one($key, $value, $non_reciprocal = false) {
		$class = get_called_class();
		$info = self::$_has_one_to_one[$class][$key];

		// unset old relationship first
		$old_object = $this->_relationship_get_one_to_one($key);
		if (!$non_reciprocal && $old_object instanceof MongoModel) {
			$old_object->_relationship_set_one_to_one($info->key, null, true);
			$old_object->save(true); // force saving in case a validation wouldn't allow for this. can't have dangling relationships
		}
		
		if (is_string($value) || is_bool($value) || is_numeric($value) || is_null($value)) {
			$this->_data[$key] = $value;
		} else if ($value instanceof MongoModel) { // this is where the magic happens
			$this->_data[$key] = $value->__get('id');
			
			if (!$non_reciprocal) { // set the other side of the relationship
				$value->_relationship_set_one_to_one($info->key, $this, true);
			}
		} else {
			return false;
		}
		
		// TODO: figure out a way where this isn't necessary. ideally, keep track of deltas and objects to save and add them to a save pool.
		$this->save();
	}
	
	
	// Helpers

	public function array_push($key, $value) {
		if (!isset($this->_data[$key]) || !is_array($this->_data[$key]))
			$this->_data[$key] = array();
		array_push($this->_data[$key], $value);
	}
	
	public function array_unset_value($key, $value) {
		if (isset($this->_data[$key]) || is_array($this->_data[$key])) {
			$array = $this->_data[$key];
			foreach ($array as $a_key => $a_value) {
				if ($a_value == $value)
					unset($array[$a_key]);
			}
			$this->_data[$key] = $array;
		}
	}
	
	/**
	 * Returns an array of properties, as requested in the keys param.
	 * Note: this will fetch relationships.
	 * 
	 * @param	array $keys of keys
	 * @return	array of key-value pairs
	 * @author Kenneth Ballenegger
	 */
	public function extract_keys($keys) {
		if (!is_array($keys)) return array();
		
		$array = array();
		foreach ($keys as $key) {
			$val = $this->__get($key);
			if ($val)
				$array[$key] = $val;
		}
		return $array;
	}

	// only works one level deep
	public function is_set($key) {
		return (!empty($this->_data[$key]) ? true : false);
	}
	
	public function dealloc($key) { // Because I can't call it unset(). PHP core lib is a piece of shit. Dammit! 
		unset($this->_data[$key]);
	}
	
	public function __toArray() {
		$response = $this->_data;
		unset($response['_id']);
		$response['id'] = $this->id;
		return $response;
	}
	
	
	// Accessors
	
	public function __get($key) {
		$class = get_called_class();
		
		if ($key == 'id' && isset($this->_data['_id'])) {
			return $this->_data['_id']->__toString();
		} else if ($key == '_errors') {
			return $this->_errors;
		} else if ($key == 'validates') { // property -> function mapping
			return $class::validates();
		} else if (isset(self::$_has_one[$class][$key])) {
			return $this->_relationship_get_one($key);
		} else if (isset(self::$_has_one_to_one[$class][$key])) {
			return $this->_relationship_get_one_to_one($key);
		} else if (isset(self::$_has_many[$class][$key])) {
			return $this->_relationship_get_many($key);
		} else if (isset(self::$_has_many_to_many[$class][$key])) {
			return $this->_relationship_get_many_to_many($key);
		} else if (isset($this->_data[$key])) {
			return $this->_data[$key];
		} else
			return null;
	}
	public function __set($key, $value) {
		$class = get_called_class();

		if ($key == '_errors') {
			return;
		} else if (isset(self::$_has_one[$class][$key])) {
			return $this->_relationship_set_one($key, $value);
		} else if (isset(self::$_has_one_to_one[$class][$key])) {
			return $this->_relationship_set_one_to_one($key, $value);
		} else if (isset(self::$_has_many[$class][$key])) {
			return false;
		} else if (isset(self::$_has_many_to_many[$class][$key])) {
			return false;
		} else if ($value instanceof MongoModel) {
			$this->_data[$key] = $value->__get('id');
		} else {
			$this->_data[$key] = $value;
		}
		if (MONGOMODEL_SAVE_IMPLICITLY)
			$this->save();
	}
	
	public function delete() {
		$class = get_called_class();
		$collection = self::_get_collection($class);
		if (isset($this->_data['_id'])) {
			return $collection->remove(array('_id' => $this->_data['_id']), array('justOne'));
		} else {
			return false;
		}
	}
	
	public function save($force = false) {
		if ($this->validates() || $force) {
			$class = get_called_class();
			$collection = self::_get_collection($class);
			if ($this->date_modified_override)
				$this->_data['date_modified'] = $this->date_modified_override;
			else
				$this->_data['date_modified'] = time();
			$collection->save($this->_data);
			return true;
		} else {
			return false;
		}
	}
	
	
	// Caching
	
	/**
	 * Read a key from the cache
	 *
	 * @param string $key 
	 * @return MongoModel
	 * @author Kenneth Ballenegger
	 */
	protected static function _cache_get($key) {
		
		$cache = isset($GLOBALS['cache']) ? $GLOBALS['cache'] : null;
		
		if (!$cache)
			return false;
		
		return $cache->get($key);
	}
	
	/**
	 * Write an object to the cache
	 *
	 * @param mixed $data 
	 * @param string $key 
	 * @return bool
	 * @author Kenneth Ballenegger
	 */
	protected static function _cache_set($data, $key) {

		$cache = isset($GLOBALS['cache']) ? $GLOBALS['cache'] : null;
		
		if (!$cache)
			return false;
		
		return $cache->set($key, $data, MONGOMODEL_CACHE_EXPIRATION);
	}
	
	/**
	 * Remove a cached key
	 *
	 * @param string $key 
	 * @return mixed - cached value
	 * @author Kenneth Ballenegger
	 */
	protected static function _cache_delete($key) {
		
		$cache = isset($GLOBALS['cache']) ? $GLOBALS['cache'] : null;
		
		if (!$cache)
			return false;
		
		return $cache->delete($key);
	}
	
	/**
	 * Find one using cache (caches the query result and the data separately)
	 *
	 * WARNING: This cache is not automatically invalidated
	 * Note: Uses a covered index where possible
	 *
	 * @param array $query 
	 * @return MongoModel - may also return null / false
	 * @author Kenneth Ballenegger
	 */
	public static function find_one_cached($query = array()) {
		$class = get_called_class();
		$collection = $class::_get_collection();
		
		ksort($query);
		$hash = md5(serialize($query));
		$key = $class.'::find_one::'.$hash;

		$id = $class::_cache_get($key);
		if (!$id) {
			$id_data = $collection->findOne($class::_prepare_query($query), array('_id' => 1));

			if (!isset($id_data['_id']))
				$id = null;
			else if ($id_data['_id'] instanceof MongoID)
				$id = $id_data['_id']->__toString();
			else
				$id = $id_data['_id'];

			if ($id)
				$class::_cache_set($id, $key);
			else
				return false;
		}

		$object = $class::find_by_id_cached($id);
		return $object;
	}
	
	/**
	 * Find many using cache (caches the query result and the data separately)
	 *
	 * WARNING: This cache is not automatically invalidated
	 * Note: Uses a covered index where possible
	 *
	 * @param array $query 
	 * @return array of MongoModel - may also return null / false
	 * @author Kenneth Ballenegger
	 */
	public static function find_many_cached($query = array()) {
		$class = get_called_class();
		$collection = $class::_get_collection();
		
		ksort($query);
		$hash = md5(serialize($query));
		$key = $class.'::find_many::'.$hash;

		$ids = $class::_cache_get($key);
		if (!$ids || !is_array($ids)) {
			$ids_cursor = $collection->find($class::_prepare_query($query), array('_id' => 1));
			
			$ids = array();
			foreach ($ids_cursor as $id_data) {
				if (!isset($id_data['_id'])) {
					// do nothing
				} else if ($id_data['_id'] instanceof MongoID) {
					$ids[] = $id_data['_id']->__toString();
				} else {
					$ids[] = $id_data['_id'];
				}
			}
			
			if (!empty($ids))
				$class::_cache_set($ids, $key);
			else
				return false;
		}

		$objects = array();
		foreach ($ids as $id)
			$objects[] = $class::find_by_id_cached($id);
		return $objects;
	}
	
	/**
	 * Find by id using cache
	 *
	 * @param string $id 
	 * @return MongoModel - may also return null / false
	 * @author Kenneth Ballenegger
	 */
	public static function find_by_id_cached($id) {
		$class = get_called_class();
		$key = $class.'::id::'.$id;
		
		$data = $class::_cache_get($key);
		if (!$data) {
			$object = $class::find_by_id($id);
			if ($object) {
				$data = $object->_data;
				$class::_cache_set($data, $key);
			}
		} else {
			$object = new $class;
			$object->_init_data($data);
		}
		return $object;
	}
	
	/**
	 * Count query using cache
	 *
	 * @param array $query 
	 * @return int
	 * @author Kenneth Ballenegger
	 */
	public static function count_cached($query = array()) {
		$class = get_called_class();

		ksort($query);
		$hash = md5(serialize($query));
		$key = $class.'::count::'.$hash;

		$count = $class::_cache_get($key);
		if (!$count) {
			$count = $class::count($query);
			$class::_cache_set($count, $key);
		}
		return $count;
	}
	
	/**
	 * Saves the object to the cache, by id
	 *
	 * @return void
	 * @author Kenneth Ballenegger
	 */
	public function cache_save() {
		$class = get_called_class();
		$id = $this->id;
		$key = $class.'::id::'.$id;
		
		$class::_cache_set($this, $key);
	}
	

	// Validations
	
	final public function validates() {
		$this->_errors = array();
		$this->validate();
		if (!(count($this->_errors)))
			return true;
		else
			return false;
	}
	
	public function validate() {
		// override this to run validations
		// always call parent first
		
		// add any errors to $this->_errors[$key];
		return;
	}
		
	final public function validate_presence_of($key) {
		if (!empty($this->_data[$key])) {
			return true;
		} else {
			$this->_errors[$key] = 'must be present';
			return false;
		}
	}
	
	final public function validate_numericality_of($key) {
		if (!isset($this->_data[$key])) {
			$this->_errors[$key] = 'must be present';
			return false;
		} else if (!is_numeric($this->_data[$key])) {
			$this->_errors[$key] = 'must be be numeric';
			return false;
		} else {
			return true;
		}
	}

	final public function validate_uniqueness_of($key) {
		$class = get_called_class();
		$id = '';
		if (isset($this->_data['_id']))
			$id = $this->_data['_id'];
		
		if (empty($this->_data[$key])) {
			$this->_errors[$key] = 'must be present';
			return false;
		} else if ($class::count(array('_id' => array('$ne' => $id), $key => $this->_data[$key]))>0) {
			$this->_errors[$key] = 'must be be a valid '.$model;
			return false;
		} else {
			return true;
		}
	}

	final public function validate_presence_of_one($array) {
		foreach ($array as $key) {
			if (!empty($this->_data[$key])) {
				return true;
			}
		}
		
		// if got to here, all keys were empty
		$this->_errors[implode('_', $array)] = 'one of these keys must be present: '.implode(', ', $array);
		return false;
	}

	final public function validate_relationship($key, $model) {
		if (empty($this->_data[$key])) {
			$this->_errors[$key] = 'must be present';
			return false;
		} else if (!$model::find_by_id($this->_data[$key])) {
			$this->_errors[$key] = 'must be be a valid '.$model;
			return false;
		} else {
			return true;
		}
	}

	final public function validate_email($key) {
		if (empty($this->_data[$key])) {
			$this->_errors[$key] = 'must be present';
			return false;
		} else if (!Validator::email($this->_data[$key])) {
			$this->_errors[$key] = 'must be be a valid email address';
			return false;
		} else {
			return true;
		}
	}

	final public function validate_url($key) {
		if (empty($this->_data[$key])) {
			$this->_errors[$key] = 'must be present';
			return false;
		} else if (!preg_match('/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $this->_data[$key])) {
			$this->_errors[$key] = 'must be be a valid url';
			return false;
		} else {
			return true;
		}
	}
}
