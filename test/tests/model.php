<?php

require_once dirname(__FILE__).'/../../lib/model.php';

class TestModel extends MongoModel {

	public function validate() {
		parent::validate();
		$this->validate_presence_of('required_field');
	}
}

class RelationshipTestModel extends MongoModel {
	
	public function validate() {
		parent::validate();
		$this->validate_relationship('target', 'TestModel');
	}
}

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

	public static function test_relationships() {
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
}