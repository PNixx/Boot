<?php
class Boot_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		$this->message = $message;
		$this->code = $code;
		self::ex($this);
	}

	public static function ex(Exception $e) {

		//Устанавливаем код ошибки
		if($e->getCode() == 0 ) {
			$e->code = 500;
		}

		//Устанавливаем заголовок
		header('HTTP/1.0 ' . $e->getCode());

		//Если ошибка была не в БД
		if( get_class($e) != "DB_Exception" ) {

			//Обрабатываем библиотеки, в которых добавлена прослушка на ошибки
			foreach( Boot::getInstance()->library->getLibraries() as $library) {
				if( in_array("Boot_Exception_Interface", class_implements($library, false)) ) {
					$library->onException($e);
				}
			}

			require_once SYSTEM_PATH . '/boot/exception/exception.phtml';
		}
		exit;
	}

	public static function err_handler($errno, $errstr, $errfile, $errline, $errcontext) {
		$l = error_reporting();
		if( $l & $errno ) {

			$exit = false;
			switch( $errno ) {
				case E_USER_ERROR:
					$type = 'Fatal Error';
					$exit = true;
					break;
				case E_USER_WARNING:
				case E_WARNING:
					$type = 'Warning';
					break;
				case E_USER_NOTICE:
				case E_NOTICE:
				case @E_STRICT:
					$type = 'Notice';
					break;
				case @E_RECOVERABLE_ERROR:
					$type = 'Catchable';
					break;
				default:
					$type = 'Unknown Error';
					$exit = true;
					break;
			}
			if( class_exists("Boot_Controller", false) && Boot_Controller::getInstance()->isAjax() ) {
				echo $type . ": " . $errstr . "({$errfile}:{$errline})\r\n";
			} else {
				echo "<pre>" . $type . ": " . $errstr . "({$errfile}:<b>{$errline}</b>)<pre>";
			}

		}
		return true;
	}
}

class Ajax_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		header('HTTP/1.0 ' . $code);
		echo json_encode(array(
			'error'      => $code,
			'error_code' => $error_code,
			'message'    => $message,
			'trace'      => APPLICATION_ENV == "production" ? "" : $this->getTraceAsString()
		));
		exit;
	}
}
class Controller_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		header('HTTP/1.0 ' . $code);
		if( Boot_Controller::getInstance()->isAjax() ) {
			echo json_encode(array(
				'error' => $code,
				'error_code' => $error_code,
				'message' => $message,
				'trace' => APPLICATION_ENV == "production" ? "" : $this->getTraceAsString()
			));
		} else {
			new Boot_Exception($message, $code);
		}
		exit;
	}
}