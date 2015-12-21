<?php
/**
 * Date: 29.11.15
 * Time: 13:02
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
class Boot_Console_Helper {

	/**
	 * Создание директории
	 * @param string|array $dir
	 */
	public static function mkdir($dir) {

		//Создаем массив
		$dir = is_array($dir) ? $dir : [$dir];

		//Проходим по списку
		foreach( $dir as $d ) {
			if( is_dir(APPLICATION_ROOT . $d) == false ) {
				mkdir(APPLICATION_ROOT . $d, 0777, true);
				echo "Create directory: " . $d . PHP_EOL;
			}
		}
	}

	/**
	 * Создание файла, если его не существует
	 * @param $file
	 * @param $append
	 */
	public static function create_file($file, $append) {
		if( file_exists(APPLICATION_ROOT . $file) == false ) {
			if( file_put_contents(APPLICATION_ROOT . $file, $append) ) {
				echo "File create: {$file}" . PHP_EOL;
			}
		} else {
			echo "File is exists: {$file}" . PHP_EOL;
		}
	}
}