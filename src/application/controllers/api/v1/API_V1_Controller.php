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

use \EA\Engine\Types\NonEmptyText;

/**
 * API V1 Controller
 *
 * Parent controller class for the API v1 resources. Extend this class instead of the CI_Controller
 * and call the parent constructor.
 *
 * @package Controllers
 * @subpackage API
 */
class API_V1_Controller extends CI_Controller {
    /**
     * Class Constructor
     *
     * This constructor will handle the common operations of each API call.
     *
     * Important: Do not forget to call the this constructor from the child classes.
     *
     * Notice: At the time being only the basic authentication is supported. Make sure
     * that you use the API through SSL/TLS for security.
     */
    public function __construct()
    {
        if ( ! isset($_SERVER['PHP_AUTH_USER']))
        {
            $this->_requestAuthentication();
            return;
        }

        parent::__construct();

        try
        {
            $username = new NonEmptyText($_SERVER['PHP_AUTH_USER']);
            $password = new NonEmptyText($_SERVER['PHP_AUTH_PW']);
            $authorization = new \EA\Engine\Api\V1\Authorization($this);
            $authorization->basic($username, $password);
        }
        catch (\Exception $exception)
        {
            exit($this->_handleException($exception));
        }
    }

    /**
     * Sets request authentication headers.
     */
    protected function _requestAuthentication()
    {
        header(/* @mangle */ 'WWW-Authenticate: Basic realm="TekhneeAppointments"' /* @/mangle */);
        header(/* @mangle */ 'HTTP/1.0 401 Unauthorized' /* @/mangle */);
        exit(/* @mangle */ 'You are not authorized to use the API.' /* @/mangle */);
    }

    /**
     * Outputs the required headers and messages for exception handling.
     *
     * Call this method from catch blocks of child controller callbacks.
     *
     * @param \Exception $exception Thrown exception to be outputted.
     */
    protected function _handleException(\Exception $exception)
    {
        $error = [
            'code' => $exception->getCode() ?: 500,
            'message' => $exception->getMessage(),
        ];

        $header = $exception instanceof \EA\Engine\Api\V1\Exception
            ? $exception->getCode() . ' ' . $exception->getHeader()
            : '500 Internal Server Error';

        header('HTTP/1.0 ' . $header);
        header('Content-Type: application/json');

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    /**
     * Throw an API exception stating that the requested record was not found.
     *
     * @throws \EA\Engine\Api\V1\Exception
     */
    protected function _throwRecordNotFound()
    {
        throw new \EA\Engine\Api\V1\Exception('The requested record was not found!', 404, 'Not Found');
    }
}
