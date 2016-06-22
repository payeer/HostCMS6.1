<?php
class Shop_Payment_System_HandlerXX extends Shop_Payment_System_Handler
{
	////////////////////////  Настройки Payeer  ///////////////////////////////////
	
	// url для оплаты в системе Payeer
	
    protected $m_url = 'https://payeer.com/merchant/';
	
	// Идентификатор магазина, зарегистрированного в системе "PAYEER"
	
    protected $m_shop = '';
	
	// Секретный ключ оповещения о выполнении платежа,<br/>который используется для проверки целостности полученной информации
	
    protected $secret_key = '';
	
	// Комментарий к заказу
	
    protected $payeer_description = '';
	
	// 1 - рубли (RUB), 2 - евро (EUR), 3 - доллары (USD)
	
    protected $payeer_currency = 1;
	
	// доверенные ip-адреса. Указать через запятую, можно указать маску
	
	protected $ipfilter = ''; 
	
	// email для отправки сообщений об ошибках оплаты
	
	protected $emailerror = '';
	
	// путь до файла-журнала (например, /payeer_orders.log). Если пусто, то запись не ведется
	
	protected $payeer_log = ''; 

	////////////////////////  Конец настроек Payeer  //////////////////////////////
	
	public function execute()
    {
        parent::execute();
        $this->printNotification();
        return $this;
    }
	
    protected function _processOrder()
    {
        parent::_processOrder();
        $this->setXSLs();
        $this->send();
        return $this;
    }

    public function getSumWithCoeff()
    {
        return Shop_Controller::instance()->round(($this->payeer_currency > 0 && $this->_shopOrder->shop_currency_id > 0 
			? 
			Shop_Controller::instance()->getCurrencyCoefficientInShopCurrency(
                $this->_shopOrder->Shop_Currency,
                Core_Entity::factory('Shop_Currency', $this->payeer_currency)
            ) : 0) * $this->_shopOrder->getAmount()
		);
    }

    public function getInvoice()
    {
        return $this->getNotification();
    }

    public function getNotification()
    {
		$m_url = $this->m_url;
        $m_shop = $this->m_shop;
		$m_key = $this->secret_key;
        $m_orderid = $this->_shopOrder->id;
        $m_amount = number_format($this->getSumWithCoeff(), 2, '.', '');
        $oShop_Currency = Core_Entity::factory('Shop_Currency')->find($this->payeer_currency);
        $currency_code = $oShop_Currency->code;
        $currency_name = $oShop_Currency->name;
        $m_desc = base64_encode($this->payeer_description);
		
		$m_curr = ($currency_code == 'RUR') ? 'RUB' : $currency_code;
		
		$arHash = array(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));
		
        ob_start();

		?>

		<h1>Оплата через систему Payeer</h1>
		<p>Сумма к оплате составляет <strong><?php echo $m_amount; ?> <?php echo $currency_name; ?></strong></p>
		<form action="<?php echo $m_url; ?>" name="pay" method="get">
			<input type="hidden" name="m_shop" value="<?php echo $m_shop; ?>">
			<input type="hidden" name="m_orderid" value="<?php echo $m_orderid; ?>">
			<input type="hidden" name="m_amount" value="<?php echo $m_amount; ?>">
			<input type="hidden" name="m_curr" value="<?php echo $m_curr; ?>">
			<input type="hidden" name="m_desc" value="<?php echo $m_desc; ?>">
			<input type="hidden" name="m_sign" value="<?php echo $sign; ?>">
			<p><img src="https://payeer.com/bitrix/templates/difiz/images/logo.png" 
				alt="Система электронных платежей Payeer" 
				title="Система электронных платежей Payeer" 
				style="float:left; margin-right:0.5em; margin-bottom:0.5em; padding-top:0.25em;">
				<b>Payeer® Merchant позволяет принимать платежи всеми возможными способами по всему миру!</b>. 
			</p>
			<p><input type="submit" name="submit" value="Оплатить с помощью Payeer"></p>
		</form>

		<?php

        return ob_get_clean();
    }

    public function paymentProcessing($request)
    {
        $status = $this->ProcessResult($request);
		return $status;
    }

    function ProcessResult($request)
    {

		if (isset($request['m_orderid']))
		{
			$err = false;
			$message = '';
			
			// запись логов
			
			$log_text = 
				"--------------------------------------------------------\n" .
				"operation id		" . $request['m_operation_id'] . "\n" .
				"operation ps		" . $request['m_operation_ps'] . "\n" .
				"operation date		" . $request['m_operation_date'] . "\n" .
				"operation pay date	" . $request['m_operation_pay_date'] . "\n" .
				"shop				" . $request['m_shop'] . "\n" .
				"order id			" . $request['m_orderid'] . "\n" .
				"amount				" . $request['m_amount'] . "\n" .
				"currency			" . $request['m_curr'] . "\n" .
				"description		" . base64_decode($request['m_desc']) . "\n" .
				"status				" . $request['m_status'] . "\n" .
				"sign				" . $request['m_sign'] . "\n\n";
			
			$log_file = $this->payeer_log;
			
			if (!empty($log_file))
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
			}
			
			// проверка цифровой подписи и ip

			$sign_hash = strtoupper(hash('sha256', implode(":", array(
				$request['m_operation_id'],
				$request['m_operation_ps'],
				$request['m_operation_date'],
				$request['m_operation_pay_date'],
				$request['m_shop'],
				$request['m_orderid'],
				$request['m_amount'],
				$request['m_curr'],
				$request['m_desc'],
				$request['m_status'],
				$this->secret_key
			))));
			
			$valid_ip = true;
			$sIP = str_replace(' ', '', $this->ipfilter);
			
			if (!empty($sIP))
			{
				$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
				if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
				'(' . $arrIP[1] . '|\*{1})(\.)' .
				'(' . $arrIP[2] . '|\*{1})(\.)' .
				'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
				{
					$valid_ip = false;
				}
			}
			
			if (!$valid_ip)
			{
				$message .= " - ip-адрес сервера не является доверенным\n" .
				"   доверенные ip: " . $sIP . "\n" .
				"   ip текущего сервера: " . $_SERVER['REMOTE_ADDR'] . "\n";
				$err = true;
			}

			if ($request["m_sign"] != $sign_hash)
			{
				$message .= " - не совпадают цифровые подписи\n";
				$err = true;
			}
			
			if (!$err)
			{
				$order_curr = ($this->_shopOrder->Shop_Currency->code == 'RUR') ? 'RUB' : $this->_shopOrder->Shop_Currency->code;
				$order_amount = number_format($this->_shopOrder->getAmount(), 2, '.', '');
				
				// проверка суммы и валюты
			
				if ($request['m_amount'] != $order_amount)
				{
					$message .= " - неправильная сумма\n";
					$err = true;
				}

				if ($request['m_curr'] != $order_curr)
				{
					$message .= " - неправильная валюта\n";
					$err = true;
				}
				
				// проверка статуса
				
				if (!$err)
				{
					switch ($request['m_status'])
					{
						case 'success':
							$this->_shopOrder->paid();
							$this->setXSLs();
							$this->send();
							break;
							
						default:
							$message .= " - статус платежа не является success\n";
							$err = true;
							break;
					}
				}
			}
			
			if ($err)
			{
				$to = $this->emailerror;

				if (!empty($to))
				{
					$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n" . $message . "\n" . $log_text;
					$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
					"Content-type: text/plain; charset=utf-8 \r\n";
					mail($to, "Ошибка оплаты", $message, $headers);
				}
				
				return $request['m_orderid'] . '|error';
			}
			else
			{
				return $request['m_orderid'] . '|success';
			}
		}
		else
		{
			return false;
		}
    }
}