<?php
class SpectrumChildRelationship extends SpectrumRelationship
{
	public $parentRelationship;
	public $parentRelationshipName;

	public function __construct($relationshipName, $modelType, $parentRelationshipName)
	{
		parent::__construct($relationshipName, $modelType);
		$this->parentRelationshipName = $parentRelationshipName;
		$this->parentRelationship = $modelType::getRelationship($parentRelationshipName);
	}

	public function getCondition()
	{
		$parentRelationship = $this->parentRelationship;
		if(is_a($parentRelationship, 'SpectrumFieldRelationship'))
		{
			return new SpectrumFieldCondition($parentRelationship->relationshipField, $parentRelationship->relationshipColumn, 'IN', null);
		}
		else if(is_a($parentRelationship, 'SpectrumPropertyRelationship'))
		{
			return new SpectrumPropertyCondition($parentRelationship->relationshipField, 'IN', null);
		}
	}
}