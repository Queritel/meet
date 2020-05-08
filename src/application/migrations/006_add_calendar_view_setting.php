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

class Migration_Add_calendar_view_setting extends CI_Migration {
    public function up()
    {
        if ( ! $this->db->field_exists('calendar_view', 'ea_user_settings'))
        {
            $fields = [
                'calendar_view' => [
                    'type' => 'VARCHAR',
                    'constraint' => '32',
                    'default' => 'default'
                ]
            ];

            $this->dbforge->add_column('ea_user_settings', $fields);

            $this->db->update('ea_user_settings', ['calendar_view' => 'default']);
        }
    }

    public function down()
    {
        if ($this->db->field_exists('calendar_view', 'ea_user_settings'))
        {
            $this->dbforge->drop_column('ea_user_settings', 'calendar_view_calendar');
        }
    }
}
