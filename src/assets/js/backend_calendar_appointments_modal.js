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
 * Backend Calendar Appointments Modal
 *
 * This module implements the appointments modal functionality.
 *
 * @module BackendCalendarAppointmentsModal
 */
window.BackendCalendarAppointmentsModal = window.BackendCalendarAppointmentsModal || {};

(function (exports) {

    'use strict';

    function _bindEventHandlers() {
        /**
         * Event: Manage Appointments Dialog Cancel Button "Click"
         *
         * Closes the dialog without saving any changes to the database.
         */
        $('#manage-appointment #cancel-appointment').click(function () {
            $('#manage-appointment').modal('hide');
        });

        /**
         * Event: Manage Appointments Dialog Save Button "Click"
         *
         * Stores the appointment changes or inserts a new appointment depending the dialog mode.
         */
        $('#manage-appointment #save-appointment').click(function () {
            // Before doing anything the appointment data need to be validated.
            if (!_validateAppointmentForm()) {
                return;
            }

            // Prepare appointment data for AJAX request.
            var $dialog = $('#manage-appointment');

            // ID must exist on the object in order for the model to update the record and not to perform
            // an insert operation.

            var startDatetime = moment.tz($dialog.find('#start-datetime')[0]._flatpickr.selectedDates[0].toString('yyyy-MM-dd HH:mm'), GlobalVariables.timezone).tz('UTC').format('YYYY-MM-DD HH:mm:ss');
            var endDatetime = moment.tz($dialog.find('#end-datetime')[0]._flatpickr.selectedDates[0].toString('yyyy-MM-dd HH:mm'), GlobalVariables.timezone).tz('UTC').format('YYYY-MM-DD HH:mm:ss');

            var appointment = {
                id_services: $dialog.find('#select-service').val(),
                id_users_provider: $dialog.find('#select-provider').val(),
                start_datetime: startDatetime,
                end_datetime: endDatetime,
				timezone: $dialog.find('#hidden-timezone').val() == '' ?  GlobalVariables.timezone : $dialog.find('#hidden-timezone').val(),
                notes: $dialog.find('#appointment-notes').val(),
                is_unavailable: false
            };

            if ($dialog.find('#appointment-id').val() !== '') {
                // Set the id value, only if we are editing an appointment.
                appointment.id = $dialog.find('#appointment-id').val();
            }

            var customer = {
                first_name: $dialog.find('#first-name').val(),
                last_name: $dialog.find('#last-name').val(),
                email: $dialog.find('#email').val(),
                phone_number: $dialog.find('#phone-number').val(),
                address: $dialog.find('#address').val(),
                city: $dialog.find('#city').val(),
                zip_code: $dialog.find('#zip-code').val(),
                notes: $dialog.find('#customer-notes').val()
            };

            if ($dialog.find('#customer-id').val() !== '') {
                // Set the id value, only if we are editing an appointment.
                customer.id = $dialog.find('#customer-id').val();
                appointment.id_users_customer = customer.id;
            }

            // Define success callback.
            var successCallback = function (response) {
                if (!GeneralFunctions.handleAjaxExceptions(response)) {
                    $dialog.find('.modal-message').text(EALang.unexpected_issues_occurred);
                    $dialog.find('.modal-message').addClass('alert-danger').removeClass('hidden');
                    return false;
                }

                // Display success message to the user.
                $dialog.find('.modal-message').text(EALang.appointment_saved);
                $dialog.find('.modal-message').addClass('alert-success').removeClass('alert-danger hidden');
                $dialog.find('.modal-body').scrollTop(0);

                // Close the modal dialog and refresh the calendar appointments after one second.
                setTimeout(function () {
                    $dialog.find('.alert').addClass('hidden');
                    $dialog.modal('hide');
                    $('#select-filter-item').trigger('change');
                }, 2000);
            };

            // Define error callback.
            var errorCallback = function () {
                $dialog.find('.modal-message').text(EALang.service_communication_error);
                $dialog.find('.modal-message').addClass('alert-danger').removeClass('hidden');
                $dialog.find('.modal-body').scrollTop(0);
            };

            // Save appointment data.
            BackendCalendarApi.saveAppointment(appointment, customer, successCallback, errorCallback);
        });

        /**
         * Event: Insert Appointment Button "Click"
         *
         * When the user presses this button, the manage appointment dialog opens and lets the user to
         * create a new appointment.
         */
        $('#insert-appointment').click(function (event, options) {

            BackendCalendarAppointmentsModal.resetAppointmentDialog(options);
            var $dialog = $('#manage-appointment');

            // Set the selected filter item and find the next appointment time as the default modal values.
            if ($('#select-filter-item option:selected').attr('type') == 'provider') {
                var providerId = $('#select-filter-item').val();

                var providers = GlobalVariables.availableProviders.filter(function (provider) {
                    return provider.id == providerId;
                });

                if (providers.length) {
                    $dialog.find('#select-service').val(providers[0].services[0]).trigger('change');
                    $dialog.find('#select-provider').val(providerId);
                }
            } else {
                $dialog.find('#select-service option[value="'
                    + $('#select-filter-item').val() + '"]').prop('selected', true);
            }

            var serviceDuration = 0;
            $.each(GlobalVariables.availableServices, function (index, service) {
                if (service.id == $dialog.find('#select-service').val()) {
                    serviceDuration = service.duration;
                    return false; // exit loop
                }
            });

            // Display modal form.
            $dialog.find('.modal-header h3').text(EALang.new_appointment_title);
            $dialog.modal('show');
        });

        /**
         * Event: Pick Existing Customer Button "Click"
         */
        $('#select-customer').click(function () {
            var $list = $('#existing-customers-list');

            if (!$list.is(':visible')) {
                $(this).text(EALang.hide);
                $list.empty();
                $list.slideDown('slow');
                $('#filter-existing-customers').fadeIn('slow');
                $('#filter-existing-customers').val('');
                $.each(GlobalVariables.customers, function (index, c) {
                    $list.append('<div data-id="' + c.id + '">'
                        + c.first_name + ' ' + c.last_name + '</div>');
                });
            } else {
                $list.slideUp('slow');
                $('#filter-existing-customers').fadeOut('slow');
                $(this).text(EALang.select);
            }
        });

        /**
         * Event: Select Existing Customer From List "Click"
         */
        $('#manage-appointment').on('click', '#existing-customers-list div', function () {
            var id = $(this).attr('data-id');

            $.each(GlobalVariables.customers, function (index, c) {
                if (c.id == id) {
                    $('#customer-id').val(c.id);
                    $('#first-name').val(c.first_name);
                    $('#last-name').val(c.last_name);
                    $('#email').val(c.email);
                    $('#phone-number').val(c.phone_number);
                    $('#address').val(c.address);
                    $('#city').val(c.city);
                    $('#zip-code').val(c.zip_code);
                    $('#customer-notes').val(c.notes);
                    return false;
                }
            });

            $('#select-customer').trigger('click'); // hide list
        });

        /**
         * Event: Filter Existing Customers "Change"
         */
        $('#filter-existing-customers').keyup(function () {
            var key = $(this).val().toLowerCase();
            var $list = $('#existing-customers-list');
            var postUrl = GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ] + /* @mangle */ '/index.php/backend_api/ajax_filter_customers' /* @/mangle */;
            var postData = {
                csrfToken: GlobalVariables[/* @mangle */ 'csrfToken' /* @/mangle */ ],
                key: key
            };

            // Try to get the updated customer list.
            $.ajax({
                type: /* @mangle */  'POST' /* @/mangle */,
                url: postUrl,
                data: postData,
                dataType: 'json',
                timeout: 1000,
                global: false,
                success: function (response) {
                    $list.empty();
                    $.each(response, function (index, c) {
                        $list.append('<div data-id="' + c.id + '">'
                            + c.first_name + ' ' + c.last_name + '</div>');

                        // Verify if this customer is on the old customer list.
                        var result = $.grep(GlobalVariables.customers,
                            function (e) {
                                return e.id == c.id;
                            });

                        // Add it to the customer list.
                        if (result.length == 0) {
                            GlobalVariables.customers.push(c);
                        }
                    });
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    // If there is any error on the request, search by the local client database.
                    $list.empty();
                    $.each(GlobalVariables.customers, function (index, c) {
                        if (c.first_name.toLowerCase().indexOf(key) != -1
                            || c.last_name.toLowerCase().indexOf(key) != -1
                            || c.email.toLowerCase().indexOf(key) != -1
                            || c.phone_number.toLowerCase().indexOf(key) != -1
                            || c.address.toLowerCase().indexOf(key) != -1
                            || c.city.toLowerCase().indexOf(key) != -1
                            || c.zip_code.toLowerCase().indexOf(key) != -1
                            || c.notes.toLowerCase().indexOf(key) != -1) {
                            $list.append('<div data-id="' + c.id + '">'
                                + c.first_name + ' ' + c.last_name + '</div>');
                        }
                    });
                }
            });
        });

        /**
         * Event: Selected Service "Change"
         *
         * When the user clicks on a service, its available providers should become visible. Also we need to
         * update the start and end time of the appointment.
         */
        $('#select-service').change(function () {
            var sid = $('#select-service').val();
            $('#select-provider').empty();

            // Automatically update the service duration.
            $.each(GlobalVariables.availableServices, function (indexService, service) {
                if (service.id == sid) {
                    var start = $('#start-datetime')[0]._flatpickr.selectedDates[0];
                    $('#end-datetime')[0]._flatpickr.setDate(start.clone().addMinutes(service.duration));
                    return false; // break loop
                }
            });

            // Update the providers select box.
            $.each(GlobalVariables.availableProviders, function (indexProvider, provider) {
                $.each(provider.services, function (indexService, serviceId) {
                    if (GlobalVariables.user.role_slug === Backend.DB_SLUG_PROVIDER && parseInt(provider.id) !== GlobalVariables.user.id) {
                        return true; // continue
                    }

                    if (GlobalVariables.user.role_slug === Backend.DB_SLUG_SECRETARY && GlobalVariables.secretaryProviders.indexOf(provider.id) === -1) {
                        return true; // continue
                    }

                    // If the current provider is able to provide the selected service, add him to the listbox.
                    if (serviceId == sid) {
                        var optionHtml = '<option value="' + provider.id + '">'
                            + provider.first_name + ' ' + provider.last_name
                            + '</option>';
                        $('#select-provider').append(optionHtml);
                    }
                });
            });
        });

        /**
         * Event: Enter New Customer Button "Click"
         */
        $('#new-customer').click(function () {
            $('#manage-appointment').find('#customer-id, #first-name, #last-name, #email, '
                + '#phone-number, #address, #city, #zip-code, #customer-notes').val('');
        });
    }

    /**
     * Reset Appointment Dialog
     *
     * This method resets the manage appointment dialog modal to its initial state. After that you can make
     * any modification might be necessary in order to bring the dialog to the desired state.
     */
    exports.resetAppointmentDialog = function (options) {
        var $dialog = $('#manage-appointment');

        // Empty form fields.
        $dialog.find('input, textarea').val('');
        $dialog.find('.modal-message').fadeOut();

        // Prepare service and provider select boxes.
        $dialog.find('#select-service').val(
		$dialog.find('#select-service').eq(0).attr('value'));

        // Fill the providers listbox with providers that can serve the appointment's
        // service and then select the user's provider.
        $dialog.find('#select-provider').empty();
        $.each(GlobalVariables.availableProviders, function (index, provider) {
            var canProvideService = false;

            $.each(provider.services, function (index, serviceId) {
                if (serviceId == $dialog.find('#select-service').val()) {
                    canProvideService = true;
                    return false;
                }
            });

            if (canProvideService) { // Add the provider to the listbox.
                var option = new Option(provider.first_name
                    + ' ' + provider.last_name, provider.id);
                $dialog.find('#select-provider').append(option);
            }
        });

        // Close existing customers-filter frame.
        $('#existing-customers-list').slideUp('slow');
        $('#filter-existing-customers').fadeOut('slow');
        $('#select-customer').text(EALang.select);

        // Setup start and datetimepickers.
        // Get the selected service duration. It will be needed in order to calculate the appointment end datetime.
        var serviceDuration = 0;
        $.each(GlobalVariables.availableServices, function (index, service) {
            if (service.id == $dialog.find('#select-service').val()) {
                serviceDuration = service.duration;
                return false;
            }
        });

		// Initialize hidden timezone placeholder.
		$dialog.find('#hidden-timezone').val('');

		// Note that defaultEnd is in fact overridden by the service duration (see onClose callback below).
		// I think this mostly makes sense (inasmuch it could be risky for consistency in back-end logic
		// to enable flexible-duration services at the moment).
		var defaultStart, defaultEnd;
		if (typeof options === 'undefined' || !(options.start && options.end)) {
			defaultStart = moment().minute(Math.ceil(moment().minute() / 15) * 15);
			defaultEnd = defaultStart.clone().add(60, 'm');
		} else {
			defaultStart = moment.tz(options.start.format(), GlobalVariables.timezone);
			defaultEnd = moment.tz(options.end.format(), GlobalVariables.timezone);
		}

		$dialog.find('#start-datetime').flatpickr({
			defaultDate: defaultStart.second(0).tz(GlobalVariables.timezone).format('YYYY-MM-DD HH:mm'),
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
                var sid = $('#select-service').val();
                // Automatically update the #end-datetime DateTimePicker based on service duration.
                $.each(GlobalVariables.availableServices, function (indexService, service) {
                    if (service.id == sid) {
                        var start = $('#start-datetime')[0]._flatpickr.selectedDates[0];
						$('#end-datetime')[0]._flatpickr.setDate(new Date(start.getTime() + service.duration * 60000));
                        return false; // break loop
                    }
                });
				picker.setDate(picker.altInput.value, true, picker.config.altFormat);
             }
		});

		var defaultStart = moment().minute(Math.ceil(moment().minute() / 15) * 15);

        $dialog.find('#end-datetime').flatpickr({
			defaultDate: defaultEnd.second(0).tz(GlobalVariables.timezone).format('YYYY-MM-DD HH:mm'),
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

    /**
     * Validate the manage appointment dialog data. Validation checks need to
     * run every time the data are going to be saved.
     *
     * @returns {Boolean} Returns the validation result.
     */
    function _validateAppointmentForm() {
        var $dialog = $('#manage-appointment');

        // Reset previous validation css formatting.
        $dialog.find('.has-error').removeClass('has-error');
        $dialog.find('.modal-message').addClass('hidden');

        try {
            // Check required fields.
            var missingRequiredField = false;

            $dialog.find('.required').each(function () {
                if ($(this).val() == '' || $(this).val() == null) {
                    $(this).closest('.form-group').addClass('has-error');
                    missingRequiredField = true;
                }
            });

            if (missingRequiredField) {
                throw EALang.fields_are_required;
            }

            // Check email address.
            if (!GeneralFunctions.validateEmail($dialog.find('#email').val())) {
                $dialog.find('#email').closest('.form-group').addClass('has-error');
                throw EALang.invalid_email;
            }

            // Check appointment start and end time.
            var start = $('#start-datetime')[0]._flatpickr.selectedDates[0];
            var end = $('#end-datetime')[0]._flatpickr.selectedDates[0];
            if (start >= end) {
                $dialog.find('#start-datetime, #end-datetime').closest('.form-group').addClass('has-error');
                throw EALang.start_date_before_end_error;
            }

            return true;
        } catch (exc) {
            $dialog.find('.modal-message').addClass('alert-danger').text(exc).removeClass('hidden');
            return false;
        }
    }

    exports.initialize = function () {
        _bindEventHandlers();
    };

})(window.BackendCalendarAppointmentsModal);
