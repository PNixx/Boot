<?
class DB_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		$this->message = $message;
		$this->code = $code;

		//Если на продакшене выводим в лог
		if( APPLICATION_ENV == 'production' ) {
			//@todo перенести модель в систему
			Model_Log::log("db.error.log", "Error {$code}: {$message}\r\n{$this->getTraceAsString()}");
		}
		header('HTTP/1.0 ' . $code);
		if( class_exists("Boot_Controller", false) && Boot_Controller::getInstance()->isAjax() ) {
			echo json_encode(array(
				'error' => $code,
				'error_code' => $error_code,
				'message' => $message,
				'trace' => APPLICATION_ENV == "production" ? "" : $this->getTraceAsString()
			));
		} else {
			require_once SYSTEM_PATH . '/boot/exception/db.phtml';
		}
	}
}