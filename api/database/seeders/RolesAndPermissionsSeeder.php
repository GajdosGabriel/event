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

        // Role canal-* sa nepriraďujú ručne — odvodzuje ich CanalMembership
        // z členstva v kanáli (canal_user.role) a slúžia len ako hrubé sito pre
        // `permission:` middleware na routách. O tom, čo smie člen v konkrétnom
        // kanáli, rozhoduje až policy cez User::canInCanal().
        $ownerRole = Role::firstOrCreate([
            'name' => 'canal-owner',
            'guard_name' => 'web',
        ]);

        $editorRole = Role::firstOrCreate([
            'name' => 'canal-editor',
            'guard_name' => 'web',
        ]);

        $checkinRole = Role::firstOrCreate([
            'name' => 'canal-checkin',
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

        // Editor je „dramaturg" — robí obsah. Doteraz mal len právo pozerať,
        // takže pozvaný editor by v dashboarde nedokázal založiť podujatie.
        $editorRole->syncPermissions([
            'canal.view',
            'event.view',
            'event.comment',
            'event.create',
            'event.update',
            'venue.view',
            'venue.create',
            'venue.update',
            'organization.view',
            'user.view',
            'file.view',
            'file.create',
            'file.update',
            'file.delete',
            'ticket.view',
            'ticket.create',
            'ticket.update',
            'ticket.checkin',
        ]);

        // Brigádnik na vstupe: vidí podujatia a lístky, robí len check-in.
        $checkinRole->syncPermissions([
            'canal.view',
            'event.view',
            'ticket.view',
            'ticket.checkin',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
