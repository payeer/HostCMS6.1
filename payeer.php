<?php
class Shop_Payment_System_HandlerXX extends Shop_Payment_System_Handler
{
	////////////////////////  Настройки Payeer  ///////////////////////////////////
	
	// url для оплаты в системе Payeer
	
    protected $m_url = '//payeer.com/merchant/';
	
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
        $m_amount = $this->getSumWithCoeff();
        $oShop_Currency = Core_Entity::factory('Shop_Currency')->find($this->payeer_currency);
        $currency_code = $oShop_Currency->code;
        $currency_name = $oShop_Currency->name;
        $m_desc = base64_encode($this->payeer_description);
		
		$m_curr = $currency_code;
		
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
		<p>Сумма к оплате составляет <strong><?php echo $m_amount?> <?php echo $currency_name?></strong></p>
		<form action="<?=$m_url?>" name="pay" method="get">
			<input type="hidden" name="m_shop" value="<?=$m_shop?>">
			<input type="hidden" name="m_orderid" value="<?=$m_orderid?>">
			<input type="hidden" name="m_amount" value="<?=$m_amount?>">
			<input type="hidden" name="m_curr" value="<?=$m_curr?>">
			<input type="hidden" name="m_desc" value="<?=$m_desc?>">
			<input type="hidden" name="m_sign" value="<?=$sign?>">
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

    public function paymentProcessing()
    {
        $this->ProcessResult();

        return TRUE;
    }

    function ProcessResult()
    {
		if (isset($_POST['m_orderid']))
		{            
			$m_key = $this->secret_key;
			
			$arHash = array($_POST['m_operation_id'],
				$_POST['m_operation_ps'],
				$_POST['m_operation_date'],
				$_POST['m_operation_pay_date'],
				$_POST['m_shop'],
				$_POST['m_orderid'],
				$_POST['m_amount'],
				$_POST['m_curr'],
				$_POST['m_desc'],
				$_POST['m_status'],
				$m_key
			);
				
			$sign_hash = strtoupper(hash('sha256', implode(':', $arHash)));
			
			// проверка принадлежности ip списку доверенных ip
			
			$list_ip_str = str_replace(' ', '', $this->ipfilter);
			
			if (!empty($list_ip_str)) 
			{
				$list_ip = explode(',', $list_ip_str);
				$this_ip = $_SERVER['REMOTE_ADDR'];
				$this_ip_field = explode('.', $this_ip);
				$list_ip_field = array();
				$i = 0;
				$valid_ip = FALSE;
				foreach ($list_ip as $ip)
				{
					$ip_field[$i] = explode('.', $ip);
					if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
						(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
						(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
						(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
						{
							$valid_ip = TRUE;
							break;
						}
					$i++;
				}
			}
			else
			{
				$valid_ip = TRUE;
			}
		
			$log_text = 
				"--------------------------------------------------------\n".
				"operation id		" . $_POST["m_operation_id"] . "\n".
				"operation ps		" . $_POST["m_operation_ps"] . "\n".
				"operation date		" . $_POST["m_operation_date"] . "\n".
				"operation pay date	" . $_POST["m_operation_pay_date"] . "\n".
				"shop				" . $_POST["m_shop"] . "\n".
				"order id			" . $_POST["m_orderid"] . "\n".
				"amount				" . $_POST["m_amount"] . "\n".
				"currency			" . $_POST["m_curr"] . "\n".
				"description		" . base64_decode($_POST["m_desc"]) . "\n".
				"status				" . $_POST["m_status"] . "\n".
				"sign				" . $_POST["m_sign"] . "\n\n";
					
			if ($this->payeer_log != '')
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $this->payeer_log, $log_text, FILE_APPEND);
			}

			if ($_POST['m_sign'] == $sign_hash && $_POST['m_status'] == 'success' && $valid_ip)
			{
				$this->_shopOrder->paid();
				$this->setXSLs();
				$this->send();

				echo ($_POST['m_orderid'] . '|success');
			}
			else
			{
				$oSite_Alias = $this->_shopOrder->Shop->Site->getCurrentAlias();
				$site_alias = !is_null($oSite_Alias) ? $oSite_Alias->name : '';
				$to = $this->emailerror;
				$subject = "Ошибка оплаты";
				$message = "Не удалось провести платёж через систему Payeer по следующим причинам:\n\n";
				
				if ($_POST["m_sign"] != $sign_hash)
				{
					$message .= " - Не совпадают цифровые подписи\n";
				}
				
				if ($_POST['m_status'] != "success")
				{
					$message .= " - Cтатус платежа не является success\n";
				}
				
				if (!$valid_ip)
				{
					$message .= " - ip-адрес сервера не является доверенным\n";
					$message .= "   доверенные ip: " . $this->ipfilter . "\n";
					$message .= "   ip текущего сервера: " . $_SERVER['REMOTE_ADDR'] . "\n";
				}
				
				$message .= "\n" . $log_text;
				$headers = "From: no-reply@" . $site_alias . "\r\nContent-type: text/plain; charset=utf-8 \r\n";
				mail($to, $subject, $message, $headers);
				echo ($_POST['m_orderid'] . '|error');
			}
		}
		else
		{
			return false;
		}
    }
}