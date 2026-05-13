<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVehicleTypeIdToVehicles extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('vehicle_type_id', 'vehicles')) {
            $this->forge->addColumn('vehicles', [
                'vehicle_type_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'vehicle_type',
                    'comment'    => 'Reference to vehicle_types.vehicle_type_id',
                ],
            ]);
        }

        $vehicleTypes = $this->db->table('vehicle_types')
            ->select('vehicle_type_id, vehicle_type_name')
            ->get()
            ->getResultArray();

        $typeMap = [];
        foreach ($vehicleTypes as $vehicleType) {
            $name = strtolower(trim((string) ($vehicleType['vehicle_type_name'] ?? '')));
            $id = (int) ($vehicleType['vehicle_type_id'] ?? 0);

            if ($name !== '' && $id > 0) {
                $typeMap[$name] = $id;
            }
        }

        $aliases = [
            'bike' => 'bicycle',
            'bikes' => 'bicycle',
            'bicycle' => 'bicycle',
            'bicycles' => 'bicycle',
            'bycicle' => 'bicycle',
            'bycycle' => 'bicycle',
            'motorbike' => 'motorcycle',
            'motor bike' => 'motorcycle',
            'motor-cycle' => 'motorcycle',
            'motor cycle' => 'motorcycle',
            'cars' => 'car',
        ];

        $vehicles = $this->db->table('vehicles')
            ->select('vehicle_id, vehicle_type, vehicle_type_id')
            ->get()
            ->getResultArray();

        foreach ($vehicles as $vehicle) {
            if (!empty($vehicle['vehicle_type_id'])) {
                continue;
            }

            $legacyType = strtolower(trim((string) ($vehicle['vehicle_type'] ?? '')));
            if ($legacyType === '') {
                continue;
            }

            $normalizedType = $aliases[$legacyType] ?? $legacyType;
            $mappedId = $typeMap[$normalizedType] ?? null;

            if ($mappedId) {
                $this->db->table('vehicles')
                    ->where('vehicle_id', $vehicle['vehicle_id'])
                    ->update(['vehicle_type_id' => $mappedId]);
            }
        }

        $this->db->query('ALTER TABLE `vehicles` ADD CONSTRAINT `fk_vehicles_vehicle_type` FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types` (`vehicle_type_id`) ON UPDATE CASCADE ON DELETE SET NULL');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `vehicles` DROP FOREIGN KEY `fk_vehicles_vehicle_type`');

        if ($this->db->fieldExists('vehicle_type_id', 'vehicles')) {
            $this->forge->dropColumn('vehicles', 'vehicle_type_id');
        }
    }
}
