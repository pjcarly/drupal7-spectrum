<?php
class SpectrumFieldRelationship extends SpectrumParentRelationship
{
	public $relationshipColumn;

	public function __construct($relationshipName, $modelType, $relationshipField, $relationshipColumn)
	{
		parent::__construct($relationshipName, $modelType, $relationshipField);
		$this->relationshipColumn = $relationshipColumn;
	}

	public function getCondition()
	{
		$modelType = $this->modelType;
		return new SpectrumPropertyCondition($modelType::$idField, 'IN', null);
	}
}