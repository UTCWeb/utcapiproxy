<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class HealthController extends Controller
{
    public function show(): Response
    {
        // Keep this fast and dependency‑free.
        // If you want to add checks (DB, cache, etc.), do it here.

        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
}
