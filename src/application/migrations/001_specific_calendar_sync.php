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

class Migration_Specific_calendar_sync extends CI_Migration {

    public function up()
    {
        if ( ! $this->db->field_exists('google_calendar', 'ea_user_settings'))
        {
            $fields = [
                'google_calendar' => [
                    'type' => 'VARCHAR',
                    'constraint' => '128',
                    'null' => TRUE
                ]
            ];
            $this->dbforge->add_column('ea_user_settings', $fields);
        }
    }

    public function down()
    {
        if ($this->db->field_exists('google_calendar', 'ea_user_settings'))
        {
            $this->dbforge->drop_column('ea_user_settings', 'google_calendar');
        }
    }
}
