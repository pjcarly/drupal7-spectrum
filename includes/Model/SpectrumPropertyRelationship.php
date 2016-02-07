<?php
class SpectrumPropertyRelationship extends SpectrumParentRelationship
{
	public function getCondition()
	{
		$modelType = $this->modelType;
		return new SpectrumPropertyCondition($modelType::$idField, 'IN', null);
	}
}