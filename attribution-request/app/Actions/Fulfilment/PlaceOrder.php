<?php

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\Concerns\EnsuresRole;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\SimAction;
use App\Exceptions\Domain\FulfilmentException;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Models\Operator;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\User;
use App\ValueObjects\MoneyGnf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Étape 04 — Commande : bon de commande fournisseur (téléphone) ou demande de puce à l'opérateur (SIM).
 */
final class PlaceOrder
{
    use EnsuresRole;

    public function handle(
        ServiceRequest $request,
        User $actor,
        OrderType $type,
        ?string $reference = null,
        ?string $supplier = null,
        ?CarbonInterface $expectedAt = null,
        ?MoneyGnf $unitPrice = null,
        ?string $notes = null,
    ): Order {
        $this->ensureRole($actor, Role::fulfilment(), __('passer une commande'));

        return DB::transaction(function () use ($request, $actor, $type, $reference, $supplier, $expectedAt, $unitPrice, $notes): Order {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $request->load(['phoneDetail.approvedDeviceModel', 'simDetail']);

            if (! in_array($request->status, [RequestStatus::Approved, RequestStatus::Ordering], true)) {
                throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::Ordering);
            }

            $item = match ($type) {
                OrderType::DevicePurchase => $this->deviceItem($request),
                OrderType::OperatorRequest => $this->simItem($request),
            };

            $order = Order::create([
                'reference' => $reference,
                'type' => $type,
                'status' => OrderStatus::Sent,
                'supplier' => $type === OrderType::DevicePurchase ? $supplier : null,
                'operator_id' => $type === OrderType::OperatorRequest ? Operator::query()->where('is_active', true)->value('id') : null,
                'ordered_by_id' => $actor->id,
                'ordered_at' => now(),
                'expected_at' => $expectedAt,
                'total_gnf' => $unitPrice,
                'notes' => $notes,
            ]);

            $order->items()->create($item + ['service_request_id' => $request->id, 'quantity' => 1, 'unit_price_gnf' => $unitPrice]);

            $comment = $type->getLabel().($reference ? ' n° '.$reference : '');

            if ($request->status === RequestStatus::Approved) {
                $request->transitionTo(RequestStatus::Ordering, $actor, RequestEventType::Ordered, $comment, ['order_id' => $order->id]);
            } else {
                $request->recordEvent(RequestEventType::Ordered, $actor, $comment, ['order_id' => $order->id]);
            }

            return $order;
        });
    }

    /** @return array<string, mixed> */
    private function deviceItem(ServiceRequest $request): array
    {
        $model = $request->phoneDetail?->approvedDeviceModel;

        if (! $request->phoneDetail?->reason?->requiresNewDevice() || $model === null) {
            throw FulfilmentException::nothingToOrder($request->displayReference());
        }

        return ['device_model_id' => $model->id, 'description' => $model->label];
    }

    /** @return array<string, mixed> */
    private function simItem(ServiceRequest $request): array
    {
        if ($request->simDetail?->action !== SimAction::Creation) {
            throw FulfilmentException::nothingToOrder($request->displayReference());
        }

        return ['description' => __('Puce SIM — création de ligne (:plan)', ['plan' => $request->simDetail->plan_type?->getLabel() ?? '—'])];
    }
}
