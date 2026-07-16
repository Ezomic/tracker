<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $descriptions = [
        'TRACK' => 'Self-hosted single-user issue tracker replacing Linear, with per-project sequential identifiers and GitHub PR auto-linking.',
        'CMS' => 'Portfolio CMS powering the public thijssensoftware.nl site and its admin.',
        'SHOP' => 'Digital script sales webshop with Stripe and Mollie checkout.',
        'STK' => 'Stock portfolio manager with automated IBKR trading rules.',
        'FIN' => 'Self-hosted household finance tracker: accounts, budgets, bills, bank-statement import (CSV/XLS/MT940/CAMT.053), auto-categorization, and subscription detection.',
        'GROC' => 'Grocery scanner, pantry, and shopping-list API for the mobile app.',
        'HAB' => 'Spanish and Portuguese learning app built on CEFR levels, FSI pacing, and FSRS spaced repetition.',
        'ZERO' => 'Multi-account unified mail client.',
        'ID' => 'Thijssensoftware ID: a passwordless OAuth2 SSO identity provider for the workflow apps.',
        'ST' => 'Music theory, guitar, and bass learning PWA — read it, see it, hear it, play it.',
        'BILLR' => 'Invoicing and billing app.',
        'ARBO' => 'Occupational-health SaaS: a multi-service platform (admin, doctors, employers, case officers, identity).',
        'SRV' => 'Server management and provisioning tool.',
        'INFRA' => 'Infrastructure, deployment, and tooling for the Thijssen Software apps.',
        'THI' => 'Cross-cutting Thijssen Software work not tied to a single app.',
    ];

    public function up(): void
    {
        foreach ($this->descriptions as $key => $description) {
            DB::table('projects')
                ->where('key', $key)
                ->whereNull('description')
                ->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->descriptions) as $key) {
            DB::table('projects')->where('key', $key)->update(['description' => null]);
        }
    }
};
