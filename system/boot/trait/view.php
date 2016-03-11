<?php
/**
 * Date: 11.03.16
 * Time: 20:38
 * @author  Sergey Odintsov <nixx.dj@gmail.com>
 */
namespace Boot;

trait ViewTrait {

	/**
	 * HTML minify
	 * @param $buffer
	 * @return mixed
	 */
	protected static function html_min($buffer) {

		$search = array(
			'/\>[^\S ]+/su',  // strip whitespaces after tags, except space
			'/[^\S ]+\</su',  // strip whitespaces before tags, except space
			'/(\s)+/su'       // shorten multiple whitespace sequences
		);

		$replace = array(
			'>',
			'<',
			'\\1'
		);

		return preg_replace($search, $replace, $buffer);
	}
}