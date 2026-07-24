<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdministrator();

        $search = trim((string) $request->query('search', ''));
        $role = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere(
                            'last_login_ip',
                            'ilike',
                            "%{$search}%"
                        );
                });
            })
            ->when(
                in_array($role, User::roles(), true),
                fn ($query) => $query->where('role', $role)
            )
            ->when(
                $status === 'active',
                fn ($query) => $query->where('active', true)
            )
            ->when(
                $status === 'inactive',
                fn ($query) => $query->where('active', false)
            )
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'roles' => User::roles(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdministrator();

        return view('users.create', [
            'managedUser' => new User([
                'role' => User::ROLE_VIEWER,
                'active' => true,
                'must_change_password' => true,
            ]),
            'roles' => User::roles(),
            'avatars' => User::avatars(),
        ]);
    }

    public function store(
        StoreUserRequest $request,
        AuditService $audit
    ): RedirectResponse {
        $data = $request->validated();
        $data['password_changed_at'] = now();

        $user = User::create($data);

        $audit->record(
            'created',
            $user,
            null,
            $user->getAttributes(),
            $user->email
        );

        return redirect()
            ->route('users.edit', $user)
            ->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(User $user): View
    {
        $this->authorizeAdministrator();

        return view('users.edit', [
            'managedUser' => $user,
            'roles' => User::roles(),
            'avatars' => User::avatars(),
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        AuditService $audit
    ): RedirectResponse {
        if (
            auth()->id() === $user->id
            && ! $request->boolean('active')
        ) {
            return back()
                ->withErrors([
                    'active' => 'Você não pode desativar sua própria conta.',
                ])
                ->withInput();
        }

        if (
            auth()->id() === $user->id
            && $request->input('role') !== User::ROLE_ADMIN
        ) {
            return back()
                ->withErrors([
                    'role' => 'Você não pode remover seu próprio acesso administrativo.',
                ])
                ->withInput();
        }

        $oldValues = $user->getOriginal();

        $user->update($request->validated());

        $audit->record(
            'updated',
            $user,
            $oldValues,
            $user->fresh()->getAttributes(),
            $user->email
        );

        return redirect()
            ->route('users.edit', $user)
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    public function toggleActive(
        User $user,
        AuditService $audit
    ): RedirectResponse {
        $this->authorizeAdministrator();

        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'active' => 'Você não pode bloquear sua própria conta.',
            ]);
        }

        $oldValues = $user->getOriginal();

        $user->update([
            'active' => ! $user->active,
        ]);

        $audit->record(
            $user->active ? 'activated' : 'deactivated',
            $user,
            $oldValues,
            $user->fresh()->getAttributes(),
            $user->email
        );

        return back()->with(
            'success',
            $user->active
                ? 'Usuário ativado com sucesso.'
                : 'Usuário bloqueado com sucesso.'
        );
    }

    public function resetPassword(
        ResetUserPasswordRequest $request,
        User $user,
        AuditService $audit
    ): RedirectResponse {
        $oldValues = $user->getOriginal();

        $user->forceFill([
            'password' => Hash::make(
                $request->string('password')->toString()
            ),
            'must_change_password' => $request->boolean(
                'must_change_password'
            ),
            'password_changed_at' => now(),
            'remember_token' => null,
        ])->save();

        $audit->record(
            'password_reset',
            $user,
            $oldValues,
            [
                'must_change_password' =>
                    $user->must_change_password,
                'password_changed_at' =>
                    $user->password_changed_at?->toISOString(),
            ],
            $user->email
        );

        return back()->with(
            'success',
            'Senha redefinida com sucesso.'
        );
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );
    }
}
