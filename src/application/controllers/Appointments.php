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

use \EA\Engine\Types\Text;
use \EA\Engine\Types\Email;
use \EA\Engine\Types\Url;

/**
 * Appointments Controller
 *
 * @package Controllers
 */
class Appointments extends CI_Controller {
    /**
     * Class Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('session');
		$this->load->helper('installation');

        // Set user's selected language.
        if ($this->session->userdata('language'))
        {
            $this->config->set_item('language', $this->session->userdata('language'));
			$this->lang->load(
				'translations',
				$this->config->item('available_languages')[$this->session->userdata('language')][0]
			);
        }
        else
        {
            $this->lang->load('translations', $this->config->item('available_languages')[$this->config->item('language')][0]);
        }

        // Common helpers
        $this->load->helper('google_analytics');
    }

    /**
     * Default callback method of the application.
     *
     * This method creates the appointment book wizard. If an appointment hash
     * is provided then it means that the customer followed the appointment
     * manage link that was send with the book success email.
     *
     * @param string $appointment_hash DB appointment hash of an existing record (default '').
     */
    public function index($appointment_hash = '')
    {
        if ( ! is_ea_installed())
        {
            redirect('installation/index');
            return;
        }

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('customers_model');
        $this->load->model('settings_model');

        try
        {
            $available_services = $this->services_model->get_available_services();
            $available_providers = $this->providers_model->get_available_providers();
            $company_name = $this->settings_model->get_setting('company_name');
            $date_format = $this->settings_model->get_setting('date_format');
            $time_format = $this->settings_model->get_setting('time_format');
            $require_phone_number = $this->settings_model->get_setting('require_phone_number');
            $display_cookie_notice = $this->settings_model->get_setting('display_cookie_notice');
            $cookie_notice_content = $this->settings_model->get_setting('cookie_notice_content');
            $display_terms_and_conditions = $this->settings_model->get_setting('display_terms_and_conditions');
            $terms_and_conditions_content = $this->settings_model->get_setting('terms_and_conditions_content');
            $display_privacy_policy = $this->settings_model->get_setting('display_privacy_policy');
            $privacy_policy_content = $this->settings_model->get_setting('privacy_policy_content');
            $display_any_provider = $this->settings_model->get_setting('display_any_provider');


            // Remove the data that are not needed inside the $available_providers array.
            foreach ($available_providers as $index => $provider)
            {
                $stripped_data = [
                    'id' => $provider['id'],
                    'first_name' => $provider['first_name'],
                    'last_name' => $provider['last_name'],
                    'services' => $provider['services']
                ];
                $available_providers[$index] = $stripped_data;
            }

            // If an appointment hash is provided then it means that the customer
            // is trying to edit a registered appointment record.
            if ($appointment_hash !== '')
            {
                // Load the appointments data and enable the manage mode of the page.
                $manage_mode = TRUE;

                $results = $this->appointments_model->get_batch(['hash' => $appointment_hash]);

                if (count($results) === 0)
                {
                    // The requested appointment doesn't exist in the database. Display
                    // a message to the customer.
                    $view = [
                        'message_title' => $this->lang->line('appointment_not_found'),
                        'message_text' => $this->lang->line('appointment_does_not_exist_in_db'),
                        'message_icon' => base_url('assets/img/error.png')
                    ];
                    $this->load->view('appointments/message', $view);
                    return;
                }

                $appointment = $results[0];
                $provider = $this->providers_model->get_row($appointment['id_users_provider']);
                $customer = $this->customers_model->get_row($appointment['id_users_customer']);

                $customer_token = md5(uniqid(mt_rand(), true));

                $this->load->driver('cache', ['adapter' => 'file']);

                $this->cache->save('customer-token-' . $customer_token, $customer['id'], 600); // save for 10 minutes
            }
            else
            {
                // The customer is going to book a new appointment so there is no
                // need for the manage functionality to be initialized.
                $manage_mode = FALSE;
                $customer_token = FALSE;
                $appointment = [];
                $provider = [];
                $customer = [];
            }

            // Load the book appointment view.
            $view = [
                'available_services' => $available_services,
                'available_providers' => $available_providers,
                'company_name' => $company_name,
                'manage_mode' => $manage_mode,
                'customer_token' => $customer_token,
                'date_format' => $date_format,
                'time_format' => $time_format,
                'require_phone_number' => $require_phone_number,
                'appointment_data' => $appointment,
                'provider_data' => $provider,
                'customer_data' => $customer,
                'display_cookie_notice' => $display_cookie_notice,
                'cookie_notice_content' => $cookie_notice_content,
                'display_terms_and_conditions' => $display_terms_and_conditions,
                'terms_and_conditions_content' => $terms_and_conditions_content,
                'display_privacy_policy' => $display_privacy_policy,
                'privacy_policy_content' => $privacy_policy_content,
                'display_any_provider' => $display_any_provider
            ];
        }
        catch (Exception $exc)
        {
            $view['exceptions'][] = $exc;
        }

        $this->load->view('appointments/book', $view);
    }

    /**
     * Cancel an existing appointment.
     *
     * This method removes an appointment from the company's schedule. In order for the appointment to be deleted, the
     * hash string must be provided. The customer can only cancel the appointment if the edit time period is not over
     * yet. Provide the $_POST['cancel_reason'] parameter to describe the cancellation reason.
     *
     * @param string $appointment_hash This is used to distinguish the appointment record.
     */
    public function cancel($appointment_hash)
    {
        try
        {
            $this->load->model('appointments_model');
            $this->load->model('providers_model');
            $this->load->model('customers_model');
            $this->load->model('services_model');
            $this->load->model('settings_model');

            // Check whether the appointment hash exists in the database.
            $records = $this->appointments_model->get_batch(['hash' => $appointment_hash]);
            if (count($records) == 0)
            {
                throw new Exception('No record matches the provided hash.');
            }

            $appointment = $records[0];
            $provider = $this->providers_model->get_row($appointment['id_users_provider']);
            $customer = $this->customers_model->get_row($appointment['id_users_customer']);
            $service = $this->services_model->get_row($appointment['id_services']);

            $company_settings = [
                'company_name' => $this->settings_model->get_setting('company_name'),
                'company_email' => $this->settings_model->get_setting('company_email'),
                'company_link' => $this->settings_model->get_setting('company_link'),
                'date_format' => $this->settings_model->get_setting('date_format'),
                'time_format' => $this->settings_model->get_setting('time_format')
            ];

            // :: DELETE APPOINTMENT RECORD FROM THE DATABASE.
            if ( ! $this->appointments_model->delete($appointment['id']))
            {
                throw new Exception('Appointment could not be deleted from the database.');
            }

            // :: SYNC APPOINTMENT REMOVAL WITH GOOGLE CALENDAR
            if ($appointment['id_google_calendar'] != NULL)
            {
                try
                {
                    $google_sync = filter_var($this->providers_model
                        ->get_setting('google_sync', $appointment['id_users_provider']), FILTER_VALIDATE_BOOLEAN);

                    if ($google_sync == TRUE)
                    {
                        $google_token = json_decode($this->providers_model
                            ->get_setting('google_token', $provider['id']));
                        $this->load->library('Google_sync');
                        $this->google_sync->refresh_token($google_token->refresh_token);
                        $this->google_sync->delete_appointment($provider, $appointment['id_google_calendar']);
                    }
                }
                catch (Exception $exc)
                {
                    $exceptions[] = $exc;
                }
            }

            // :: SEND NOTIFICATION EMAILS TO CUSTOMER AND PROVIDER
            try
            {
                $this->config->load('email');
                $email = new \EA\Engine\Notifications\Email($this, $this->config->config);

                $send_provider = filter_var($this->providers_model
                    ->get_setting('notifications', $provider['id']), FILTER_VALIDATE_BOOLEAN);

                if ($send_provider === TRUE)
                {
                    $email->sendDeleteAppointment($appointment, $provider,
                        $service, $customer, $company_settings, new Email($provider['email']),
                        new Text($this->input->post('cancel_reason')));
                }

                $send_customer = filter_var($this->settings_model->get_setting('customer_notifications'),
                    FILTER_VALIDATE_BOOLEAN);

                if ($send_customer === TRUE)
                {
                    $email->sendDeleteAppointment($appointment, $provider,
                        $service, $customer, $company_settings, new Email($customer['email']),
                        new Text($this->input->post('cancel_reason')));
                }

            }
            catch (Exception $exc)
            {
                $exceptions[] = $exc;
            }
        }
        catch (Exception $exc)
        {
            // Display the error message to the customer.
            $exceptions[] = $exc;
        }

        $view = [
            'message_title' => $this->lang->line('appointment_cancelled_title'),
            'message_text' => $this->lang->line('appointment_cancelled'),
            'message_icon' => base_url('assets/img/success.png')
        ];

        if (isset($exceptions))
        {
            $view['exceptions'] = $exceptions;
        }

        $this->load->view('appointments/message', $view);
    }

    /**
     * GET an specific appointment book and redirect to the success screen.
     *
     * @param int $appointment_id Contains the ID of the appointment to retrieve.
     */
    public function book_success($appointment_id)
    {
        // If the appointment id doesn't exist or zero redirect to index.
        if ( ! $appointment_id)
        {
            redirect('appointments');
        }
        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
        //retrieve the data needed in the view
        $appointment = $this->appointments_model->get_row($appointment_id);
        $provider = $this->providers_model->get_row($appointment['id_users_provider']);
        $service = $this->services_model->get_row($appointment['id_services']);
        $company_name = $this->settings_model->get_setting('company_name');
        //get the exceptions
        $exceptions = $this->session->flashdata('book_success');

        unset($provider['settings']);

        // :: LOAD THE BOOK SUCCESS VIEW
        $view = [
            'appointment_data' => $appointment,
            'provider_data' => $provider,
            'service_data' => $service,
            'company_name' => $company_name,
        ];
        if ($exceptions)
        {
            $view['exceptions'] = $exceptions;
        }
        $this->load->view('appointments/book_success', $view);
    }

    /**
     * [AJAX] Get the available appointment hours for the given date.
     *
     * This method answers to an AJAX request. It calculates the available hours for the given service, provider and
     * date.
     *
     * Required POST parameters:
     *
     * - int $_POST['service_id'] Selected service record ID.
     * - int|string $_POST['provider_id'] Selected provider record id, can also be 'any-provider'.
     * - string $_POST['selected_date'] Selected date for availabilities.
     * - int $_POST['service_duration'] Selected service duration in minutes.
     * - string $_POST['manage_mode'] Contains either 'true' or 'false' and determines the if current user
     * is managing an already booked appointment or not.
     *
     * Outputs a JSON string with the availabilities.
     *
     * @deprecated Since v1.3.0, this method will be replaced with a future release.
     */
    public function ajax_get_available_hours()
    {
        $this->load->model('providers_model');
        $this->load->model('appointments_model');
        $this->load->model('settings_model');
        $this->load->model('services_model');

        try
        {
            // Do not continue if there was no provider selected (more likely there is no provider in the system).
            if (empty($this->input->post('provider_id')))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([]));
                return;
            }

            // If manage mode is TRUE then the selected appointment should not be
            // factored in when calculating the available time periods of the provider.
            $exclude_appointments = ($this->input->post('manage_mode') === 'true')
                ? [$this->input->post('appointment_id')]
                : [];

            // If the user has selected the "any-provider" option then we will need to search
            // for an available provider that will provide the requested service.
            if ($this->input->post('provider_id') === ANY_PROVIDER)
            {
                $_POST['provider_id'] = $this->_search_any_provider($this->input->post('service_id'),
                    $this->input->post('selected_date'));
                if ($this->input->post('provider_id') === NULL)
                {
                    $this->output
                        ->set_content_type('application/json')
                        ->set_output(json_encode([]));
                    return;
                }
            }

            $service = $this->services_model->get_row($this->input->post('service_id'));
            $provider = $this->providers_model->get_row($_POST['provider_id']);

			// Get available hours in the vicinity of the selected date.
			// See `ajax_get_unavailable_dates` for more detailed commentary.
			$selected_date = (new DateTime($this->input->post('selected_date')))->format('Y-m-d');
			$previous_date = (new DateTime($this->input->post('selected_date')))->modify('-1 day')->format('Y-m-d');
			$next_date = (new DateTime($this->input->post('selected_date')))->modify('+1 day')->format('Y-m-d');

			$empty_periods_selected = $this->_get_provider_available_time_periods(
				$this->input->post('provider_id'),
				$selected_date,
				$exclude_appointments
			);

            $available_hours_selected = array_map(
				function($t) use($selected_date) {
					return DateTime::createFromFormat('Y-m-d H:i',  $selected_date . ' ' . $t);
				},
				$this->_calculate_available_hours(
					$empty_periods_selected,
					$selected_date,
					$this->input->post('service_duration'),
					filter_var($this->input->post('manage_mode'), FILTER_VALIDATE_BOOLEAN),
					$service['availabilities_type']
				)
			);

			$empty_periods_previous = $this->_get_provider_available_time_periods(
				$this->input->post('provider_id'),
				$previous_date,
				$exclude_appointments
			);

            $available_hours_previous = array_map(
				function($t) use($previous_date) {
					return DateTime::createFromFormat('Y-m-d H:i',  $previous_date . ' ' . $t);
				},
				$this->_calculate_available_hours(
					$empty_periods_previous,
					$previous_date,
					$this->input->post('service_duration'),
					filter_var($this->input->post('manage_mode'), FILTER_VALIDATE_BOOLEAN),
					$service['availabilities_type']
				)
			);

			$empty_periods_next = $this->_get_provider_available_time_periods(
				$this->input->post('provider_id'),
				$next_date,
				$exclude_appointments
			);

            $available_hours_next = array_map(
				function($t) use($next_date) {
					return DateTime::createFromFormat('Y-m-d H:i',  $next_date . ' ' . $t);
				},
				$this->_calculate_available_hours(
					$empty_periods_next,
					$next_date,
					$this->input->post('service_duration'),
					filter_var($this->input->post('manage_mode'), FILTER_VALIDATE_BOOLEAN),
					$service['availabilities_type']
				)
			);

			// Merge the available hours in the vicinity of the current date.
			$available_hours_merged = [];
			$c = 0;
			foreach ($available_hours_previous as $i => $v) {
				$available_hours_merged[$c] = $v;
				$c++;
			}
			foreach ($available_hours_selected as $i => $v) {
				$available_hours_merged[$c] = $v;
				$c++;
			}
			foreach ($available_hours_next as $i => $v) {
				$available_hours_merged[$c] = $v;
				$c++;
			}

			// Create the selected timezone object.
			try {
				$selected_timezone = new DateTimeZone($this->input->post('timezone'));
			} catch(Exception $e) {
				$selected_timezone = new DateTimeZone('Etc/UTC');
			}

			// Create the server timezone object.
			$utc_timezone = new DateTimeZone('Etc/UTC');

			// Internationalize the selected date and delimit it in UTC.
			$selected_date_local = new DateTime($selected_date, $selected_timezone);
			$selected_date_start_utc = (clone $selected_date_local)->setTime(0, 0)->setTimezone($utc_timezone);
			$selected_date_end_utc = (clone $selected_date_local)->setTime(23, 59)->setTimezone($utc_timezone);

			// If the selected date is "today" (in the selected timezone) then adjust today's start date-time accordingly.
			$loc_now_plus = new DateTime();
			$loc_now_plus->modify(
					"+{$this->settings_model->get_setting('book_advance_timeout')} minutes"
				);
			if ($loc_now_plus > $selected_date_start_utc) {
				$selected_date_start_utc = $loc_now_plus;
			}

			// Filter out available hours not within the current date in the selected timezone.
			$available_hours = array_filter(
				$available_hours_merged,
				function($a) use($selected_date_start_utc, $selected_date_end_utc) {
					return ($a >= $selected_date_start_utc && $a <=$selected_date_end_utc)
					? true
					: false;
				}
			);

			// Convert available times back to `H:i` strings in the local timezone.
            $available_hours = array_map(
				function($t) use($selected_timezone) {
					return $t->setTimezone($selected_timezone)->format('H:i');
				},
				$available_hours
			);

			// Handle multiple-attendant services.
            if ($service['attendants_number'] > 1)
            {
				$available_hours_selected = array_map(
					function($t) use($selected_date) {
						return DateTime::createFromFormat('Y-m-d H:i',  $selected_date . ' ' . $t);
					},
                	$available_hours_selected = $this->_get_multiple_attendants_hours($selected_date, $service, $provider)
				);
				$available_hours_previous = array_map(
					function($t) use($previous_date) {
						return DateTime::createFromFormat('Y-m-d H:i',  $previous_date . ' ' . $t);
					},
					$available_hours_previous = $this->_get_multiple_attendants_hours($previous_date, $service, $provider)
				);
				$available_hours_next = array_map(
					function($t) use($next_date) {
						return DateTime::createFromFormat('Y-m-d H:i',  $next_date . ' ' . $t);
					},
					$available_hours_next = $this->_get_multiple_attendants_hours($next_date, $service, $provider)
				);

				// Merge the available hours in the vicinity of the current date.
				$available_hours_merged = [];
				$c = 0;
				foreach ($available_hours_previous as $i => $v) {
					$available_hours_merged[$c] = $v;
					$c++;
				}
				foreach ($available_hours_selected as $i => $v) {
					$available_hours_merged[$c] = $v;
					$c++;
				}
				foreach ($available_hours_next as $i => $v) {
					$available_hours_merged[$c] = $v;
					$c++;
				}

				// Filter out available hours not within the current date in the selected timezone.
				$available_hours = array_filter(
					$available_hours_merged,
					function($a) use($selected_date_start_utc, $selected_date_end_utc) {
						return ($a >= $selected_date_start_utc && $a <=$selected_date_end_utc)
						? true
						: false;
					}
				);

				// Convert available times back to `H:i` strings in the local timezone.
				$available_hours = array_map(
					function($t) use($selected_timezone) {
						return $t->setTimezone($selected_timezone)->format('H:i');
					},
					$available_hours
				);
            }

            $available_hours = array_values($available_hours);
            sort($available_hours, SORT_STRING);
            $available_hours = array_values($available_hours);

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($available_hours));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'exceptions' => [exceptionToJavaScript($exc)]
                ]));
        }
    }

    /**
     * [AJAX] Register the appointment to the database.
     *
     * Outputs a JSON string with the appointment ID.
     */
    public function ajax_register_appointment()
    {
        try
        {
			$post_data_dirty = $this->input->post('post_data'); // alias

			$this->load->helper('htmlpurifier');	// Anti-XSS measures for end-user inputs.
			$post_data = html_purify($post_data_dirty, 'comment');

            $post_data['manage_mode'] = filter_var($post_data['manage_mode'], FILTER_VALIDATE_BOOLEAN);

            $this->load->model('appointments_model');
            $this->load->model('providers_model');
            $this->load->model('services_model');
            $this->load->model('customers_model');
            $this->load->model('settings_model');

            // Validate the CAPTCHA string.
            if ($this->settings_model->get_setting('require_captcha') === '1'
                && $this->session->userdata('captcha_phrase') !== $this->input->post('captcha'))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'captcha_verification' => FALSE
                    ]));
                return;
            }

            // Check appointment availability.
            if ( ! $this->_check_datetime_availability())
            {
                throw new Exception($this->lang->line('requested_hour_is_unavailable'));
            }

            $appointment = $post_data['appointment'];
            $customer = $post_data['customer'];

            if ($this->customers_model->exists($customer))
            {
                $customer['id'] = $this->customers_model->find_record_id($customer);
            }

			// Add customer info to database
			$customer_id = $this->customers_model->add($customer);
            $appointment['id_users_customer'] = $customer_id;
			$appointment['is_unavailable'] = (int)$appointment['is_unavailable']; // needs to be type-cast

			// Add appointment info to dataase.
            $appointment['id'] = $this->appointments_model->add($appointment);
            $appointment['hash'] = $this->appointments_model->get_value('hash', $appointment['id']);

			// Save client contact info in session.
			$this->session->set_userdata('first-name', $customer['first_name']);
			$this->session->set_userdata('last-name', $customer['last_name']);
			$this->session->set_userdata('email', $customer['email']);

            $provider = $this->providers_model->get_row($appointment['id_users_provider']);
            $service = $this->services_model->get_row($appointment['id_services']);

            $company_settings = [
                'company_name' => $this->settings_model->get_setting('company_name'),
                'company_link' => $this->settings_model->get_setting('company_link'),
                'company_email' => $this->settings_model->get_setting('company_email'),
                'date_format' => $this->settings_model->get_setting('date_format'),
                'time_format' => $this->settings_model->get_setting('time_format')
            ];

            // :: SYNCHRONIZE APPOINTMENT WITH PROVIDER'S GOOGLE CALENDAR
            // The provider must have previously granted access to his google calendar account
            // in order to sync the appointment.
            try
            {
                $google_sync = filter_var($this->providers_model->get_setting('google_sync',
                    $appointment['id_users_provider']), FILTER_VALIDATE_BOOLEAN);

                if ($google_sync == TRUE)
                {
                    $google_token = json_decode($this->providers_model
                        ->get_setting('google_token', $appointment['id_users_provider']));

                    $this->load->library('google_sync');
                    $this->google_sync->refresh_token($google_token->refresh_token);

                    if ($post_data['manage_mode'] === FALSE)
                    {
                        // Add appointment to Google Calendar.
                        $google_event = $this->google_sync->add_appointment($appointment, $provider,
                            $service, $customer, $company_settings);
                        $appointment['id_google_calendar'] = $google_event->id;
                        $this->appointments_model->add($appointment);
                    }
                    else
                    {
                        // Update appointment to Google Calendar.
                        $appointment['id_google_calendar'] = $this->appointments_model
                            ->get_value('id_google_calendar', $appointment['id']);

                        $this->google_sync->update_appointment($appointment, $provider,
                            $service, $customer, $company_settings);
                    }
                }
            }
            catch (Exception $exc)
            {
                log_message('error', $exc->getMessage());
                log_message('error', $exc->getTraceAsString());
            }

            // :: SEND NOTIFICATION EMAILS TO BOTH CUSTOMER AND PROVIDER
            try
            {
                $this->config->load('email');
                $email = new \EA\Engine\Notifications\Email($this, $this->config->config);

                if ($post_data['manage_mode'] == FALSE)
                {
                    $customer_title = new Text($this->lang->line('appointment_booked'));
                    $customer_message = new Text($this->lang->line('thank_you_for_appointment'));
                    $provider_title = new Text($this->lang->line('appointment_added_to_your_plan'));
                    $provider_message = new Text($this->lang->line('appointment_link_description'));

                }
                else
                {
                    $customer_title = new Text($this->lang->line('appointment_changes_saved'));
                    $customer_message = new Text('');
                    $provider_title = new Text($this->lang->line('appointment_details_changed'));
                    $provider_message = new Text('');
                }

                $customer_link = new Url(site_url('appointments/index/' . $appointment['hash']));
                $provider_link = new Url(site_url('backend/index/' . $appointment['hash']));

                $send_customer = filter_var($this->settings_model->get_setting('customer_notifications'),
                    FILTER_VALIDATE_BOOLEAN);

                $this->load->library('ics_file');
                $ics_stream = $this->ics_file->get_stream($appointment, $service, $provider, $customer);

                if ($send_customer === TRUE)
                {
                    $email->sendAppointmentDetails($appointment, $provider,
                        $service, $customer, $company_settings, $customer_title,
                        $customer_message, $customer_link, new Email($customer['email']), new Text($ics_stream));
                }

                $send_provider = filter_var($this->providers_model->get_setting('notifications', $provider['id']),
                    FILTER_VALIDATE_BOOLEAN);

                if ($send_provider === TRUE)
                {
                    $email->sendAppointmentDetails($appointment, $provider,
                        $service, $customer, $company_settings, $provider_title,
                        $provider_message, $provider_link, new Email($provider['email']), new Text($ics_stream));
                }
            }
            catch (Exception $exc)
            {
                log_message('error', $exc->getMessage());
                log_message('error', $exc->getTraceAsString());
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'appointment_id' => $appointment['id']
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'exceptions' => [exceptionToJavaScript($exc)]
                ]));
        }
    }

    /**
     * [AJAX] Get Unavailable Dates
     *
     * Get an array with the available dates of a specific provider, service and month of the year. Provide the
     * "provider_id", "service_id" and "selected_date" as GET parameters to the request. The "selected_date" parameter
     * must have the Y-m-d format.
     *
     * Outputs a JSON string with the unavailable dates. that are unavailable.
     *
     * @deprecated Since v1.3.0, this method will be replaced with a future release.
     */
    public function ajax_get_unavailable_dates()
    {

		$this->load->model('settings_model');

        try
        {
            $provider_id = $this->input->get('provider_id');
            $service_id = $this->input->get('service_id');
			$selected_date_string = $this->input->get('selected_date');

			// Create the selected timezone object.
			try {
				$selected_timezone = new DateTimeZone($this->input->get('timezone'));
			} catch(Exception $e) {
				$selected_timezone = new DateTimeZone('Etc/UTC');
			}

			// Create the server timezone object.
			$utc_timezone = new DateTimeZone('Etc/UTC');

			// Internationalize the selected date and delimit it in UTC.
			$selected_date = new DateTime($selected_date_string, $selected_timezone);
			$selected_date_start_utc = (clone $selected_date)->setTime(0, 0)->setTimezone($utc_timezone);
			$selected_date_end_utc = (clone $selected_date)->setTime(23, 59)->setTimezone($utc_timezone);

			// If the selected date is "today" (in the selected timezone) then adjust today's start date-time accordingly.
			$loc_now = (new DateTime())->setTimezone($selected_timezone);
			if ($loc_now->format('Y-m-d') == $selected_date->format('Y-m-d')) {
				$selected_date_start_utc = $loc_now->setTimezone($utc_timezone);
			}

            $number_of_days_in_month = (int)$selected_date->format('t');
            $unavailable_dates = [];
            $manage_mode = filter_var($this->input->get('manage_mode'), FILTER_VALIDATE_BOOLEAN);

            $exclude_appointments = ($_REQUEST['manage_mode'] === 'true')
                ? [$_REQUEST['appointment_id']]
                : [];

            $provider_list = ($provider_id === ANY_PROVIDER) ? $this->_search_providers_by_service($service_id) : [$provider_id] ;

            $this->load->model('providers_model');

            // Get the service record.
            $this->load->model('services_model');
            $service = $this->services_model->get_row($service_id);

            for ($i = 1; $i <= $number_of_days_in_month; $i++)
            {
				$current_date = DateTime::createFromFormat(
					'Y-m-d H:i',
					$selected_date->format('Y-m') . '-' . $i . ' ' . '00:00'
				);
				$current_date_local_start = DateTime::createFromFormat(
					'Y-m-d H:i',
					$selected_date->format('Y-m') . '-' . $i . ' ' . '00:00',
					$selected_timezone
				);
				$current_date_local_end = DateTime::createFromFormat(
					'Y-m-d H:i',
					$selected_date->format('Y-m') . '-' . $i . ' ' . '23:59',
					$selected_timezone
				);

				$previous_date = (clone $current_date)->modify('-1 day');
				$next_date = (clone $current_date)->modify('1 day');

				// Past dates become immediately unavailable.
				$todays_date_local = (new DateTime())->setTimezone($selected_timezone)->setTime(0,0,0,0);
				if ($current_date_local_start < $todays_date_local)
                {
                    $unavailable_dates[] = (clone $current_date)->format('Y-m-d');
                    continue;
                }

				// If the selected date is "today" (in the selected timezone) then adjust today's start date-time accordingly.
				$loc_now_plus = new DateTime();
				$loc_now_plus->modify(
						"+{$this->settings_model->get_setting('book_advance_timeout')} minutes"
					);
				if ($loc_now_plus > $current_date_local_start) {
					$current_date_local_start = $loc_now_plus;
				}

                // Finding at least one slot of availablity
                foreach ($provider_list as $curr_provider_id)
                {

                    // Get the provider record.
                    $curr_provider = $this->providers_model->get_row($curr_provider_id);

					// Get available hours on selected date (interpreted in UTC).
                    $empty_periods_current = $this->_get_provider_available_time_periods(
						$curr_provider_id,
						$current_date->format('Y-m-d'), $exclude_appointments
					);
					$available_hours_current = array_map(
						function($t) use($current_date) {
							return DateTime::createFromFormat('Y-m-d H:i',  $current_date->format('Y-m-d') . ' ' . $t);
						},
						$this->_calculate_available_hours(
							$empty_periods_current,
							$current_date->format('Y-m-d'),
							$service['duration'], $manage_mode, $service['availabilities_type']
						)
					);

					// Get available hours on next date (interpreted in UTC).
                    $empty_periods_next = $this->_get_provider_available_time_periods(
						$curr_provider_id,
						$next_date->format('Y-m-d'), $exclude_appointments
					);
					$available_hours_next = array_map(
						function($t) use($next_date) {
							return DateTime::createFromFormat('Y-m-d H:i',  $next_date->format('Y-m-d') . ' ' . $t);
						},
						$this->_calculate_available_hours(
							$empty_periods_next,
							$next_date->format('Y-m-d'),
							$service['duration'], $manage_mode, $service['availabilities_type']
						)
					);

					// Get available hours on previous date (interpreted in UTC).
                    $empty_periods_previous = $this->_get_provider_available_time_periods(
						$curr_provider_id,
						$previous_date->format('Y-m-d'), $exclude_appointments
					);
					$available_hours_previous = array_map(
						function($t) use($previous_date) {
							return DateTime::createFromFormat('Y-m-d H:i',  $previous_date->format('Y-m-d') . ' ' . $t);
						},
						$this->_calculate_available_hours(
							$empty_periods_previous,
							$previous_date->format('Y-m-d'),
							$service['duration'], $manage_mode, $service['availabilities_type']
						)
					);

					// Merge the available hours in the vicinity of the current date.
					$available_hours_merged = [];
					$c = 0;
					foreach ($available_hours_previous as $j => $v) {
						$available_hours_merged[$c] = clone $v;
						$c++;
					}
					foreach ($available_hours_current as $j => $v) {
						$available_hours_merged[$c] = clone $v;
						$c++;
					}
					foreach ($available_hours_next as $j => $v) {
						$available_hours_merged[$c] = clone $v;
						$c++;
					}

					// Filter out available hours not within the current date in the selected timezone.
					$available_hours = array_filter(
						$available_hours_merged,
						function($a) use($current_date_local_start, $current_date_local_end) {
							return ($a >= $current_date_local_start && $a <= $current_date_local_end)
							? true
							: false;
						}
					);

					if (! empty($available_hours)) break;

                    if ($service['attendants_number'] > 1)
                    {
                        $available_hours = $this->_get_multiple_attendants_hours($current_date->format('Y-m-d'), $service,
                            $curr_provider);
                        if (! empty($available_hours)) break;
                    }
                }

				// No availability among providers on the current date of the loop.
                if (empty($available_hours))
                {
                    $unavailable_dates[] = (clone $current_date)->format('Y-m-d');
                }
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($unavailable_dates));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'exceptions' => [exceptionToJavaScript($exc)]
                ]));
        }
    }

    /**
     * Check whether the provider is still available in the selected appointment date.
     *
     * Occasionally, two or more customers might select the same appointment date and time. This shouldn't be
     * allowed to happen; one of the two customers should eventually get the preferred date, while the other one should have
     * to select a different date. Use this method just before the customer confirms the appointment details. If the
     * selected date has been booked in the meantime, the customer must be prompted to select another time for the
     * appointment.
     *
     * @return bool Returns whether the selected datetime is still available.
     */
    protected function _check_datetime_availability()
    {
        $this->load->model('services_model');
        $this->load->model('appointments_model');

        $appointment = $_POST['post_data']['appointment'];

        $service_duration = $this->services_model->get_value('duration', $appointment['id_services']);

        $exclude_appointments = (isset($appointment['id'])) ? [$appointment['id']] : [];

        $attendants_number = $this->services_model->get_value('attendants_number', $appointment['id_services']);

        if ($attendants_number > 1)
        {
            // Exclude all the appointments that are currently registered.
            $exclude = $this->appointments_model->get_batch([
                'id_services' => $appointment['id_services'],
                'start_datetime' => $appointment['start_datetime']
            ]);

            if ( ! empty($exclude) && count($exclude) < $attendants_number)
            {
                foreach ($exclude as $entry)
                {
                    $exclude_appointments[] = $entry['id'];
                }
            }
        }

        if ($appointment['id_users_provider'] === ANY_PROVIDER)
        {
            $appointment['id_users_provider'] = $this->_search_any_provider($appointment['id_services'],
                date('Y-m-d', strtotime($appointment['start_datetime'])));
            $_POST['post_data']['appointment']['id_users_provider'] = $appointment['id_users_provider'];
            return TRUE; // The selected provider is always available.
        }

        $available_periods = $this->_get_provider_available_time_periods(
            $appointment['id_users_provider'],
            date('Y-m-d', strtotime($appointment['start_datetime'])),
            $exclude_appointments);

        $is_still_available = FALSE;

        foreach ($available_periods as $period)
        {
            $appt_start = new DateTime($appointment['start_datetime']);

            $appt_end = new DateTime($appointment['start_datetime']);
            $appt_end->add(new DateInterval('PT' . $service_duration . 'M'));

            $period_start = $period['start'];
            $period_end = $period['end'];

            if ($period_start <= $appt_start && $period_end >= $appt_end)
            {
                $is_still_available = TRUE;
                break;
            }
        }

        return $is_still_available;
    }

    /**
     * Get an array containing the free time periods (start - end) of a selected date.
     *
     * This method is very important because there are many cases where the system needs to know when a provider is
     * available for an appointment. This method will return an array that belongs to the selected date and contains
     * values that have the start and the end time of an available time period.
     *
     * @param int $provider_id Provider record ID.
     * @param int $service_id Service record ID.
     * @param string $selected_date Date to be checked (MySQL formatted string).
     * @param array $excluded_appointment_ids Array containing the IDs of the appointments that will not be taken into
     * consideration when the available time periods are calculated.
     *
     * @return array Returns an array with the available time periods of the provider.
     */
    protected function _get_provider_available_time_periods(
        $provider_id,
        $selected_date,
        $excluded_appointment_ids = []
    ) {
        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');

        // Get the service, provider's working plan and provider appointments.
        $working_plan = json_decode($this->providers_model->get_setting('working_plan', $provider_id), TRUE);

        $provider_appointments = $this->appointments_model->get_batch([
            'id_users_provider' => $provider_id,
        ]);

        // Sometimes it might be necessary to not take into account some appointment records in order to display what
        // the providers' available time periods would be without them.
        foreach ($excluded_appointment_ids as $excluded_appointment_id)
        {
            foreach ($provider_appointments as $index => $reserved)
            {
                if ($reserved['id'] == $excluded_appointment_id)
                {
                    unset($provider_appointments[$index]);
                }
            }
        }

        // Subtract breaks from work-day. The first split between the plan is due to a break (if any). Thereafter,
        // every reserved appointment is considered to be a taken space in the plan.
        $selected_date_working_plan = $working_plan[strtolower(date('l', strtotime($selected_date)))];

        $periods = [];

        if (isset($selected_date_working_plan['breaks']))	// This array should exist even if it is blank.
        {

            $periods[] = [
                'start' => new DateTime($selected_date . ' ' . $selected_date_working_plan['start']),
                'end' => new DateTime($selected_date . ' ' . $selected_date_working_plan['end'])
            ];

            $day_start = new DateTime($selected_date . ' ' . $selected_date_working_plan['start']);
			$day_end = new DateTime($selected_date . ' ' . $selected_date_working_plan['end']);

			// In principle, we must allow appointments starting on the selected date to cross over midnight and end on the following date.
			// If $day_end_time = '23:59'...
			if ($day_end->format('H:i') == "23:59") {

				// and the selected date does not end with a break...
				if (end($selected_date_working_plan['breaks'])['end'] != "23:59") {
					$next_date_working_plan = $working_plan[strtolower(date('l', (new DateTime($selected_date))->modify('1 day')->getTimestamp()))];

					// and the following date does not begin with a break or out-of-bounds hours,...
					if (isset($next_date_working_plan['breaks'][0]) and
						$next_date_working_plan['breaks'][0]['start'] != "00:00" and
						$next_date_working_plan['start'] == "00:00") {

							// then, for all intents and purposes, merge the two periods just before and just after midnight.
							$day_end = (new DateTime($selected_date))->modify('1 day')->modify($next_date_working_plan['breaks'][0]['start']);
							$periods[0]['end'] = new DateTime($day_end->format('Y-m-d') . ' ' . $next_date_working_plan['breaks'][0]['start']);
					}
				}
			}

            // Subtract breaks from work-day.
            foreach ($selected_date_working_plan['breaks'] as $index => $break)
            {
                $break_start = new DateTime($selected_date . ' ' . $break['start']);
                $break_end = new DateTime($selected_date . ' ' . $break['end']);

                if ($break_start < $day_start)
                {
                    $break_start = $day_start;
                }

                if ($break_end > $day_end)
                {
                    $break_end = $day_end;
                }

                if ($break_start >= $break_end)
                {
                    continue;
                }

                foreach ($periods as $key => $period)
                {
                    $period_start = $period['start'];
                    $period_end = $period['end'];

                    $remove_current_period = FALSE;

                    if ($break_start > $period_start && $break_start < $period_end && $break_end > $period_start)
                    {
                        $periods[] = [
                            'start' => $period_start,
                            'end' => $break_start
                        ];

                        $remove_current_period = TRUE;
                    }

                    if ($break_start < $period_end && $break_end > $period_start && $break_end < $period_end)
                    {
                        $periods[] = [
                            'start' => $break_end,
                            'end' => $period_end
                        ];

                        $remove_current_period = TRUE;
                    }

                    if ($break_start == $period_start && $break_end >= $period_end)
                    {
                        $remove_current_period = TRUE;
                    }

                    if ($remove_current_period)
                    {
                        unset($periods[$key]);
                    }
                }
            }
        }

		// A closing time of 23:59 is practically equivalent to midnight of next day (rounding up to avoid meaninglessly missed slots).
		foreach ($periods as $index => &$period) {
			if ($period['end']->format('H:i') == '23:59') {
				$period['end']->modify('+1 minute');
			}
		}

        // Partition the working time with the reserved appointments.
        foreach ($provider_appointments as $provider_appointment)
        {
            foreach ($periods as $index => &$period)
            {
                $appointment_start = new DateTime($provider_appointment['start_datetime']);
                $appointment_end = new DateTime($provider_appointment['end_datetime']);
                $period_start = $period['start'];
                $period_end = $period['end'];

                if ($appointment_start <= $period_start && $appointment_end <= $period_end && $appointment_end <= $period_start)
                {
					// The appointment does not belong in this time period, so we  will not change anything.
                }
                else
                {
                    if ($appointment_start <= $period_start && $appointment_end <= $period_end && $appointment_end >= $period_start)
                    {
                        // The appointment starts before the period and finishes somewhere inside. We will need to break
						// this period and leave the available part.

						if ($appointment_end->format('Y-m-d') == $selected_date) {
							// *** if appointment_end within date
							$period['start'] = $appointment_end;
						}
						else {
							// *** else unset
							unset($periods[$index]);
						}
                    }
                    else
                    {
                        if ($appointment_start >= $period_start && $appointment_end < $period_end)
                        {
                            // The appointment is inside the time period, so we will split the period into two new
                            // others.
                            unset($periods[$index]);

                            $periods[] = [
                                'start' => $period_start,
                                'end' => $appointment_start
                            ];

							// *** if appointment_end within date
							if ($appointment_end->format('Y-m-d') == $selected_date) {
								$periods[] = [
									'start' => $appointment_end,
									'end' => $period_end
								];
							}
                        }
                        else if ($appointment_start == $period_start && $appointment_end == $period_end)
                        {
                            unset($periods[$index]); // The whole period is blocked so remove it from the available periods array.
                        }
                        else
                        {
                            if ($appointment_start >= $period_start && $appointment_end >= $period_start && $appointment_start <= $period_end)
                            {
                                // The appointment starts in the period. We will need to remove
								// the time that is taken from the appointment.
                                $period['end'] = $appointment_start;
                            }
                            else
                            {
                                if ($appointment_start >= $period_start && $appointment_end >= $period_end && $appointment_start >= $period_end)
                                {
									// The appointment does not belong in the period so do not change anything.
                                }
                                else
                                {
                                    if ($appointment_start <= $period_start && $appointment_end >= $period_end && $appointment_start <= $period_end)
                                    {
                                        // The appointment is bigger than the period, so this period needs to be removed.
                                        unset($periods[$index]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_values($periods);
    }

    /**
     * Search for any provider that can handle the requested service.
     *
     * This method will return the database ID of the provider with the most available periods.
     *
     * @param int $service_id The requested service ID.
     * @param string $selected_date The date to be searched.
     *
     * @return int Returns the ID of the provider with the greatest number of availabilities for the selected service at the selected date.
     */
    protected function _search_any_provider($service_id, $selected_date)
    {
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $available_providers = $this->providers_model->get_available_providers();
        $service = $this->services_model->get_row($service_id);
        $provider_id = NULL;
        $max_hours_count = 0;

        foreach ($available_providers as $provider)
        {
            foreach ($provider['services'] as $provider_service_id)
            {
                if ($provider_service_id == $service_id)
                {
                    // Check if the provider is available for the requested date.
                    $empty_periods = $this->_get_provider_available_time_periods($provider['id'],
                        $selected_date);

                    $available_hours = $this->_calculate_available_hours($empty_periods, $selected_date,
                        $service['duration'], FALSE, $service['availabilities_type']);

                    if ($service['attendants_number'] > 1)
                    {
                        $available_hours = $this->_get_multiple_attendants_hours($selected_date, $service,
                            $provider);
                    }

                    if (count($available_hours) > $max_hours_count)
                    {
                        $provider_id = $provider['id'];
                        $max_hours_count = count($available_hours);
                    }
                }
            }
        }

        return $provider_id;
    }

    /**
     * Search for any provider that can handle the requested service.
     *
     * This method will return the database ID of the providers affected to the requested service.
     *
     * @param numeric $service_id The requested service ID.
     *
     * @return array Returns the ID of the provider that can provide the requested service.
     */
    protected function _search_providers_by_service($service_id)
    {
        $this->load->model('providers_model');
        $available_providers = $this->providers_model->get_available_providers();
        $provider_list = array();

        foreach ($available_providers as $provider)
        {
            foreach ($provider['services'] as $provider_service_id)
            {
                if ($provider_service_id === $service_id)
                {
                    // Check if the provider is affected to the selected service.
                    $provider_list[] = $provider['id'];
                }
            }
        }

        return $provider_list;
    }

    /**
     * Calculate the available appointment hours.
     *
     * Calculate the available appointment hours for the given date. The empty spaces
     * are broken down to 15 min and if the service fit in each quarter then a new
     * available hour is added to the "$available_hours" array.
     *
     * @param array $empty_periods Contains the empty periods as generated by the "_get_provider_available_time_periods"
     * method.
     * @param string $selected_date The selected date to be search (format )
     * @param int $service_duration The service duration is required for the hour calculation.
     * @param bool $manage_mode (optional) Whether we are currently on manage mode (editing an existing appointment).
     * @param string $availabilities_type Optional ('flexible'), the service availabilities type.
     *
     * @return array Returns an array with the available hours for the appointment.
     */
    protected function _calculate_available_hours(
        array $empty_periods,
        $selected_date,
        $service_duration,
        $manage_mode = FALSE,
        $availabilities_type = 'flexible'
    ) {
        $this->load->model('settings_model');

        $available_hours = [];

        foreach ($empty_periods as $period)
        {
            $start_hour = clone $period['start'];
            $end_hour = clone $period['end'];
            $interval = $availabilities_type === AVAILABILITIES_TYPE_FIXED ? (int)$service_duration : 15;

            $current_hour = clone $start_hour;
            $diff = $current_hour->diff($end_hour);

			// Accept slot if it starts on selected date and there is enough time on the calendar.
            while (($current_hour->format('Y-m-d') == $selected_date) and (($diff->d * 1440 + $diff->h * 60 + $diff->i) >= intval($service_duration)))
            {
                $available_hours[] = (clone $current_hour)->format('H:i');
                $current_hour->add(new DateInterval('PT' . $interval . 'M'));
                $diff = $current_hour->diff($end_hour);
            }
        }

        return $available_hours;
    }

    /**
     * Get multiple attendants hours.
     *
     * This method will add the extra appointment hours whenever a service accepts multiple attendants.
     *
     * @param string $selected_date The selected appointment date.
     * @param array $service Selected service data.
     * @param array $provider Selected provider data.
     *
     * @return array Returns the available hours array.
     */
    protected function _get_multiple_attendants_hours(
        $selected_date,
        $service,
        $provider
    ) {
        $this->load->model('appointments_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');

        $unavailabilities = $this->appointments_model->get_batch([
            'is_unavailable' => TRUE,
            'DATE(start_datetime)' => $selected_date,
            'id_users_provider' => $provider['id']
        ]);

        $working_plan = json_decode($provider['settings']['working_plan'], TRUE);
        $working_day = strtolower(date('l', strtotime($selected_date)));
        $working_hours = $working_plan[$working_day];

		// $periods contains DateTime pairs.
		////////////////////////////////////
        $periods = [
            [
                'start' => new DateTime($selected_date . ' ' . $working_hours['start']),
                'end' => new DateTime($selected_date . ' ' . $working_hours['end'])
            ]
		];

		// In principle, we must allow appointments starting on the selected date to cross over midnight and end on the following date.
		// If $day_end_time = '23:59'...
		if ($working_hours["end"] == "23:59") {

			// and the selected date does not end with a break...
			if (end($working_hours['breaks'])['end'] != "23:59") {
				$next_date_working_plan = $working_plan[strtolower(date('l', (new DateTime($selected_date))->modify('1 day')->getTimestamp()))];

				// and the following date does not begin with a break or an out-of-bounds moment,...
				if (isset($next_date_working_plan['breaks'][0])
					and $next_date_working_plan['breaks'][0]['start'] != "00:00"
					and	$next_date_working_plan['start'] == "00:00") {

						// then, for all intents and purposes, merge the two periods just before and just after midnight.
						$periods[0]["end"] = (new DateTime($selected_date))->modify('1 day')->modify($next_date_working_plan['breaks'][0]['start']);
				}
			}
		}

        $periods = $this->remove_breaks($selected_date, $periods, $working_hours['breaks']);
        $periods = $this->remove_unavailabilities($periods, $unavailabilities);

        $hours = [];

        $interval_value = $service['availabilities_type'] == AVAILABILITIES_TYPE_FIXED ? $service['duration'] : '15';
        $interval = new DateInterval('PT' . (int)$interval_value . 'M');
        $duration = new DateInterval('PT' . (int)$service['duration'] . 'M');

        foreach ($periods as $period)
        {
            $slot_start = clone $period['start'];
            $slot_end = clone $slot_start;
            $slot_end->add($duration);

            while ($slot_end <= $period['end'])
            {
                // Check reserved attendants for this time slot and see if current attendants fit.
                $appointment_attendants_number = $this->appointments_model->get_attendants_number_for_period($slot_start, $slot_end, $service['id']);

				// Add the current slot if there are available places *and* it starts on the selected date.
                if (($slot_start->format('Y-m-d') == $selected_date) and ($appointment_attendants_number < $service['attendants_number']))
                {
                    $hours[] = $slot_start->format('H:i');
                }

                $slot_start->add($interval);
                $slot_end->add($interval);
            }
        }

        return $hours;
    }

    /**
     * Remove breaks from available time periods.
     *
     * @param string $selected_date Selected data (Y-m-d format).
     * @param array $periods Time periods of the current date.
     * @param array $breaks Breaks array for the current date.
     *
     * @return array Returns the available time periods without the breaks.
     */
    public function remove_breaks($selected_date, $periods, $breaks)
    {
        if ( ! $breaks)
        {
            return $periods;
        }

        foreach ($breaks as $break)
        {
            $break_start = new DateTime($selected_date . ' ' . $break['start']);
            $break_end = new DateTime($selected_date . ' ' . $break['end']);

            foreach ($periods as &$period)
            {
                $period_start = $period['start'];
                $period_end = $period['end'];

                if ($break_start <= $period_start && $break_end >= $period_start && $break_end <= $period_end)
                {
                    // left
                    $period['start'] = $break_end;
                    continue;
                }

                if ($break_start >= $period_start && $break_start <= $period_end && $break_end >= $period_start && $break_end <= $period_end)
                {
                    // middle
                    $period['end'] = $break_start;
                    $periods[] = [
                        'start' => $break_end,
                        'end' => $period_end
                    ];
                    continue;
                }

                if ($break_start >= $period_start && $break_start <= $period_end && $break_end >= $period_end)
                {
                    // right
                    $period['end'] = $break_start;
                    continue;
                }

                if ($break_start <= $period_start && $break_end >= $period_end)
                {
                    // break contains period
                    $period['start'] = $break_end;
                    continue;
                }
            }
        }

        return $periods;
    }

    /**
     * Remove the unavailabilities from the available time periods of the selected date.
     *
     * @param array $periods Available time periods.
     * @param array $unavailabilities Unavailabilities of the current date.
     *
     * @return array Returns the available time periods without the unavailabilities.
     */
    public function remove_unavailabilities($periods, $unavailabilities)
    {
        foreach ($unavailabilities as $unavailability)
        {
            $unavailability_start = new DateTime($unavailability['start_datetime']);
            $unavailability_end = new DateTime($unavailability['end_datetime']);

            foreach ($periods as $index => $period)
            {
                $period_start = $period['start'];
                $period_end = $period['end'];

                if ($unavailability_start <= $period_start && $unavailability_end >= $period_start && $unavailability_end <= $period_end)
                {
					// left
					if ($unavailability_end->format('Y-m-d') == $period_start->format('Y-m-d')) {
                    	$period['start'] = $unavailability_end;
					} else {
						unset($periods[$index]);
					}
					continue;
                }

                if ($unavailability_start >= $period_start && $unavailability_start <= $period_end && $unavailability_end >= $period_start && $unavailability_end <= $period_end)
                {
                    // middle
                    $period['end'] = $unavailability_start;

					if ($unavailability_end->format('Y-m-d') == $period_start->format('Y-m-d')) {
						$periods[] = [
							'start' => $unavailability_end,
							'end' => $period_end
						];
					}
                    continue;
                }

                if ($unavailability_start >= $period_start && $unavailability_start <= $period_end && $unavailability_end >= $period_end)
                {
                    // right
                    $period['end'] = $unavailability_start;
                    continue;
                }

                if ($unavailability_start <= $period_start && $unavailability_end >= $period_end)
                {
                    // Unavaibility contains period
					// $period['start'] = $unavailability_end;  // UPSTREAM BUG?!
					unset($periods[$index]);
                    continue;
                }
            }
        }

        return $periods;
    }
}
