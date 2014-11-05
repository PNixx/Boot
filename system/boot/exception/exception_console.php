<?php
class Boot_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		self::ex(new Exception($message, $code, $error_code));
	}

	public static function ex(Exception $e) {
		echo "Error message: " . $e->getMessage() . "\r\n";
		echo $e->getTraceAsString() . PHP_EOL;
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

//			$r = ob_get_contents();
//			ob_end_clean();
			throw new ErrorException($type . ': ' . $errstr, 0, $errno, $errfile, $errline);
		}
		return false;
	}

	/**
	 * Обрабатываем библиотеки, в которых добавлена прослушка на ошибки
	 * @param Exception $e
	 */
	public static function sendLibraryException(Exception $e) {
		foreach( Boot::getInstance()->library->getLibraries() as $library) {
			if( in_array("Boot_Exception_Interface", class_implements($library, false)) ) {
				$library->onException($e);
			}
		}
	}

	/**
	* Функция выполняется, фатальной ошибке
	*/
	public static function shutdown($str) {

		//Получаем последнюю ошибку
		$l = error_get_last();

		//Если получили ошибку
		if( $l ) {

			//Строим класс эксепшена
			$e = new Exception(self::get_error_string($l["type"]) . ": " . $l["message"] . PHP_EOL . $l["file"] . ":" . $l["line"] . var_export(debug_backtrace(), true), 500);

			//Отправляем данные об ошибке в библиотеки
			self::sendLibraryException($e);

			//Устанавливаем код ошибки
			header("HTTP/1.0 500");

			//Выводим текст ошибки
			return "Oops" . PHP_EOL . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . var_export(debug_backtrace(), true);
		} else {
			return $str;
		}
	}

	/**
	 * Получение строки ошибки по коду
	 * @param $type
	 * @return string
	 */
	private static function get_error_string($type) {
		switch( $type ) {
			case E_ERROR:
				return 'Fatal Error';

			case E_USER_WARNING:
			case E_WARNING:
				return 'Warning';

			case E_USER_NOTICE:
			case E_NOTICE:
			case @E_STRICT:
				return 'Notice';

			default:
				return 'Unknown Error';
		}
	}
}

class Ajax_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		header('HTTP/1.0 ' . $code);
		echo json_encode(array(
			'error'      => $code,
			'error_code' => $error_code,
			'message'    => $message,
			'trace'      => Boot::getInstance()->config->is_work ? "" : $this->getTraceAsString()
		));
		exit;
	}
}