<?php
class DB_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		$this->message = $message;
		require_once SYSTEM_PATH . '/boot/exception/db.phtml';
	}
}