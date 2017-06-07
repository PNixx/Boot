<?php
use Boot\Core\View;

/**
 * User: Odintsov S.A.
 * Date: 24.05.12
 * Time: 20:42
 * @deprecated
 */
class Boot_Auth_Lib extends Boot_Abstract_Library {

	/**
	 * Инстанс
	 * @var Boot_Auth_Lib
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

	//Коструктор
	public function __construct() {

		//Проверяем существование моделей
		if( class_exists("Model_User") == false ) {
			throw new Boot_Exception("Model_User is not found");
		}
	}

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot_Auth_Lib
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot_Auth_Lib) ) {
			self::$_instance = new Boot_Auth_Lib();
			self::$_instance->skey = Boot::getInstance()->config->auth_skey;
		}
		return self::$_instance;
	}

	/**
	 * Возвращаем авторизацию
	 * @return Boot\AuthTrait
	 */
	public function getAuth() {
		if( $this->_me === null ) {

			//Читаем токен
			$token = Boot_Cookie::get("auth_token");

			//Разбиваем токен
			@list($id, $skey, $sig) = explode("#", $token);

			if( !$id || !$skey || !$sig ) {
				$this->_me = false;
				Boot_Cookie::clear("auth_token");
				return null;
			}

			//Получаем юзера
			try {
				$this->_me = Model_User::find($id);
			} catch( Exception $e ) {
				$this->_me = false;
			}

			//Проверяем корректность
			if( $this->_me == false || $skey != Boot_Skey::get() || $sig != md5($id . $skey . $this->_me->skey) ) {
				$this->_me = false;
				Boot_Cookie::clear("auth_token");
				return null;
			}
		}

		return $this->_me;
	}

	public function setAuth($id, $user_key = "") {

		//Получаем секретный ключ
		$skey = Boot_Skey::get();

		//Записываем токен в куки
		$token = $id . "#" . $skey . "#" . md5($id . $skey . $user_key);
		Boot_Cookie::set("auth_token", $token);

		//Возвращаем токен
		return $token;
	}

	public function clear() {
		Boot_Cookie::clear("auth_token");
		$this->_me = null;
	}

	/**
	 * Http авторизация
	 * @param $login
	 * @param $passw
	 * @deprecated
	 */
	static public function httpAuth($login, $passw) {
		if( isset($_SERVER['PHP_AUTH_USER']) == false || isset($_SERVER['PHP_AUTH_PW']) == false || $_SERVER['PHP_AUTH_USER'] != $login || $_SERVER['PHP_AUTH_PW'] != $passw ) {
			$_SESSION['http_logged'] = 1;
			header('WWW-Authenticate: Basic realm="realm"');
			header('HTTP/1.0 401 Unauthorized');
			die("В доступе отказано.");
		}
	}

	/**
	 * Инициализация библиотеки во вьюхе и контроллере
	 * @param View|Boot_Controller $class
	 * @return void
	 */
	public function init(&$class) {
		parent::init($class);

		//Добавляем объект юзера к классу
		$class->me = Boot_Auth_Lib::getInstance()->getAuth();
	}
}
