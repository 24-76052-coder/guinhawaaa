<?php

declare(strict_types=1);

namespace Guinhawa\Controllers;

use Guinhawa\Auth;
use Guinhawa\Http;
use Guinhawa\StateRepository;

final class StateController
{
    public static function index(): never
    {
        $user = Auth::requireUser();
        Http::json(['state' => StateRepository::snapshot($user)]);
    }
}
