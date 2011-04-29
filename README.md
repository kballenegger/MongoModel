# MongoModel

MongoModel is a simple and lightweight ORM for MongoDB and PHP.

# Installation & Usage


## This is required for opening up the connection. Put this in your config.php or whatever.
    
    $mongo_host = 'localhost';
    $mongo_port = 27017;
    $mongo_database = 'test';
    $mongo = new Mongo($mongo_host.':'.$mongo_port);
    $GLOBALS['db'] = $mongo->{$mongo_database};
    
    require_once 'model.php';
    
    
## This is how you write a model.
    
    class Example extends MongoModel {
    	
    	// You're not required to define anything. MongoModel will detect what you need automatically.
    	// However if you need more control, there are more advanced examples in sample.php.
    }
    
    
## This is how you use the model.
    
### Creating objects
    
    // One method
    $example_1 = Example::add(array(
    		'textfield' => 'something',
    		'numberfield' => 1234,
    		'arrayfield' => array(1, 2, 3, 4),
    		'hashfield' => array('one' => 1, 'two' => 2)
    	));
    
    // Another method
    $example_2 = new Example;
    $example_2->textfield = 'something';
    $example_2->numberfield = 4567;
    $example_2->save();
    
    var_dump($example_2->_id); // `_id` contains a MongoID.
    var_dump($example_2->id); // `id` is the string representation of the Mongo ID.
    
### Querying objects
    
    // Find many
    $examples_3 = Example::find_many(array('textfield' => 'something'));
    // Use any type of Mongo query here. See Mongo docs for more examples.
    
    var_dump($examples_3); // Is an array of Example objects.
    
    // Find one
    $example_4 = Example::find_one(array('numberfield' => 4567));
    // If more than one match exist, the first one is returned.
    
    var_dump($example_4); // Is an Example object.
    
    $example_5 = Example::find_one(array('id' => $example_2->id, 'textfield' => 'something'));
    // If you use `id` in a query, MongoModel will automatically translate it to `_id` as a MongoID object.
    
    // Find by ID
    $example_6 = Example::find_by_id($example_2->id);
    
### Modifying objects
    $example_6->textfield = 'something else'; 
    $example_6->save();

**Check out sample.php for more detailed examples.**

