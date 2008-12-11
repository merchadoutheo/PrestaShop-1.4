<?php

/**
  * OrderDetail class, OrderDetail.php
  * Orders detail management
  * @category classes
  *
  * @author PrestaShop <support@prestashop.com>
  * @copyright PrestaShop
  * @license http://www.opensource.org/licenses/osl-3.0.php Open-source licence 3.0
  * @version 1.0
  *
  */

class OrderReturn extends ObjectModel
{
	/** @var integer */
	public		$id;
	
	/** @var integer */
	public 		$id_customer;
	
	/** @var integer */
	public 		$id_order;
	
	/** @var integer */
	public 		$state;
	
	/** @var string message content */
	public		$question;
	
	/** @var string Object creation date */
	public 		$date_add;

	/** @var string Object last modification date */
	public 		$date_upd;

	protected $tables = array ('order_return');

	protected	$fieldsRequired = array ('id_customer', 'id_order');
	protected	$fieldsValidate = array('id_customer' => 'isUnsignedId', 'id_order' => 'isUnsignedId', 'question' => 'isMessage');

	protected 	$table = 'order_return';
	protected 	$identifier = 'id_order_return';
	
	public function getFields()
	{
		parent::validateFields();

		$fields['id_customer'] = pSQL($this->id_customer);
		$fields['id_order'] = pSQL($this->id_order);
		$fields['state'] = pSQL($this->state);
		$fields['date_add'] = pSQL($this->date_add);
		$fields['date_upd'] = pSQL($this->date_upd);
		$fields['question'] = pSQL(nl2br2($this->question), true);
		return $fields;
	}
	
	public function addReturnDetail($orderDetailList, $productQtyList, $customizationIds, $customizationQtyInput)
	{
		/* Classic product return */
		if ($orderDetailList)
			foreach ($orderDetailList AS $key => $orderDetail)
				if ($qty = intval($productQtyList[$key]))
					Db::getInstance()->AutoExecute(_DB_PREFIX_.'order_return_detail', array('id_order_return' => intval($this->id), 'id_order_detail' => intval($orderDetail), 'product_quantity' => $qty), 'INSERT');
		/* Customized product return */
		if ($customizationIds)
			foreach ($customizationIds AS $productId => $customizations)
				foreach ($customizations AS $customizationId)
					if ($quantity = intval($customizationQtyInput[intval($customizationId)]))
						Db::getInstance()->AutoExecute(_DB_PREFIX_.'order_customization_return', array('id_order' => intval($this->id_order), 'product_id' => intval($productId), 'customization_id' => intval($customizationId), 'quantity' => $quantity), 'INSERT');
	}
	
	public function checkEnoughProduct($orderDetailList, $productQtyList, $customizationIds, $customizationQtyInput)
	{
		$order = new Order(intval($this->id_order));
		if (!Validate::isLoadedObject($order))
			die(Tools::displayError());
		$products = $order->getProducts();
		/* Classic products already returned */
		$order_return = self::getOrdersReturn($order->id_customer, $order->id, true);
		foreach ($order_return AS $or)
		{
			$order_return_products = self::getOrdersReturnProducts($or['id_order_return'], $order);
			foreach ($order_return_products AS $key => $orp)
				$products[$key]['product_quantity'] -= intval($orp['product_quantity']);
		}
		/* Customized products already returned */
		$orderedCustomizations = Customization::getOrderedCustomizations(intval($order->id_cart));
		if ($returnedCustomizations = Customization::getReturnedCustomizations($this->id_order))
		{
			$customizationQuantityByProduct = Customization::countCustomizationQuantityByProduct($returnedCustomizations);
			foreach ($products AS &$product)
				$product['product_quantity'] -= intval($customizationQuantityByProduct[intval($product['product_id'])]);
		}
		/* Quantity check */
		if ($orderDetailList)
			foreach ($orderDetailList AS $key => $orderDetail)
				if ($qty = intval($productQtyList[$key]))
					if ($products[$key]['product_quantity'] - $qty < 0)
						return false;
		/* Customization quantity check */
		if ($customizationIds)
			foreach ($customizationIds AS $productId => $customizations)
				foreach ($customizations AS $customizationId)
				{
					$customizationId = intval($customizationId);
					if (!isset($orderedCustomizations[$customizationId]))
						return false;
					$quantity = (isset($returnedCustomizations[$customizationId]) ? $returnedCustomizations[$customizationId] : 0) + (isset($customizationQtyInput[$customizationId]) ? intval($customizationQtyInput[$customizationId]) : 0);
					if (intval($orderedCustomizations[$customizationId]['quantity']) - $quantity < 0)
						return false;
				}
		return true;
	}

	public function countProduct()
	{
		$data = Db::getInstance()->ExecuteS('
		SELECT *
		FROM `'._DB_PREFIX_.'order_return_detail`
		WHERE `id_order_return` = '.intval($this->id));
		return $data;
	}
	
	static public function getOrdersReturn($customer_id, $order_id = false, $no_denied = false)
	{
		global $cookie;
		
		$data = Db::getInstance()->ExecuteS('
		SELECT *
		FROM `'._DB_PREFIX_.'order_return`
		WHERE `id_customer` = '.intval($customer_id).
		($order_id ? ' AND `id_order` = '.intval($order_id) : '').
		($no_denied ? ' AND `state` != 4' : '').'
		ORDER BY `date_add` DESC');
		foreach ($data as $k => $or)
		{
			$state = new OrderReturnState($or['state']);
			$data[$k]['state_name'] = $state->name[$cookie->id_lang];
		}
		return $data;
	}
	
	static public function getOrdersReturnDetail($id_order_return)
	{
		return Db::getInstance()->ExecuteS('
		SELECT *
		FROM `'._DB_PREFIX_.'order_return_detail`
		WHERE `id_order_return` = '.intval($id_order_return));
	}
	
	static public function getOrdersReturnProducts($orderReturnId, $order)
	{
		$productsRet = self::getOrdersReturnDetail($orderReturnId);
		$products = $order->getProducts();
		$tmp = array();
		foreach ($productsRet as $return_detail)
			$tmp[$return_detail['id_order_detail']] = $return_detail['product_quantity'];
		$resTab = array();
		foreach ($products as $key => $product)
			if (isset($tmp[$product['id_order_detail']]))
			{
				$resTab[$key] = $product;
				$resTab[$key]['product_quantity'] = $tmp[$product['id_order_detail']];;
			}
		return $resTab;
	}

	static public function getReturnedCustomizedProducts($id_order)
	{
		$returns = Customization::getReturnedCustomizations($id_order);
		$order = new Order(intval($id_order));
		if (!Validate::isLoadedObject($order))
			die(Tools::displayError());
		$products = $order->getProducts();
		foreach ($returns AS &$return)
		{
			$return['name'] = $products[intval($return['product_id'])]['product_name'];
			$return['reference'] = $products[intval($return['product_id'])]['product_reference'];
		}
		return $returns;
	}
}

?>