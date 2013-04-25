<?php
/**
 * @author: nixx
 * Date: 25.04.13
 * Time: 20:30
 * @version 1.0
 */
interface Boot_Exception_Interface {

	/**
	 * Обработка ошибки
	 * @param Exception $e
	 * @return mixed
	 */
	public static function onException(Exception $e);
}