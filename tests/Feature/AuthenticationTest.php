<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a user can log in with valid credentials', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'correct-password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('login fails with a wrong password and keeps the email', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    $this->from(route('login'))
        ->post(route('login.submit'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login fails for an unknown user', function () {
    $this->post(route('login.submit'), ['email' => 'nobody@example.com', 'password' => 'x'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login validates required fields and email format', function () {
    $this->post(route('login.submit'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors(['email', 'password']);
});

test('logout ends the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('authenticated users are redirected away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect();
});

test('guests are redirected to login for protected pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['dashboard', 'projects.index', 'deliveries.index']);

test('guests cannot publish events through the api', function () {
    $project = Project::factory()->create();

    $this->postJson(route('api.v1.projects.events.store', $project), ['type' => 'a.b', 'data' => ['x' => 1]])
        ->assertUnauthorized();

    $this->assertDatabaseCount('webhook_events', 0);
});

test('the demo login creates the demo user once and signs in', function () {
    $this->post(route('login.test-user'))->assertRedirect(route('dashboard'));
    $this->post(route('logout'));
    $this->post(route('login.test-user'))->assertRedirect(route('dashboard'));

    expect(User::query()->where('email', 'demo@hookforge.test')->count())->toBe(1);
});
