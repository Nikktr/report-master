/**
 * Created by Nikk on 11.01.2016.
 */

var $loadedHtml1 = $('#loaded_html_1');
var $loadedHtml2 = $('#loaded_html_2');
var $mainGUI = $('#main_gui');
var $subGUI = $('#sub_gui');
var arReportParams;
var date = new Date();
var fd = date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
var contentOld = {};   //объявляем переменную для хранения исходного текста для функции редактирования в таблице отчета
var flagNL = false;  // флаг нажатия на клавишу Shift Нужно чтобы по Shift+Enter был перевод строки


// Отправка и получение ajax запроса на сервер. Возвращает Deferred объект
/**
 * Управление ajax запросами
 * @param {object} operationCmd  Параметры передаваемые серверу Post запросом.
 * @param {string} [showLoader=yes]  Показывать крутящийся индикатор загрузки (yes|no).
 * @returns {object} Возвращает deferred объект с ответом сервера (выполнено декодирование JSON).
 */
function manageAjax (operationCmd, showLoader) {
    var ajaxDeferred = $.Deferred();
    var params = BX24.getAuth(); // Параметры передаваемые аяксом на сервер. Хранят данные авторизации + operation

    showLoader = showLoader || 'yes';
    // Выводим крутящийся индикатор загрузки -- Плагин Center-Loader-Master
    if (showLoader == 'yes') {
        $('#top').loader('show', '<i class="fa fa-spinner fa-5x fa-spin"></i>'); //todo Настроить место отображения спинера (вверх страницы)
    }

    $.extend(true, params, operationCmd);
    console.log('Данные, отправленные на сервер: ',params);
    $.post(
        "https://mkaarbat.ru/apps/hourly-reports/index.php",
        params)
        .done(function (answer) { // Обработчик срабатывает ТОЛЬКО при Положительном завершении ajax запроса
            var $modal = '';
            var modalHideTimeout = 1000; // Время показа модали при успешном ответе сервера
            try {
                answer = $.parseJSON(answer);
                if (answer.msg != "") {
                    if (answer.status == "success") {
                        $modal = $('#modal-success');
                    }
                    if (answer.status == "error") {
                        $modal = $('#modal-danger');
                        modalHideTimeout = 500000;
                    }
                    //открыть модальное окно
                    $modal.modal('show').find('.modal-body').html(answer.msg);
                    setTimeout(function () {
                        $modal.modal('hide');
                    }, modalHideTimeout);
                }
                if (answer.debug != '') {
                    console.info('******** Отладочная инфа с сервера***********************************',
                                  answer.debug,
                                 '*********************************************************************');
                }
                if (answer.status == "success") {
                    ajaxDeferred.resolve(answer);
                }
                if (answer.status == "success") {
                    ajaxDeferred.reject(answer);
                }
            } catch (e) {
                console.log('Server answer: ', answer);
                console.log('Exception: ', e);
                alert('Ошибка на сервере! Перезагрузите приложение Мастер отчетов.');
                ajaxDeferred.reject(answer, e);
            }
        })
        .always(function () {  // Обработчик срабатывает при Положительном и Отрицательном завершении ajax запроса
            if (showLoader == 'yes') {
                $('#top').loader('hide'); // Убираем вращающийся индикатор загрузки
            }
        });
    return ajaxDeferred.promise();
}

// Выводит данные в поля формы редактирования записи
function setEditForm(itemData){
    $("#rep_item_date").val(itemData.item_date);
    $("#rep_item_elapsed_hr").val(parseInt(itemData.item_elapsed));
    $("#rep_item_elapsed_min").val((parseFloat(itemData.item_elapsed) - parseInt(itemData.item_elapsed)) * 60);
    $("#rep_item_action").val(itemData.item_action);
    $("#rep_item_result").val(itemData.item_result);
    $("#rep_item_comment").val(itemData.item_comment);
    $("#edit_report_form").attr("data-rep_item_id", itemData.id);
    $("#rep_item_task_name").html(itemData.item_task_name).attr("rep_item_task_id", itemData.item_task_id);
    $("#rep_item_client_name").html(itemData.item_client_name).attr("rep_item_client_id", itemData.item_client_id);
    $("#rep_item_group_name").html(itemData.item_group_name).attr("rep_item_group_id", itemData.item_group_id);
    $("#rep_item_lawsuit_name").html(itemData.item_lawsuit_name).attr("rep_item_lawsuit_id", itemData.item_lawsuit_id);
    $("#rep_item_calendar_name").html(itemData.item_calendar_name).attr("rep_item_calendar_id", itemData.item_calendar_id);
    BX24.fitWindow(); // ресайз iframe под новые данные
}

// Очищает форму редактирования записи
function resetEditForm() {
    $("#rep_item_date").val(fd);
    $("#rep_item_elapsed_hr").val("");
    $("#rep_item_elapsed_min").val("");
    $("#rep_item_action").val("");
    $("#rep_item_result").val("");
    $("#rep_item_comment").val("");
    $("#edit_report_form").attr("data-rep_item_id","");
    $("#rep_item_task_name").html("").attr("rep_item_task_id", "");
    $("#rep_item_client_name").html("").attr("rep_item_client_id", "");
    $("#rep_item_group_name").html("").attr("rep_item_group_id", "");
    $("#rep_item_lawsuit_name").html("").attr("rep_item_lawsuit_id", "");
    $("#rep_item_calendar_name").html("").attr("rep_item_calendar_id", "");
    $('#rep_item_btn_save').prop('disabled', true);
    $('#rep_item_btn_save_crt').prop('disabled', true);
    $('#rep_item_btn_delete').prop('disabled', true);
}

// Возвращает массив со значениями полей формы редактирования записи
function getDataFromEditForm() {
    return {
        rep_item_date: $("#rep_item_date").val(),
        rep_item_elapsed: parseInt($("#rep_item_elapsed_hr").val()) + parseInt($("#rep_item_elapsed_min").val())/60,
        rep_item_action: $("#rep_item_action").val(),
        rep_item_result: $("#rep_item_result").val(),
        rep_item_comment: $("#rep_item_comment").val(),
        rep_item_id: $("#edit_report_form").attr("data-rep_item_id"),
        rep_item_task_name: $("#rep_item_task_name").html(),
        rep_item_client_name: $("#rep_item_client_name").html(),
        rep_item_group_name: $("#rep_item_group_name").html(),
        rep_item_lawsuit_name: $("#rep_item_lawsuit_name").html(),
        rep_item_calendar_name: $("#rep_item_calendar_name").html(),
        rep_item_task_id: $("#rep_item_task_name").attr("rep_item_task_id"),
        rep_item_client_id: $("#rep_item_client_name").attr("rep_item_client_id"),
        rep_item_group_id: $("#rep_item_group_name").attr("rep_item_group_id"),
        rep_item_lawsuit_id: $("#rep_item_lawsuit_name").attr("rep_item_lawsuit_id"),
        rep_item_calendar_id: $("#rep_item_calendar_name").attr("rep_item_calendar_id")
    };
}

// Возвращает массив со значениями полей формы Построения отчета по Доверителю
function getBuildParamsFromForm() {
    if (arReportParams['report_object'] == 'client' ) {
        arReportParams.report_for = $("#boc_report_for").val();
    }
    arReportParams.report_name = $("#boc_report_name").val();
    arReportParams.object_id = $("#boc_object_name").attr('data-boc_object_id');
    arReportParams.object_name = $("#boc_object_name").text();
    arReportParams.first_day = $("#boc_fday").val();
    arReportParams.last_day = $("#boc_lday").val();
    arReportParams.cost_hour = $("#boc_cost_hour").val() || 0;
    arReportParams.include_all_items = $("#boc_include_all_items").prop("checked") ? 1 : 0;
    arReportParams.report_period = $("#boc_report_period").val();
}

// Загружает список и Устанавливает события на выбор Доверителя и на нажатие кнопок в списке Выбор Доверителя
function selectClient() {
    var selectDfrd = $.Deferred();
    var operationCmd = {operation: 'getContactsFiz'}; // Команда - название метода php
    $loadedHtml1.css("display", "block");
    $loadedHtml1.off('click', '#contact_btn_company');
    $loadedHtml1.off('click', '#contact_btn_fiz');
    manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
            $loadedHtml1.html(answer['reload_html']); // Загружаем форму со списком контактов
            $('#contact_btn_fiz').button('toggle'); // Ставим статус Нажата на кнопке ФизЛица
            document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
            BX24.fitWindow(); // ресайз iframe под новые данные
    });

    // Выбор клиента. Нажатие на имя клиента.
    $loadedHtml1.on('click', 'button.contact_id', function () {
        var selectedClient = {clientId: $(this).attr('data-contact-id'),
                        clientName: $(this).html()};
        $loadedHtml1.css("display", "none").empty();
        $(this).off('click');
        $loadedHtml1.off('click', '#contact_btn_fiz, #contact_btn_company');
        selectDfrd.resolve(selectedClient);
        $loadedHtml1.off('click', 'button.contact_id');
    });

    // Загружает и отображает список физлиц
    $loadedHtml1.on('click', '#contact_btn_fiz', function () {
        var operationCmd = {operation: 'getContactsFiz'}; // Команда - название метода php
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml1.html(answer['reload_html']); // Загружаем список контактов
                $('#contact_btn_fiz').button('toggle');
                $('#contact_btn_company').button('reset');
                document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    // Загружает и отображает список юрлиц
    $loadedHtml1.on('click', '#contact_btn_company', function () {
        var operationCmd = {operation: 'getContactsCompany'}; // Команда - название метода php
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml1.html(answer['reload_html']); // Загружаем список контактов
                $('#contact_btn_fiz').button('reset');
                $('#contact_btn_company').button('toggle');
                document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    return selectDfrd.promise();
}

// Устанавливает события на выбор Группы в списке Выбор Группы
function selectGroup() {
    var selectDfrd = $.Deferred();
    var operationCmd = {operation: 'getGroups'}; // Команда - название метода php
    $loadedHtml1.css("display", "block");
    manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
            $loadedHtml1.html(answer['reload_html']); // Отображаем загруженный список Групп
            document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
            BX24.fitWindow(); // ресайз iframe под новые данные
    });

    $loadedHtml1.on('click', 'button.group_id', function () {
        var selectedGroup = {groupId: $(this).attr('data-group-id'), groupName: $(this).html()};
        $loadedHtml1.css("display", "none").empty();
        $(this).off('click');
        selectDfrd.resolve(selectedGroup);
        $loadedHtml1.off('click', 'button.group_id');
    });

    return selectDfrd.promise();
}

// Устанавливает события на выбор Задачи и на нажатие кнопок в списке Выбор Задачи
function selectTask() {
    var selectDfrd = $.Deferred();
    var operationCmd = {operation: 'getTasksDo'}; // Команда - название метода php

    $loadedHtml1.css("display", "block");
    $loadedHtml1.off('change', '#show_canceled');
    $loadedHtml1.off('click', '#task_btn_do');
    $loadedHtml1.off('click', '#task_btn_accomp');
    $loadedHtml1.off('click', '#task_btn_delegate');
    $loadedHtml1.off('click', '#task_btn_audit');

    manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
            $loadedHtml1.html(answer['reload_html']); // Загружаем  список задач Делаю
            $('#task_btn_do').button('toggle'); // Ставим статус Нажата на кнопке Делаю
            document.getElementById('top').scrollIntoView(); // Листает экран в верхнюю точку iframe
            BX24.fitWindow(); // ресайз iframe под новые данные
    });

    // Выбор задачи
    $loadedHtml1.on('click', 'button.task_id', function () {
        var selectedTask = {taskId: $(this).attr('data-task-id'), taskName: $(this).html()};
        $loadedHtml1.css("display", "none").empty();
        $(this).off('click');
        $loadedHtml1.off('click change', '#show_canceled, #task_btn_do, #task_btn_accomp, #task_btn_delegate, #task_btn_audit');

        // Дополнительный запрос к серверу - Получаем с сервера название Группы и Доверителя
        var operationCmd = {operation: 'getTaskData', taskId: selectedTask.taskId}; // Команда - название метода php
        manageAjax(operationCmd, 'yes').done(function (answer) { // при успешном выполнении ajax запроса
                // добавляем в массив данные по Группе и Доверителю
                // groupId, groupName, clientId, clientName
                $.extend(true, selectedTask, answer.result);
                $loadedHtml1.off('click', 'button.task_id');
                selectDfrd.resolve(selectedTask);
        });
    });

    // Чекбокс - показывать завершенные задачи
    $loadedHtml1.on('change', '#show_canceled', function () {
        if ($(this).is(':checked')) {
            $('.task-canceled').css('display', 'table-row');
        }
        else {
            $('.task-canceled').css('display', 'none');
        }
        BX24.fitWindow(); // ресайз iframe под новые данные
    });

    // Нажатие на кнопку Делаю
    $loadedHtml1.on('click', '#task_btn_do', function () {
        var operationCmd = {operation: 'getTasksDo'}; // Команда - название метода php
        document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                $('#task_btn_do, #task_btn_accomp, #task_btn_delegate, #task_btn_audit').button('reset');
                $('#task_btn_do').button('toggle');
                BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    // Нажатие на кнопку Помогаю
    $loadedHtml1.on('click', '#task_btn_accomp', function () {
        var operationCmd = {operation: 'getTasksAccomp'}; // Команда - название метода php
        document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                $('#task_btn_accomp, #task_btn_do, #task_btn_delegate, #task_btn_audit').button('reset');
                $('#task_btn_accomp').button('toggle');
                BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    // Нажатие на кнопку Поручил
    $loadedHtml1.on('click', '#task_btn_delegate', function () {
        var operationCmd = {operation: 'getTasksDelegate'}; // Команда - название метода php
        document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                $('#task_btn_delegate, #task_btn_do, #task_btn_accomp, #task_btn_audit').button('reset');
                $('#task_btn_delegate').button('toggle');
                BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    // Нажатие на кнопку Наблюдаю
    $loadedHtml1.on('click', '#task_btn_audit', function () {
        var operationCmd = {operation: 'getTasksAudit'}; // Команда - название метода php
        document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                $('#task_btn_audit, #task_btn_do, #task_btn_accomp, #task_btn_delegate').button('reset');
                $('#task_btn_audit').button('toggle');
                BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    return selectDfrd.promise();
}

// Стандартный диалог подтверждения Удаления записи. Возвращает bool
function confirmDeleteItem(str) {
    return (confirm("Вы подтверждаете удаление записи:\n" + str));
}

BX24.init(function () {

});

BX24.ready(function () {
    BX24.fitWindow();
});

$(document).ready(function () {
    //todo Сделать кнопки Назад или Отмена в блоках ВЫБОРА

    // Управляет значениями в поле ввода Затраченного времени
    $('#rep_item_elapsed_min').on('change', function () {
        var elTimeMin = $('#rep_item_elapsed_min').val();
        var elTimeHr = $('#rep_item_elapsed_hr').val();
        if (elTimeMin == 60) {
            $('#rep_item_elapsed_min').val('0');
            $('#rep_item_elapsed_hr').val(parseInt(elTimeHr) + 1);
        }
        if (elTimeMin == -15) {
            $('#rep_item_elapsed_min').val('0');
            if (elTimeHr >= 1) {
                $('#rep_item_elapsed_hr').val(parseInt(elTimeHr) - 1);
            }
        }
    });

    // Компонент Tooltip Bootstrap - Показывает содержание поля комментарии
    $("[data-toggle = 'tooltip']").tooltip();

    // Устанавливаем автоматический Скрол в начало страницы при отображении модали
    $(".modal").on("shown.bs.modal", function () {
            document.getElementById('top').scrollIntoView(); // Скрол в начало страницы при отображении модали
    });

    // Загрузка записи в форму редактирования из списка Недавние записи
    $([$mainGUI[0], $loadedHtml1[0]]).on('click', 'span.recent-item-txt', function () {
        var itemId = $(this).attr('data-recent_item_id');
        var operationCmd = {operation: 'getItem', rep_item_id: itemId};
        resetEditForm();
        document.getElementById('top').scrollIntoView(); // Листает экран в верхнюю точку iframe
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
            setEditForm(answer.result);
            $mainGUI.css("display", "block");
            $loadedHtml1.css("display", "none");
            $('#rep_item_btn_save, #rep_item_btn_save_crt, #rep_item_btn_delete').prop("disabled", false);
            $('#home').addClass('active');
            $('#show_items, #back, #settings, #build_report, #manage_reports').removeClass('active');
            BX24.fitWindow();
        });
    });

    //Нажатие на кнопку Привязать Доверителя
    $mainGUI.on('click', '#rep_item_btn_client', function () {
        $mainGUI.css("display", "none");

        //  Загружаем форму выбора Доверителя и ставим event на клики в форме
        // Обрабатываем Promise от формы выбора Доверителя
        selectClient().done(function (selectedClient) {
            $('#rep_item_client_name')
                .attr('rep_item_client_id', selectedClient.clientId)
                .html(selectedClient.clientName);
            $mainGUI.css("display", "block");
            document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
            BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    //Нажатие на кнопку Привязать Группу
    $mainGUI.on('click', '#rep_item_btn_group', function () {
        $mainGUI.css("display", "none");

        // Грузим форму и ставим event на клик по имени Группы в блоке Выбор Группы
        selectGroup().done(function (selectedGroup) {
            $('#rep_item_group_name')
                .attr('rep_item_group_id', selectedGroup.groupId)
                .html(selectedGroup.groupName);
            $mainGUI.css("display", "block");
            document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
            BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    // Нажатие на кнопку Привязать Задачу
    $mainGUI.on('click', '#rep_item_btn_task', function () {
        $mainGUI.css("display", "none");

        // Грузим форму выбора задачи. Возвращает deferred объет
        selectTask().done(function (selectedTask) {
            $('#rep_item_task_name')
                .attr('rep_item_task_id', selectedTask.taskId)
                .html(selectedTask.taskName);
            $('#rep_item_client_name')
                .attr('rep_item_client_id', selectedTask.clientId)
                .html(selectedTask.clientName);
            $('#rep_item_group_name')
                .attr('rep_item_group_id', selectedTask.groupId)
                .html(selectedTask.groupName);
            $mainGUI.css("display", "block");
            document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
            BX24.fitWindow(); // ресайз iframe под новые данные
        })
    });

    // Активируем кнопки Сохранить и Сохранить+создать если хотя бы одно поле ввода не пусто
    $('#rep_item_action, #rep_item_result, #rep_item_comment').change(function () {
        var action = $('#rep_item_action').val();
        var result = $('#rep_item_result').val();
        var comment = $('#rep_item_comment').val();
        var $saveButton = $('#rep_item_btn_save, #rep_item_btn_save_crt');
        if (action != '' || result != '' || comment != ''){
            $saveButton.prop('disabled', false);
        } else {
            $saveButton.prop('disabled', true);
        }
    });

    // Нажатие на кнопку Удалить в форме редактирования записи отчета или в списке Недавние записи
    $('#edit_report_item, #recent_items, #loaded_html_1').on('click', '#rep_item_btn_delete, span.recent-item-delete', function () {
        var $button = $(this);
        var itemId = '';
        var itemVal = '';
        var operationCmd = {operation: 'deleteItem'}; // Команда - название метода  php

        if ($button[0].id == 'rep_item_btn_delete') {
            itemId = {rep_item_id: $("#edit_report_form").attr("data-rep_item_id")}; // Получаем id записи из формы
            itemVal = $("#rep_item_action").val(); // Получаем значение Action записи из формы для подтверждения удаления
        } else {
            itemId = {rep_item_id: $(this).attr("data-recent_item_id")}; // Получаем id записи из списка
            itemVal = $(this).attr("data-recent_item_action"); //Получаем значение Action записи из СТРОКИ для подтверждения удаления
        }
        $.extend(true, operationCmd, itemId);
        if (confirmDeleteItem(itemVal)) {
            // Проверяем, если Удалить нажато в списке,
            // то скрываем строку таблицы <tr> с экрана, чтобы не перезагружать список с сервера
            // костыль для меню - Показать записи
            if ($button[0].className == 'recent-item-delete') {
                var trId = '#' + itemId.rep_item_id;
                $(trId, '#loaded_html_1').css('display', 'none');
            }
            resetEditForm();
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    $('#recent_items').html(answer['reload_html']); // обновляем Недавние записи
                    BX24.fitWindow(); // ресайз iframe под новые данные
            });
        }
    });

    // Нажатие на кнопку Сохранить или Сохранить+Создать в форме редактирования записи отчета
    $('#rep_item_btn_save, #rep_item_btn_save_crt').on('click', function () {
        var $button = $(this);
        var form_items = getDataFromEditForm();
        var operationCmd = {}; // Команда - название метода php

        if (form_items.rep_item_id == ''){
            operationCmd = {operation: 'insertItem'};
        } else {
            operationCmd = {operation: 'updateItem'};
        }
        $.extend(true, operationCmd, form_items);
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
            // Если нажата кнопка Сохранить+Создать
            if ($button[0].id == 'rep_item_btn_save_crt') {
                $('#rep_item_btn_delete').prop('disabled', true); // Деактивируем кнопку Удалить
                resetEditForm();// Если нажата кнопка Сохранить+Создать - обнуляем форму ввода
            } else { // если нажата просто Сохранить
                $('#edit_report_form').attr('data-rep_item_id', answer.result); // заполняем id записи в форме
                $('#rep_item_btn_delete').prop('disabled', false); // Активируем кнопку Удалить
            }
            $('#recent_items').html(answer['reload_html']); // обновляем Недавние записи
            BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    // NavBar -- Нажатие на Создать/Редактировать
    $('#home').on('click', function () {
        var operationCmd = {operation: 'reloadRecentItems'}; // Команда - название метода php
        $(this).addClass('active');
        $('#show_items, #settings, #build_report, #manage_reports').removeClass('active');

        $mainGUI.css("display", "block");
        $loadedHtml1.css("display", "none");
        $loadedHtml2.css("display", "none");
        $subGUI.css("display", "none");
        document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $('#recent_items').html(answer['reload_html']); // обновляем Недавние записи
                BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    // NavBar -- Нажатие на Показать записи
    $('#show_items').on('click', function (){
        var operationCmd = {operation: 'showAllItems', period: 'curWeek'}; // Команда - название метода php
        $(this).addClass('active');
        $('#home, #settings, #build_report, #manage_reports').removeClass('active');
        $mainGUI.css("display", "none");
        $loadedHtml1.css("display", "block");
        $subGUI.css("display", "none");
        $loadedHtml2.css("display", "none");

        $loadedHtml1.off('click', '#all_items_current_week');
        $loadedHtml1.off('click', '#all_items_previous_week');
        $loadedHtml1.off('click', '#all_items_current_month');
        $loadedHtml1.off('click', '#all_items_previous_month');

        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                $('#all_items_current_week').button('toggle');
                document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                BX24.fitWindow(); // ресайз iframe под новые данные
        });

        $loadedHtml1.on('click', '#all_items_current_week', function () {
            var operationCmd = {operation: 'showAllItems', period: 'curWeek'}; // Команда - название метода php
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                    $('#all_items_current_week, #all_items_previous_week, #all_items_current_month, #all_items_previous_month').button('reset');
                    $('#all_items_current_week').button('toggle');
                    BX24.fitWindow(); // ресайз iframe под новые данные
            });
        });

        $loadedHtml1.on('click', '#all_items_previous_week', function () {
            var operationCmd = {operation: 'showAllItems', period: 'prevWeek'}; // Команда - название метода php
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                    $('#all_items_current_week, #all_items_previous_week, #all_items_current_month, #all_items_previous_month').button('reset');
                    $('#all_items_previous_week').button('toggle');
                    BX24.fitWindow(); // ресайз iframe под новые данные
            });
        });

        $loadedHtml1.on('click', '#all_items_current_month', function () {
            var operationCmd = {operation: 'showAllItems', period: 'curMonth'}; // Команда - название метода php
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                    $('#all_items_current_week, #all_items_previous_week, #all_items_current_month, #all_items_previous_month').button('reset');
                    $('#all_items_current_month').button('toggle');
                    BX24.fitWindow(); // ресайз iframe под новые данные
            });
        });

        $loadedHtml1.on('click', '#all_items_previous_month', function () {
            var operationCmd = {operation: 'showAllItems', period: 'prevMonth'}; // Команда - название метода php
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    $loadedHtml1.html(answer['reload_html']); // обновляем Недавние записи
                    $('#all_items_current_week, #all_items_previous_week, #all_items_current_month, #all_items_previous_month').button('reset');
                    $('#all_items_previous_month').button('toggle');
                    BX24.fitWindow(); // ресайз iframe под новые данные
            });
        });
    });

    // NavBar -- Нажатие на Построить отчет
    $('#build_report').on('click', function () {
        $(this).addClass('active');
        $('#home, #settings, #show_items, #manage_reports').removeClass('active');
    });

    // NavBar -- Нажатие на Построить отчет -> По Сотруднику
    $('#build_worker').on('click', function () {
        var operationCmd = {operation: 'getWorkerReportForm'}; // Команда - название метода php

        $subGUI.off('click', '#boc_build_report'); // отключаем предыдущие ивенты по кнопке Построить отчет
        $subGUI.off('click', '#boc_save_report');
        $subGUI.off('click', '#bow_select_worker');

        $(this).addClass('active'); // Делаем активным пункт меню
        $('#home, #settings, #show_items, #manage_reports').removeClass('active');
        $mainGUI.css("display", "none");
        $subGUI.css("display", "block");
        $loadedHtml2.empty().css("display", "none");
        $loadedHtml1.empty().css("display", "none");
        // Загружаем форму построения отчета
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $subGUI.html(answer['reload_html']); //
                arReportParams = answer.arReportParams;
                document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                BX24.fitWindow(); // ресайз iframe под новые данные
        });

        // Клик на кнопке в форме Выбрать Сотрудника
        $subGUI.on('click', '#bow_select_worker', function () {
            BX24.selectUser(function(worker){
                $('#boc_object_name')
                    .attr('data-boc_object_id', worker.id)
                    .html(worker.name);
                $('#boc_build_report').prop('disabled', false);  // Активируем кнопку Построить отчет
            })
        });

        // Клик на кнопке в Форме Построить отчет
        $subGUI.on('click', '#boc_build_report', function () {
            var operationCmd = {operation: 'buildReport'}; // Команда - название метода php
            getBuildParamsFromForm();
            var buildParams = {arReportParams: JSON.stringify(arReportParams)};
            $.extend(true, operationCmd, buildParams);
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml2.html(answer['reload_html']); //
                $loadedHtml2.css("display", "block");
                arReportParams = answer.arReportParams;
                // Проверяем на пустой отчет
                if (arReportParams['item_ids_json'].length > 0) {
                    $('#boc_save_report').prop('disabled', false);  // Активируем кнопку Сохранить отчет
                } else {
                    $('#boc_save_report').prop('disabled', true);  // Деактивируем кнопку Сохранить отчет
                }
                $('#boc_report_name').val(arReportParams.report_name);
                document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                BX24.fitWindow(); // ресайз iframe под новые данные
            });
        });

        // Клик на кнопке в Форме Сохранить отчет
        $subGUI.on('click', '#boc_save_report', function () {
            var operationCmd = {operation: 'saveReport'}; // Команда - название метода php
            getBuildParamsFromForm();
            var buildParams = {arReportParams: JSON.stringify(arReportParams)};
            $.extend(true, operationCmd, buildParams);
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    $loadedHtml1.html(answer['reload_html']); //
                    $loadedHtml1.css("display", "block");
                    $loadedHtml2.empty().css("display", "none");
                    $subGUI.css("display", "none");
                    document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                    BX24.fitWindow(); // ресайз iframe под новые данные
            });
            $subGUI.off('click', '#boc_save_report');
        });
    });

    // NavBar -- Нажатие на Построить отчет -> По доверителю
    $('#build_client').on('click', function () {
        var operationCmd = {operation: 'getClientReportForm'}; // Команда - название метода php

        $subGUI.off('click', '#boc_build_report'); // отключаем предыдущие ивенты по кнопке Построить отчет
        $subGUI.off('click', '#boc_select_client');
        $subGUI.off('click', '#boc_save_report');

        $(this).addClass('active'); // Делаем активным пункт меню
        $('#home, #back, #settings, #show_items, #manage_reports').removeClass('active');
        $mainGUI.css("display", "none");
        $subGUI.css("display", "block");
        $loadedHtml2.empty().css("display", "none");
        $loadedHtml1.empty().css("display", "none");
        // Загружаем форму построения отчета
        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $subGUI.html(answer['reload_html']); //
                arReportParams = answer.arReportParams;
                document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                BX24.fitWindow(); // ресайз iframe под новые данные
        });

        // Клик на кнопке в Форме Сохранить отчет
        $subGUI.on('click', '#boc_save_report', function () {
            var operationCmd = {operation: 'saveReport'}; // Команда - название метода php
            getBuildParamsFromForm();
            var buildParams = {arReportParams: JSON.stringify(arReportParams)};
            $.extend(true, operationCmd, buildParams);
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    $loadedHtml1.html(answer['reload_html']); //
                    $loadedHtml1.css("display", "block");
                    $loadedHtml2.empty().css("display", "none");
                    $subGUI.css("display", "none");
                    document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                    BX24.fitWindow(); // ресайз iframe под новые данные
            });
            $subGUI.off('click', '#boc_save_report');
        });

        // Клик на кнопке в Форме Построить отчет
        $subGUI.on('click', '#boc_build_report', function () {
            var operationCmd = {operation: 'buildReport'}; // Команда - название метода php
            getBuildParamsFromForm();
            var buildParams = {arReportParams: JSON.stringify(arReportParams)};
            $.extend(true, operationCmd, buildParams);
            manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    $loadedHtml2.html(answer['reload_html']); //
                    $loadedHtml2.css("display", "block");
                    arReportParams = answer.arReportParams;
                    if (arReportParams['item_ids_json'].length > 0) {
                        $('#boc_save_report').prop('disabled', false);  // Активируем кнопку Сохранить отчет
                    } else {
                        $('#boc_save_report').prop('disabled', true);  // Деактивируем кнопку Сохранить отчет (пришел пустой отчет)
                    }
                    $('#boc_report_name').val(arReportParams.report_name);
                    document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                    BX24.fitWindow(); // ресайз iframe под новые данные
            });
        });

        // Клик на кнопке в форме Выбрать Доверителя
        $subGUI.on('click', '#boc_select_client', function (){
            $subGUI.css("display", "none");
            $loadedHtml2.css("display", "none");
            //  Загружаем форму выбора Доверителя и ставим event на клики в форме
            selectClient().done(function (selectedClient) {
                $('#boc_object_name')
                    .attr('data-boc_object_id', selectedClient.clientId)
                    .html(selectedClient.clientName);
                $subGUI.css("display", "block");
                $('#boc_build_report').prop('disabled', false);  // Активируем кнопку Построить отчет
                BX24.fitWindow(); // ресайз iframe под новые данные
            });
        });
    });

    // NavBar -- Нажатие на Управлять отчетами
    $('#manage_reports').on('click', function () {
        var operationCmd = {operation: 'getReportList', reportRange: 'curMonth'}; // Команда - название метода php

        $(this).addClass('active');
        $('#home, #build_report, #show_items, #settings').removeClass('active');
        $mainGUI.css("display", "none");
        $loadedHtml1.css("display", "none");
        $subGUI.css("display", "none");
        $loadedHtml2.css("display", "none");

        manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                $loadedHtml2.html(answer['reload_html']); //
                $loadedHtml2.css("display", "block");
                document.getElementById('top').scrollIntoView(); // Скрол в начало страницы
                BX24.fitWindow(); // ресайз iframe под новые данные
        });
    });

    // NavBar -- Нажатие на Настройки
    $('#settings').on('click', function () {

        $(this).addClass('active');
        $('#home, #back, #build_report, #show_items, #manage_reports').removeClass('active');


    });


    //Функционал инлайн редактирования в таблице отчета
    //обрабатываем событие нажатие любой клавиши в поле редактирования и отображаем кнопку Сохранить
    $loadedHtml2.on('keydown', '[contenteditable="true"]', function (e) {         //обработчик нажатия Enter
        var elementId = $(this).parent().attr('data-item_id');
        $("button." + elementId).css("display", "block"); //показываем кнопку "сохранить"
        if (e.keyCode == 27) {
            console.log($(this));
            e.preventDefault();
            $(this).text(contentOld[elementId + $(this)[0]['className']]);	//возвращаем текст до редактирования
            $("button." + elementId).css("display", "none"); //показываем кнопку "сохранить"
        }
        if (e.keyCode == 16) {
            flagNL = true; // Нажата Shift
        }
        if ((e.keyCode == 13) && !flagNL) { // Если нажат Enter (если Shift+Enter - Перевод строки)
            e.preventDefault();
            $(this).trigger('blur');	//вызываем Сохранение данных
        }
    });

    $loadedHtml2.on('keyup', '[contenteditable="true"]', function (e) {  // Сбрасываем флаг удержания Shift или Ctrl
        if (e.keyCode == 16) {
            flagNL = false;
        }
    });

    //Функционал инлайн редактирования в таблице отчета
    //обрабатываем событие нажатие мышки или любой клавиши в поле редактирования
    $loadedHtml2.on('focusin', '[contenteditable="true"]', function (e) {
        var elementId = $(this).parent().attr('data-item_id');
        //Сохраняем текст до редактирования. iD + имя класса нужно чтобы отличить Action и Result
        contentOld[elementId + $(this)[0]['className']] = $(this).text();
    });

    //Функционал инлайн редактирования в таблице отчета
    // Потеря фокуса поля, в котором редактируются данные -> Сохранение изменений в базе
    $loadedHtml2.on('focusout', '[contenteditable="true"]', function (e) {
        var elementIdSave = $(this).parent().attr('data-item_id');  //id элемента потерявшего фокус
        var contentSave = $(this).text();           //текст для сохранения
        e.stopImmediatePropagation();
        // Проверяем если текст изменился, то сохраняем в базе
        if ((contentOld[elementIdSave + $(this)[0]['className']] != null) &&
            (contentSave != contentOld[elementIdSave + $(this)[0]['className']]))
        {
            $("button." + elementIdSave).html('<i class= "fa fa-spinner fa-spin"></i> Сохранение');
            var operationCmd = {operation: 'shortUpdateItem'}; // Команда - название метода php
            var itemData = {};
            if ($(this).hasClass('action-item-txt')) {
                itemData = {
                    'item_action': contentSave,
                    'item_id': elementIdSave
                };
            }
            if ($(this).hasClass('result-item-txt')) {
                itemData = {
                    'item_result': contentSave,
                    'item_id': elementIdSave
                };
            }
            if ($(this).hasClass('alt-task-name')) {
                itemData = {
                    'alt_task_name': contentSave || ' ',
                    'item_id': elementIdSave
                };
            }
            $.extend(true, operationCmd, itemData);
            manageAjax(operationCmd, 'no').done(function () { // при успешном выполнении ajax запроса
                $("button." + elementIdSave).html('<span style="color: green">Сохранено</span>');
                setTimeout(function () {
                    $("button." + elementIdSave).html('Сохранить (Enter)').css("display", "none");
                }, 3000);
                BX24.fitWindow(); // ресайз iframe под новые данные
            });
        }
    });

    //Функционал инлайн редактирования в таблице отчета
    // Клик по ячейке таблицы - действие Редактировать elapsed - затрач время
    $loadedHtml2.on('click', 'p.elapsed-item-txt', function () {
        var itemElapsed = $(this).attr('data-item_elapsed');
        var inputHtml = '<input type="number" step="0.25" min="0" max="20" id="edited_action" value="' + itemElapsed + '">';
        $(this).after(inputHtml).css("display", "none");
        $('#edited_action').focus();
        BX24.fitWindow(); // ресайз iframe под новые данные

        $('td #edited_action').one('blur', function () {
            console.log(itemElapsed);
            var operationCmd = {operation: 'shortUpdateItem'}; // Команда - название метода php
            var itemData = {
                'item_elapsed': $(this).val(),
                'item_id': $(this).parent().attr('data-item_id')
            };
            $.extend(true, operationCmd, itemData);
            // проверяем были ли внесены изменения, если Да, то сохраняем в БД
            if ($(this).val() != $(this).parent().find('p:eq(0)').attr('data-item_elapsed')) {
                manageAjax(operationCmd).done(function (answer) { // при успешном выполнении ajax запроса
                    BX24.fitWindow(); // ресайз iframe под новые данные
                });
            }
            $(this).parent().find('p:eq(0)').css("display", "block")
                .html($(this).val()).attr('data-item_elapsed', $(this).val());
            $(this).unbind('blur').remove();
            return false;
        });
    });

});
