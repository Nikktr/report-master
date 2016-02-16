/**
 * Created by Nikk on 21.01.2016.
 */

$('#cmd-1').click(function () {
    operation_cmd = {operation: 'show_request'};
    $.extend(true, params, operation_cmd);
    target_html_id = '#debug-info';

    manageAjax(params, target_html_id);

    <!-- Изменение надписи на кнопке на время загрузки ajax  -->
    $(this).button('loading').delay(1000).queue(function () {
        $(this).button('reset');
        $(this).dequeue();
    });

});

$('#cmd-2').click(function () {
    operation_cmd = {operation: 'show_user_info'};
    $.extend(true, params, operation_cmd);
    target_html_id = '#debug-info';

    manageAjax(params, target_html_id);

    <!-- Изменение надписи на кнопке на время загрузки ajax  -->
    $(this).button('loading').delay(1000).queue(function () {
        $(this).button('reset');
        $(this).dequeue();
    });

});

$('#cmd-6').click(function () { <!-- кнопка очистки отладочной инфы  -->
    target_html_id = '#debug-info';
    $(target_html_id).html("Нет данных...");
    BX24.fitWindow(); // ресайз iframe под новые данные
    $(this).queue(function () {
        $(this).dequeue();
    });
});

$('#cmd-10').click(function () {
    operation_cmd = {operation: 'show_tasks'};
    $.extend(true, params, operation_cmd);
    target_html_id = '#content-2';
    $('#h-content-2').html('Выберите задачу');
    BX24.fitWindow(); // ресайз iframe под новые данные

    manageAjax(params, target_html_id);

    <!-- Изменение надписи на кнопке на время загрузки ajax  -->
    $(this).button('loading').delay(1000).queue(function () {
        $(this).button('reset');
        $(this).dequeue();
    });

});
