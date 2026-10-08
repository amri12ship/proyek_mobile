<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.holidays.index', [
            'holidays' => Holiday::query()->orderByDesc('date')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $holiday = Holiday::create($data);

        $this->audit->success('holiday.create', $request->user(), null, "Hari libur {$holiday->name} ditambahkan");

        return back()->with('success', "Hari libur {$holiday->name} ditambahkan.");
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $name = $holiday->name;
        $holiday->delete();

        $this->audit->success('holiday.delete', $request->user(), null, "Hari libur {$name} dihapus");

        return back()->with('success', "Hari libur {$name} dihapus.");
    }
}
