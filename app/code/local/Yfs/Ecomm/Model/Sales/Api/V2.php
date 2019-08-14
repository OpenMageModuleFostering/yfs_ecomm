<?php
class Yfs_Ecomm_Model_Sales_Api_V2 extends Mage_Sales_Model_Order_Api_V2
{
    public function getOrderCount($date_from = null,$date_to = null)
    {
        $tableName = Mage::getSingleton('core/resource')->getTableName('sales_flat_order');

        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        if($date_from)
        {
            $query = "SELECT COUNT('increment_id') FROM ".$tableName." where created_at BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
        }
        else
        {
            $query = "SELECT COUNT('increment_id') FROM ".$tableName;
        }        
        $result = $readConnection->fetchAll($query); 
        return $result[0]["COUNT('increment_id')"];
    }

    public function getOrderList($fromDate = null,$toDate = null,$page = null, $limit = null, $resync = 1, $filters = null)
    {
        if(!$limit)
            $limit = 100;
        if(!$page)
            $page = 1;
        if(!$toDate)
            date_default_timezone_set('UTC');
            $toDate = date('Y-m-d H:i:s',strtotime("-2 min"));

        //TODO: add full name logic
        $billingAliasName = 'billing_o_a';
        $shippingAliasName = 'shipping_o_a';
        
        $collection = Mage::getModel("sales/order")->getCollection()
            ->addAttributeToSelect('*')
            ->addAddressFields()
            ->addExpressionFieldToSelect(
                'billing_firstname', "{{billing_firstname}}", array('billing_firstname'=>"$billingAliasName.firstname")
            )
            ->addExpressionFieldToSelect(
                'billing_lastname', "{{billing_lastname}}", array('billing_lastname'=>"$billingAliasName.lastname")
            )
            ->addExpressionFieldToSelect(
                'shipping_firstname', "{{shipping_firstname}}", array('shipping_firstname'=>"$shippingAliasName.firstname")
            )
            ->addExpressionFieldToSelect(
                'shipping_lastname', "{{shipping_lastname}}", array('shipping_lastname'=>"$shippingAliasName.lastname")
            )
            ->addExpressionFieldToSelect(
                    'billing_name',
                    "CONCAT({{billing_firstname}}, ' ', {{billing_lastname}})",
                    array('billing_firstname'=>"$billingAliasName.firstname", 'billing_lastname'=>"$billingAliasName.lastname")
            )
            ->addExpressionFieldToSelect(
                    'shipping_name',
                    'CONCAT({{shipping_firstname}}, " ", {{shipping_lastname}})',
                    array('shipping_firstname'=>"$shippingAliasName.firstname", 'shipping_lastname'=>"$shippingAliasName.lastname")
            )
            ->addAttributeToFilter('created_at', array('from' => $fromDate, 'to' => $toDate))->setPageSize($limit)->setCurPage($page);
        
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if (isset($this->_attributesMap['order'][$field])) {
                        $field = $this->_attributesMap['order'][$field];
                    }

                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }

        $result = array();

        foreach ($collection as $order) {
            $row = array();
            $row = $this->_getAttributes($order, 'order');
            $row['shipping_address'] = $this->_getAttributes($order->getShippingAddress(), 'order_address');
            $row['billing_address']  = $this->_getAttributes($order->getBillingAddress(), 'order_address');
            $row['items'] = array();

            foreach ($order->getAllItems() as $key => $item) {

                if ($item->getGiftMessageId() > 0) {
                    $item->setGiftMessage(
                        Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
                    );
                }
                $_product = Mage::getModel('catalog/product')->load($item->getProductId());
                $itemData = $this->_getAttributes($item, 'order_item');
                if($itemData['product_type'] == 'bundle')
                {
                    $options1 = unserialize($itemData['product_options']);
                    if($_product)
                        $options1['price_type'] = $_product->getPriceType()==1;
                    $options = json_encode($options1);                    
                    $itemData['product_options'] = $options;
                }
                if($itemData['product_type'] == 'configurable')
                {
                    $tempArray = $itemData;
                }
                if($itemData['product_type'] == 'simple' && $itemData['sku'] == $tempArray['sku'])
                {
                    $itemData['qty_canceled'] = ($itemData['qty_canceled'] <= 0) ? $tempArray['qty_canceled'] : $itemData['qty_canceled'];
                    $itemData['qty_invoiced'] = ($itemData['qty_invoiced'] <= 0) ? $tempArray['qty_invoiced'] : $itemData['qty_invoiced'];
                    $itemData['qty_refunded'] = ($itemData['qty_refunded'] <= 0) ? $tempArray['qty_refunded'] : $itemData['qty_refunded'];
                    $itemData['qty_shipped'] = ($itemData['qty_shipped'] <= 0) ? $tempArray['qty_shipped'] : $itemData['qty_shipped'];
                    $itemData['price'] = ($itemData['price'] <= 0) ? $tempArray['price'] : $itemData['price'];
                    $itemData['base_price'] = ($itemData['base_price'] <= 0) ? $tempArray['base_price'] : $itemData['base_price'];
                    $itemData['original_price'] = ($itemData['original_price'] <= 0) ? $tempArray['original_price'] : $itemData['original_price'];
                    $itemData['base_original_price'] = ($itemData['base_original_price'] <= 0) ? $tempArray['base_original_price'] : $itemData['base_original_price'];
                    $itemData['tax_percent'] = ($itemData['tax_percent'] <= 0) ? $tempArray['tax_percent'] : $itemData['tax_percent'];
                    $itemData['tax_amount'] = ($itemData['tax_amount'] <= 0) ? $tempArray['tax_amount'] : $itemData['tax_amount'];
                    $itemData['discount_percent'] = ($itemData['discount_percent'] <= 0) ? $tempArray['discount_percent'] : $itemData['discount_percent'];
                    $itemData['discount_amount'] = ($itemData['discount_amount'] <= 0) ? $tempArray['discount_amount'] : $itemData['discount_amount'];
                    $itemData['row_total'] = ($itemData['row_total'] <= 0) ? $tempArray['row_total'] : $itemData['row_total'];
                    $itemData['base_row_total'] = ($itemData['base_row_total'] <= 0) ? $tempArray['base_row_total'] : $itemData['base_row_total'];
                    $tempArray = array();
                }
                $row['items'][$key] = $itemData;
                if($resync == 0)
                {   
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);                
                    $row['items'][$key]['current_qty'] = $stock->getQty();
                }
            }
            $row['payment'] = $this->_getAttributes($order->getPayment(), 'order_payment');

            $row['status_history'] = array();

            foreach ($order->getAllStatusHistory() as $history) {
                $row['status_history'][] = $this->_getAttributes($history, 'order_status_history');
            }
            foreach($order->getShipmentsCollection() as $shipment)
            {
                $shipmentArray = array();
                $shipmentId = $shipment->getId();

                $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);

                $shipmentArray = $this->_getAttributes($shipment, 'shipment');
                
                $shipmentArray['items'] = array();                
                foreach ($shipment->getAllItems() as $item) {
                    $shipmentArray['items'][] = $this->_getAttributes($item, 'shipment_item');
                }

                $shipmentArray['tracks'] = array();
                foreach ($shipment->getAllTracks() as $track) {
                    $shipmentArray['tracks'][] = $this->_getAttributes($track, 'shipment_track');
                }
                $row['shipmentCollection'][] = $shipmentArray; 
            }
            $result[] = $row;
        }

        return $result;
    }

    public function getTaxConfig()
    {
        $tableName = Mage::getSingleton('core/resource')->getTableName('core_config_data');

        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $query1 = "SELECT value FROM  ".$tableName." WHERE  `path` LIKE  'tax/calculation/price_includes_tax'";
        $result1 = $readConnection->fetchAll($query1);
        
        $tax_config = array();
        
        $query2 = "SELECT value FROM  ".$tableName." WHERE  `path` LIKE  'tax/calculation/shipping_includes_tax'";
        $result2 = $readConnection->fetchAll($query2);

        /*if($result1[0]['value'] == 1)
            $tax_config['price'] = 'inward';
        else
            $tax_config['price'] = 'outward';*/
        if($result2[0]['value'] == 1)
            $tax_config['shipping'] = 'inward';
        else
            $tax_config['shipping'] = 'outward';
        return $tax_config['shipping'];
    }

    public function updateOrderStatus($orderId,$status,$sku1 = Null,$shipping_method_name = Null,$tracking_number = Null)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        $items = $order->getItemsCollection();
        $qtys = array();
        $sku = array();
        $bool = false;
        foreach($sku1->sku as $key => $s1)
        {
            $sku[$s1->key] = $s1->value;
        }
        
        foreach($items as $item)
        {
            $Itemsku = $item->getSku();
            if(array_key_exists($Itemsku,$sku))
            {
                $qtys[$item->getId()] = $sku[$Itemsku];
            }   
        }

        if($status == shipped)
        {
            if($qtys)
            {
                $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($qtys);
                
                $arrTracking = array(
                        'carrier_code' => $order->getShippingCarrier()->getCarrierCode(),
                        'title' => isset($shipping_method_name) ? $shipping_method_name : $order->getShippingCarrier()->getConfigData('title'),
                        'number' => $tracking_number,
                                    );
                
                $track = Mage::getModel('sales/order_shipment_track')->addData($arrTracking);
                $shipment->addTrack($track);
                
                $shipment->register();
    
                $shipment->sendEmail(true)->setEmailSent(true)->save();
        
                $order->setIsInProcess(true);
        
                $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($shipment)
                        ->addObject($shipment->getOrder())
                        ->save();
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
                $bool = true;
            }
        }        
        else if($status == complete)
        {
            if($qtys)
            {
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice($qtys);
    
                $amount = $invoice->getGrandTotal();
                $invoice->register()->pay();
                $invoice->getOrder()->setIsInProcess(true);
                
                $history = $invoice->getOrder()->addStatusHistoryComment(
                    'Partial amount of $' . $amount . ' captured automatically.', false
                );
                
                $history->setIsCustomerNotified(true);
                
                $order->save();
                
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
                $invoice->save();
                $invoice->sendEmail(true, ''); 
                $order->setState(Mage_Sales_Model_Order::STATE_COMPLETE, true)->save();
                $bool = true;
            }
            $bool = true;
        }
        else if($status == canceled)
        {
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
            $bool = true;
        }
        if($bool)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function getOrderStatus($orderId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        if($order)
        {
            $status = $order->getStatusLabel(); 
            return $status;
        }
        else
        {
            return false;
        }       
    }

    protected function _getAttributes($object, $type, array $attributes = null)
    {
        $result = array();

        if (!is_object($object)) {
            return $result;
        }

        foreach ($object->getData() as $attribute=>$value) {
            if ($this->_isAllowedAttribute($attribute, $type, $attributes)) {
                $result[$attribute] = $value;
            }
        }

        foreach ($this->_attributesMap['global'] as $alias=>$attributeCode) {
            $result[$alias] = $object->getData($attributeCode);
        }

        if (isset($this->_attributesMap[$type])) {
            foreach ($this->_attributesMap[$type] as $alias=>$attributeCode) {
                $result[$alias] = $object->getData($attributeCode);
            }
        }

        return $result;
    }

    protected function _isAllowedAttribute($attributeCode, $type, array $attributes = null)
    {
        if (!empty($attributes)
            && !(in_array($attributeCode, $attributes))) {
            return false;
        }

        if (in_array($attributeCode, $this->_ignoredAttributeCodes['global'])) {
            return false;
        }

        if (isset($this->_ignoredAttributeCodes[$type])
            && in_array($attributeCode, $this->_ignoredAttributeCodes[$type])) {
            return false;
        }

        return true;
    }
}
?>