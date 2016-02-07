<?php
abstract class SpectrumRelationship 
{
	public $modelType;
	public $relationshipName;

	public function __construct($relationshipName, $modelType)
	{
		$this->modelType = $modelType;
		$this->relationshipName = $relationshipName;
	}

	public abstract function getCondition();
}