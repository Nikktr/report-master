<?php
/**
 * Created by PhpStorm.
 * User: Nikk
 * Date: 16.01.2016
 * Time: 3:10
 */
class CB24
{
    public $arB24App;   // Объект для работы с SDK B24
    public $b24_error = '';
    public $arAccessParams = array();
    public $arCurrentB24User = array();
    public $arB24Tasks = array();
    public $arB24Task = array();
    public $arB24Groups = array();
    public $arB24ContactsFiz = array();
    public $arB24ContactsCompany = array();

    public function prepareFromRequest($arRequest) // Получаем токены из запроса
    {
        $arResult = array();
        $arResult['domain'] = $arRequest['DOMAIN'];
        $arResult['member_id'] = $arRequest['member_id'];
        $arResult['refresh_token'] = $arRequest['REFRESH_ID'];
        $arResult['access_token'] = $arRequest['AUTH_ID'];
        return $arResult;
    }

    public function checkB24Auth() // Проверяет авторизацию, получает новые токены и возвращает объект Б24
    {
        // проверяем актуальность доступа
        $isTokenRefreshed = false;
        $this->arB24App = $this->getBitrix24($this->arAccessParams, $isTokenRefreshed, $this->b24_error);
        return $this->b24_error === true;
    }

    public function getBitrix24(&$arAccessData, &$btokenRefreshed, &$errorMessage, $arScope = array())
    {
        $btokenRefreshed = null;

        $obB24App = new \Bitrix24\Bitrix24();
        if (!is_array($arScope)) {
            $arScope = array();
        }
        if (!in_array('user', $arScope)) {
            $arScope[] = 'user';
        }
        $obB24App->setApplicationScope($arScope);
        $obB24App->setApplicationId(APP_ID);
        $obB24App->setApplicationSecret(APP_SECRET_CODE);

        // set user-specific settings
        $obB24App->setDomain($arAccessData['domain']);
        $obB24App->setMemberId($arAccessData['member_id']);
        $obB24App->setRefreshToken($arAccessData['refresh_token']);
        $obB24App->setAccessToken($arAccessData['access_token']);

        try {
            $resExpire = $obB24App->isAccessTokenExpire();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            // cnLog::Add('Access-expired exception error: '. $error);
        }

        if ($resExpire) {
            // cnLog::Add('Access - expired');

            $obB24App->setRedirectUri(APP_REG_URL);

            try {
                $result = $obB24App->getNewAccessToken();
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                // cnLog::Add('getNewAccessToken exception error: '. $error);
            }
            if ($result === false) {
                $errorMessage = 'access denied';
            } elseif (is_array($result) && array_key_exists('access_token', $result) && !empty($result['access_token'])) {
                $arAccessData['refresh_token'] = $result['refresh_token'];
                $arAccessData['access_token'] = $result['access_token'];
                $obB24App->setRefreshToken($arAccessData['refresh_token']);
                $obB24App->setAccessToken($arAccessData['access_token']);
                // \cnLog::Add('Access - refreshed');
                $btokenRefreshed = true;
            } else {
                $btokenRefreshed = false;
            }
        } else {
            $btokenRefreshed = false;
        }

        return $obB24App;
    }

    public function getB24User() // get information about current user from bitrix24
        {
        $obB24User = new \Bitrix24\User\User($this->arB24App);
        $this->arCurrentB24User = $obB24User->current()['result'];
        $this->arCurrentB24User['IS_ADMIN'] = $obB24User->admin();
        $this->arCurrentB24User['FIO'] = $this->arCurrentB24User['LAST_NAME'] . ' ' .
                                        mb_substr($this->arCurrentB24User['NAME'], 0, 1, 'UTF-8') .
                                        '.' . mb_substr($this->arCurrentB24User['SECOND_NAME'], 0, 1, 'UTF-8') . '.';

        $this->arCurrentB24User = array_change_key_case($this->arCurrentB24User); // переводим ключи массива в нижний регистр ???
        }

    public function getTaskData($taskId) {
        $obB24Task = new \Bitrix24\Task\Item($this->arB24App);
        $arTaskDataFull = $obB24Task->getData($taskId)['result'];
        return $arTaskDataFull;
    }

    // Возвращает все задачи. Для Админа - возвращает задачи ВСЕХ пользователей
    public function getAllTasks()
    {
        $obB24Tasks = new \Bitrix24\Task\Items($this->arB24App);
        $ORDER = array(
            'CREATED_DATE' => 'desc',
        );
        $FILTER = array(">ID" => 1);
        $SELECT = array(
            'TITLE', 'DEADLINE', 'GROUP_ID', 'CREATED_BY', 'CREATED_BY_LAST_NAME', 'CREATED_BY_NAME', 'REAL_STATUS',
            'CHANGED_DATE',
        );
        $nPageSize = 50;
        $iNumPage = 1;
        $NAV_PARAMS = array(
            "nPageSize" => $nPageSize,
            'iNumPage' => $iNumPage
        );
        $this->arB24Tasks = array();
        do {
            $taskList = $obB24Tasks->getList($ORDER, $FILTER, $SELECT, $NAV_PARAMS);
            $this->arB24Tasks = array_merge($this->arB24Tasks, $taskList['result']);
            $iNumPage++;
            $NAV_PARAMS = array(
                "nPageSize" => $nPageSize,
                'iNumPage' => $iNumPage,
            );
        } while ($taskList['next']);
    }

    public function getTasksDo()
    {
        $obB24Tasks = new \Bitrix24\Task\Items($this->arB24App);
        $ORDER = array(
            'CREATED_DATE' => 'desc',
        );
        $FILTER = array(
            'RESPONSIBLE_ID' => $this->arCurrentB24User['id'],
        );
        $SELECT = array(
            'TITLE', 'DEADLINE', 'GROUP_ID', 'CREATED_BY', 'CREATED_BY_LAST_NAME', 'CREATED_BY_NAME',
            'FORUM_ID', 'FORUM_TOPIC_ID', 'REAL_STATUS',
        );
        $nPageSize = 50;
        $iNumPage = 1;
        $NAV_PARAMS = array(
            "nPageSize" => $nPageSize,
            'iNumPage' => $iNumPage
        );
        $this->arB24Tasks = array();
        do {
            $taskList = $obB24Tasks->getList($ORDER, $FILTER, $SELECT, $NAV_PARAMS);
            $this->arB24Tasks = array_merge($this->arB24Tasks, $taskList['result']);
            $iNumPage++;
            $NAV_PARAMS = array(
                "nPageSize" => $nPageSize,
                'iNumPage' => $iNumPage,
            );
        } while ($taskList['next']);
    }

    public function getTasksAccomp()
    {
        $obB24Tasks = new \Bitrix24\Task\Items($this->arB24App);
        $ORDER = array(
            'CREATED_DATE' => 'desc',
        );
        $FILTER = array(
            'ACCOMPLICE' => $this->arCurrentB24User['id'],
        );
        $SELECT = array(
            'TITLE', 'DEADLINE', 'GROUP_ID', 'CREATED_BY', 'CREATED_BY_LAST_NAME', 'CREATED_BY_NAME',
            'FORUM_ID', 'FORUM_TOPIC_ID', 'REAL_STATUS',
        );
        $nPageSize = 50;
        $iNumPage = 1;
        $NAV_PARAMS = array(
            "nPageSize" => $nPageSize,
            'iNumPage' => $iNumPage
        );
        $this->arB24Tasks = array();
        do {
            $taskList = $obB24Tasks->getList($ORDER, $FILTER, $SELECT, $NAV_PARAMS);
            $this->arB24Tasks = array_merge($this->arB24Tasks, $taskList['result']);
            $iNumPage++;
            $NAV_PARAMS = array(
                "nPageSize" => $nPageSize,
                'iNumPage' => $iNumPage,
            );
        } while ($taskList['next']);
    }

    public function getTasksDelegate()
    {
        $obB24Tasks = new \Bitrix24\Task\Items($this->arB24App);
        $ORDER = array(
            'CREATED_DATE' => 'desc',
        );
        $FILTER = array(
            'CREATED_BY' => $this->arCurrentB24User['id'],
        );
        $SELECT = array(
            'TITLE', 'DEADLINE', 'GROUP_ID', 'CREATED_BY', 'CREATED_BY_LAST_NAME', 'CREATED_BY_NAME',
            'FORUM_ID', 'FORUM_TOPIC_ID', 'REAL_STATUS',
        );
        $nPageSize = 50;
        $iNumPage = 1;
        $NAV_PARAMS = array(
            "nPageSize" => $nPageSize,
            'iNumPage' => $iNumPage
        );
        $this->arB24Tasks = array();
        do {
            $taskList = $obB24Tasks->getList($ORDER, $FILTER, $SELECT, $NAV_PARAMS);
            $this->arB24Tasks = array_merge($this->arB24Tasks, $taskList['result']);
            $iNumPage++;
            $NAV_PARAMS = array(
                "nPageSize" => $nPageSize,
                'iNumPage' => $iNumPage,
            );
        } while ($taskList['next']);
    }

    public function getTasksAudit()
    {
        $obB24Tasks = new \Bitrix24\Task\Items($this->arB24App);
        $ORDER = array(
            'CREATED_DATE' => 'desc',
        );
        $FILTER = array(
            'AUDITOR' => $this->arCurrentB24User['id'],
        );
        $SELECT = array(
            'TITLE', 'DEADLINE', 'GROUP_ID', 'CREATED_BY', 'CREATED_BY_LAST_NAME', 'CREATED_BY_NAME',
            'FORUM_ID', 'FORUM_TOPIC_ID', 'REAL_STATUS',
        );
        $nPageSize = 50;
        $iNumPage = 1;
        $NAV_PARAMS = array(
            "nPageSize" => $nPageSize,
            'iNumPage' => $iNumPage
        );
        $this->arB24Tasks = array();
        do {
            $taskList = $obB24Tasks->getList($ORDER, $FILTER, $SELECT, $NAV_PARAMS);
            $this->arB24Tasks = array_merge($this->arB24Tasks, $taskList['result']);
            $iNumPage++;
            $NAV_PARAMS = array(
                "nPageSize" => $nPageSize,
                'iNumPage' => $iNumPage,
            );
        } while  ($taskList['next']);
    }

    public function getTasksAll ()
    {
        $arAllTasks = array();
        $this->getTasksAccomp();
        $arAllTasks = array_merge($arAllTasks, $this->arB24Tasks);
        $this->getTasksAudit();
        $arAllTasks = array_merge($arAllTasks, $this->arB24Tasks);
        $this->getTasksDelegate();
        $arAllTasks = array_merge($arAllTasks, $this->arB24Tasks);
        $this->getTasksDo();
        $arAllTasks = array_merge($arAllTasks, $this->arB24Tasks);
        $this->arB24Tasks = $arAllTasks;
    }

    public function getGroups()
    {
        $obB24Groups = new Bitrix24\Sonet\SonetGroup($this->arB24App);
        $ORDER = array(
            'NAME' => 'asc',
        );
        $FILTER = array();
       //$IS_ADMIN = 'N';
        $groups = $obB24Groups->Get($ORDER, $FILTER);
        $this->arB24Groups = $groups['result'];
    }

    public function getContactsCompany()
    {
        $obB24Contacts = new Bitrix24\CRM\Company($this->arB24App);
        $ORDER = array(
            'TITLE' => 'asc',
        );
        $FILTER = array(
            "COMPANY_TYPE" => "CUSTOMER"
        );
        $SELECT = array(
            "ID", "TITLE",
        );
        $START = 0;
        $this->arB24ContactsCompany = $obB24Contacts->getList($ORDER, $FILTER, $SELECT, $START)['result'];
    }

    public function getContactData($id, $type)
    {
        $contactFizData = array(
          'fiz' => array(),
          'ur' => array(),
        );

        if ($type == 'fiz') {
            $obB24ContactFiz = new Bitrix24\CRM\Contact($this->arB24App);
            $contactFizData['fiz'] = $obB24ContactFiz->get($id)['result'];
        } else {
            $obB24ContactUr = new Bitrix24\CRM\Company($this->arB24App);
            $contactFizData['ur'] = $obB24ContactUr->get($id)['result'];
        }
        return $contactFizData;
    }

    public function getContactsFiz()
    {
        $obB24Contacts = new Bitrix24\CRM\Contact($this->arB24App);
        $ORDER = array(
            'LAST_NAME' => 'asc',
        );
        $FILTER = array(
            "TYPE_ID" => "CLIENT"
        );
        $SELECT = array(
            "ID", "NAME", "LAST_NAME", "SECOND_NAME", "SOURCE_ID"
        );
        $START = 0;
        $this->arB24ContactsFiz = $obB24Contacts->getList($ORDER, $FILTER, $SELECT, $START)['result'];
    }

    public function get_task($task_id)
    {
        $obB24Task = new \Bitrix24\Task\Item($this->arB24App);
        $this->arB24Task = $obB24Task->getData($task_id)['result'];
    }

} // end CB24 class ************************************************************************************************