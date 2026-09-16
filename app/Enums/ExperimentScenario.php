<?php

namespace App\Enums;

enum ExperimentScenario: string
{
    case Valid = 'valid';
    case WrongAudience = 'wrong_audience';
    case WrongBranch = 'wrong_branch';
    case WrongWorkflow = 'wrong_workflow';
    case WrongRepository = 'wrong_repository';
    case WrongEnvironment = 'wrong_environment';
    case RevokedCredential = 'revoked_credential';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Autentikasi valid',
            self::WrongAudience => 'Audience tidak sesuai',
            self::WrongBranch => 'Branch tidak diizinkan',
            self::WrongWorkflow => 'Workflow tidak diizinkan',
            self::WrongRepository => 'Repositori tidak sesuai',
            self::WrongEnvironment => 'Environment tidak sesuai',
            self::RevokedCredential => 'Kredensial OAuth dicabut',
        };
    }

    public function expectedDecision(): Decision
    {
        return $this === self::Valid ? Decision::Allow : Decision::Deny;
    }
}
