Boot framework v2.1
==============

Технологии
----------
* [PHP](http://php.net) - язык программирования PHP 5.5
* [Composer](https://getcomposer.org) - package manager


##Структура папок

	/application - контроллеры, модели, вьюхи 
		/assets - js и css файлы
		/config - настройки проекта
		/controllers - контроллеры
		/layouts - шаблоны
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