<?php
namespace Boot;

use Boot;
use Boot_Exception;
use Controller_Exception;
use Exception;
use Sass;

class Assets {

	/**
	 * Расширение
	 * @var string
	 */
	private $ext;

	/**
	 * Компиляция assets
	 * @var bool
	 */
	private $compile;

	/**
	 * Сжимает файлы
	 * @var bool
	 */
	private $compress = true;

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
	 * Вывод ошибки
	 * @param $message
	 */
	public static function error($message) {
		echo "\x1b[31m{$message}\x1b[0m\r\n";
		exit(127);
	}

	/**
	 * @param $compress
	 */
	public function setCompress($compress) {
		$this->compress = $compress;
	}

	/**
	 * Копирует указанные файла в bower.json
	 */
	static public function install() {
		if( file_exists(APPLICATION_ROOT . '/bower.json') ) {
			$bower = json_decode(file_get_contents(APPLICATION_ROOT . '/bower.json'));

			if( isset($bower->install->path) && isset($bower->install->sources) ) {
				foreach( $bower->install->sources as $source => $paths ) {
					foreach( $paths as $path ) {
						$ext = pathinfo($path, PATHINFO_EXTENSION);

						if( !file_exists(APPLICATION_ROOT . '/' . $path) ) {
							self::error('Asset ' . $path . ' not found');
						}
						if( empty($bower->install->path->$ext) ) {
							self::error('Asset copy path is not defined: install->path->' . $ext);
						}

						//Директория размещения ассета
						$dir = APPLICATION_ROOT . '/public/assets/' . str_replace('bower_components/', '', pathinfo($path, PATHINFO_DIRNAME));

						if( !is_dir($dir) ) {
							mkdir($dir, 0777, true);
						}
						copy(APPLICATION_ROOT . '/' . $path, $dir . '/' . pathinfo($path, PATHINFO_BASENAME));
					}
				}
			}
		}
	}

	/**
	 * @param $link
	 * @return null|string
	 * @throws Boot_Exception
	 * @throws Controller_Exception
	 */
	static public function find_path($link) {
		$directories = ['/bower_components', '/node_modules'];
		foreach( $directories as $directory ) {
			if( file_exists(APPLICATION_ROOT . $directory . '/' . $link) ) {
				return APPLICATION_ROOT . $directory . '/' . $link;
			}
		}
		throw new Controller_Exception('File not found: ' . $link, 404);
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
			if( preg_match("/^" . ($this->ext == "css" ? "\\s?\\*" : "\\/\\/") . "=\\s(require[^\\s]*)\\s([^\\s\n\r]+)/", $row, $matches) ) {

				//Выполняем функцию
				switch( $matches[1] ) {

					//Чтение директории
					case "require_tree":
						$this->readdir($matches[2]);
						break;

					//Чтение директории
					case "require_directory":
						$this->readdir($matches[2], false);
						break;

					//Чтение файла
					case "require":
						$this->data .= $this->readfile($matches[2]) . ($this->compile ? PHP_EOL : "");
						break;

					default:
						echo "  * \x1b[31m[Error] Asset unknown function, use: require_tree, require_directory, require\x1b[0m\r\n";
				}
			}
		}
	}

	/**
	 * Ищет указанный файл
	 * @param $path
	 * @return null|string
	 */
	public function full_path($path) {

		//Если добавляем из bower
		if( strpos($path, 'bower_components') === 0 ) {
			return realpath(APPLICATION_ROOT . '/' . $path);
		}
		$directories = ['/bower_components', '/node_modules'];
		foreach( $directories as $directory ) {
			if( file_exists(APPLICATION_ROOT . $directory . '/' . $path) ) {
				return realpath(APPLICATION_ROOT . $directory . '/' . $path);
			}
		}
		return realpath(APPLICATION_PATH . '/assets/' . $this->ext . '/' . $path);
	}

	/**
	 * Normalize path
	 *
	 * @param   string  $path
	 * @param   string  $separator
	 * @return  string  normalized path
	 */
	public function normalizePath($path, $separator = '\\/') {
		// Remove any kind of funky unicode whitespace
		$normalized = preg_replace('#\p{C}+|^\./#u', '', $path);

		// Path remove self referring paths ("/./").
		$normalized = preg_replace('#/\.(?=/)|^\./|\./$#', '', $normalized);

		// Regex for resolving relative paths
		$regex = '#\/*[^/\.]+/\.\.#Uu';

		while (preg_match($regex, $normalized)) {
			$normalized = preg_replace($regex, '', $normalized);
		}

		if (preg_match('#/\.{2}|\.{2}/#', $normalized)) {
			throw new \LogicException('Path is outside of the defined root, path: [' . $path . '], resolved: [' . $normalized . ']');
		}

		return trim($normalized, $separator);
	}

	/**
	 * Чтение деректории рукурсивно
	 * @param $dir
	 * @param bool $recursive
	 */
	public function readdir($dir, $recursive = true) {
		$realpath = $this->full_path($dir);
		if( $f = opendir($realpath) ) {

			//Проходим по списку файлов
			while (false !== ($entry = readdir($f))) {
				if( !in_array($entry, ['.', '..']) ) {

					//Строим полный путь до файла
					$path = $this->normalizePath($dir . '/' . $entry);

					//Если это директория
					if( is_dir($realpath . '/' . $entry) ) {

						//Если нужно проходить по дереву
						if( $recursive ) {
							$this->readdir($path, true);
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
	 * @throws \Exception
	 * @return string
	 */
	public function readfile($path) {

		//Получаем расширение файла
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		//Если расширение входим в массив
		if( in_array($ext, [$this->ext, 'scss']) ) {
			if( $this->compile ) {
				$path_new = $this->full_path($path);
				if( !$path_new ) {
					echo 'File ' . $path . ' not be found' . PHP_EOL;
					exit(127);
				}
				$path = $path_new;

				//SASS
				//todo сделать кеширование для development
				if( $ext == 'scss' ) {

					//Компилируем SASS файл
					$sass = new Sass();
					$sass->setStyle($this->compress ? Sass::STYLE_COMPRESSED : Sass::STYLE_EXPANDED);
					$sass->setIncludePath(APPLICATION_ROOT);
					$sass->setComments(!$this->compress);

					$filename = pathinfo($path, PATHINFO_FILENAME);

					//Если компилируем готовые ассеты или в конфиге включен режим префиксов
					if( $this->compress || Boot::getInstance()->config->autoprefixer ) {
						file_put_contents('/tmp/' . $filename . '.css', $sass->compileFile($path));

						//Добавляем префиксы
						//$result = system('postcss --use autoprefixer -o /tmp/' . $filename . '.out.css /tmp/' . $filename . '.css 2>&1', $r);
						$command = 'postcss --use autoprefixer -o /tmp/' . $filename . '.out.css /tmp/' . $filename . '.css';
						$pipes = [];
						$process = proc_open($command, [
								0 => ['pipe', 'r'],
								1 => ['pipe', 'w'],
								2 => ['pipe', 'w'],
							], $pipes);

						stream_set_blocking($pipes[1], true);
						stream_set_blocking($pipes[2], true);

						if( is_resource($process) ) {

							$output = stream_get_contents($pipes[1]);
							$error = stream_get_contents($pipes[2]);

							fclose($pipes[1]);
							fclose($pipes[2]);

							proc_close($process);

							if( $error ) {
								throw new Boot_Exception($error);
							}
						}

						$css = file_get_contents('/tmp/' . $filename . '.out.css');
						unlink('/tmp/' . $filename . '.out.css');
						unlink('/tmp/' . $filename . '.css');
						return $css;
					} else {
						return $sass->compileFile($path);
					}
				}

				//Выполняем для обычных css файлов
				if( $this->compress ) {
					return $this->compress(file_get_contents($path));
				} else {
					return file_get_contents($path);
				}
			} else {
				return $this->developerTag($path);
			}
		}
		return null;
	}

	/**
	 * Сразу вывод
	 * @param $path
	 * @return string
	 * @throws \Exception
	 */
	public function developerTag($path) {
		switch( $this->ext ) {

			case "css":
				return "<link href='/assets/css/" . preg_replace('/\.scss$/i', '.css', $path) . "' rel='stylesheet' type='text/css'>" . PHP_EOL;
				break;

			case "js":
				return "<script src='/assets/js/" . $path . "' type=\"text/javascript\"></script>" . PHP_EOL;
				break;

			default:
				throw new Exception("Wrong file extension");
		}
	}

	/**
	 * Чтение данных файла для продакшен сервера
	 * @param      $path
	 * @param bool $async
	 * @return string
	 * @throws Exception
	 */
	public function readfile_production($path, $async = false) {
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
					return "<script src='/assets/" . pathinfo($file, PATHINFO_BASENAME) . "' type=\"text/javascript\"" . ($async ? 'async="async"' : "") . "></script>" . PHP_EOL;
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
				return \Minify_CSS_Compressor::process($buffer);

			case "js":
				if( class_exists("JSMin", false) == false ) {
					require_once APPLICATION_ROOT . "/system/boot/extension/JSMin.php";
				}
				return \JSMin::minify($buffer);
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