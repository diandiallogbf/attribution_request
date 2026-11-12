<?php

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\Concerns\EnsuresRole;
use App\Enums\DeviceStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PlanType;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\SimLineStatus;
use App\Exceptions\Domain\FulfilmentException;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceRequest;
use App\Models\SimLine;
use App\Models\User;
use App\ValueObjects\Iccid;
use App\ValueObjects\Imei;
use App\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Réception d'une commande : les appareils (IMEI / n° de série) ou les lignes (numéro / ICCID)
 * entrent en stock ; les demandes dont tout le matériel est arrivé passent « Prête à attribuer ».
 */
final class ReceiveOrder
{
    use EnsuresRole;

    /**
     * @param  list<array{imei?: string|null, serial_number?: string|null}>  $devices
     * @param  list<array{msisdn: string, iccid?: string|null}>  $lines
     */
    public function handle(Order $order, User $actor, array $devices = [], array $lines = []): Order
    {
        $this->ensureRole($actor, Role::fulfilment(), __('réceptionner une commande'));

        return DB::transaction(function () use ($order, $actor, $devices, $lines): Order {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $order->load('items.serviceRequest.simDetail');

            $order->items->pluck('serviceRequest')->filter()->unique('id')
                ->each(fn (ServiceRequest $request) => $this->ensureCanFulfil($request, $actor, __('réceptionner une commande')));

            if (! in_array($order->status, [OrderStatus::Sent, OrderStatus::PartiallyReceived], true)) {
                throw new InvalidTransitionException(__('Cette commande est « :status » : elle ne peut pas être réceptionnée.', ['status' => $order->status->getLabel()]));
            }

            $expected = (int) $order->items->sum('quantity');

            match ($order->type) {
                OrderType::DevicePurchase => $this->receiveDevices($order, $devices, $expected),
                OrderType::OperatorRequest => $this->receiveLines($order, $lines, $expected),
            };

            $order->update(['status' => OrderStatus::Received, 'received_at' => now()]);

            $this->releaseRequests($order, $actor);

            return $order;
        });
    }

    /** @param  list<array{imei?: string|null, serial_number?: string|null}>  $devices */
    private function receiveDevices(Order $order, array $devices, int $expected): void
    {
        if (count($devices) !== $expected) {
            throw FulfilmentException::receivedCountMismatch($expected, count($devices), __('appareil(s)'));
        }

        $queue = $devices;

        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $data = array_shift($queue);

                Device::create([
                    'device_model_id' => $item->device_model_id,
                    'imei' => filled($data['imei'] ?? null) ? Imei::from($data['imei']) : null,
                    'serial_number' => $data['serial_number'] ?? null,
                    'status' => DeviceStatus::InStock,
                    'order_item_id' => $item->id,
                    'acquired_at' => now(),
                ]);
            }

            $item->update(['received_quantity' => $item->quantity]);
        }
    }

    /** @param  list<array{msisdn: string, iccid?: string|null}>  $lines */
    private function receiveLines(Order $order, array $lines, int $expected): void
    {
        if (count($lines) !== $expected) {
            throw FulfilmentException::receivedCountMismatch($expected, count($lines), __('ligne(s)'));
        }

        $queue = $lines;

        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $data = array_shift($queue);
                $sim = $item->serviceRequest?->simDetail;

                SimLine::create([
                    'operator_id' => $order->operator_id,
                    'msisdn' => PhoneNumber::from($data['msisdn']),
                    'iccid' => filled($data['iccid'] ?? null) ? Iccid::from($data['iccid']) : null,
                    'plan_type' => $sim?->plan_type ?? PlanType::Prepaid,
                    'options' => $sim?->options?->all() ?? [],
                    'monthly_recharge_gnf' => $sim?->approved_monthly_recharge_gnf ?? $sim?->requested_monthly_recharge_gnf,
                    'is_fleet' => true,
                    'status' => SimLineStatus::InStock,
                ]);
            }

            $item->update(['received_quantity' => $item->quantity]);
        }
    }

    /** Une demande en commande devient « Prête à attribuer » quand toutes ses commandes sont reçues. */
    private function releaseRequests(Order $order, User $actor): void
    {
        $requestIds = $order->items->pluck('service_request_id')->filter()->unique();

        foreach (ServiceRequest::query()->whereKey($requestIds)->where('status', RequestStatus::Ordering)->get() as $request) {
            $pending = OrderItem::query()
                ->where('service_request_id', $request->id)
                ->whereColumn('received_quantity', '<', 'quantity')
                ->exists();

            if (! $pending) {
                $request->transitionTo(RequestStatus::ReadyForAssignment, $actor, RequestEventType::Received, $order->type->getLabel().($order->reference ? ' n° '.$order->reference : ''));
            }
        }
    }
}
