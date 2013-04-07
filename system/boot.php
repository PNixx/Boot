<?
class Boot {

	/**
	 * Инстанс
	 * @var Boot
	 */
	static private $_instance = null;

	/**
	 * Хранилище конфига
	 * @var Boot_Config
	 */
	public $config = null;

	/**
	 * Коннект к базе
	 * @var null
	 */
	public $_connect = null;

	/**
	 * Системная директория
	 * @var null
	 */
	public $root = null;

	private $_layout = true;

	private $_view = true;

	private $_nameLayout = null;

	/**
	 * @var translate
	 */
	public $translate = null;

	/**
	 * Список маршрутов
	 * @var null
	 */
	public $routes = null;

	/**
	 * Получаем инстанс
	 * @static
	 * @return Boot
	 */
	static public function getInstance() {

		if( !(self::$_instance instanceof Boot) ) {
			self::$_instance = new Boot();
		}
		return self::$_instance;
	}

	public function __construct() {
		define('SYSTEM_PATH', realpath(dirname(__FILE__)));
		date_default_timezone_set("Europe/Moscow");
	}

	/**
	 * Инициализация
	 * @return void
	 */
	public function run() {

		$this->root = realpath(dirname(__FILE__));
		header("Content-type: text/html; charset=UTF-8");

		//Загружаем класс ошибок
		require_once 'boot/exception/exception.php';
		require_once 'boot/exception/db.php';
		require_once 'boot/cookie.php';
		require_once 'boot/skey.php';
		require_once 'boot/auth.php';
		require_once 'boot/routes.php';
		require_once 'boot/flash.php';
		require_once 'boot/mail.php';

		//Устанавливаем отлавливатели ошибок
		set_error_handler( 'Boot_Exception::err_handler' );
		set_exception_handler("Boot_Exception::ex");

		ob_start();

		//Загружаем конфиг
		$this->config();

		//Создаём маршруты
		Boot_Routes::getInstance();

		//Инициализируем защищённый ключ
		Boot_Skey::getInstance();

		//Инициализируем авторизацию
		Boot_Auth::getInstance();

		//Получаем имя шаблона
		$this->_nameLayout = $this->config->default->layout ? $this->config->default->layout : "index";

		//Загружаем драйвер БД
		$this->load_model();

		//Загружаем модель предствлений
		$this->load_view();

		//Загружаем модель контроллера
		$this->load_controller();

		//Загружаем переводчик
		$this->load_translate();

		//Инициализируем переводчик
		$this->init_translate();

		//Устанавливаем путь подключения моделей
		set_include_path(APPLICATION_PATH . '/models/');

		spl_autoload_register(array(
			"Boot",
			"autoload"
		));

		//Инициализируем контроллер
		$this->init_controller();

		//Если не отключали вьюху, запускаем
		if( $this->_view ) {
			$view = $this->init_view();
		} else {
			$view = null;
		}

		//Загружаем шаблон
		$this->load_layout($view);

	}

	/**
	 * Инициализация для крона
	 * @return void
	 */
	public function cron() {

		$this->root = realpath(dirname(__FILE__));

		header("Content-type: text/html; charset=UTF-8");

		//Загружаем класс ошибок
		require_once 'boot/exception/exception_console.php';
		require_once 'boot/exception/db_console.php';

		//Устанавливаем отлавливатели ошибок
		set_error_handler( 'Boot_Exception::err_handler' );
		set_exception_handler("Boot_Exception::ex");

		//Загружаем конфиг
		$this->config();

		//Загружаем драйвер БД
		$this->load_model();

		//Загружаем переводчик
		$this->load_translate();

		//Инициализируем переводчик
		$this->init_translate();

		//Устанавливаем путь подключения моделей
		set_include_path(APPLICATION_PATH . '/models/');

		spl_autoload_register(array(
			"Boot",
			"autoload"
		));
	}

	/**
	 * Автозагрузка моделей
	 * @param $name
	 * @return bool
	 */
	static public function autoload($name) {

		//Имя файла
		if( preg_match("/^Model_.+_Row$/", $name) ) {
			$file = "row/" . strtolower(preg_replace("/^Model_(.+)_Row$/", "$1", $name)) . "_row.php";
		} elseif( preg_match("/^Model_.+$/", $name) ) {
			$file = strtolower(preg_replace("/^Model_/", "", $name)) . ".php";
		}

		if( isset($file) && file_exists(get_include_path() . $file) ) {
			require_once $file;
		} else {
			return false;
		}
	}

	/**
	 * Загрузка конфига
	 * @return void
	 */
	private function config() {

		//Загружаем класс конфига
		require_once 'boot/config.php';

		//Инициализируем класс настроек
		$this->config = new Boot_Config();

	}

	/**
	 * Загружаем драйвер доступа к базе
	 * @return void
	 */
	private function load_model() {

		//Если в конфиге был указан драйвер БД, подключаем и инициализируем
		if( $this->config->db->adapter && is_file($this->root . '/boot/db/driver/' . $this->config->db->adapter . '.php') ) {

			//Грузим драйвер
			require_once 'boot/db/driver/' . $this->config->db->adapter . '.php';
			require_once 'boot/db/model.php';
			require_once 'boot/db/row.php';
			require_once 'boot/db/select.php';

		} else {

			throw new Exception('Не найден драйвер базы данных: ' . $this->config->db->adapter);
		}
	}

	/**
	 * Загрузка модели представлений
	 * @return void
	 */
	private function load_view() {

		//Загружаем модель
		require_once 'boot/view.php';
	}

	/**
	 * Загрузка модели контроллера
	 * @return void
	 */
	private function load_controller() {

		//Загружаем модель
		require_once 'boot/controller.php';
	}

	/**
	 * Инициализируем контроллер
	 * @return void
	 */
	private function init_controller() {

		//Запускаем инстанс
		Boot_Controller::getInstance();
	}

	/**
	 * Инициализируем вьюху
	 * @return string
	 */
	private function init_view() {

		//Запускаем инстанс
		return Boot_View::getInstance()->run();
	}

	/**
	 * Загрузка шаблона
	 * @return void
	 */
	private function load_layout(&$view) {

		//Если шаблон не был отключен
		if( $this->_layout ) {

			require_once("boot/layout.php");

			Boot_Layout::getInstance()->run($view);
		} elseif( $this->_view ) {
			echo $view;
		}
	}

	/**
	 * Получение или установка текущего шаблона
	 * @param string $set
	 * @return void
	 */
	public function  layout($set = null) {
		if( $set ) {
			$this->_nameLayout = $set;
		} else {
			return $this->_nameLayout;
		}
	}

	/**
	 * Отключить подключение представления
	 * @return void
	 */
	public function disableView() {
		$this->_view = false;
	}

	/**
	 * Включение представления
	 * @return void
	 */
	public function enableView() {
		$this->_view = true;
	}

	/**
	 * Отключить подключение шаблона
	 * @return void
	 */
	public function disableLayout() {
		$this->_layout = false;
	}

	/**
	 * Включить подключение шаблона
	 * @return void
	 */
	public function enableLayout() {
		$this->_layout = true;
	}

	/**
	 * Загрузка переводчика
	 */
	public function load_translate() {

		//Загружаем модель
		require_once 'boot/translate.php';
	}

	/**
	 * Инициализация переводчика
	 */
	public function init_translate() {

		//Получаем из куков язык
		if( class_exists("Boot_Cookie") ) {
			$lang = Boot_Cookie::get("lang");
		} else {
			$lang = "ru";
		}

		if( $lang ) {
			$this->config->translate->lang = $lang;
		}

		$this->translate = new translate($this->config->translate->dir, $this->config->translate->lang);
	}

	/**
	 * Получение реального IP
	 * @static
	 */
	static public function getRealIp() {
		return isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : (isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR']);
	}
}