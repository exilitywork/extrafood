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
 
class Connections {

    private $cfg = [];

    function  __construct() {
        try {
            if ($this->cfg = parse_ini_file(__DIR__ . '/../tickets.ini')) {

                // подключение к LDAP
                $this->cfg['ldap_conn'] = ldap_connect($this->cfg['ldap_address']);
                if ($this->cfg['ldap_conn']) {
                    ldap_set_option($this->cfg['ldap_conn'], LDAP_OPT_PROTOCOL_VERSION, 3);
                    $this->cfg['ldap_bind'] = ldap_bind($this->cfg['ldap_conn'], $this->cfg['ldap_user'], $this->cfg['ldap_pass']);
                }
                if(ldap_errno($this->cfg['ldap_conn'])) {
                    Logger::error($_SERVER['HTTP_X_REAL_IP']." - Ошибка при подключении к LDAP: ".ldap_err2str(ldap_errno($this->cfg['ldap_conn'])));
                    print_r("Обратитесь в ОИТ! Ошибка при подключении к LDAP ".ldap_err2str(ldap_errno($this->cfg['ldap_conn'])));
                    //drupal_set_message(t('Обратитесь в ОИТ! Ошибка при подключении к LDAP ' . ldap_err2str(ldap_errno($this->cfg['ldap_conn']))), 'error');
                }

                // подключение к базе Гедымин
                $this->cfg['gedemin_conn'] = ibase_connect($this->cfg['gedemin_server'].':'.$this->cfg['gedemin_name'], $this->cfg['gedemin_user'], $this->cfg['gedemin_pass']);
                //print_r($this->cfg);
                if (!($this->cfg['gedemin_conn'])) {
                    print_r('Обратитесь в ОИТ! Ошибка при подключении к базе данных Гедымин ' . ibase_errmsg());
                    drupal_set_message(t('Обратитесь в ОИТ! Ошибка при подключении к базе данных Гедымин ' . ibase_errmsg()), 'error');
                } else {
                    $sql = "
                        EXECUTE BLOCK AS BEGIN
                        IF (NOT EXISTS(SELECT 1 FROM RDB\$RELATIONS WHERE RDB\$RELATION_NAME = 'BW_TICKETS')) THEN
                            BEGIN
                                EXECUTE STATEMENT 'CREATE TABLE BW_TICKETS
                                    (ID             INTEGER         NOT NULL,
                                    ASSIGN_DATE     DATE            NOT NULL,
                                    TABNUM          VARCHAR(10)     NOT NULL,
                                    CONTACTKEY      INTEGER         NOT NULL,
                                    TICKET          SMALLINT        NOT NULL,
                                    PRIMARY KEY (ID));';
                                EXECUTE STATEMENT 'CREATE SEQUENCE BW_TICKETS_ID_SEQUENCE;';
                                EXECUTE STATEMENT 'CREATE TRIGGER BW_TICKETS_AUTOINCREMENT FOR BW_TICKETS
                                    ACTIVE BEFORE INSERT POSITION 0
                                    AS
                                    BEGIN
                                        NEW.ID = NEXT VALUE FOR BW_TICKETS_ID_SEQUENCE;
                                    END;';
                            END
                        END
                    ";                   
                    ibase_query($this->cfg['gedemin_conn'], $sql);
                    $sql = "
                        EXECUTE BLOCK AS BEGIN
                        IF (NOT EXISTS(SELECT 1 FROM RDB\$RELATIONS WHERE RDB\$RELATION_NAME = 'BW_TICKET_MANAGERS')) THEN
                            BEGIN
                                EXECUTE STATEMENT 'CREATE TABLE BW_TICKET_MANAGERS
                                    (ID                 INTEGER         NOT NULL,
                                    DEPARTMENT_MANAGER  VARCHAR(300)    NOT NULL,
                                    ASSIGNED_MANAGER    VARCHAR(100)    NOT NULL,
                                    PRIMARY KEY (ID));';
                                EXECUTE STATEMENT 'CREATE SEQUENCE BW_TICKET_MANAGERS_ID_SEQUENCE;';
                                EXECUTE STATEMENT 'CREATE TRIGGER BW_TICKET_MANAGERS_A_I FOR BW_TICKET_MANAGERS
                                    ACTIVE BEFORE INSERT POSITION 0
                                    AS
                                    BEGIN
                                        NEW.ID = NEXT VALUE FOR BW_TICKET_MANAGERS_ID_SEQUENCE;
                                    END;';
                            END
                        END
                    ";
                    ibase_query($this->cfg['gedemin_conn'], $sql);
                    //ibase_free_result($res);
                }
            }         
        } catch (Exception $e) {
            Logger::error($_SERVER['HTTP_X_REAL_IP']." - Ошибка при создании подключений к LDAP / GEDEMIN: ".$e->getMessage());
            print_r('Поймано исключение: '.$e->getMessage());
            //drupal_set_message(t('Поймано исключение: '.$e->getMessage()), 'error');
        }
    }

    /*function getLdapAddress() {
        return $this->ldap_address;
    }

    function getLdapUser() {
        return $this->ldap_user;
    }

    function getLdapPass() {
        return $this->ldap_pass;
    }*/

    function getLdapConn() {
        return $this->cfg['ldap_conn'];
    }

    /*function getDbAddress() {
        return $this->db_address;
    }

    function getDbName() {
        return $this->db_name;
    }

    function getDbUser() {
        return $this->db_user;
    }

    function getDbPass() {
        return $this->db_pass;
    }*/

    /*function getDbConn() {
        return $this->cfg['db_conn'];
    }*/

    /*function getGedeminAddress() {
        return $this->gedemin_address;
    }

    function getGedeminName() {
        return $this->gedemin_name;
    }

    function getGedeminUser() {
        return $this->gedemin_user;
    }

    function getGedeminPass() {
        return $this->gedemin_pass;
    }*/

    function getGedeminConn() {
        return $this->cfg['gedemin_conn'];
    }

    function checkConnection() {
        return $this->cfg['ldap_bind'] && $this->cfg['gedemin_conn'];
    }
}