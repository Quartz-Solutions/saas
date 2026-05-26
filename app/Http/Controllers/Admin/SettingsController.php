<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsUpdateRequest;
use App\Support\Admin\AppSettingsService;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\StripeClient;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private readonly AppSettingsService $settings) {}

    public function index(): Response
    {
        return Inertia::render('admin/settings/index', [
            'groups' => $this->settings->presentForAdmin(),
        ]);
    }

    public function update(SettingsUpdateRequest $request, string $group): RedirectResponse
    {
        if ($this->settings->group($group) === null) {
            abort(404);
        }

        $this->settings->updateGroup($group, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':group settings saved.', ['group' => $this->settings->group($group)['label']]),
        ]);

        return back();
    }

    public function test(Request $request, string $group): JsonResponse
    {
        if ($this->settings->group($group) === null) {
            abort(404);
        }

        try {
            $message = match ($group) {
                'mail' => $this->testMail($request),
                'stripe' => $this->testStripe(),
                'sentry' => $this->testSentry(),
                'slack' => $this->testSlack(),
                'aws' => $this->testAws(),
                default => throw new \RuntimeException(__('No connection test for this group.')),
            };

            return response()->json(['ok' => true, 'message' => $message]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    protected function testMail(Request $request): string
    {
        $email = (string) $request->user()->email;

        Mail::raw(
            "This is a test message from your SaaS boilerplate.\n\nSent at: ".now()->toDateTimeString(),
            function ($msg) use ($email) {
                $msg->to($email)->subject('Mail connection test');
            }
        );

        return __('Test email queued to :email — check your inbox.', ['email' => $email]);
    }

    protected function testStripe(): string
    {
        $secret = config('billing.gateways.stripe.secret');
        if (! $secret) {
            throw new \RuntimeException(__('Stripe secret key is not set.'));
        }

        $client = new StripeClient(['api_key' => $secret]);
        $account = $client->accounts->retrieve();

        return __('Connected to Stripe account :id (:env).', [
            'id' => $account->id,
            'env' => str_starts_with($secret, 'sk_live_') ? 'live' : 'test',
        ]);
    }

    protected function testSentry(): string
    {
        $dsn = config('sentry.dsn');
        if (! $dsn) {
            throw new \RuntimeException(__('Sentry DSN is not set.'));
        }

        if (! function_exists('\\Sentry\\captureMessage')) {
            throw new \RuntimeException(__('Sentry SDK is not installed.'));
        }

        \Sentry\captureMessage('Admin settings test event — '.now()->toIso8601String());

        return __('Test event sent to Sentry.');
    }

    protected function testSlack(): string
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');
        $channel = config('services.slack.notifications.channel');

        if (! $token || ! $channel) {
            throw new \RuntimeException(__('Slack token or channel is not set.'));
        }

        $res = Http::withToken($token)
            ->asJson()
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channel,
                'text' => 'Test message from admin settings — '.now()->toDateTimeString(),
            ]);

        $body = $res->json();
        if (! ($body['ok'] ?? false)) {
            throw new \RuntimeException('Slack error: '.($body['error'] ?? 'unknown'));
        }

        return __('Test message posted to :channel.', ['channel' => $channel]);
    }

    protected function testAws(): string
    {
        $key = config('filesystems.disks.s3.key');
        $secret = config('filesystems.disks.s3.secret');
        $region = config('filesystems.disks.s3.region');
        $bucket = config('filesystems.disks.s3.bucket');

        if (! $key || ! $secret || ! $bucket) {
            throw new \RuntimeException(__('AWS access key, secret, and bucket are required.'));
        }

        if (! class_exists(S3Client::class)) {
            throw new \RuntimeException(__('aws/aws-sdk-php is not installed (run composer require league/flysystem-aws-s3-v3).'));
        }

        $client = new S3Client([
            'region' => $region ?: 'us-east-1',
            'version' => 'latest',
            'credentials' => ['key' => $key, 'secret' => $secret],
        ]);

        $client->headBucket(['Bucket' => $bucket]);

        return __('Bucket :bucket is reachable.', ['bucket' => $bucket]);
    }
}
