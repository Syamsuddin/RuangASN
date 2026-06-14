<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AiController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExecutiveController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- Guest ---
Route::middleware('guest')->group(function () {
    Route::get('/', fn() => redirect('/login'));
    Route::get('/login', fn() => Inertia::render('Auth/Login'))->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::post('/login/mfa', [LoginController::class, 'mfaVerify'])->name('login.mfa');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// --- Authenticated ---
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // ── Settings ──
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::patch('/profile', [SettingsController::class, 'updateProfile'])->name('profile');
        Route::post('/avatar', [SettingsController::class, 'updateAvatar'])->name('avatar');
        Route::patch('/password', [SettingsController::class, 'updatePassword'])->name('password');
        Route::post('/mfa/setup', [SettingsController::class, 'mfaSetup'])->name('mfa.setup');
        Route::post('/mfa/enable', [SettingsController::class, 'mfaEnable'])->name('mfa.enable');
        Route::post('/mfa/disable', [SettingsController::class, 'mfaDisable'])->name('mfa.disable');
        Route::delete('/sessions/{tokenId}', [SettingsController::class, 'revokeSession'])->name('sessions.revoke');
    });

    // ── Task Management (Inertia web) ──
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/',                                          [TaskController::class, 'index'])->name('index');
        Route::post('/',                                         [TaskController::class, 'store'])->name('store');
        Route::get('/{task}',                                    [TaskController::class, 'show'])->name('show');
        Route::patch('/{task}',                                  [TaskController::class, 'update'])->name('update');
        Route::post('/{task}/transition',                        [TaskController::class, 'transition'])->name('transition');
        Route::post('/{task}/comments',                          [TaskController::class, 'addComment'])->name('comments.store');
        Route::post('/{task}/checklists',                        [TaskController::class, 'addChecklist'])->name('checklists.store');
        Route::patch('/checklists/{checklist}/toggle',           [TaskController::class, 'toggleChecklist'])->name('checklists.toggle');
        Route::delete('/checklists/{checklist}',                 [TaskController::class, 'deleteChecklist'])->name('checklists.destroy');
        Route::post('/{task}/evidences',                         [TaskController::class, 'addEvidence'])->name('evidences.store');
        Route::delete('/evidences/{evidence}',                   [TaskController::class, 'deleteEvidence'])->name('evidences.destroy');
    });

    // ── Meeting Workspace (Inertia web) ──
    Route::prefix('meetings')->name('meetings.')->group(function () {
        Route::get('/',                                                    [MeetingController::class, 'index'])->name('index');
        Route::post('/',                                                   [MeetingController::class, 'store'])->name('store');
        Route::get('/{meeting}',                                           [MeetingController::class, 'show'])->name('show');
        Route::patch('/{meeting}',                                         [MeetingController::class, 'update'])->name('update');
        Route::delete('/{meeting}',                                        [MeetingController::class, 'destroy'])->name('destroy');
        Route::post('/{meeting}/transition',                               [MeetingController::class, 'transition'])->name('transition');
        Route::post('/{meeting}/participants',                             [MeetingController::class, 'addParticipant'])->name('participants.store');
        Route::patch('/participants/{participant}/attendance',             [MeetingController::class, 'recordAttendance'])->name('participants.attendance');
        Route::post('/{meeting}/agenda',                                   [MeetingController::class, 'addAgendaItem'])->name('agenda.store');
        Route::post('/{meeting}/decisions',                                [MeetingController::class, 'addDecision'])->name('decisions.store');
        Route::post('/{meeting}/action-items',                             [MeetingController::class, 'addActionItem'])->name('action_items.store');
        Route::post('/{meeting}/minutes',                                  [MeetingController::class, 'upsertMinutes'])->name('minutes.upsert');
        Route::post('/{meeting}/minutes/ai-draft',                         [MeetingController::class, 'generateMinutesDraft'])->name('minutes.ai_draft');
        Route::post('/minutes/{minutes}/approve',                          [MeetingController::class, 'approveMinutes'])->name('minutes.approve');
        Route::get('/{meeting}/checkin-qr',                               [MeetingController::class, 'checkInQr'])->name('checkin_qr');
        Route::get('/{meeting}/checkin',                                  [MeetingController::class, 'checkIn'])->name('checkin')->middleware('signed');
    });

    // ── Calendar (Inertia web) ──
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/',           [CalendarController::class, 'index'])->name('index');
        Route::get('/feed',       [CalendarController::class, 'feed'])->name('feed');
        Route::post('/',          [CalendarController::class, 'store'])->name('store');
        Route::patch('/{event}',  [CalendarController::class, 'update'])->name('update');
        Route::delete('/{event}', [CalendarController::class, 'destroy'])->name('destroy');
    });

    // ── Document Workspace (Inertia web) ──
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/',                                              [DocumentController::class, 'index'])->name('index');
        Route::post('/',                                             [DocumentController::class, 'store'])->name('store');
        // Approval queue — must be before /{document} wildcard
        Route::get('/approval-queue',                                [DocumentController::class, 'approvalQueue'])->name('approval_queue');
        Route::post('/approvals/{approval}/approve',                 [DocumentController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{approval}/reject',                  [DocumentController::class, 'reject'])->name('approvals.reject');
        Route::get('/{document}',                                    [DocumentController::class, 'show'])->name('show');
        Route::get('/{document}/download',                           [DocumentController::class, 'download'])->name('download');
        // Signed download (short TTL, redirected from show)
        Route::get('/{document}/download/signed',                    [DocumentController::class, 'download'])->name('download.signed')->middleware('signed');
        // Signed inline stream for in-app viewer
        Route::get('/{document}/stream',                             [DocumentController::class, 'stream'])->name('stream')->middleware('signed');
        Route::patch('/{document}',                                  [DocumentController::class, 'update'])->name('update');
        Route::delete('/{document}',                                 [DocumentController::class, 'destroy'])->name('destroy');
        Route::post('/{document}/submit',                            [DocumentController::class, 'submit'])->name('submit');
        Route::post('/{document}/publish',                           [DocumentController::class, 'publish'])->name('publish');
        Route::post('/{document}/archive',                           [DocumentController::class, 'archive'])->name('archive');
        Route::post('/{document}/versions',                          [DocumentController::class, 'createVersion'])->name('versions.store');
    });

    // ── Report (Inertia web) ──
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',                        [ReportController::class, 'index'])->name('index');
        Route::post('/',                       [ReportController::class, 'store'])->name('store');
        Route::get('/{report}',                [ReportController::class, 'show'])->name('show');
        Route::patch('/{report}',              [ReportController::class, 'update'])->name('update');
        Route::delete('/{report}',             [ReportController::class, 'destroy'])->name('destroy');
        Route::post('/{report}/submit',        [ReportController::class, 'submit'])->name('submit');
        Route::post('/{report}/transition',    [ReportController::class, 'transition'])->name('transition');
        Route::post('/{report}/publish',       [ReportController::class, 'publish'])->name('publish');
        Route::post('/{report}/ai-draft',      [ReportController::class, 'generateAiDraft'])->name('ai_draft');
    });

    // ── Project Workspace (Inertia web) ──
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/',                                  [ProjectController::class, 'index'])->name('index');
        Route::post('/',                                 [ProjectController::class, 'store'])->name('store');
        // Static milestone/risk routes before the /{project} wildcard.
        Route::patch('/milestones/{milestone}',          [ProjectController::class, 'updateMilestone'])->name('milestones.update');
        Route::post('/milestones/{milestone}/complete',  [ProjectController::class, 'completeMilestone'])->name('milestones.complete');
        Route::delete('/milestones/{milestone}',         [ProjectController::class, 'deleteMilestone'])->name('milestones.destroy');
        Route::patch('/risks/{risk}',                    [ProjectController::class, 'updateRisk'])->name('risks.update');
        Route::post('/risks/{risk}/close',               [ProjectController::class, 'closeRisk'])->name('risks.close');
        Route::get('/{project}',                         [ProjectController::class, 'show'])->name('show');
        Route::patch('/{project}',                       [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}',                      [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/{project}/transition',             [ProjectController::class, 'transition'])->name('transition');
        Route::post('/{project}/close',                  [ProjectController::class, 'close'])->name('close');
        Route::post('/{project}/members',                [ProjectController::class, 'addMember'])->name('members.add');
        Route::delete('/{project}/members/{member}',     [ProjectController::class, 'removeMember'])->name('members.remove');
        Route::post('/{project}/milestones',             [ProjectController::class, 'addMilestone'])->name('milestones.add');
        Route::post('/{project}/risks',                  [ProjectController::class, 'addRisk'])->name('risks.add');
    });

    // ── Knowledge Base (Inertia web) ──
    Route::prefix('knowledge')->name('knowledge.')->group(function () {
        Route::get('/', [KnowledgeController::class, 'index'])->name('index');
        Route::get('/create', [KnowledgeController::class, 'create'])->name('create');
        Route::post('/', [KnowledgeController::class, 'store'])->name('store');
        Route::post('/categories', [KnowledgeController::class, 'storeCategory'])->name('categories.store');
        Route::patch('/categories/{category}', [KnowledgeController::class, 'updateCategory'])->name('categories.update');
        Route::get('/{article}', [KnowledgeController::class, 'show'])->name('show');
        Route::get('/{article}/edit', [KnowledgeController::class, 'edit'])->name('edit');
        Route::patch('/{article}', [KnowledgeController::class, 'update'])->name('update');
        Route::delete('/{article}', [KnowledgeController::class, 'destroy'])->name('destroy');
        Route::post('/{article}/transition', [KnowledgeController::class, 'transition'])->name('transition');
        Route::post('/{article}/publish', [KnowledgeController::class, 'publish'])->name('publish');
        Route::post('/{article}/archive', [KnowledgeController::class, 'archive'])->name('archive');
        Route::post('/{article}/versions', [KnowledgeController::class, 'createVersion'])->name('versions.store');
        Route::post('/{article}/helpful', [KnowledgeController::class, 'markHelpful'])->name('helpful');
    });

    // ── Performance / SKP Workspace ──
    Route::prefix('performance')->name('performance.')->group(function () {
        Route::get('/', [PerformanceController::class, 'index'])->name('index');
        Route::get('/analytics', [PerformanceController::class, 'analytics'])->name('analytics');
        Route::post('/periods', [PerformanceController::class, 'storePeriod'])->name('periods.store');
        Route::post('/plans', [PerformanceController::class, 'storePlan'])->name('plans.store');
        Route::get('/plans/{plan}', [PerformanceController::class, 'show'])->name('show');
        Route::patch('/plans/{plan}', [PerformanceController::class, 'updatePlan'])->name('plans.update');
        Route::post('/plans/{plan}/submit', [PerformanceController::class, 'submit'])->name('submit');
        Route::post('/plans/{plan}/approve', [PerformanceController::class, 'approve'])->name('approve');
        Route::post('/plans/{plan}/transition', [PerformanceController::class, 'transition'])->name('transition');
        Route::post('/plans/{plan}/evaluate', [PerformanceController::class, 'evaluate'])->name('evaluate');
        Route::delete('/plans/{plan}', [PerformanceController::class, 'destroy'])->name('plans.destroy');
        Route::post('/plans/{plan}/indicators', [PerformanceController::class, 'addIndicator'])->name('indicators.store');
        Route::patch('/indicators/{indicator}', [PerformanceController::class, 'updateIndicator'])->name('indicators.update');
        Route::delete('/indicators/{indicator}', [PerformanceController::class, 'deleteIndicator'])->name('indicators.destroy');
        Route::post('/indicators/{indicator}/realizations', [PerformanceController::class, 'addRealization'])->name('realizations.store');
    });

    // ── Executive Dashboard (Bupati / Kepala OPD; analytics.view.opd/pemda) ──
    Route::get('/executive', [ExecutiveController::class, 'index'])->name('executive.index');
    Route::post('/executive/ai-brief', [ExecutiveController::class, 'aiBrief'])->name('executive.ai_brief');

    // ── Admin ──
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/deactivate', [Admin\UserController::class, 'deactivate'])->name('users.deactivate');

        Route::get('/organizations', [Admin\OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('/organizations', [Admin\OrganizationController::class, 'store'])->name('organizations.store');
        Route::patch('/organizations/{organization}', [Admin\OrganizationController::class, 'update'])->name('organizations.update');

        Route::get('/teams', [Admin\TeamController::class, 'index'])->name('teams.index');
        Route::get('/teams/{team}', [Admin\TeamController::class, 'show'])->name('teams.show');
        Route::post('/teams', [Admin\TeamController::class, 'store'])->name('teams.store');
        Route::patch('/teams/{team}', [Admin\TeamController::class, 'update'])->name('teams.update');
        Route::post('/teams/{team}/members', [Admin\TeamController::class, 'addMember'])->name('teams.members.add');
        Route::delete('/teams/{team}/members/{member}', [Admin\TeamController::class, 'removeMember'])->name('teams.members.remove');

        Route::get('/delegations', [Admin\DelegationController::class, 'index'])->name('delegations.index');
        Route::post('/delegations', [Admin\DelegationController::class, 'store'])->name('delegations.store');
        Route::patch('/delegations/{delegation}', [Admin\DelegationController::class, 'update'])->name('delegations.update');
        Route::patch('/delegations/{delegation}/revoke', [Admin\DelegationController::class, 'revoke'])->name('delegations.revoke');

        Route::get('/audit', [Admin\AuditController::class, 'index'])->name('audit.index');

        Route::get('/integrations', [Admin\IntegrationController::class, 'index'])->name('integrations.index');
        Route::patch('/integrations', [Admin\IntegrationController::class, 'update'])->name('integrations.update');
        Route::get('/integrations/monitor', [Admin\IntegrationMonitorController::class, 'monitor'])->name('integrations.monitor');
        Route::post('/integrations/run', [Admin\IntegrationMonitorController::class, 'run'])->name('integrations.run');
        Route::post('/integrations/test', [Admin\IntegrationMonitorController::class, 'testConnection'])->name('integrations.test');
    });

    // ── Search ──
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/search/quick', [SearchController::class, 'quick'])->name('search.quick');

    // ── Notifications (Inertia web) ──
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',                            [NotificationController::class, 'index'])->name('index');
        Route::get('/preferences',                 [NotificationController::class, 'preferences'])->name('preferences');
        Route::patch('/preferences',               [NotificationController::class, 'updatePreferences'])->name('preferences.update');
        Route::patch('/read-all',                  [NotificationController::class, 'markAllRead'])->name('read_all');
        Route::patch('/{notification}/read',       [NotificationController::class, 'markRead'])->name('read');
        Route::delete('/{notification}',           [NotificationController::class, 'destroy'])->name('destroy');
    });

    // ── AI Foundation (Phase 3) ──
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('/',                            [AiController::class, 'index'])->name('index');
        Route::get('/conversations',               [AiController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{conversation}', [AiController::class, 'show'])->name('show');
        Route::post('/send',                       [AiController::class, 'send'])->name('send');
        Route::post('/messages/{message}/confirm', [AiController::class, 'confirmAction'])->name('confirm');
        Route::post('/messages/{message}/reject',  [AiController::class, 'rejectAction'])->name('reject');
        Route::get('/memories',                    [AiController::class, 'memories'])->name('memories');
        Route::patch('/memories/{memory}',         [AiController::class, 'updateMemory'])->name('memories.update');
    });

    // ── Chat (DM + channels, realtime via Reverb) ──
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/',                                       [ChatController::class, 'index'])->name('index');
        Route::post('/channels',                              [ChatController::class, 'storeChannel'])->name('channels.store');
        Route::post('/dm',                                    [ChatController::class, 'startDm'])->name('dm.start');
        Route::get('/channels/{channel}',                     [ChatController::class, 'show'])->name('show');
        Route::get('/channels/{channel}/messages',            [ChatController::class, 'messages'])->name('messages');
        Route::post('/channels/{channel}/messages',           [ChatController::class, 'sendMessage'])->name('messages.store');
        Route::post('/channels/{channel}/read',               [ChatController::class, 'markRead'])->name('read');
        Route::post('/channels/{channel}/members',            [ChatController::class, 'addMember'])->name('members.add');
        Route::delete('/channels/{channel}/members/{member}', [ChatController::class, 'removeMember'])->name('members.remove');
        Route::post('/channels/{channel}/archive',            [ChatController::class, 'archive'])->name('archive');
        Route::patch('/messages/{message}',                   [ChatController::class, 'editMessage'])->name('messages.update');
        Route::delete('/messages/{message}',                  [ChatController::class, 'deleteMessage'])->name('messages.destroy');
        Route::post('/messages/{message}/react',              [ChatController::class, 'react'])->name('messages.react');
    });
});
