<?php

use App\Actions\Fulfilment\DelegateFulfilment;
use App\Actions\Fulfilment\PlaceOrder;
use App\Actions\Fulfilment\ReceiveOrder;
use App\Enums\OrderType;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\FulfilmentException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Filament\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\DeviceModel;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestMilestone;
use App\Support\Luhn;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedReferentials();
    Notification::fake();

    ['manager' => $this->manager, 'employee' => $this->employee] = team();
    $this->dsi = tap(User::factory()->create())->assignRole('dsi');
    $this->agent = tap(User::factory()->create())->assignRole('it_agent');
    $this->otherAgent = tap(User::factory()->create())->assignRole('it_agent');
    $this->model = DeviceModel::query()->where('name', 'Galaxy A16')->firstOrFail();

    $this->request = approvedTelephonyRequest($this->employee, $this->manager, $this->dsi, $this->model, simAction: null);
});

it('permet à la DSI de confier la commande à un membre de son équipe', function () {
    $request = app(DelegateFulfilment::class)->handle($this->request, $this->dsi, $this->agent, 'Commander chez Orange Business.');

    expect($request->handler_id)->toBe($this->agent->id)
        ->and($request->delegated_by_id)->toBe($this->dsi->id)
        ->and($request->delegation_note)->toBe('Commander chez Orange Business.')
        ->and(ServiceRequest::query()->handledBy($this->agent)->pluck('id')->all())->toBe([$request->id]);

    // Le journal (visible du collaborateur) ne reprend pas les consignes internes.
    $event = $request->events()->where('type', RequestEventType::Delegated)->sole();
    expect($event->comment)->toBe('Confiée à '.$this->agent->name);

    Notification::assertSentTo($this->agent, RequestMilestone::class);
});

it('réserve ensuite la commande au délégataire (et à la DSI)', function () {
    app(DelegateFulfilment::class)->handle($this->request, $this->dsi, $this->agent);

    expect(fn () => app(PlaceOrder::class)->handle($this->request, $this->otherAgent, OrderType::DevicePurchase))
        ->toThrow(UnauthorizedActionException::class);

    $order = app(PlaceOrder::class)->handle($this->request, $this->agent, OrderType::DevicePurchase, 'BC-2611-001');
    expect($this->request->fresh()->status)->toBe(RequestStatus::Ordering);

    // La réception suit la même règle ; la DSI garde la main.
    expect(fn () => app(ReceiveOrder::class)->handle($order, $this->otherAgent, devices: [['imei' => Luhn::complete('35'.'111111111111')]]))
        ->toThrow(UnauthorizedActionException::class);

    app(ReceiveOrder::class)->handle($order, $this->dsi, devices: [['imei' => Luhn::complete('35'.'111111111111')]]);
    expect($this->request->fresh()->status)->toBe(RequestStatus::ReadyForAssignment);
});

it('laisse toute l\'équipe IT agir tant que rien n\'est délégué', function () {
    app(PlaceOrder::class)->handle($this->request, $this->otherAgent, OrderType::DevicePurchase);

    expect($this->request->fresh()->status)->toBe(RequestStatus::Ordering);
});

it('réserve la délégation à la DSI', function () {
    $itManager = tap(User::factory()->create())->assignRole('it_manager');

    app(DelegateFulfilment::class)->handle($this->request, $itManager, $this->agent);
})->throws(UnauthorizedActionException::class);

it('refuse de déléguer hors de l\'équipe IT', function () {
    app(DelegateFulfilment::class)->handle($this->request, $this->dsi, $this->manager);
})->throws(FulfilmentException::class);

it('ne délègue que la phase commande / exécution', function () {
    $pending = ServiceRequest::factory()->forBeneficiary($this->employee)->create(['status' => RequestStatus::PendingDsi]);

    app(DelegateFulfilment::class)->handle($pending, $this->dsi, $this->agent);
})->throws(FulfilmentException::class);

it('propose « Déléguer » à la DSI dans le back-office et masque « Commander » aux autres techniciens', function () {
    Livewire::actingAs($this->dsi)
        ->test(ViewServiceRequest::class, ['record' => $this->request->uuid])
        ->assertActionVisible('delegate')
        ->callAction('delegate', data: ['handler_id' => $this->agent->id, 'instructions' => 'Urgent.'])
        ->assertHasNoActionErrors();

    expect($this->request->fresh()->handler_id)->toBe($this->agent->id);

    Livewire::actingAs($this->otherAgent)
        ->test(ViewServiceRequest::class, ['record' => $this->request->uuid])
        ->assertActionHidden('order')
        ->assertActionHidden('delegate');

    Livewire::actingAs($this->agent)
        ->test(ViewServiceRequest::class, ['record' => $this->request->uuid])
        ->assertActionVisible('order');
});
