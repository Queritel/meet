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

class Migration_Add_service_attendants_number extends CI_Migration {
    public function up()
    {
        if ( ! $this->db->field_exists('attendants_number', 'ea_services'))
        {
            $fields = [
                'attendants_number' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'default' => '1',
                    'after' => 'availabilities_type'
                ]
            ];

            $this->dbforge->add_column('ea_services', $fields);

            $this->db->update('ea_services', ['attendants_number' => '1']);
        }
    }

    public function down()
    {
        if ($this->db->field_exists('attendants_number', 'ea_services'))
        {
            $this->dbforge->drop_column('ea_services', 'attendants_number');
        }
    }
}
