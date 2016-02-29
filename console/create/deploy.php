<?
//Путь до структуры
define('APPLICATION_PATH', realpath('.') . '/application');
define('APPLICATION_ROOT', realpath('.'));
define('LIBRARY_PATH', realpath('.') . '/library');

error_reporting(E_ALL);
ini_set("display_errors", 1);

//Получаем, какой мы создаем инстанс деплоя
$application = isset($argv[1]) ? ucfirst($argv[1]) : null;
if( $application == null ) {
	exit("Write application deploy" . PHP_EOL);
}

$directories = array(
	"/deploy"
);

$php = <<<PHP
<?
class Boot_{$application}_Deploy extends Boot_Deploy_Abstract {

	/**
	 * Репозиторий
	 * @var string
	 */
	protected \$repository = "git repository";

	/**
	 * Ветка
	 * @var string
	 */
	protected \$branch = "master";

	/**
	 * Путь для публикации
	 * @var string
	 */
	protected \$deploy_to = "/home/www/your_website";

	/**
	 * Сервер деплоя
	 * @var string
	 */
	protected \$server = "your_website";

	/**
	 * Прокидывание ссылок на папки
	 * @var array
	 */
	protected \$shared_children = [
		"vendor",
		"log",
		"bower_components",
		"public/uploads"
	];

	/**
	 * Выполнение команды после успешного деплоя
	 * @var string
	 */
	protected \$exec_after = "";
}
PHP;

/**
 * Создание файла, если его не существует
 * @param $file
 * @param $append
 */
function create_file($file, $append) {
	if( file_exists(APPLICATION_ROOT . $file) == false ) {
		if( file_put_contents(APPLICATION_ROOT . $file, $append) ) {
			echo "File create: {$file}\r\n";
		}
	} else {
		echo "File is exists: {$file}\r\n";
	}
}

//Создаём необходимые директории
foreach($directories as $dir) {
	if( is_dir(APPLICATION_ROOT . $dir) == false ) {
		mkdir(APPLICATION_ROOT . $dir, 0777);
		echo "Create directory: " . $dir . "\r\n";
	}
}

create_file("/deploy/" . strtolower($application) . ".php", $php);