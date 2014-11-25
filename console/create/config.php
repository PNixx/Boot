<?php
/**
 * Create default config
 * User: P.Nixx
 * Date: 28.09.12
 * Time: 11:14
 */
//Путь до структуры
define('APPLICATION_PATH', realpath('.') . '/application');
define('APPLICATION_ROOT', realpath('.'));
define('LIBRARY_PATH', realpath('.') . '/library');

error_reporting(E_ALL);
ini_set("display_errors", 1);

$ukey = uniqid();
$config = <<<INI
[production]
host		= "localhost"

db.adapter	= "mysql"
db.host		= "localhost"
db.port		= 3306
db.user		= "root"
db.password	= ""
db.dbase	= "boot"

default.page				= "home"
default.layout		  = "home"
default.skey        = "{$ukey}"
default.module			= false

log.on	= 1
log.dir	= "[APPLICATION_ROOT]/log/"

translate.dir = "[APPLICATION_ROOT]/application/lang/"
translate.lang = "ru"

admin.login = "admin"
admin.passw = "boot"

[development]
host		= "localhost"
INI;

$directories = array(
	"/log",
	"/application",
	"/application/assets",
	"/application/assets/css",
	"/application/assets/js",
	"/application/lang",
	"/application/config",
	"/application/controllers",
	"/application/views",
	"/application/views/home",
	"/application/models",
	"/application/layouts",
	"/db",
	"/public",
	"/library",
	"/deploy"
);

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

//Создаём файл конфига
create_file("/application/config/application.ini", $config);

//Создаем файл библиотек
create_file("/application/config/library.conf", "translate" . PHP_EOL);

//Cоздаем файлы для асетов
$assets = <<<PHP
/**
 * Assets
 * example: require_tree, require_directory, require
 *= require_tree .
 */
PHP;
//Создаём файл application.css
create_file("/application/assets/application.css", $assets);
$assets = <<<PHP
// Assets
// example: require_tree, require
//= require_tree .
PHP;
//Создаём файл application.css
create_file("/application/assets/application.js", $assets);

//Создание роутера
$routes = <<<PHP
<?php
/**
 * Routes
 * User: Odintsov S.A.
 * Date: 30.08.12
 * Time: 14:40
 */
\$routes = array(
	"about" => Boot_Routes::ROUTE_CONTROLLER
);
PHP;

//Создаём файл роутера
create_file("/application/config/routes.php", $routes);

//Создаём файл перевода
$lang_file = APPLICATION_PATH . "/lang/ru.po";
if( file_exists($lang_file) == false ) {
	file_put_contents($lang_file, "");
}

//Создаём стандартный контроллер
$home = <<<CONTROLLER
<?php
/**
 * homeController
 */

class homeController extends Boot_Abstract_Controller {

	public function indexAction() {

	}
}
CONTROLLER;
create_file("/application/controllers/home.php", $home);

//Создаём стандартную вьюху
create_file("/application/views/home/index.phtml", "<h1>Hello world!</h1>");

//Создаём стандартную layout
$layout = <<<HTML
<html>
<head>
	<title>Hello world!</title>
	<meta name="viewport" content="width=device-width; initial-scale=1.0">

	<?= \$this->stylesheet_link_tag("application.css") ?>
	<?= \$this->javascript_include_tag("application.js") ?>
</head>
<body>
<div class="container">
	<div class="wrapper">
		<?//header ?>
		<div class="content">
			<?= \$content ?>
		</div>
	</div>
	<?//footer ?>
</div>
</body>
</html>
HTML;
create_file("/application/layouts/home.phtml", $layout);

$index = <<<PHP
<?php
session_start();

//Путь до структуры
define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));
define('APPLICATION_ROOT', realpath(dirname(__FILE__) . '/..'));
define('LIBRARY_PATH', realpath(dirname(__FILE__) . '/../library'));

//Устанавливаем загрузку библиотек
set_include_path(implode(PATH_SEPARATOR, array(
	realpath(realpath(dirname(__FILE__)) . '/../system'),
	get_include_path(),
)));

date_default_timezone_set("Europe/Moscow");

//Загружаем фреймворк
require_once 'boot.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

//Запускаем
Boot::getInstance()->run();
PHP;
create_file("/public/index.php", $index);

$htaccess = <<<PHP
SetEnv APPLICATION_ENV development

RewriteEngine on
RewriteCond $1 !^(index\.php|img|css|js|robots\.txt|sitemap\.xml|favicon\.ico)
RewriteCond %{REQUEST_URI} !-f
RewriteCond %{REQUEST_URI} !-d
RewriteRule ^(.*)$ ./index.php/$1 [L,QSA]
PHP;
create_file("/public/.htaccess", $htaccess);