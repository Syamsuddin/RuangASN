<?php
namespace App\Providers;

use App\Models\Organization;
use App\Models\Task;
use App\Policies\OrganizationPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Task::class         => TaskPolicy::class,
        Organization::class => OrganizationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
