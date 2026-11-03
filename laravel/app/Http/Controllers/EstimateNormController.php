<?php

namespace App\Http\Controllers;

use App\Services\EstimateNormWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EstimateNormController extends Controller
{
    public function index(Request $request, EstimateNormWorkflow $workflow): View
    {
        $selectedCode = $request->query('building_type');
        $data = $workflow->managementData(is_string($selectedCode) ? $selectedCode : null);

        return view('staff.estimate-norms', $data);
    }

    public function import(Request $request, EstimateNormWorkflow $workflow): RedirectResponse
    {
        $count = $workflow->importLegacy((int) $request->user()->id);
        $message = $count > 0
            ? "Đã nhập {$count} loại định mức cũ thành phiên bản 1 và kích hoạt."
            : 'Không có định mức cũ cần nhập; các phiên bản đã có được giữ nguyên.';

        return redirect()->route('management.estimate-norms.index')->with('status', $message);
    }

    public function store(Request $request, EstimateNormWorkflow $workflow): RedirectResponse
    {
        $request->merge([
            'label' => trim((string) $request->input('label', '')),
            'change_note' => trim((string) $request->input('change_note', '')),
            'items' => collect($request->input('items', []))->map(function ($item) {
                if (is_array($item) && isset($item['source'])) {
                    $item['source'] = trim((string) $item['source']);
                }

                return $item;
            })->all(),
        ]);
        $data = $request->validate([
            'building_type' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'label' => ['required', 'string', 'min:2', 'max:100'],
            'change_note' => ['required', 'string', 'min:3', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('active', true)],
            'items.*.quantity_per_square_meter' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'lte:100000'],
            'items.*.per_floor' => ['required', 'boolean'],
            'items.*.rounding' => ['required', Rule::in(['whole', 'tenth'])],
            'items.*.source' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'building_type.regex' => 'Mã loại công trình chỉ dùng chữ in hoa, số và dấu gạch dưới; ký tự đầu là chữ.',
            'items.*.product_id.distinct' => 'Mỗi vật liệu chỉ được thêm một lần trong một phiên bản.',
            'items.*.source.required' => 'Ghi nguồn hoặc chú thích minh họa cho từng định mức.',
        ]);

        $versionId = $workflow->createAndActivate($data, (int) $request->user()->id);
        $version = DB::table('estimation_norm_versions')->where('id', $versionId)->first();

        return redirect()->route('management.estimate-norms.index', ['building_type' => $version->building_type])
            ->with('status', "Đã tạo và kích hoạt phiên bản {$version->version}.");
    }

    public function activate(Request $request, int $versionId, EstimateNormWorkflow $workflow): RedirectResponse
    {
        $version = DB::table('estimation_norm_versions')->where('id', $versionId)->first();
        abort_unless($version, 404);
        $workflow->activateVersion($versionId, (int) $request->user()->id);

        return redirect()->route('management.estimate-norms.index', ['building_type' => $version->building_type])
            ->with('status', "Đã kích hoạt lại phiên bản {$version->version}.");
    }
}
