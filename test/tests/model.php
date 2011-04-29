<?php

require_once dirname(__FILE__).'/../../lib/model.php';

class TestModel extends MongoModel {
	
	public function validate() {
		parent::validate();
		$this->validate_presence_of('required_field');
	}
}

class RelationshipTestModel extends MongoModel {

	public static function define() {
		self::has_one('target', 'TestModel');
		self::has_many('many_targets', 'TestModel');
		self::has_many_to_many('many_to_many_targets', 'RelationshipTestModel', 'many_to_many_targets');
	}
	
	public function validate() {
		parent::validate();
		//$this->validate_relationship('target', 'TestModel');
	}
} RelationshipTestModel::define();

class ModelTester extends Tester {
		
	public static function test_adding() {
		$model = new TestModel;
		$model->required_field = 'whatever';
		$model->save();
		
		if (!$model->is_set('_id') && !TestModel::find_by_id($model->id))
			throw new TestError('Problem adding new object.');
		
		// clean up
		$model->delete();
	}
	
	public static function test_validations() {
		self::depends_on('adding');

		$model = new TestModel;
		$model->bs_field = 'whatever';
		
		if ($model->validates)
			throw new TestError('Validation passes when it should not.');

		$model->required_field = 'whatever';
		if (!$model->validates)
			throw new TestError('Validation fails when it should not.');
		
		// clean up
		// model never saved, no need to clean up
	}

	public static function test_relationship_validation() {
		return;
		self::depends_on('adding');

		$model = new TestModel;
		$model->required_field = 'whatever';
		$model->save();
		
		$relationship_model = new RelationshipTestModel;
		
		if ($relationship_model->validates)
			throw new TestError('Validation passes when it should not.');
		
		$relationship_model->target = $model;
		$relationship_model->save();

		if ($relationship_model->target != $model->id)
			throw new TestError('Relationship auto-assignment failed.');
		
		if (!$relationship_model->validates)
			throw new TestError('Validation fails when it should not.');

		// clean up
		$model->delete();
		$relationship_model->delete();
	}
	
	public static function test_relationship_has_one() {
		self::depends_on('adding');
		
		$model = new TestModel;
		$model->required_field = 'whatever';
		$model->save();

		$relationship_model = new RelationshipTestModel;
		
		$relationship_model->target = $model;
		if (!$relationship_model->save())
			throw new TestError('Error saving RelationshipTestModel.');
		
		$relationship_model = RelationshipTestModel::find_by_id($relationship_model->id);

		if (!$relationship_model->target instanceof TestModel)
			throw new TestError('Error retrieving relationship target.'."\n".var_export($relationship_model->target, true));
		
		$model->delete();
		$relationship_model->delete();
	}
	
	public static function test_relationship_has_many() {
		self::depends_on('adding');
		self::depends_on('relationship_has_one');
		
		$model1 = new TestModel;
		$model1->required_field = 'whatever';
		$model1->save();

		$model2 = new TestModel;
		$model2->required_field = 'whatever';
		$model2->save();

		$relationship_model = new RelationshipTestModel;
		
		$relationship_model->many_targets->add($model1);
		$relationship_model->many_targets->add($model2);
		// relationship model implicitly saved
		
		$relationship_model = RelationshipTestModel::find_by_id($relationship_model->id);
		
		if (!$relationship_model->many_targets->contains($model1))
			throw new TestError('Relationship\'s `contains` method doesn\'t work.');
		
		$counter = 0;
		foreach($relationship_model->many_targets as $model) {
			$counter++;
			if (!$model instanceof TestModel)
				throw new TestError('Error retrieving one-to-many relationship target.'."\n".var_export($model, true));
		}
		
		if ($counter != 2)
			throw new TestError('One-to-many iterator did not iterate twice as expected.'."\n".'$counter = '.$counter);

		$relationship_model->many_targets->delete($model1);

		if ($relationship_model->many_targets->contains($model1))
			throw new TestError('Relationship deletion doesn\'t work.');
		
		$model1->delete();
		$model2->delete();
		$relationship_model->delete();
	}

	public static function test_relationship_has_many_to_many() {
		self::depends_on('adding');
		self::depends_on('relationship_has_one');
		self::depends_on('relationship_has_many');
		
		$relationship_model = new RelationshipTestModel;
		$relationship_model->save();

		$relationship_model2 = new RelationshipTestModel;
		$relationship_model2->save();
		$relationship_model3 = new RelationshipTestModel;
		$relationship_model3->save();
		
		$relationship_model->many_to_many_targets->add($relationship_model2);
		$relationship_model->many_to_many_targets->add($relationship_model3);
		// relationship models implicitly saved
		$relationship_model->save();
		
		$relationship_model = RelationshipTestModel::find_by_id($relationship_model->id);
		
		if (!$relationship_model->many_to_many_targets->contains($relationship_model2))
			throw new TestError('Many-to-Many Relationship\'s `contains` method doesn\'t work.');
		
		$counter = 0;
		foreach($relationship_model->many_to_many_targets as $model) {
			$counter++;
			if (!$model instanceof RelationshipTestModel)
				throw new TestError('Error retrieving many-to-many relationship target.'."\n".var_export($model, true));
		}
		
		if ($counter != 2)
			throw new TestError('Many-to-many iterator did not iterate twice as expected.'."\n".'$counter = '.$counter);

		$counter = 0;
		foreach($relationship_model2->many_to_many_targets as $model) { // should only be one
			$counter++;
			if (!$model instanceof RelationshipTestModel && $model->id != $relationship_model->id)
				throw new TestError('Error retrieving many-to-many relationship target.'."\n".var_export($model, true));
		}

		if ($counter != 1)
			throw new TestError('Many-to-many counter over $relationship_model2 did not iterate only once as expected.'."\n".'$counter = '.$counter);
		
		$relationship_model->many_to_many_targets->delete($relationship_model2);

		if ($relationship_model2->many_to_many_targets->contains($relationship_model))
			throw new TestError('Reciprocal relationship deletion doesn\'t work.');
		
		$relationship_model2->delete();
		$relationship_model3->delete();
		$relationship_model->delete();
	}
}