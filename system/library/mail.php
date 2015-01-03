<?
class Boot_Mail_Lib extends Boot_Abstract_Library implements Boot_Exception_Interface {

	static public function send($mail, $title, $message) {
		$headers = 'MIME-Version: 1.0' . "\r\n" .
						'Content-type: text/html; charset=utf-8' . "\r\n" .
						'From: ' . (isset(Boot::getInstance()->config->mail->from) ? Boot::getInstance()->config->mail->from : 'no-reply@' . Boot::getInstance()->config->host) . "\r\n";

		mail($mail, $title, $message, $headers);
	}

	/**
	 * Обработка ошибки
	 * @param Exception $e
	 * @return mixed
	 */
	public static function onException(Exception $e) {
		if( APPLICATION_ENV == 'production' && ($e->getCode() >= 500 || $e->getCode() == 0) && isset(Boot::getInstance()->config->mail->error) ) {
			self::send(Boot::getInstance()->config->mail->error, "Error", "<pre>Error " . $e->getCode() . ": " . $e->getMessage() . "\r\n" . $e->getTraceAsString() . "SERVER:\r\n" . var_export($_SERVER, true) . "</pre>");
		}
	}
}