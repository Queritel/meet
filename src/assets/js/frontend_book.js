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

window.FrontendBook = window.FrontendBook || {};

/**
 * Frontend Book
 *
 * This module contains functions that implement the book appointment page functionality. Once the
 * initialize() method is called the page is fully functional and can serve the appointment booking
 * process.
 *
 * @module FrontendBook
 */
(function (exports) {

    'use strict';

    /**
     * Contains terms and conditions consent.
     *
     * @type {Object}
     */
    var termsAndConditionsConsent;

    /**
     * Contains privacy policy consent.
     *
     * @type {Object}
     */
    var privacyPolicyConsent;

    /**
     * Determines the functionality of the page.
     *
     * @type {Boolean}
     */
    exports.manageMode = false;

    /**
     * This method initializes the book appointment page.
     *
     * @param {Boolean} bindEventHandlers (OPTIONAL) Determines whether the default
     * event handlers will be bound to the dom elements.
     * @param {Boolean} manageMode (OPTIONAL) Determines whether the customer is going
     * to make  changes to an existing appointment rather than booking a new one.
     */
    exports.initialize = function (bindEventHandlers, manageMode) {

        bindEventHandlers = bindEventHandlers || true;
        manageMode = manageMode || false;

		// Use spinner with all AJAX requests.
		$.LoadingOverlaySetup({
			"background"      : "rgba(255, 255, 255, 0)",
			"imageAnimation"  : "rotate_right",
		});
        $(document).ajaxSend(function(event, jqxhr, settings){
            $("#book-appointment-wizard").LoadingOverlay("show");
        });
        $(document).ajaxComplete(function(event, jqxhr, settings){
            $("#book-appointment-wizard").LoadingOverlay("hide");
        });
        $("#book-appointment-wizard").LoadingOverlay("hide");

        if (window.console === undefined) {
            window.console = function () {
            }; // IE compatibility
        }

        if (GlobalVariables.displayCookieNotice) {
            if (typeof cookieconsent !== 'undefined') {
				cookieconsent.initialise({
					palette: {
						popup: {
							background: '#ffffffbd',
							text: '#666666'
						},
						button: {
							background: '#3DD481',
							text: '#ffffff'
						}
					},
					content: {
						message: EALang.website_using_cookies_to_ensure_best_experience,
						dismiss: 'OK'
					},
				});

				$('.cc-link').replaceWith(
					$('<a/>', {
						'data-toggle': 'modal',
						'data-target': '#cookie-notice-modal',
						'href': '#',
						'class': 'cc-link',
						'text': $('.cc-link').text()
					})
				);
			}
        }

        FrontendBook.manageMode = manageMode;

        // Initialize page's components (tooltips, datepickers etc).
        $('.book-step').qtip({
            position: {
                my: 'top center',
                at: 'bottom center'
            },
            style: {
                classes: 'qtip-green qtip-shadow custom-qtip'
            }
        });

        $('#select-date').datepicker({
            dateFormat: 'dd-mm-yy',
            firstDay: 0,
            minDate: -1,
            defaultDate: Date.today(),

            dayNames: [
                EALang.sunday, EALang.monday, EALang.tuesday, EALang.wednesday,
                EALang.thursday, EALang.friday, EALang.saturday],
            dayNamesShort: [EALang.sunday.substr(0, 3), EALang.monday.substr(0, 3),
                EALang.tuesday.substr(0, 3), EALang.wednesday.substr(0, 3),
                EALang.thursday.substr(0, 3), EALang.friday.substr(0, 3),
                EALang.saturday.substr(0, 3)],
            dayNamesMin: [EALang.sunday.substr(0, 2), EALang.monday.substr(0, 2),
                EALang.tuesday.substr(0, 2), EALang.wednesday.substr(0, 2),
                EALang.thursday.substr(0, 2), EALang.friday.substr(0, 2),
                EALang.saturday.substr(0, 2)],
            monthNames: [EALang.january, EALang.february, EALang.march, EALang.april,
                EALang.may, EALang.june, EALang.july, EALang.august, EALang.september,
                EALang.october, EALang.november, EALang.december],
            prevText: EALang.previous,
            nextText: EALang.next,
            currentText: EALang.now,
            closeText: EALang.close,

            onSelect: function (dateText, instance) {
                FrontendBookApi.getAvailableHours($(this).datepicker('getDate').toString('yyyy-MM-dd'));
                FrontendBook.updateConfirmFrame();
            },

            onChangeMonthYear: function (year, month, instance) {
				GlobalVariables.isActiveDate = [];
                var currentDate = moment().set('year', year).set('month', month - 1).set('date', 1);
                FrontendBookApi.getUnavailableDates($('#select-provider').select2('data')[0].id, $('#select-service').select2('data')[0].id,
                    currentDate.format('YYYY-MM-DD'));
			},

			beforeShowDay: function(date) {
				return [GlobalVariables.isActiveDate[moment(date).date() - 1], '', ''];
			}
        });

		// Construct and mount the service selector.
		$("#select-service").select2({
			// Select2 placeholder. Works only if an empty <option /> exists at the top of the <select>.
			// placeholder: EALang.select_service,
			minimumResultsForSearch: -1,
			theme: 'bootstrap',
			width: '100%',
            language: GlobalVariables.availableLanguages[GlobalVariables.language][5]
		}).maximizeSelect2Height();		// We chain-in this plugin to maximize the dropdown length.

		// Construct and mount the provider selector.
		$("#select-provider").select2({
			// placeholder: EALang.select_provider,
			minimumResultsForSearch: -1,
			theme: 'bootstrap',
			width: '100%',
            language: GlobalVariables.availableLanguages[GlobalVariables.language][5]
			// Dropdown disabled until a service is selected (event handler below).
			// For use with Select2 placeholders:
			// disabled: 'true'
		});

		// Construct the timezone selector.
		tz_init();

		// If using placeholders, the provider should be selectable only once a service has been selected.
		$("#select-service").on("select2:select", function(e) {
			$("#select-provider").attr('disabled', false);
		})

        // Bind the event handlers (might not be necessary every time we use this class).
        if (bindEventHandlers) {
            _bindEventHandlers();
        }

        // If the manage mode is true, the appointments data should be loaded by default.
        if (FrontendBook.manageMode) {
            _applyAppointmentData(GlobalVariables.appointmentData,
                GlobalVariables.providerData, GlobalVariables.customerData);
        } else {
            var $selectProvider = $('#select-provider');
            var $selectService = $('#select-service');

            // Check if a specific service was selected (via URL parameter).
            var selectedServiceId = GeneralFunctions.getUrlParameter(location.href, 'service');

            if (selectedServiceId && $selectService.find('option[value="' + selectedServiceId + '"]').length > 0) {
                $selectService.val(selectedServiceId);
            }

            $selectService.trigger('change'); // Load the available hours.

            // URL PARAM `provider`: Check if a specific provider was selected. 
            var selectedProviderId = GeneralFunctions.getUrlParameter(location.href, 'provider');

            if (selectedProviderId && $selectProvider.find('option[value="' + selectedProviderId + '"]').length === 0) {
                // Select a service of this provider in order to make the provider available in the select box.
                for (var index in GlobalVariables.availableProviders) {
                    var provider = GlobalVariables.availableProviders[index];

                    if (provider.id === selectedProviderId && provider.services.length > 0) {
                        $selectService
                            .val(provider.services[0])
                            .trigger('change');
                    }
                }
            }

            if (selectedProviderId && $selectProvider.find('option[value="' + selectedProviderId + '"]').length > 0) {
                $selectProvider
                    .val(selectedProviderId)
                    .trigger('change');
            }

			// URL PARAM `proceed`: Check whether the caller wishes to proceed to step 2 automatically.
            var proceed = GeneralFunctions.getUrlParameter(location.href, 'proceed');
			if (proceed != '') {
				$(".button-next").trigger('click');
			}

			// URL PARAM `mini`: Hide service descriptions.
			var mini = GeneralFunctions.getUrlParameter(location.href, 'mini');
			if (mini != '') {
				$("#service-description").hide();
			}
        }
    };

    /**
     * This method binds the necessary event handlers for the book appointments page.
     */
    function _bindEventHandlers() {
        /**
         * Event: Selected Provider "Changed"
         *
         * Whenever the provider changes the available appointment date - time periods must be updated.
         */
        $('#select-provider').change(function () {
            FrontendBookApi.getUnavailableDates($(this).select2('data')[0].id, $('#select-service').select2('data')[0].id,
                $('#select-date').datepicker('getDate').toString('yyyy-MM-dd'));
            FrontendBook.updateConfirmFrame();
        });

        /**
         * Event: Selected Service "Changed"
         *
         * When the user clicks on a service, its available providers should
         * become visible.
         */
        $('#select-service').change(function () {

            if ($('#select-service').select2('data') !== undefined  && $('#select-service').select2('data').length !== 0) {
                var currServiceId = $('#select-service').select2('data')[0].id


                $('#select-provider').html('');

                $.each(GlobalVariables.availableProviders, function (indexProvider, provider) {
                    $.each(provider.services, function (indexService, serviceId) {
                        // If the current provider is able to provide the selected service,
                        // add him to the listbox.
                        if (serviceId == currServiceId) {
                            $('#select-provider').append(new Option(provider.first_name + ' ' + provider.last_name, provider.id));
                        }
                    });
                });

                // Add the "Any Provider" entry.
                if ($('#select-provider option').length >= 1 && GlobalVariables.displayAnyProvider === '1') {
                    $('#select-provider').append(new Option(EALang.any_provider, 'any-provider'));
                }

                FrontendBookApi.getUnavailableDates($('#select-provider').select2('data')[0].id, $(this).val(),
                    $('#select-date').datepicker('getDate').toString('yyyy-MM-dd'));
                FrontendBook.updateConfirmFrame();
                _updateServiceDescription($('#select-service').select2('data')[0].id, $('#service-description'));
            }
        });

        /**
         * Event: Next Step Button "Clicked"
         *
         * This handler is triggered every time the user pressed the "next" button on the book wizard.
         * Some special tasks might be performed, depending the current wizard step.
         */
        $('.button-next').click(function () {
            // If no service is selectable (for instance because of lack of availability in the given locale), then ignore the click.
            if ($('#select-service').select2('data') === undefined || $('#select-service').select2('data').length === 0) {
                return
            }

            // If we are on the first step and there is not provider selected do not continue
            // with the next step.
            if ($(this).attr('data-step_index') === '1' && $('#select-provider').select2('data')[0].id == null) {
                return;
            }

            // If we are at the 1st tab update the info frames before proceeding.
            if ($(this).attr('data-step_index') === '1') {
                FrontendBook.updateConfirmFrame();
            }

            // If we are on the 2nd tab then the user should have an appointment hour
            // selected.
            if ($(this).attr('data-step_index') === '2') {
                if ($('.selected-hour').length == 0) {
                    if ($('#select-hour-prompt').length == 0) {
                        $('#available-hours').append('<br><br>'
                            + '<span id="select-hour-prompt" class="text-danger">'
                            + EALang.appointment_hour_missing
                            + '</span>');
                    }
                    return;
                }
            }

            // If we are on the 3rd tab then we will need to validate the user's
            // input before proceeding to the next step.
            if ($(this).attr('data-step_index') === '3') {
                if (!_validateCustomerForm()) {
                    return; // Validation failed, do not continue.
                } else {
                    FrontendBook.updateConfirmFrame();

                    var $acceptToTermsAndConditions = $('#accept-to-terms-and-conditions');
                    if ($acceptToTermsAndConditions.length && $acceptToTermsAndConditions.prop('checked') === true) {
                        var newTermsAndConditionsConsent = {
                            first_name: $('#first-name').val(),
                            last_name: $('#last-name').val(),
                            email: $('#email').val(),
                            type: 'terms-and-conditions'
                        };

                        if (JSON.stringify(newTermsAndConditionsConsent) !== JSON.stringify(termsAndConditionsConsent)) {
                            termsAndConditionsConsent = newTermsAndConditionsConsent;
                            FrontendBookApi.saveConsent(termsAndConditionsConsent);
                        }
                    }

                    var $acceptToPrivacyPolicy = $('#accept-to-privacy-policy');
                    if ($acceptToPrivacyPolicy.length && $acceptToPrivacyPolicy.prop('checked') === true) {
                        var newPrivacyPolicyConsent = {
                            first_name: $('#first-name').val(),
                            last_name: $('#last-name').val(),
                            email: $('#email').val(),
                            type: 'privacy-policy'
                        };

                        if (JSON.stringify(newPrivacyPolicyConsent) !== JSON.stringify(privacyPolicyConsent)) {
                            privacyPolicyConsent = newPrivacyPolicyConsent;
                            FrontendBookApi.saveConsent(privacyPolicyConsent);
                        }
                    }
					FrontendBookApi.registerAppointment();
                }
            }

            // Display the next step tab (uses jquery animation effect).
			// Replace `fade` with `slide` for a different nauseating effect.
            var parentEl;
			var nextTabIndex;

			if ($(this).attr('data-step_index') == 1) {
				parentEl = $(this).parents().eq(3);
				nextTabIndex = 2;
			} else {
				parentEl = $(this).parents().eq(1);
				nextTabIndex = parseInt($(this).attr('data-step_index')) + 1;
			}

            parentEl.hide('fade', { direction: "left" }, function () {
                $('.active-step').removeClass('active-step');
                $('#step-' + nextTabIndex).addClass('active-step');
                $('#wizard-frame-' + nextTabIndex).show('fade', { direction: "right" });
            });
        });

        /**
         * Event: Back Step Button "Clicked"
         *
         * This handler is triggered every time the user pressed the "back" button on the
         * book wizard.
         */
        $('.button-back').click(function () {
            var prevTabIndex = parseInt($(this).attr('data-step_index')) - 1;

            $(this).parents().eq(1).hide('fade', { direction: "right" }, function () {
                $('.active-step').removeClass('active-step');
                $('#step-' + prevTabIndex).addClass('active-step');
                $('#wizard-frame-' + prevTabIndex).show('fade', { direction: "left" });
            });
        });

        /**
         * Event: Available Hour "Click"
         *
         * Triggered whenever the user clicks on an available hour
         * for his appointment.
         */
        $('#available-hours').on('click', '.available-hour', function () {
            $('.selected-hour').removeClass('selected-hour');
            $(this).addClass('selected-hour');
            FrontendBook.updateConfirmFrame();
        });

        if (FrontendBook.manageMode) {
            /**
             * Event: Cancel Appointment Button "Click"
             *
             * When the user clicks the "Cancel" button this form is going to be submitted. We need
             * the user to confirm this action because once the appointment is cancelled, it will be
             * delete from the database.
             *
             * @param {jQuery.Event} event
             */
            $('#cancel-appointment').click(function (event) {
                var buttons = [
                    {
                        text: 'OK',
                        click: function () {
                            if ($('#cancel-reason').val() === '') {
                                $('#cancel-reason').css('border', '2px solid red');
                                return;
                            }
                            $('#cancel-appointment-form textarea').val($('#cancel-reason').val());
                            $('#cancel-appointment-form').submit();
                        }
                    },
                    {
                        text: EALang.cancel,
                        click: function () {
                            $('#message_box').dialog('close');
                        }
                    }
                ];

                GeneralFunctions.displayMessageBox(EALang.cancel_appointment_title,
                    EALang.write_appointment_removal_reason, buttons);

                $('#message_box').append('<textarea id="cancel-reason" rows="3"></textarea>');
                $('#cancel-reason').css('width', '100%');
                return false;
            });

            $('#delete-personal-information').on('click', function () {
                var buttons = [
                    {
                        text: EALang.delete,
                        click: function () {
                            FrontendBookApi.deletePersonalInformation(GlobalVariables.customerToken);
                        }
                    },
                    {
                        text: EALang.cancel,
                        click: function () {
                            $('#message_box').dialog('close');
                        }
                    }
                ];

                GeneralFunctions.displayMessageBox(EALang.delete_personal_information,
                    EALang.delete_personal_information_prompt, buttons);
            });
        }

        /**
         * Event: Book Appointment Form "Submit"
         *
         * Before the form is submitted to the server we need to make sure that
         * in the meantime the selected appointment date/time wasn't reserved by
         * another customer or event.
         *
         * @param {jQuery.Event} event
         */
        // $('#book-appointment-submit').click(function (event) {
        //     FrontendBookApi.registerAppointment();
        // });

        /**
         * Event: Refresh captcha image.
         *
         * @param {jQuery.Event} event
         */
        $('.captcha-title small').click(function (event) {
            $('.captcha-image').attr('src', GlobalVariables[/* @mangle */ 'baseUrl' /* @/mangle */ ] + /* @mangle */ '/index.php/captcha?' /* @/mangle */ + Date.now());
        });
    }

    /**
     * This function validates the customer's data input. The user cannot continue
     * without passing all the validation checks.
     *
     * @return {Boolean} Returns the validation result.
     */
    function _validateCustomerForm() {
        $('#wizard-frame-3 .has-error').removeClass('has-error');
        $('#wizard-frame-3 label.text-danger').removeClass('text-danger');

        try {
            // Validate required fields.
            var missingRequiredField = false;
            $('.required').each(function () {
                if ($(this).val() == '') {
                    $(this).parents('.form-group').addClass('has-error');
                    missingRequiredField = true;
                }
            });
            if (missingRequiredField) {
                throw EALang.fields_are_required;
            }

            var $acceptToTermsAndConditions = $('#accept-to-terms-and-conditions');
            if ($acceptToTermsAndConditions.length && !$acceptToTermsAndConditions.prop('checked')) {
                $acceptToTermsAndConditions.parents('label').addClass('text-danger');
                throw EALang.fields_are_required;
            }

            var $acceptToPrivacyPolicy = $('#accept-to-privacy-policy');
            if ($acceptToPrivacyPolicy.length && !$acceptToPrivacyPolicy.prop('checked')) {
                $acceptToPrivacyPolicy.parents('label').addClass('text-danger');
                throw EALang.fields_are_required;
            }


            // Validate email address.
            if (!GeneralFunctions.validateEmail($('#email').val())) {
                $('#email').parents('.form-group').addClass('has-error');
                throw EALang.invalid_email;
            }

            return true;
        } catch (exc) {
            $('#form-message').text(exc);
            return false;
        }
    }

    /**
     * Every time this function is executed, it updates the confirmation page with the latest
     * customer settings and input for the appointment booking.
     */
    exports.updateConfirmFrame = function () {
        if ($('.selected-hour').text() === '') {
            return;
        }

        // Appointment Details
        var selectedDate = $('#select-date').datepicker('getDate');

        if (selectedDate !== null) {
            selectedDate = GeneralFunctions.formatDate(selectedDate, GlobalVariables.dateFormat);
        }

        var selServiceId = $('#select-service').select2('data')[0].id;
        var servicePrice = '';
        var serviceCurrency = '';
		var serviceDuration = 0;

        $.each(GlobalVariables.availableServices, function (index, service) {
			if (service.id == selServiceId) {
                if (service.price != '' && service.price != null && service.price != '0.00' && service.price != '0,00') {
                    servicePrice = '<br>' + service.price;
                    serviceCurrency = service.currency;
                }
                serviceDuration = service.duration;
                return false; // break loop
            }
        });

        // Update summary appointment data below date picker.
        if ($('.js-Selector').data('select2')) {
            var html =
                // Service
                '<strong>' + $('#select-service option:selected').text() + '</strong>' +
                '<p>'
                // Time to meeting (with uppercase first letter)
                + moment.tz().locale(GlobalVariables.language).to(moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone)).charAt(0).toUpperCase() + moment.tz().locale(GlobalVariables.language).to(moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone)).slice(1) + '<br>'
                // Full date
                + moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone).locale(GlobalVariables.language).format('dddd') + ', '
                // (incl. week day on same line)
                + moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone).locale(GlobalVariables.language).format('D MMM') + ', '
                // Start time
                + moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone).locale(GlobalVariables.language).format('LT')
                // – End time
                + '&ndash;' + moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone).locale(GlobalVariables.language).add(serviceDuration, 'minutes').format('LT')
                + ' (' + $(".js-Selector").select2('data')[0]['text'] + ')'
                + '</strong>'
                + '</p>';
        }

		$('#appointment-details-1').html(html);

        if ($('.js-Selector').data('select2')) {
            var html =
                // Service
                '<h4>' + $('#select-service option:selected').text() + '</h4>' +
                '<p>'
                + '<strong class="text-primary">'
                // Provider
                + $('#select-provider option:selected').text() + '<br><br>'
                // Time to meeting (with uppercase first letter)
                + moment.tz().locale(GlobalVariables.language).to(moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone)).charAt(0).toUpperCase() + moment.tz().locale(GlobalVariables.language).to(moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone)).slice(1) + ':<br>'
                // Full date
                + moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone).locale(GlobalVariables.language).format('dddd') + ', '
                // (incl. week day on same line)
                + moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone).locale(GlobalVariables.language).format('LL') + '<br>'
                // Start time
                + moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone).locale(GlobalVariables.language).format('LT')
                // – End time
                + '&ndash;' + moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone).locale(GlobalVariables.language).add(serviceDuration, 'minutes').format('LT')
                // (incl. timezone city on same line)
                + ' (' + $(".js-Selector").select2('data')[0]['text'] + ')<br>'
                // Price
                + servicePrice + ' ' + serviceCurrency
                + '</strong>' +
                '</p>';
        }

        $('#appointment-details-2').html(html);

        // Customer details (for POST).
        var firstName = GeneralFunctions.escapeHtml($('#first-name').val());
        var lastName = GeneralFunctions.escapeHtml($('#last-name').val());
        var phoneNumber = GeneralFunctions.escapeHtml($('#phone-number').val());
        var email = GeneralFunctions.escapeHtml($('#email').val());
        var address = GeneralFunctions.escapeHtml($('#address').val());
        var city = GeneralFunctions.escapeHtml($('#city').val());
        var zipCode = GeneralFunctions.escapeHtml($('#zip-code').val());

        html =
            '<h4>' + firstName + ' ' + lastName + '</h4>' +
            '<p>' +
            EALang.email + ': ' + email +
            '<br/>' +
            EALang.phone + ': ' + phoneNumber +
            '<br/>';
            // EALang.address + ': ' + address +
            // '<br/>' +
            // EALang.city + ': ' + city +
            // '<br/>' +
            // EALang.zip_code + ': ' + zipCode +
            // '</p>';

        // Update appointment form data (for POST).
        var postData = {};

        postData.customer = {
            last_name: $('#last-name').val(),
            first_name: $('#first-name').val(),
            email: $('#email').val(),
            phone_number: $('#phone-number').val(),
            address: $('#address').val(),
            city: $('#city').val(),
            zip_code: $('#zip-code').val()
        };

        postData.appointment = {
            start_datetime: moment.tz($('.selected-hour').data('date'), 'Etc/UTC').format('YYYY-MM-DD HH:mm:ss'),
			end_datetime: _calcEndDatetime(),
			timezone: GlobalVariables.timezone,
            notes: $('#notes').val(),
            is_unavailable: false,
            id_users_provider: $('#select-provider').select2('data')[0].id,
            id_services: $('#select-service').select2('data')[0].id
        };

        postData.manage_mode = FrontendBook.manageMode;

        if (FrontendBook.manageMode) {
            postData.appointment.id = GlobalVariables.appointmentData.id;
            postData.customer.id = GlobalVariables.customerData.id;
        }
        $('input[name="csrfToken"]').val(GlobalVariables[/* @mangle */ 'csrfToken' /* @/mangle */ ]);
        $('input[name="post_data"]').val(JSON.stringify(postData));
    };

    /**
     * This method calculates the end datetime of the current appointment.
     * End datetime is depending on the service and start datetime fields.
     *
     * @return {String} Returns the end datetime in string format.
     */
    function _calcEndDatetime() {

		// Find selected service duration.
        var selServiceDuration = undefined;

        $.each(GlobalVariables.availableServices, function (index, service) {
            if (service.id == $('#select-service').select2('data')[0].id) {
                selServiceDuration = service.duration;
                return false; // Stop searching ...
            }
        });

        // Add the duration to the start datetime.
        var startDatetime = moment.tz($('.selected-hour').data('date'), GlobalVariables.timezone);
        var endDatetime = undefined;

        if (selServiceDuration !== undefined && startDatetime !== null) {
            endDatetime = startDatetime.add(selServiceDuration, 'm');
        } else {
            endDatetime = new moment.tz(GlobalVariables.timezone);
        }

        return endDatetime.utc().format('YYYY-MM-DD HH:mm:ss');
    }

    /**
     * This method applies the appointment's data to the wizard so
     * that the user can start making changes on an existing record.
     *
     * @param {Object} appointment Selected appointment's data.
     * @param {Object} provider Selected provider's data.
     * @param {Object} customer Selected customer's data.
     *
     * @return {Boolean} Returns the operation result.
     */
    function _applyAppointmentData(appointment, provider, customer) {
        try {
            // Select Service & Provider
            $('#select-service').val(appointment.id_services).trigger('change');
            $('#select-provider').val(appointment.id_users_provider).trigger('change');

            // Set Appointment Date
            $('#select-date').datepicker('setDate',
                Date.parseExact(appointment.start_datetime, 'yyyy-MM-dd HH:mm:ss'));
            FrontendBookApi.getAvailableHours(appointment.start_datetime.slice(0,10));

			// Set appointment timezone.
			GlobalVariables.timezone = appointment.timezone;
			$(".js-Selector")
			.val(GlobalVariables.timezone)
			.trigger("change.select2"); // Set up change event handler.

            // Apply Customer's Data
            $('#last-name').val(customer.last_name);
            $('#first-name').val(customer.first_name);
            $('#email').val(customer.email);
            $('#phone-number').val(customer.phone_number);
            $('#address').val(customer.address);
            $('#city').val(customer.city);
            $('#zip-code').val(customer.zip_code);
            var appointmentNotes = (appointment.notes !== null)
                ? appointment.notes : '';
            $('#notes').val(appointmentNotes);

            FrontendBook.updateConfirmFrame();

            return true;
        } catch (exc) {
            return false;
        }
    }

    /**
     * This method updates a div's html content with a brief description of the
     * user selected service (only if available in db). This is useful for the
     * customers upon selecting the correct service.
     *
     * @param {Number} serviceId The selected service record id.
     * @param {Object} $div The destination div jquery object (e.g. provide $('#div-id')
     * object as value).
     */
    function _updateServiceDescription(serviceId, $div) {
        var html = '';

        $.each(GlobalVariables.availableServices, function (index, service) {
            if (service.id == serviceId) { // Just found the service.
                html = '<strong>' + service.name + ' </strong>';

                if (service.description != '' && service.description != null) {
                    html += '<br>' + service.description + '<br>';
                }

                if (service.duration != '' && service.duration != null) {
                    html += EALang.duration + ': ' + service.duration + ' ' + EALang.minutes + '<br />';
                }

				if (service.price != '' && service.price != null && service.price != '0.00' && service.price != '0,00') {
                    html += EALang.price + ': ' + service.price + ' ' + service.currency;
                }

                html += '<br>';

                return false;
            }
        });

        $div.html(html);

        if (html != '') {
            $div.show();
        } else {
            $div.hide();
        }
    }

})(window.FrontendBook);
