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

namespace EA\Engine\Api\V1\Parsers;

/**
 * Settings Parser
 *
 * This class will handle the encoding and decoding from the API requests.
 */
class Settings implements ParsersInterface {
    /**
     * Encode Response Array
     *
     * @param array &$response The response to be encoded.
     */
    public function encode(array &$response)
    {
        $encodedResponse = [
            'name' => $response['name'],
            'value' => $response['value']
        ];

        $response = $encodedResponse;
    }

    /**
     * Decode Request
     *
     * @param array &$request The request to be decoded.
     * @param array $base Optional (null), if provided it will be used as a base array.
     */
    public function decode(array &$request, array $base = NULL)
    {
        $decodedRequest = $base ?: [];

        if ( ! empty($request['name']))
        {
            $decodedRequest['name'] = $request['name'];
        }

        if ( ! empty($request['value']))
        {
            $decodedRequest['value'] = $request['value'];
        }

        $request = $decodedRequest;
    }
}
