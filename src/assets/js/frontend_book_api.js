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

window.FrontendBookApi = window.FrontendBookApi || {};

/**
 * Frontend Book API
 *
 * This module serves as the API consumer for the booking wizard of the app.
 *
 * @module FrontendBookApi
 */
(function (exports) {

    'use strict';

    var unavailableDatesBackup;
    var selectedDateStringBackup;
    var processingUnavailabilities = false;

    /**
     * Get Available Hours
     *
     * This function makes an AJAX call and returns the available hours for the selected service,
     * provider and date.
     *
     * @param {String} selDate The selected date of which the available hours we need to receive.
     */
    exports.getAvailableHours = function (selDate) {
        $('#available-hours').empty();

        // Find the selected service duration (it is going to be send within the "postData" object).
        var selServiceDuration = 15; // Default value of duration (in minutes).
        $.each(GlobalVariables.availableServices, function (index, service) {
            if (service.id == $('#select-service').val()) {
                selServiceDuration = service.duration;
            }
        });

        // If the manage mode is true then the appointment's start date should return as available too.
        var appointmentId = FrontendBook.manageMode ? GlobalVariables.appointmentData.id : undefined;

		// Set up time format and calculate timezone offset.
		// In the future, this value should be wired with a UI control.
		// var timeFormat = GlobalVariables.timeFormat === 'regular' ? 'h:mm a' : 'HH:mm';
		var timeFormat = 'LT';

        // Make ajax post request and get the available hours.
        var postUrl = GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ] + /* @mangle */ '/index.php/appointments/ajax_get_available_hours' /* @/mangle */;
        var postData = {
            csrfToken: GlobalVariables[/* @mangle */ 'csrfToken' /* @/mangle */ ],
            service_id: $('#select-service').val(),
            provider_id: $('#select-provider').val(),
			selected_date: selDate,
			timezone: GlobalVariables.timezone,
            service_duration: selServiceDuration,
            manage_mode: FrontendBook.manageMode,
            appointment_id: appointmentId
        };

        $.post(postUrl, postData, function (response) {
            if (!GeneralFunctions.handleAjaxExceptions(response)) {
                return;
            }

            // The response contains the available hours for the selected provider and
            // service. Fill the available hours div with response data
            if (response.length > 0) {
                var currColumn = 1;
                $('#available-hours').html('<div class="hours-column"></div>');

				var col_length = Math.max(10, (response.length/4>>0) + 1);
                $.each(response, function (index, availableHour) {
                    if (currColumn * col_length < index + 1) {
                        currColumn++;
                        $('#available-hours').append('<div class="hours-column"></div>');
                    }

					// Massage the select event date and time into normal form in UTC.
					var localEventDateTime = moment.tz(selDate + 'T' + availableHour, GlobalVariables.timezone);

                    $('#available-hours div:eq(' + (currColumn - 1) + ')').append(
						'<span class="available-hour" data-date="' + localEventDateTime.utc().format() + '"  data-timezone="' + GlobalVariables.timezone + '" data-duration="' + selServiceDuration + '">' + localEventDateTime.tz(GlobalVariables.timezone).locale(GlobalVariables.language).format(timeFormat) + '</span><br/>'); // or `.format('DD/' + timeFormat)`
                });

				// If in manage mode, then convert the server-provided appointment timestamp to an RFC timestamp...
				var appointmentDataDateTime;
				if (FrontendBook.manageMode) {
					appointmentDataDateTime = GlobalVariables.appointmentData.start_datetime.replace(' ', 'T').concat('Z');
					// ... and find the corresponding time for the appointment in the UI time-grid (the result will be [] if the selected date is wrong).
					var appointmentDataHourElement = $('.available-hour').filter(function () {
                        return $(this).data('date') === appointmentDataDateTime;
                    });
				} else {
					appointmentDataDateTime = '';
				}
				// If in manage mode and the appointment date and time are in the current timegrid, then select the appointment time...
                if (FrontendBook.manageMode && $("span[data-date='" + appointmentDataDateTime +"']").length > 0 && appointmentDataHourElement.length > 0) {
                    $('.available-hour').removeClass('selected-hour');
                    appointmentDataHourElement.addClass('selected-hour');
                } else {
                    // else set the first available hour as the default selection.
                    $('.available-hour:eq(0)').addClass('selected-hour');
                }

                FrontendBook.updateConfirmFrame();

            } else {
                $('#available-hours').text(EALang.no_available_hours);
            }
        }, 'json').fail(GeneralFunctions.ajaxFailureHandler);
    };

    /**
     * Register an appointment to the database.
     *
     * This method will make an ajax call to the appointments controller that will register
     * the appointment to the database.
     */
    exports.registerAppointment = function () {
        var $captchaText = $('.captcha-text');

        if ($captchaText.length > 0) {
            $captchaText.closest('.form-group').removeClass('has-error');
            if ($captchaText.val() === '') {
                $captchaText.closest('.form-group').addClass('has-error');
                return;
            }
        }

        var formData = jQuery.parseJSON($('input[name="post_data"]').val());
        var postData = {
            csrfToken: GlobalVariables[/* @mangle */ 'csrfToken' /* @/mangle */ ],
            post_data: formData
        };

        if ($captchaText.length > 0) {
            postData.captcha = $captchaText.val();
        }

        if (GlobalVariables.manageMode) {
            postData.exclude_appointment_id = GlobalVariables.appointmentData.id;
        }

        var postUrl = GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ] + /* @mangle */ '/index.php/appointments/ajax_register_appointment' /* @/mangle */;
        var $layer = $('<div/>');

        $.ajax({
            url: postUrl,
            method: /* @mangle */  'POST' /* @/mangle */,
            data: postData,
            dataType: 'json',
            beforeSend: function (jqxhr, settings) {
                $layer
                    .appendTo('body')
                    .css({
                        background: 'white',
                        position: 'fixed',
                        top: '0',
                        left: '0',
                        height: '100vh',
                        width: '100vw',
                        opacity: '0.5'
                    });
            }
        })
            .done(function (response) {

                if (!GeneralFunctions.handleAjaxExceptions(response)) {
                    $('.captcha-title small').trigger('click');
                    return false;
                }

                if (response.captcha_verification === false) {
                    $('#captcha-hint')
                        .text(EALang.captcha_is_wrong)
                        .fadeTo(400, 1);

                    setTimeout(function () {
                        $('#captcha-hint').fadeTo(400, 0);
                    }, 3000);

                    $('.captcha-title small').trigger('click');

                    $captchaText.closest('.form-group').addClass('has-error');

                    return false;
                }

                window.location.href = GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ]
                    + /* @mangle */ '/index.php/appointments/book_success/' /* @/mangle */ + response.appointment_id;
            })
            .fail(function (jqxhr, textStatus, errorThrown) {
                $('.captcha-title small').trigger('click');
                GeneralFunctions.ajaxFailureHandler(jqxhr, textStatus, errorThrown);
            })
            .always(function () {
                $layer.remove();
            });
    };

    /**
     * Get the unavailable dates of a provider.
     *
     * This method will fetch the unavailable dates of the selected provider and service and then it will
     * select the first available date (if any). It uses the "FrontendBookApi.getAvailableHours" method to
     * fetch the appointment* hours of the selected date.
     *
     * @param {Number} providerId The selected provider ID.
     * @param {Number} serviceId The selected service ID.
     * @param {String} selectedDateString Y-m-d value of the selected date.
     */
    exports.getUnavailableDates = function (providerId, serviceId, selectedDateString) {
        if (processingUnavailabilities) {
            return;
        }

        var appointmentId = FrontendBook.manageMode ? GlobalVariables.appointmentData.id : undefined;

        var url = GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ] + /* @mangle */ '/index.php/appointments/ajax_get_unavailable_dates' /* @/mangle */;
        var data = {
            provider_id: providerId,
            service_id: serviceId,
			selected_date: encodeURIComponent(selectedDateString),
			timezone: GlobalVariables.timezone,
            csrfToken: GlobalVariables[/* @mangle */ 'csrfToken' /* @/mangle */ ],
            manage_mode: FrontendBook.manageMode,
            appointment_id: appointmentId
        };

        $.ajax({
            url: url,
            type: 'GET',
            data: data,
            dataType: 'json'
        })
            .done(function (response) {
                unavailableDatesBackup = response;
				selectedDateStringBackup = selectedDateString;
                _applyUnavailableDates(response, selectedDateString, true, selectedDateString);
            })
            .fail(GeneralFunctions.ajaxFailureHandler);
    };

    function _applyUnavailableDates(unavailableDates, selectedDateString, setDate) {
        setDate = setDate || false;
        processingUnavailabilities = true;

		var local_unavailableDates = unavailableDates.map(function (e) {
			return moment.tz(e, GlobalVariables.timezone).format('YYYY-MM-DD');
		})

		var foundDate = false;
        if (selectedDateString && GlobalVariables.manageMode) {
			var appointmentDataDateTime = GlobalVariables.appointmentData.start_datetime.replace(' ', 'T').concat('Z');
			appointmentDataDateTime = moment(appointmentDataDateTime).tz(GlobalVariables.timezone);

			if ( (unavailableDates.indexOf(appointmentDataDateTime.format('YYYY-MM-DD')) === -1)
				&& (selectedDateString.slice(0,7) === appointmentDataDateTime.format('YYYY-MM')) ) {
					$('#select-date').datepicker('setDate', appointmentDataDateTime.format('DD-MM-YYYY'));
					FrontendBookApi.getAvailableHours(
						appointmentDataDateTime.format('YYYY-MM-DD')
					);
					foundDate = true;
			}
        }

        // Select first enabled date.
        var selectedDate = moment.tz(selectedDateString, GlobalVariables.timezone);
        var numberOfDays = selectedDate.daysInMonth();

        if (setDate && !foundDate) {
            for (var i = 1; i <= numberOfDays; i++) {
				var currentDate = new moment.tz([selectedDate.year(), selectedDate.month(), i], GlobalVariables.timezone);

                if (unavailableDates.indexOf(currentDate.format('YYYY-MM-DD')) === -1) {
					$('#select-date').datepicker('setDate', currentDate.format('DD-MM-YYYY'));
					FrontendBookApi.getAvailableHours(
						currentDate.format('YYYY-MM-DD')
					);
                    break;
                }
            }
        }

        // If all the days are unavailable then hide the appointments hours.
        if (unavailableDates.length === numberOfDays) {
            $('#available-hours').text(EALang.no_available_hours);
        }

        // Grey out unavailable dates.
        $('#select-date .ui-datepicker-calendar td:not(.ui-datepicker-other-month)').each(function (index, td) {
			selectedDate.tz(GlobalVariables.timezone).date(index + 1);
            if ($.inArray(selectedDate.format('YYYY-MM-DD'), local_unavailableDates) != -1) {
				GlobalVariables.isActiveDate[index] = false;
            } else {
				GlobalVariables.isActiveDate[index] = true;
			}
        });

		$('#select-date').datepicker('refresh');

        processingUnavailabilities = false;
    }

    /**
     * Save the user's consent.
     *
     * @param {Object} consent Contains user's consents.
     */
    exports.saveConsent = function (consent) {
        var url = GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ] + /* @mangle */ '/index.php/consents/ajax_save_consent' /* @/mangle */;
        var data = {
            csrfToken: GlobalVariables[/* @mangle */ 'csrfToken' /* @/mangle */ ],
            consent: consent
        };

        $.post(url, data, function (response) {
            if (!GeneralFunctions.handleAjaxExceptions(response)) {
                return;
            }
        }, 'json').fail(GeneralFunctions.ajaxFailureHandler);
    };

    /**
     * Delete personal information.
     *
     * @param {Number} customerToken Customer unique token.
     */
    exports.deletePersonalInformation = function (customerToken) {
        var url = GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ] + /* @mangle */ '/index.php/privacy/ajax_delete_personal_information' /* @/mangle */;
        var data = {
            csrfToken: GlobalVariables[/* @mangle */ 'csrfToken' /* @/mangle */ ],
            customer_token: customerToken
        };

        $.post(url, data, function (response) {
            if (!GeneralFunctions.handleAjaxExceptions(response)) {
                return;
            }

            location.href = GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ];
        }, 'json').fail(GeneralFunctions.ajaxFailureHandler);
    };

})(window.FrontendBookApi);
