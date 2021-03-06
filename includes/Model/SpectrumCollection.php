<?php
class SpectrumCollection
{
	private static $newKeyIndex = 0;

	public $modelType;
  public $models;
  public $originalModels;

  public $parentModel;

  public function __construct()
  {
  	$this->models = array();
  	$this->originalModels = array();
  }

	public function save($relationshipName = NULL)
	{
		if(empty($relationshipName))
		{
			foreach($this->models as $model)
			{
				$model->save();
			}
		}
		else
		{
			$this->get($relationshipName)->save();
		}
	}

  public function fetch($relationshipName)
  {
  	$lastRelationshipNameIndex = strrpos($relationshipName, '.');

  	if(empty($lastRelationshipNameIndex)) // relationship name without extra relationships
  	{
  		$modelType = $this->modelType;
    	$relationship = $modelType::getRelationship($relationshipName);

    	$relationshipModelType = $relationship->modelType;
			$relationshipModelQuery = $relationshipModelType::getModelQuery();
			$relationshipCondition = $relationship->getCondition();

    	if(is_a($relationship, 'SpectrumParentRelationship'))
    	{
	   		$parentIds = $this->getParentIds($relationship);
        if(!empty($parentIds))
        {
        	// we set the parent ids in the condition, and fetch the collection of parents
	        $relationshipCondition->value = $parentIds;
	        $relationshipModelQuery->addCondition($relationshipCondition);
	        $parentCollection = $relationshipModelQuery->fetchCollection();

	        // next loop all the current models, and put the fetched parents on each model, if eligible
	        if(!$parentCollection->isEmpty)
	        {
	        	foreach($this->models as $model)
	        	{
	        		$parentId = $model->getParentId($relationship);
	        		if($parentCollection->containsKey($parentId)) // we found a parentId lets put it
	        		{
	        			$parentModel = $parentCollection->getModel($parentId);
        				$model->put($relationship, $parentModel);

        				// now we musnt forget to put the model as child on the parent for circular references
        				$childRelationship = $relationshipModelType::getChildRelationshipForParentRelationship($relationship);
			            if(!empty($childRelationship))
			            {
			                $parentModel->put($childRelationship, $model);
			            }
	        		}
	        	}
	        }
	    	}
      }
      else if(is_a($relationship, 'SpectrumChildRelationship'))
      {
  			$childIds = $this->getChildIds();

  			if(!empty($childIds))
  			{
  				$relationshipCondition->value = $childIds;
  				$relationshipModelQuery->addCondition($relationshipCondition);

  				$childCollection = $relationshipModelQuery->fetchCollection();

  				foreach($this->models as $model)
  				{
  					foreach($childCollection->models as $childModel)
  					{
  						if($model->isParentOf($childModel, $relationship->parentRelationship))
  						{
  							$model->put($relationship, $childModel);
  							$childModel->put($relationship->parentRelationship, $model);
  						}
  					}
  				}
  			}
      }
  	}
  	else
  	{
  		$secondToLastRelationshipName = substr($relationshipName, 0, $lastRelationshipNameIndex);
  		$resultCollection = $this->get($secondToLastRelationshipName);
  		$lastRelationshipName = substr($relationshipName, $lastRelationshipNameIndex+1);
  		$resultCollection->fetch($lastRelationshipName);
  	}
  }

  public function getChildIds()
  {
  	$modelType = $this->modelType;
  	$models = $this->models;

		$ids = array();
		foreach($models as $model)
		{
			$id = $model->getId();
			$ids[$id] = $id;
		}

		return $ids;
  }

  public function getParentIds($relationship)
  {
  	$parentIds = array();

  	foreach($this->models as $model)
  	{
  		$parentId = $model->getParentId($relationship);
  		if(!empty($parentId))
  		{
  			$parentIds[$parentId] = $parentId;
  		}
  	}

  	return $parentIds;
  }

  public static function forge($modelType, $models = array(), $entities = array(), $ids = array(), $modelQuery = null)
  {
  	$collection = new SpectrumCollection();
  	$collection->modelType = $modelType;

		if(is_array($ids) && !empty($ids))
		{
			$entities = SpectrumCollection::fetchEntities($modelType, $ids);
		}

		if(is_array($entities) && !empty($entities))
		{
			$models = SpectrumCollection::getModels($modelType, $entities);
		}

		if(is_array($models) && !empty($models))
		{
			$collection->setModels($models);
		}

  	return $collection;
  }

  private static function fetchEntities($modelType, $ids)
  {
  	$query = new SpectrumQuery($modelType::$entityType, $modelType::$bundle);
  	$entityInfo = entity_get_info($modelType::$entityType);

  	$query->addCondition(new SpectrumPropertyCondition($modelType::$idField, 'IN', $ids));
  	return $query->fetch();
  }

  public function getEntities()
  {
  	$entities = array();
  	$modelType = $this->modelType;
  	$idField = $modelType::$idField;
  	foreach($this->models as $model)
  	{
  		$entity = $model->entity;
  		$entities[$entity->$idField] = $model->entity;
  	}

  	return $entities;
  }

  private static function getModels($modelType, $entities)
  {
  	$models = array();
  	foreach($entities as $entity)
  	{
  		$models[] = $modelType::forge($entity);
  	}
  	return $models;
  }

  private function setModels($models)
  {
  	foreach($models as $model)
		{
			$this->put($model);
		}
  }

  public function put($model)
  {
  	if(!is_a($model, $this->modelType))
		{
			throw new SpectrumInvalidTypeException('Wrong model type: '.$model);
		}

		if(!array_key_exists($model->key, $this->models))
		{
			$this->models[$model->key] = $model;
			$this->originalModels[$model->key] = $model;
		}
  }

	public function size()
	{
		return count($this->models);
	}

	public function isEmpty()
	{
		return empty($this->models);
	}

	public function containsKey($key)
	{
		return array_key_exists($key, $this->models);
	}

	public function getModel($key)
	{
		if($this->containsKey($key))
		{
			return $this->models[$key];
		}
		else
		{
			return null;
		}
	}

	public function get($relationshipName)
	{
		$resultCollection;
		$modelType = $this->modelType;

		$firstRelationshipNameIndex = strpos($relationshipName, '.');

		if(empty($firstRelationshipNameIndex))
		{
			$relationship = $modelType::getRelationship($relationshipName);
			$resultCollection = static::forge($relationship->modelType);

			if(is_a($relationship, 'SpectrumParentRelationship'))
    	{
				foreach($this->models as $model)
				{
					$parent = $model->getParent($relationship);
					if(!empty($parent))
					{
						$resultCollection->put($parent);
					}
				}
      }
      else if(is_a($relationship, 'SpectrumChildRelationship'))
      {
      	foreach($this->models as $model)
      	{
      		if(array_key_exists($relationship->relationshipName, $model->children))
      		{
      			$childCollection = $model->children[$relationship->relationshipName];
      			foreach($childCollection->models as $childmodel)
      			{
      				$resultCollection->put($childModel);
      			}
      		}
      	}
      }
		}
		else
		{
			$firstRelationshipName = substr($relationshipName, 0,  $firstRelationshipNameIndex);
			$newCollection = $this->get($firstRelationshipName);
			$newRelationshipName = substr($relationshipName, $firstRelationshipNameIndex+1);

			$resultCollection = $newCollection->get($newRelationshipName);
		}

		return $resultCollection;
	}

  public function __get($property)
  {
		if (property_exists($this, $property))
		{
			return $this->$property;
		}
		else // lets check for pseudo properties
		{
			switch($property)
			{
				case "size":
					return $this->size();
				break;
				case "isEmpty":
					return $this->isEmpty();
				break;
				case "entities":
					return $this->getEntities();
				break;
			}
		}
	}

	public function __set($property, $value)
	{
		switch($property)
		{
			case "models":
			case "originalModels":
			break;

			default:
				if(property_exists($this, $property))
				{
					$this->$property = $value;
				}
			break;
		}

		return $this;
	}
}
