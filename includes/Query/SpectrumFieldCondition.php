<?php
class SpectrumFieldCondition extends SpectrumCondition
{
	public $column;

	public function __construct($fieldName, $column, $operator, $value)
	{
		parent::__construct($fieldName, $operator, $value);
		$this->column = $column;
	}

	public function addQueryCondition($query) 
	{
		$query->fieldCondition($this->fieldName, $this->column, $this->value, $this->operator);
	}
}