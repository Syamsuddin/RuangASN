<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // User Management
            'admin.users.view', 'admin.users.create', 'admin.users.edit',
            'admin.users.deactivate', 'admin.users.roles.assign',
            // Dashboard & Workspace
            'dashboard.view.own', 'workspace.view.own', 'workspace.settings.manage.own',
            // Organization
            'admin.organizations.view', 'admin.organizations.create', 'admin.organizations.edit',
            'admin.units.manage', 'organization.view.tree', 'organization.delegation.view',
            'organization.delegation.manage',
            // Task
            'task.view.own', 'task.view.team', 'task.view.subordinate', 'task.view.all',
            'task.create', 'task.edit.own', 'task.edit.any', 'task.assign',
            'task.start', 'task.submit', 'task.complete.own', 'task.review',
            'task.close', 'task.delete.own', 'task.delete.any',
            'task.evidence.add.own', 'task.evidence.view.all',
            // Meeting
            'meeting.view.own', 'meeting.view.all', 'meeting.create', 'meeting.edit.own',
            'meeting.host', 'meeting.secretary', 'meeting.minutes.create', 'meeting.minutes.approve',
            'meeting.action_item.create',
            // Notification
            'notification.view.own', 'notification.manage.own',
            // Audit
            'audit.view.own', 'audit.view.all',
            // Profile
            'profile.view.own', 'profile.edit.own', 'profile.mfa.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // --- Roles ---
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $adminPemda = Role::firstOrCreate(['name' => 'admin_pemda', 'guard_name' => 'web']);
        $adminPemda->syncPermissions([
            'admin.users.view', 'admin.users.create', 'admin.users.edit',
            'admin.users.deactivate', 'admin.users.roles.assign',
            'admin.organizations.view', 'admin.organizations.create', 'admin.organizations.edit',
            'admin.units.manage', 'organization.view.tree', 'organization.delegation.view',
            'organization.delegation.manage',
            'dashboard.view.own', 'workspace.view.own', 'workspace.settings.manage.own',
            'task.view.own', 'task.view.team', 'task.view.subordinate', 'task.view.all',
            'task.create', 'task.edit.own', 'task.edit.any', 'task.assign',
            'task.start', 'task.submit', 'task.complete.own', 'task.review', 'task.close',
            'task.delete.own', 'task.delete.any', 'task.evidence.add.own', 'task.evidence.view.all',
            'meeting.view.own', 'meeting.view.all', 'meeting.create', 'meeting.edit.own',
            'meeting.host', 'meeting.secretary', 'meeting.minutes.create', 'meeting.minutes.approve',
            'meeting.action_item.create',
            'notification.view.own', 'notification.manage.own',
            'audit.view.own', 'audit.view.all',
            'profile.view.own', 'profile.edit.own', 'profile.mfa.manage',
        ]);

        $kepalaOpd = Role::firstOrCreate(['name' => 'kepala_opd', 'guard_name' => 'web']);
        $kepalaOpd->syncPermissions([
            'dashboard.view.own', 'workspace.view.own', 'workspace.settings.manage.own',
            'organization.view.tree', 'organization.delegation.view', 'admin.units.manage',
            'task.view.own', 'task.view.team', 'task.view.subordinate', 'task.view.all',
            'task.create', 'task.edit.own', 'task.edit.any', 'task.assign',
            'task.start', 'task.submit', 'task.complete.own', 'task.review', 'task.close',
            'task.delete.own', 'task.evidence.add.own', 'task.evidence.view.all',
            'meeting.view.own', 'meeting.view.all', 'meeting.create', 'meeting.edit.own',
            'meeting.host', 'meeting.secretary', 'meeting.minutes.create', 'meeting.minutes.approve',
            'meeting.action_item.create',
            'notification.view.own', 'notification.manage.own',
            'audit.view.own',
            'profile.view.own', 'profile.edit.own', 'profile.mfa.manage',
        ]);

        $kepalaBidang = Role::firstOrCreate(['name' => 'kepala_bidang', 'guard_name' => 'web']);
        $kepalaBidang->syncPermissions([
            'dashboard.view.own', 'workspace.view.own', 'workspace.settings.manage.own',
            'organization.view.tree', 'organization.delegation.view',
            'task.view.own', 'task.view.team', 'task.view.subordinate', 'task.view.all',
            'task.create', 'task.edit.own', 'task.edit.any', 'task.assign',
            'task.start', 'task.submit', 'task.complete.own', 'task.review', 'task.close',
            'task.delete.own', 'task.evidence.add.own', 'task.evidence.view.all',
            'meeting.view.own', 'meeting.create', 'meeting.edit.own', 'meeting.host',
            'meeting.minutes.create', 'meeting.action_item.create',
            'notification.view.own', 'notification.manage.own',
            'audit.view.own',
            'profile.view.own', 'profile.edit.own', 'profile.mfa.manage',
        ]);

        $asn = Role::firstOrCreate(['name' => 'asn', 'guard_name' => 'web']);
        $asn->syncPermissions([
            'dashboard.view.own', 'workspace.view.own', 'workspace.settings.manage.own',
            'organization.view.tree',
            'task.view.own', 'task.view.team',
            'task.create', 'task.edit.own', 'task.start', 'task.submit', 'task.complete.own',
            'task.delete.own', 'task.evidence.add.own',
            'meeting.view.own',
            'notification.view.own', 'notification.manage.own',
            'profile.view.own', 'profile.edit.own', 'profile.mfa.manage',
        ]);
    }
}
