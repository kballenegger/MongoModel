<?php

/*

	Written by Kenneth Ballenegger in late 2010, early 2011

	Copyright © Kenneth Ballenegger. All rights reserved.

*/

require_once dirname(__FILE__).'/lib/case_conversion.php';

abstract class MongoModel {
	
	// static
	public static $_collection = null;
	
	// non-static
	protected $_data = array();
	private $date_modified_override = null;
	public $_errors = array();
	
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
	
	protected static function _get_collection($class) {
		$db = $GLOBALS['db'];
		if (isset($class::$_collection) && $class::$_collection!=null) {
			$_collection = $class::$_collection;
		} else {
			$_collection = camel_case_to_underscore($class).'s';
		}
		return $db->{$_collection};
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
		$collection = self::_get_collection($class);
		$doc_data = $collection->findOne($class::_prepare_query($query));
		if ($doc_data) {
			$doc = new $class;
			$doc->_init_data($doc_data);
			return $doc;
		} else
			return null;
	}
	
	public static function find_many($query = array(), $sort = null, $limit = 0) {
		$class = get_called_class();
		$collection = self::_get_collection($class);
		$cursor = $collection->find($class::_prepare_query($query));
		if ($sort) {
			$cursor->sort($sort);
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
			$collection = self::_get_collection($class);
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
		$collection = self::_get_collection($class);
		$count = $collection->count($query);
		return $count;
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
		
	public function __get($key) {
		if ($key == 'id') {
			return $this->_data['_id']->__toString();
		} else if ($key == '_errors') {
			return $this->_errors;
		} else if ($key == 'validates') { // property -> function mapping
			$class = get_called_class();
			return $class::validates();
		} else if (isset($this->_data[$key])) {
			return $this->_data[$key];
		} else
			return null;
	}
	public function __set($key, $value) {
		if ($key == '_errors') {
			return;
		} else if ($value instanceof MongoModel) {
			$this->_data[$key] = $value->__get('id');
		} else {
			$this->_data[$key] = $value;
		}
		// Disabled for efficiency
		// $this->save();
	}
	
	// only works one level deep
	public function is_set($key) {
		return isset($this->_data[$key]) ? true : false ;
	}
	
	public function delete() {
		$class = get_called_class();
		$collection = self::_get_collection($class);
		$collection->remove(array('_id' => $this->_data['_id']), array('justOne'));
	}
	
	public function save() {
		if ($this->validates()) {
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
	
	public function __toArray() {
		$response = $this->_data;
		unset($response['_id']);
		$response['id'] = $this->id;
		return $response;
	}
	
	// Validations
	
	final public function validates() {
		$this->_errors = array();
		$this->validate();
		if (!(count($this->_errors)))
		return true;
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
}
