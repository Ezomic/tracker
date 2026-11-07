<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Integrations;
use App\Support\Cast;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(Request $request, Integrations $integrations): Response
    {
        $operator = config('app.operator_name');

        return Inertia::render('Landing', [
            'operatorName' => Cast::string(is_string($operator) && filled($operator) ? $operator : config('app.name')),
            'host' => $request->getHost(),
            'identityUrl' => $integrations->sso() ? config('services.thijssensoftware.base_url') : null,
        ]);
    }
}
