<?php

use App\Actions\Fulfilment\ConfirmReceipt;
use App\Actions\Fulfilment\FulfilTelephonyRequest;
use App\Actions\Fulfilment\PlaceOrder;
use App\Actions\Fulfilment\ReceiveOrder;
use App\Actions\Fulfilment\ServeFromStock;
use App\Data\TelephonyFulfilmentData;
use App\Enums\AttachmentKind;
use App\Enums\DeviceStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RequestStatus;
use App\Enums\SimAction;
use App\Enums\SimLineStatus;
use App\Exceptions\Domain\FulfilmentException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\Assignment;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\SimLine;
use App\Models\User;
use App\Support\Luhn;
use App\ValueObjects\MoneyGnf;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedReferentials();
    Notification::fake();
    Storage::fake('local');

    ['manager' => $this->manager, 'employee' => $this->employee] = team();
    $this->dsi = tap(User::factory()->create())->assignRole('dsi');
    $this->it = tap(User::factory()->create())->assignRole('it_agent');
    $this->model = DeviceModel::query()->where('name', 'Galaxy A16')->firstOrFail();
});

it('commande, réceptionne, attribue puis clôture sur confirmation du bénéficiaire', function () {
    $request = approvedTelephonyRequest($this->employee, $this->manager, $this->dsi, $this->model);
    expect($request->status)->toBe(RequestStatus::Approved);

    // Étape 04 : bon de commande appareil + demande de puce à l'opérateur
    $order = app(PlaceOrder::class)->handle($request, $this->it, OrderType::DevicePurchase, 'BC-2026-118', 'Orange Business', unitPrice: MoneyGnf::from(2_100_000));
    $simOrder = app(PlaceOrder::class)->handle($request, $this->it, OrderType::OperatorRequest, 'OGN-5521');
    expect($request->fresh()->status)->toBe(RequestStatus::Ordering);

    $imei = Luhn::complete('35'.'123456789012');
    app(ReceiveOrder::class)->handle($order, $this->it, devices: [['imei' => $imei, 'serial_number' => 'R5CX1']]);
    expect($request->fresh()->status)->toBe(RequestStatus::Ordering); // la puce n'est pas encore arrivée

    app(ReceiveOrder::class)->handle($simOrder, $this->it, lines: [['msisdn' => '625 00 11 22']]);
    expect($request->fresh()->status)->toBe(RequestStatus::ReadyForAssignment)
        ->and($order->fresh()->status)->toBe(OrderStatus::Received);

    // Étape 05 : configuration et remise
    $device = Device::query()->where('imei', $imei)->sole();
    $line = SimLine::query()->where('msisdn', '+224625001122')->sole();

    $request = app(FulfilTelephonyRequest::class)->handle($request->fresh(), $this->it, new TelephonyFulfilmentData(
        deviceId: $device->id,
        setupChecklist: ['microsoft_account' => true, 'intune' => true],
        simLineId: $line->id,
    ));

    expect($request->status)->toBe(RequestStatus::PendingReceipt)
        ->and($device->fresh()->status)->toBe(DeviceStatus::Assigned)
        ->and($line->fresh()->status)->toBe(SimLineStatus::Active)
        ->and($line->fresh()->monthly_recharge_gnf->amount)->toBe(200_000);

    // Le bénéficiaire confirme : clôture + fiche archivée
    $request = app(ConfirmReceipt::class)->handle($request, $this->employee, true);

    expect($request->status)->toBe(RequestStatus::Closed)
        ->and(Assignment::query()->where('service_request_id', $request->id)->whereNull('received_at')->exists())->toBeFalse()
        ->and($request->attachments()->where('kind', AttachmentKind::RequestForm)->exists())->toBeTrue();
});

it('sert une demande depuis le stock sans commande', function () {
    Device::factory()->create(['device_model_id' => $this->model->id]);
    $request = approvedTelephonyRequest($this->employee, $this->manager, $this->dsi, $this->model, simAction: null);

    $request = app(ServeFromStock::class)->handle($request, $this->it);

    expect($request->status)->toBe(RequestStatus::ReadyForAssignment);
});

it('refuse de servir depuis un stock vide', function () {
    $request = approvedTelephonyRequest($this->employee, $this->manager, $this->dsi, $this->model, simAction: null);

    app(ServeFromStock::class)->handle($request, $this->it);
})->throws(FulfilmentException::class);

it('clôture directement une modification de forfait (aucune remise)', function () {
    $line = SimLine::factory()->create(['monthly_recharge_gnf' => 50_000]);
    Assignment::create([
        'user_id' => $this->employee->id, 'assignable_type' => 'sim_line', 'assignable_id' => $line->id, 'assigned_at' => now()->subYear(),
    ]);

    $request = approvedTelephonyRequest($this->employee, $this->manager, $this->dsi, $this->model, SimAction::Modification, $line);

    $request = app(FulfilTelephonyRequest::class)->handle($request, $this->it, new TelephonyFulfilmentData);

    expect($request->status)->toBe(RequestStatus::Closed)
        ->and($line->fresh()->monthly_recharge_gnf->amount)->toBe(200_000);
});

it('réserve l\'attribution aux équipes IT', function () {
    $request = approvedTelephonyRequest($this->employee, $this->manager, $this->dsi, $this->model);

    app(PlaceOrder::class)->handle($request, $this->manager, OrderType::DevicePurchase);
})->throws(UnauthorizedActionException::class);

it('seul le bénéficiaire confirme la réception', function () {
    Device::factory()->create(['device_model_id' => $this->model->id]);
    $request = approvedTelephonyRequest($this->employee, $this->manager, $this->dsi, $this->model, simAction: null);
    $request = app(ServeFromStock::class)->handle($request, $this->it);
    $request = app(FulfilTelephonyRequest::class)->handle($request, $this->it, new TelephonyFulfilmentData(
        deviceId: Device::query()->where('status', DeviceStatus::InStock)->value('id'),
    ));

    app(ConfirmReceipt::class)->handle($request, $this->manager, true);
})->throws(FulfilmentException::class);
