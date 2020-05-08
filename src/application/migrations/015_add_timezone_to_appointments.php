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

class Migration_Add_timezone_to_appointments extends CI_Migration {
    public function up()
    {
        $this->load->model('appointments_model');

		if ( ! $this->db->field_exists('timezone', 'ea_appointments'))
			{
				$fields = [
					'timezone' => [
						'type' => 'VARCHAR',
						'constraint' => '64',
						'default' => 'UTC',
						'after' => 'end_datetime'
					]
				];

				$this->dbforge->add_column('ea_appointments', $fields);

				$this->db->update('ea_appointments', ['timezone' => 'UTC']);
			}
    }

    public function down()
    {
        $this->load->model('appointments_model');

        $this->appointments_model->remove_column('ea_appointments', 'timezone');
    }
}
