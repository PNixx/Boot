<?php
/**
 * Date: 20.02.16
 * Time: 20:46
 * @author  Sergey Odintsov <nixx.dj@gmail.com>
 */
namespace Boot;
use Boot\Library\Translate;

trait LibraryTrait {

	/**
	 * Перевод
	 * @param string $text
	 * @param null|int $args
	 * @param bool $plural
	 * @return string
	 */
	static public function t($text, $args = null, $plural = false) {
		return Translate::getInstance()->_($text, $args, $plural);
	}

	/**
	 * Достает текущую локаль
	 * @return string
	 */
	static public function getLocale() {
		return Translate::getInstance()->getLocale();
	}

	/**
	 * Получение всех языков
	 * @return array
	 */
	static public function getLanguages() {
		return Translate::getInstance()->getLanguages();
	}

	/**
	 * Сохраняет авторизацию
	 * @param int $id
	 * @param string $user_key
	 */
	static public function setAuth($id, $user_key = '') {
		\Boot_Auth_Lib::getInstance()->setAuth($id, $user_key);
	}

	/**
	 * Возвращаем авторизацию
	 * @return \Model_User
	 */
	static public function me() {
		return \Boot_Auth_Lib::getInstance()->getAuth();
	}
}