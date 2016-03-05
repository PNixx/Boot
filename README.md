Boot framework v2.3
==============

##Технологии
* [PHP](http://php.net) - язык программирования PHP 5.6
* [Composer](https://getcomposer.org) - package manager
* [Bower](http://bower.io) - package manager
* [SASS](https://github.com/sensational/sassphp)

##SASS
Если вы планируете использовать в своем проекте SASS, то в системе должны быть установлены:
	
	apt-get install nodejs npm
	
Ваш сервер должен видеть область окружения вашего юзера, для этого в php-fpm пришлось прописать:

	env[PATH] = $PATH
	
Автоматическая установка библиотеки SASS for PHP:

	php console/install/sass.php
	
В вашу систему будет установлена библиотека sass.so, которую нужно прописать в php.ini:

	extension=sass.so
	
Автопрефиксы SASS:

	npm install --global postcss-cli autoprefixer


##Структура папок

	/application - контроллеры, модели, вьюхи 
		/assets - js и css файлы
		/config - настройки проекта
		/controllers - контроллеры
		/layouts - шаблоны
		/mailers - классы для отправки почты
		/models - модели для работы с БД
		/uploader - класс загрузчика изображений
		/views - вьюхи
	/console - управление проектом через консоль
	/db - миграции БД
	/deploy - классы деплоя на сервер
	/log - логи
	/public - корневой каталог сайта
	/system - системный каталог с фрейворком
	README.md - тестовый файл с описанием, в формате Markdown

##Создание проекта и установка

Заходим в консоль в корневою директорию и вводим команду:

	composer require pnixx/boot
	
Для настройки директории вводим:

	php vendor/pnixx/boot/console/create/symlink.php

Открываем файл конфигурации `/application/config/application.ini`, настраиваем сервер.

##Настройка веб-сервера (Nginx)

	http {
      # ...
      include /path/to/application/config/nginx.conf;
	}
	
Для более точной ностройки необходимо отредактировать файл `application/config/nginx.conf`

##Дополнительные модули

* [jQuery ujs](https://github.com/rails/jquery-ujs) - Ненавязчивый адаптер сценариев для jQuery
* [Boot Auth](https://github.com/PNixx/Boot_Auth) - модуль авторизации