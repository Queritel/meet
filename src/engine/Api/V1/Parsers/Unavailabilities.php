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
 * Unavailabilities Parser
 *
 * This class will handle the encoding and decoding from the API requests.
 */
class Unavailabilities implements ParsersInterface {
    /**
     * Encode Response Array
     *
     * @param array &$response The response to be encoded.
     */
    public function encode(array &$response)
    {
        $encodedResponse = [
            'id' => $response['id'] !== NULL ? (int)$response['id'] : NULL,
            'book' => $response['book_datetime'],
            'start' => $response['start_datetime'],
			'end' => $response['end_datetime'],
			'timezone' => $response['timezone'],
            'notes' => $response['notes'],
            'providerId' => $response['id_users_provider'] !== NULL ? (int)$response['id_users_provider'] : NULL,
            'googleCalendarId' => $response['id_google_calendar'] !== NULL ? (int)$response['id_google_calendar'] : NULL
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

        if ( ! empty($request['id']))
        {
            $decodedRequest['id'] = $request['id'];
        }

        if ( ! empty($request['book']))
        {
            $decodedRequest['book_datetime'] = $request['book'];
        }

        if ( ! empty($request['start']))
        {
            $decodedRequest['start_datetime'] = $request['start'];
        }

        if ( ! empty($request['end']))
        {
            $decodedRequest['end_datetime'] = $request['end'];
        }

        if ( ! empty($request['timezone']))
        {
            $decodedRequest['timezone'] = $request['timezone'];
        }

        if ( ! empty($request['notes']))
        {
            $decodedRequest['notes'] = $request['notes'];
        }

        if ( ! empty($request['providerId']))
        {
            $decodedRequest['id_users_provider'] = $request['providerId'];
        }

        if ( ! empty($request['googleCalendarId']))
        {
            $decodedRequest['id_google_calendar'] = $request['googleCalendarId'];
        }

        $decodedRequest['is_unavailable'] = TRUE;

        $request = $decodedRequest;
    }
}
