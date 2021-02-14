// ------------------------------------------------
// Обработка уведомления об оплате от Payeer     //
// ------------------------------------------------

$payeer_m_operation_id = Core_Array::getRequest('m_operation_id');
$payeer_m_sign = Core_Array::getRequest('m_sign');

if (!empty($payeer_m_operation_id) && !empty($payeer_m_sign))
{
	$order_id = intval(Core_Array::getRequest('m_orderid'));

	$oShop_Order = Core_Entity::factory('Shop_Order')->find($order_id);

	if (!is_null($oShop_Order->id))
	{
		// Вызов обработчика платежной системы
		
		$payeer_status = Shop_Payment_System_Handler::factory($oShop_Order->Shop_Payment_System)
			->shopOrder($oShop_Order)
			->paymentProcessing($_POST);
			
		exit($payeer_status);
	}
}
// ------------------------------------------------
// /конец обработчика Payeer                     //
// ------------------------------------------------