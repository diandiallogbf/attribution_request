<?php

use App\Enums\RequestStatus;

it('suit le circuit de la fiche officielle', function () {
    expect(RequestStatus::Draft->canTransitionTo(RequestStatus::PendingManager))->toBeTrue()
        ->and(RequestStatus::PendingManager->canTransitionTo(RequestStatus::PendingDsi))->toBeTrue()
        ->and(RequestStatus::PendingDsi->canTransitionTo(RequestStatus::Approved))->toBeTrue()
        ->and(RequestStatus::Approved->canTransitionTo(RequestStatus::Ordering))->toBeTrue()
        ->and(RequestStatus::Ordering->canTransitionTo(RequestStatus::ReadyForAssignment))->toBeTrue()
        ->and(RequestStatus::ReadyForAssignment->canTransitionTo(RequestStatus::PendingReceipt))->toBeTrue()
        ->and(RequestStatus::PendingReceipt->canTransitionTo(RequestStatus::Closed))->toBeTrue();
});

it('interdit de sauter une étape', function () {
    expect(RequestStatus::Draft->canTransitionTo(RequestStatus::Approved))->toBeFalse()
        ->and(RequestStatus::PendingManager->canTransitionTo(RequestStatus::Approved))->toBeFalse()
        ->and(RequestStatus::Approved->canTransitionTo(RequestStatus::Returned))->toBeFalse();
});

it('considère clôturée, rejetée et annulée comme des états finaux', function (RequestStatus $status) {
    expect($status->isFinal())->toBeTrue()
        ->and($status->allowedTransitions())->toBe([]);
})->with([RequestStatus::Closed, RequestStatus::Rejected, RequestStatus::Cancelled]);

it('associe chaque statut à une étape de 1 à 6', function () {
    foreach (RequestStatus::cases() as $status) {
        expect($status->stage())->toBeBetween(1, 6);
    }
});

it('ne laisse modifier que les brouillons et les demandes renvoyées', function () {
    $editable = array_filter(RequestStatus::cases(), fn (RequestStatus $s) => $s->isEditableByRequester());

    expect(array_values($editable))->toBe([RequestStatus::Draft, RequestStatus::Returned]);
});
