<?php
class DB_Exeption extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		self::ex(new Exception($message, $code, $error_code));
	}

	public static  function ex(Exception $e) {
		require_once SYSTEM_PATH . '/boot/exception/db.phtml';
		exit;
	}
}