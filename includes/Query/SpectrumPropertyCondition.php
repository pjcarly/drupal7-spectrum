<?php
class SpectrumPropertyCondition extends SpectrumCondition
{
	public function addQueryCondition($query) 
	{
		$query->propertyCondition($this->fieldName, $this->value, $this->operator);
	}
}