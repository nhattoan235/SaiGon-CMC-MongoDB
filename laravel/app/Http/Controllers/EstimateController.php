<?php

namespace App\Http\Controllers;

use App\Services\EstimateNormWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EstimateController extends Controller
{
    public function index(Request $request, EstimateNormWorkflow $workflow): View
    {
        $typeOptions = $workflow->activeTypeOptions();
        $buildingTypes = $typeOptions->pluck('code');
        $data = $request->validate([
            'building_type' => ['nullable', 'string', 'max:50', Rule::in($buildingTypes->all())],
            'area' => ['nullable', 'numeric', 'gt:0', 'lte:10000'],
            'floors' => ['nullable', 'integer', 'between:1,50'],
        ]);
        $rows = collect();
        $total = '0.00';
        $estimateSet = null;
        if (isset($data['building_type'], $data['area'])) {
            $currentSet = $workflow->currentFor($data['building_type']);
            $estimateSet = $currentSet['meta'];
            $rows = $currentSet['items']
                ->map(function ($row) use ($data, &$total) {
                    $quantity = bcmul((string) $row->quantity_per_square_meter, (string) $data['area'], 4);
                    if ($row->per_floor) {
                        $quantity = bcmul($quantity, (string) ($data['floors'] ?? 1), 4);
                    }
                    $multiplier = $row->rounding === 'tenth' ? 10 : 1;
                    $row->quantity = (string) (ceil((float) $quantity * $multiplier) / $multiplier);
                    $row->line_total = bcmul($row->quantity, (string) $row->price, 2);
                    $total = bcadd($total, $row->line_total, 2);

                    return $row;
                });
        }

        $buildingTypeLabels = $typeOptions->mapWithKeys(fn ($option): array => [$option->code => $option->label]);

        return view('store.estimate', compact('data', 'rows', 'total', 'buildingTypes', 'buildingTypeLabels', 'estimateSet'));
    }

}
