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
			self::sendLibraryException($e);

			require_once SYSTEM_PATH . '/boot/exception/exception.phtml';
		}
		exit;
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
	 * Перехват ошибок
	 * @param $errno
	 * @param $errstr
	 * @param $errfile
	 * @param $errline
	 * @param $errcontext
	 * @return bool
	 */
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
					$exit = true;
					break;
				case E_USER_NOTICE:
				case E_NOTICE:
				case @E_STRICT:
					$type = 'Notice';
					$exit = true;
					break;
				case @E_RECOVERABLE_ERROR:
					$type = 'Catchable';
					$exit = true;
					break;
				default:
					$type = 'Unknown Error';
					$exit = true;
					break;
			}
			if( class_exists("Boot_Controller", false) && Boot_Controller::getInstance()->isAjax() ) {
				echo $type . ": " . $errstr . "({$errfile}:{$errline})\r\n";
			} else {
				if( APPLICATION_ENV != "production" ) {
					echo "<pre>" . $type . ": " . $errstr . "({$errfile}:<b>{$errline}</b>)</pre>";
				} else {
					Boot_Log_Lib::log("error.log", $type . ": " . $errstr . "({$errfile}:{$errline})");
					echo "<pre>" . $type . ": " . $errstr . "</pre>";
				}
			}
			if( $exit ) {
				throw new ErrorException($errstr);
			}

		}
		return true;
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
			$e = new Exception(self::get_error_string($l["type"]) . ": " . $l["message"] . PHP_EOL . $l["file"] . ":" . $l["line"], 500);

			//Отправляем данные об ошибке в библиотеки
			self::sendLibraryException($e);

			//Устанавливаем код ошибки
			header("HTTP/1.0 500");

			//Выводим текст ошибки
			return "<h1>Oops</h1>" . (APPLICATION_ENV != "production" ? $e->getMessage() : "");
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

/**
 * Class Ajax_Exception
 * @deprecated
 */
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