<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class VehicleTypeDeductionRatesSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'vehicle_type_id' => 1, // Car
                'deduction_rate'  => 5.00,
                'is_active'       => 1,
            ],
            [
                'vehicle_type_id' => 2, // Motorcycle
                'deduction_rate'  => 3.00,
                'is_active'       => 1,
            ],
            [
                'vehicle_type_id' => 3, // Bicycle
                'deduction_rate'  => 3.00,
                'is_active'       => 1,
            ],
        ];

        // Using REPLACE INTO or ON DUPLICATE KEY UPDATE logic manually or via simple insert ignore
        // CI4 builder->insertBatch doesn't do "ON DUPLICATE" easily, so let's loop
        $db = \Config\Database::connect();
        $builder = $db->table('vehicle_type_deduction_rates');

        foreach ($data as $row) {
            $existing = $builder->where('vehicle_type_id', $row['vehicle_type_id'])->get()->getRow();
            if ($existing) {
                $builder->where('vehicle_type_id', $row['vehicle_type_id'])->update($row);
            } else {
                $builder->insert($row);
            }
        }
    }
}
