<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * TekhneeAppointments - Self-hosted appointment-booking system for round-the-clock, timezone-aware, multilingual operations.
 *
 * @package     TekhneeAppointments
 * @author      A.Tselegidis
 * @author      Tekhnee
 * @copyright   Copyright (c) 2013 - 2019 Alex Tselegidis
 * @copyright   Copyright (c) 2019 - 2020 Tekhnee
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://github.com/tekhnee/appointments/
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * Check if a string date is valid for insertion or update
 * to the database.
 *
 * @param string $datetime The given date.
 * @return bool Returns the validation result.
 *
 * @link http://stackoverflow.com/a/8105844/1718162 [SOURCE]
 */
function validate_mysql_datetime($datetime)
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    return ($dt) ? TRUE : FALSE;
}
