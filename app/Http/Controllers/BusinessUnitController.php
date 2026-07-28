<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\Admin\StoreBusinessUnitRequest;
use App\Http\Requests\Admin\UpdateBusinessUnitRequest;
use App\Models\BusinessUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BusinessUnitController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', BusinessUnit::class);

        return view('admin.business-units.index', [
            'businessUnits' => BusinessUnit::query()->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BusinessUnit::class);

        return view('admin.business-units.create', [
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function store(StoreBusinessUnitRequest $request): RedirectResponse
    {
        BusinessUnit::query()->create($request->validated());

        return redirect()->route('admin.business-units.index')->with('success', 'Unidade cadastrada com sucesso.');
    }

    public function edit(BusinessUnit $businessUnit): View
    {
        $this->authorize('update', $businessUnit);

        return view('admin.business-units.edit', [
            'businessUnit' => $businessUnit,
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function update(UpdateBusinessUnitRequest $request, BusinessUnit $businessUnit): RedirectResponse
    {
        $businessUnit->update($request->validated());

        return redirect()->route('admin.business-units.index')->with('success', 'Unidade atualizada com sucesso.');
    }

    public function destroy(BusinessUnit $businessUnit): RedirectResponse
    {
        $this->authorize('delete', $businessUnit);

        $businessUnit->update(['status' => UserStatus::Inactive]);

        return redirect()->route('admin.business-units.index')->with('success', 'Unidade inativada com sucesso.');
    }
}
