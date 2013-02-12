<?php
class DB_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		self::ex(new Exception($message, $code, $error_code));
	}

	public static function ex(Exception $e) {
		echo "Error message: " . $e->getMessage() . "\r\n";
		throw $e;
	}
}