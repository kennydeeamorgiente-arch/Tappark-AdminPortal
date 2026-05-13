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
     * Create a new vehicle type
     */
    public function create()
    {
        $json = $this->request->getJSON(true);
        $name = trim((string) ($json['vehicle_type_name'] ?? ''));
        $rate = $json['rate'] ?? null;

        if ($name === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Vehicle type name is required'
            ])->setStatusCode(400);
        }

        if (mb_strlen($name) > 100) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Vehicle type name is too long'
            ])->setStatusCode(400);
        }

        if ($rate !== null && $rate !== '' && (!is_numeric($rate) || $rate < 0)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Rate must be a positive number'
            ])->setStatusCode(400);
        }

        $existing = $this->db->table('vehicle_types')
            ->where('LOWER(vehicle_type_name)', mb_strtolower($name))
            ->countAllResults();

        if ($existing > 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Vehicle type already exists'
            ])->setStatusCode(409);
        }

        $this->db->transStart();

        $inserted = $this->db->table('vehicle_types')->insert([
            'vehicle_type_name' => $name
        ]);

        $newId = $this->db->insertID();

        if ($inserted && $newId && $rate !== null && $rate !== '') {
            $this->db->table('vehicle_type_deduction_rates')->insert([
                'vehicle_type_id' => $newId,
                'deduction_rate' => $rate,
                'is_active' => 1
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus()) {
            if (function_exists('log_create')) {
                log_create('Vehicle Type', $newId, $name);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Vehicle type created successfully',
                'data' => [
                    'vehicle_type_id' => $newId,
                    'vehicle_type_name' => $name,
                    'vehicle_type_deduction_rate' => (float) ($rate ?? 0)
                ]
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to create vehicle type'
        ])->setStatusCode(500);
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
