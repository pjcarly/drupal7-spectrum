<?php
class SpectrumModelQuery extends SpectrumQuery
{
	public $modelType;
	public function __construct($modelType)
	{
		parent::__construct($modelType::$entityType, $modelType::$bundle);
		$this->modelType = $modelType;
	}

	public function fetchCollection()
	{
		$entities = $this->fetch();
		return SpectrumCollection::forge($this->modelType, null, $entities);
	}

	public function fetchSingleModel()
	{
		$entity = $this->fetchSingle();

		$modelType = $this->modelType;
		return $modelType::forge($entity);
	}
}
