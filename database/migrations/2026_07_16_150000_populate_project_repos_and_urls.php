<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, array{repos: list<string>, url: string}>
     */
    private array $projects = [
        'TRACK' => ['repos' => ['Ezomic/tracker'], 'url' => 'https://tracker.thijssensoftware.nl'],
        'CMS' => ['repos' => ['Ezomic/cms'], 'url' => 'https://thijssensoftware.nl'],
        'SHOP' => ['repos' => ['Ezomic/shop-web'], 'url' => 'https://shop.thijssensoftware.nl'],
        'STK' => ['repos' => ['Ezomic/stocks'], 'url' => 'https://stocks.thijssensoftware.nl'],
        'FIN' => ['repos' => ['Ezomic/finance'], 'url' => 'https://finance.thijssensoftware.nl'],
        'GROC' => ['repos' => ['Ezomic/groceries'], 'url' => 'https://groceries.thijssensoftware.nl'],
        'HAB' => ['repos' => ['Ezomic/hablas'], 'url' => 'https://hablas.thijssensoftware.nl'],
        'ZERO' => ['repos' => ['Ezomic/zero'], 'url' => 'https://zero.thijssensoftware.nl'],
        'ID' => ['repos' => ['Ezomic/id', 'Ezomic/id-client'], 'url' => 'https://id.thijssensoftware.nl'],
        'ST' => ['repos' => ['Ezomic/string-theory'], 'url' => 'https://string-theory.thijssensoftware.nl'],
        'BILLR' => ['repos' => ['Ezomic/billr'], 'url' => 'https://billr.thijssensoftware.nl'],
        'ARBO' => ['repos' => [
            'Ezomic/arbo-saas',
            'Ezomic/arbo-identity',
            'Ezomic/arbo-admin',
            'Ezomic/arbo-doctors',
            'Ezomic/arbo-employers',
            'Ezomic/arbo-case-officers',
            'Ezomic/arbo-identity-sso-kit',
        ], 'url' => 'https://arbo.thijssensoftware.nl'],
        'SRV' => ['repos' => ['Ezomic/server-manager'], 'url' => 'https://server-manager.thijssensoftware.nl'],
    ];

    public function up(): void
    {
        foreach ($this->projects as $key => $data) {
            DB::table('projects')
                ->where('key', $key)
                ->update([
                    'github_repos' => json_encode($data['repos']),
                    'production_url' => $data['url'],
                ]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->projects) as $key) {
            DB::table('projects')
                ->where('key', $key)
                ->update(['github_repos' => null, 'production_url' => null]);
        }
    }
};
