<?php
/**
 * User: Odintsov S.A.
 * Date: 18.08.11
 * Time: 18:53
 */

class Boot_Cookie {

	/**
	 * Запись в cookie
	 * @static
	 * @param      $name
	 * @param      $value
	 * @param bool $subdomain
	 */
	static public function set($name, $value, $subdomain = false) {
		setcookie($name, $value, time() + 2678400, "/", $subdomain ? '.' . Boot::getInstance()->config->host : null);
	}

	/**
	 * Получить куку
	 * @static
	 * @param $name
	 * @return boolean|String
	 */
	static public function get($name) {
		if( isset($_COOKIE[$name]) ) {
			return $_COOKIE[$name];
		}
		return false;
	}

	static public function clear($name) {
		setcookie($name, "", time() - 1, "/");
	}

	/**
	 * Запускаем сессию
	 */
	static public function session_start() {
		if( empty($_COOKIE[session_name()]) || !preg_match('/^[a-zA-Z0-9,\-]{22,40}$/', $_COOKIE[session_name()]) ) {
			session_id(uniqid());
			session_start();
			session_regenerate_id();
		} else {
			session_start();
		}
	}
}
