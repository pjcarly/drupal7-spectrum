<?php
class SpectrumEntityCondition extends SpectrumCondition
{
	public function addQueryCondition($query) 
	{
		$query->entityCondition($this->fieldName, $this->value, $this->operator);
	}
}