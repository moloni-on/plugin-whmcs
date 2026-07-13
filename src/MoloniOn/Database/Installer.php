<?php

declare(strict_types=1);

namespace MoloniOn\Database;

use Illuminate\Database\Schema\Blueprint;
use Throwable;
use WHMCS\Database\Capsule;

/**
 * Creates and drops the module's custom database tables.
 *
 * Called from the WHMCS activate/deactivate hooks in moloni_on.php. Uses the
 * WHMCS-bundled Illuminate schema builder (Capsule).
 */
class Installer
{
    /** All custom tables owned by this module. */
    public const TABLES = [
        'mod_moloni_on_config',
        'mod_moloni_on_auth',
        'mod_moloni_on_orders',
        'mod_moloni_on_documents',
        'mod_moloni_on_logs',
    ];

    /**
     * Create all tables. Idempotent: existing tables are left untouched.
     *
     * @return array{status:string,description:string}
     */
    public static function install(): array
    {
        try {
            self::createConfigTable();
            self::createAuthTable();
            self::createOrdersTable();
            self::createDocumentsTable();
            self::createLogsTable();
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'description' => 'Moloni ON: failed to create database tables - ' . $e->getMessage(),
            ];
        }

        return [
            'status' => 'success',
            'description' => 'Moloni ON module activated and database tables created.',
        ];
    }

    /**
     * Drop all tables.
     *
     * @return array{status:string,description:string}
     */
    public static function uninstall(): array
    {
        try {
            foreach (self::TABLES as $table) {
                if (Capsule::schema()->hasTable($table)) {
                    Capsule::schema()->drop($table);
                }
            }
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'description' => 'Moloni ON: failed to drop database tables - ' . $e->getMessage(),
            ];
        }

        return [
            'status' => 'success',
            'description' => 'Moloni ON module deactivated and database tables removed.',
        ];
    }

    private static function createConfigTable(): void
    {
        if (Capsule::schema()->hasTable('mod_moloni_on_config')) {
            return;
        }

        Capsule::schema()->create('mod_moloni_on_config', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('setting_key')->unique();
            $table->longText('setting_value')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    private static function createAuthTable(): void
    {
        if (Capsule::schema()->hasTable('mod_moloni_on_auth')) {
            return;
        }

        // Single-row table holding the OAuth2 session and credentials.
        Capsule::schema()->create('mod_moloni_on_auth', static function (Blueprint $table): void {
            $table->increments('id');
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->integer('access_expire')->default(0);
            $table->integer('refresh_expire')->default(0);
            $table->integer('company_id')->default(0);
        });
    }

    private static function createOrdersTable(): void
    {
        if (Capsule::schema()->hasTable('mod_moloni_on_orders')) {
            return;
        }

        Capsule::schema()->create('mod_moloni_on_orders', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('order_id')->unique();
            $table->string('moloni_document_id')->nullable();
            $table->string('document_type', 50)->nullable();
            $table->enum('status', ['pending', 'synced', 'discarded', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->index('status');
        });
    }

    private static function createDocumentsTable(): void
    {
        if (Capsule::schema()->hasTable('mod_moloni_on_documents')) {
            return;
        }

        Capsule::schema()->create('mod_moloni_on_documents', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('order_id');
            $table->float('order_total');
            $table->integer('invoice_id');
            $table->date('invoice_date');
            $table->integer('invoice_status');
            $table->float('invoice_total');
            $table->float('value');
            $table->index('order_id');
            $table->index('invoice_id');
        });
    }

    private static function createLogsTable(): void
    {
        if (Capsule::schema()->hasTable('mod_moloni_on_logs')) {
            return;
        }

        Capsule::schema()->create('mod_moloni_on_logs', static function (Blueprint $table): void {
            $table->increments('id');
            $table->enum('level', ['debug', 'info', 'notice', 'warning', 'error', 'critical'])->default('info');
            $table->text('message')->nullable();
            $table->text('context')->nullable();
            $table->integer('order_id')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->index('level');
            $table->index('timestamp');
        });
    }
}
