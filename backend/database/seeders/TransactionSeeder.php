<?php

namespace Database\Seeders;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CalculationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $calc  = new CalculationService();
        $admin = User::where('role', 'ADMIN')->first();

        if (! $admin) {
            $this->command->warn('No admin user found. Run UserSeeder first.');
            return;
        }

        $mode      = Setting::mode();
        $defaultRp = Setting::defaultReceivePercentage();
        $defaultPp = Setting::defaultPaymentPercentage();

        $clients = Client::with('accounts')->get();

        if ($clients->isEmpty()) {
            $this->command->warn('No clients found. Run ClientSeeder first.');
            return;
        }

        // 30 days of transactions, 2-6 per day per client
        $totalCreated = 0;

        for ($dayOffset = 29; $dayOffset >= 0; $dayOffset--) {
            $date = Carbon::today()->subDays($dayOffset);

            foreach ($clients as $client) {
                $count = random_int(2, 5);

                for ($i = 0; $i < $count; $i++) {
                    $account = $client->accounts->random();
                    if (! $account) continue;

                    // Randomize percentages slightly around defaults
                    $rp = round(max(70, min(95, $defaultRp + (random_int(-300, 300) / 100))), 2);
                    $pp = round(max(70, min(95, $defaultPp + (random_int(-300, 300) / 100))), 2);

                    // Ensure payment >= receive (profit positive) mostly
                    if ($pp < $rp) $pp = $rp + 1;

                    $amount = random_int(100, 5000);

                    $computed = $calc->calculateTransaction([
                        'amount'             => $amount,
                        'receive_percentage' => $rp,
                        'payment_percentage' => $pp,
                        'mode'               => $mode,
                    ]);

                    $time = sprintf('%02d:%02d:00', random_int(8, 21), random_int(0, 59));

                    Transaction::create([
                        'transaction_no'     => Transaction::nextNumber(),
                        'client_id'          => $client->id,
                        'account_id'         => $account->id,
                        'transaction_type'   => TransactionType::RECEIVE->value,
                        'transaction_date'   => $date->toDateString(),
                        'transaction_time'   => $time,
                        'amount'             => $computed['amount'],
                        'receive_percentage' => $rp,
                        'receive_amount'     => $computed['receive_amount'],
                        'payment_percentage' => $pp,
                        'payment_amount'     => $computed['payment_amount'],
                        'profit'             => $computed['profit'],
                        'status'             => TransactionStatus::COMPLETED->value,
                        'reference'          => 'SEED-'.strtoupper(bin2hex(random_bytes(3))),
                        'notes'              => 'Seeded transaction.',
                        'created_by'         => $admin->id,
                    ]);

                    $totalCreated++;
                }
            }
        }

        $this->command->info("   → {$totalCreated} transactions created for last 30 days.");
    }
}