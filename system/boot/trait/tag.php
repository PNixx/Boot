<?php
/**
 * Date: 20.02.16
 * Time: 9:44
 * @author  Sergey Odintsov <nixx.dj@gmail.com>
 */
namespace Boot;

trait TagTrait {

	/**
	 * @param       $name
	 * @param       $url
	 * @param array $attr
	 * @return string
	 */
	public function link_to($name, $url, $attr = []) {
		return '<a' . $this->implode(array_merge(['href' => $url], $attr)) . '>' . $name . '</a>';
	}

	/**
	 * Собирает параметры
	 * @param $params
	 * @return string
	 */
	protected function implode($params) {
		$string = [];
		foreach( $params as $key => $value ) {
			$string[] = $key . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5) . '"';
		}
		return ($string ? ' ' : '' ) . implode(' ', $string);
	}
}