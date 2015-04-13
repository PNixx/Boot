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

;;db.adapter	= "postgres"
;;db.host		= "localhost"
;;db.port		= 5432
;;db.user		= "root"
;;db.password	= ""
;;db.dbase	= "boot"

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
	"/application/uploader",
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
$lang_file = APPLICATION_PATH . "/lang/ru.json";
if( file_exists($lang_file) == false ) {
	file_put_contents($lang_file, "{}");
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

$fastcgi = <<<CONF
fastcgi_pass       127.0.0.1:9000 ;
fastcgi_buffer_size 128k;
fastcgi_buffers    4 256k;
fastcgi_busy_buffers_size 256k;
fastcgi_index      index.php;

fastcgi_param  DOCUMENT_ROOT      \$document_root;
fastcgi_param  GATEWAY_INTERFACE  CGI/1.1;
fastcgi_param  SCRIPT_FILENAME    \$document_root/index.php;

fastcgi_param  HTTP_HOST          \$host;
fastcgi_param  SERVER_NAME        \$host;
fastcgi_param  REQUEST_URI        \$uri;
fastcgi_param  QUERY_STRING       \$query_string;
fastcgi_param  REQUEST_METHOD     \$request_method;
fastcgi_param  CONTENT_TYPE       \$content_type;
fastcgi_param  CONTENT_LENGTH     \$content_length;
fastcgi_param  DOCUMENT_URI       \$document_uri;
fastcgi_param  SERVER_PROTOCOL    \$server_protocol;

fastcgi_param  SERVER_ADDR        \$server_addr;
fastcgi_param  SERVER_PORT        \$server_port;
fastcgi_param  SERVER_NAME        \$server_name;
fastcgi_param  REQUEST_SCHEME     \$scheme;

fastcgi_param  REMOTE_ADDR        \$proxy_add_x_forwarded_for;
fastcgi_param  REMOTE_PORT        \$remote_port;
CONF;
create_file("/application/config/fastcgi.conf", $fastcgi);

$nginx = "server {
	listen 80;
	server_name localhost;
	root " . APPLICATION_ROOT . "/public;

	#Only for Development
	location ~ /assets/.*$ {
		include " . APPLICATION_PATH . "/config/fastcgi.conf;
	}

	location ~* \\.(jpg|jpeg|gif|png|ico|bmp|swf|woff|ttf|eot|js|css|svg|zip|txt|xml)$ {
		root " . APPLICATION_ROOT . "/public;
		access_log off;
	}

	location / {
		include " . APPLICATION_PATH . "/config/fastcgi.conf;
	}
}
";
create_file("/application/config/nginx.conf", $nginx);

//Composer
create_file("/composer.json", '{
    "require": {
        "intervention/image": "~2.1"
    }
}');
