<?

/**
 * Class CApplication
 *
 */
class CApplication
{
    public $is_ajax_mode = false;
    public $arItems = array();
    public $obB24;  // object class CB24 for access to Bitrix24
    public $obDb;  // object class SafeMySQL to access to MySql
    public $obTwig;  // object to access to Twig - templater
    public $ajaxCommands = array(  // Создадим массив, который будет содержать названия методов для Ajax
         'insertItem', 'updateItem', 'deleteItem', 'getItem', 'getTasksDo', 'getTasksAccomp', 'getTasksDelegate',
        'getTasksAudit', 'getGroups', 'getContactsFiz', 'getContactsCompany', 'showAllItems', 'reloadRecentItems',
        'getClientReportForm', 'buildReport', 'shortUpdateItem', 'saveReport', 'showReportById', 'getReportList',
        'getTaskData', 'getWorkerReportForm',
    );

    public function __construct()
    {
        global $dbSettings, $twigTemplatesDir;

        $this->obB24 = new CB24;
        $this->obDb = new SafeMySQL($dbSettings);

        $loader = new Twig_Loader_Filesystem($twigTemplatesDir); // указывае где хранятся шаблоны
        $this->obTwig = new Twig_Environment($loader); // Init Twig

    }

    public function start()
    {
        $this->is_ajax_mode = isset($_REQUEST['operation']);

        // Если НЕ Ajax mode -  то заполняем массив Параметров через функцию из Request
        // так как при первой инициализации приложения Битрикс по другому называет параметры авторизации
        if (!$this->is_ajax_mode) {
            $this->obB24->arAccessParams = $this->obB24->prepareFromRequest($_REQUEST);
        } else {
            $this->obB24->arAccessParams = $_REQUEST;
        }

        // Проверяем авториацию в Bitrix24
        $this->obB24->b24_error = $this->obB24->checkB24Auth();
        if ($this->obB24->b24_error != '') {
            if ($this->is_ajax_mode)
            {
                $ajax_answer = array(
                    'status' => 'error',
                    'html' => '',
                    'msg' => 'Ошибка авторизации в Битрикс24',
                    'reload_html' => ''
                );
                $this->returnJSONResult($ajax_answer);
            }
            else
            {
                echo "B24 error: " . $this->obB24->b24_error;
            }
            die;
        }

        // Регистрируем нового пользователя в БД или
        // обновляем данные авторизации в Б24 в БД для зарегистрированного пользователя
        $this->obB24->getB24User();
        $this->registerUser();
    }

	public function returnJSONResult ($answer) {
        global $debug, $debugInfo;
		ob_start();
		ob_end_clean();
		Header('Cache-Control: no-cache');
		Header('Pragma: no-cache');
        if ($debug == 1) {
            $debugI = array('debug' => $debugInfo);
            $answer=array_merge($answer, $debugI);
        }
		echo json_encode($answer);
		die();
	}

    public function draw_main_UI() {  // Выводим шаблоны главного интерфейса приложения
        global $arDate;

        try {
            $template = $this->obTwig->loadTemplate('main.tmpl'); // подгружаем шаблон
            $num = rand(0, 999999); // Случайное число, добавляется к ссылке на aplication.js чтобы не кешировалось

            // передаём в шаблон переменные и значения
            // выводим сформированное содержание
            echo $template->render(array(
                'num' => $num, // Случайное число чтобы не кешировался application.js?num
                'today' => $arDate['today'], // Текущая дата для первичной инициации формы ввода строки отчета
                'data' => $this->arItems,  // Массив данных для заполнения раздела Недавние записи
            ));
        } catch (Exception $e) {
            die ('ERROR: ' . $e->getMessage());
        }
    }

    public function registerUser()
    {

        // заполняем таблицу users
        $table = 'b24_users';
        $data1 = array(
            'user_id' => $this->obB24->arCurrentB24User['id'],
        );
        $data2 = array(
            'user_id' => $this->obB24->arCurrentB24User['id'],
            'domain' => $this->obB24->arAccessParams['domain'],
            'access_token' => $this->obB24->arAccessParams['access_token'],
            'refresh_token' => $this->obB24->arAccessParams['refresh_token'],
            'is_admin' => $this->obB24->arCurrentB24User['is_admin'],
            'first_name' => $this->obB24->arCurrentB24User['name'],
            'last_name' => $this->obB24->arCurrentB24User['last_name'],
            'second_name' => $this->obB24->arCurrentB24User['second_name'],
            'fio' => $this->obB24->arCurrentB24User['fio'],
        );
        $sql = "SELECT * FROM ?n WHERE ?u";
        $row = $this->obDb->getRow($sql, $table, $data1);
        if ($row) {
            $sql = "UPDATE ?n SET ?u WHERE ?u";
            $this->obDb->query($sql, $table, $data2, $data1);
        } else {
            $sql = "INSERT INTO ?n SET ?u";
            $this->obDb->query($sql, $table, $data2);
        }

        $filename = "reports/{$this->obB24->arCurrentB24User['id']}";
        if (!file_exists($filename)) {
            if (!mkdir($filename, 0777, true)) {
                echo 'Не удалось создать директорию пользователя...';
            }
        }
    }

    public function getItem()
    {
        $id = $_POST['rep_item_id'];
        if (is_numeric($id))
        {
            $where = "WHERE user_id=" . $this->obB24->arCurrentB24User['id'] . " AND id=" . $id;
            $sql = "SELECT * FROM rm_items ?p";
            try {
                $this->arItems = $this->obDb->getRow($sql, $where);
                $ajax_answer = array(
                    'status' => 'success',
                    'result' => $this->arItems,
                    'msg' => '',
                    'reload_html' => ''
                );
            } catch (Exception $e) {
                $ajax_answer = array(
                    'status' => 'error',
                    'result' => '',
                    'msg' => 'Получение записи не удалось!<br>Выброшено исключение: ' . $e->getMessage() . "\n",
                    'reload_html' => ''
                );
            }
            return $ajax_answer;
        } else {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Получение записи не удалось!<br>Нет такой записи',
                'reload_html' => ''
            );
        }
        return $ajax_answer;
    }

    public function getItems($first_day, $last_day){
        $table = 'rm_items';
        $where = "WHERE user_id=". $this->obB24->arCurrentB24User['id']." AND item_date >= '".$first_day.
            "' AND item_date <= '".$last_day."' ORDER BY item_date DESC, id DESC";
        $sql = "SELECT *, DATE_FORMAT(item_date,'%d.%m.%Y') as correct_item_date FROM ?n ?p";
         try {
             $this->arItems = $this->obDb->getAll($sql, $table, $where);
             return true;
         }
         catch (Exception $e){
             $ajax_answer = array(
                 'status' => 'error',
                 'result' => '',
                 'msg' => 'Ошибка!<br>Выборка записей из БД не удалась <br>Выброшено исключение: \n' . $e->getMessage() . "\n",
                 'reload_html' => ''
             );
             $this->returnJSONResult($ajax_answer);
             return false;
        }
    }

    public function showAllItems()
    {
        global $arDate;
        $fDay = '';
        $lDay = '';
        $period = 'curWeek';

        if ($_POST['period']){
            $period = $_POST['period'];
        }
        //todo Добавить обработку периода выборки записей range в HTML форму -> $fDay и $lDay

        switch ($period) {
            case 'curWeek':
                $firstDay = $arDate['this_week_monday'];
                $lastDay = $arDate['today'];
                break;
            case 'prevWeek':
                $firstDay = $arDate['last_week_monday'];
                $lastDay = $arDate['last_week_sunday'];
                break;
            case 'curMonth':
                $firstDay = $arDate['this_month_first_day'];
                $lastDay = $arDate['today'];
                break;
            case 'prevMonth':
                $firstDay = $arDate['last_month_first_day'];
                $lastDay = $arDate['last_month_last_day'];
                break;
            case 'range':
                $firstDay = $fDay;
                $lastDay = $lDay;
                break;
            default:
                $firstDay = $arDate['this_week_monday'];
                $lastDay = $arDate['today'];
        };

        //получаем новый массив записей для обновления списка записей
        $this->getItems($firstDay, $lastDay);
        // возвращаем html со списком последних записей
        $template = $this->obTwig->loadTemplate('show-recent-rep-item.tmpl');
        $html = $template->render(array(
            'show_ctrl_btn' => 'show',
            'header' => 'Записи отчета',
            'data' => $this->arItems,
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $html,
        );
        return $ajax_answer;
    }

    private function getItemFromRequest(){
        if ($_POST){
            return array(
                'user_id' => $this->obB24->arCurrentB24User['id'],
                'item_date' => $_POST['rep_item_date'],
                'item_elapsed' => $_POST['rep_item_elapsed'],
                'item_action' => cleanStr($_POST['rep_item_action']),
                'item_result' => cleanStr($_POST['rep_item_result']),
                'item_comment' => cleanStr($_POST['rep_item_comment']),
                'item_freestyle' => $_POST['rep_item_freestyle'],
                'item_task_id' => $_POST['rep_item_task_id'],
                'item_client_id' => $_POST['rep_item_client_id'],
                'item_group_id' => $_POST['rep_item_group_id'],
                'item_lawsuit_id' => $_POST['rep_item_lawsuit_id'],
                'item_calendar_id' => $_POST['rep_item_calendar_id'],
                'is_freestyle' => $_POST['rep_item_freestyle'] || 0,
                'item_task_name' => cleanStr($_POST['rep_item_task_name']),
                'item_client_name' => cleanStr($_POST['rep_item_client_name']),
                'item_group_name' => cleanStr($_POST['rep_item_group_name']),
                'item_lawsuit_name' => cleanStr($_POST['rep_item_lawsuit_name']),
                'item_calendar_name' => cleanStr($_POST['rep_item_calendar_name']),
            );
        } else
        {
            return null;
        }
    }

    public function getWorkerReportForm()
    {
        global $arDate;
        $arReportParams = array(
            'id' => '',
            'create_date' => $arDate['today'],      // дата  создания отчета
            'report_for' => 'management',       // отчет для
            'report_name' => '',     // Наименование отчета
            'report_object' => 'worker',        // отчет по
            'object_id' => '',     //  id  сущности отчета
            'object_name' => '',     // Наименование сущности отчета
            'user_id' => $this->obB24->arCurrentB24User['id'],     // id пользователя, создавшшего отчет
            'user_fio' => $this->obB24->arCurrentB24User['fio'],   // ФИО пользователя, создавшшего отчет
            'first_day' => '',     // Начальная дата отчета
            'last_day' => '',     //  Конечная дата отчета
            'cost_hour' => '',     // Стоимость часа
            'hours' => 0,     // Общее количество часов по отчету
            'total_price' => 0,     // Сумма по отчету
            'item_ids_json' => array(),     // массив json с id записей
            'excel_file' => '',     // путь к xls файлу
            'pdf_file' => '',     //  путь к pdf файлу
            'is_locked' => 0,     //
            'is_archive' => 0,     //
            'include_all_items' => 0,     //
        );
        // возвращаем html с формой
        $template = $this->obTwig->loadTemplate('build-on-worker.tmpl');
        $reload_html = $template->render(array(
            'fday' => $arDate['this_month_first_day'],
            'lday' => $arDate['today'],
            'is_admin' => $this->obB24->arCurrentB24User['is_admin'],
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html,
            'arReportParams' => $arReportParams,
        );
        return $ajax_answer;
    }

    public function getClientReportForm()
    {
        global $arDate;
        $arReportParams = array(
            'id' => '',
            'create_date' => $arDate['today'],      // дата  создания отчета
            'report_for' => '',       // отчет для
            'report_name' => '',     // Наименование отчета
            'report_object' => 'client',        // отчет по
            'object_id' => '',     //  id  сущности отчета
            'object_name' => '',     // Наименование сущности отчета
            'user_id' => $this->obB24->arCurrentB24User['id'],     // id пользователя, создавшшего отчет
            'user_fio' => $this->obB24->arCurrentB24User['fio'],   // ФИО пользователя, создавшшего отчет
            'first_day' => '',     // Началььная дата отчтета
            'last_day' => '',     //  Конечная дата отчтета
            'cost_hour' => '',     // Стоимость часа
            'hours' => 0,     // Общее количество часов по отчету
            'total_price' => 0,     // Сумма по отчету
            'item_ids_json' => array(),     // массив json с id записей
            'excel_file' => '',     // путь к xls файлу
            'pdf_file' => '',     //  путь к pdf файлу
            'is_locked' => 0,     //
            'is_archive' => 0,     //
            'include_draft' => 1,     //
            'include_all_items' => 0,     //
        );
        // возвращаем html с формой
        $template = $this->obTwig->loadTemplate('build-on-client.tmpl');
        $reload_html = $template->render(array(
            'fday' => $arDate['this_month_first_day'],
            'lday' => $arDate['today'],
            'is_admin' => $this->obB24->arCurrentB24User['is_admin'],
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html,
            'arReportParams' => $arReportParams,
        );
        return $ajax_answer;
    }

    public function buildReport()
    {
        global $arDate;
        $sortedItems = array(
            0 => array(
                'id' => 0,
                'objName' => '',
                'objReportName' => '',
                'items' => array()
            )
        );

        $arReportParams = $this->getBuildParams('request');
        switch ($arReportParams['report_period']) {
            case 'curWeek':
                $firstDay = $arDate['this_week_monday'];
                $lastDay = $arDate['today'];
                break;
            case 'prevWeek':
                $firstDay = $arDate['last_week_monday'];
                $lastDay = $arDate['last_week_sunday'];
                break;
            case 'curMonth':
                $firstDay = $arDate['this_month_first_day'];
                $lastDay = $arDate['today'];
                break;
            case 'prevMonth':
                $firstDay = $arDate['last_month_first_day'];
                $lastDay = $arDate['last_month_last_day'];
                break;
            case 'range':
                $firstDay = $arReportParams['first_day'];
                $lastDay = $arReportParams['last_day'];
                break;
            default:
                $firstDay = $arDate['this_week_monday'];
                $lastDay = $arDate['today'];
        };

        switch ($arReportParams['report_object']) {
            case 'client':
                if ($arReportParams['include_all_items'] == 0) { // включить только мои записи
                    $where = "WHERE rm_items.user_id=" . $this->obB24->arCurrentB24User['id'] .
                        " AND item_client_id='" . $arReportParams['object_id'] .
                        "' AND item_date >='" . $firstDay . "' AND item_date <='" . $lastDay . "'";
                } else { // включить записи всех пользователей
                    $where = "WHERE item_client_id='" . $arReportParams['object_id'] .
                        "' AND item_date >='" . $firstDay . "' AND item_date <='" . $lastDay . "'";
                }
                break;
            case 'worker':
                if ($this->obB24->arCurrentB24User['is_admin'] == 0) { // включить только мои записи
                    $where = "WHERE rm_items.user_id=" . $this->obB24->arCurrentB24User['id'] .
                        " AND item_date >='" . $firstDay . "' AND item_date <='" . $lastDay . "'";
                } else { // включить записи ВЫБРАННОГО пользователя
                    $where = "WHERE rm_items.user_id=" . $arReportParams['object_id'] .
                        " AND item_date >='" . $firstDay . "' AND item_date <='" . $lastDay . "'";
                }
                break;
        }

        $sql = "SELECT rm_items.*, b24_users.fio AS user_fio, DATE_FORMAT(item_date,'%d.%m.%Y') as correct_item_date,
                    TRUNCATE(item_elapsed,0) AS item_elapsed_hr, TRUNCATE((item_elapsed - TRUNCATE(item_elapsed,0))*60,0) AS item_elapsed_min
                    FROM rm_items INNER JOIN b24_users ON rm_items.user_id = b24_users.user_id ?p
                    ORDER BY  rm_items.item_date, rm_items.id ";
        try {
            $this->arItems = $this->obDb->getAll($sql, $where); // запрос к БД

            $arItemIds = array();
            $hours = 0;
            foreach ($this->arItems as $key => $value) {
                $arItemIds[] = $value['id'];
                $hours += $value['item_elapsed'];
            };
            $arReportParams['item_ids_json'] = $arItemIds;
            $arReportParams['hours'] = round($hours,2);
            $arReportParams['total_price'] = $hours * $arReportParams['cost_hour'];
            $correctFirstDay = date('d-m-Y', strtotime($firstDay));
            $correctLastDay = date('d-m-Y', strtotime($lastDay));
            if (!$arReportParams['report_name']) {
                $arReportParams['report_name'] =
                    "Отчет {$arReportParams['object_name']} с {$correctFirstDay} по {$correctLastDay}";
            }

            // Получаем Альтернативные названия задач из БД
            $sql = "SELECT * FROM rm_alt_task_name";
            $altTaskName = $this->obDb->getIndCol('task_id', $sql); // запрос к БД

            // сортировка и группировка массива по Задачам
            foreach ($this->arItems as $key => $value) {
                if (!$value['item_task_id']) {
                    $sortedItems[0]['items'][] = $value;
                } else {
                    $newId = 0;
                    for ($i = 1; $i < count($sortedItems); $i++) {
                        if ($sortedItems[$i]['id'] == $value['item_task_id']) {
                            $sortedItems[$i]['items'][] = $value;
                            $newId = 1;
                            break;
                        }
                    }
                    if ($newId == 0) {
                        $i = count($sortedItems);
                        $sortedItems[$i]['id'] = $value['item_task_id'];
                        $sortedItems[$i]['objName'] = $value['item_task_name'];
                        $sortedItems[$i]['objReportName'] = !is_null($altTaskName[$value['item_task_id']]) ? $altTaskName[$value['item_task_id']] : $value['item_task_name'];
                        $sortedItems[$i]['items'][] = $value;

                        if (is_null($altTaskName[$value['item_task_id']])) {
                            $data1 = array(
                                'alt_task_name' => $value['item_task_name'],
                                'task_id' => $value['item_task_id'],
                            );
                            $sql = "INSERT INTO rm_alt_task_name SET ?u";
                            $this->obDb->query($sql, $data1);
                        }
                    }
                }
            };

            // Генерация HTML шаблона
            $template = $this->obTwig->loadTemplate('report-on-client.tmpl');
            $reload_html = $template->render(array(
                'data' => $sortedItems,
                'object_name' => $arReportParams['object_name'],
                'total_price' => $arReportParams['total_price'],
                'hours' => $arReportParams['hours'],
                'report_name' => $arReportParams['report_name'],
                'show_download_btn' => 'no',
                'saved_report' => 'no',
                'report_object' => $arReportParams['report_object'],
                'fDay' => $correctFirstDay,
                'lDay' => $correctLastDay,
            ));

            $ajax_answer = array(
                'status' => 'success',
                'result' => '',
                'msg' => '',
                'reload_html' => $reload_html,
                'arReportParams' => $arReportParams,
            );
        } catch (Exception $e) {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Выборка записей не удалась!<br>Выброшено исключение: <br>' . $e->getMessage() . "<br>",
                'reload_html' => ''
            );
        }
        return $ajax_answer;
    }

    public function getReportList()
    {
        global $arDate;
        $reportRange = 'curMonth'; //$_POST('reportRange');

        switch ($reportRange) {
            case 'curMonth':
                $firstDay = $arDate['this_month_first_day'];
                $lastDay = $arDate['today'];
                break;
            case 'prevMonth':
                $firstDay = $arDate['last_month_first_day'];
                $lastDay = $arDate['last_month_last_day'];
                break;
            default:
                $firstDay = $arDate['this_month_first_day'];
                $lastDay = $arDate['today'];
        };

        try {
            // Получаем список отчетов из БД
            $sql = "SELECT *, DATE_FORMAT(create_date,'%d.%m.%Y') as correct_date,
                     DATE_FORMAT(first_day,'%d.%m.%Y') as correct_first_day,
                     DATE_FORMAT(last_day,'%d.%m.%Y') as correct_last_day
                     FROM rm_reports ?p";
            $where = "WHERE create_date >='" . $firstDay . "' AND create_date <='" . $lastDay . "'";
            $reportList = $this->obDb->getAll($sql, $where); // запрос к БД

            // Генерация HTML шаблона
            $template = $this->obTwig->loadTemplate('report-select.tmpl');
            $reload_html = $template->render(array(
                'reports' => $reportList,
                'cur_user' => $this->obB24->arCurrentB24User['id'],
            ));

            $ajax_answer = array(
                'status' => 'success',
                'result' => '',
                'msg' => '',
                'reload_html' => $reload_html,
            );
        } catch (Exception $e) {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Не удалась получить список отчетов!<br>Выброшено исключение: <br>' . $e->getMessage() . "<br>",
                'reload_html' => ''
            );
        }

        return $ajax_answer;
    }

    public function showReportById($id = 0)
    {
        $sortedItems = array(
            0 => array(
                'id' => 0,
                'objName' => '',
                'objReportName' => '',
                'items' => array()
            )
        );

        if ($id == 0 && is_numeric($_POST['report_id'])) {
            $id = $_POST['report_id'];
        }

        // Вытаскиваем json строку со списком id строк отчета
        $where1 = array(
            'id' => $id,
        );
        $sql1 = "SELECT * FROM rm_reports WHERE ?u";
        $reportItem = $this->obDb->getAll($sql1, $where1)[0];
        $itemIds = json_decode($reportItem['item_ids_json'],false);
        $itemIdsStr = implode(',', $itemIds);

        // Вытаскиваем записи по списку
        $where2 = "WHERE rm_items.id IN (". $itemIdsStr.")";
        $sql2 = "SELECT rm_items.*, b24_users.fio AS user_fio, DATE_FORMAT(item_date,'%d.%m.%Y') as correct_item_date,
                    TRUNCATE(item_elapsed,0) AS item_elapsed_hr, TRUNCATE((item_elapsed - TRUNCATE(item_elapsed,0))*60,0) AS item_elapsed_min
                    FROM rm_items INNER JOIN b24_users ON rm_items.user_id = b24_users.user_id ?p
                    ORDER BY  rm_items.item_date, rm_items.id ";
        try {
            $this->arItems = $this->obDb->getAll($sql2, $where2); // запрос к БД

            // Получаем Альтернативные названия задач из БД
            $sql = "SELECT * FROM rm_alt_task_name";
            $altTaskName = $this->obDb->getIndCol('task_id', $sql); // запрос к БД

            // сортировка и группировка массива по Задачам -> $sortedItems
            foreach ($this->arItems as $key => $value) {
                if (!$value['item_task_id']) {
                    $sortedItems[0]['items'][] = $value;
                } else {
                    $newId = 0;
                    for ($i = 1; $i < count($sortedItems); $i++) {
                        if ($sortedItems[$i]['id'] == $value['item_task_id']) {
                            $sortedItems[$i]['items'][] = $value;
                            $newId = 1;
                            break;
                        }
                    }
                    if ($newId == 0) {
                        $i = count($sortedItems);
                        $sortedItems[$i]['id'] = $value['item_task_id'];
                        $sortedItems[$i]['objName'] = $value['item_task_name'];
                        $sortedItems[$i]['objReportName'] = $altTaskName[$value['item_task_id']];
                        $sortedItems[$i]['items'][] = $value;
                    }
                }
            };

            $template = $this->obTwig->loadTemplate('report-on-client.tmpl');
            $reload_html = $template->render(array(
                'data' => $sortedItems,
                'object_name' => $reportItem['object_name'],
                'total_price' => $reportItem['total_price'],
                'hours' => $reportItem['hours'],
                'report_name' => $reportItem['report_name'],
                'show_download_btn' => 'yes',
                'saved_report' => 'yes',
                'xlsxFile' => $reportItem['excel_file'],
                'pdfFile' => $reportItem['pdf_file'],
                'fDay' => date('d-m-Y', strtotime($reportItem['first_day'])),
                'lDay' => date('d-m-Y', strtotime($reportItem['last_day'])),
            ));
            $ajax_answer = array(
                'status' => 'success',
                'result' => '',
                'msg' => '',
                'reload_html' => $reload_html,
            );
        } catch (Exception $e) {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Выборка записей по отчету не удалась!<br>Выброшено исключение: <br>' . $e->getMessage() . "<br>",
                'reload_html' => ''
            );
        }
        return $ajax_answer;
    }

    public function saveReport()
    {
        global $arDate;
        $arReportParams = $this->getBuildParams('request');

        switch ($arReportParams['report_period']) {
            case 'curWeek':
                $arReportParams['first_day'] = $arDate['this_week_monday'];
                $arReportParams['last_day'] = $arDate['today'];
                break;
            case 'prevWeek':
                $arReportParams['first_day'] = $arDate['last_week_monday'];
                $arReportParams['last_day'] = $arDate['last_week_sunday'];
                break;
            case 'curMonth':
                $arReportParams['first_day'] = $arDate['this_month_first_day'];
                $arReportParams['last_day'] = $arDate['today'];
                break;
            case 'prevMonth':
                $arReportParams['first_day'] = $arDate['last_month_first_day'];
                $arReportParams['last_day'] = $arDate['last_month_last_day'];
                break;
        };

        try {
            $translitReportName = $this->gTranslate($arReportParams['report_name'], 'ru', 'en');
            //$translitReportName = $translitReportName->sentences['0']->src_translit;
            $translitReportName = trim(str_replace(array("'"," ","\"","/"), array("", "_","","-"), $translitReportName));

            // вставляем новую запись
            $sql = "INSERT INTO rm_reports SET ?u";
            $this->obDb->query($sql, $arReportParams);

            // получаем id только что вставленной записи
            $data = array(
                'user_id' => $this->obB24->arCurrentB24User['id'],
            );
            $sql = "SELECT MAX(id) FROM rm_reports WHERE ?u";
            $id = $this->obDb->getOne($sql, $data);// возвращем id записи для вставки в атрибут формы редактирования записи

            $arReportParams['excel_file'] = "{$this->obB24->arCurrentB24User['id']}/{$id}_{$translitReportName}.xlsx";
            $arReportParams['pdf_file'] = "{$this->obB24->arCurrentB24User['id']}/{$id}_{$translitReportName}.pdf";

            $sql = "UPDATE rm_reports SET excel_file = '" . $arReportParams['excel_file'] . "', pdf_file = '" .
                        $arReportParams['pdf_file'] . "' WHERE id={$id}";
            $this->obDb->query($sql);

            // Создаем Xlxs и Pdf файлы
            $pdfFile = '';
            $xlsxFile = '';
            $this->createXlsxPdf($id, $arReportParams);

            $ajax_answer = array(
                'status' => 'success',
                'result' => $id,
                'msg' => 'Отчет успешно сохранен',
                'reload_html' => $this->showReportById($id, $pdfFile, $xlsxFile)['reload_html'],
            );
            return $ajax_answer;
        } catch (Exception $e) {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Отчет НЕ сохранен! <br>Выброшено исключение: ' . $e->getMessage() . "\n",
                'reload_html' => ''
            );
            return $ajax_answer;
        }
    }

    public function gTranslate($str, $lang_from, $lang_to)
    {
        $query_data = array(
            'client' => 'x',
            'q' => $str,
            'sl' => $lang_from,
            'tl' => $lang_to
        );
        $filename = 'http://translate.google.ru/translate_a/t';
        $options = array(
            'http' => array(
                'user_agent' => 'Mozilla/5.0 (Windows NT 6.0; rv:26.0) Gecko/20100101 Firefox/26.0',
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($query_data)
            )
        );
        $context = stream_context_create($options);
        $response = file_get_contents($filename, false, $context);
        debug('Var $response: ',$response );
        return json_decode($response);
    }

    public function createXlsxPdf($reportId, $arReportParams){
        $templFile = 'template-2.xlsx';
        $xlsxOutputFileName = $arReportParams['excel_file'];
        $pdfOutputFileName = $arReportParams['pdf_file'];;
        $row = 15;  // первая строка таблицы данных

        // Инициализация PHPExcel
        $inputFileName = "templates/{$templFile}";  // Файл шаблона
        $objPHPExcel = PHPExcel_IOFactory::load($inputFileName);  // Загружаем шаблон
        $locale = 'ru_ru';                      // Локаль русская
        PHPExcel_Settings::setLocale($locale);  // устанавливаем локаль
        $sheet = $objPHPExcel->getActiveSheet();  // устанавливаем активный лист

        // Инициализация PDF Creator
        $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
        $rendererLibrary = 'mpdf';  // Папка с библиотекой
        $rendererLibraryPath = $_SERVER['DOCUMENT_ROOT'] . '/apps/vendor/appotter/' . $rendererLibrary;
        if (!PHPExcel_Settings::setPdfRenderer(
            $rendererName,
            $rendererLibraryPath
        )
        ) {
            die('Please set the $rendererName and $rendererLibraryPath values' .
                PHP_EOL . ' as appropriate for your directory structure');
        }

        // Дополняем массив с данными по отчету данными по объекту отчета
        //  и сортированным и группированным массивом записей
        $arReportParams = $this->expandReportData($arReportParams);

        // Заполняем отчет
        $sheet->setCellValue('C5', $reportId);
        $sheet->setCellValue('C6', $reportId);
        $sheet->setCellValue('C7', "с {$arReportParams['first_day']} по {$arReportParams['last_day']}");
        $sheet->setCellValue('I5', $arReportParams['create_date']);
        $sheet->setCellValue('C9', $arReportParams['object_name']);
        $sheet->setCellValue('C10', $arReportParams['object_address']);
        $sheet->setCellValue('C11', $arReportParams['object_phone']);
        $sheet->setCellValue('C12', $arReportParams['object_email']);

        // выводим записи по строкам начиная с $row
        // Вставлять самую первую строку нужно указывая $row + 1, В конце удалим лишние
        foreach ($arReportParams['sortedItems'] as $key => $value) {
            $sheet->insertNewRowBefore($row + 1, 1);  // Вставляем пустую строку в таблицу

            // Начало группы записей по ОДНОЙ задаче
            // Выводим Строку с альтернативным названием Задачи
            if ($value['id'] > 0) {
                $sheet->mergeCells("A{$row}:I{$row}");  // объединение ячеек
                $sheet->getStyle("A{$row}")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('A7C0DC');
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                $str = $value['objReportName'];
                $h = $this->getXlsxRowHeight($str, 100, 15);  // Вычисляем высоту строки
                $sheet->getRowDimension($row)->setRowHeight($h);
                $sheet->getStyle("B{$row}")->getAlignment()->setWrapText(true);  // переносить по словам
                $sheet->setCellValue("A{$row}", $str);
                $row++;
            }

            // Выводим записи по ОДНОЙ задаче
            $itemNum = 1;
            foreach ($value['items'] as $index => $item) {
                $sheet->insertNewRowBefore($row + 1, 1);  // Вставляем пустую строку в таблицу
                $sheet->mergeCells("B{$row}:G{$row}");  // объединение ячеек
                $sheet->getStyle("B{$row}")->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
                $str = $item['item_action'] . "-\n" . $item['item_result'];
                $h = $this->getXlsxRowHeight($str, 150, 15);
                $sheet->getRowDimension($row)->setRowHeight($h);
                $sheet->getStyle("B{$row}")->getAlignment()->setWrapText(true);  // переносить по словам

                $sheet->setCellValue("A{$row}", $itemNum);
                $sheet->setCellValue("B{$row}", $str);
                $sheet->setCellValue("H{$row}", $item['correct_item_date']);
                $sheet->setCellValue("I{$row}", $item['item_elapsed_hr'] ." ч. " . $item['item_elapsed_min'] . " мин.");
                $row++;
                $itemNum++;
            }
        }
        $sheet->removeRow($row, 3);
        $hours = floor($arReportParams['hours']) . " ч. " .
            intval((round($arReportParams['hours'], 2) - floor($arReportParams['hours'])) * 60) . " мин.";
        $sheet->setCellValue("I{$row}", $hours);

        // Запись xlxs в файл
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save("reports/{$xlsxOutputFileName}");  // Записываем в файл

        // Создание PDF и запись в файл
        $objWriter = new PHPExcel_Writer_PDF($objPHPExcel);
        $objWriter->save("reports/{$pdfOutputFileName}");


        // Вызов родного PDF creator для примера
        // Saves file on the server as 'filename.pdf'
        //$mpdf = new mPDF();
        //$mpdf->WriteHTML($html);
        //$mpdf->Output('filename.pdf', 'F');
    }

    /**
     * Вычислить высоту строки для длинного текста Excel
     * @param $str string Текст для анализа
     * @param $size_pole integer  Кол-во символов, которое умещается в объедененном диапазоне
     * @param $height_need integer Преблизительная высота шрифта (Arial 10) = 10
     * @return int
     */
    function getXlsxRowHeight($str, $size_pole, $height_need)
    {
        $dec_h = 0;
        if (strlen($str) > $size_pole - 9) {
            //$size_h = (((strlen($str)) - (strlen($str)) % $size_pole) / $size_pole) + 1;
            $size_h = ceil(strlen($str) / $size_pole);   // (((strlen($str)) - (strlen($str)) % $size_pole) / $size_pole) + 1;
            $dec_h = floor(strlen($str) / ($size_pole*3));
        } else $size_h = 1;
        $NLcount = substr_count($str, chr(10));
        $size_h = ($size_h * $height_need) + ($NLcount * $height_need) - ($dec_h * $height_need);
        return $size_h;
    }

    private function expandReportData ($arReportParams) {
        $sortedItems = array(
            0 => array(
                'id' => 0,
                'objName' => '',
                'objReportName' => '',
                'items' => array()
            )
        );

        if (($arReportParams['report_object'] == 'client') && ($arReportParams['object_id']) ) {
            if (strpos($arReportParams['object_id'], 'CO') === false) {
                $id = intval(substr($arReportParams['object_id'], 2));
                $arContactData = $this->obB24->getContactData($id, 'fiz');
                $arReportParams['object_phone'] = $arContactData['fiz']['PHONE']['0']['VALUE'];
                $arReportParams['object_address'] = $arContactData['fiz']['ADDRESS_CITY'] . ", " . $arContactData['fiz']['ADDRESS'] .
                    " " . $arContactData['fiz']['ADDRESS_2'];
                $arReportParams['object_email'] = $arContactData['fiz']['EMAIL']['0']['VALUE'];
            } else {
                $id = intval(substr($arReportParams['object_id'], 3));
                $arContactData = $this->obB24->getContactData($id, 'ur');
                $arReportParams['object_phone'] = $arContactData['ur']['PHONE']['0']['VALUE'];
                $arReportParams['object_address'] = $arContactData['ur']['ADDRESS_CITY'] . ", " . $arContactData['ur']['ADDRESS'] .
                    " " . $arContactData['ur']['ADDRESS_2'];
                $arReportParams['object_email'] = $arContactData['ur']['EMAIL']['0']['VALUE'];
            }
        }

        // Исправляем даты
        $arReportParams['first_day'] = date('d-m-Y', strtotime($arReportParams['first_day']));
        $arReportParams['last_day'] = date('d-m-Y', strtotime($arReportParams['last_day']));
        $arReportParams['create_date'] = date('d-m-Y', strtotime($arReportParams['create_date']));

        $itemIds = json_decode($arReportParams['item_ids_json'], false);
        $itemIdsStr = implode(',', $itemIds);

        // Вытаскиваем записи по списку
        $where = "WHERE rm_items.id IN (" . $itemIdsStr . ")";
        $sql = "SELECT rm_items.*, b24_users.fio AS user_fio, DATE_FORMAT(item_date,'%d.%m.%Y') as correct_item_date,
                    TRUNCATE(item_elapsed,0) AS item_elapsed_hr, TRUNCATE((item_elapsed - TRUNCATE(item_elapsed,0))*60,0) AS item_elapsed_min
                    FROM rm_items INNER JOIN b24_users ON rm_items.user_id = b24_users.user_id ?p
                    ORDER BY  rm_items.item_date, rm_items.id ";
        try {
            $this->arItems = $this->obDb->getAll($sql, $where); // запрос к БД

            // Получаем Альтернативные названия задач из БД
            $sql = "SELECT * FROM rm_alt_task_name";
            $altTaskName = $this->obDb->getIndCol('task_id', $sql); // запрос к БД

            // сортировка и группировка массива по Задачам -> $sortedItems
            foreach ($this->arItems as $key => $value) {
                if (!$value['item_task_id']) {
                    $sortedItems[0]['items'][] = $value;
                } else {
                    $newId = 0;
                    for ($i = 1; $i < count($sortedItems); $i++) {
                        if ($sortedItems[$i]['id'] == $value['item_task_id']) {
                            $sortedItems[$i]['items'][] = $value;
                            $newId = 1;
                            break;
                        }
                    }
                    if ($newId == 0) {
                        $i = count($sortedItems);
                        $sortedItems[$i]['id'] = $value['item_task_id'];
                        $sortedItems[$i]['objName'] = $value['item_task_name'];
                        $sortedItems[$i]['objReportName'] = $altTaskName[$value['item_task_id']];
                        $sortedItems[$i]['items'][] = $value;
                    }
                }
            };
            $arReportParams['sortedItems'] = $sortedItems;

        } catch (Exception $e) {
                $msg = 'Выборка записей по отчету не удалась!<br>Выброшено исключение: <br>' . $e->getMessage() . "<br>";
            debug('Var $msg: ',$msg );
        }
        return $arReportParams;
    }

    private function getBuildParams($fromRequest)
    {
        global $arDate;
        if ($fromRequest == 'request' && $_POST['arReportParams']) {
            $arReportParams = json_decode($_POST['arReportParams'], true);
            $arReportParams['item_ids_json'] = json_encode($arReportParams['item_ids_json']);
            $arReportParams['object_name'] = cleanStr($arReportParams['object_name']);
        } else {
            $arReportParams = array(
                'create_date' => $arDate['today'],      // дата  создания отчета
                'report_for' => '',       // отчет для
                'report_name' => '',     // Наименование отчета
                'report_object' => 'client',        // отчет по
                'object_id' => '',     //  id  сущности отчета
                'object_name' => '',     // Наименование сущности отчета
                'user_id' => $this->obB24->arCurrentB24User['id'],     // id пользователя, создавшшего отчет
                'user_fio' => $this->obB24->arCurrentB24User['fio'],   // ФИО пользователя, создавшшего отчет
                'first_day' => '',     // Началььная дата отчтета
                'last_day' => '',     //  Конечная дата отчтета
                'cost_hour' => '',     // Стоимость часа
                'hours' => 0,     // Общее количество часов по отчету
                'total_price' => 0,     // Сумма по отчету
                'item_ids_json' => array(),     // массив json с id записей
                'excel_file' => '',     // путь к xls файлу
                'pdf_file' => '',     //  путь к pdf файлу
                'is_locked' => 0,     //
                'is_archive' => 0,     //
                'include_all_items' => 0,     //
                'report_period' => 'curWeek',
            );
        };
        return $arReportParams;
    }

    public function insertItem() {
        $table = 'rm_items';
        $data1 = $this->getItemFromRequest();
        try {
            // вставляем новую запись
            $sql = "INSERT INTO ?n SET ?u";
            $this->obDb->query($sql, $table, $data1);

            // получаем id только что вставленной записи
            $data2 = array(
                'user_id' => $this->obB24->arCurrentB24User['id'],
            );
            $sql = "SELECT MAX(id) FROM ?n WHERE ?u";
            // возвращем id записи для вставки в атрибут формы редактирования записи
            $id = $this->obDb->getOne($sql, $table, $data2);
            $ajax_answer = array(
                'status' => 'success',
                'result' => $id,
                'msg' => 'Запись успешно сохранена',
                'reload_html' => $this->reloadRecentItems('html'),
            );
            return $ajax_answer;
        } catch (Exception $e) {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Ошибка! <br>Вставка записи не удалась! <br>Выброшено исключение: ' . $e->getMessage() . "\n",
                'reload_html' => ''
            );
            return $ajax_answer;
        }
    }

    public function shortUpdateItem()
    {
        $id = $_POST['item_id'];
        if (is_numeric($id)) {
            $table = 'rm_items';
            $sql = "UPDATE ?n SET ?u WHERE ?u";
            $data2 = array(
                'id' => $id,
            );
            $data1 = array();
            if ($_POST['item_action']) {
                $data1 += array('item_action' => cleanStr($_POST['item_action']));
            };
            if ($_POST['item_result']) {
                $data1 += array('item_result' => cleanStr($_POST['item_result']));
            };
            if (is_numeric($_POST['item_elapsed'])) {
                $data1 += array('item_elapsed' => cleanStr($_POST['item_elapsed']));
            };
            if ($_POST['alt_task_name']) {
                $table = 'rm_alt_task_name';
                $data1 = array(
                    'alt_task_name' => cleanStr($_POST['alt_task_name'], 'delNL'),
                    'task_id' => $id,
                    );
                $data2 = array(
                    'alt_task_name' => cleanStr($_POST['alt_task_name'], 'delNL'),
                );
                $sql = "INSERT ?n SET ?u ON DUPLICATE KEY UPDATE ?u";
            };
            if (empty($data1)){
                $ajax_answer = array(
                    'status' => 'error',
                    'result' => '',
                    'msg' => 'Обновление записи не удалось!<br>Нет такой записи в БД<br>ShortUpdate',
                    'reload_html' => ''
                );
                return $ajax_answer;
            }
            try {
                $this->obDb->query($sql, $table, $data1, $data2);
                $ajax_answer = array(
                    'status' => 'success',
                    'result' => $id,
                    'msg' => '',
                    'reload_html' => $this->reloadRecentItems('html'),
                );
            } catch (Exception $e) {
                $ajax_answer = array(
                    'status' => 'error',
                    'result' => '',
                    'msg' => 'Обновление записи не удалось!<br>Выброшено исключение:<br>' . $e->getMessage() . "<br>",
                    'reload_html' => ''
                );
            }
        } else {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Ошибка! <br>Обновление записи не удалось!<br>Нет такой записи',
                'reload_html' => ''
            );
        }
        return $ajax_answer;
    }

    public function updateItem()
    {
        $id = $_POST['rep_item_id'];
        if (is_numeric($id)) {
            $table = 'rm_items';
            $data1 = $this->getItemFromRequest();
            $data2 = array(
                'id' => $id,
            );
            try {
                $sql = "UPDATE ?n SET ?u WHERE ?u";
                $this->obDb->query($sql, $table, $data1, $data2);
                $ajax_answer = array(
                    'status' => 'success',
                    'result' => $id,
                    'msg' => 'Запись успешно обновлена',
                    'reload_html' => $this->reloadRecentItems('html'),
                );
            } catch (Exception $e) {
                $ajax_answer = array(
                    'status' => 'error',
                    'result' => '',
                    'msg' => 'Ошибка! <br>Обновление записи не удалось! <br>Выброшено исключение: ' . $e->getMessage() . "\n",
                    'reload_html' => ''
                );
            }
        } else {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Ошибка! <br>Обновление записи не удалось!<br>Нет такой записи',
                'reload_html' => ''
            );
        }
        return $ajax_answer;
    }

    public function deleteItem()
    {
        $id = $_POST['rep_item_id'];
        if (is_numeric($id)) {
            $table = 'rm_items';
            $data2 = array(
                'id' => $id,
            );
            $sql = "DELETE FROM ?n WHERE ?u";
            try {
                $this->obDb->query($sql, $table, $data2);
                $ajax_answer = array(
                    'status' => 'success',
                    'result' => '',
                    'msg' => 'Запись успешно удалена',
                    'reload_html' => $this->reloadRecentItems('html'),
                );
            } catch (Exception $e) {
                $ajax_answer = array(
                    'status' => 'error',
                    'result' => '',
                    'msg' => 'Ошибка! <br>Удаление не удалось! <br>Выброшено исключение: ' . $e->getMessage() . "\n",
                    'reload_html' => ''
                );
            }
        } else {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Ошибка! <br>Удаление записи не удалось!<br>Нет такой записи',
                'reload_html' => ''
            );
        }
        return $ajax_answer;
    }

    public function reloadRecentItems($fromAjax=null)
    {
        global $arDate;

        //получаем новый массив записей для обновления списка последних записей
        $this->getItems($arDate['last_week_monday'], $arDate['today']); //todo Вынести Первый и Последний день в Tools
        // возвращаем html со списком последних записей
        $template = $this->obTwig->loadTemplate('show-recent-rep-item.tmpl');
        $reload_html = $template->render(array(
            'data' => $this->arItems,
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html,
        );
        return $fromAjax ? $reload_html : $ajax_answer;
    }

    public function getContactsCompany()
    {
        $this->obB24->getContactsCompany();
        foreach ($this->obB24->arB24ContactsCompany as &$value) {
            $value['UF_ID'] = 'CO_' . $value['ID'];
        };
            $template = $this->obTwig->loadTemplate('contact-select.tmpl');
        $reload_html = $template->render(array(
            'contacts_data' => $this->obB24->arB24ContactsCompany,
            'fiz' => 0,  // выводит выриант для ЮрЛиц
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html
        );
        return $ajax_answer;
    }

    public function getContactsFiz()
    {

        $this->obB24->getContactsFiz();
        foreach ($this->obB24->arB24ContactsFiz as &$value){
            $value['UF_ID'] = 'C_'. $value['ID'];
        };
        $template = $this->obTwig->loadTemplate('contact-select.tmpl');
        $reload_html = $template->render(array(
            'contacts_data' => $this->obB24->arB24ContactsFiz,
            'fiz' => 1,  // выводит выриант для физлиц
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html
        );
        return $ajax_answer;
    }

    public function getGroups()
    {
        $this->obB24->getGroups();
        $template = $this->obTwig->loadTemplate('group-select.tmpl');
        $reload_html = $template->render(array(
            'groups_data' => $this->obB24->arB24Groups,
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html
        );
        return $ajax_answer;
    }

    public function getTaskData() {

        if ($_POST['taskId'] && is_numeric($_POST['taskId'])) {
            $taskId = $_POST['taskId'];

            $arTaskDataFull = $this->obB24->getTaskData($taskId);
            $this->obB24->getGroups();

            $arTaskDataShort['groupId'] = $arTaskDataFull['GROUP_ID'];
            $arTaskDataShort['groupName'] = "";
            $arTaskDataShort['clientId'] = $arTaskDataFull['UF_CRM_TASK'][0] ? $arTaskDataFull['UF_CRM_TASK'][0] : '';
            $arTaskDataShort['clientName'] = "";

            foreach ($this->obB24->arB24Groups as $index => $group) {
                if ($group['ID'] == $arTaskDataShort['groupId']) {
                    $arTaskDataShort['groupName'] = $group['NAME'];
                }
            }

            //проверяем клиент fiz или ur
            if (strstr($arTaskDataShort['clientId'], 'C_') != '') {
                $id = intval(substr($arTaskDataShort['clientId'], 2));
                $arContactData = $this->obB24->getContactData($id, 'fiz');
                $arTaskDataShort['clientName'] = $arContactData['fiz']['LAST_NAME'] . " " .
                    $arContactData['fiz']['NAME'] . " " . $arContactData['fiz']['SECOND_NAME'];
            }
            if (strstr($arTaskDataShort['clientId'], 'CO_') != '') {
                $id = intval(substr($arTaskDataShort['clientId'], 3));
                $arContactData = $this->obB24->getContactData($id, 'ur');
                $arTaskDataShort['clientName'] = $arContactData['ur']['TITLE'];
            }
            $ajax_answer = array(
                'status' => 'success',
                'result' => $arTaskDataShort,
                'msg' => '',
                'reload_html' => '',
            );
        } else {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Нет такого клиента!',
                'reload_html' => '',
            );
        }
        return $ajax_answer;
    }

    public function getTasksDo()
    {
        $this->obB24->getTasksDo();
        $template = $this->obTwig->loadTemplate('task-select.tmpl');
        $reload_html = $template->render(array(
            'tasks_data' => $this->obB24->arB24Tasks,
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html
        );
        return $ajax_answer;
    }

    public function getTasksAccomp()
    {
        $this->obB24->getTasksAccomp();
        $template = $this->obTwig->loadTemplate('task-select.tmpl');
        $reload_html = $template->render(array(
            'tasks_data' => $this->obB24->arB24Tasks,
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html
        );
        return $ajax_answer;
    }

    public function getTasksDelegate()
    {
        $this->obB24->getTasksDelegate();
        $template = $this->obTwig->loadTemplate('task-select.tmpl');
        $reload_html = $template->render(array(
            'tasks_data' => $this->obB24->arB24Tasks,
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html
        );
        return $ajax_answer;
    }

    public function getTasksAudit()
    {
        $this->obB24->getTasksAudit();
        $template = $this->obTwig->loadTemplate('task-select.tmpl');
        $reload_html = $template->render(array(
            'tasks_data' => $this->obB24->arB24Tasks,
        ));
        $ajax_answer = array(
            'status' => 'success',
            'result' => '',
            'msg' => '',
            'reload_html' => $reload_html
        );
        return $ajax_answer;
    }

    public function manageAjax($operation)
    {
        // Убедимся, что метод разрешен - имеется в массиве методов (команд)
        if (in_array($operation, $this->ajaxCommands)) {
            // Используем переменные переменных, чтобы вызвать соответствующие методы класса
            $ajax_answer = $this->$operation();
        } else {
            $ajax_answer = array(
                'status' => 'error',
                'result' => '',
                'msg' => 'Неизвестная операция',
                'reload_html' => ''
            );
        }
        $this->returnJSONResult($ajax_answer);
    }

}// конец класса =================================================================================================



