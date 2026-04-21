<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class VehicleTypes extends BaseController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * Get all vehicle types with rates
     */
    public function list()
    {
        $types = $this->db->table('vehicle_types')
            ->select('vehicle_types.vehicle_type_id, vehicle_types.vehicle_type_name, COALESCE(vtdr.deduction_rate, 0) as vehicle_type_deduction_rate')
            ->join('vehicle_type_deduction_rates as vtdr', 'vtdr.vehicle_type_id = vehicle_types.vehicle_type_id', 'left')
            ->orderBy('vehicle_types.vehicle_type_id', 'ASC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Update vehicle type deduction rate
     */
    public function update($id)
    {
        // Get JSON body
        $json = $this->request->getJSON();
        $rate = $json->rate ?? null;

        // Validation
        if (!is_numeric($rate) || $rate < 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Rate must be a positive number'
            ])->setStatusCode(400);
        }

        // Check if rate entry exists
        $table = $this->db->table('vehicle_type_deduction_rates');
        $exists = $table->where('vehicle_type_id', $id)->countAllResults();

        if ($exists > 0) {
            // Update existing
            $updated = $table->where('vehicle_type_id', $id)
                            ->update(['deduction_rate' => $rate]);
        } else {
            // Insert new
            $updated = $table->insert([
                'vehicle_type_id' => $id,
                'deduction_rate' => $rate,
                'is_active' => 1
            ]);
        }

        if ($updated) {
            // Log activity (assuming helper exists)
            if (function_exists('log_update')) {
                log_update('Vehicle Type Rate', $id, "Rate updated to $rate");
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Rate updated successfully'
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to update rate'
        ])->setStatusCode(500);
    }
}
