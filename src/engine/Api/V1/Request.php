<?php

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

namespace EA\Engine\Api\V1;

/**
 * Request Class
 *
 * This class handles the common request handling before the data are manipulated and
 * returned back with the Response class.
 */
class Request {
    /**
     * Get request body as an associative array.
     *
     * @return array
     */
    public function getBody()
    {
        return json_decode(file_get_contents('php://input'), TRUE);
    }
}
