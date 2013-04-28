<?
class Boot_Mail_Lib extends Boot_Abstract_Library {

	static public function send($mail, $title, $message) {
		$headers = 'MIME-Version: 1.0' . "\r\n" .
						'Content-type: text/html; charset=utf-8' . "\r\n" .
						'From: info@' . Boot::getInstance()->config->host . "\r\n";

		mail($mail, $title, $message, $headers);
	}
}