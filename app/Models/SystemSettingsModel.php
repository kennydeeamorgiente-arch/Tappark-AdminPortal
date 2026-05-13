<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemSettingsModel extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'setting_id';
    protected $allowedFields = [
        'setting_group',
        'setting_key',
        'setting_value',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getGroupSettings(string $group = 'application'): array
    {
        $rows = $this->where('setting_group', $group)
            ->where('is_active', 1)
            ->findAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    public function upsertGroupSettings(array $settings, string $group = 'application'): bool
    {
        foreach ($settings as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $payload = [
                'setting_group' => $group,
                'setting_key' => $key,
                'setting_value' => is_scalar($value) ? (string) $value : json_encode($value),
                'is_active' => 1,
            ];

            $existing = $this->where('setting_group', $group)
                ->where('setting_key', $key)
                ->first();

            if ($existing) {
                $this->update($existing['setting_id'], $payload);
            } else {
                $this->insert($payload);
            }
        }

        return true;
    }
}
