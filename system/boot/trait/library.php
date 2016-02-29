<?php
/**
 * Date: 20.02.16
 * Time: 20:46
 * @author  Sergey Odintsov <nixx.dj@gmail.com>
 */
namespace Boot;
use Boot\Library\Translate;

trait LibraryTrait {

	/**
	 * Перевод
	 * @param string $text
	 * @param null|int $args
	 * @param bool $plural
	 * @return string
	 */
	static public function t($text, $args = null, $plural = false) {
		return Translate::getInstance()->_($text, $args, $plural);
	}
}