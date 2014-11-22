<?php
/**
 * @author: nixx
 * Date: 04.03.14
 * Time: 17:41
 */

abstract class Boot_Deploy_Abstract {

	/**
	 * Репозиторий
	 * @var string
	 */
	protected $repository;

	/**
	 * Ветка
	 * @var string
	 */
	protected $branch = "master";

	/**
	 * Путь для публикации
	 * @var string
	 */
	protected $deploy_to;

	/**
	 * Сервер деплоя
	 * @var string
	 */
	protected $server;

	/**
	 * Прокидывание ссылок на папки
	 * @var array
	 */
	protected $shared_children = [];

	/**
	 * Путь до фреймворка
	 * @var string
	 */
	protected $boot_path;

	/**
	 * Разрешить настройку каталога?
	 * @var bool
	 */
	protected $setup_access = true;

	/**
	 * Выполнение команды после успешного деплоя
	 * @var string
	 */
	protected $exec_after;

	/**
	 * Штамп времени начала деплоя
	 * @var string
	 */
	private $timestamp;

	/**
	 * Деплой
	 */
	public function deploy() {

		//Проверяем настройки
		$this->check_variables();

		//Запоминаем время
		$this->timestamp = date("YmdHis");

		//Выполняем запрос на хэш в гите
		$hash = null;
		if( preg_match("/^([^\s]+)/", $this->exec("git ls-remote {$this->repository} {$this->branch}"), $match) ) {
			$hash = $match[1];
		}

		//Если хэш не найден
		if( $hash == null ) {
			$this->error("Error get hash from git");
		}

		//Создаем удаленно директорию с клоном проекта или обновляем существующий
		$this->ssh_exec("if [ -d {$this->deploy_to}/shared/cached-copy ]; then cd {$this->deploy_to}/shared/cached-copy && git fetch -q origin && git fetch --tags -q origin && git reset -q --hard $hash && git clean -q -d -x -f; else git clone -q -b {$this->branch} {$this->repository} {$this->deploy_to}/shared/cached-copy && cd {$this->deploy_to}/shared/cached-copy && git checkout -q -b deploy $hash; fi");

		//Хопом выполняем комманды
		$exec = [
			//Копируем директорию в ревизию
			"cp -RPp {$this->deploy_to}/shared/cached-copy {$this->deploy_to}/releases/{$this->timestamp}",
			//Создаем ссылки на директории фреймворка
			"rm -rf -- {$this->deploy_to}/releases/{$this->timestamp}/console && ln -s -- {$this->boot_path}/console {$this->deploy_to}/releases/{$this->timestamp}/console",
			"rm -rf -- {$this->deploy_to}/releases/{$this->timestamp}/system && ln -s -- {$this->boot_path}/system {$this->deploy_to}/releases/{$this->timestamp}/system",
			//Добавляем права группе
			"chmod -R -- g+w {$this->deploy_to}/releases/{$this->timestamp}",
//			"chmod -R -- +x {$this->deploy_to}/releases/{$this->timestamp}/*.sh",
//			"chmod -R -- +x {$this->deploy_to}/releases/{$this->timestamp}/cron/*.sh"
		];

		//Добавляем в массив ссылку на асеты
		array_push($this->shared_children, "public/assets");

		//Создаем комманды, для прокидывания ссылок
		foreach( $this->shared_children as $shared ) {

			//Удаляем возможную директорию
			$exec[] = "rm -rf -- {$this->deploy_to}/releases/{$this->timestamp}/" . $shared;

			//Если указана не конечная директория
			if( dirname($shared) != "." /*&& !file_exists("{$this->deploy_to}/releases/{$this->timestamp}/" . dirname($shared) . "/")*/ ) {
				$exec[] = "mkdir -p -- {$this->deploy_to}/releases/{$this->timestamp}/" . dirname($shared) . "/";
			}

			//Создаем символическую ссылку
			$exec[] = "ln -s -- {$this->deploy_to}/shared/{$shared} {$this->deploy_to}/releases/{$this->timestamp}/{$shared}";
		}

		//Если нужно выполнить дополнительно
		if( $this->exec_after != null ) {

			//Переходим в директорию
			$exec[] = "cd {$this->deploy_to}/releases/{$this->timestamp} && " . $this->exec_after;
		}

		//Делаем компиляцию асетов
		$exec[] = "cd {$this->deploy_to}/releases/{$this->timestamp} && php console/assets.php";

		//Выполняем код
		$this->ssh_exec(implode(" && ", $exec));

		//Создаем боевой симлинк
		$this->ssh_exec("rm -f {$this->deploy_to}/current &&  ln -s {$this->deploy_to}/releases/{$this->timestamp} {$this->deploy_to}/current");
	}

	/**
	 * Настройка удаленной директории на сервере
	 */
	public function setup() {
		if( $this->setup_access ) {

			//Проверяем настройки
			$this->check_variables();

			//Создаем директории
			$exec = [
				"mkdir -p -- {$this->deploy_to}/releases/",
				"mkdir -p -- {$this->deploy_to}/shared/",
				"mkdir -p -- {$this->deploy_to}/shared/public/assets",
			];

			//Создаем комманды, для прокидывания ссылок
			foreach( $this->shared_children as $shared ) {
				$exec[] = "mkdir -p -- {$this->deploy_to}/shared/" . $shared . "/";
			}

			//Выполняем код
			$this->ssh_exec(implode(" && ", $exec));
		} else {
			$this->error("Setup directory was disabled");
		}
	}

	/**
	 * Проверка переменных
	 */
	private function check_variables() {
		if( $this->repository == null ) {
			$this->error("Repository name is null");
		}
		if( $this->branch == null ) {
			$this->error("Repository branch is null");
		}
		if( $this->deploy_to == null ) {
			$this->error("Deploy to path is null");
		}
		if( $this->boot_path == null ) {
			$this->error("Boot to path is null");
		}
	}

	/**
	 * Выполняет миграции на сервере
	 */
	public function migrate() {

		//Проверяем настройки
		$this->check_variables();

		//Выполняем код
		$this->ssh_exec("cd {$this->deploy_to}/current && php console/db.php migrate");
	}

	/**
	 * Вывод ошибки
	 * @param $message
	 */
	public function error($message) {
		exit("\x1b[31m{$message}\x1b[0m\r\n");
	}

	/**
	 * Выполнение команды на удаленном сервере
	 * @param $command
	 */
	public function ssh_exec($command) {

		//Выводим сообщение
		echo " * \x1b[33mexecuting: \"{$command}\"\x1b[0m\r\n";

		//Запоминаем время
		$time = $this->microtime_float();

		//Выполняем команду
		passthru("ssh " . $this->server . " \"" . $command . "\"");

		//Выводим строку выполнения
		$this->message("command finished in " . $this->get_time($time) . "ms");
	}

	/**
	 * Выполнение команды локально
	 * @param $command
	 */
	public function exec($command) {

		//Выводим сообщение
		echo "   \x1b[33mexecuting locally: \"{$command}\"\x1b[0m\r\n";

		//Запоминаем время
		$time = $this->microtime_float();

		//Выполняем команду
		$return = `$command`;

		//Выводим строку выполнения
		$this->message("command finished in " . $this->get_time($time) . "ms");

		//Возвращаем результат
		return $return;
	}

	/**
	 * Вывод информативного сообщения
	 * @param $message
	 */
	public function message($message) {
		echo "   " . $message . PHP_EOL;
	}

	/**
	 * Получение времени
	 * @return float
	 */
	private function microtime_float() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	/**
	 * Получение времени выполнения
	 * @param $time
	 * @return float
	 */
	private function get_time($time) {
		return round(($this->microtime_float() - $time) * 1000);
	}
}