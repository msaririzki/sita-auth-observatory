<?php

namespace App\Enums;

enum AuthenticationProfile: string
{
    case OAuthStatic = 'oauth_static';
    case WifBasic = 'wif_basic';
    case WifMultiClaim = 'wif_multi_claim';

    public function label(): string
    {
        return match ($this) {
            self::OAuthStatic => 'OAuth statis',
            self::WifBasic => 'WIF dasar',
            self::WifMultiClaim => 'WIF multi-klaim',
        };
    }
}
