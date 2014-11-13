// ------------------------------------------------
// Обработка уведомления об оплате от Payeer     //
// ------------------------------------------------
if (isset($_POST['m_operation_id']) && isset($_POST['m_sign']))
{
	$order_id = intval(Core_Array::getRequest('m_orderid'));

	$oShop_Order = Core_Entity::factory('Shop_Order')->find($order_id);

	if (!is_null($oShop_Order->id))
	{
		// Вызов обработчика платежной системы
		Shop_Payment_System_Handler::factory($oShop_Order->Shop_Payment_System)
			->shopOrder($oShop_Order)
			->paymentProcessing();
	}

	exit();
}
// ------------------------------------------------
// /конец обработчика Payeer                     //
// ------------------------------------------------