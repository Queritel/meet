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

use \EA\Engine\Types\NonEmptyText;

/**
 * API v1 Authorization Class
 *
 * This class will handle the authorization procedure of the API.
 */
class Authorization {
    /**
     * Framework Instance
     *
     * @var CI_Controller
     */
    protected $framework;

    /**
     * Class Constructor
     *
     * @param \CI_Controller $framework
     */
    public function __construct(\CI_Controller $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Perform Basic Authentication
     *
     * @param NonEmptyText $username Admin Username
     * @param NonEmptyText $password Admin Password
     *
     * @throws \EA\Engine\Api\V1\Exception Throws 401-Unauthorized exception if the authentication fails.
     */
    public function basic(NonEmptyText $username, NonEmptyText $password)
    {
        $this->framework->load->model('user_model');

        if ( ! $this->framework->user_model->check_login($username->get(), $password->get()))
        {
            throw new Exception(/* @mangle */ 'The provided credentials do not match any admin user!' /* @/mangle */, 401, /* @mangle */ 'Unauthorized' /* @/mangle */);
        }
    }
}
