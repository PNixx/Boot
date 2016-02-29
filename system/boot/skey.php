<?php
/**
 * User: Odintsov S.A.
 * Date: 06.02.12
 * Time: 21:12
 */

class Boot_Skey {

  /**
   * @var Boot_Skey
   */
  static public $_instance = null;

  /**
   * Хранилище случайного числа
   * @var null
   */
  private $_rand_key = null;

  /**
   * Время генерации скрипта
   * @var null
   */
  private $_time = null;

  /**
   * Сгенерированный уникальный ключ
   * @var null
   */
  private $_init_skey = null;

  /**
   * Получаем инстанс
   * @static
   * @return Boot_Skey
   */
  static public function getInstance() {

    if( !(self::$_instance instanceof Boot_Skey) ) {
      self::$_instance = new Boot_Skey();
    }
    return self::$_instance;
  }

  /**
   * Создаём и возвращаем ключ
   */
  public function __construct() {

    if( isset($_COOKIE['skey_public']) ) {

      //Получаем ключ
      $skey_public = $_COOKIE['skey_public'];

      //Разбиваем
      $match = explode("_", $skey_public);

      //Если вроде всё правильно получили
      if( $match && count($match) == 3 ) {

        //Проверяем правильность ключа
        if( md5(Boot::getInstance()->config->default->skey . $match[2] . $match[1]) == $match[0] ) {

          //Сохраняем данные
          $this->_rand_key = $match[2];
          $this->_time = $match[1];
          $this->_init_skey = $skey_public;
        }
      }

    }

    //Если чего-то не хватает, создаём ключ заново
    if( $this->_init_skey == false || $this->_time == false || $this->_rand_key == false ) {

      //Создаём уникальное число
      $this->_rand_key = uniqid("revo");

      //Запоминаем время генерации
      $this->_time = time();

      //Генерируем хэш
      $this->_init_skey = md5(Boot::getInstance()->config->default->skey . $this->_rand_key . $this->_time) . "_" . $this->_time . "_" . $this->_rand_key;

      //Запоминаем в сессию
			Boot_Cookie::set("skey_public", $this->_init_skey);
    }
  }

  /**
   * @static
   * Получить секретный ключ
   */
  static public function get() {
    return self::getInstance()->_init_skey;
  }

	/**
	 * Парсинг закрытого ключа
	 * @static
	 * @param $skey
	 * @return bool
	 */
  static public function validKey($skey) {

    //Разбиваем
    $match = explode("_", $skey);

    //Если вроде всё правильно получили
    if( $match && count($match) == 3 ) {

      //Проверяем правильность ключа
      if( md5(Boot::getInstance()->config->default->skey . $match[2] . $match[1]) == $match[0] ) {
        return true;
      }
    }
    return false;
  }
}
