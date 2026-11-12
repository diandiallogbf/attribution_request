<?php

use App\Actions\Finance\RecordOrderAccounting;
use App\Data\OrderAccountingData;
use App\Enums\AccountingStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PlanType;
use App\Enums\SimLineStatus;
use App\Exceptions\Domain\AccountingException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Filament\Pages\FinanceDashboard;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Order;
use App\Models\SimLine;
use App\Models\User;
use App\Services\Finance\FinanceExports;
use App\Services\Finance\SpendingReport;
use App\Support\Spreadsheet\XlsxWorkbook;
use App\ValueObjects\MoneyGnf;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

beforeEach(function () {
    seedReferentials();
    $this->travelTo(now()->setDate(2026, 11, 20));

    $this->finance = tap(User::factory()->create())->assignRole('finance');
    $this->agent = tap(User::factory()->create())->assignRole('it_agent');
});

function order(string $reference, int $amount, string $orderedAt): Order
{
    return Order::create([
        'reference' => $reference,
        'type' => OrderType::DevicePurchase,
        'status' => OrderStatus::Received,
        'supplier' => 'Orange Business',
        'ordered_at' => $orderedAt,
        'total_gnf' => $amount,
    ]);
}

it('permet à la Finance de saisir la facture puis le règlement', function () {
    $order = order('BC-1', 2_100_000, '2026-10-02');

    $order = app(RecordOrderAccounting::class)->handle($order, $this->finance, new OrderAccountingData(
        invoiceReference: 'FAC-001',
        invoicedAt: now()->subDays(10),
        invoiceAmount: MoneyGnf::from(2_050_000),
        costCenter: '6056-DSI',
    ));

    expect($order->accountingStatus())->toBe(AccountingStatus::Invoiced)
        ->and($order->spentAmount()->amount)->toBe(2_050_000) // la facture fait foi
        ->and($order->accounted_by_id)->toBe($this->finance->id);

    $order = app(RecordOrderAccounting::class)->handle($order, $this->finance, new OrderAccountingData(
        invoiceReference: 'FAC-001',
        invoicedAt: now()->subDays(10),
        invoiceAmount: MoneyGnf::from(2_050_000),
        paidAt: now(),
    ));

    expect($order->accountingStatus())->toBe(AccountingStatus::Paid);
});

it('réserve les écritures comptables à la Finance', function () {
    app(RecordOrderAccounting::class)->handle(order('BC-1', 100, '2026-10-02'), $this->agent, new OrderAccountingData(invoiceReference: 'X'));
})->throws(UnauthorizedActionException::class);

it('refuse un règlement sans facture ou antérieur à la facture', function (OrderAccountingData $data) {
    app(RecordOrderAccounting::class)->handle(order('BC-1', 100, '2026-10-02'), $this->finance, $data);
})->throws(AccountingException::class)->with([
    'sans facture' => fn () => new OrderAccountingData(paidAt: now()),
    'avant la facture' => fn () => new OrderAccountingData(invoiceReference: 'F', invoicedAt: now(), invoiceAmount: MoneyGnf::from(100), paidAt: now()->subDay()),
    'dans le futur' => fn () => new OrderAccountingData(invoiceReference: 'F', invoicedAt: now()->addDay(), invoiceAmount: MoneyGnf::from(100)),
]);

it('calcule les dépenses du mois, de l\'exercice et les recharges engagées', function () {
    order('BC-1', 2_000_000, '2026-10-02')->update(['invoice_reference' => 'F1', 'invoice_amount_gnf' => 1_900_000]);
    order('BC-2', 500_000, '2026-11-05');
    order('BC-2025', 9_999_999, '2025-12-30'); // autre exercice
    order('BC-X', 7_000_000, '2026-11-06')->update(['status' => OrderStatus::Cancelled]); // annulée : ignorée

    $holder = User::factory()->create(['department_id' => Department::query()->whereHas('direction', fn ($q) => $q->where('code', 'DEX'))->value('id')]);
    $line = SimLine::factory()->create(['status' => SimLineStatus::Active, 'plan_type' => PlanType::Prepaid, 'monthly_recharge_gnf' => 250_000]);
    Assignment::create(['user_id' => $holder->id, 'assignable_type' => 'sim_line', 'assignable_id' => $line->id, 'assigned_at' => now()->subYear()]);
    SimLine::factory()->create(['status' => SimLineStatus::Terminated, 'monthly_recharge_gnf' => 999_000]);

    $report = app(SpendingReport::class);
    $totals = $report->totals(2026);
    $monthly = collect($report->monthly(2026))->keyBy('month');

    expect($totals['spent']->amount)->toBe(2_400_000)
        ->and($totals['spentThisMonth']->amount)->toBe(500_000)
        ->and($totals['unpaid']['count'])->toBe(1)
        ->and($totals['toInvoice']['amount']->amount)->toBe(500_000)
        ->and($totals['monthlyRecharge']->amount)->toBe(250_000)
        ->and($monthly[10]['spent']->amount)->toBe(1_900_000)
        ->and($monthly[10]['ordered']->amount)->toBe(2_000_000)
        ->and(collect($report->byDirection(2026))->firstWhere('code', 'DEX')['monthlyRecharge']->amount)->toBe(250_000);
});

it('produit un classeur Excel lisible : montants numériques et ligne de total', function () {
    order('BC-1', 2_000_000, '2026-10-02');
    order('BC-2', 500_000, '2026-11-05');

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    app(FinanceExports::class)->orders(XlsxWorkbook::make(), Order::query()->with(FinanceExports::ORDER_RELATIONS)->orderBy('ordered_at')->get())->writeTo($path);

    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($path);

    expect($rows)->toHaveCount(4) // en-tête + 2 commandes + total
        ->and($rows[0][0])->toBe('N° BC / réf. opérateur')
        ->and($rows[1][9])->toBe(2_000_000)
        ->and($rows[3][0])->toBe('Total')
        ->and($rows[3][13])->toBe(2_500_000);
});

it('ouvre l\'espace Finance à la Finance et le ferme aux techniciens', function () {
    $this->actingAs($this->finance)->get(FinanceDashboard::getUrl())->assertOk()->assertSee('Dépenses d&#039;attribution', false);
    $this->actingAs($this->agent)->get(FinanceDashboard::getUrl())->assertForbidden();
});

it('comptabilise une commande depuis le back-office, action invisible pour l\'IT', function () {
    $order = order('BC-1', 2_000_000, '2026-10-02');

    Livewire::actingAs($this->finance)
        ->test(ManageOrders::class)
        ->callAction(TestAction::make('accounting')->table($order), data: [
            'invoice_reference' => 'FAC-77',
            'invoiced_at' => '2026-11-02',
            'invoice_amount' => 1_950_000,
            'paid_at' => '2026-11-15',
        ])
        ->assertHasNoActionErrors();

    expect($order->fresh()->accountingStatus())->toBe(AccountingStatus::Paid)
        ->and($order->fresh()->invoice_amount_gnf->amount)->toBe(1_950_000);

    Livewire::actingAs($this->agent)
        ->test(ManageOrders::class)
        ->assertActionHidden(TestAction::make('accounting')->table($order));
});

it('exporte en Excel les commandes affichées', function () {
    order('BC-1', 2_000_000, '2026-10-02');

    Livewire::actingAs($this->finance)
        ->test(ManageOrders::class)
        ->callAction('export')
        ->assertFileDownloaded();
});
