<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Shared column helpers for the Solar CRM migrations.
 */
abstract class BaseSchema extends Migration
{
    protected function id(): array
    {
        return ['id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true]];
    }

    protected function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'null' => $null];
    }

    protected function str(int $len = 191, bool $null = true): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
    }

    protected function text(): array
    {
        return ['type' => 'TEXT', 'null' => true];
    }

    protected function money(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0];
    }

    protected function qty(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0];
    }

    protected function date(bool $null = true): array
    {
        return ['type' => 'DATE', 'null' => $null];
    }

    protected function datetime(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    protected function bool(int $default = 1): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default];
    }

    /** created_by, created_at, updated_at (and deleted_at when $soft). */
    protected function stamps(bool $soft = false): array
    {
        $cols = [
            'created_by' => $this->fk(true),
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ];
        if ($soft) {
            $cols['deleted_at'] = $this->datetime();
        }

        return $cols;
    }

    protected function create(string $table, array $fields, array $keys = [], array $unique = []): void
    {
        $this->forge->addField($fields);
        $this->forge->addPrimaryKey('id');
        foreach ($keys as $k) {
            $this->forge->addKey($k);
        }
        foreach ($unique as $u) {
            $this->forge->addUniqueKey($u);
        }
        $this->forge->createTable($table, true);
    }
}
