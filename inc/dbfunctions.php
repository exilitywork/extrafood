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
 
class DBFunctions {

    static $searchbase = "OU=СООО Белвест,DC=belwest,DC=corp";

    static function updateTicket($tabnum, $date, $ticket, $userlogin) {
        $connect = new Connections();
        if ($connect->checkConnection()) {
            $gedemin_conn = $connect->getGedeminConn();
            $d = explode('-', $date);
            $year = $d[0];
            $month = $d[1];
            $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            $date_obj = new DateTime($date);
            $date_obj->modify('-1 month');

            try {
                $sql = '
                    EXECUTE BLOCK 
                    AS 
                    DECLARE IDUSERCARD INTEGER = 0;
                    DECLARE IDUSEREXTRA INTEGER = 0;
                    DECLARE TICKETS INTEGER = 0;
                    DECLARE REMAINS INTEGER = 0; 
                    BEGIN 
                        SELECT USR$CONTACTKEY 
                            FROM 
                                USR$MN_STAFFCARD 
                            WHERE 
                                USR$NUMBER = \''.$tabnum.'\'
                            INTO 
                                :IDUSERCARD;
                        SELECT USR$CONTACTKEY 
                            FROM 
                                USR$MN_EXTRAFOOD 
                            WHERE 
                                USR$CONTACTKEY = :IDUSERCARD
                            INTO 
                                :IDUSEREXTRA;
                        UPDATE OR INSERT INTO  BW_TICKETS (ASSIGN_DATE, TABNUM, CONTACTKEY, TICKET)
                            VALUES(\''.$date.'\', \''.$tabnum.'\', :IDUSERCARD, '.$ticket.')
                            MATCHING(CONTACTKEY, ASSIGN_DATE);
                    ';
                    if ($month == date('m')) {
                        $sql .= '
                        SELECT COUNT(*) 
                            FROM 
                                BW_TICKETS 
                            WHERE 
                                CONTACTKEY = :IDUSERCARD 
                                AND TICKET = \'1\'
                                AND ASSIGN_DATE BETWEEN \'01.'.$month.'.'.$year.'\' AND \''.$last_day.'.'.$month.'.'.$year.'\'
                            INTO 
                                :TICKETS;
                        SELECT COUNT(*) 
                            FROM 
                                BW_TICKET_REMAINS 
                            WHERE 
                                TABNUM = \''.$tabnum.'\'
                                AND REM_YEAR = \''.$date_obj->format('Y').'\'
                                AND REM_MONTH = \''.$date_obj->format('n').'\'
                                AND IS_USED = 1
                            INTO 
                                :REMAINS;
                        IF (:IDUSEREXTRA = 0) THEN
                            INSERT INTO USR$MN_EXTRAFOOD (USR$CONTACTKEY, EDITIONDATE, USR$ISSUPPOSED, USR$SUPPOSED_QUANTITY, USR$ISDAYLIMIT) 
                                VALUES (:IDUSERCARD, \''.date('Y-m-d H:i:s').'\', 1, :TICKETS + :REMAINS, 0);
                        ELSE
                            UPDATE USR$MN_EXTRAFOOD
                                SET
                                    EDITIONDATE = \''.date('Y-m-d H:i:s').'\',
                                    USR$ISSUPPOSED = 1,
                                    USR$SUPPOSED_QUANTITY = :TICKETS + :REMAINS,
                                    USR$ISDAYLIMIT = 0
                                WHERE
                                    USR$CONTACTKEY = :IDUSERCARD;
                        ';
                    }
                    $sql .= ' END';
                ibase_query($gedemin_conn, $sql);
                if (ibase_errmsg()) {
                    throw new Exception(ibase_errmsg());
                }
                return true;
                //ibase_affected_rows($gedemin_conn);
            } catch (Exception $e) {
                Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$userlogin." - Ошибка в функции DBFunction::updateTicket(): ".$e->getMessage());
            }
        } else {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$userlogin." - Нет соединения с LDAP / GEDEMIN");
        }

        return false;
    }

    /**
     * Получить состояние выдачи талона за определенную дату для определенного сотрудника
     * 
     * @param           $gedemin_conn
     * @param String    $tabnum
     * @param String    $date
     * @param String    $loginuser
     * 
     * @return String
     */
    static function getTicket($gedemin_conn, $tabnum, $date, $loginuser) {
        try {
            $sql = "SELECT TICKET FROM BW_TICKETS WHERE TABNUM = '".$tabnum."' AND ASSIGN_DATE = '".$date."'";
            $res_sel = ibase_query($gedemin_conn, $sql);
            while ($row = ibase_fetch_assoc($res_sel)) {
                return $row['TICKET'];
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$loginuser." - Ошибка в функции DBFunction::getTicket(): ".$e->getMessage());
        }
        return 0;
    }

    /**
     * Получить состояние выдачи талонов за определенный период времени для определенного сотрудника
     * 
     * @param           $gedemin_conn
     * @param String    $tabnum
     * @param int       $year
     * @param int       $month
     * @param int       $days
     * 
     * @return String
     */
    static function getAllTickets($gedemin_conn, $tabnum, $year, $month, $days) {
        $begin_date = $year.'-'.$month.'-01';
        $end_date = $year.'-'.$month.'-'.$days;
        try {
            $sql = "SELECT TICKET, ASSIGN_DATE FROM BW_TICKETS WHERE TABNUM = '".$tabnum."' AND ASSIGN_DATE BETWEEN '".$begin_date."' AND '".$end_date."'";
            $res_sel = ibase_query($gedemin_conn, $sql);
            $tickets['total'] = 0;
            while ($row = ibase_fetch_assoc($res_sel)) {
                $day = intval(mb_substr($row['ASSIGN_DATE'], 8));
                $tickets[$day] = $row['TICKET'];
                if ($row['TICKET'] == 1) {
                    $tickets['total'] ++;
                }
            }
            if (isset($tickets)) {
                return $tickets;
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getAllTickets(): ".$e->getMessage());
            return 0;
        }
        return null;
    }

    /**
     * Формирование таблицы со списком работников, выданных талонов, состояния карты сотрудника на определенную дату
     * 
     * @param           $ldap_conn
     * @param           $gedemin_conn
     * @param String    $manager_sap_id
     * @param String    $date
     * 
     * @return String
     */
    static function loadTickets($ldap_conn, $gedemin_conn, $manager_sap_id, $date, $loginuser) {
        $res = [];
        $out = "
            <tbody>
            <tr>
                <th>ФИО</th>
                <th>Табельный</th>
                <th>Талон</th>
                <th>Статус карты</th>
            <tr>
        ";
        
        try {
            $filter = "(&(employeeType>=0)(localeid=".$manager_sap_id."))";
            $attrs = array("manager", "employeeid", "objectclass", "department", "title", "cn", "postalcode", "sn", "givenname", "initials");
            $search = ldap_search($ldap_conn, self::$searchbase, $filter, $attrs);
            $info = ldap_get_entries($ldap_conn, $search);
            for ($i = 0; $i < $info['count']; $i++) {
                if (in_array('user', $info[$i]['objectclass'])) {
                    $index = isset($res[$info[$i]['manager'][0]]) ? count($res[$info[$i]['manager'][0]]) : 0;
                    $res[$info[$i]['manager'][0]][$index]['name'] = $info[$i]["sn"][0].' '.substr($info[$i]["givenname"][0], 0, 2).'.'.$info[$i]["initials"][0].'.';
                    $res[$info[$i]['manager'][0]][$index]['tabnum'] = $info[$i]['employeeid'][0];
                    $vacation = isset($info[$i]['postalcode'][0]) ? explode("-", $info[$i]['postalcode'][0]) : false;
                    if ($vacation) {
                        $res[$info[$i]['manager'][0]][$index]['vacation_begin'] = $vacation[0];
                        $res[$info[$i]['manager'][0]][$index]['vacation_end'] = $vacation[1];
                    }
                }
            }

            if (date_format(date_create($date), 'm') == date('m') && date_format(date_create($date), 'd') <= date('d') || mb_strtolower($loginuser) == "kapeshkooa") {
                $disabled = '';
            } else {
                $disabled = 'disabled';
            }

            foreach ($res as $manager => $employees) {
                array_push($employees, self::getUserBySapId($ldap_conn, $manager_sap_id));
                $sorted = self::array_orderby($employees, 'name', SORT_ASC, 'tabnum', SORT_ASC);
                $employees = $sorted;
                $prev_tabnum = 0;
                foreach ($employees as $num => $emp) {
                    if ($prev_tabnum != $emp['tabnum']) {
                        $checked = self::getTicket($gedemin_conn, $emp['tabnum'], $date, $loginuser) ? 'checked' : '';
                        if (self::getGedeminUserId($gedemin_conn, $emp['tabnum'])) {
                            $check = "<td><input type='checkbox' onchange='updateTicket(this)' $checked $disabled></td>";
                            $card = "<td><span style='color: green'>АКТИВНА</td>";
                            if (isset($emp['vacation_begin']) 
                                    && strtotime($emp['vacation_begin']) <= strtotime($date)
                                    && strtotime($emp['vacation_end']) >= strtotime($date)) {
                                if ($checked) {
                                    $check = "<td style='background-color: red'><input type='checkbox' onchange='updateTicket(this)' $checked $disabled></td>";
                                } else {
                                    $check = "<td style='background-color: orange'><input type='checkbox' onchange='updateTicket(this)' $checked $disabled></td>";
                                }
                            }
                        } else {
                            $check = "<td></td>";
                            $card = "<td><span style='color: red'>НЕАКТИВНА</td>";
                        }
                        $out .= "
                            <tr>
                                <td>".$emp['name']."</td>
                                <td>".$emp['tabnum']."</td>";
                        $out .= "
                            $check
                            $card
                        </tr>";
                        $prev_tabnum = $emp['tabnum'];
                    }
                }
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$loginuser." - Ошибка в функции DBFunction::loadTickets(): ".$e->getMessage());
        }
        $out .= "</tbody>";
        return $out;
    }

    /**
     * Формирование таблицы со списком работников и статистики выдачи талонов за определенный месяц
     * 
     * @param           $ldap_conn
     * @param           $gedemin_conn
     * @param String    $manager_sap_id
     * @param String    $date
     * 
     * @return String
     */
    static function loadIssuedTickets($ldap_conn, $gedemin_conn, $manager_sap_id, $date, $loginuser) {
        //global $user;
        $res = [];
        $out = "";
        try {
            $filter="(&(employeeType>=0)(localeid=".$manager_sap_id."))";
            $attrs = array("manager", "employeeid", "objectclass", "department", "title", "cn", "sn", "givenName", "initials", "postalcode");
            $search=ldap_search($ldap_conn, "OU=СООО Белвест,DC=belwest,DC=corp", $filter, $attrs);
            $info = ldap_get_entries($ldap_conn, $search);
            for ($i=0; $i<$info["count"]; $i++) {
                if (in_array('user', $info[$i]["objectclass"])) {
                    $index = isset($res[$info[$i]["manager"][0]]) ? count($res[$info[$i]["manager"][0]]) : 0;
                    $res[$info[$i]["manager"][0]][$index]['name'] = $info[$i]["sn"][0].' '.substr($info[$i]["givenname"][0], 0, 2).'.'.$info[$i]["initials"][0].'.';
                    $res[$info[$i]["manager"][0]][$index]['tabnum'] = $info[$i]['employeeid'][0];
                    $vacation = isset($info[$i]['postalcode'][0]) ? explode("-", $info[$i]['postalcode'][0]) : false;
                    if ($vacation) {
                        $res[$info[$i]['manager'][0]][$index]['vacation_begin'] = $vacation[0];
                        $res[$info[$i]['manager'][0]][$index]['vacation_end'] = $vacation[1];
                    }
                }
            }
                
            $d = explode('-', $date);
            $year = $d[0];
            $month = $d[1];
            $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $out .= "
                <thead>
                <tr>
                    <th>ФИО</th>
                    <th class='specific-cell'>Ост.</th>";
            for ($i = 1; $i <= $days; $i++) {
                $out .='<th>'.$i.'</th>';
            }
            $out .="
                    <th class='specific-cell'>Выд.</th>
                    <th class='specific-cell'>Исп.</th>
                    <th class='specific-cell'>Есть</th>
                </tr></thead><tbody>
            ";
            foreach ($res as $mananger => $employees) {
                array_push($employees, self::getUserBySapId($ldap_conn, $manager_sap_id));
                $sorted = self::array_orderby($employees, 'name', SORT_ASC, 'tabnum', SORT_ASC);
                $employees = $sorted;
                $prev_tabnum = 0;
                foreach ($employees as $num => $emp) {
                    if ($prev_tabnum != $emp['tabnum']) {
                        $tickets = self::getAllTickets($gedemin_conn, $emp['tabnum'], $year, $month, $days);
                        $out .= "
                            <tr id='".$emp['tabnum']."'>
                                <td>".$emp['name']."</td>";
                        switch (self::checkRemains($gedemin_conn, $emp['tabnum'], $date, $loginuser)) {
                            case -1: 
                                $out .="<td>0<sup>+1</sup></td>";
                                break;
                            case 1: 
                                $out .="<td>1</td>";
                                $tickets['total'] += 1;
                                break;
                            default: $out .="<td>0</td>";
                        }
                        for ($i = 1; $i <= $days; $i++) {
                            if (isset($emp['vacation_begin']) 
                                    && strtotime($emp['vacation_begin']) <= strtotime($i.'.'.$month.'.'.$year)
                                    && strtotime($emp['vacation_end']) >= strtotime($i.'.'.$month.'.'.$year)) {
                                $out .= "<td style='background: ".(isset($tickets[$i]) && $tickets[$i] ? "red" : "orange")."'></td>";
                            } elseif (isset($tickets[$i])) {  
                                //$out .= "<td>".($tickets[$i] ? '&#10004;' : '-')."</td>";
                                $out .= "<td style='background: ".($tickets[$i] ? 'rgb(178, 224, 182)' : '')."'></td>";
                            } else {
                                //$out .='<td>-</td>';
                                $out .="<td></td>";
                            }
                        }
                        $stats = self::getGedeminTicketStats($gedemin_conn, $emp['tabnum'], $year, $month, $days);
                        if ($year != date('Y') || $month != date('m')) {
                            $stats['remain'] = $tickets['total'] - $stats['spent'];
                        }
                        $out .= "
                                <td>".$tickets['total']."</td>
                                <td>".$stats['spent']."</td>
                                <td>".$stats['remain']."</td>
                            </tr>
                        ";
                        $prev_tabnum = $emp['tabnum'];
                    }
                }
            }
            $out .= "
                <tfoot><tr>
                    <th>ФИО</th>
                    <th class='specific-cell'>Ост.</th>";
            for ($i = 1; $i <= $days; $i++) {
                $out .='<th>'.$i.'</th>';
            }
            $out .="
                    <th class='specific-cell'>Выд.</th>
                    <th class='specific-cell'>Исп.</th>
                    <th class='specific-cell'>Есть</th>
                </tr></tfoot>
            ";
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$loginuser." - Ошибка в функции DBFunction::loadIssuedTickets(): ".$e->getMessage());
        }
        return $out;
    }

    /**
     * Получить информацию о руководителе из LDAP для пользователя
     * 
     * @param $ldap_conn
     * @param String    $employeenumber
     * 
     * @return array
     */
    static function getManager($ldap_conn, $employeenumber) {
        global $user;
        try {
            $filter="(&(employeeType>=0)(employeenumber=$employeenumber))";
            $attrs = array(
                'manager', 
                'employeeid', 
                'objectclass', 
                'department', 
                'title', 
                'sn', 
                'samaccountname', 
                'departmentnumber', 
                'employeenumber', 
                'localeid', 
                'givenname', 
                'initials'
            );
            $manager = [];
            $search = ldap_search($ldap_conn, self::$searchbase, $filter, $attrs);
            $info = ldap_get_entries($ldap_conn, $search);
            if ($info['count'] > 0) {
                $manager['name']                = $info[0]['sn'][0].' '.substr($info[0]['givenname'][0], 0, 2).'.'.$info[0]['initials'][0].'.';
                $manager['department']          = $info[0]['department'][0];
                $manager['title']               = $info[0]['title'][0];
                $manager['dn']                  = $info[0]['dn'];
                $manager['manager']             = mb_substr(stristr($info[0]['manager'][0], ',', true), 3);
                $manager['employeeid']          = $info[0]['employeeid'][0];
                $manager['departmentnumber']    = $info[0]['departmentnumber'][0];
                $manager['employeenumber']      = $info[0]['employeenumber'][0];
                $manager['localeid']            = $info[0]['localeid'][0];
                return $manager;
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getManager(): ".$e->getMessage());
        }
        return false;
    }

    /**
     * Функция сортировки массива
     * 
     * @return array
     */
    static function array_orderby()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                foreach ($data as $key => $row)
                    $tmp[$key] = $row[$field];
                    $args[$n] = $tmp;
                }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }

    /**
     * Получить назначенных руководителей для определенного подразделения
     * 
     * @param $gedemin_conn
     * @param String    $dep_mgr_sap_id
     * 
     * @return array
     */
    static function getAssigned($gedemin_conn, $dep_mgr_sap_id) {
        global $user;
        try {
            $i = 0;
            $sql = 'SELECT * FROM BW_TICKET_MANAGERS WHERE DEPARTMENT_MANAGER = \''.$dep_mgr_sap_id.'\'';
            $res = ibase_query($gedemin_conn, $sql);
            while ($row = ibase_fetch_assoc($res)) {
                $assigned[$i] = $row['ASSIGNED_MANAGER'];
                $i++;
            }
            if (isset($assigned)) {
                return $assigned;
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getAssigned(): ".$e->getMessage());
        }
        return false;
    }

    /**
     * Назначить ответственного руководителя для подразделения
     * 
     * @param $gedemin_conn
     * @param String    $assigned_manager
     * @param String    $selected_manager
     */
    static function addAssigned($gedemin_conn, $assigned_manager, $selected_manager) {
        global $user;
        try {
            $sql = "INSERT INTO BW_TICKET_MANAGERS (ASSIGNED_MANAGER, DEPARTMENT_MANAGER) VALUES ('".$assigned_manager."', '".$selected_manager."')";
            $res = ibase_query($gedemin_conn, $sql);
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::addAssigned(): ".$e->getMessage());
        }
    }

    /**
     * Удалить ответственного руководителя для подразделения
     * 
     * @param $gedemin_conn
     * @param String    $assigned_manager
     * @param String    $department_manager
     */
    static function deleteAssigned($gedemin_conn, $assigned_manager, $department_manager) {
        global $user;
        try {
            $sql = "DELETE FROM BW_TICKET_MANAGERS WHERE ASSIGNED_MANAGER = '".$assigned_manager."' AND DEPARTMENT_MANAGER = '".$department_manager."'";
            $res = ibase_query($gedemin_conn, $sql);
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::deleteAssigned(): ".$e->getMessage());
        }
    }

    /**
     * Получить инфо пользователя из LDAP по его SAP ID
     * 
     * @param $ldap_conn
     * @param String    $sap_emp_id
     * 
     * @return array
     */
    static function getUserBySapId($ldap_conn, $sap_emp_id) {
        global $user;
        try {
            $filter = "(&(employeeType>=0)(objectCategory=person)(objectClass=user)(employeenumber=".$sap_emp_id."))";
            $attrs = array(
                'cn', 
                'department', 
                'employeenumber', 
                'manager', 
                'name', 
                'localeid', 
                'title', 
                'givenname', 
                'initials', 
                'sn', 
                'postalcode', 
                'employeeid'
            );
            $search = ldap_search($ldap_conn, DBFunctions::$searchbase, $filter, $attrs);
            $info = ldap_get_entries($ldap_conn, $search);
            if ($info['count'] > 0) {
                $employee['cn']             = $info[0]['cn'][0];
                $employee['dn']             = $info[0]['dn'];
                $employee['department']     = $info[0]['department'][0];
                $employee['employeenumber'] = $info[0]['employeenumber'][0];
                $employee['tabnum']         = $info[0]['employeeid'][0];
                $employee['manager']        = $info[0]['manager'][0];
                $employee['title']          = $info[0]['title'][0];
                $employee['localeid']       = $info[0]['localeid'][0];
                $employee['name']            = $info[0]['sn'][0].' '.substr($info[0]['givenname'][0], 0, 2).'.'.$info[0]['initials'][0].'.';
                $vacation = isset($employee['postalcode'][0]) ? explode("-", $employee['postalcode'][0]) : false;
                if ($vacation) {
                    $employee['vacation_begin'] = $vacation[0];
                    $employee['vacation_end'] = $vacation[1];
                }
                return $employee;
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getUserBySapId(): ".$e->getMessage());
        }
        return false;
    }

    /**
     * Получить рекурсивно информацию о нижестоящих руководителях и их группах
     * 
     * @param $ldap_conn
     * @param String    $dn
     * @param array     $dep_list
     * 
     * @return array
     */
    static function getGroups($ldap_conn, $dn, $dep_list) {
        global $user;
        try {
            $filter = "(&(employeeType>=0)(objectCategory=person)(objectClass=user)(manager=".$dn."))";
            $attrs = array('cn', 'department', 'departmentnumber', 'localeid', 'employeenumber', 'employeetype');
            $search = ldap_search($ldap_conn, DBFunctions::$searchbase, $filter, $attrs);
            $users = ldap_get_entries($ldap_conn, $search);
            if ($users['count'] == 1) {
                foreach ($users as $key => $employee) {
                    if ($employee['localeid'][0] == $employee['employeenumber'][0]) {
                        return $dep_list;
                    }
                }
            }
            if ($users['count'] > 0) {
                foreach ($users as $key => $employee) {

                    if ($employee['employeetype'][0] == "Офис" && isset($employee['localeid']) && $employee['localeid'][0] == $employee['employeenumber'][0]) {
                        array_push($dep_list, $employee['employeenumber'][0]);
                    }
                    if ($employee['employeetype'][0] == "Офис" && isset($employee['localeid']) && $employee['localeid'][0] != $employee['employeenumber'][0]) {
                        if (self::isManager($ldap_conn, $employee['dn'])) {
                            array_push($dep_list, $employee['employeenumber'][0]);
                            $dep_list = self::getGroups($ldap_conn, $employee['dn'], $dep_list);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getGroups(): ".$e->getMessage());
        }
        return $dep_list;
    }

    /**
     * Является ли сотрудник руководителем
     * 
     * @param $ldap_conn
     * @param String        $dn
     *
     * @return bool
     */
    static function isManager($ldap_conn, $dn) {
        global $user;
        try {
            $filter = "(&(employeeType>=0)(objectCategory=person)(objectClass=user)(manager=".$dn."))";
            $attrs = array('cn', 'department', 'departmentnumber', 'localeid', 'employeenumber', 'employeetype');
            $search = ldap_search($ldap_conn, DBFunctions::$searchbase, $filter, $attrs);
            $users = ldap_get_entries($ldap_conn, $search);
            if ($users['count'] == 1) {
                foreach ($users as $key => $employee) {
                    if ($employee['localeid'][0] != $employee['employeenumber'][0]) {
                        return true;
                    }
                }
            }
            if ($users['count'] > 1) {
                return true;
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::isManager(): ".$e->getMessage());
        }
        return false;
    }

    /**
     * Имеет ли сотрудник доступ к спецпитанию
     * 
     * @param $ldap_conn
     * @param String        $user
     *
     * @return bool
     */
    static function hasAccess($ldap_conn, $samaccountname) {
        $cur_user = [];
        try {
            $filter = "(&(memberOf:1.2.840.113556.1.4.1941:=CN=Спецпитание,OU=СООО Белвест,DC=belwest,DC=corp)(employeeType>=0)(objectCategory=person)(objectClass=user)(sAMAccountName=".$samaccountname."))";
            $attrs = array('cn', 'department', 'departmentnumber', 'employeeid', 'employeenumber', 'employeetype', 'localeid', 'name', 'title', 'sn', 'givenname', 'initials');
            $search = ldap_search($ldap_conn, DBFunctions::$searchbase, $filter, $attrs);
            $users = ldap_get_entries($ldap_conn, $search);
            if ($users['count'] > 0) {
                $cur_user['cn']                 = $users[0]['cn'][0];
                $cur_user['department']         = $users[0]['department'][0];
                $cur_user['dn']                 = $users[0]['dn'];
                $cur_user['employeeid']         = $users[0]['employeeid'][0];
                $cur_user['departmentnumber']   = $users[0]['departmentnumber'][0];
                $cur_user['employeenumber']     = $users[0]['employeenumber'][0];
                $cur_user['localeid']           = $users[0]['localeid'][0];
                $cur_user['title']              = $users[0]['title'][0];
                $cur_user['name']               = $users[0]['sn'][0].' '.substr($users[0]['givenname'][0], 0, 2).'.'.$users[0]['initials'][0].'.';
                return $cur_user;
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$samaccountname." - Ошибка в функции DBFunction::hasAccess(): ".$e->getMessage());
        }
        return false;
    }

    /**
     * Получить назначенные группы для определенного руководителя
     * 
     * @param $conn
     * @param String    $mgr_sap_id
     * @param array     $dep_list  
     *
     * @return array
     */
    static function getAssignedGroups($conn, $mgr_sap_id, $dep_list) {
        global $user;
        try {
            $sql = 'SELECT * FROM BW_TICKET_MANAGERS WHERE ASSIGNED_MANAGER = \''.$mgr_sap_id.'\'';
            $res = ibase_query($conn, $sql);
            while ($row = ibase_fetch_assoc($res)) {
                array_push($dep_list, $row['DEPARTMENT_MANAGER']);
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getAssignedGroups(): ".$e->getMessage());
        }
        return $dep_list;
    }

    /**
     * Получить id сотрудника в БД Gedemin по его табельному номеру
     * 
     * @param           $conn
     * @param String    $tabnum
     *
     * @return String
     */
    static function getGedeminUserId($conn, $tabnum) {
        global $user;
        try {
            $sql = '
                SELECT USR$CONTACTKEY AS ID
                FROM USR$MN_STAFFCARD 
                WHERE USR$NUMBER = \''.$tabnum.'\' 
                    AND USR$DISABLED = 0 
                    AND (USR$ENDDATE > \''.date('Y-m-d').'\' OR COALESCE(USR$ENDDATE, \'\') = \'\')
            ';
            $res = ibase_query($conn, $sql);
            while ($row = ibase_fetch_assoc($res)) {
                return $row['ID'];
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getGedeminUserId(): ".$e->getMessage());
        }
        return false;
    }

    /**
     * Получить количство потраченных талонов за определенный промежуток времени для определенного сотрудника
     * 
     * @param $conn
     * @param String    $tabnum
     * @param String    $date_begin
     * @param String    $date_end
     *
     * @return int
     */
    static function getGedeminTicketUse($conn, $tabnum, $date_begin, $date_end) {
        global $user;
        try {
            $specfood = 0;
            $sql = '
                SELECT 
                    CON.NAME AS STAFFNAME,
                    CON.ID AS STAFFKEY,
                    P.USR$TABNUM AS TABNUM,
                    G.NAME AS GOODNAME,
                    G.ID AS GOODKEY,
                    SUM(COALESCE(OL.USR$QUANT_SPECFOOD, 0)) AS SPECFOOD
                FROM 
                    USR$MN_ORDER O 
                    JOIN USR$MN_ORDERLINE OL ON OL.MASTERKEY = O.DOCUMENTKEY 
                    JOIN GD_GOOD G ON G.ID = OL.USR$GOODKEY
                    JOIN GD_CONTACT CON ON CON.ID = O.USR$STAFFKEY 
                    JOIN GD_PEOPLE P ON P.CONTACTKEY = CON.ID
                WHERE 
                    O.USR$LOGICDATE BETWEEN \''.$date_begin.'\' AND \''.$date_end.'\' 
                    AND O.USR$PAY = 1 
                    AND P.USR$TABNUM = \''.$tabnum.'\' 
                    AND COALESCE(O.USR$RETURN, 0) = 0 
                GROUP BY 1, 2, 3, 4, 5 
                HAVING SUM(OL.USR$QUANT_SPECFOOD) > 0 
                ORDER BY CON.ID 
            ';
            $res = ibase_query($conn, $sql);
            while ($row = ibase_fetch_assoc($res)) {
                $specfood += (int)$row['SPECFOOD'];
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getGedeminTicketUse(): ".$e->getMessage());
        }
        return $specfood;
    }

    /**
     * Получить текущее количество потраченных и оставшихся талонов определенного сотрудника
     * 
     * @param $conn
     * @param String    $gedemin_id
     *
     * @return array
     */
    static function getGedeminTicketStats($gedemin_conn, $tabnum, $year = false, $month = false, $days = false) {
        global $user;
        try {
            $stats['spent'] = 0;
            $stats['remain'] = 0;
            $gedemin_id = self::getGedeminUserId($gedemin_conn, $tabnum);
            if ($gedemin_id <> '') {
                $sql = '
                    SELECT 
                        COALESCE(USR$SPENT_QUANTITY, 0) AS SPENT,
                        COALESCE(USR$SUPPOSED_QUANTITY - USR$SPENT_QUANTITY, 0) AS REMAIN
                    FROM 
                        USR$MN_EXTRAFOOD
                    WHERE 
                        USR$CONTACTKEY = \''.$gedemin_id.'\'';
                $res = ibase_query($gedemin_conn, $sql);
                while ($row = ibase_fetch_assoc($res)) {
                    $stats['spent'] = (int)$row['SPENT'];
                    $stats['remain'] = (int)$row['REMAIN'];
                }
            }
            if ($year != date('Y') || $month != date('m')) {
                $stats['spent'] = (int)self::getGedeminTicketUse($gedemin_conn, $tabnum, "01.".$month.".".$year, $days.".".$month.".".$year);
            }
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$user->name." - Ошибка в функции DBFunction::getGedeminTicketStats(): ".$e->getMessage());
        }
        return $stats;
    }

    /**
     * Перенести неиспользованный талон последнего дня предыдущего месяца
     * 
     * @param String    $tabnum
     * @param String    $userlogin
     *
     * @return bool
     */
    static function updateRemains($tabnum, $userlogin) {
        $connect = new Connections();
        if ($connect->checkConnection()) {
            $gedemin_conn = $connect->getGedeminConn();
            $date = new DateTime();
            $date->modify('-1 month');
            $year = $date->format('Y');
            $month = $date->format('n');
            $last_day = cal_days_in_month(CAL_GREGORIAN, date('m'), date('Y'));
            try {
                $sql = '
                    EXECUTE BLOCK 
                    AS 
                    DECLARE IDUSERCARD INTEGER = 0;
                    DECLARE IDUSEREXTRA INTEGER = 0;
                    DECLARE TICKETS INTEGER = 0;
                    DECLARE REMAINS INTEGER = 0; 
                    BEGIN 
                        SELECT USR$CONTACTKEY 
                            FROM 
                                USR$MN_STAFFCARD 
                            WHERE 
                                USR$NUMBER = \''.$tabnum.'\'
                            INTO 
                                :IDUSERCARD;
                        SELECT USR$CONTACTKEY 
                            FROM 
                                USR$MN_EXTRAFOOD 
                            WHERE 
                                USR$CONTACTKEY = :IDUSERCARD
                            INTO 
                                :IDUSEREXTRA;
                        UPDATE BW_TICKET_REMAINS 
                            SET IS_USED = 1
                            WHERE REM_YEAR = \''.$year.'\'
                                AND REM_MONTH = \''.$month.'\'
                                AND TABNUM = \''.$tabnum.'\';
                        SELECT COUNT(*) 
                            FROM 
                                BW_TICKETS 
                            WHERE 
                                CONTACTKEY = :IDUSERCARD 
                                AND TICKET = \'1\'
                                AND ASSIGN_DATE BETWEEN \'01.'.date('m').'.'.date('Y').'\' AND \''.$last_day.'.'.date('m').'.'.date('Y').'\'
                            INTO 
                                :TICKETS;
                        SELECT COUNT(*) 
                            FROM 
                                BW_TICKET_REMAINS 
                            WHERE 
                                TABNUM = \''.$tabnum.'\'
                                AND REM_YEAR = \''.$year.'\'
                                AND REM_MONTH = \''.$month.'\'
                                AND IS_USED = 1
                            INTO 
                                :REMAINS;
                        IF (:IDUSEREXTRA = 0) THEN
                            INSERT INTO USR$MN_EXTRAFOOD (USR$CONTACTKEY, EDITIONDATE, USR$ISSUPPOSED, USR$SUPPOSED_QUANTITY, USR$ISDAYLIMIT) 
                                VALUES (:IDUSERCARD, \''.date('Y-m-d H:i:s').'\', 1, :TICKETS + :REMAINS, 0);
                        ELSE
                            UPDATE USR$MN_EXTRAFOOD
                                SET
                                    EDITIONDATE = \''.date('Y-m-d H:i:s').'\',
                                    USR$ISSUPPOSED = 1,
                                    USR$SUPPOSED_QUANTITY = :TICKETS + :REMAINS,
                                    USR$ISDAYLIMIT = 0
                                WHERE
                                    USR$CONTACTKEY = :IDUSERCARD;
                    END
                ';
                ibase_query($gedemin_conn, $sql);
                if (ibase_errmsg()) {
                    throw new Exception(ibase_errmsg());
                }
                return true;
                //ibase_affected_rows($gedemin_conn);
            } catch (Exception $e) {
                Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$userlogin." - Ошибка в функции DBFunction::updateRemains(): ".$e->getMessage());
            }
        } else {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$userlogin." - Нет соединения с LDAP / GEDEMIN");
        }

        return false;
    }

    /**
     * Проверить наличие неиспользованных талонов последнего дня предыдущего месяца
     * 
     * @param mixed     $gedemin_conn
     * @param String    $tabnum
     * @param mixed     $date
     * @param String    $userlogin
     *
     * @return bool
     */
    static function checkRemains($gedemin_conn, $tabnum, $date, $userlogin) {
        try {
            $date = new DateTime($date);
            $date->modify('-1 month');
            $year = $date->format('Y');
            $month = $date->format('n');
            $sql = '
                SELECT IS_USED 
                FROM 
                    BW_TICKET_REMAINS 
                WHERE 
                    REM_YEAR = \''.$year.'\' 
                    AND REM_MONTH = \''.$month.'\'
                    AND TABNUM = \''.$tabnum.'\'
                ';
            $res = ibase_query($gedemin_conn, $sql);
            if ($row = ibase_fetch_assoc($res)) {
                $ticket = (int)$row['IS_USED'] ? 1 : -1; // 1 - остаток перенесен / -1 - остаток не перенесен
            } else {
                $ticket = 0;
            }
            if (ibase_errmsg()) {
                throw new Exception(ibase_errmsg());
            }
            return $ticket;
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." | ".$userlogin." - Ошибка в функции DBFunction::updateRemains(): ".$e->getMessage());
        }
        return false;
    }
}