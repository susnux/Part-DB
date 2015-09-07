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
    /** @brief Supported HTTP methods by this controller. Needed by 405 failure ('Allow' header).
     *  @note When extending this class, simply add your accepted methods.
     */
    protected $supported_methods;

    /** @brief Constructor
      *        Sets database, log and current_user
      * @throws Exception If database, log or user initialization failed.
      */
    protected function __construct()
    {
        $this->database           = new Database();
        $this->log                = new Log($this->database);
        $this->current_user       = new User($this->database, $this->current_user, $this->log, 1); // admin
        $this->supported_methods  = array();
    }

    /** @brief Return supported HTTP methods by this controller */
    public function get_supported_methods()
    {
        return $this->supported_methods;
    }
    /* Stub implementation for a method would be:
     * (Returning 405 for wrong method ;-)  )
     * public function get_action()
     * {
     *     return array('status' => 405);
     * }
     */
}
?>