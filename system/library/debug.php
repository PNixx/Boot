<?php
/**
 * User: nixx
 * Date: 15.04.14
 * Time: 22:38
 */
class Boot_Debug_Lib extends Boot_Abstract_Library implements Boot_Exception_Interface {

	/**
	 * Нужно ли инициализировать библиотеку в системе?
	 * @var bool
	 */
	public static $is_init = true;

	/**
	 * @param $logger
	 */
	public static function log($logger) {
		//Добавляем в лог
		if( is_writeable(Boot::getInstance()->config->log->dir) ) {
			file_put_contents(Boot::getInstance()->config->log->dir . "debug.log", $logger . PHP_EOL, FILE_APPEND);
		} else {
			echo "Permission denied to save debug.log" . PHP_EOL;
			exit;
		}
	}

	/**
	 * Обработка ошибки
	 * @param Exception $e
	 * @return mixed
	 */
	public static function onException(Exception $e) {
		Boot::getInstance()->debug("  \x1b[31mMessage: " . $e->getMessage() . "\x1b[0m" . PHP_EOL . $e->getTraceAsString());
	}
}