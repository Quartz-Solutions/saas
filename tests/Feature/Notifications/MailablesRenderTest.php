<?php

namespace Tests\Feature\Notifications;

use App\Mail\EmailVerificationMail;
use App\Mail\LoginAlertMail;
use App\Mail\MagicLinkMail;
use App\Mail\PasswordResetMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\PlanChangedMail;
use App\Mail\TenantInviteMail;
use App\Mail\TrialEndingMail;
use App\Mail\TwoFactorRecoveryMail;
use App\Mail\WelcomeMail;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailablesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_mailable_renders_to_html(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $cases = [
            new WelcomeMail($user),
            new EmailVerificationMail($user, 'https://example.test/verify'),
            new PasswordResetMail($user, 'https://example.test/reset'),
            new MagicLinkMail($user, 'https://example.test/magic'),
            new TwoFactorRecoveryMail($user, ['ip' => '127.0.0.1', 'remaining' => 4]),
            new PaymentReceiptMail($user, ['amount' => '$10.00']),
            new PlanChangedMail($user, ['fromPlan' => 'Free', 'toPlan' => 'Pro']),
            new TrialEndingMail($user, ['planName' => 'Pro', 'daysRemaining' => 3]),
            new PaymentFailedMail($user, ['planName' => 'Pro', 'reason' => 'Card declined']),
            new LoginAlertMail($user, ['ip' => '127.0.0.1', 'userAgent' => 'PHPUnit']),
        ];

        foreach ($cases as $mail) {
            $html = $mail->render();
            $this->assertNotEmpty($html, get_class($mail).' produced empty HTML.');
        }
    }

    public function test_tenant_invite_mailable_renders(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $invitation = TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'pending@example.test',
            'role' => 'Member',
            'token' => str_repeat('a', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load(['tenant', 'inviter']);

        $html = (new TenantInviteMail($invitation))->render();

        $this->assertStringContainsString('Acme', $html);
        $this->assertStringContainsString('Member', $html);
    }
}
