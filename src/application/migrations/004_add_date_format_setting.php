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

class Migration_Add_date_format_setting extends CI_Migration {
    public function up()
    {
        $this->load->model('settings_model');

        try
        {
            $this->settings_model->get_setting('date_format');
        }
        catch (Exception $exception)
        {
            $this->settings_model->set_setting('date_format', DATE_FORMAT_DMY);
        }
    }

    public function down()
    {
        $this->load->model('settings_model');

        $this->settings_model->remove_setting('date_format');
    }
}
