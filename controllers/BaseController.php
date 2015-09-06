<?php
/*
    Copyright (C) 2015 Part-DB Authors (see authors.php)
    https://github.com/sandboxgangster/Part-DB

    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA

    $Id$

    Changelog (sorted by date):
        [DATE]      [NICKNAME]     [CHANGES]
        2015-09-06  susnux         - created
*/

// Autoload classes from lib/
spl_autoload_register(function($class){
    if (file_exists('lib/class.' . $class . '.php'))
    {
        include "lib/class.$class.php";
        return true;
    }
    return false;
});

/** @file  BaseController.php
 *  @brief Class BaseController
 *
 *  @class BaseController
 *  @brief Base class for all controllers. Used for request handling, common methods and attributes.
 *  @note If you want to extend controllers with a common feature, do it here.
 *
 * @author susnux
 */
abstract class BaseController
{
    /** @brief Database object of this class
     *  @sa Database
     */
    protected $database;
    /** @brief Log object of this class.
     *  @sa Log
     */
    protected $log;
    /** @brief Current logged in user.
      * @todo  Handle userser correctly, after we support users and groups.
      * @sa User
      */
    protected $current_user;

    /** @brief Constructor
      *        Sets database, log and current_user
      * @throws Exception If database, log or user initialization failed.
      */
    protected function __construct()
    {
        $this->database           = new Database();
        $this->log                = new Log($this->database);
        $this->current_user       = new User($this->database, $this->current_user, $this->log, 1); // admin
    }
}
?>