<?php
class Yfs_Ecomm_Model_Customer_Api_V2 extends Mage_Customer_Model_Customer_Api_V2
{
	public function getCustomerCount()
    {
        $output = Mage::getResourceModel('customer/customer_collection')->getAllIds();
        return sizeof($output);
    }
    public function getCustomerList($customerId = null,$limit = null, $filters)
    {
        if(!$customerId)
            $customerId = 0;
        if(!$limit)
            $limit = 100;
        $collection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*')->addFieldToFilter('entity_id',array('gt' => $customerId))->setPageSize($limit);
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if (isset($this->_mapAttributes[$field])) {
                        $field = $this->_mapAttributes[$field];
                    }

                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }

        $result = array();
        foreach ($collection as $customer) {
            $data = $customer->toArray();
            $row  = array();

            foreach ($this->_mapAttributes as $attributeAlias => $attributeCode) {
                $row[$attributeAlias] = (isset($data[$attributeCode]) ? $data[$attributeCode] : null);
            }

            foreach ($this->getAllowedAttributes($customer) as $attributeCode => $attribute) {
                if (isset($data[$attributeCode])) {
                    $row[$attributeCode] = $data[$attributeCode];
                }
            }
            
            foreach($customer->getAddresses() as $address)
            {
                $row['addresses'][] = $address->getData();
            }
            $result[] = $row;
        }
        return $result;
    }
    public function getAllowedAttributes($entity, array $filter = null)
    {
        $attributes = $entity->getResource()
                        ->loadAllAttributes($entity)
                        ->getAttributesByCode();
        $result = array();
        foreach ($attributes as $attribute) {
            if ($this->_isAllowedAttribute($attribute, $filter)) {
                $result[$attribute->getAttributeCode()] = $attribute;
            }
        }
        return $result;
    }
    protected function _isAllowedAttribute($attribute, array $filter = null)
    {
        if (!is_null($filter)
            && !( in_array($attribute->getAttributeCode(), $filter)
                  || in_array($attribute->getAttributeId(), $filter))) {
            return false;
        }
        return !in_array($attribute->getFrontendInput(), $this->_ignoredAttributeTypes)
               && !in_array($attribute->getAttributeCode(), $this->_ignoredAttributeCodes);
    }
}
?>