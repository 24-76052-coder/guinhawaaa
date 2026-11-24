<?php

declare(strict_types=1);

namespace Guinhawa\Controllers;

use Guinhawa\Auth;
use Guinhawa\Http;
use Guinhawa\MailSettings;

final class MailSettingsController
{
    public static function show(): never
    {
        Auth::requireAdmin();

        Http::json(['mail' => MailSettings::current()]);
    }

    public static function update(): never
    {
        $actor = Auth::requireAdmin();

        $mail = MailSettings::save(Http::body(), $actor);

        Http::json([
            'mail' => $mail,
            'message' => 'Email delivery settings saved.',
        ]);
    }

    public static function test(): never
    {
        $actor = Auth::requireAdmin();
        $body = Http::body();

        $result = MailSettings::sendTest((string) ($body['to'] ?? ''), $actor);

        Http::json(['test' => $result]);
    }
}
