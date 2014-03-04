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

//Создаём необходимые директории
foreach($directories as $dir) {
	if( is_dir(APPLICATION_ROOT . $dir) == false ) {
		mkdir(APPLICATION_ROOT . $dir, 0777);
		echo "Create directory: " . $dir . "\r\n";
	}
}

//Создаём файл конфига
$config_file = "/application/config/application.ini";
if( file_exists(APPLICATION_ROOT . $config_file) == false ) {
	if( file_put_contents(APPLICATION_ROOT . $config_file, $config) ) {
		echo "Config create: {$config_file}\r\n";
	}
} else {
	echo "Config file is exists: {$config_file}\r\n";
}

//Создаем файл библиотек
$library_file = "/application/config/library.conf";
if( file_exists(APPLICATION_ROOT . $library_file) == false ) {
	if( file_put_contents(APPLICATION_ROOT . $library_file, "translate" . PHP_EOL . "auth" . PHP_EOL) ) {
		echo "Config library create: {$library_file}\r\n";
	}
} else {
	echo "Config library is exists: {$library_file}\r\n";
}

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
$routes_file = "/application/config/routes.php";
if( file_exists(APPLICATION_ROOT . $routes_file) == false ) {
	if( file_put_contents(APPLICATION_ROOT . $routes_file, $routes) ) {
		echo "Routes create: {$routes_file}\r\n";
	}
} else {
	echo "Routes file is exists: {$routes_file}\r\n";
}

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
$home_file = "/application/controllers/home.php";
if( file_exists(APPLICATION_ROOT . $home_file) == false ) {
	if( file_put_contents(APPLICATION_ROOT . $home_file, $home) ) {
		echo "Controller home create: {$home_file}\r\n";
	}
}

//Создаём стандартную вьюху
$view = "<h1>Hello world!</h1>";
$view_file = "/application/views/home/index.phtml";
if( file_exists(APPLICATION_ROOT . $view_file) == false ) {
	if( file_put_contents(APPLICATION_ROOT . $view_file, $view) ) {
		echo "View home create: {$view_file}\r\n";
	}
}

//Создаём стандартную layout
$layout = <<<HTML
<html>
<head>
	<title>Hello world!</title>
	<meta name="viewport" content="width=device-width; initial-scale=1.0">
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
$layout_file = "/application/layouts/home.phtml";
if( file_exists(APPLICATION_ROOT . $layout_file) == false ) {
	if( file_put_contents(APPLICATION_ROOT . $layout_file, $layout) ) {
		echo "Layout home create: {$layout_file}\r\n";
	}
}

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
$index_file = "/public/index.php";
if( file_exists(APPLICATION_ROOT . $index_file) == false ) {
	if( file_put_contents(APPLICATION_ROOT . $index_file, $index) ) {
		echo "Create: {$index_file}\r\n";
	}
}

$htaccess = <<<PHP
SetEnv APPLICATION_ENV development

RewriteEngine on
RewriteCond $1 !^(index\.php|img|css|js|robots\.txt|sitemap\.xml|favicon\.ico)
RewriteCond %{REQUEST_URI} !-f
RewriteCond %{REQUEST_URI} !-d
RewriteRule ^(.*)$ ./index.php/$1 [L,QSA]
PHP;
$htaccess_file = "/public/.htaccess";
if( file_exists(APPLICATION_ROOT . $htaccess_file) == false ) {
	if( file_put_contents(APPLICATION_ROOT . $htaccess_file, $htaccess) ) {
		echo "Create: {$htaccess_file}\r\n";
	}
}