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

include ("../inc/dbfunctions.php");
include ("../inc/connections.php");
include ("../inc/logger.php");

$cfg = parse_ini_file('../tickets.ini');

Logger::init();

$ticket = 1;
if (isset($_POST['tabnum']) && isset($_POST['date']) && isset($_POST['ticket']) && isset($_POST['user'])) {
    $tab_num = $_POST['tabnum'];
    $date = $_POST['date'];
    $ticket = $_POST['ticket'];
    $user = openssl_decrypt($_POST['user'], $cfg['method'], $cfg['key'].date('Ymd'), 0 , date('YmdYmd'));
} else {
    Logger::error($_SERVER['HTTP_X_REAL_IP']." | ajax/updateticket.php - Недостаточно данных POST");
    print '{"reply": "Внимание! Недостаточно данных!"}';
    die();
}
$date_arr = explode('-', ($date));

if (!(checkdate($date_arr[1], $date_arr[2], $date_arr[0]))) {
    Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user." | ajax/updateticket.php - Некорректная дата ".$date." в POST");
    print '{"reply": "Внимание! Введите правильную дату!"}';
    die();
}

if (strlen($tab_num) != 8) {
    Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user." | ajax/updateticket.php - Некорректный табельный номер ".$tab_num." в POST");
    print '{"reply": "Внимание! Некорректный табельный номер: '.$tab_num.'! Обратитесь в ОИТ!"}';
    die();
}

$result = DBFunctions::updateTicket($tab_num, $date, $ticket, $user);
if ($result) {
    Logger::info($_SERVER['HTTP_X_REAL_IP']." | ".$user." | ajax/updateticket.php - Обновлен статус выдачи талона на дату для ".$date." по табельному ".$tab_num);
    print '{"reply": "TRUE"}';
} else {
    Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user." | ajax/updateticket.php - Ошибка подключения к базе данных");
    print '{"reply": "Внимание! Ошибка подключения к базе данных! Обратитесь в ОИТ"}';
}
?>