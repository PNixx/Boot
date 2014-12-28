<?php
/**
 * User: Odintsov S.A.
 * Date: 13.01.12
 * Time: 17:57
 */

class Boot_Translate_Lib extends Boot_Abstract_Library {

  /**
   * Найденные языки
   * @var array
   */
  private $_lang = null;

  /**
   * Директория с переводами
   * @var null
   */
  private $_dir = null;

  /**
   * Языка перевода по умолчанию
   * @var null
   */
  private $_default = null;

  /**
   * Хранилище парсенных файлов
   * @var null
   */
  private $_parse = array();

	/**
	 * Строим переводчик
	 * @throws Exception
	 */
  public function __construct() {

		//Строим путь до базы
		$dir = APPLICATION_PATH . "/lang/";

		//Получаем из куков язык
		if( class_exists("Boot_Cookie") && Boot_Cookie::get("lang") ) {
			$lang = Boot_Cookie::get("lang");
		} elseif( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ) {
			$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
		} else {
			$lang = "ru";
		}

		//Проверяем директорию
    if( is_dir($dir) == false ) {
      throw new Exception("База перевода не найдена");
    }
    $this->_dir = $dir;

		//Парсим JSON файлы локализации
		// todo для уже перешедших на новую версию локализации
		if( file_exists($dir . $lang . ".json") ) {

			//Парсим файл
			$this->parseJSON($lang);
			$this->_default = $lang;
		} else {

			//Проверяем существование файла по умолчанию
			if( file_exists($dir . $lang . ".po") == false ) {
				$lang = "ru";
			}
			$this->_default = $lang;

			//Парсим язык по умолчанию
			$this->parse($lang);
		}
  }

  /**
   * Парспинг файлов перевода
   * @param $lang
   * @return array|null
   * @throws Exception
   */
  public function parse($lang) {

    //Если раньше до этого не парсили
    if( !isset($this->_parse[$lang]) ) {

      //Проверяем существование файла
      if( file_exists($this->_dir . $lang . ".po") == false ) {
        throw new Exception("Файл перевода языка не найден: {$lang}.po");
      }

      //Читаем файл
      if( preg_match_all("/msgid \"([^\n\r]*)\"[\n\r]+msgstr \"([^\n\r]*)\"/", file_get_contents($this->_dir . $lang . ".po"), $po) ) {

        $return = array();

        foreach( $po[1] as $key => $line ) {
          $return[$line] = $po[2][$key];
        }
        $this->_parse[$lang] = $return;

      } else {
        $this->_parse[$lang] = array();
      }

    }
    return $this->_parse[$lang];
  }

	/**
	 * @param $lang
	 * @return array
	 * @throws Exception
	 */
	public function parseJSON($lang) {

		//Если раньше до этого не парсили
		if( !isset($this->_parse[$lang]) ) {

			//Проверяем существование файла
			if( file_exists($this->_dir . $lang . ".json") == false ) {
				throw new Exception("Файл перевода языка не найден: {$lang}.json");
			}

			//Читаем файл
			$this->_parse[$lang] = json_decode(file_get_contents($this->_dir . $lang . ".json"), true);
		}
		return $this->_parse[$lang];
	}

	/**
	 * Перевод
	 * @param string $text
	 * @param null|int $args
	 * @param bool $plural
	 * @throws Exception
	 * @return string
	 */
  public function _($text, $args = null, $plural = false) {

    //Определяем язык
    $lang = !$plural && $args ? $args : $this->_default;

    if( in_array($lang, $this->getLangs()) == false ) {
      $lang = "ru";
    }

    //Получаем массив перевода
		if( file_exists($this->_dir . $lang . ".json") ) {
			$po = $this->parseJSON($lang);
		} else {
			$po = $this->parse($lang);
		}

		if( $plural ) {
			return $this->plural($text, $args, $lang);
		}
    return isset($po[$text]) && $po[$text] ? $po[$text] : $text;
  }

  /**
   * Возвращает текущий язык
   */
  public function getLocale() {
    return $this->_default;
  }

	/**
	 * Устанавливает текущий язык
	 * @param $lang
	 */
  public function setLocale($lang) {
    $this->_default = $lang;
  }

  /**
   * Получить список доступных языков
   */
  public function getLangs() {
    if( $this->_lang === null ) {
      foreach( glob($this->_dir . "*.po") as $file) {
        $this->_lang[] = preg_replace("/\.po$/", "", basename($file));
      }
      foreach( glob($this->_dir . "*.json") as $file) {
        $this->_lang[] = preg_replace("/\.json$/", "", basename($file));
      }
    }
    return $this->_lang;
  }

	/**
	 * @param string $text
	 * @param int    $number
	 * @param string $lang
	 *
	 * array('арбуз', 'арбуза', 'арбузов')
	 *
	 * @return int
	 */
	private function plural($text, $number, $lang) {
		$i18n = $this->_($text);

		//Определяем позицию слова для перевода
		if( $lang == 'ru' ) {
			$i = ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number % 10 >= 2 && $number % 10 <= 4 && ($number % 100 < 10 || $number % 100 >= 20) ? 1 : 2));
		} else {
			$i = $number == 1 ? 0 : 1;
		}
		if( is_array($i18n) && count($i18n) > $i ) {
			return str_replace("%count%", $number, $i18n[$i]);
		}
		return str_replace("%count%", $number, $text);
	}
}
