<?php
/**
 * User: nixx
 * Date: 15.04.14
 * Time: 16:27
 */
class DB {

	/**
	 * @var mysql|postgres
	 */
	private $_db = null;

	/**
	 * Инстанс
	 * @var DB
	 */
	static private $_instance = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return DB
	 */
	static public function &getInstance() {

		if( !(self::$_instance instanceof DB) ) {
			self::$_instance = new DB();
		}
		return self::$_instance;
	}

	/**
	 * Конструктор
	 */
	public function __construct() {

		/**
		 * Получаем имя драйвера
		 * @var stdClass $db
		 */
		$db = Boot::getInstance()->config->db;
		$driver = $db->adapter;
		$host = $db->host;
		$port = $db->port;
		$user = $db->user;
		$pass = $db->password;
		$dbase = $db->dbase;

		//Инитим драйвер
		$this->_db = new $driver($host, $port, $user, $pass, $dbase);

		//Подключаемся к базе
		$this->_db->connect();
	}

	/**
	 * @return mysql|postgres
	 */
	public static function &getDB() {
		return self::getInstance()->_db;
	}
}