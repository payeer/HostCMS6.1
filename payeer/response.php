<?php 
	$order_id = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_GET['m_orderid'], 0, 32));
	$status = preg_replace('/[^a-z]/', '', substr($_GET['m_status'], 0, 7));
	header('Location: ' . $_SERVER['HOST'] . '/shop/cart/?order_id=' . $order_id . '&payment=' . $status);
?>