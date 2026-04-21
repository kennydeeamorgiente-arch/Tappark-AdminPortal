<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVehicleTypeDeductionRate extends Migration
{
    public function up()
    {
        $this->forge->addColumn('vehicle_types', [
            'vehicle_type_deduction_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0.00,
                'after'      => 'vehicle_type_name',
                'comment'    => 'Hourly deduction rate for this vehicle type'
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('vehicle_types', 'vehicle_type_deduction_rate');
    }
}
