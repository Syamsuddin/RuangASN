<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Mail\NotificationMail;
use App\Models\AppNotification;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Services\Integrations\Clients\WhatsAppClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    public function send(
        User $recipient,
        NotificationType $type,
        string $title,
        string $body,
        array $data = [],
        NotificationChannel $channel = NotificationChannel::IN_APP,
    ): AppNotification {
        $notif = AppNotification::create([
            'id'                => (string) Str::ulid(),
            'organization_id'   => $recipient->organization_id,
            'recipient_id'      => $recipient->id,
            'notification_type' => $type->value,
            'title'             => $title,
            'body'              => $body,
            'data'              => $data,
            'channel'           => $channel->value,
            'status'            => 'sent',
            'delivered_at'      => now(),
        ]);

        // Broadcast real-time via Reverb
        broadcast(new \App\Events\NotificationSent($notif))->toOthers();

        // Queue email if recipient has email preference enabled
        $this->maybeSendEmail($recipient, $notif, $type);

        // Dispatch via WhatsApp if the recipient opted in AND the org's WhatsApp
        // integration is configured. Wrapped so a messaging failure never breaks
        // the in-app notification (which is already persisted above).
        $this->maybeSendWhatsApp($recipient, $notif, $type);

        return $notif;
    }

    public function notifyTaskAssigned(User $assignee, Task $task): void
    {
        $this->send(
            $assignee,
            NotificationType::TASK_ASSIGNED,
            'Tugas Baru Ditugaskan',
            "Anda mendapat tugas baru: {$task->title}",
            ['task_id' => $task->id, 'url' => "/tasks/{$task->id}"],
        );
    }

    public function notifyTaskDue(User $recipient, Task $task): void
    {
        $this->send(
            $recipient,
            NotificationType::TASK_DUE,
            'Tenggat Waktu Tugas Mendekat',
            "Tugas \"{$task->title}\" akan segera melewati tenggat waktu.",
            ['task_id' => $task->id, 'url' => "/tasks/{$task->id}"],
        );
    }

    public function notifyTaskOverdue(User $recipient, Task $task): void
    {
        $this->send(
            $recipient,
            NotificationType::TASK_OVERDUE,
            'Tugas Melewati Tenggat Waktu',
            "Tugas \"{$task->title}\" telah melewati tenggat waktu.",
            ['task_id' => $task->id, 'url' => "/tasks/{$task->id}"],
        );
    }

    public function notifyMeetingInvited(User $recipient, Meeting $meeting): void
    {
        $this->send(
            $recipient,
            NotificationType::MEETING_INVITE,
            'Undangan Meeting',
            "Anda diundang ke meeting: {$meeting->title}",
            ['meeting_id' => $meeting->id, 'url' => "/meetings/{$meeting->id}"],
        );
    }

    public function notifyDocumentApproval(User $recipient, Document $document): void
    {
        $this->send(
            $recipient,
            NotificationType::APPROVAL_REQUEST,
            'Permintaan Persetujuan Dokumen',
            "Dokumen \"{$document->title}\" memerlukan persetujuan Anda.",
            ['document_id' => $document->id, 'url' => "/documents/{$document->id}"],
        );
    }

    public function notifyReportStatus(User $recipient, Report $report, string $status): void
    {
        $this->send(
            $recipient,
            NotificationType::SYSTEM,
            'Status Laporan Diperbarui',
            "Status laporan \"{$report->title}\" diubah menjadi: {$status}.",
            ['report_id' => $report->id, 'status' => $status, 'url' => "/reports/{$report->id}"],
        );
    }

    private function maybeSendEmail(User $recipient, AppNotification $notif, NotificationType $type): void
    {
        try {
            $prefs = $recipient->prefs();

            if (! $prefs->email || $prefs->digest_frequency === 'off') {
                return;
            }

            if (! $this->typeEnabledInPrefs($prefs, $type)) {
                return;
            }

            Mail::to($recipient->email)->queue(new NotificationMail($notif));
        } catch (\Throwable $e) {
            Log::warning('NotificationService: failed to queue email', [
                'recipient_id'    => $recipient->id,
                'notification_id' => $notif->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    private function maybeSendWhatsApp(User $recipient, AppNotification $notif, NotificationType $type): void
    {
        try {
            $prefs = $recipient->prefs();

            if (! $prefs->whatsapp) {
                return;
            }

            if (! $this->typeEnabledInPrefs($prefs, $type)) {
                return;
            }

            $org = $recipient->organization;
            if ($org === null) {
                return;
            }

            /** @var WhatsAppClient $client */
            $client = app(WhatsAppClient::class);
            if (! $client->isConfigured($org)) {
                return;
            }

            $to = (string) ($recipient->phone ?? '');
            if (! $this->isPlausiblePhone($to)) {
                // Malformed / missing number → skip silently (no send, no throw).
                // Avoids creating a doomed IntegrationRun for unsendable input.
                return;
            }

            // Records its own IntegrationRun (success/failure) for observability.
            $client->send($org, $to, "{$notif->title}\n\n{$notif->body}");
        } catch (\Throwable $e) {
            Log::warning('NotificationService: failed to dispatch WhatsApp', [
                'recipient_id'    => $recipient->id,
                'notification_id' => $notif->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cheap plausibility gate for a WhatsApp recipient number. Accepts an
     * optional leading + followed by 8–15 digits (E.164 ceiling), after stripping
     * spaces, dashes and parentheses. Not a strict validator — just enough to
     * skip obviously malformed input before hitting the provider.
     */
    private function isPlausiblePhone(string $phone): bool
    {
        $normalized = (string) preg_replace('/[\s\-()]/', '', $phone);

        return preg_match('/^\+?[1-9]\d{7,14}$/', $normalized) === 1;
    }

    private function typeEnabledInPrefs(\App\Models\NotificationPreference $prefs, NotificationType $type): bool
    {
        return match ($type) {
            NotificationType::TASK_ASSIGNED, NotificationType::TASK_OVERDUE => $prefs->task_assigned,
            NotificationType::TASK_DUE                                       => $prefs->task_due,
            NotificationType::MEETING_INVITE, NotificationType::MEETING_REMINDER,
            NotificationType::MEETING_STARTED                                => $prefs->meeting_invited,
            NotificationType::APPROVAL_REQUEST, NotificationType::APPROVAL_DONE => $prefs->document_approval,
            NotificationType::REPORT_DUE                                     => $prefs->report_status,
            default                                                           => true,
        };
    }
}
