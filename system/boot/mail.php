<?
use Boot\Controllers\MailerController;

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
	 * @see MailerController
	 */
	static public function preview() {
		throw new Boot_Exception('This method is deprecated');
	}
}