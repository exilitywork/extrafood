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

$connect = new Connections();

$user = "";
if (isset($_POST['user'])) {
    $user = openssl_decrypt($_POST['user'], $cfg['method'], $cfg['key'].date('Ymd'), 0 , date('YmdYmd'));
}
if (isset($_POST['manager_id']) && isset($_POST['date']) && $connect->checkConnection()) {
    $out['day'] = DBFunctions::loadTickets($connect->getLdapConn(), $connect->getGedeminConn(), $_POST['manager_id'], $_POST['date'], $user);
    if ($_POST['loadmonth'] == true) {
        $out['month'] = DBFunctions::loadIssuedTickets($connect->getLdapConn(), $connect->getGedeminConn(), $_POST['manager_id'], $_POST['date'], $user);
    }
    print json_encode($out);
} else {
    Logger::error($_SERVER['HTTP_X_REAL_IP']." | ajax/loadtickets.php | ".$user." - Нет соединения с LDAP или GEDEMIN / Нет параметров POST");
}