<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVehicleTypeDeductionRatesTable extends Migration
{
    public function up()
    {
        // 1. Create the new table
        $this->forge->addField([
            'rate_id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'vehicle_type_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'deduction_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0.00,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('rate_id');
        $this->forge->addUniqueKey('vehicle_type_id'); // Ensure one rate per vehicle type
        $this->forge->createTable('vehicle_type_deduction_rates', true);

        // Add Foreign Key
        // We use try-catch or raw SQL because addForeignKey with actions is tricky in some drivers via Forge
        $this->db->query('ALTER TABLE `vehicle_type_deduction_rates` ADD CONSTRAINT `fk_vehicle_type_rate_vehicle_type` FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types` (`vehicle_type_id`) ON DELETE CASCADE ON UPDATE CASCADE');

        // 2. Migrate data if column exists
        if ($this->db->fieldExists('vehicle_type_deduction_rate', 'vehicle_types')) {
            // Copy existing rates to new table
            $sql = "INSERT INTO vehicle_type_deduction_rates (vehicle_type_id, deduction_rate) 
                    SELECT vehicle_type_id, vehicle_type_deduction_rate FROM vehicle_types 
                    WHERE vehicle_type_deduction_rate > 0 
                    ON DUPLICATE KEY UPDATE deduction_rate = VALUES(deduction_rate)";
            $this->db->query($sql);

            // Drop the column
            $this->forge->dropColumn('vehicle_types', 'vehicle_type_deduction_rate');
        }
    }

    public function down()
    {
        // 1. Restore the column
        if (!$this->db->fieldExists('vehicle_type_deduction_rate', 'vehicle_types')) {
            $this->forge->addColumn('vehicle_types', [
                'vehicle_type_deduction_rate' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                    'after'      => 'vehicle_type_name',
                    'comment'    => 'Hourly deduction rate for this vehicle type'
                ],
            ]);

            // Restore data from table back to column
            $sql = "UPDATE vehicle_types vt 
                    JOIN vehicle_type_deduction_rates vtdr ON vt.vehicle_type_id = vtdr.vehicle_type_id 
                    SET vt.vehicle_type_deduction_rate = vtdr.deduction_rate";
            $this->db->query($sql);
        }

        // 2. Drop the table
        $this->forge->dropTable('vehicle_type_deduction_rates', true);
    }
}
