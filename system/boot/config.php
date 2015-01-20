<?
class Boot_Config {

	/**
	 * Хранилище настроек
	 * @var array
	 */
	private $_config = null;

	/**
	 * Достаём данные из конфига
	 * @param  $name
	 * @return array|null
	 */
	public function __get($name) {

		if( isset($this->_config->$name) ) {
			return $this->_config->$name;
		}

		return null;
	}

	/**
	 * Читаем файл конфига, и помещаем его в хранилище
	 */
	public function __construct() {

		//Объявляем конфиг классом
		$this->_config = new stdClass();

		if( getenv('APPLICATION_ENV') && getenv('APPLICATION_ENV') != 'production' ) {
			define('APPLICATION_ENV', getenv('APPLICATION_ENV'));
		} elseif( file_exists(APPLICATION_ROOT . "/.env") ) {
			define('APPLICATION_ENV', file_get_contents(APPLICATION_ROOT . "/.env"));
		} elseif( file_exists(APPLICATION_ROOT . "/public/.htaccess") ) {

			//Читаем файл
			$htaccess = file_get_contents(APPLICATION_ROOT . "/public/.htaccess");
			if( $htaccess ) {
				$htaccess = explode("\n", $htaccess);
			}

			//Проходим по списску ищем строку с APPLICATION_ENV
			if( is_array($htaccess) ) {
				foreach($htaccess as $row) {
					if( preg_match("/^SetEnv APPLICATION_ENV\s+(.*?)$/", $row, $match) ) {
						define('APPLICATION_ENV', trim($match[1]));
					}
				}
			}
		}
		if( getenv('APPLICATION_ENV') === false && defined("APPLICATION_ENV") === false ) {
			define('APPLICATION_ENV', "development");
		}

		if( is_file(APPLICATION_PATH . '/config/application.ini') ) {

			//Читаем файл
			$config = parse_ini_file(APPLICATION_PATH . '/config/application.ini', true);

			if( isset($config['production']) == false ) {
				throw new Exception('Ключ production в конфиге не найден: config.ini');
			}

			//Если есть ключ разработки
			if( APPLICATION_ENV && isset($config[APPLICATION_ENV]) ) {
				$config = array_merge($config['production'], $config[APPLICATION_ENV]);
			} else {
				$config = $config['production'];
			}

			foreach($config as $key => $param) {

				//Чистим лишние пробелы
				$key = trim($key);
				if( !is_array($param) ) {
					$param = trim($param);
				}

				//Если строка
				if( $param === "true" || $param === "false" ) {
					$param = $param === "true" ? true : false;
				}
				$param = str_replace("[APPLICATION_ROOT]", APPLICATION_ROOT, $param);

				//Записываем данные в массив
				if( strstr($key, '.') == false ) {

					$this->_config->$key = $param;
				} else {

					//Разбиваем строку
					$keys = explode('.', $key);

					//Добавляем в конфиг
					$array = array();
					for($i = count($keys) - 1; $i >= 0; $i--) {
						if( $i == count($keys) - 1 ) {
							$array[$keys[$i]] = $param;
						} else {
							$array = array($keys[$i] => $array);
						}
					}

					//Преобразовываем связку массивов в объекты
					$this->_config = $this->object_megre_recursive($this->_config, $array);
				}

			}
		} else {
			throw new Exception('Файл конфига не найден: ' . APPLICATION_PATH . '/config/application.ini');
		}
	}

	/**
	 * Добавление в оъект
	 * @param $array
	 * @param $input
	 * @return stdClass
	 */
	private function object_megre_recursive($array, $input) {

		//Если класс еще не объявлен
		if( $array == null ) {
			$array = new stdClass();
		}

		if( is_object($input) || is_array($input) ) {
			foreach($input as $i => $value) {

				if( !isset($array->$i) ) {
					$array->$i = null;
				}

				if( is_object($input) ) {
					$array->$i = $this->object_megre_recursive($array->$i, $value);
				} elseif( is_array($value) ) {
					$array->$i = $this->object_megre_recursive($array->$i, (object)$value);
				} else {
					$array->$i = $value;
				}
			}
		} else {
			$array = $input;
		}
		return $array;
	}
}