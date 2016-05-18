<?php
namespace Boot\Abstracts {

use ActiveRecord;
use Boot;
use Boot_Exception;

	/**
 * Date: 19.03.15
 * Time: 16:33
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
abstract class Uploader {

	/**
	 * @var ActiveRecord
	 */
	protected $model;

	/**
	 * @var string
	 */
	protected $_column;

	/**
	 * Прежнее значение колонки
	 * @var string
	 */
	protected $_value;

	/**
	 * Качество сохраняемых изображений
	 * @var int
	 */
	protected $quality = 80;

	/**
	 * Версии файлов
	 * [
	 *  "version_name" => ["resize_to_fit", [100,100]]
	 * ]
	 * Accept processes: resize_to_fill, resize_to_fit, resize
	 * "version_name" = "original" -> Изменяет оригинальное изображение при загрузке
	 * @require "intervention/image": "~2.1"
	 * @var array
	 */
	protected $version = [];

	//Событие перед загрузкаой
	protected function before_upload() {
	}

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
			$this->generate_filename($this->original_filename());
			$filename = $this->_value;
		}

		//Если указана версия файла
		if( $version && $filename ) {
			$filename = pathinfo($filename, PATHINFO_FILENAME) . '_' . $version . '.' . pathinfo($filename, PATHINFO_EXTENSION);
		}
		return $filename;
	}

	/**
	 * Генерирует уникальное имя файла
	 * @param $file
	 */
	private function generate_filename($file) {
		$this->_value = uniqid() . '-' . md5(uniqid('boot')) . '.' . pathinfo($file, PATHINFO_EXTENSION);
	}

	/**
	 * Оригинальное имя загружаемого файла
	 * @return null|string
	 */
	protected function original_filename() {
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
		return APPLICATION_ROOT . '/public/' . $this->storeDir() . '/' . $this->filename($version);
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

			//Событие перед загрузкой
			$this->before_upload();

			//Дебаг
			Boot::getInstance()->debug("  * Create image version: \x1b[33m" . $this->storeDir() . '/' .$this->filename() . "\x1b[0m");

			//Загружаем основной файл
			$this->moveOriginalFile();

			//Создаем версии файлов версии
			$this->create_versions($this->path());
		}
	}

	/**
	 * Загружает системный файл
	 * @param $path
	 * @throws Boot_Exception
	 */
	public function set($path) {

		//Проверяем существование файла
		if( !file_exists($path) ) {
			throw new Boot_Exception('Upload file not found: ' . $path);
		}

		//Дебаг
		Boot::getInstance()->debug("  * Set local image: \x1b[33m" . $path . "\x1b[0m");

		//Удаляем старый файл, если имеется
		$this->remove();
		$this->generate_filename($path);
		$this->fetchDirectory();

		//Если не указано, что нужно изменить оригинальный файл
		if( empty($this->version['original']) ) {
			//Копируем файл
			if( !copy($path, $this->path()) ) {
				throw new Boot_Exception('Error copy to file: ' . $this->path());
			}
		} else {
			$this->create_version($path, null, $this->version['original']);
		}

		//Создаем версии файлов версии
		$this->create_versions($this->path());

		//Сохраняем имя колонки
		$this->model->{$this->_column} = $this->filename();
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
		if( empty($this->version['original']) ) {
			move_uploaded_file($this->uploadTempFile(), $this->path());
		} else {
			move_uploaded_file($this->uploadTempFile(), $this->path('tmp'));
			$this->create_version($this->path('tmp'), null, $this->version['original']);
			unlink($this->path('tmp'));
		}
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
		 * @var \Intervention\Image\ImageManager $manager;
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
		$canvas->save($this->path($name), $this->quality);
	}

	/**
	 * @param $original_file
	 * @param $name
	 * @param $size
	 */
	private function processResize($original_file, $name, $size) {

		/**
		 * create an image manager instance with favored driver
		 * @var \Intervention\Image\ImageManager $manager;
		 */
		$class = "Intervention\\Image\\ImageManager";
		$manager = new $class(array('driver' => 'imagick'));

		// to finally create image instances
		$photo = $manager->make($original_file);

		//Изменяем размер
		$photo->resize($size[0], $size[1]);

		//Сохраняем изображение
		$photo->save($this->path($name), $this->quality);
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
		 * @var \Intervention\Image\ImageManager $manager;
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
		$photo->save($this->path($name), $this->quality);
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
				if( $name != 'original' ) {
					$this->create_version($original_file, $name, $process);
				}
			}
		}
	}

	/**
	 * Создание версии для файла
	 * @param             $path
	 * @param null|string $name
	 * @param array       $process
	 * @throws Boot_Exception
	 */
	private function create_version($path, $name, array $process) {

		//Дебаг
		Boot::getInstance()->debug("  * Create image version: \x1b[33m" . $this->storeDir() . '/' . $this->filename($name) . "\x1b[0m");

		//Выбираем действие
		switch( $process[0]) {

			//Fill
			case "resize_to_fill":
				$this->processFill($path, $name, $process[1]);
				break;

			//Fit
			case "resize_to_fit":
				$this->processFit($path, $name, $process[1]);
				break;

			//Resize
			case "resize":
				$this->processResize($path, $name, $process[1]);
				break;

			//Неизвестный процесс
			default:
				throw new Boot_Exception("Unknown uploader process name: " . $process[0]);
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

}

//todo удалить
namespace {

	/**
	 * @deprecated
	 */
	abstract class Boot_Uploader_Abstract extends Boot\Abstracts\Uploader {}
}