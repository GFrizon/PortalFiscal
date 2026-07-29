<?php

namespace App\Http\Controllers;

use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserGroupController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('admin.user-groups.index', [
            'groups' => UserGroup::query()
                ->withCount('users')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('admin.user-groups.create', [
            'group' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        UserGroup::query()->create($this->validated($request));

        return redirect()->route('admin.user-groups.index')->with('success', 'Grupo cadastrado com sucesso.');
    }

    public function edit(UserGroup $userGroup): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('admin.user-groups.edit', [
            'group' => $userGroup,
        ]);
    }

    public function update(Request $request, UserGroup $userGroup): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $userGroup->update($this->validated($request, $userGroup));

        return redirect()->route('admin.user-groups.index')->with('success', 'Grupo atualizado com sucesso.');
    }

    public function destroy(Request $request, UserGroup $userGroup): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($userGroup->users()->exists()) {
            return back()->withErrors(['group' => 'Remova os usuarios deste grupo antes de excluir.']);
        }

        $userGroup->delete();

        return redirect()->route('admin.user-groups.index')->with('success', 'Grupo excluido com sucesso.');
    }

    private function validated(Request $request, ?UserGroup $group = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:user_groups,name'.($group ? ','.$group->id : '')],
        ]);
    }
}
