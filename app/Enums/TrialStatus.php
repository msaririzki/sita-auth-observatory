<?php

namespace App\Enums;

enum TrialStatus: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Authenticating = 'authenticating';
    case IdentityAccepted = 'identity_accepted';
    case TailnetJoined = 'tailnet_joined';
    case TargetReachable = 'target_reachable';
    case SshVerified = 'ssh_verified';
    case EvidenceCollected = 'evidence_collected';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
