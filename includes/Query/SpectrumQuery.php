<?php
class SpectrumQuery
{
    private $bundle;
    private $entityType;

    public $conditions = array();
    public $orders = array();

    public function __construct($entityType, $bundle)
    {
        $this->bundle = $bundle;
        $this->entityType = $entityType;
    }

    public function addCondition($condition)
    {
        if(is_a($condition, 'SpectrumCondition'))
        {
            $this->conditions[] = $condition;
        }
        else
        {
            throw new SpectrumUnsupportedClassException();
        }
    }

    public function addOrder($order)
    {
        if(is_a($order, 'SpectrumOrder'))
        {
            $this->orders[] = $order;
        }
        else
        {
            throw new SpectrumUnsupportedClassException();
        }
    }

    public function getEntityFieldQuery()
    {
        if(variable_get('spectrum_development_mode', true))
        {
            $this->validateQuery();
        }

        $query = new EntityFieldQuery();
        $query->entityCondition('entity_type', $this->entityType);

        if(!empty($this->bundle))
        {
            $query->entityCondition('bundle', $this->bundle);
        }

        foreach($this->conditions as $condition)
        {
            $condition->addQueryCondition($query);
        }

        foreach($this->orders as $order)
        {
            $order->addQueryOrder($query);
        }

        return $query;
    }

    public function fetch()
    {
        $query = $this->getEntityFieldQuery();
        $result = $query->execute();

        return empty($result[$this->entityType]) ? array() : entity_load($this->entityType, array_keys($result[$this->entityType]));
    }

    public function fetchSingle()
    {
        $query = $this->getEntityFieldQuery();
        $result = $query->execute();

        if(empty($result[$this->entityType]))
        {
            return null;
        }
        else
        {
            $keys = array_keys($result[$this->entityType]);
            $id = array_shift($keys);
            return entity_load_single($this->entityType, $id);
        }
    }

    public function validateQuery()
    {
        // This function validates the existance of the entity type, the bundle, the fields and the columns on the fields
        $entityInfo = entity_get_info();

        if(!array_key_exists($this->entityType, $entityInfo))
        {
            dpm($entityInfo);
            throw new SpectrumInvalidEntityException('Entity '.$this->entityType.' does not exist');
        }
        if(!empty($this->bundle) && !array_key_exists($this->bundle, $entityInfo[$this->entityType]['bundles']))
        {
            dpm($entityInfo[$this->entityType]['bundles']);
            throw new SpectrumInvalidBundleException('Bundle '.$this->bundle.' does not exist for entity '.$this->entityType);
        }

        foreach($this->conditions as $condition)
        {
            if(is_a($condition, 'SpectrumPropertyCondition'))
            {
                $this->validateField('Property', $condition->fieldName);
            }
            else if(is_a($condition, 'SpectrumFieldCondition'))
            {
                $this->validateField('Field', $condition->fieldName, $condition->column);
            }
        }

        foreach($this->orders as $order)
        {
            if(is_a($order, 'SpectrumPropertyOrder'))
            {
                $this->validateField('Property', $order->fieldName);
            }
            else if(is_a($order, 'SpectrumFieldOrder'))
            {
                $this->validateField('Field', $order->fieldName, $order->column);
            }
        }
    }

    public function validateField($type, $fieldName, $column = null)
    {
        $fieldsInfo = field_info_instances($this->entityType, $this->bundle);

        switch($type)
        {
            case 'Property':
                $entityInfo = entity_get_info();

                if(!in_array($fieldName, $entityInfo[$this->entityType]['schema_fields_sql']['base table']))
                {
                    dpm($entityInfo[$this->entityType]['schema_fields_sql']['base table']);
                    throw new SpectrumInvalidFieldException('Property '.$fieldName. ' does not exist for bundle '.$this->bundle.' on entity type '.$this->entityType);
                }
            break;
            case 'Field':
                $fieldInfo = field_info_field($fieldName);

                if(!array_key_exists($fieldName, $fieldsInfo))
                {
                    dpm($fieldsInfo);
                    throw new SpectrumInvalidFieldException('Field '.$fieldName. ' does not exist for bundle '.$this->bundle.' on entity type '.$this->entityType);
                }

                if(!array_key_exists($column, $fieldInfo['columns']))
                {
                    dpm($fieldInfo['columns']);
                    throw new SpectrumInvalidFieldException('Column '.$column.' of field '.$fieldName. ' does not exist for bundle '.$this->bundle.' on entity type '.$this->entityType);
                }
            break;
        }
    }
}
