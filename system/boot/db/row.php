<?php
/**
 * User: Odintsov S.A.
 * Date: 16.08.11
 * Time: 23:07
 */
class Model_Row {

	private $_row = null;

	private $_row_update = array();

	/**
	 * Рабочая таблица модели
	 * @var null||string
	 */
	protected $_table = null;

	/**
	 * Связь с таблицами
	 * Многие к одному
	 * @var null|array
	 */
	protected $_belongs_to = null;

	/**
	 * Связь с таблицами
	 * Один ко многим
	 * @var null|array([column] => [table])
	 */
	protected $_has_many = null;

	/**
	 * Первичный ключ
	 * @var null|array
	 */
	protected $_pkey = null;

	/**
	 * Инстанс модели
	 * @var Model
	 */
	private $_model_instance = null;

	public function __construct($data, $table, $belongs_to, $has_many, $pkey, $model, $create = false) {

		$this->_row = $data;
		foreach($data as $name => $value) {
			if( property_exists($this, $name) ) {
				$this->$name = $value;
			}
		}
		$this->_table = $table;
		$this->_belongs_to = $belongs_to;
		$this->_has_many = $has_many;
		$this->_pkey = $pkey;
		$this->_model_instance = $model;
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
		if( property_exists($this, $name) ) {
			$this->$name = $value;
		}
		if( in_array($name, $this->_row_update) == false ) {
			array_push($this->_row_update, $name);
		}
	}

	/**
	 * Получаем данные
	 * @param $name
	 * @return string
	 */
	public function __get($name) {

		if( isset($this->_row->$name) ) {
			return $this->_row->$name;
		} else {
			return false;
		}
	}

	/**
	 * Запрос функции
	 * @param $name
	 * @param $params
	 * @throws Exception
	 * @return string
	 */
	public function __call($name, $params) {

		if( preg_match("/^get([A-Z].*?)$/", $name, $match) ) {
			if( array_key_exists(strtolower($match[1]), $this->_row) ) {
				return $this->{strtolower($match[1])};
			}
		}
		throw new Exception("Функция {$name} не определена");
	}

	/**
	 * Удаляет текущую запись
	 */
	public function destroy() {

		//Делаем запрос на удаление
		$this->_model_instance->delete($this->{$this->_pkey});

		//Если есть связь с другими таблицами
		if( $this->_has_many ) {
			foreach($this->_has_many as $table) {

				//Строим название модели
				$model = "Model_" . ucfirst($table);

				/**
				 * Получаем объект записи
				 * @var $model Model
				 */
				$model = new $model;

				//Удаляем все значения
				$model->delete(array($this->_table . "_id" => $this->{$this->_pkey}));
			}
		}
	}

	public function toArray() {
		return (array)$this->_row;
	}
}
