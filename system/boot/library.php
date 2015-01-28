<?php
/**
 * Created by JetBrains PhpStorm.
 * User: nixx
 * Date: 24.04.13
 * Time: 13:50
 * To change this template use File | Settings | File Templates.
 */

class Boot_Library {

	/**
	 * @var stdClass
	 */
	private $_libraries;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_libraries = new stdClass();
	}

	/**
	 * Set var
	 * @param $name
	 * @param $value
	 */
	public function __set($name, $value) {
		$this->_libraries->$name = $value;
	}

	/**
	 * Get var
	 * @param $name
	 * @return mixed
	 */
	public function __get($name) {
		return $this->_libraries->$name;
	}

	/**
	 * Получение списка библиотек
	 * @return stdClass|Boot_Abstract_Library[]|Boot_Exception_Interface[]
	 */
	public function getLibraries() {
		return $this->_libraries;
	}
}