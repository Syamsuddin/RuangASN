<?php
namespace App\Providers;

use App\Models\AiConversation;
use App\Models\CalendarEvent;
use App\Models\Document;
use App\Models\KnowledgeArticle;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\Report;
use App\Models\SkpPlan;
use App\Models\Task;
use App\Policies\AiConversationPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\KnowledgeArticlePolicy;
use App\Policies\MeetingPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ReportPolicy;
use App\Policies\SkpPlanPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Task::class             => TaskPolicy::class,
        Organization::class     => OrganizationPolicy::class,
        Meeting::class          => MeetingPolicy::class,
        Document::class         => DocumentPolicy::class,
        CalendarEvent::class    => CalendarEventPolicy::class,
        Report::class           => ReportPolicy::class,
        KnowledgeArticle::class => KnowledgeArticlePolicy::class,
        SkpPlan::class          => SkpPlanPolicy::class,
        AiConversation::class   => AiConversationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
