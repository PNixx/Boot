<?php
/**
 * Date: 19.03.15
 * Time: 16:33
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
abstract class Boot_Uploader_Abstract {

	/**
	 * @var ActiveRecord
	 */
	protected $model;

	/**
	 * @var string
	 */
	private $_column;

	/**
	 * Прежнее значение колонки
	 * @var string
	 */
	private $_value;

	/**
	 * Версии файлов
	 * [
	 *  "version_name" => ["resize_to_fit", [100,100]]
	 * ]
	 * Accept processes: resize_to_fill, resize_to_fit, resize
	 * @require "intervention/image": "~2.1"
	 * @var array
	 */
	protected $version = [];

	/**
	 * Constructor
	 * @param ActiveRecord $model
	 * @param string       $column
	 * @param string       $value
	 */
	public function __construct(ActiveRecord &$model, $column, $value) {
		$this->model = $model;
		$this->_column = $column;
		$this->_value = $value;
	}

	/**
	 * Получение имени таблицы
	 * @return string
	 */
	protected function getTable() {
		return strtolower(str_ireplace("Model_", "", get_class($this->model)));
	}

	/**
	 * Директория загрузки файлов
	 * @return string
	 */
	protected function storeDir() {
		return "uploads/" . $this->getTable();
	}

	/**
	 * Получение имени файла
	 * @param null $version
	 * @return null|string
	 */
	protected function filename($version = null) {
		$filename = null;

		if( $this->_value ) {
			$filename = $this->_value;
		} elseif( $this->original_filename() ) {
			$this->_value = uniqid() . '-' . md5(uniqid('boot')) . '.' . pathinfo($this->original_filename(), PATHINFO_EXTENSION);
			$filename = $this->_value;
		}

		//Если указана версия файла
		if( $version && $filename ) {
			$filename = pathinfo($filename, PATHINFO_FILENAME) . '_' . $version . '.' . pathinfo($filename, PATHINFO_EXTENSION);
		}
		return $filename;
	}

	/**
	 * Оригинальное имя загружаемого файла
	 * @return null|string
	 */
	private function original_filename() {
		if( static::fetchUploadFile($this->getTable(), $this->_column) ) {
			return $_FILES[$this->getTable()]['name'][$this->_column];
		}
		return null;
	}

	/**
	 * Проверяет, загружается ли файл
	 * @param $table
	 * @param $column
	 * @return bool
	 */
	public static function fetchUploadFile($table, $column) {
		return !empty($_FILES[$table]['name'][$column]) && $_FILES[$table]['error'][$column] == UPLOAD_ERR_OK;
	}

	/**
	 * Проверка директории
	 */
	private function fetchDirectory() {

		//Строим директорию
		$dir = pathinfo($this->path(), PATHINFO_DIRNAME);

		//Если директория не существует, создаем
		if( file_exists($dir) == false ) {
			mkdir($dir, 0775, true);
			chmod($dir, 0775);
		}
	}

	/**
	 * Полный путь к файлу
	 * @param string $version
	 * @return string|null
	 */
	public function path($version = null) {
		if( $this->present() ) {
			return APPLICATION_ROOT . '/public/' . $this->storeDir() . '/' . $this->filename($version);
		}
		return null;
	}

	/**
	 * Получить ссылку на файл
	 * @param null $version
	 * @return string
	 */
	public function url($version = null) {
		return '/' . $this->storeDir() . '/' . $this->filename($version);
	}

	/**
	 * Запись существует?
	 * @return bool
	 */
	public function present() {
		return (bool) $this->_value;
	}

	/**
	 * Загружает файл
	 */
	public function uploadFile() {

		//Проверяем, был ли загружен файл
		if( $this->original_filename() ) {
			$this->fetchDirectory();

			//Дебаг
			Boot::getInstance()->debug("  * Create image version: \x1b[33m" . $this->storeDir() . '/' .$this->filename() . "\x1b[0m");

			//Загружаем основной файл
			$this->moveOriginalFile();

			//Создаем версии файлов версии
			$this->create_versions($this->path());
		}
	}

	/**
	 * Путь к временному файлу загрузки
	 * @return mixed
	 */
	protected function uploadTempFile() {
		return $_FILES[$this->getTable()]['tmp_name'][$this->_column];
	}

	/**
	 * Перемещаем оригинальный файл
	 */
	protected function moveOriginalFile() {
		move_uploaded_file($this->uploadTempFile(), $this->path());
	}

	/**
	 * Удаление файла
	 */
	public function remove() {

		//Если файл существует
		if( $this->present() ) {

			//Удаляем разные версии
			foreach( glob(pathinfo($this->path(), PATHINFO_DIRNAME) . '/' . pathinfo($this->path(), PATHINFO_FILENAME) . '_*.' . pathinfo($this->path(), PATHINFO_EXTENSION)) as $file ) {
				unlink($file);
			}

			//Удаляем оригинальный файл
			if( file_exists($this->path()) ) {
				unlink($this->path());
			}

			//Очищаем старый файл
			$this->_value = null;
		}
	}

	/**
	 * Заполняет необходимый размер фотографией
	 * @param $original_file
	 * @param $name
	 * @param $size
	 */
	private function processFill($original_file, $name, $size) {

		/**
		 * create an image manager instance with favored driver
		 * @var Intervention\Image\ImageManager $manager;
		 */
		$class = "Intervention\\Image\\ImageManager";
		$manager = new $class(array('driver' => 'imagick'));

		// to finally create image instances
		$canvas = $manager->canvas($size[0], $size[1]);

		//Загружаем изображение
		$photo = $manager->make($original_file);

		//Изменяем размер
		$width = $photo->getWidth();
		$height = $photo->getHeight();
		if( $size[0] / $width > $size[1] / $height ) {
			// prevent possible upsizing
			$photo->resize($size[0], null, function($constraint) {
				$constraint->aspectRatio();
			});
		} else {
			// prevent possible upsizing
			$photo->resize(null, $size[1], function($constraint) {
				$constraint->aspectRatio();
			});
		}

		//Запиливаем изображение на холст
		$canvas->insert($photo, 'center');

		//Сохраняем изображение
		$canvas->save($this->path($name), 80);
	}

	/**
	 * @param $original_file
	 * @param $name
	 * @param $size
	 */
	private function processResize($original_file, $name, $size) {

		/**
		 * create an image manager instance with favored driver
		 * @var Intervention\Image\ImageManager $manager;
		 */
		$class = "Intervention\\Image\\ImageManager";
		$manager = new $class(array('driver' => 'imagick'));

		// to finally create image instances
		$photo = $manager->make($original_file);

		//Изменяем размер
		$photo->resize($size[0], $size[1]);

		//Сохраняем изображение
		$photo->save($this->path($name), 80);
	}

	/**
	 * Умещает фотографию в нужный размер
	 * @param $original_file
	 * @param $name
	 * @param $size
	 */
	private function processFit($original_file, $name, $size) {

		/**
		 * create an image manager instance with favored driver
		 * @var Intervention\Image\ImageManager $manager;
		 */
		$class = "Intervention\\Image\\ImageManager";
		$manager = new $class(array('driver' => 'imagick'));

		// to finally create image instances
		$photo = $manager->make($original_file);

		//Изменяем размер
		$width = $photo->getWidth();
		$height = $photo->getHeight();
		if( $size[0] / $width > $size[1] / $height ) {
			// prevent possible upsizing
			$photo->resize(null, $size[1], function($constraint) {
				$constraint->aspectRatio();
			});
		} else {
			// prevent possible upsizing
			$photo->resize($size[0], null, function($constraint) {
				$constraint->aspectRatio();
			});
		}

		//Сохраняем изображение
		$photo->save($this->path($name), 80);
	}

	/**
	 * Создание версий файлов
	 * @throws Boot_Exception
	 */
	public function recreate_versions() {

		if( $this->present() ) {
			//Удаляем файлы
			foreach( glob(pathinfo($this->path(), PATHINFO_DIRNAME) . '/' . pathinfo($this->path(), PATHINFO_FILENAME) . '_*.' . pathinfo($this->path(), PATHINFO_EXTENSION)) as $file ) {
				unlink($file);
			}

			//Создаем версии
			$this->create_versions($this->path());
		}
	}

	private function create_versions($original_file) {

		//Если нужно создавать версии
		if( !empty($this->version) ) {

			//Проходим по версиям
			foreach( $this->version as $name => $process ) {

				//Дебаг
				Boot::getInstance()->debug("  * Create image version: \x1b[33m" . $this->storeDir() . '/' . $this->filename($name) . "\x1b[0m");

				//Выбираем действие
				switch( $process[0]) {

					//Fill
					case "resize_to_fill":
						$this->processFill($original_file, $name, $process[1]);
						break;

					//Fit
					case "resize_to_fit":
						$this->processFit($original_file, $name, $process[1]);
						break;

					//Resize
					case "resize":
						$this->processResize($original_file, $name, $process[1]);
						break;

					//Неизвестный процесс
					default:
						throw new Boot_Exception("Unknown uploader process name: " . $process[0]);
				}
			}
		}
	}

	/**
	 * Преобразование в строку
	 * @return null|string
	 */
	public function __toString() {
		return (string)$this->filename();
	}
}