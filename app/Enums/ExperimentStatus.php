<?php

namespace App\Enums;

enum ExperimentStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
