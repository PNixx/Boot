<?php
/**
 * User: nixx
 * Date: 22.12.14
 * Time: 16:10
 */

class Boot_Cache_Lib extends Boot_Abstract_Library {

	//Префикс
	private $prefix;

	/**
	 * @var Memcache
	 */
	private $m;

	/**
	 * Состояние подключения
	 * @var bool
	 */
	private $connected = true;

	/**
	 * Конструктор кеша
	 */
	public function __construct() {

		//Проверяем ключи в конфиге
		if( !isset(Boot::getInstance()->config->memcache->host) || !isset(Boot::getInstance()->config->memcache->port) ) {
			throw new Boot_Exception('Не указаны настройки Memcache', 500);
		}

		//Добавляем префикс
		if( isset(Boot::getInstance()->config->memcache->prefix) ) {
			$this->prefix = Boot::getInstance()->config->memcache->prefix . "::";
		}

		//Инициализируем класс кеша
		$this->m = new Memcache();

		//Добавляем сервер
		$this->m->addserver(Boot::getInstance()->config->memcache->host, Boot::getInstance()->config->memcache->port);
	}

	/**
	 * @return Boot_Cache_Lib
	 */
	static public function getInstance() {
		return Boot::getInstance()->library->cache;
	}

	/**
	 * Получение данных из кеша
	 * @param $key
	 * @return array|string|stdClass
	 */
	static public function get($key) {
		return self::getInstance()->_get($key);
	}

	/**
	 * Получение данных из кеша
	 * @param $key
	 * @return array|string|bool
	 */
	public function _get($key) {
		if( $this->connected ) {
			//Запоминаем время начала
			$time = Boot::mktime();

			try {
				//Получаем данные
				$result = $this->m->get($this->prefix . $key);

				//Debug
				Boot::getInstance()->debug("  \x1b[36mCache (" . Boot::check_time($time) . "ms)\x1b[0m GET " . $this->prefix . $key . ($result === false ? " \x1b[31mfalse\x1b[0m" : " \x1b[32mtrue\x1b[0m"));

				return $result;
			} catch( Exception $e ) {
				$this->error($e->getMessage());
			}
		}
		return false;
	}

	/**
	 * Запись в кеш
	 * @param     $key
	 * @param     $value
	 * @param int $expire
	 * @return bool
	 */
	static public function set($key, $value, $expire = 3600) {
		return self::getInstance()->_set($key, $value, $expire);
	}

	/**
	 * Запись в кеш
	 * @param     $key
	 * @param     $value
	 * @param int $expire
	 * @return bool
	 */
	public function _set($key, $value, $expire = 3600) {
		if( $this->connected ) {
			//Запоминаем время начала
			$time = Boot::mktime();

			try {
				//Отправляем данные
				$result = $this->m->set($this->prefix . $key, $value, MEMCACHE_COMPRESSED, $expire);

				//Debug
				Boot::getInstance()->debug("  \x1b[36mCache (" . Boot::check_time($time) . "ms)\x1b[0m SET " . $this->prefix . $key);

				return $result;
			} catch( Exception $e ) {
				$this->error($e->getMessage());
			}
		}
		return false;
	}

	/**
	 * Удаление ключа
	 * @param $key
	 */
	static public function delete($key) {
		self::getInstance()->_delete($key);
	}

	/**
	 * @param $key
	 */
	public function _delete($key) {

		//Запоминаем время начала
		$time = Boot::mktime();

		try {
			//Очищаем данные
			$this->m->delete($this->prefix . $key);

			//Debug
			Boot::getInstance()->debug("  \x1b[36mCache (" . Boot::check_time($time) . "ms)\x1b[0m DELETE " . $this->prefix . $key);
		} catch(Exception $e) {
			$this->error($e->getMessage());
		}
	}

	/**
	 * При ошибке подключения
	 * @param $message
	 */
	private function error($message) {
		$this->connected = false;
		Boot::getInstance()->debug("  \x1b[31m" . $message . "\x1b[0m");
	}
}