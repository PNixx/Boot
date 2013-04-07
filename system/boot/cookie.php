<?php
/**
 * User: Odintsov S.A.
 * Date: 18.08.11
 * Time: 18:53
 */
//if( session_name() == false ) {
//	session_start();
//}
class Boot_Cookie {

	/**
	 * Запись в cookie
	 * @static
	 * @param $name
	 * @param $value
	 * @return void
	 */
	static public function set($name, $value) {
//		$_SESSION[$name] = $value;
		setcookie($name, $value, time() + 2678400, "/");
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

//		if( isset($_SESSION[$name]) ) {
//			return $_SESSION[$name];
//		}
		return false;
	}

	static public function clear($name) {
//		unset($_SESSION[$name]);
		setcookie($name, "", time() - 1, "/");
	}
}
