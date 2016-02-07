<?php
abstract class SpectrumModel
{
    public static $entityType;
    public static $bundle;
    public static $idField;

    public static $relationships = array();
    public static $relationshipsSet = false;
    public static $keyIndex = 1;

    public $entity;
    public $key;

    public $parents = array();
    public $children = array();

    public function __construct($entity)
    {
        if(is_array($entity) && !is_a($entity, 'SpectrumModel'))
        {
            throw new SpectrumInvalidTypeException();
        }

        $this->entity = $entity;
        $id = $this->getId();

        if(isset($id))
        {
            $this->key = $id;
        }
        else
        {
            $this->key = static::getNextKey();
        }
    }

    public function save($relationshipName = NULL)
    {
      if(empty($relationshipName))
      {
        entity_save(static::$entityType, $this->entity);
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
            $relationship = static::getRelationship($relationshipName);

            $relationshipModelType = $relationship->modelType;
            $relationshipModelQuery = $relationshipModelType::getModelQuery();
            $relationshipCondition = $relationship->getCondition();

            if(is_a($relationship, 'SpectrumParentRelationship'))
            {
                $parentId = $this->getParentId($relationship);

                if(!empty($parentId))
                {
                    // we set the parent ids in the condition, and fetch the collection of parents
                    $relationshipCondition->value = $parentId;
                    $relationshipCondition->operator = '=';
                    $relationshipModelQuery->addCondition($relationshipCondition);

                    $parentModel = $relationshipModelQuery->fetchSingleModel();

                    if(!empty($parentModel))
                    {
                        $this->put($relationship, $parentModel);

                        // now we musnt forget to put the model as child on the parent for circular references
                        $childRelationship = $relationshipModelType::getChildRelationshipForParentRelationship($relationship);
                        if(!empty($childRelationship))
                        {
                           $parentModel->put($childRelationship, $this);
                        }
                    }
                }
            }
            else if(is_a($relationship, 'SpectrumChildRelationship'))
            {
                $id = $this->getId();
                if(!empty($id))
                {
                    $relationshipCondition->value = array($id);
                    $relationshipModelQuery->addCondition($relationshipCondition);

                    $childCollection = $relationshipModelQuery->fetchCollection();

                    foreach($childCollection->models as $childModel)
                    {
                        $this->put($relationship, $childModel);
                        $childModel->put($relationship->parentRelationship, $this);
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

    public function get($relationshipName)
    {
        $firstRelationshipNameIndex = strpos($relationshipName, '.');

        if(empty($firstRelationshipNameIndex))
        {
            $relationship = static::getRelationship($relationshipName);

            if(is_a($relationship, 'SpectrumParentRelationship'))
            {
                return $this->getParent($relationship);
            }
            else if(is_a($relationship, 'SpectrumChildRelationship'))
            {
                if(array_key_exists($relationship->relationshipName, $this->children))
                {
                    return $this->children[$relationship->relationshipName];
                }
            }
        }
        else
        {
            $firstRelationshipName = substr($relationshipName, 0,  $firstRelationshipNameIndex);
            $firstRelationshipGet = $this->get($firstRelationshipName);
            $newRelationshipName = substr($relationshipName, $firstRelationshipNameIndex+1);

            return $firstRelationshipGet->get($newRelationshipName);
        }

        return null;
    }

    public function getId()
    {
        $idField = static::$idField;
        if(!empty($this->entity->$idField))
        {
            return $this->entity->$idField;
        }
        else
        {
            throw new SpectrumModelNotPersistedException();
        }
    }

    public function getParentId($relationship)
    {
        $entity = $this->entity;

        $fieldName = $relationship->relationshipField;
        if(is_a($relationship, 'SpectrumPropertyRelationship') || is_a($relationship, 'SpectrumEntityRelationship'))
        {
            return empty($entity->$fieldName) ? null : $entity->$fieldName;
        }
        else if(is_a($relationship, 'SpectrumFieldRelationship'))
        {
            $field = $entity->$fieldName;
            return empty($field[LANGUAGE_NONE][0][$relationship->relationshipColumn]) ? null : $field[LANGUAGE_NONE][0][$relationship->relationshipColumn];
        }
        else
        {
            throw new SpectrumInvalidRelationshipTypeException('Only Parent relationships allowed');
        }
    }

    public function isParentOf($model, $relationship)
    {
        $parentId = $model->getParentId($relationship);
        $id = $this->getId();
        return !empty($parentId) && !empty($id) && $parentId == $id;
    }

    public function getParent($relationship)
    {
        $parents = $this->parents;
        if(array_key_exists($relationship->relationshipName, $parents))
        {
            return $parents[$relationship->relationshipName];
        }
        else
        {
            return null;
        }
    }

    public function put($relationship, $model)
    {
        if($model != null && is_a($model, 'SpectrumModel'))
        {
            if(is_a($relationship, 'SpectrumParentRelationship'))
            {
                if(!array_key_exists($relationship->relationshipName, $this->parents))
                {
                    $this->parents[$relationship->relationshipName] = $model;
                }
            }
            else if(is_a($relationship, 'SpectrumChildRelationship'))
            {
                if(!array_key_exists($relationship->relationshipName, $this->children))
                {
                    $this->children[$relationship->relationshipName] = SpectrumCollection::forge($relationship->modelType);
                }

                $this->children[$relationship->relationshipName]->put($model);
            }
        }
    }

    public function debugEntity()
    {
        $values = array();
        foreach ($this->entity->getPropertyInfo() as $key => $val)
        {
            $values[$key] = $this->entity->$key->value();
        }

        dpm($values);
    }

    public static function hasRelationship($relationshipName)
    {
        return array_key_exists($relationshipName, static::$relationships);
    }

    public static function getNextKey()
    {
        return 'PLH'.(static::$keyIndex++);
    }

    public static function createNew()
    {
        if(!empty(static::$bundle))
        {
            $entity = entity_create(static::$entityType, array('type' => static::$bundle));
        }
        else
        {
            $entity = entity_create(static::$entityType);
        }
        return static::forge($entity);
    }

    public static function forge($entity = null, $id = null)
    {
        if(!empty($id))
        {
            $query = static::getModelQuery();

            // we dont know the name of the id field, so we must get it from entity info
            $entityInfo = entity_get_info(static::$entityType);

            // add a condition on the id
            $query->addCondition(new SpectrumPropertyCondition(static::$idField, '=', $id));
            $model = $query->fetchSingleModel();

            if(!empty($model))
            {
                return $model;
            }
        }

        // TODO, think of considerations, always create a new node when we have an empty entity?
        if(empty($entity))
        {
            $values = array();
            if(!empty(static::$bundle))
            {
                $values['type'] = static::$bundle;
            }

            $entity = entity_create(static::$entityType, $values);

        }

        return new static($entity);
    }

    public static function getModelQuery()
    {
        return new SpectrumModelQuery(get_called_class());
    }

    public static function getQuery()
    {
        return new SpectrumQuery(static::$entityType, static::$bundle);
    }

    public static function getChildRelationshipForParentRelationship($parentRelationship)
    {
        $relationships = static::getRelationships();
        $childRelationship = null;
        foreach($relationships as $relationship)
        {
            if(is_a($relationship, 'SpectrumChildRelationship'))
            {
                if($relationship->parentRelationship === $parentRelationship)
                {
                    $childRelationship = $relationship;
                    break;
                }
            }
        }

        return $childRelationship;
    }

    public static function setRelationships(){}

    public static function getRelationship($relationshipName)
    {
        if(!static::$relationshipsSet)
        {
            static::setRelationships();
            static::$relationshipsSet = true;
        }

        if(static::hasRelationship($relationshipName))
        {
            return static::$relationships[$relationshipName];
        }
        else
        {
            throw new SpectrumRelationshipNotDefinedException('Relationship '.$relationshipName.' does not exist');
        }
    }

    public static function getRelationships()
    {
        if(!static::$relationshipsSet)
        {
            static::setRelationships();
            static::$relationshipsSet = true;
        }

        return static::$relationships;
    }

    public static function addRelationship($relationship)
    {
        if(is_a($relationship, 'SpectrumRelationship'))
        {
            static::$relationships[$relationship->relationshipName] = $relationship;
        }
    }

    public function __get($property)
    {
  		if (property_exists($this, $property))
  		{
  			return $this->$property;
  		}
  		else if(array_key_exists($property, $this->parents)) // lets check for pseudo properties
  		{
  			return $this->parents[$property];
  		}
  		else if(array_key_exists($property, $this->children)) // lets check for pseudo properties
  		{
  			return $this->children[$property];
  		}
  	}

    public function beforeInsert(){}
    public function afterInsert(){}
    public function beforeUpdate(){}
    public function afterUpdate(){}
    public function beforeDelete(){}
}
