<?php
/**
 * User: Odintsov S.A.
 * Date: 24.05.12
 * Time: 20:42
 */

class Boot_Auth {

	/**
	 * Инстанс
	 * @var Boot_Auth
	 */
	static private $_instance = null;

	/**
	 * Секретный ключ для проверки авторизации
	 * @var null
	 */
	private $skey = null;

	/**
	 * Хранилище авторизации
	 * @var null
	 */
	private $_me = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Auth
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot_Auth) ) {
			self::$_instance = new Boot_Auth();
			self::$_instance->skey = Boot::getInstance()->config->auth_skey;
		}
		return self::$_instance;
	}

	/**
	 * Возвращаем авторизацию
	 * @return Model_User_Row
	 */
	public function getAuth() {
		if( $this->_me === null ) {

			//Записываем токен в куки
			$token = Boot_Cookie::get("auth_token");

			//Разбиваем токен
			@list($id, $skey, $sig) = explode("#", $token);

			if( !$id || !$skey || !$sig ) {
				$this->_me = false;
				Boot_Cookie::clear("auth_token");
				return false;
			}

			//Проверяем корректность
			if( $skey != Boot_Skey::get() || $sig != md5($id . $skey) ) {
				$this->_me = false;
				Boot_Cookie::clear("auth_token");
				return false;
			}

			//Получаем юзера
			$this->_me = Model_User::model()->getById($id);
		}

		return $this->_me;
	}

	public function setAuth($id) {

		//Получаем секретный ключ
		$skey = Boot_Skey::get();

		//Записываем токен в куки
		Boot_Cookie::set("auth_token", $id . "#" . $skey . "#" . md5($id . $skey));
	}

	public function clear() {
		Boot_Cookie::clear("auth_token");
		$this->_me = null;
	}

	/**
	 * Http авторизация
	 * @param $login
	 * @param $passw
	 */
	static public function httpAuth($login, $passw) {
		if( isset($_SERVER['PHP_AUTH_USER']) == false || isset($_SERVER['PHP_AUTH_PW']) == false || $_SERVER['PHP_AUTH_USER'] != $login || $_SERVER['PHP_AUTH_PW'] != $passw ) {
			$_SESSION['http_logged'] = 1;
			header('WWW-Authenticate: Basic realm="realm"');
			header('HTTP/1.0 401 Unauthorized');
			die("В доступе отказано.");
		}
	}
}
