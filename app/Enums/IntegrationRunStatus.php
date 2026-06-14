<?php
namespace App\Enums;

/**
 * Lifecycle status of one IntegrationRun (a sync or webhook delivery).
 * PARTIAL means some items processed and some failed.
 */
enum IntegrationRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case PARTIAL = 'partial';
    case FAILED  = 'failed';
}
