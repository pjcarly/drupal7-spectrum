<?php
abstract class SpectrumCondition 
{
	public $fieldName;
	public $operator;
	public $value;

	public static $singleValueOperators = array('=', '<>', '>', '>=', '<', '<=', 'STARTS_WITH', 'CONTAINS');
	public static $multipleValueOperators = array('IN', 'NOT IN');

	public function __construct($fieldName, $operator, $value)
	{
		$this->fieldName = $fieldName;
		$this->operator = $operator;
		$this->value = $value;
	}

	public function validateValues()
	{
		if(is_array($this->value) && !in_array($this->operator, SpectrumCondition::$multipleValueOperators))
		{
			throw new SpectrumInvalidOperatorException();
		}
		else if(!is_array($this->value) && !in_array($this->operator, SpectrumCondition::$singleValueOperators))
		{
			throw new SpectrumInvalidOperatorException();
		}
	}

	public abstract function addQueryCondition($query);
}