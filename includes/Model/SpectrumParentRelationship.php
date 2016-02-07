<?php
abstract class SpectrumParentRelationship extends SpectrumRelationship
{
	public $relationshipField;

	public function __construct($relationshipName, $modelType, $relationshipField)
	{
		parent::__construct($relationshipName, $modelType);
		$this->relationshipField = $relationshipField;
	}
}