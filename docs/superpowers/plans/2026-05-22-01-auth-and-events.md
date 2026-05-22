# Auth & Events — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install Laravel Breeze (Inertia/Vue), build Event CRUD, per-event collaborator roles, and email invitation flow.

**Architecture:** Standard Laravel MVC with Inertia responses. Events are owned by one user; collaborators join via a pivot table with an `editor`/`viewer` role. Invitations for non-registered emails are stored with a token and accepted automatically on registration.

**Tech Stack:** Laravel 13, Inertia.js, Vue 3, Laravel Breeze, Tailwind CSS 4, SQLite (tests), MariaDB (dev/prod)

---

## File Map

**Created:**
- `database/migrations/..._create_events_table.php`
- `database/migrations/..._create_event_collaborators_table.php`
- `database/migrations/..._create_event_invitations_table.php`
- `database/factories/EventFactory.php`
- `app/Models/Event.php`
- `app/Models/EventCollaborator.php`
- `app/Models/EventInvitation.php`
- `app/Policies/EventPolicy.php`
- `app/Http/Controllers/EventController.php`
- `app/Http/Controllers/EventCollaboratorController.php`
- `app/Http/Controllers/InvitationController.php`
- `app/Http/Requests/StoreEventRequest.php`
- `app/Http/Requests/UpdateEventRequest.php`
- `app/Http/Requests/InviteCollaboratorRequest.php`
- `app/Mail/EventInvitationMail.php`
- `resources/views/emails/event-invitation.blade.php`
- `resources/js/Pages/Dashboard.vue`
- `resources/js/Pages/Events/Create.vue`
- `resources/js/Pages/Events/Edit.vue`
- `resources/js/Pages/Events/Show.vue` (stub)
- `tests/Feature/EventCrudTest.php`
- `tests/Feature/EventCollaboratorTest.php`
- `tests/Feature/EventInvitationTest.php`

**Modified:**
- `vite.config.js` — restore Tailwind CSS 4 after Breeze overwrites it
- `routes/web.php`
- `app/Models/User.php` — add event relationships
- `app/Http/Controllers/Auth/RegisteredUserController.php` — accept pending invitations on register

---

### Task 1: Install Laravel Breeze and reconcile Tailwind CSS 4

**Files:**
- Modify: `vite.config.js`
- Modify: `resources/css/app.css`

- [ ] **Step 1: Install Breeze**

```bash
composer require laravel/breeze --dev
php artisan breeze:install vue
npm install
```

Expected output: Breeze publishes auth pages, layouts, `routes/auth.php`, updates `app/Http/Kernel.php` and `vite.config.js`.

- [ ] **Step 2: Restore Tailwind CSS 4 in vite.config.js**

Breeze overwrites `vite.config.js` with a Tailwind CSS 3 setup. Replace the file with:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

- [ ] **Step 3: Fix app.css**

Breeze publishes a Tailwind CSS 3 `app.css`. Replace `resources/css/app.css` with:

```css
@import "tailwindcss";
```

- [ ] **Step 4: Remove any published tailwind.config.js**

```bash
rm -f tailwind.config.js
```

- [ ] **Step 5: Build and verify**

```bash
npm run build
php artisan migrate
php artisan serve
```

Visit `http://localhost:8000/register` — the registration page should render without errors.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: install Laravel Breeze with Inertia/Vue"
```

---

### Task 2: Database migrations

**Files:**
- Create: `database/migrations/..._create_events_table.php`
- Create: `database/migrations/..._create_event_collaborators_table.php`
- Create: `database/migrations/..._create_event_invitations_table.php`

- [ ] **Step 1: Generate migration files**

```bash
php artisan make:migration create_events_table
php artisan make:migration create_event_collaborators_table
php artisan make:migration create_event_invitations_table
```

- [ ] **Step 2: Write events migration**

```php
public function up(): void
{
    Schema::create('events', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->text('description')->nullable();
        $table->timestamps();
    });
}
```

- [ ] **Step 3: Write event_collaborators migration**

```php
public function up(): void
{
    Schema::create('event_collaborators', function (Blueprint $table) {
        $table->foreignId('event_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->enum('role', ['editor', 'viewer']);
        $table->primary(['event_id', 'user_id']);
        $table->timestamps();
    });
}
```

- [ ] **Step 4: Write event_invitations migration**

```php
public function up(): void
{
    Schema::create('event_invitations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('event_id')->constrained()->cascadeOnDelete();
        $table->string('email');
        $table->enum('role', ['editor', 'viewer']);
        $table->string('token')->unique();
        $table->timestamp('expires_at');
        $table->timestamp('created_at')->useCurrent();
    });
}
```

- [ ] **Step 5: Run migrations**

```bash
php artisan migrate
```

Expected: 3 new tables created.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/
git commit -m "feat: add events, event_collaborators, event_invitations migrations"
```

---

### Task 3: Models and factory

**Files:**
- Create: `app/Models/Event.php`
- Create: `app/Models/EventCollaborator.php`
- Create: `app/Models/EventInvitation.php`
- Create: `database/factories/EventFactory.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Write Event model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_collaborators')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->isOwnedBy($user)
            || $this->collaborators()->where('user_id', $user->id)->exists();
    }

    public function roleFor(User $user): ?string
    {
        if ($this->isOwnedBy($user)) {
            return 'owner';
        }
        $collaborator = $this->collaborators()->where('user_id', $user->id)->first();
        return $collaborator?->pivot?->role;
    }
}
```

- [ ] **Step 2: Write EventCollaborator model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EventCollaborator extends Pivot
{
    protected $table = 'event_collaborators';

    protected $fillable = ['role'];
}
```

- [ ] **Step 3: Write EventInvitation model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvitation extends Model
{
    public $timestamps = false;

    protected $fillable = ['email', 'role', 'token', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
```

- [ ] **Step 4: Add relationships to User model**

In `app/Models/User.php`, add inside the class:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function events(): HasMany
{
    return $this->hasMany(Event::class);
}

public function collaboratingEvents(): BelongsToMany
{
    return $this->belongsToMany(Event::class, 'event_collaborators')
        ->withPivot('role')
        ->withTimestamps();
}
```

- [ ] **Step 5: Write EventFactory**

```bash
php artisan make:factory EventFactory --model=Event
```

```php
public function definition(): array
{
    return [
        'user_id' => User::factory(),
        'name' => fake()->sentence(3),
        'description' => fake()->paragraph(),
    ];
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Models/ database/factories/EventFactory.php
git commit -m "feat: add Event, EventCollaborator, EventInvitation models"
```

---

### Task 4: EventPolicy

**Files:**
- Create: `app/Policies/EventPolicy.php`

- [ ] **Step 1: Generate policy**

```bash
php artisan make:policy EventPolicy --model=Event
```

- [ ] **Step 2: Write policy methods**

```php
<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $event->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function delete(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function manageCollaborators(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function duplicate(User $user, Event $event): bool
    {
        return $event->isAccessibleBy($user);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Policies/EventPolicy.php
git commit -m "feat: add EventPolicy"
```

---

### Task 5: Event CRUD — tests then controller

**Files:**
- Create: `tests/Feature/EventCrudTest.php`
- Create: `app/Http/Controllers/EventController.php`
- Create: `app/Http/Requests/StoreEventRequest.php`
- Create: `app/Http/Requests/UpdateEventRequest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('events', 1)
        );
    }

    public function test_authenticated_user_can_create_event(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/events', [
            'name' => 'Tour de France 2026',
            'description' => 'Annual cycling race',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', [
            'name' => 'Tour de France 2026',
            'user_id' => $user->id,
        ]);
    }

    public function test_event_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/events', ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_unauthenticated_user_cannot_create_event(): void
    {
        $response = $this->post('/events', ['name' => 'Test']);

        $response->assertRedirect('/login');
    }

    public function test_owner_can_update_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patch("/events/{$event->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'name' => 'Updated Name']);
    }

    public function test_non_owner_cannot_update_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->patch("/events/{$event->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_can_delete_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/events/{$event->id}");

        $response->assertRedirect('/');
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_non_owner_cannot_delete_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->delete("/events/{$event->id}");

        $response->assertForbidden();
    }

    public function test_collaborator_can_view_event(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($collaborator->id, ['role' => 'viewer']);

        $response = $this->actingAs($collaborator)->get("/events/{$event->id}");

        $response->assertOk();
    }

    public function test_non_collaborator_cannot_view_event(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger)->get("/events/{$event->id}");

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test tests/Feature/EventCrudTest.php
```

Expected: All tests fail (routes and controllers don't exist yet).

- [ ] **Step 3: Write form requests**

```bash
php artisan make:request StoreEventRequest
php artisan make:request UpdateEventRequest
```

`StoreEventRequest`:
```php
public function authorize(): bool { return true; }

public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
    ];
}
```

`UpdateEventRequest` — identical rules.

- [ ] **Step 4: Write EventController**

```bash
php artisan make:controller EventController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class EventController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $events = Event::where('user_id', $user->id)
            ->orWhereHas('collaborators', fn ($q) => $q->where('user_id', $user->id))
            ->withCount('collaborators')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'description' => $e->description,
                'role' => $e->roleFor($user),
                'collaborators_count' => $e->collaborators_count,
                'created_at' => $e->created_at->toDateString(),
            ]);

        return Inertia::render('Dashboard', compact('events'));
    }

    public function create(): Response
    {
        return Inertia::render('Events/Create');
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = $request->user()->events()->create($request->validated());
        return redirect()->route('events.show', $event);
    }

    public function show(Event $event): Response
    {
        $this->authorize('view', $event);

        return Inertia::render('Events/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'role' => $event->roleFor(auth()->user()),
            ],
        ]);
    }

    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);

        $collaborators = $event->collaborators->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role,
        ]);

        $pendingInvitations = $event->invitations()
            ->where('expires_at', '>', now())
            ->get(['id', 'email', 'role', 'expires_at']);

        return Inertia::render('Events/Edit', [
            'event' => $event->only('id', 'name', 'description'),
            'collaborators' => $collaborators,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $event->update($request->validated());
        return redirect()->route('events.edit', $event);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $event->delete();
        return redirect()->route('dashboard');
    }
}
```

- [ ] **Step 5: Add routes (minimal, just what tests need)**

In `routes/web.php`:

```php
<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('events', EventController::class)->except('index');
});

require __DIR__.'/auth.php';
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/EventCrudTest.php
```

Expected: All tests pass. Fix any failures before continuing.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/EventController.php app/Http/Requests/ routes/web.php tests/Feature/EventCrudTest.php
git commit -m "feat: event CRUD with authorization"
```

---

### Task 6: Event duplication

**Files:**
- Modify: `app/Http/Controllers/EventController.php`
- Modify: `tests/Feature/EventCrudTest.php`

- [ ] **Step 1: Add failing test**

Append to `tests/Feature/EventCrudTest.php`:

```php
public function test_any_user_with_access_can_duplicate_event(): void
{
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Race 2026']);
    $event->collaborators()->attach($viewer->id, ['role' => 'viewer']);

    $response = $this->actingAs($viewer)->post("/events/{$event->id}/duplicate");

    $response->assertRedirect();
    $this->assertDatabaseCount('events', 2);
    $newEvent = Event::where('user_id', $viewer->id)->first();
    $this->assertNotNull($newEvent);
    $this->assertSame('Race 2026 (copy)', $newEvent->name);
    $this->assertCount(0, $newEvent->collaborators);
}

public function test_stranger_cannot_duplicate_event(): void
{
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($stranger)->post("/events/{$event->id}/duplicate");

    $response->assertForbidden();
}
```

- [ ] **Step 2: Run to confirm failure**

```bash
php artisan test tests/Feature/EventCrudTest.php --filter=duplicate
```

- [ ] **Step 3: Add duplicate method to EventController**

```php
public function duplicate(Event $event): RedirectResponse
{
    $this->authorize('duplicate', $event);

    $copy = $event->replicate(['user_id']);
    $copy->user_id = auth()->id();
    $copy->name = $event->name . ' (copy)';
    $copy->save();

    return redirect()->route('events.show', $copy);
}
```

- [ ] **Step 4: Add route**

In `routes/web.php`, inside the auth group:

```php
Route::post('events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/EventCrudTest.php
```

Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/EventController.php routes/web.php tests/Feature/EventCrudTest.php
git commit -m "feat: event duplication"
```

---

### Task 7: Collaborator management — tests then controller

**Files:**
- Create: `tests/Feature/EventCollaboratorTest.php`
- Create: `app/Http/Controllers/EventCollaboratorController.php`
- Create: `app/Http/Requests/InviteCollaboratorRequest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCollaboratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_existing_user_as_collaborator(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create(['email' => 'co@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->post("/events/{$event->id}/collaborators", [
            'email' => 'co@example.com',
            'role' => 'editor',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
        ]);
    }

    public function test_non_owner_cannot_add_collaborator(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($editor->id, ['role' => 'editor']);

        $response = $this->actingAs($editor)->post("/events/{$event->id}/collaborators", [
            'email' => 'stranger@example.com',
            'role' => 'viewer',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_can_update_collaborator_role(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($collaborator->id, ['role' => 'viewer']);

        $response = $this->actingAs($owner)->patch(
            "/events/{$event->id}/collaborators/{$collaborator->id}",
            ['role' => 'editor']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
        ]);
    }

    public function test_owner_can_remove_collaborator(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $event->collaborators()->attach($collaborator->id, ['role' => 'viewer']);

        $response = $this->actingAs($owner)->delete(
            "/events/{$event->id}/collaborators/{$collaborator->id}"
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $collaborator->id,
        ]);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

```bash
php artisan test tests/Feature/EventCollaboratorTest.php
```

- [ ] **Step 3: Write InviteCollaboratorRequest**

```bash
php artisan make:request InviteCollaboratorRequest
```

```php
public function authorize(): bool { return true; }

public function rules(): array
{
    return [
        'email' => ['required', 'email'],
        'role' => ['required', 'in:editor,viewer'],
    ];
}
```

- [ ] **Step 4: Write EventCollaboratorController**

```bash
php artisan make:controller EventCollaboratorController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteCollaboratorRequest;
use App\Mail\EventInvitationMail;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EventCollaboratorController extends Controller
{
    public function store(InviteCollaboratorRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('manageCollaborators', $event);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $event->collaborators()->syncWithoutDetaching([
                $user->id => ['role' => $request->role],
            ]);
        } else {
            $invitation = EventInvitation::updateOrCreate(
                ['event_id' => $event->id, 'email' => $request->email],
                [
                    'role' => $request->role,
                    'token' => Str::random(32),
                    'expires_at' => now()->addDays(7),
                ]
            );
            Mail::to($request->email)->send(new EventInvitationMail($invitation));
        }

        return redirect()->route('events.edit', $event);
    }

    public function update(Request $request, Event $event, User $user): RedirectResponse
    {
        $this->authorize('manageCollaborators', $event);
        $request->validate(['role' => ['required', 'in:editor,viewer']]);
        $event->collaborators()->updateExistingPivot($user->id, ['role' => $request->role]);
        return redirect()->route('events.edit', $event);
    }

    public function destroy(Event $event, User $user): RedirectResponse
    {
        $this->authorize('manageCollaborators', $event);
        $event->collaborators()->detach($user->id);
        return redirect()->route('events.edit', $event);
    }
}
```

- [ ] **Step 5: Add routes to web.php**

```php
Route::middleware('auth')->group(function () {
    // inside existing group, add:
    Route::post('events/{event}/collaborators', [EventCollaboratorController::class, 'store'])
        ->name('events.collaborators.store');
    Route::patch('events/{event}/collaborators/{user}', [EventCollaboratorController::class, 'update'])
        ->name('events.collaborators.update');
    Route::delete('events/{event}/collaborators/{user}', [EventCollaboratorController::class, 'destroy'])
        ->name('events.collaborators.destroy');
});
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/EventCollaboratorTest.php
```

Expected: All pass (Mail::fake() not needed since email is not tested here yet).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/EventCollaboratorController.php app/Http/Requests/InviteCollaboratorRequest.php routes/web.php tests/Feature/EventCollaboratorTest.php
git commit -m "feat: collaborator management (add, update role, remove)"
```

---

### Task 8: Email invitation flow

**Files:**
- Create: `app/Mail/EventInvitationMail.php`
- Create: `resources/views/emails/event-invitation.blade.php`
- Create: `tests/Feature/EventInvitationTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature;

use App\Mail\EventInvitationMail;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviting_unknown_email_sends_invitation_mail(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->post("/events/{$event->id}/collaborators", [
            'email' => 'new@example.com',
            'role' => 'editor',
        ]);

        Mail::assertSent(EventInvitationMail::class, fn ($mail) =>
            $mail->hasTo('new@example.com')
        );
        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $event->id,
            'email' => 'new@example.com',
            'role' => 'editor',
        ]);
    }

    public function test_visiting_expired_token_shows_error(): void
    {
        $invitation = EventInvitation::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get("/invitations/{$invitation->token}");

        $response->assertInertia(fn ($page) => $page
            ->component('Invitations/Expired')
        );
    }

    public function test_authenticated_user_visiting_valid_token_gets_added_immediately(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $invitation = EventInvitation::factory()->create([
            'event_id' => $event->id,
            'email' => $user->email,
            'role' => 'editor',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get("/invitations/{$invitation->token}");

        $response->assertRedirect('/');
        $this->assertDatabaseHas('event_collaborators', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'editor',
        ]);
        $this->assertDatabaseMissing('event_invitations', ['id' => $invitation->id]);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

```bash
php artisan test tests/Feature/EventInvitationTest.php
```

- [ ] **Step 3: Create EventInvitationMail**

```bash
php artisan make:mail EventInvitationMail
```

```php
<?php

namespace App\Mail;

use App\Models\EventInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EventInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to collaborate on {$this->invitation->event->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event-invitation',
        );
    }
}
```

- [ ] **Step 4: Create email view**

Create `resources/views/emails/event-invitation.blade.php`:

```blade
<p>Hello,</p>
<p>You have been invited to collaborate on <strong>{{ $invitation->event->name }}</strong> as a <strong>{{ $invitation->role }}</strong>.</p>
<p>
    <a href="{{ url('/register?invitation=' . $invitation->token) }}">
        Create your account to accept this invitation
    </a>
</p>
<p>This invitation expires on {{ $invitation->expires_at->toFormattedDateString() }}.</p>
```

- [ ] **Step 5: Create EventInvitation factory**

```bash
php artisan make:factory EventInvitationFactory --model=EventInvitation
```

```php
public function definition(): array
{
    return [
        'event_id' => Event::factory(),
        'email' => fake()->safeEmail(),
        'role' => 'viewer',
        'token' => \Illuminate\Support\Str::random(32),
        'expires_at' => now()->addDays(7),
    ];
}
```

- [ ] **Step 6: Create InvitationController**

```bash
php artisan make:controller InvitationController
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\EventInvitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = EventInvitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            return Inertia::render('Invitations/Expired');
        }

        if (auth()->check()) {
            $invitation->event->collaborators()->syncWithoutDetaching([
                auth()->id() => ['role' => $invitation->role],
            ]);
            $invitation->delete();
            return redirect()->route('dashboard');
        }

        return redirect("/register?invitation={$token}");
    }
}
```

- [ ] **Step 7: Create Invitations/Expired Vue page**

Create `resources/js/Pages/Invitations/Expired.vue`:

```vue
<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head } from '@inertiajs/vue3'
</script>

<template>
    <GuestLayout>
        <Head title="Invitation Expired" />
        <div class="text-center">
            <h1 class="text-xl font-semibold mb-2">This invitation has expired</h1>
            <p class="text-gray-600">Please ask the event owner to send a new invitation.</p>
        </div>
    </GuestLayout>
</template>
```

- [ ] **Step 8: Add invitation route**

In `routes/web.php`:

```php
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
```

- [ ] **Step 9: Run tests**

```bash
php artisan test tests/Feature/EventInvitationTest.php
```

Expected: All pass.

- [ ] **Step 10: Commit**

```bash
git add app/Mail/ app/Http/Controllers/InvitationController.php resources/views/emails/ resources/js/Pages/Invitations/ database/factories/EventInvitationFactory.php routes/web.php tests/Feature/EventInvitationTest.php
git commit -m "feat: email invitation flow for unregistered users"
```

---

### Task 9: Accept invitations on registration

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Modify: `tests/Feature/EventInvitationTest.php`

- [ ] **Step 1: Add failing test**

Append to `tests/Feature/EventInvitationTest.php`:

```php
public function test_pending_invitations_are_accepted_when_user_registers(): void
{
    $owner = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);
    EventInvitation::factory()->create([
        'event_id' => $event->id,
        'email' => 'invited@example.com',
        'role' => 'editor',
        'expires_at' => now()->addDays(7),
    ]);

    $this->post('/register', [
        'name' => 'New User',
        'email' => 'invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $newUser = User::where('email', 'invited@example.com')->first();
    $this->assertDatabaseHas('event_collaborators', [
        'event_id' => $event->id,
        'user_id' => $newUser->id,
        'role' => 'editor',
    ]);
    $this->assertDatabaseMissing('event_invitations', ['email' => 'invited@example.com']);
}

public function test_register_page_prefills_email_from_invitation_token(): void
{
    $invitation = EventInvitation::factory()->create([
        'email' => 'invited@example.com',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->get("/register?invitation={$invitation->token}");

    $response->assertInertia(fn ($page) => $page
        ->component('Auth/Register')
        ->where('invitationEmail', 'invited@example.com')
    );
}
```

- [ ] **Step 2: Run to confirm failure**

```bash
php artisan test tests/Feature/EventInvitationTest.php --filter=register
```

- [ ] **Step 3: Modify RegisteredUserController**

In `app/Http/Controllers/Auth/RegisteredUserController.php`, update the `create` and `store` methods:

```php
use App\Models\EventInvitation;

public function create(Request $request): Response
{
    $invitationEmail = null;
    if ($token = $request->query('invitation')) {
        $invitation = EventInvitation::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
        $invitationEmail = $invitation?->email;
    }

    return Inertia::render('Auth/Register', [
        'invitationEmail' => $invitationEmail,
    ]);
}

// Inside store(), after Auth::login($user), before the return:
EventInvitation::where('email', $user->email)
    ->where('expires_at', '>', now())
    ->each(function (EventInvitation $invitation) use ($user) {
        $invitation->event->collaborators()->syncWithoutDetaching([
            $user->id => ['role' => $invitation->role],
        ]);
        $invitation->delete();
    });
```

- [ ] **Step 4: Update Register.vue to use invitationEmail prop**

In `resources/js/Pages/Auth/Register.vue`, add to `<script setup>`:

```js
const props = defineProps({
    invitationEmail: {
        type: String,
        default: null,
    },
})

const form = useForm({
    name: '',
    email: props.invitationEmail ?? '',
    password: '',
    password_confirmation: '',
})
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/EventInvitationTest.php
```

Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Auth/RegisteredUserController.php resources/js/Pages/Auth/Register.vue tests/Feature/EventInvitationTest.php
git commit -m "feat: accept pending invitations on registration"
```

---

### Task 10: Dashboard Vue page

**Files:**
- Create: `resources/js/Pages/Dashboard.vue`

- [ ] **Step 1: Write Dashboard.vue**

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

defineProps({
    events: Array,
})

function duplicate(id) {
    router.post(route('events.duplicate', id))
}
</script>

<template>
    <Head title="My Events" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">My Events</h2>
                <Link :href="route('events.create')"
                      class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800">
                    New Event
                </Link>
            </div>
        </template>

        <div class="py-8 max-w-5xl mx-auto px-4">
            <div v-if="events.length === 0" class="text-center text-gray-500 py-16">
                No events yet. Create your first event to get started.
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="event in events" :key="event.id"
                     class="border rounded-xl p-5 hover:shadow-md transition-shadow bg-white">
                    <div class="flex items-start justify-between mb-2">
                        <Link :href="route('events.show', event.id)"
                              class="font-semibold text-lg hover:underline">
                            {{ event.name }}
                        </Link>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 capitalize ml-2 shrink-0">
                            {{ event.role }}
                        </span>
                    </div>
                    <p v-if="event.description" class="text-sm text-gray-500 mb-4 line-clamp-2">
                        {{ event.description }}
                    </p>
                    <div class="flex items-center gap-3 text-sm">
                        <Link :href="route('events.show', event.id)"
                              class="text-blue-600 hover:underline">Open</Link>
                        <Link v-if="event.role === 'owner'"
                              :href="route('events.edit', event.id)"
                              class="text-gray-500 hover:underline">Settings</Link>
                        <button @click="duplicate(event.id)"
                                class="text-gray-500 hover:underline">Duplicate</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Verify in browser**

```bash
npm run dev
php artisan serve
```

Log in and visit `http://localhost:8000/`. Create a test event and verify it appears on the dashboard.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Dashboard.vue
git commit -m "feat: dashboard page with event list"
```

---

### Task 11: Events/Create and Events/Edit Vue pages

**Files:**
- Create: `resources/js/Pages/Events/Create.vue`
- Create: `resources/js/Pages/Events/Edit.vue`

- [ ] **Step 1: Write Events/Create.vue**

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    description: '',
})
</script>

<template>
    <Head title="New Event" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold">New Event</h2>
        </template>

        <div class="py-8 max-w-2xl mx-auto px-4">
            <form @submit.prevent="form.post(route('events.store'))">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Event name</label>
                    <input v-model="form.name" type="text" autofocus
                           class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black" />
                    <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea v-model="form.description" rows="3"
                              class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black" />
                </div>
                <button type="submit" :disabled="form.processing"
                        class="px-5 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50">
                    Create Event
                </button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Write Events/Edit.vue**

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    event: Object,
    collaborators: Array,
    pendingInvitations: Array,
})

const form = useForm({
    name: props.event.name,
    description: props.event.description ?? '',
})

const inviteForm = useForm({
    email: '',
    role: 'editor',
})

function removeCollaborator(userId) {
    router.delete(route('events.collaborators.destroy', [props.event.id, userId]))
}

function updateRole(userId, role) {
    router.patch(route('events.collaborators.update', [props.event.id, userId]), { role })
}

function deleteEvent() {
    if (confirm('Delete this event? This cannot be undone.')) {
        router.delete(route('events.destroy', props.event.id))
    }
}
</script>

<template>
    <Head :title="`Settings — ${event.name}`" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold">Event Settings</h2>
        </template>

        <div class="py-8 max-w-2xl mx-auto px-4 space-y-8">
            <!-- Event details -->
            <section>
                <h3 class="font-semibold mb-3">Details</h3>
                <form @submit.prevent="form.patch(route('events.update', event.id))">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Name</label>
                        <input v-model="form.name" type="text"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black" />
                        <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Description</label>
                        <textarea v-model="form.description" rows="3"
                                  class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black" />
                    </div>
                    <button type="submit" :disabled="form.processing"
                            class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-50">
                        Save Changes
                    </button>
                </form>
            </section>

            <!-- Collaborators -->
            <section>
                <h3 class="font-semibold mb-3">Collaborators</h3>

                <div v-if="collaborators.length > 0" class="border rounded-xl overflow-hidden mb-4">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="c in collaborators" :key="c.id" class="border-t first:border-0">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ c.name }}</div>
                                    <div class="text-gray-500">{{ c.email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <select :value="c.role" @change="updateRole(c.id, $event.target.value)"
                                            class="border rounded px-2 py-1 text-sm">
                                        <option value="editor">Editor</option>
                                        <option value="viewer">Viewer</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="removeCollaborator(c.id)"
                                            class="text-red-500 hover:underline text-sm">Remove</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pendingInvitations.length > 0" class="mb-4">
                    <p class="text-sm text-gray-500 mb-2">Pending invitations:</p>
                    <ul class="text-sm space-y-1">
                        <li v-for="inv in pendingInvitations" :key="inv.id" class="text-gray-600">
                            {{ inv.email }} ({{ inv.role }}) — expires {{ inv.expires_at }}
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="inviteForm.post(route('events.collaborators.store', event.id), { onSuccess: () => inviteForm.reset() })">
                    <div class="flex gap-2">
                        <input v-model="inviteForm.email" type="email" placeholder="Email address"
                               class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-black" />
                        <select v-model="inviteForm.role"
                                class="border rounded-lg px-3 py-2 text-sm">
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                        <button type="submit" :disabled="inviteForm.processing"
                                class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium disabled:opacity-50">
                            Invite
                        </button>
                    </div>
                    <p v-if="inviteForm.errors.email" class="text-red-500 text-sm mt-1">{{ inviteForm.errors.email }}</p>
                </form>
            </section>

            <!-- Danger zone -->
            <section>
                <h3 class="font-semibold mb-3 text-red-600">Danger Zone</h3>
                <button @click="deleteEvent"
                        class="px-4 py-2 border border-red-500 text-red-500 rounded-lg text-sm hover:bg-red-50">
                    Delete Event
                </button>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 3: Verify in browser**

Create and edit an event. Invite a collaborator. Verify all forms work.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Events/
git commit -m "feat: create and edit event pages with collaborator management"
```

---

### Task 12: Events/Show stub

**Files:**
- Create: `resources/js/Pages/Events/Show.vue`

- [ ] **Step 1: Write stub Show.vue**

This is a placeholder. The map editor is built in Plan 2.

```vue
<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    event: Object,
})
</script>

<template>
    <Head :title="event.name" />
    <div class="h-screen flex flex-col">
        <div class="px-4 py-3 border-b flex items-center gap-4 bg-white">
            <span class="font-semibold">{{ event.name }}</span>
            <span class="text-xs text-gray-500 capitalize">{{ event.role }}</span>
        </div>
        <div class="flex-1 flex items-center justify-center text-gray-400">
            Map editor coming in Plan 2
        </div>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Events/Show.vue
git commit -m "feat: events/show stub (map editor placeholder)"
```

---

### Task 13: Event plans — migration, model, and default plan on creation

**Files:**
- Create: `database/migrations/..._create_event_plans_table.php`
- Create: `app/Models/EventPlan.php`
- Create: `database/factories/EventPlanFactory.php`
- Modify: `app/Models/Event.php`
- Modify: `app/Http/Controllers/EventController.php`
- Modify: `tests/Feature/EventCrudTest.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/EventCrudTest.php`:

```php
public function test_creating_event_automatically_creates_default_plan(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)->post('/events', ['name' => 'Race 2026']);

    $event = Event::where('name', 'Race 2026')->first();
    $this->assertCount(1, $event->plans);
    $this->assertSame('Plan 1', $event->plans->first()->name);
}

public function test_duplicating_event_copies_all_plans(): void
{
    $owner = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $event->plans()->create(['name' => 'Plan A', 'sort_order' => 1]);
    $event->plans()->create(['name' => 'Plan B', 'sort_order' => 2]);

    $this->actingAs($owner)->post("/events/{$event->id}/duplicate");

    $copy = Event::where('user_id', $owner->id)->where('id', '!=', $event->id)->first();
    $this->assertCount(2, $copy->plans);
    $this->assertSame('Plan A', $copy->plans->first()->name);
}
```

- [ ] **Step 2: Run to confirm failure**

```bash
php artisan test tests/Feature/EventCrudTest.php --filter=plan
```

- [ ] **Step 3: Generate migration**

```bash
php artisan make:migration create_event_plans_table
```

```php
public function up(): void
{
    Schema::create('event_plans', function (Blueprint $table) {
        $table->id();
        $table->foreignId('event_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->unsignedInteger('sort_order')->default(1);
        $table->timestamps();
    });
}
```

- [ ] **Step 4: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 5: Write EventPlan model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPlan extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
```

- [ ] **Step 6: Write EventPlanFactory**

```bash
php artisan make:factory EventPlanFactory --model=EventPlan
```

```php
public function definition(): array
{
    return [
        'event_id' => Event::factory(),
        'name' => 'Plan ' . fake()->numberBetween(1, 5),
        'sort_order' => 1,
    ];
}
```

- [ ] **Step 7: Add plans relationship to Event model**

In `app/Models/Event.php` add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function plans(): HasMany
{
    return $this->hasMany(EventPlan::class)->orderBy('sort_order');
}
```

- [ ] **Step 8: Create default plan on event creation**

In `EventController::store()`, after creating the event:

```php
public function store(StoreEventRequest $request): RedirectResponse
{
    $event = $request->user()->events()->create($request->validated());
    $event->plans()->create(['name' => 'Plan 1', 'sort_order' => 1]);
    return redirect()->route('events.show', $event);
}
```

- [ ] **Step 9: Copy plans on event duplication**

Update `EventController::duplicate()`:

```php
public function duplicate(Event $event): RedirectResponse
{
    $this->authorize('duplicate', $event);

    $copy = $event->replicate(['user_id']);
    $copy->user_id = auth()->id();
    $copy->name = $event->name . ' (copy)';
    $copy->save();

    foreach ($event->plans as $plan) {
        $copy->plans()->create([
            'name' => $plan->name,
            'sort_order' => $plan->sort_order,
        ]);
    }

    return redirect()->route('events.show', $copy);
}
```

- [ ] **Step 10: Run tests**

```bash
php artisan test tests/Feature/EventCrudTest.php
```

Expected: All pass.

- [ ] **Step 11: Commit**

```bash
git add database/migrations/ app/Models/EventPlan.php database/factories/EventPlanFactory.php app/Models/Event.php app/Http/Controllers/EventController.php tests/Feature/EventCrudTest.php
git commit -m "feat: event plans with default plan on creation and copy on duplication"
```

---

### Task 14: Run full test suite

- [ ] **Step 1: Run all tests**

```bash
php artisan test
```

Expected: All tests pass (Breeze auth tests + all feature tests written in this plan).

- [ ] **Step 2: Fix any failures before proceeding to Plan 2**
