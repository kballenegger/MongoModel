# MongoModel

MongoModel is a simple and lightweight ORM for MongoDB and PHP.

Like Mongo, it is schema-less. It lets you build native PHP model objects, while automatically taking care of Mongo persistence for you. It also takes care of tricky common problems for you: *relationships*,  *caching* and *validations*.

# Installation & Usage


### Setup

This is required for opening up the connection. Put this in your config.php or whatever.
    
    $mongo_host = 'localhost';
    $mongo_port = 27017;
    $mongo_database = 'test';
    $mongo = new Mongo($mongo_host.':'.$mongo_port);
    $GLOBALS['db'] = $mongo->{$mongo_database};
    
    require_once 'model.php';
    
    
This is how you write a model.
    
    class Example extends MongoModel {
    	
    	// You're not required to define anything. MongoModel will detect what you need automatically.
    	// However if you need more control, there are more advanced examples in sample.php.
    }
    
    
### Usage

#### Creating objects
    
    // Another method
    $example_1 = new Example;
    $example_1->textfield = 'something';
    $example_1->numberfield = 4567;
    $example_1->save();
    
    var_dump($example_1->_id); // `_id` contains a MongoID.
    var_dump($example_1->id); // `id` is the string representation of the Mongo ID.
    
#### Querying objects
    
    // Find many
    $examples_2 = Example::find_many(array('textfield' => 'something'));
    // Use any type of Mongo query here. See Mongo docs for more examples.
    
    var_dump($examples_2); // Is an array of Example objects.
    
    // Find one
    $example_3 = Example::find_one(array('numberfield' => 4567));
    // If more than one match exist, the first one is returned.
    
    var_dump($example_3); // Is an Example object.
    
    $example_4 = Example::find_one(array('id' => $example_1->id, 'textfield' => 'something'));
    // If you use `id` in a query, MongoModel will automatically translate it to `_id` as a MongoID object.
    
    // Find by ID
    $example_5 = Example::find_by_id($example_1->id);
    
#### Modifying objects
    $example_5->textfield = 'something else'; 
    $example_5->save();


**Check out sample.php for more detailed examples.**

