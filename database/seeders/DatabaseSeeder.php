<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $client = ApiClient::query()->create(['name' => 'Demo client', 'token_hash' => hash('sha256', 'ledger-lab-demo-token')]);
        $client->wallets()->createMany([
            ['name' => 'Operating wallet', 'currency' => 'BRL', 'balance_cents' => 0],
            ['name' => 'Savings wallet', 'currency' => 'BRL', 'balance_cents' => 0],
        ]);
        $this->command?->warn('Demo API key: ledger-lab-demo-token (local study environment only). Wallets intentionally start at zero.');
    }
}
