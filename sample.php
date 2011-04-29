<?php

// This is required for opening up the connection. Put this in your config.php or whatever.

$mongo_host = 'localhost';
$mongo_port = 27017;
$mongo_database = 'test';
$mongo = new Mongo($mongo_host.':'.$mongo_port);
$GLOBALS['db'] = $mongo->{$mongo_database};

require_once 'model.php';


// This is how you write a model.

class Example extends MongoModel {
	
	// In this case will MongoModel will by default use the connection 'examples,' which it extrapolates from the class name.
	
	// You can, however, choose another collection by setting $_collection
	public static $_collection = 'test_collection';
	
	// Feel free to add any methods to the class.
	
	// MongoDB is schema-agnostic, so you do not need to define your schema. No migrations, woohoo!
	// Any requirements should be defined using validations.
	
	// Validations: implement the validate() method
	// Validations will be checked everytime save is called.
	public function validate() {
		parent::validate(); // Always call parent.
		
		// You can write your own validations here.
		// Test whatever you need, and if something goes wrong, add an error to $this->_errors[$key]
		
		if (!is_numeric($this->_data['numberfield']))
			$this->_errors['numberfield'] = 'numberfield must be numeric';
		
		// Or use one of the handy presets
		$this->validate_presence_of('textfield');
		// Check out also, validate_relationship.
	}
}


// This is how you use the model.

// Creating objects

// One method
$example_1 = Example::add(array(
		'textfield' => 'something',
		'numberfield' => 1234,
		'arrayfield' => array(1, 2, 3, 4),
		'hashfield' => array('one' => 1, 'two' => 2)
	)); // Saved to the database straight away
// Another method
$example_2 = new Example; // Not saved until save() is called
$example_2->textfield = 'something';
$example_2->numberfield = 4567;
$example_2->arrayfield = array(1, 2, 3, 4);
$example_2->hashfield = array('one' => 1, 'two' => 2);
$example_2->save();

var_dump($example_2->_id); // `_id` contains a MongoID.
var_dump($example_2->id); // `id` is the string representation of the Mongo ID.

// Querying objects

// Find many
$examples_3 = Example::find_many(array('textfield' => 'something')); // Use any type of Mongo query here. See Mongo docs for more examples.
var_dump($examples_3); // Is an array of Example objects.

// Find one
$example_4 = Example::find_one(array('numberfield' => 4567)); // If more than one match exist, the first one is returned.
var_dump($example_4); // Is an Example object.

$example_5 = Example::find_one(array('id' => $example_2->id, 'textfield' => 'something')); // If you use `id` in a query, MongoModel will automatically translate it to `_id` as a MongoID object.

// Find by ID
$example_6 = Example::find_by_id($example_2->id);

// Modifying objects
$example_6->textfield = 'something else';
$example_6->save();

// Relationships are automatically converted to ids.
$example_6->objectfield = $example_2;
var_dump($example_6->objectfield); // String id.

$example_7 = Example::find_by_id($example_6->objectfield); // To retrieve the object, use the finder.
var_dump($example_7);

