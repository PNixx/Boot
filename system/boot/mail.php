<?

/**
 * Class Boot_Mail
 */
class Boot_Mail {

	static public function send($mail, $title, $message, $from = null) {
		$headers = 'MIME-Version: 1.0' . "\r\n" .
						'Content-type: text/html; charset=utf-8' . "\r\n" .
						'From: ' . ($from ? $from : 'info@' . Boot::getInstance()->config->host) . "\r\n";

		return mail($mail, $title, $message, $headers);
	}

	/**
	 * Предпросмотр писем
	 * @param $param
	 * @return mixed|void
	 */
	static public function preview($param) {

		//Если кол-во = 2, то отображаем весь список
		if( count($param) == 2 ) {
			foreach( glob(APPLICATION_ROOT . '/test/mailers/*.php') as $file ) {
				$class = pathinfo($file, PATHINFO_FILENAME);
				echo '<h1>' . ucfirst($class) . '</h1>';
				echo '<ul>';
				require APPLICATION_ROOT . '/test/mailers/' . $class . '.php';

				//Проходим по списку методов
				foreach(get_class_methods($class . 'MailerPreview') as $method) {
					if( in_array($method, get_class_methods($class . 'Mailer')) ) {
						echo "<li><a href=\"/boot/mailer/{$class}/{$method}\">{$class}/{$method}</a></li>";
					}
				}
				echo '</ul>';
			}
		} elseif( count($param) == 4 ) {
			require APPLICATION_ROOT . '/test/mailers/' . $param[2] . '.php';

			/**
			 * @var $class PromotionMailerPreview
			 */
			$class = $param[2] . 'MailerPreview';

			//Если нужно вывести только письмо
			echo $class::$param[3]();
		}
	}
}