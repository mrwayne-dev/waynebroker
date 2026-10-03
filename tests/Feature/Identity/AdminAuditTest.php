<?php

use App\Domains\Identity\AdminAuditEvent;
use App\Domains\Identity\AdminAuditRecorder;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Covers the recorder and the catch-all middleware that retire Maveren H-1.
 *
 * The routes here are declared in the test rather than taken from the
 * application, because no /admin route exists yet — Checkpoint 7 builds the
 * first. The point of landing the audit log before the surface it audits is
 * that the surface cannot arrive un-audited.
 */
function adminRoute(string $method, string $uri, ?string $name = null): void
{
    $route = Route::middleware('web')->{$method}($uri, fn () => response('ok'));

    if ($name !== null) {
        $route->name($name);
    }
}

test('the recorder writes an actor, subject, states and reason', function () {
    $admin = User::factory()->create();
    $subject = User::factory()->create();

    $this->actingAs($admin);

    $event = app(AdminAuditRecorder::class)->record(
        action: 'wallet.adjust',
        subject: $subject,
        before: ['balance_cents' => 1000],
        after: ['balance_cents' => 1500],
        reason: 'Goodwill credit, ticket #42',
    );

    expect($event->admin_id)->toBe($admin->id)
        ->and($event->action)->toBe('wallet.adjust')
        ->and($event->subject_type)->toBe($subject->getMorphClass())
        ->and($event->subject_id)->toBe($subject->id)
        ->and($event->before)->toBe(['balance_cents' => 1000])
        ->and($event->after)->toBe(['balance_cents' => 1500])
        ->and($event->reason)->toBe('Goodwill credit, ticket #42')
        ->and($event->created_at)->not->toBeNull();
});

test('the recorder accepts an action alone', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $event = app(AdminAuditRecorder::class)->record(action: 'admin.login');

    expect($event->action)->toBe('admin.login')
        ->and($event->subject_type)->toBeNull()
        ->and($event->subject_id)->toBeNull()
        ->and($event->before)->toBeNull()
        ->and($event->after)->toBeNull()
        ->and($event->reason)->toBeNull();
});

test('the recorder records an unauthenticated actor as null rather than skipping', function () {
    $event = app(AdminAuditRecorder::class)->record(action: 'admin.attempted');

    expect($event->admin_id)->toBeNull()
        ->and(AdminAuditEvent::query()->count())->toBe(1);
});

test('an audit row keeps no updated_at', function () {
    $this->actingAs(User::factory()->create());

    $event = app(AdminAuditRecorder::class)->record(action: 'admin.login');

    expect($event->getAttributes())->not->toHaveKey('updated_at');
});

dataset('writing verbs', [
    'post' => ['post'],
    'put' => ['put'],
    'patch' => ['patch'],
    'delete' => ['delete'],
]);

test('the middleware records every writing verb on an admin route', function (string $verb) {
    adminRoute($verb, 'admin/things');

    $admin = User::factory()->create();

    $this->actingAs($admin)->{$verb}('admin/things')->assertOk();

    $event = AdminAuditEvent::query()->sole();

    expect($event->admin_id)->toBe($admin->id)
        ->and($event->action)->toBe($verb.' admin/things')
        ->and($event->after['status'])->toBe(200);
})->with('writing verbs');

test('the middleware prefers the route name when there is one', function () {
    adminRoute('post', 'admin/wallets/1/adjust', 'admin.wallets.adjust');

    $this->actingAs(User::factory()->create())
        ->post('admin/wallets/1/adjust')
        ->assertOk();

    expect(AdminAuditEvent::query()->sole()->action)->toBe('admin.wallets.adjust');
});

test('the middleware ignores reads', function () {
    adminRoute('get', 'admin/things');

    $this->actingAs(User::factory()->create())->get('admin/things')->assertOk();

    expect(AdminAuditEvent::query()->count())->toBe(0);
});

test('the middleware ignores writes outside the admin prefix', function () {
    adminRoute('post', 'settings/things');

    $this->actingAs(User::factory()->create())->post('settings/things')->assertOk();

    expect(AdminAuditEvent::query()->count())->toBe(0);
});

test('a refused admin write is still recorded', function () {
    Route::middleware('web')->post('admin/forbidden', fn () => abort(403));

    $this->actingAs(User::factory()->create())->post('admin/forbidden')->assertForbidden();

    expect(AdminAuditEvent::query()->sole()->after['status'])->toBe(403);
});
