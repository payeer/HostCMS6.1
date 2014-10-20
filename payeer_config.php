// ------------------------------------------------
// Processing of payment notification from Payeer     //
// ------------------------------------------------
if (isset($_POST['m_operation_id']) && isset($_POST['m_sign']))
{
	$order_id = intval(Core_Array::getRequest('m_orderid'));

	$oShop_Order = Core_Entity::factory('Shop_Order')->find($order_id);

	if (!is_null($oShop_Order->id))
	{
		// The call handler payment system
		Shop_Payment_System_Handler::factory($oShop_Order->Shop_Payment_System)
			->shopOrder($oShop_Order)
			->paymentProcessing();
	}

	exit();
}
// ------------------------------------------------
// /the end handler Payeer                       //
// ------------------------------------------------