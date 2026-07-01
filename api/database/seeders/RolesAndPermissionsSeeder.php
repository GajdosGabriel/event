<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed application roles and permissions for Spatie Laravel Permission.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'canal.view',
            'canal.update',
            'canal.delete',
            'event.create',
            'event.update',
            'event.delete',
            'event.view',
            'event.comment',
            'venue.view',
            'venue.create',
            'venue.update',
            'venue.delete',
            'organization.view',
            'organization.create',
            'organization.update',
            'organization.delete',
            'user.view',
            'user.update',
            'user.delete',
            'file.view',
            'file.create',
            'file.update',
            'file.delete',
            'ticket.view',
            'ticket.create',
            'ticket.update',
            'ticket.checkin',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $ownerRole = Role::firstOrCreate([
            'name' => 'canal-owner',
            'guard_name' => 'web',
        ]);

        $editorRole = Role::firstOrCreate([
            'name' => 'canal-editor',
            'guard_name' => 'web',
        ]);

        $superAdminRole->syncPermissions(Permission::query()->pluck('name')->all());

        $ownerRole->syncPermissions([
            'canal.view',
            'canal.update',
            'canal.delete',
            'event.view',
            'event.create',
            'event.update',
            'event.delete',
            'venue.view',
            'venue.create',
            'venue.update',
            'venue.delete',
            'organization.view',
            'organization.create',
            'organization.update',
            'organization.delete',
            'user.view',
            'user.update',
            'user.delete',
            'file.view',
            'file.create',
            'file.update',
            'file.delete',
            'ticket.view',
            'ticket.create',
            'ticket.update',
            'ticket.checkin',
        ]);

        $editorRole->syncPermissions([
            'event.view',
            'event.comment',
            'venue.view',
            'organization.view',
            'user.view',
            'file.view',
            'ticket.view',
            'ticket.checkin',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
