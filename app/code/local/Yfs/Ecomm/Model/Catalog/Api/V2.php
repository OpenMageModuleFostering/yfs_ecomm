<?php
class Yfs_Ecomm_Model_Catalog_Api_V2 extends Mage_Catalog_Model_Product_Api_V2
{
	public function getProductCount()
	{
		$collection = Mage::getModel('catalog/product')->getCollection()->
                addAttributeToFilter('type_id', array('eq' => 'simple'))->
                addAttributeToFilter('status',array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED))->getSize();
        return $collection;
	}
	public function getProductList($productId = null,$limit = null,$filters = null, $store = null)
    {
        if(!$productId)
            $productId = 0;
        if(!$limit)
            $limit = 100;
        if(!$store)
            $storeId = Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();
        else
            $storeId = $this->_getStoreId($store);

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($storeId)->addFieldToFilter('entity_id',array('gt' => $productId))
            ->addAttributeToSelect('name')->setPageSize($limit);

        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if (isset($this->_filtersMap[$field])) {
                        $field = $this->_filtersMap[$field];
                    }

                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }

        $result = array();

        foreach ($collection as $product1) {
            $parentId = Null;
            $parentSku = Null;
            $product = Mage::helper('catalog/product')->getProduct($product1->getId(), $storeId);
            if($product->getTypeId() == "simple")
                $parentId = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
            if(isset($parentId[0]))
            {
                $model = Mage::helper('catalog/product')->getProduct($parentId[0], $storeId);
                $parentSku = $model->getSku();
            }
            $result[] = array( // Basic product data
                'product_id'    => $product->getId(),
                'sku'           => $product->getSku(),
                'name'          => $product->getName(),
                'short_description' => $product->getShortDescription(),
                'price'         => $product->getFinalPrice(),
                'qty'           => $product->getStockItem()->getQty(),
                'set'           => $product->getAttributeSetId(),
                'type'          => $product->getTypeId(),
                'created_at'    => $product->getCreatedAt(),
                'category_ids'  => $product->getCategoryIds(),
                'parent_sku'     => $parentSku
            );
        }

        return $result;
    }    
    public function updateProduct($sku, $price = null, $quantity = null)
    {
        $product1 = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        if($product1)
        {
            $product = Mage::getModel('catalog/product')->load($product1->getId());
            if($product)
            {       
                $product->setPrice($price);         
                $stockItem = $product->getStockItem();
                $stockItem->setData('qty', $quantity);
                if($quantity > 0)
                    $stockItem->setData('is_in_stock', '1');
                if($stockItem->save() && $product->save())
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
        }       
        else
        {
            return false;
        }
    }
    protected function _getStoreId($store = null)
    {
        if (is_null($store)) {
            $store = ($this->_getSession()->hasData($this->_storeIdSessionField)
                        ? $this->_getSession()->getData($this->_storeIdSessionField) : 0);
        }

        try {
            $storeId = Mage::app()->getStore($store)->getId();
        } catch (Mage_Core_Model_Store_Exception $e) {
            $this->_fault('store_not_exists');
        }

        return $storeId;
    }
    public function extensionEnabled()
    {
        return Mage::getStoreConfig('ecomm/ecomm/allow');
    }
}
?>