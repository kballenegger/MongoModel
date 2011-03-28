<?php

/*

	Written by Kenneth Ballenegger in late 2010, early 2011

	Copyright © Kenneth Ballenegger. All rights reserved.

*/

abstract class MongoModel {
	
	// static
	public static $_collection = null;
	
	// non-static
	protected $_data = array();
	private $date_modified_override = null;
	
	// static
	protected static function _replace_mongo_id_recursively(&$array) {
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
	
	public static function add($data = array()) {
		$class = get_called_class();
		$doc = new $class;
		$doc->_init_data($data);
		$doc->_save();
		return $doc;
	}
	
	public static function find_one($query = array()) {
		$db = $GLOBALS['db'];
		$class = get_called_class();
		$collection = $db->{$class::$_collection};
		$doc_data = $collection->findOne($class::_prepare_query($query));
		if ($doc_data) {
			$doc = new $class;
			$doc->_init_data($doc_data);
			return $doc;
		} else
			return null;
	}
	
	public static function find_many($query = array(), $sort = null, $limit = 0) {
		$db = $GLOBALS['db'];
		$class = get_called_class();
		$collection = $db->{$class::$_collection};
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
			$db = $GLOBALS['db'];
			$class = get_called_class();
			$collection = $db->{$class::$_collection};
			$doc_data = $collection->findOne(array('_id' => new MongoID($id)));
			if ($doc_data) {
				$doc = new $class();
				$doc->_init_data($doc_data);
				return $doc;
			} else
				return null;
		}
	}
		
	public static function find_many_not_id($id, $query=array(), $sort=null) {
		if ($id) {
			$query['_id'] = array('$ne' => new MongoID($id));
			return self::find_many($query, $sort);
		}
	}
	
	public static function count($query = null) {
		$db = $GLOBALS['db'];
		$class = get_called_class();
		$collection = $db->{$class::$_collection};
		$count = $collection->count($query);
		return $count;
	}
	
	// non-static

	public function __construct() {
		$this->_data['date_created'] = time();
	}
	
	public function override_date_modified($date_modified) {
		$this->date_modified_override = (int)$date_modified;
		$this->_save();
	}
	
	public function _init_data($data) {
		foreach($data as $key => $value) {
			$this->_data[$key] = $value;
		}
	}
		
	public function __get($key) {
		if (isset($this->_data[$key])) {
			return $this->_data[$key];
		} else if ($key=='id') {
			return $this->_data['_id']->__toString();
		} else
			return null;
	}
	public function __set($key, $value) {
		$this->_data[$key] = $value;
		$this->_save();
	}
	
	// only works one level deep
	public function is_set($key) {
		return isset($this->_data[$key]) ? true : false ;
	}
	
	public function delete() {
		$db = $GLOBALS['db'];
		$class = get_called_class();
		$collection = $db->{$class::$_collection};
		$collection->remove(array('_id' => $this->_data['_id']), array('justOne'));
	}
	
	protected function _save() {
		$db = $GLOBALS['db'];
		$class = get_called_class();
		$collection = $db->{$class::$_collection};
		if ($this->date_modified_override)
			$this->_data['date_modified'] = $this->date_modified_override;
		else
			$this->_data['date_modified'] = time();
		$collection->save($this->_data);
	}
	
	public function __toArray() {
		$response = $this->_data;
		unset($response['_id']);
		$response['id'] = $this->id;
		return $response;
	}
}
