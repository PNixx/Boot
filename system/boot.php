<?
/**
 * Class Boot
 * @method void debug(string $logger, bool $error = false)
 */
class Boot {

	/**
	 * Инстанс
	 * @var Boot
	 */
	static private $_instance = null;

	/**
	 * Хранилище конфига
	 * @var Boot_Config|stdClass
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
	 * Запоминаем время начала
	 * @var int
	 */
	private $_time_start;

	/**
	 * @var Boot_Library $library
	 */
	public $library;

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

	/**
	 * Конструктор
	 */
	public function __construct() {

		//Запоминаем время начала
		$this->_time_start = Boot::mktime();

		/**
		 * @const SYSTEM_PATH
		 */
		define('SYSTEM_PATH', realpath(dirname(__FILE__)));
		date_default_timezone_set("Europe/Moscow");

		//Composer
		if( file_exists(APPLICATION_ROOT . '/vendor/autoload.php') ) {
			require APPLICATION_ROOT . '/vendor/autoload.php';
		}
	}

	/**
	 * Псевдо вызов функций
	 * @param $name
	 * @param $params
	 */
	public function __call($name, $params) {

		//Если выполняем дебаг
		if( $name == "debug" && class_exists("Boot_Debug_Lib", false) && (APPLICATION_ENV == 'development' || $this->config->debug_production ) ) {
			Boot_Debug_Lib::log($params[0], isset($params[1]) ? $params[1] : false);
		}
	}

	/**
	 * Инициализация
	 * @throws Exception
	 * @return void
	 */
	public function run() {

		$this->root = realpath(dirname(__FILE__));
		header("Content-type: text/html; charset=UTF-8");

		//Загружаем классы
		require_once 'boot/exception/exception.php';
		require_once 'boot/exception/db.php';
		require_once 'boot/cookie.php';
		require_once 'boot/skey.php';
		require_once 'boot/routes.php';
		require_once 'boot/flash.php';
		require_once 'boot/mail.php';
		require_once 'boot/assets.php';
		require_once 'boot/params.php';

		//Инклудим треды
		require_once SYSTEM_PATH . '/boot/trait/controller.php';
		foreach(glob(SYSTEM_PATH . '/boot/trait/' . '*.php') as $path) {
			require_once $path;
		}

		//Инклудим абстрактные классы
		foreach(glob(SYSTEM_PATH . '/boot/abstract/' . '*.php') as $path) {
			require_once $path;
		}

		//Инклудим интерфейсы
		foreach(glob(SYSTEM_PATH . '/boot/interface/' . '*.php') as $path) {
			require_once $path;
		}
		require_once 'library/log.php';

		//Устанавливаем отлавливатели ошибок
		set_error_handler('Boot_Exception::err_handler');
		set_exception_handler("Boot_Exception::ex");
		ob_start("Boot_Exception::shutdown");

		//Загружаем конфиг
		$this->config();

		//Создаём маршруты
		Boot_Routes::getInstance();

		//Инициализируем защищённый ключ
		Boot_Skey::getInstance();

		//Получаем имя шаблона
		$this->_nameLayout = $this->config->default->layout ? $this->config->default->layout : "index";

		//Загружаем драйвер БД
		$this->load_model();

		//Загружаем модель предствлений
		$this->load_view();

		//Загружаем модель контроллера
		$this->load_controller();

		//Устанавливаем путь подключения моделей
		set_include_path(APPLICATION_PATH);

		spl_autoload_register(array(
			"Boot",
			"autoload"
		));

		//Загружаем библиотеки
		$this->load_library();

		//Debug
		if( preg_match("/\\.(css|js)$/", $_SERVER['REQUEST_URI']) == false ) {
			$this->debug(PHP_EOL . PHP_EOL . $_SERVER['REQUEST_METHOD'] . " \"" . $_SERVER['REQUEST_URI'] . "\" for " . self::getRealIp() . " at " . date("Y-m-d H:i:s O"));
		}

		try {
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

			//Выводим содержимое буфера
			ob_end_flush();
		} catch( Exception $e ) {
			ob_end_clean();
			throw $e;
		}

		//Завершение работы скрипта
		$this->end();
	}

	/**
	 * Завершение работы скрипта
	 */
	public function end() {
		//Выводим время работы
		Boot::getInstance()->debug("  Completed (" . Boot::check_time($this->_time_start) . "ms)");
	}

	/**
	 * Инициализация для крона
	 * @return void
	 */
	public function cron() {
		define('APPLICATION_CLI', true);

		$this->root = realpath(dirname(__FILE__));

		header("Content-type: text/html; charset=UTF-8");

		//Загружаем класс ошибок
		require_once 'boot/exception/exception.php';
		require_once 'boot/exception/db.php';

		//Инклудим треды
		require_once SYSTEM_PATH . '/boot/trait/controller.php';
		foreach(glob(SYSTEM_PATH . '/boot/trait/' . '*.php') as $path) {
			require_once $path;
		}

		//Инклудим абстрактные классы
		foreach(glob(SYSTEM_PATH . '/boot/abstract/' . '*.php') as $path) {
			require_once $path;
		}

		//Инклудим интерфейсы
		foreach(glob(SYSTEM_PATH . '/boot/interface/' . '*.php') as $path) {
			require_once $path;
		}
		require_once 'library/log.php';

		//Устанавливаем отлавливатели ошибок
		set_error_handler( 'Boot_Exception::err_handler' );
		set_exception_handler("Boot_Exception::ex");
		register_shutdown_function(function() {
			$error = error_get_last();
			if( $error ) {
				Boot_Exception::err_handler($error['type'], $error['message'], $error['file'], $error['line'], null);
			} else {
				Boot::getInstance()->end();
			}
		});

		//Загружаем конфиг
		$this->config();

		//Загружаем драйвер БД
		$this->load_model();

		//Устанавливаем путь подключения моделей
		set_include_path(APPLICATION_PATH);

		spl_autoload_register(array(
			"Boot",
			"autoload"
		));

		//Загружаем библиотеки
		$this->load_library();

		//Debug
		$this->debug(PHP_EOL . "Console at " . date("Y-m-d H:i:s O"));
		if( isset($_SERVER['argv']) ) {
			$this->debug("  File: " . implode(" ", $_SERVER['argv']));
		}
	}

	/**
	 * Автозагрузка моделей
	 * @param $name
	 * @return bool
	 */
	static public function autoload($name) {

		//Загрузка моделей
		if( preg_match("/^Model_.+_Collection$/", $name) ) {
			$file = "models/collection/" . strtolower(preg_replace("/^Model_(.+)_Collection$/", "$1", $name)) . "_collection.php";
		} elseif( preg_match("/^Model_.+$/", $name) ) {
			$file = 'models/' . strtolower(preg_replace("/^Model_/", "", $name)) . ".php";
		}

		//Загрузка контроллеров модулем
		if( preg_match("/^(.+?)_(.+)Controller$/", $name, $match) ) {
			$file = 'controllers/' . strtolower($match[1]) . "/" . strtolower($match[2]) . ".php";
		} elseif( preg_match("/^(.+)Controller$/", $name, $match) ) {
			$file = 'controllers/' . strtolower($match[1]) . ".php";
		}

		//Загрузка загрузчиков файлов
		if( preg_match("/(.+)Uploader$/", $name, $match) ) {
			$file = 'uploader/' . strtolower($match[1]) . ".php";
		}

		//Загрузка загрузчиков файлов
		if( preg_match("/(.+)Mailer$/", $name, $match) ) {
			$file = 'mailers/' . strtolower($match[1]) . ".php";
		}

		if( isset($file) ) {
			$paths = explode(PATH_SEPARATOR, get_include_path());
			foreach( $paths as $p ) {
				if( file_exists(realpath($p) . '/' . $file) ) {
					require_once $file;
					break;
				}
			}
		}
		return false;
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
	 * @throws Exception
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
			require_once 'boot/db/collection.php';
			require_once 'boot/db/db.php';

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
	 * @param $view
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
	 * @return string
	 */
	public function  layout($set = null) {
		if( $set ) {
			$this->_nameLayout = $set;
		}
		return $this->_nameLayout;
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
	 * Загрузка библиотек
	 * @throws Boot_Exception
	 */
	public function load_library() {

		//Подключаем клас библиотек
		require_once "boot/library.php";

		//Инициализируем библиотеки
		$this->library = new Boot_Library();

		//Файл библиотеки
		$file = APPLICATION_PATH . "/config/library.conf";

		//Проверяем доступность
		if( file_exists($file) == false ) {
			throw new Boot_Exception("Config library is not found: " . $file);
		}

		//Получаем список библиотек
		$libs = explode(PHP_EOL, file_get_contents($file));

		//Проходим по списску
		foreach( $libs as $lib ) {
			if( trim($lib) ) {

				//Проверяем существование файла
				if( file_exists(APPLICATION_ROOT . "/system/library/" . $lib . ".php") == false ) {
					throw new Boot_Exception("Library not found: $lib");
				}

				//Подключаем файл
				require_once APPLICATION_ROOT . "/system/library/" . $lib . ".php";

				//Проверяем существование класса
				$class = "Boot_" . ucfirst($lib) . "_Lib";
				if( class_exists($class, false) == false ) {
					throw new Boot_Exception("Class library not found: $class");
				}

				//Инициализируем
				if( $class::$is_init ) {
					$this->library->$lib = new $class();
					$this->library->$lib->key = $lib;
				}
			}
		}
	}

	/**
	 * Получение реального IP
	 * @static
	 */
	static public function getRealIp() {
		return isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : (isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR']);
	}

	/**
	 * @return float
	 */
	static public function mktime() {
		return microtime(true);
	}

	static public function check_time($mktime) {
		return round((Boot::mktime() - $mktime) * 1000, 2);
	}
}