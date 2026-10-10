<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\TransactionResource;
use App\Models\Client;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $today      = Carbon::today();
        $weekStart  = Carbon::now()->startOfWeek();
        $weekEnd    = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();

        /* ---------- Summary cards ---------- */

        $totalClients  = Client::count();
        $activeClients = Client::where('status', 'ACTIVE')->count();

        $todayPurchases = $this->sumPurchases($today, $today);
        $todaySales     = $this->sumSales($today, $today);

        $weekPurchases = $this->sumPurchases($weekStart, $weekEnd);
        $weekSales     = $this->sumSales($weekStart, $weekEnd);

        $monthPurchases = $this->sumPurchases($monthStart, $monthEnd);
        $monthSales     = $this->sumSales($monthStart, $monthEnd);

        /* ---------- Profit ---------- */

        $todayProfit = $this->profitSummary($today, $today);
        $weekProfit  = $this->profitSummary($weekStart, $weekEnd);
        $monthProfit = $this->profitSummary($monthStart, $monthEnd);

        /* ---------- Due ---------- */

        $payables    = $this->totalDue('PURCHASE');
        $receivables = $this->totalDue('SALE');

        // 🔑 FIX: subtract the 'due' scalar, not the whole arrays
        $netDue = round(
            (float) ($receivables['due'] ?? 0) - (float) ($payables['due'] ?? 0),
            2
        );

        /* ---------- Charts ---------- */

        $dailyChart    = $this->dailyChart(14);
        $monthlyProfit = $this->monthlyChart(12);
        $clientWise    = $this->clientWiseChart(
            Carbon::today()->subDays(29),
            Carbon::today()
        );

        /* ---------- Recent transactions ---------- */

        $recent = Transaction::query()
            ->with([
                'client:id,client_code,name',
                'account:id,account_name,masked_number,last_four,currency',
                'creator:id,name',
                'payments',
            ])
            ->where('is_deleted', false)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        /* ---------- Open purchases ---------- */

        $openPurchases = Transaction::query()
            ->with(['client:id,client_code,name', 'payments'])
            ->where('type', 'PURCHASE')
            ->where('is_deleted', false)
            ->whereIn('status', ['OPEN', 'PARTIAL'])
            ->orderByDesc('transaction_date')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'today' => [
                'clients'        => $totalClients,
                'active_clients' => $activeClients,

                'purchase_amount'  => $todayPurchases['purchase_usd'],
                'purchase_count'   => $todayPurchases['count'],
                'purchase_payable' => $todayPurchases['payable'],

                'sale_amount'      => $todaySales['purchase_usd'],
                'sale_count'       => $todaySales['count'],
                'sale_receivable'  => $todaySales['payable'],

                'profit_realized'  => $todayProfit['realized'],
                'profit_pending'   => $todayProfit['pending'],
                'profit_total'     => $todayProfit['total'],
            ],

            'weekly' => [
                'purchase_amount' => $weekPurchases['purchase_usd'],
                'purchase_count'  => $weekPurchases['count'],
                'sale_amount'     => $weekSales['purchase_usd'],
                'sale_count'      => $weekSales['count'],
                'profit_realized' => $weekProfit['realized'],
                'profit_pending'  => $weekProfit['pending'],
                'profit_total'    => $weekProfit['total'],
            ],

            'monthly' => [
                'purchase_amount' => $monthPurchases['purchase_usd'],
                'purchase_count'  => $monthPurchases['count'],
                'sale_amount'     => $monthSales['purchase_usd'],
                'sale_count'      => $monthSales['count'],
                'profit_realized' => $monthProfit['realized'],
                'profit_pending'  => $monthProfit['pending'],
                'profit_total'    => $monthProfit['total'],
            ],

            'due' => [
                'payables'    => $payables,
                'receivables' => $receivables,
                'net'         => number_format($netDue, 2, '.', ''),
            ],

            'charts' => [
                'daily'       => $dailyChart,
                'monthly'     => $monthlyProfit,
                'client_wise' => $clientWise,
            ],

            'recent_transactions' => TransactionResource::collection($recent),
            'open_purchases'      => TransactionResource::collection($openPurchases),
        ], 'Admin dashboard');
    }

    /* ============================================================
     |  Helpers
     ============================================================ */

    protected function sumPurchases(Carbon $from, Carbon $to): array
    {
        $row = Transaction::query()
            ->where('is_deleted', false)
            ->where('type', 'PURCHASE')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                COUNT(*) AS count,
                COALESCE(SUM(purchase_usd), 0)   AS purchase_usd,
                COALESCE(SUM(total_payable), 0)  AS payable
            ')
            ->first();

        return [
            'count'        => (int) $row->count,
            'purchase_usd' => number_format((float) $row->purchase_usd, 2, '.', ''),
            'payable'      => number_format((float) $row->payable, 2, '.', ''),
        ];
    }

    protected function sumSales(Carbon $from, Carbon $to): array
    {
        $row = Transaction::query()
            ->where('is_deleted', false)
            ->where('type', 'SALE')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                COUNT(*) AS count,
                COALESCE(SUM(purchase_usd), 0)   AS purchase_usd,
                COALESCE(SUM(total_payable), 0)  AS payable
            ')
            ->first();

        return [
            'count'        => (int) $row->count,
            'purchase_usd' => number_format((float) $row->purchase_usd, 2, '.', ''),
            'payable'      => number_format((float) $row->payable, 2, '.', ''),
        ];
    }

    protected function profitSummary(Carbon $from, Carbon $to): array
    {
        $realizedRow = DB::table('transaction_sales')
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(profit_realized), 0) AS total')
            ->first();

        $realized = (float) $realizedRow->total;

        $pendingRow = Transaction::query()
            ->where('is_deleted', false)
            ->where('type', 'PURCHASE')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(remaining_quantity), 0) AS remaining')
            ->first();

        $remaining = (float) $pendingRow->remaining;

        $avgRate = 0;
        if ($realized > 0) {
            $soldRow = DB::table('transaction_sales')
                ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('COALESCE(SUM(sold_quantity), 0) AS sold')
                ->first();
            $sold = (float) $soldRow->sold;
            if ($sold > 0) {
                $avgRate = $realized / $sold;
            }
        }

        $pending = round($remaining * $avgRate, 2);
        $total   = round($realized + $pending, 2);

        return [
            'realized' => number_format($realized, 2, '.', ''),
            'pending'  => number_format($pending,  2, '.', ''),
            'total'    => number_format($total,    2, '.', ''),
        ];
    }

    protected function totalDue(string $type): array
    {
        $rows = Transaction::query()
            ->where('is_deleted', false)
            ->where('type', $type)
            ->whereIn('status', ['OPEN', 'PARTIAL'])
            ->get(['id', 'total_payable']);

        $totalPayable = 0;
        $totalPaid    = 0;

        foreach ($rows as $row) {
            $totalPayable += (float) $row->total_payable;
            $totalPaid    += (float) DB::table('transaction_payments')
                ->where('transaction_id', $row->id)
                ->sum('amount_usd');
        }

        return [
            'count'   => $rows->count(),
            'payable' => number_format($totalPayable, 2, '.', ''),
            'paid'    => number_format($totalPaid,    2, '.', ''),
            'due'     => number_format(max(0, $totalPayable - $totalPaid), 2, '.', ''),
        ];
    }

    protected function dailyChart(int $days = 14): array
    {
        $end    = Carbon::today();
        $start  = $end->copy()->subDays($days - 1);
        $out    = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();

            $p = Transaction::query()
                ->where('is_deleted', false)
                ->where('type', 'PURCHASE')
                ->whereDate('transaction_date', $d)
                ->selectRaw('COALESCE(SUM(purchase_usd), 0) AS purchase, COALESCE(SUM(total_payable), 0) AS payable')
                ->first();

            $s = Transaction::query()
                ->where('is_deleted', false)
                ->where('type', 'SALE')
                ->whereDate('transaction_date', $d)
                ->selectRaw('COALESCE(SUM(purchase_usd), 0) AS sale, COALESCE(SUM(total_payable), 0) AS payable')
                ->first();

            $pr = DB::table('transaction_sales')
                ->whereDate('sale_date', $d)
                ->selectRaw('COALESCE(SUM(profit_realized), 0) AS profit')
                ->first();

            $out[] = [
                'date'             => $d,
                'purchase_usd'     => number_format((float) $p->purchase, 2, '.', ''),
                'purchase_payable' => number_format((float) $p->payable,  2, '.', ''),
                'sale_usd'         => number_format((float) $s->sale,     2, '.', ''),
                'sale_payable'     => number_format((float) $s->payable,  2, '.', ''),
                'profit_realized'  => number_format((float) $pr->profit,  2, '.', ''),
            ];

            $cursor->addDay();
        }

        return $out;
    }

    protected function monthlyChart(int $months = 12): array
    {
        $out = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = Carbon::today()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end   = $date->copy()->endOfMonth();

            $p = Transaction::query()
                ->where('is_deleted', false)
                ->where('type', 'PURCHASE')
                ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('COALESCE(SUM(purchase_usd), 0) AS purchase')
                ->first();

            $s = Transaction::query()
                ->where('is_deleted', false)
                ->where('type', 'SALE')
                ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('COALESCE(SUM(purchase_usd), 0) AS sale')
                ->first();

            $pr = DB::table('transaction_sales')
                ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('COALESCE(SUM(profit_realized), 0) AS profit')
                ->first();

            $out[] = [
                'label'           => $start->format('Y-m'),
                'purchase_usd'    => number_format((float) $p->purchase, 2, '.', ''),
                'sale_usd'        => number_format((float) $s->sale,     2, '.', ''),
                'profit_realized' => number_format((float) $pr->profit,  2, '.', ''),
            ];
        }

        return $out;
    }

    protected function clientWiseChart(Carbon $from, Carbon $to): array
    {
        $purchases = Transaction::query()
            ->where('transactions.is_deleted', false)
            ->where('transactions.type', 'PURCHASE')
            ->whereBetween('transactions.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->join('clients', 'clients.id', '=', 'transactions.client_id')
            ->selectRaw('
                clients.id AS client_id,
                clients.name AS client_name,
                clients.client_code,
                COUNT(*) AS total_transactions,
                COALESCE(SUM(transactions.purchase_usd), 0) AS total_purchase,
                COALESCE(SUM(transactions.total_payable), 0) AS total_payable
            ')
            ->groupBy('clients.id', 'clients.name', 'clients.client_code')
            ->get();

        $sales = Transaction::query()
            ->where('transactions.is_deleted', false)
            ->where('transactions.type', 'SALE')
            ->whereBetween('transactions.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->join('clients', 'clients.id', '=', 'transactions.client_id')
            ->selectRaw('
                clients.id AS client_id,
                COUNT(*) AS total_transactions,
                COALESCE(SUM(transactions.purchase_usd), 0) AS total_sale,
                COALESCE(SUM(transactions.total_payable), 0) AS total_payable
            ')
            ->groupBy('clients.id')
            ->get()
            ->keyBy('client_id');

        $profitPerClient = DB::table('transaction_sales')
            ->join('transactions', 'transactions.id', '=', 'transaction_sales.parent_transaction_id')
            ->whereBetween('transaction_sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                transactions.client_id,
                COALESCE(SUM(transaction_sales.profit_realized), 0) AS total_profit
            ')
            ->groupBy('transactions.client_id')
            ->get()
            ->keyBy('client_id');

        $map = [];

        foreach ($purchases as $p) {
            $map[$p->client_id] = [
                'client_id'        => (int) $p->client_id,
                'client_name'      => $p->client_name,
                'client_code'      => $p->client_code,
                'purchase_count'   => (int) $p->total_transactions,
                'purchase_usd'     => number_format((float) $p->total_purchase, 2, '.', ''),
                'purchase_payable' => number_format((float) $p->total_payable, 2, '.', ''),
                'sale_count'       => 0,
                'sale_usd'         => '0.00',
                'sale_payable'     => '0.00',
                'profit_realized'  => '0.00',
            ];
        }

        foreach ($sales as $clientId => $s) {
            if (! isset($map[$clientId])) {
                $map[$clientId] = [
                    'client_id'        => (int) $clientId,
                    'client_name'      => '',
                    'client_code'      => '',
                    'purchase_count'   => 0,
                    'purchase_usd'     => '0.00',
                    'purchase_payable' => '0.00',
                    'sale_count'       => 0,
                    'sale_usd'         => '0.00',
                    'sale_payable'     => '0.00',
                    'profit_realized'  => '0.00',
                ];
            }
            $map[$clientId]['sale_count']   = (int) $s->total_transactions;
            $map[$clientId]['sale_usd']     = number_format((float) $s->total_sale,    2, '.', '');
            $map[$clientId]['sale_payable'] = number_format((float) $s->total_payable, 2, '.', '');
        }

        foreach ($profitPerClient as $clientId => $p) {
            if (isset($map[$clientId])) {
                $map[$clientId]['profit_realized'] = number_format((float) $p->total_profit, 2, '.', '');
            }
        }

        $clientIds = array_keys($map);
        $clients = Client::whereIn('id', $clientIds)->get(['id', 'name', 'client_code'])->keyBy('id');
        foreach ($map as $id => &$row) {
            if (empty($row['client_name']) && isset($clients[$id])) {
                $row['client_name'] = $clients[$id]->name;
                $row['client_code'] = $clients[$id]->client_code;
            }
        }
        unset($row);

        $result = array_values($map);
        usort($result, fn ($a, $b) => (float) $b['profit_realized'] <=> (float) $a['profit_realized']);

        return $result;
    }
}