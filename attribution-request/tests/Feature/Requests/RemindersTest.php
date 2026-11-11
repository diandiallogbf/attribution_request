<?php

use App\Actions\Requests\SaveTelephonyDraft;
use App\Actions\Requests\StartTelephonyDraft;
use App\Actions\Requests\SubmitServiceRequest;
use App\Data\TelephonyRequestData;
use App\Enums\PlanType;
use App\Enums\SimAction;
use App\Models\RequestApproval;
use App\Notifications\ApprovalRequested;
use App\ValueObjects\MoneyGnf;
use Illuminate\Support\Facades\Notification;

it('relance une seule fois par jour les valideurs en retard', function () {
    seedReferentials();
    ['manager' => $manager, 'employee' => $employee] = team();

    $draft = app(StartTelephonyDraft::class)->handle($employee, $employee);
    app(SaveTelephonyDraft::class)->handle($draft, $employee, new TelephonyRequestData(
        wantsPhone: false, wantsSim: true, simAction: SimAction::Creation, planType: PlanType::Prepaid, monthlyRecharge: MoneyGnf::from(50_000),
    ));
    app(SubmitServiceRequest::class)->handle($draft->fresh(), $employee, true);

    Notification::fake();
    RequestApproval::query()->update(['created_at' => now()->subDays(3)]);

    $this->artisan('requests:send-reminders')->assertSuccessful();
    $this->artisan('requests:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($manager, ApprovalRequested::class, 1);
    expect(RequestApproval::query()->whereNotNull('reminded_at')->count())->toBe(1);
});
