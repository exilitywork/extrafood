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

$result = true;
if (isset($_POST['remains']) && isset($_POST['user'])) {
    $remains = json_decode($_POST['remains']);
    $user = openssl_decrypt($_POST['user'], $cfg['method'], $cfg['key'].date('Ymd'), 0 , date('YmdYmd'));
} else {
    Logger::error($_SERVER['HTTP_X_REAL_IP']." | ajax/updateremains.php - Недостаточно данных POST");
    print '{"reply": "Внимание! Недостаточно данных!"}';
    die();
}

foreach ($remains as $tabnum) {
    if (strlen($tabnum) != 8) {
        Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user." | ajax/updateremains.php - Некорректный табельный номер ".$tabnum." в POST");
        print '{"reply": "Внимание! Некорректный табельный номер: '.$tabnum.'! Обратитесь в ОИТ!"}';
        die();
    }
}

foreach ($remains as $tabnum) {
    if ($result) $result = DBFunctions::updateRemains($tabnum, $user);
}

if ($result) {
    Logger::info($_SERVER['HTTP_X_REAL_IP']." | ".$user." | ajax/updateremains.php - Талоны с предыдущего месяца перенесены");
    print '{"reply": "TRUE"}';
} else {
    Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user." | ajax/updateremains.php - Ошибка подключения к базе данных");
    print '{"reply": "Внимание! Ошибка подключения к базе данных! Обратитесь в ОИТ"}';
}
?>