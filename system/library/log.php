<?php
/**
 * User: Odintsov S.A.
 * Date: 22.10.11
 * Time: 11:41
 */

class Boot_Log_Lib extends Boot_Abstract_Library implements Boot_Exception_Interface {

	/**
	 * Добавить в лог
	 * @static
	 * @param $file
	 * @param $append
	 * @return void
	 */
	public static function log($file, $append) {

		//Если включено логирование
		if( Boot::getInstance()->config->log->on ) {

			//Добавляем в лог
			if( is_writeable(Boot::getInstance()->config->log->dir) ) {
				file_put_contents(Boot::getInstance()->config->log->dir . $file, "[" . date("d.m.y H:i:s") . "] " . $append . PHP_EOL, FILE_APPEND);
			} else {
				echo "Permission denied to save " . $file . PHP_EOL;
				exit;
			}
		}
	}

	/**
	 * Очистка лога
	 * @param $file
	 */
	public static function clear($file) {
		//Добавляем в лог
		file_put_contents(Boot::getInstance()->config->log->dir . $file, "");
	}

	/**
	 * Обработка ошибки
	 * @param Throwable $e
	 * @return mixed
	 */
	public static function onException($e) {
		if( APPLICATION_ENV == 'production' && $e->getCode() != 404 ) {
			self::log("error.log", "Error " . $e->getCode() . ": " . $e->getMessage() . "\r\n" . $e->getTraceAsString());
		}
	}
}