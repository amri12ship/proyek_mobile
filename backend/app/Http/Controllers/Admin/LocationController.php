<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Services\AuditLogger;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $locations = AttendanceLocation::query()
            ->withCount('employees')
            ->when($request->string('q')->isNotEmpty(), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.locations.index', ['locations' => $locations]);
    }

    public function create(): View
    {
        return view('admin.locations.form', ['location' => new AttendanceLocation([
            'radius' => AttendanceLocation::DEFAULT_RADIUS_METERS,
            'status' => AttendanceLocation::STATUS_ACTIVE,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $location = AttendanceLocation::create($data);

        $this->audit->success('location.create', $request->user(), null, "Lokasi {$location->name} dibuat");

        return redirect()->route('admin.locations.index')->with('success', "Lokasi {$location->name} berhasil ditambahkan.");
    }

    public function edit(AttendanceLocation $location): View
    {
        return view('admin.locations.form', ['location' => $location]);
    }

    public function update(Request $request, AttendanceLocation $location): RedirectResponse
    {
        $location->update($this->validated($request, $location));

        $this->audit->success('location.update', $request->user(), null, "Lokasi {$location->name} diperbarui");

        return redirect()->route('admin.locations.index')->with('success', "Lokasi {$location->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, AttendanceLocation $location): RedirectResponse
    {
        $name = $location->name;
        $location->delete();

        $this->audit->success('location.delete', $request->user(), null, "Lokasi {$name} dihapus");

        return redirect()->route('admin.locations.index')->with('success', "Lokasi {$name} dihapus.");
    }

    /**
     * Show the printable QR sheet. This is the only place the QR secret is revealed.
     */
    public function qr(AttendanceLocation $location): View
    {
        return view('admin.locations.qr', ['location' => $location]);
    }

    /**
     * Render the QR code itself as a PNG so it can be embedded or printed.
     */
    public function qrImage(AttendanceLocation $location)
    {
        $result = (new PngWriter)->write(
            new QrCode($location->qrPayload(), size: 600, margin: 20),
        );

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function rotateToken(Request $request, AttendanceLocation $location): RedirectResponse
    {
        $token = $location->rotateToken();

        $this->audit->success('location.rotate_token', $request->user(), null, "Token QR {$location->name} dirotasi");

        return back()->with('success', "Token QR {$location->name} diganti. QR yang dicetak sebelumnya sudah tidak berlaku, cetak ulang QR code.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AttendanceLocation $location = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('attendance_locations', 'name')->ignore($location?->id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required', 'integer', 'min:'.AttendanceLocation::MIN_RADIUS_METERS, 'max:'.AttendanceLocation::MAX_RADIUS_METERS],
            'status' => ['required', Rule::in([AttendanceLocation::STATUS_ACTIVE, AttendanceLocation::STATUS_INACTIVE])],
        ]);
    }
}
