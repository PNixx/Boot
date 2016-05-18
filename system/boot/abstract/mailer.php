<?php
namespace Boot\Abstracts {

use Boot;
use Boot\Core\View;
use Boot_Exception;

	/**
 * Date: 29.11.15
 * Time: 13:14
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 */
abstract class Mailer {
	use Boot\ViewTrait, Boot\LibraryTrait;

	/**
	 * Шаблон письма
	 * @var string
	 */
	protected static $layout;

	/**
	 * От какого email отправляется письмо
	 * @var string
	 */
	protected static $from;

	/**
	 * Тип контента по умолчанию
	 * @var string
	 */
	protected static $content_type = 'text/html';

	/**
	 * MIME-Version
	 * @var string
	 */
	protected static $mime_version = '1.0';

	/**
	 * @var string
	 */
	protected static $charset = 'utf-8';

	/**
	 * Параметры передаваемые в шаблон
	 * @var array
	 */
	protected static $params = [];

	/**
	 * Инициализация заголовков, перед отправкой письма
	 * @var array
	 */
	private static $headers;

	/**
	 * Отправка сообщения
	 * @param       $to
	 * @param       $subject
	 * @param array $headers
	 * @throws Boot_Exception
	 */
	protected final static function mail($to, $subject, $headers = []) {

		//Собираем все заголовки
		self::$headers = [];
		self::$headers['MIME-Version'] = self::header($headers, 'mime_version');
		self::$headers['Content-type'] = self::header($headers, 'content_type') . '; charset=' . self::header($headers, 'charset');
		self::$headers['From'] = self::header($headers, 'from');
		if( empty(self::$headers['From']) ) {
			self::$headers['From'] = 'no-reply@' . Boot::getInstance()->config->host;
		}
		self::$headers['Content-Transfer-Encoding'] = 'base64';

		//Мигрируем остальные заголовки
		self::$headers = array_merge(self::$headers, $headers);

		//Достаем функцию вызова
		$caller = debug_backtrace()[1];
		$object = new \ReflectionClass($caller['class']);
		if( !preg_match('/^(.*?)Mailer$/', $object->getShortName(), $match) ) {
			throw new Boot_Exception('Parent execute not Mailer class');
		}

		//Рендерим письмо
		$view = self::_render('mailer/' . strtolower($match[1]) . '/' . $caller['function']);

		//Инициализируем шаблон
		if( static::$layout ) {
			$view = self::_render('layouts/' . static::$layout, $view);
		}

		//Отправляем письмо
		if( isset(debug_backtrace()[2]) && debug_backtrace()[2]['class'] == $caller['class'] . 'Preview' ) {
			echo $view;
		} else {
			mail($to, self::encode_header($subject), rtrim(chunk_split(base64_encode(self::html_min($view)))), self::make_headers());
		}
	}

	/**
	 * Поиск заголовка
	 * @param $headers
	 * @param $name
	 * @return string
	 */
	private static function header(&$headers, $name) {
		$header = !empty($headers[$name]) ? $headers[$name] : static::$$name;
		unset($headers[$name]);
		return $header;
	}

	/**
	 * Создает набор заголовков
	 * @return string
	 */
	private static function make_headers() {
		$return = '';
		foreach( self::$headers as $key => $value ) {
			$return .= $key . ': ' . $value . "\r\n";
		}
		return $return;
	}

	/**
	 * Рендирит шаблон
	 * @param string $path Путь к файлу без расширения
	 * @param null   $content
	 * @return string
	 * @throws Boot_Exception
	 */
	private static function _render($path, $content = null) {
		return (new View(View::include_path($path), array_merge(['content' => $content], static::$params)))->html();
	}

	/**
	 * Кодирует заголовок в base64
	 * @param $text
	 * @return string
	 */
	protected static function encode_header($text) {
		return '=?UTF-8?B?' . base64_encode($text) . '?=';
	}
}

}

//todo удалить
namespace {

	/**
	 * @deprecated
	 */
	abstract class Boot_Mailer_Abstract extends Boot\Abstracts\Mailer {}
}