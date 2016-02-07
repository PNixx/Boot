<?php
class Boot_Exception extends Exception {

	/**
	 * Конструктор
	 * @param null $message
	 * @param int  $code
	 * @param null $error_code
	 */
	public function __construct($message = null, $code = 500, $error_code = null) {
		$this->message = $message;
		$this->code = $code;
		self::ex($this);
	}

	/**
	 * @param Exception $e
	 */
	public static function ex(Exception $e) {
		//Обрабатываем библиотеки, в которых добавлена прослушка на ошибки
		self::sendLibraryException($e);

		//Если работаем через консоль
		if( defined('APPLICATION_CLI') && APPLICATION_CLI ) {
			echo "Error message: " . $e->getMessage() . "\r\n";
			echo $e->getTraceAsString() . PHP_EOL;
		} else {

			//Устанавливаем код ошибки
			if( $e->getCode() == 0 ) {
				$e->code = 500;
			}

			//Устанавливаем заголовок
			header('HTTP/1.0 ' . $e->getCode());
			require_once SYSTEM_PATH . '/boot/exception/exception.phtml';
		}
		exit(127);
	}

	/**
	 * Обрабатываем библиотеки, в которых добавлена прослушка на ошибки
	 * @param Exception $e
	 */
	public static function sendLibraryException(Exception $e) {
		if( Boot::getInstance()->library ) {
			foreach( Boot::getInstance()->library->getLibraries() as $library ) {
				if( in_array("Boot_Exception_Interface", class_implements($library, false)) ) {
					$library->onException($e);
				}
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
	 * @throws ErrorException
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
					$type = 'Notice';
					$exit = APPLICATION_ENV == 'development' && true;
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

			//Если на продакшене, отправляем в лог
			if( APPLICATION_ENV == "production" ) {
				Boot_Log_Lib::log("error.log", $type . ": " . $errstr . "({$errfile}:{$errline})");
			}

			//Останавливаем при ошибке
			throw new ErrorException($errstr, 500);
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
 * Class Controller_Exception
 */
class Controller_Exception extends Exception {

	public function __construct($message = null, $code = 500, $error_code = null) {
		http_response_code($code);
		if( Boot_Controller::getInstance()->isAjax() ) {

			//Обрабатываем библиотеки, в которых добавлена прослушка на ошибки
			Boot_Exception::sendLibraryException(new Exception($message, $code));

			echo json_encode(array(
				'error' => $code,
				'error_code' => $error_code,
				'message' => $message,
				'trace' => APPLICATION_ENV == "production" ? "" : $this->getTraceAsString()
			));
		} else {
			throw new Boot_Exception($message, $code);
		}
		exit;
	}
}