<?php

namespace App\Policies;

use App\Enums\DataClassification;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'document.view.public', 'document.view.internal',
            'document.view.confidential', 'document.view.restricted',
        ]);
    }

    public function view(User $user, Document $document): bool
    {
        if ($document->owner_id === $user->id) {
            return true;
        }

        $permMap = [
            DataClassification::PUBLIC->value       => 'document.view.public',
            DataClassification::INTERNAL->value     => 'document.view.internal',
            DataClassification::CONFIDENTIAL->value => 'document.view.confidential',
            DataClassification::RESTRICTED->value   => 'document.view.restricted',
        ];

        $level = $document->data_classification->value;

        $required = $permMap[$level];

        return $user->hasPermissionTo($required);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('document.create');
    }

    public function update(User $user, Document $document): bool
    {
        $inEditableStatus = in_array($document->status->value, ['draft', 'rejected'], true);

        if ($user->hasPermissionTo('document.edit.own') && $document->owner_id === $user->id && $inEditableStatus) {
            return true;
        }

        return $user->hasAnyPermission(['admin.organizations.edit', 'task.edit.any']) && $inEditableStatus;
    }

    public function submit(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('document.submit') && $document->owner_id === $user->id;
    }

    public function publish(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('document.submit')
            && $document->status->value === 'approved'
            && $document->owner_id === $user->id;
    }

    public function approve(User $user, Document $document): bool
    {
        return $user->hasPermissionTo('document.approve');
    }

    public function download(User $user, Document $document): bool
    {
        $level = $document->data_classification->value;

        if ($level >= DataClassification::CONFIDENTIAL->value) {
            return $user->hasPermissionTo('document.download.confidential');
        }

        return $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->owner_id === $user->id && $document->status->value === 'draft';
    }
}
