<?php
/**
 * User: Odintsov S.A.
 * Date: 10.08.12
 * Time: 10:43
 */

class Boot_Flash {
	/**
	 * @var Boot_Flash
	 */
	static public $_instance = null;

	public $_flash = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Flash
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot_Flash) ) {
			self::$_instance = new Boot_Flash();
		}
		return self::$_instance;
	}

	/**
	 * Создаём и возвращаем ключ
	 */
	public function __construct() {
		if( isset($_COOKIE['flash']) ) {
			$this->_flash = unserialize($_COOKIE['flash']);
			setcookie('flash', null, time() - 1, "/", Boot::getInstance()->config->host);
		}
	}

	public function set($name, $value) {
		$this->_flash[$name] = $value;
		setcookie('flash', serialize($this->_flash), null, "/", Boot::getInstance()->config->host);
	}

	/**
	 * @param $name
	 * @return bool|string
	 */
	static public function get($name) {
		if( isset(self::getInstance()->_flash[$name]) ) {
			return self::getInstance()->_flash[$name];
		}
		return false;
	}
}
