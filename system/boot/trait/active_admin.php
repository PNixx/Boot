<?php

/**
 * Date: 22.07.15
 * Time: 16:47
 * @author  Sergey Odintsov <sergey.odintsov@mkechinov.ru>
 *
 * @property stdClass $view
 * @property array $permit
 */
trait Boot_ActiveAdmin {
	use Boot_TraitController;

	/**
	 * Список
	 * @throws DB_Exception
	 *
	 * view return:
	 *   $rows       - список строк
	 *   $pagination - пагинатор (если подключен класс)
	 */
	public function indexAction() {

		//Получаем страницу
		$page = (int)$this->getParam("page") - 1;
		if( $page < 0 ) {
			$page = 0;
		}
		$limit = $this->getLimit();

		//Получаем модель
		$model = $this->getModel();

		//Собираем данные
		if( class_exists('\PNixx\Pagination\Pagination') ) {
			$rows = $model::page($page, $limit)->all();
		} else {
			$rows = $model::all();
		}
		$this->view->rows = $rows;

		if( class_exists('\PNixx\Pagination\Pagination') ) {
			//Инициализируем пагинатор
			$this->view->pagination = new \PNixx\Pagination\Pagination([
				'total'     => $model::count(),
				'per_page'  => $limit,
				'proximity' => 2,
				'page'      => $page + 1
			]);
		}
	}

	/**
	 * Просмотр объекта
	 * @throws Exception
	 */
	public function showAction() {

		//Получаем модель
		$model = $this->getModel();

		//Получаем строку
		$this->view->row = $model::find($this->getParam('id'));
	}

	/**
	 * Форма создания нового объекта
	 *
	 * view return:
	 *   $row - инициализированная модель
	 */
	public function newAction() {

		//Получаем модель
		$model = $this->getModel();

		//Создаем
		$this->view->row = $model::create();
	}

	/**
	 * Создание записи
	 * @throws Exception
	 */
	public function createAction() {

		//Получаем модель
		$model = $this->getModel();

		//Создаем
		$row = $model::create($this->getFromParams());
		$this->view->row = $row;

		//Если создался
		if( $row->save() ) {

			//Добавляем ответ
			$this->setFlash("notice", "Запись успешно создана");

			//Редиректим в список
			$this->success_redirect($row);
		} else {
			$this->render('new');
		}
	}

	/**
	 * Форма редактирования записи
	 *
	 * view return:
	 *   $row - инициализированная модель
	 */
	public function editAction() {

		//Получаем параметры
		$id = (int)$this->getParam("id");
		if( $id < 1 ) {
			throw new Controller_Exception("Страница не найдена, не корректный ID", 400);
		}

		//Получаем модель
		$model = $this->getModel();

		//Получаем строку
		$this->view->row = $model::find($this->getParam('id'));
	}

	/**
	 * Сохранение записи
	 */
	public function saveAction() {

		//Получаем параметры
		$params = $this->getFromParams();

		//Получаем модель
		$model = $this->getModel();

		//Получаем строку
		$row = $model::find($params['id']);
		$this->view->row = $row;

		if( $row->update($this->getFromParams()) ) {

			//Добавляем ответ
			$this->setFlash("notice", "Запись успешно обновлена");

			//Редиректим в список
			$this->success_redirect($row);
		} else {
			$this->render('edit');
		}
	}

	public function destroyAction() {

		//Получаем параметры
		$id = (int)$this->getParam("id");
		if( $id < 1 ) {
			throw new Controller_Exception("Страница не найдена, не корректный ID", 400);
		}

		//Получаем модель
		$model = $this->getModel();

		//Получаем строку
		$row = $model::find($this->getParam('id'));
		$row->destroy();

		//Редиректим в список
		$this->_redirect("/" . \Boot\Routes::getInstance()->getController());
	}

	/**
	 * @param ActiveRecord $row
	 */
	protected function success_redirect(ActiveRecord $row) {
		$this->_redirect("/" . \Boot\Routes::getInstance()->getController());
	}

	/**
	 * Возвращаем число строк по умолчанию
	 * @return int
	 */
	protected function getLimit() {
		return 20;
	}

	/**
	 * Получение модели
	 * @return ActiveRecord
	 * @throws Boot_Exception
	 */
	protected function getModel() {

		//Строим имя модели
		$model = 'Model_' . ucfirst($this->getController());

		//Проверяем существует ли такая модель
		if( class_exists($model) ) {
			return $model;
		}
		throw new Boot_Exception($model . ' can\'t be found');
	}

	/**
	 * Получаем параметры формы
	 * @return Boot_Params
	 */
	private function getFromParams() {
		return $this->getParam(strtolower($this->getController()))->permit(array_merge($this->permit, ['id']));
	}
}