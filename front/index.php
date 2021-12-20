<?php
/**
 * -------------------------------------------------------------------------
 * Extrafood tickets module
 * Copyright (C) 2021 by the Kapeshko Oleg.
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Extrafood tickets module.
 *
 * Extrafood tickets module is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Extrafood tickets module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Extrafood tickets module. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

include ("sites/all/modules/bw-tickets/inc/dbfunctions.php");
include ("sites/all/modules/bw-tickets/inc/connections.php");
include ("sites/all/modules/bw-tickets/inc/logger.php");

drupal_add_css('sites/all/modules/bw-tickets/lib/js-datepicker-master/cssworld.ru-xcal.css');
drupal_add_css('sites/all/modules/bw-tickets/css/tickets.css');
drupal_add_css('sites/all/modules/bw-tickets/lib/select2-4.1.0-rc.0/dist/css/select2.css');

drupal_add_js('sites/all/modules/bw-tickets/lib/jquery.maskedinput/jquery.maskedinput.min.js');
drupal_add_js('sites/all/modules/bw-tickets/lib/js-datepicker-master/cssworld.ru-xcal-en.js');
drupal_add_js('sites/all/modules/bw-tickets/lib/select2-4.1.0-rc.0/dist/js/select2.js');

Logger::init();

$cfg = parse_ini_file(__DIR__ . '/../tickets.ini');

// Имя текущего пользователя
global $user;
$userlogin = mb_strtolower($user->name);

$encrypted_name = openssl_encrypt($userlogin, $cfg['method'], $cfg['key'].date('Ymd'), 0 , date('YmdYmd'));

// ПОДКЛЮЧЕНИЕ К LDAP И GEDEMIN
$connect = new Connections();
if (!($connect->checkConnection())) {
    Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$userlogin." - Ошибка при подключении к LDAP / GEDEMIN");
    print_r("ПРОВЕРЬТЕ НАСТРОЙКИ ПОДКЛЮЧЕНИЯ К LDAP И БАЗЕ ДАННЫХ!");
} else {
    
    // ОПРЕДЕЛЕНИЕ ПРАВА ДОСТУПА ПОЛЬЗОВАТЕЛЯ К УПРАВЛЕНИЮ СПЕЦПИТАНИЕМ
    $current_user = DBFunctions::hasAccess($connect->getLdapConn(), $userlogin);
    if (!($current_user)) {
        Logger::info($_SERVER['HTTP_X_REAL_IP']." | ".$userlogin." - Нет прав на управление спецпитанием");
        print_r("ДОСТУП К УПРАВЛЕНИЮ СПЕЦПИТАНИЕМ ЗАПРЕЩЕН!");
    } else {

        if (isset($_GET['action']) && $connect->getGedeminConn()) {
            if ($_GET['action'] == 'add' && isset($_GET['selected_mgr'])  && isset($_GET['assigned_manager'])) {
                DBFunctions::addAssigned($connect->getGedeminConn(), $_GET['assigned_manager'], $_GET['selected_mgr']);
            } elseif (strripos($_GET['action'], 'delete') !== false) {
                DBFunctions::deleteAssigned($connect->getGedeminConn(), str_replace('delete', '', $_GET['action']), $_GET['selected_mgr']);
            }
        }

        // ПРОВЕРКА НА НАЛИЧИЕ ПОДЧИНЕННЫХ ГРУПП ДЛЯ УПРАВЛЕНИЯ И ИХ ЗАГРУЗКА
        $dep_list = DBFunctions::isManager($connect->getLdapConn(), $current_user['dn']) ? [$current_user['employeenumber']] : [];
        $dep_list = DBFunctions::getAssignedGroups($connect->getGedeminConn(), $current_user['employeenumber'], $dep_list);
        if (isset($_GET['action']) && $_GET['action'] == 'select') {
            $sel_mgr_sap_id = $_GET['selected_mgr'];
            $sel_mgr = DBFunctions::getUserBySapId($connect->getLdapConn(), $sel_mgr_sap_id);
        } elseif (DBFunctions::isManager($connect->getLdapConn(), $current_user['dn'])) {
            $sel_mgr_sap_id = $current_user['employeenumber'];
            $sel_mgr = $current_user;
        } elseif (count($dep_list) > 0) {
            $sel_mgr_sap_id = $dep_list[0];
            $sel_mgr = DBFunctions::getUserBySapId($connect->getLdapConn(), $sel_mgr_sap_id);
        } else {
            $sel_mgr = '';
            $sel_mgr_sap_id = '';
        }
        // ПРОВЕРКА ПРИНАДЛЕЖНОСТИ ПОЛЬЗОВАТЕЛЯ К АДМИНИСТРАТОРАМ (50009164 - SAP ID гендиректора)
        if (in_array('administrator', $user->roles)) {
            $gen_dir = DBFunctions::getUserBySapId($connect->getLdapConn(), '50009164');
            $current_user['dn'] = $gen_dir['dn'];
        }
        $dep_list = DBFunctions::getGroups($connect->getLdapConn(), $current_user['dn'], $dep_list);
        $list_mgrs = [];
        $manager = $sel_mgr;
        while ($manager) {
            array_push($list_mgrs, ['employeenumber'    => $manager['employeenumber'],
                                    'name'              => $manager['name'],
                                    'title'             => $manager['title']]);
            if ($manager['employeenumber'] != $manager['localeid']) {
                $manager = DBFunctions::getManager($connect->getLdapConn(), $manager['localeid']);
            } else {
                break;
            }
        }

        if ($assigned = DBFunctions::getAssigned($connect->getGedeminConn(), $sel_mgr_sap_id)) {
            foreach ($assigned as $assign_id) {
                $manager = DBFunctions::getUserBySapId($connect->getLdapConn(), $assign_id);
                array_push($list_mgrs, ['employeenumber'    => $manager['employeenumber'],
                                        'name'              => $manager['name'],
                                        'title'             => $manager['title']]);
            }
        }

        $invalid_mgr = true;
        if (in_array('administrator', $user->roles)) {
            $invalid_mgr = false;
        } else {
            foreach ($list_mgrs as $mgr) {
                if ($mgr['employeenumber'] == $current_user['employeenumber']) {
                    $invalid_mgr = false;
                    break;
                }
            }
        }
        if ($invalid_mgr) {
            print_r("НЕТ ПОДЧИНЕННЫХ ГРУПП ИЛИ ВЫБРАННАЯ ГРУППА НЕ ДОСТУПНА ДЛЯ УПРАВЛЕНИЯ!");
        } else {
            echo '<div id="data">';

            // ВЫВОД ВЫБРАННОГО ПОДРАЗДЕЛЕНИЯ И ЕГО РУКОВОДИТЕЛЯ
            echo '
                <form id="assigned" action="" method="get" accept-charset="utf-8">
                <table class="tickettable">
                    <tr>
                        <th colspan="2">Пользователь:</th>
                    </tr>
                    <tr>
                        <td colspan="2">'.$current_user['cn'].' - '.$current_user['department'].'</td>
                    </tr>
                    <tr>
                        <th colspan="2">Выбранное подразделение:</th>
                    </tr>';
            if (count($dep_list) == 1) { 
                echo '<tr>
                        <td colspan="2">'.$sel_mgr['department'].' ('.$sel_mgr['name'].')</td>
                        <input type="hidden" name="selected_mgr" value="'.$sel_mgr_sap_id.'">
                    </tr>
                ';
            } else {
                echo '  
                    <tr>
                        <td>
                            <select class="js-select2" name="selected_mgr" placeholder="Выберите подразделение">
                                <option value=""></option>';
                foreach ($dep_list as $key => $mgr_id) {
                    $selected = $sel_mgr_sap_id == $mgr_id ? 'selected' : '';
                    $user_info = DBFunctions::getUserBySapId($connect->getLdapConn(), $mgr_id);
                    echo '<option '.$selected.' value="'.$mgr_id.'">'.$user_info['department'].' ('.$user_info['name'].')</option>';
                }
                echo '      </select>
                        </td>
                        <td>
                            <button type="submit" class="custom-button add-button" name="action" value="select">Выбрать</button>
                        </td>
                    </tr>';  
            }

            // ВЫВОД СПИСКА ОТВЕСТВЕННЫХ ЛИЦ ПО ВЫБРАННОМУ ПОДРАЗДЕЛЕНИЮ
            echo '
                    <tr>
                        <th colspan="2">Ответственные:</th>
                    </tr>
            ';
            foreach ($list_mgrs as $mgr) {
                echo '
                    <tr>
                        <td colspan="2">'.$mgr['name'].' - '.$mgr['title'].'</td>
                    </tr>
                ';
            }

            // ВЫВОД СПИСКА ВРЕМЕННО НАЗНАЧЕННЫХ ОТВЕТСТВЕННЫХ ПО ВЫБРАННОМУ ПОДРАЗДЕЛЕНИЮ
            echo '
                    <tr>
                        <th colspan="2">Временнные ответственные:</th>
                    </tr>
            ';
            if ($assigned = DBFunctions::getAssigned($connect->getGedeminConn(), $sel_mgr_sap_id)) {
                foreach ($assigned as $manager_id) {
                    $manager = DBFunctions::getManager($connect->getLdapConn(), $manager_id);
                    array_push($list_mgrs, $manager['name']);
                    echo '
                        <tr>
                            <td>'.$manager['name'].' - '.$manager['title'].'</td>
                            <td><button type="submit" class="custom-button delete-button" name="action" value="delete'.$manager['employeenumber'].'">Удалить</button></td>
                        </tr>
                    '; 
                }
            }

            // ФОРМИРОВАНИЕ СПИСКА ДЛЯ ДОБАВЛЕНИЯ ОТВЕТСТВЕННОГО
            echo '
                    <tr>
                        <td>
                            <select class="js-select2" name="assigned_manager" placeholder="Выберите ответственного">
                                <option value=""></option>'; 
            $filter = "(&(employeeType>=0)(objectCategory=person)(objectClass=user))";
            $attrs = array('cn', 'employeenumber');
            $search = ldap_search($connect->getLdapConn(), DBFunctions::$searchbase, $filter, $attrs);
            $users = ldap_get_entries($connect->getLdapConn(), $search);
            if ($users['count'] > 0) {
                foreach ($users as $usr) {
                    if (isset($usr['cn']) && isset($usr['employeenumber']) && !(in_array($usr['employeenumber'][0], $list_mgrs))) {
                        echo '<option value="'.$usr['employeenumber'][0].'">'.$usr['cn'][0].'</option>';
                    }
                }
            }
            echo '          </select>
                        </td>
                        <td><button type="submit" class="custom-button add-button" name="action" value="add">Добавить</button></td>
                    </tr>
                </table>
                </form>
            ';

            // ВЫВОД ДАТЫ
            echo '<div class="div-container">';
            echo '<input class="custom-input" id="date" type="text" value="'.date('Y-m-d').'" size="8" onClick="xCal(this)" onmouseover="xCal(this)" onKeyUp="xCal()">';
            echo '<button class="custom-button" onclick="loadTickets(jQuery(\'#date\').val())">Загрузить</button><p>';
            echo '</div>';

            // ВЫВОД ТАБЛИЦЫ ДЛЯ ВЫДАЧИ ТАЛОНОВ
            echo "<table id='tickettable' class='tickettable'>";
            echo DBFunctions::loadTickets($connect->getLdapConn(), $connect->getGedeminConn(), $sel_mgr_sap_id, date('Y-m-d'), $user->name);
            echo "</table>";

            // ВЫВОД ТАБЛИЦЫ ВЫДАННЫХ ТАЛОНОВ ЗА МЕСЯЦ
            $arr = [
                'Январь',
                'Февраль',
                'Март',
                'Апрель',
                'Май',
                'Июнь',
                'Июль',
                'Август',
                'Сентябрь',
                'Октябрь',
                'Ноябрь',
                'Декабрь'
                ];
            echo "<h3 id='selected-month' align='center'>".($arr[date('n') - 1])." - ".date('Y')."</h3>";
            echo "<table id='calendartable' class='tickettable calendartable'>";
            echo DBFunctions::loadIssuedTickets($connect->getLdapConn(), $connect->getGedeminConn(), $sel_mgr_sap_id, date('Y-m-d'));
            echo "</table>";
            echo "<div id='square' style='background: red'></div> - ОШИБОЧНО ВЫДАН ТАЛОН (сотрудник отсутствовал в день выдачи)<br>";
            echo "<div style='height: 1px;'></div>";
            echo "<div id='square' style='background: orange'></div> - ОТПУСК<br>";
            echo "<div style='height: 1px;'></div>";
            echo "<div id='square' style='background: rgb(178, 224, 182)'></div> - ТАЛОН ВЫДАН<br>";
            echo "<div style='height: 1px;'></div>";
            echo "<div id='square' style='background: white'></div> - ТАЛОН НЕ ВЫДАН";
            echo '</div>';

            echo '
                <script>
                    jQuery(function() {
                        jQuery("#date").mask("9999-99-99", {placeholder: "гггг-мм-дд" });
                    });

                    xCal.set({
                        order: 1, // Обратный порядок
                        delim: \'-\' // Разделитель между числами тире
                    });

                    function updateTicket(el) {
                        let tdCheck = jQuery(el).closest(\'td\');
                        let checked = jQuery(el).closest(\'input\').is(\':checked\');
                        let tabnum = tdCheck.prev().text();
                        let date = jQuery(\'#date\').val();
                        let ticket = jQuery(el).is(\':checked\') ? 1 : 0;
                        if(isNaN((new Date(date)).getTime())) {
                            alert("Внимание! Введите правильную дату!");
                        } else {
                            jQuery.ajax({
                                type: "POST",
                                url: "'.$_SERVER['HTTP_X_FORWARDED_PROTO'].'://'.$_SERVER['HTTP_HOST'].'/sites/all/modules/bw-tickets/ajax/updateticket.php",
                                data:{
                                    tabnum : tabnum,
                                    date : date,
                                    ticket : ticket,
                                    user : "'.$encrypted_name.'"
                                },
                                dataType: "json",
                                success: function(data) {
                                    if (data.reply == "TRUE") {
                                        let td = jQuery(\'#\' + tabnum).children(\'td\');
                                        let cell = jQuery(td[(new Date(date)).getDate()]);
                                        let total = jQuery(td[td.length - 3]);
                                        let remain = jQuery(td[td.length - 1]);
                                        if (checked){
                                            total.text(parseInt(total.text()) + 1);
                                            remain.text(parseInt(remain.text()) + 1);
                                        } else {
                                            total.text(parseInt(total.text()) - 1);
                                            remain.text(parseInt(remain.text()) - 1);
                                        }
                                        if (cell.css("background-color") == "rgb(178, 224, 182)") {
                                            cell.removeAttr("style");
                                        } else if (cell.css("background-color") == "rgb(255, 0, 0)") {
                                            tdCheck.css("background-color", "orange");                                            
                                            cell.css("background-color", "rgb(255, 165, 0)");
                                        } else if (cell.css("background-color") == "rgb(255, 165, 0)") {
                                            tdCheck.css("background-color", "red");
                                            cell.css("background-color", "rgb(255, 0, 0)");
                                        } else {
                                            cell.css("background-color", "rgb(178, 224, 182)");
                                        }
                                    } else {
                                        alert("Внимание! Некорректный ответ сервера!");
                                    }
                                },
                                error: function() {
                                    alert("Внимание! Ошибка сохранения данных!");
                                }
                            });
                        }
                    }

                    function loadTickets(date) {
                        let loadmonth = 0;
                        let months = [
                            "Январь",
                            "Февраль",
                            "Март",
                            "Апрель",
                            "Май",
                            "Июнь",
                            "Июль",
                            "Август",
                            "Сентябрь",
                            "Октябрь",
                            "Ноябрь",
                            "Декабрь"
                          ];
                        if(isNaN((new Date(date)).getTime())) {
                            alert("Внимание! Введите правильную дату!");
                        } else {
                            let cur_month = jQuery(\'#selected-month\').text();
                            let sel_month = months[(new Date(date)).getMonth()] + \' - \' + (new Date(date)).getFullYear();
                            if (cur_month != sel_month) {
                                loadmonth = 1;

                            }
                            jQuery.ajax({
                                type: "POST",
                                url: "'.$_SERVER['HTTP_X_FORWARDED_PROTO'].'://'.$_SERVER['HTTP_HOST'].'/sites/all/modules/bw-tickets/ajax/loadtickets.php",
                                beforeSend: function() {
                                    jQuery(\'#tickettable\').html("<div class=\'loadingindicator\'>ЗАГРУЗКА</div>");
                                    if (loadmonth) {
                                        jQuery(\'#calendartable\').html("<div class=\'loadingindicator\'>ЗАГРУЗКА</div>");
                                    }
                                },      
                                data:{
                                    manager_id: "'.$sel_mgr_sap_id.'",
                                    date : date,
                                    loadmonth : loadmonth,
                                    user : "'.$encrypted_name.'"
                                },
                                success: function(data) {
                                    let jdata = JSON.parse(data);
                                    jQuery(\'#tickettable\').html(jdata.day);
                                    if(jdata.month) {
                                        jQuery(\'#calendartable\').html(jdata.month);
                                        jQuery(\'#selected-month\').text(sel_month);
                                    }
                                },
                                error: function() {
                                    alert("Внимание! Ошибка сохранения данных!");
                                }
                            });
                        }
                    }

                    jQuery(document).ready(function() {
                        let sel = jQuery(".js-select2");
                        let ph;
                        sel.each(function() {
                            if (jQuery(this).attr("name") == "assigned_manager") {
                                ph = "Выберите ответственного";
                            } else {
                                ph = "Выберите подразделение";
                            }
                            jQuery(this).select2({
                                placeholder: ph,
                                maximumSelectionLength: 2,
                                language: "ru",
                                sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),
                                width: "100%"
                            });
                        });
                    });
                </script>
            ';
            ibase_close($connect->getGedeminConn());
        }
    }
}
?>

