<?php
/**
 * Date: 18.03.15
 * Time: 14:56
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
trait Boot_Console {

	/**
	 * @var string
	 */
	protected $server;

	/**
	 * Вывод ошибки
	 * @param $message
	 */
	public function error($message) {
		echo "\x1b[31m{$message}\x1b[0m\r\n";
		exit(127);
	}

	/**
	 * Выполнение команды на удаленном сервере
	 * @param $command
	 */
	public function ssh_exec($command) {

		//Проверяем указан ли сервер
		if( empty($this->server) ) {
			$this->error('Unknown ssh server');
		}

		//Выводим сообщение
		echo " * \x1b[33mexecuting: \"{$command}\"\x1b[0m\r\n";

		//Запоминаем время
		$time = $this->microtime_float();

		//Выполняем команду
		passthru("ssh " . $this->server . " \"" . $command . "\"", $r);

		//Выводим строку выполнения
		$this->message("command finished in " . $this->get_time($time) . "ms");

		if( $r != 0 ) {
			$this->error(" * Error execute ssh command");
		}
	}

	/**
	 * Выполнение команды локально
	 * @param $command
	 * @return string
	 */
	public function exec($command) {

		//Выводим сообщение
		echo "   \x1b[33mexecuting locally: \"{$command}\"\x1b[0m\r\n";

		//Запоминаем время
		$time = $this->microtime_float();

		//Выполняем команду
		$return = system($command, $r);
		if( !$return && $r > 0 ) {
			$this->error($r);
		}

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
	public function microtime_float() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	/**
	 * Получение времени выполнения
	 * @param $time
	 * @return float
	 */
	public function get_time($time) {
		return round(($this->microtime_float() - $time) * 1000);
	}
}