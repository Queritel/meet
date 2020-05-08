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
 * Backend Calendar Unavailabilities Modal
 *
 * This module implements the unavailabilities modal functionality.
 *
 * @module BackendCalendarUnavailabilitiesModal
 */
window.BackendCalendarUnavailabilitiesModal = window.BackendCalendarUnavailabilitiesModal || {};

(function (exports) {

    'use strict';

    function _bindEventHandlers() {
        /**
         * Event: Manage Unavailable Dialog Save Button "Click"
         *
         * Stores the unavailable period changes or inserts a new record.
         */
        $('#manage-unavailable #save-unavailable').click(function () {
            var $dialog = $('#manage-unavailable');
            $dialog.find('.has-error').removeClass('has-error');
            var start = moment.tz($dialog.find('#unavailable-start')[0]._flatpickr.selectedDates[0].toString('yyyy-MM-dd HH:mm'), GlobalVariables.timezone);
            var end = moment.tz($dialog.find('#unavailable-end')[0]._flatpickr.selectedDates[0].toString('yyyy-MM-dd HH:mm'), GlobalVariables.timezone);

            if (start >= end) {
                // Start time is after end time - display message to user.
                $dialog.find('.modal-message')
                    .text(EALang.start_date_before_end_error)
                    .addClass('alert-danger')
                    .removeClass('hidden');

                $dialog.find('#unavailable-start, #unavailable-end').closest('.form-group').addClass('has-error');
                return;
            }

            // Unavailable period records go to the appointments table.
            var unavailable = {
                start_datetime: start.tz('UTC').format('YYYY-MM-DD HH:mm'),
                end_datetime: end.tz('UTC').format('YYYY-MM-DD HH:mm'),
				timezone: GlobalVariables.timezone,
                notes: $dialog.find('#unavailable-notes').val(),
                id_users_provider: $('#unavailable-provider').val() // curr provider
            };

            if ($dialog.find('#unavailable-id').val() !== '') {
                // Set the id value, only if we are editing an appointment.
                unavailable.id = $dialog.find('#unavailable-id').val();
            }

            var successCallback = function (response) {
                if (response.exceptions) {
                    response.exceptions = GeneralFunctions.parseExceptions(response.exceptions);
                    GeneralFunctions.displayMessageBox(GeneralFunctions.EXCEPTIONS_TITLE,
                        GeneralFunctions.EXCEPTIONS_MESSAGE);
                    $('#message_box').append(GeneralFunctions.exceptionsToHtml(response.exceptions));

                    $dialog.find('.modal-message')
                        .text(EALang.unexpected_issues_occurred)
                        .addClass('alert-danger')
                        .removeClass('hidden');

                    return;
                }

                if (response.warnings) {
                    response.warnings = GeneralFunctions.parseExceptions(response.warnings);
                    GeneralFunctions.displayMessageBox(GeneralFunctions.WARNINGS_TITLE,
                        GeneralFunctions.WARNINGS_MESSAGE);
                    $('#message_box').append(GeneralFunctions.exceptionsToHtml(response.warnings));
                }

                // Display success message to the user.
                $dialog.find('.modal-message')
                    .text(EALang.unavailable_saved)
                    .addClass('alert-success')
                    .removeClass('alert-danger hidden');

                // Close the modal dialog and refresh the calendar appointments after one second.
                setTimeout(function () {
                    $dialog.find('.alert').addClass('hidden');
                    $dialog.modal('hide');
                    $('#select-filter-item').trigger('change');
                }, 2000);
            };

            var errorCallback = function (jqXHR, textStatus, errorThrown) {
                GeneralFunctions.displayMessageBox('Communication Error', 'Unfortunately ' +
                    'the operation could not complete due to server communication errors.');

                $dialog.find('.modal-message').txt(EALang.service_communication_error);
                $dialog.find('.modal-message').addClass('alert-danger').removeClass('hidden');
            };

            BackendCalendarApi.saveUnavailable(unavailable, successCallback, errorCallback);
        });

        /**
         * Event: Manage Unavailable Dialog Cancel Button "Click"
         *
         * Closes the dialog without saveing any changes to the database.
         */
        $('#manage-unavailable #cancel-unavailable').click(function () {
            $('#manage-unavailable').modal('hide');
        });

        /**
         * Event : Insert Unavailable Time Period Button "Click"
         *
         * When the user clicks this button a popup dialog appears and the use can set a time period where
         * he cannot accept any appointments.
         */
        $('#insert-unavailable').click(function (event, options) {
            BackendCalendarUnavailabilitiesModal.resetUnavailableDialog(options);
            var $dialog = $('#manage-unavailable');

            // Set the default datetime values.
            var start = new Date();
            var currentMin = parseInt(start.toString('mm'));

            if (currentMin > 0 && currentMin < 15) {
                start.set({'minute': 15});
            } else if (currentMin > 15 && currentMin < 30) {
                start.set({'minute': 30});
            } else if (currentMin > 30 && currentMin < 45) {
                start.set({'minute': 45});
            } else {
                start.addHours(1).set({'minute': 0});
            }

            if ($('.calendar-view').length === 0) {
                $dialog.find('#unavailable-provider')
                    .val($('#select-filter-item').val())
                    .closest('.form-group')
                    .hide();
            }

            $dialog.find('.modal-header h3').text(EALang.new_unavailable_title);
            $dialog.modal('show');
        });
    }

    /**
     * Reset unavailable dialog form.
     *
     * Reset the "#manage-unavailable" dialog. Use this method to bring the dialog to the initial state
     * before it becomes visible to the user.
     */
    exports.resetUnavailableDialog = function (options) {
        var $dialog = $('#manage-unavailable');

		var start, end, dragged;
		if (typeof options === 'undefined' || !(options.start && options.end)) {
			// Set default time values
			start = moment().minute(Math.ceil(moment().minute() / 15) * 15).second(0).tz(GlobalVariables.timezone).format('YYYY-MM-DD HH:mm');
			end = moment().minute(Math.ceil(moment().minute() / 15) * 15).add(60, 'm').second(0).tz(GlobalVariables.timezone).format('YYYY-MM-DD HH:mm');
			dragged = false;
		} else {
			start = moment.tz(options.start.format(), GlobalVariables.timezone).format('YYYY-MM-DD HH:mm');
			end = moment.tz(options.end.format(), GlobalVariables.timezone).format('YYYY-MM-DD HH:mm');
			dragged = true;
		}

		$dialog.find('#unavailable-start').flatpickr({
			defaultDate: start,
			previousDate: null,
			allowInput: true,
			altInput: true,
			altFormat: 'D, j F, H:i',
			dateFormat: 'Y-m-d H:i',
            locale: GlobalVariables.availableLanguages[GlobalVariables.language][4],
			weekNumbers: true,
			enableTime: true,
			time_24hr: true,
			onOpen: function(selectedDates, dateStr, instance) {
				this.config.previousDate = selectedDates[0].toString('yyyy-MM-dd HH:mm');
			},
			onClose: function(dates, currentdatestring, picker) {
				var oldStart = moment.tz(this.config.previousDate, GlobalVariables.timezone);
				var oldEnd = moment.tz($dialog.find('#unavailable-end')[0]._flatpickr.selectedDates[0].toString('yyyy-MM-dd HH:mm'), GlobalVariables.timezone);
				var diff = oldEnd.diff(oldStart, 'm');
				// Automatically update the #end-datetime DateTimePicker.
				var start = $dialog.find('#unavailable-start')[0]._flatpickr.selectedDates[0];
				$dialog.find('#unavailable-end')[0]._flatpickr.setDate(new Date(start.getTime() + diff * 60000)); // (min x msec/min)
				picker.setDate(picker.altInput.value, true, picker.config.altFormat);

				this.config.previousDate = start.toString('yyyy-MM-dd HH:mm');
            },
			onReady: function() { this.showTimeInput = true }
		});

		$dialog.find('#unavailable-end').flatpickr({
			defaultDate: end,
			allowInput: true,
			altInput: true,
			altFormat: 'D, j F, H:i',
			dateFormat: 'Y-m-d H:i',
            locale: GlobalVariables.availableLanguages[GlobalVariables.language][4],
			weekNumbers: true,
			enableTime: true,
			time_24hr: true,
			onReady: function() { this.showTimeInput = true },
			onClose: function(dates, currentdatestring, picker) {
				picker.setDate(picker.altInput.value, true, picker.config.altFormat);
            }
		});
    };

    exports.initialize = function () {
        var $unavailabilityProvider = $('#unavailable-provider');

        for (var index in GlobalVariables.availableProviders) {
            var provider = GlobalVariables.availableProviders[index];

            $unavailabilityProvider.append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));
        }

        _bindEventHandlers();
    };

})(window.BackendCalendarUnavailabilitiesModal); 
