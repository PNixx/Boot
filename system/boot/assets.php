<?php
/**
 * User: nixx
 * Date: 03.09.14
 * Time: 23:37
 */
class Boot_Assets {

	/**
	 * Расширение
	 * @var string
	 */
	private $ext;

	/**
	 * Компиляция assests
	 * @var bool
	 */
	private $compile;

	/**
	 * @var bool
	 */
	private $debug;

	/**
	 * Хранилище данных
	 * @var string
	 */
	private $data;

	/**
	 * Создание ассетов
	 * @param $ext
	 * @param bool $compile
	 * @param bool $debug
	 */
	public function __construct($ext, $compile = false, $debug = false) {

		//Запоминаем расширение
		$this->ext = $ext;
		$this->compile = $compile;
		$this->debug = $debug;
	}

	/**
	 * Сбор всех файлов
	 */
	public function read_all_assets() {

		//Проходим по списку файлов
		foreach(glob(APPLICATION_PATH . '/assets/*.' . $this->ext) as $path) {
			$this->data = '';

			//Собираем файл
			$this->read_asset_file($path);

			//Если компилируем ассеты
			if( $this->compile ) {
				$file = APPLICATION_ROOT . "/public/assets/" . pathinfo($path, PATHINFO_FILENAME) . "-" . md5($this->data) . "." . $this->ext;
				if( $this->debug ) {
					echo "Make file: " . $file . PHP_EOL;
				}
				file_put_contents($file, $this->data);
				file_put_contents(APPLICATION_ROOT . "/public/assets/" . pathinfo($path, PATHINFO_BASENAME), $this->data);
			}
		}
	}

	/**
	 * Собирает файл ассетов
	 * @param $path
	 */
	public function read_asset_file($path) {

		//Открываем файл на чтение
		$f = fopen($path, 'r');
		while( ($row = fgets($f, 4096)) !== false ) {

			//Если попадает под маску
			if( preg_match("/^" . ($this->ext == "css" ? "\s?\*" : "\/\/") . "=\s(require[^\s]*)\s([^\s\n\r]+)/", $row, $matches) ) {

				//Выполняем функцию
				switch( $matches[1] ) {

					//Чтение директории
					case "require_tree":
						$this->readdir(realpath(APPLICATION_PATH . '/assets/' . $this->ext . '/' . $matches[2]));
						break;

					//Чтение директории
					case "require_directory":
						$this->readdir(realpath(APPLICATION_PATH . '/assets/' . $this->ext . '/' . $matches[2]), false);
						break;

					//Чтение файла
					case "require":
						$this->data .= $this->readfile(realpath(APPLICATION_PATH . '/assets/' . $this->ext . '/' . $matches[2])) . ($this->compile ? PHP_EOL : "");
						break;

					default:
						echo "  * \x1b[31m[Error] Asset unknown function, use: require_tree, require_directory, require\x1b[0m\r\n";
				}
			}
		}
	}

	/**
	 * Чтение деректории рукурсивно
	 * @param $dir
	 * @param bool $recursive
	 */
	public function readdir($dir, $recursive = true) {
		if( $f = opendir($dir) ) {

			//Проходим по списку файлов
			while (false !== ($entry = readdir($f))) {
				if( !in_array($entry, ['.', '..']) ) {

					//Строим полный путь до файла
					$path = $dir . '/' . $entry;

					//Если это директория
					if( is_dir($path) ) {

						//Если нужно проходить по дереву
						if( $recursive ) {
							$this->readdir($path, $this->data);
						}
					} else {
						$this->data .= $this->readfile($path) . ($this->compile ? PHP_EOL : "");
					}
				}
			}
		}
	}

	/**
	 * Чтение данных файла
	 * @param $path
	 * @throws Exception
	 * @return string
	 */
	public function readfile($path) {
		if( strtolower(pathinfo($path, PATHINFO_EXTENSION)) == $this->ext ) {
			if( $this->compile ) {
				return $this->compress(file_get_contents($path));
			} else {
				switch( $this->ext ) {

					case "css":
						return "<link href='" . str_replace(APPLICATION_PATH, "", $path) . "' rel='stylesheet' type='text/css'>" . PHP_EOL;
						break;

					case "js":
						return "<script src='" . str_replace(APPLICATION_PATH, "", $path) . "' type=\"text/javascript\"></script>" . PHP_EOL;
						break;

					default:
						throw new Exception("Wrong file extension");
				}
			}
		}
		return null;
	}

	/**
	 * Чтение данных файла для продакшен сервера
	 * @param $path
	 * @throws Exception
	 * @return string
	 */
	public function readfile_production($path) {
		if( strtolower(pathinfo($path, PATHINFO_EXTENSION)) == $this->ext ) {

			//Ищем скомпилированный файл
			try {
				$files = glob(APPLICATION_ROOT . "/public/assets/" . pathinfo($path, PATHINFO_FILENAME) . "-*." . $this->ext);
				$files = array_combine(array_map("filemtime", $files), $files);
				krsort($files);
				$file = array_values($files)[0];
			} catch( Exception $e ) {
				throw new Exception('Assets was not compiled!');
			}

			//Для разных типов
			switch( $this->ext ) {

				case "css":
					return "<link href='/assets/" . pathinfo($file, PATHINFO_BASENAME) . "' rel='stylesheet' type='text/css'>" . PHP_EOL;
					break;

				case "js":
					return "<script src='/assets/" . pathinfo($file, PATHINFO_BASENAME) . "' type=\"text/javascript\"></script>" . PHP_EOL;
					break;

				default:
					throw new Exception("Wrong file extension");
			}
		}
		return null;
	}

	/**
	 * Компрессия файлов
	 * @param $buffer
	 * @return mixed|string
	 */
	private function compress($buffer) {

		switch( $this->ext ) {
			case "css":
				if( class_exists("Minify_CSS_Compressor", false) == false ) {
					require_once APPLICATION_ROOT . "/system/boot/extension/Minify_CSS_Compressor.php";
				}
				return Minify_CSS_Compressor::process($buffer);

			case "js":
				if( class_exists("JSMin", false) == false ) {
					require_once APPLICATION_ROOT . "/system/boot/extension/JSMin.php";
				}
				return JSMin::minify($buffer);
		}
		return "";
	}

	/**
	 * Преобразование в строку
	 * @return string
	 */
	public function __toString() {
		return $this->data;
	}
} 