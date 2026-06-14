<?php
namespace App\Enums;

/**
 * External integration providers wired through the IntegrationManager.
 * Each case maps 1:1 to an IntegrationSettingsService credential `group`
 * and to a concrete IntegrationClient implementation.
 */
enum IntegrationProvider: string
{
    case SIASN           = 'siasn';
    case SRIKANDI        = 'srikandi';
    case SIPD            = 'sipd';
    case GOOGLE_CALENDAR = 'google_calendar';
    case WHATSAPP        = 'whatsapp';
    case SSO             = 'sso';

    /**
     * The IntegrationSettingsService credential group backing this provider.
     * SIPD reuses the generic siasn-style fields; google_calendar/sso/whatsapp
     * each have their own group in the settings schema.
     */
    public function settingsGroup(): string
    {
        return match ($this) {
            self::SIASN           => 'siasn',
            self::SRIKANDI        => 'srikandi',
            self::SIPD            => 'sipd',
            self::GOOGLE_CALENDAR => 'video',
            self::WHATSAPP        => 'whatsapp',
            self::SSO             => 'sso',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SIASN           => 'SIASN BKN',
            self::SRIKANDI        => 'SRIKANDI',
            self::SIPD            => 'SIPD',
            self::GOOGLE_CALENDAR => 'Google Calendar',
            self::WHATSAPP        => 'WhatsApp Business',
            self::SSO             => 'SSO Nasional',
        };
    }
}
