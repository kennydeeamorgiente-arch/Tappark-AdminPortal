<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSystemSettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'setting_id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'setting_group' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'application',
            ],
            'setting_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'setting_value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type'    => 'TIMESTAMP',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'    => 'TIMESTAMP',
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('setting_id');
        $this->forge->addUniqueKey(['setting_group', 'setting_key']);
        $this->forge->createTable('system_settings', true);

        $defaults = [
            ['setting_group' => 'application', 'setting_key' => 'app_name', 'setting_value' => 'TapPark Admin'],
            ['setting_group' => 'application', 'setting_key' => 'timezone', 'setting_value' => 'Asia/Manila'],
            ['setting_group' => 'application', 'setting_key' => 'session_timeout', 'setting_value' => '60'],
            ['setting_group' => 'application', 'setting_key' => 'records_per_page', 'setting_value' => '25'],
        ];

        $this->db->table('system_settings')->insertBatch($defaults);
    }

    public function down()
    {
        $this->forge->dropTable('system_settings', true);
    }
}
