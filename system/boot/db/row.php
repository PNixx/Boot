<?php
/**
 * User: Odintsov S.A.
 * Date: 16.08.11
 * Time: 23:07
 */
class Model_Row {

	private $_row = null;
	private $_row_update = array();
	protected $_table = null;

	public function __construct($data, $table = null, $create = false) {
		$this->_row = $data;
		$this->_table = $table;
		if( $create ) {
			foreach($data as $name => $value) {
				if( in_array($name, $this->_row_update) == false ) {
					array_push($this->_row_update, $name);
				}
			}
		}
	}

	/**
	 * Запись параметра
	 * @param $name
	 * @param $value
	 */
	public function __set($name, $value) {
		$this->_row->$name = $value;
		if( in_array($name, $this->_row_update) == false ) {
			array_push($this->_row_update, $name);
		}
	}

	/**
	 * Получаем данные
	 * @param $name
	 * @return void
	 */
	public function __get($name) {
		if( isset($this->_row->$name) ) {
			return $this->_row->$name;
		} else {
			return false;
		}
	}

	public function __call($name, $params) {
		if( preg_match("/^get([A-Z].*?)$/", $name, $match) ) {
			if( array_key_exists(strtolower($match[1]), $this->_row) ) {
				return $this->{strtolower($match[1])};
			} else {
				throw new Exception("Функция {$name} не определена");
			}
		}
	}
}
