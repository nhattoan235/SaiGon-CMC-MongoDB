<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EstimateNormWorkflow
{
    private const LEGACY_LABELS = [
        'NHA_CAP_4' => 'Nhà cấp 4',
        'NHA_PHO_BTCT' => 'Nhà phố bê tông cốt thép',
        'BIET_THU' => 'Biệt thự',
    ];

    public function activeTypeOptions(): Collection
    {
        $versioned = DB::table('estimation_norm_active as active')
            ->join('estimation_norm_versions as versions', 'versions.id', '=', 'active.version_id')
            ->join('estimation_norm_version_items as items', 'items.version_id', '=', 'versions.id')
            ->join('products', 'products.id', '=', 'items.product_id')->where('products.active', true)
            ->select('active.building_type as code', 'versions.label')
            ->get()->unique('code')->keyBy('code');

        $legacyCodes = DB::table('estimation_norms')->join('products', 'products.id', '=', 'estimation_norms.product_id')
            ->where('products.active', true)->orderBy('estimation_norms.building_type')->pluck('estimation_norms.building_type')->unique();
        foreach ($legacyCodes as $code) {
            if (! $versioned->has($code)) {
                $versioned->put($code, (object) ['code' => $code, 'label' => $this->legacyLabel($code)]);
            }
        }

        return $versioned->sortBy('code')->values();
    }

    /** @return array{meta: array, items: Collection} */
    public function currentFor(string $buildingType, bool $includeInactive = false): array
    {
        $active = DB::table('estimation_norm_active as active')
            ->join('estimation_norm_versions as versions', 'versions.id', '=', 'active.version_id')
            ->where('active.building_type', $buildingType)
            ->select('versions.*', 'active.activated_at', 'active.activated_by')
            ->first();

        if ($active) {
            $query = DB::table('estimation_norm_version_items as items')
                ->join('products', 'products.id', '=', 'items.product_id')
                ->where('items.version_id', $active->id)
                ->select('items.product_id', 'items.quantity_per_square_meter', 'items.per_floor', 'items.rounding', 'items.source',
                    'products.name', 'products.unit', 'products.price', 'products.active as product_active');
            if (! $includeInactive) {
                $query->where('products.active', true);
            }
            $items = $query->orderBy('products.name')->get();

            return [
                'meta' => [
                    'building_type' => $buildingType,
                    'label' => $active->label,
                    'version' => (int) $active->version,
                    'versioned' => true,
                    'change_note' => $active->change_note,
                    'activated_at' => $active->activated_at,
                ],
                'items' => $items,
            ];
        }

        $query = DB::table('estimation_norms')->join('products', 'products.id', '=', 'estimation_norms.product_id')
            ->where('estimation_norms.building_type', $buildingType)
            ->select('estimation_norms.product_id', 'estimation_norms.quantity_per_square_meter', 'estimation_norms.per_floor',
                'estimation_norms.rounding', 'estimation_norms.source', 'products.name', 'products.unit', 'products.price', 'products.active as product_active');
        if (! $includeInactive) {
            $query->where('products.active', true);
        }
        $items = $query->orderBy('products.name')->get();

        return [
            'meta' => [
                'building_type' => $buildingType,
                'label' => $this->legacyLabel($buildingType),
                'version' => null,
                'versioned' => false,
                'change_note' => 'Bản cũ chưa phiên bản hóa',
                'activated_at' => null,
            ],
            'items' => $items,
        ];
    }

    public function managementData(?string $selectedCode): array
    {
        $options = $this->activeTypeOptions();
        $knownCodes = DB::table('estimation_norm_versions')->distinct()->pluck('building_type')->all();
        $legacyCodes = DB::table('estimation_norms')->distinct()->pluck('building_type')->all();
        $hasLegacyToImport = collect($legacyCodes)->contains(fn ($code) => ! in_array($code, $knownCodes, true));
        $selectedCode = $selectedCode ?: ($options->first()->code ?? null);
        $current = $selectedCode ? $this->currentFor($selectedCode, true) : ['meta' => null, 'items' => collect()];

        $products = DB::table('products')->orderBy('name')->get(['id', 'code', 'name', 'unit', 'active']);
        $history = $selectedCode ? DB::table('estimation_norm_versions as versions')
            ->leftJoin('users', 'users.id', '=', 'versions.created_by')
            ->where('versions.building_type', $selectedCode)->orderByDesc('versions.version')
            ->select('versions.*', 'users.name as created_by_name')->get() : collect();
        $activeVersionId = $selectedCode
            ? DB::table('estimation_norm_active')->where('building_type', $selectedCode)->value('version_id')
            : null;
        $history = $history->map(function ($version) use ($activeVersionId) {
            $version->is_active = (int) $version->id === (int) $activeVersionId;
            $version->items = DB::table('estimation_norm_version_items as items')
                ->leftJoin('products', 'products.id', '=', 'items.product_id')
                ->where('items.version_id', $version->id)
                ->select('items.*', 'products.name as product_name', 'products.unit')
                ->orderBy('products.name')->get();

            return $version;
        });
        $activations = $selectedCode ? DB::table('estimation_norm_activation_events as events')
            ->leftJoin('users', 'users.id', '=', 'events.activated_by')
            ->join('estimation_norm_versions as versions', 'versions.id', '=', 'events.version_id')
            ->where('events.building_type', $selectedCode)->orderByDesc('events.activated_at')->limit(30)
            ->select('events.*', 'users.name as activated_by_name', 'versions.version', 'versions.label')->get() : collect();

        return [
            'options' => $options,
            'selectedCode' => $selectedCode,
            'current' => $current,
            'products' => $products,
            'history' => $history,
            'activations' => $activations,
            'hasLegacyToImport' => $hasLegacyToImport,
        ];
    }

    public function importLegacy(?int $actorId): int
    {
        return DB::transaction(function () use ($actorId): int {
            $imported = 0;
            $codes = DB::table('estimation_norms')->distinct()->orderBy('building_type')->pluck('building_type');

            foreach ($codes as $code) {
                if (DB::table('estimation_norm_versions')->where('building_type', $code)->exists()) {
                    continue;
                }
                $legacyItems = DB::table('estimation_norms')->where('building_type', $code)->orderBy('product_id')->get();
                if ($legacyItems->isEmpty()) {
                    continue;
                }

                $now = now();
                $versionId = DB::table('estimation_norm_versions')->insertGetId([
                    'building_type' => $code,
                    'version' => 1,
                    'label' => $this->legacyLabel($code),
                    'change_note' => 'Nhập từ định mức cũ; chưa được xác nhận chuyên môn.',
                    'created_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($legacyItems as $item) {
                    DB::table('estimation_norm_version_items')->insert([
                        'version_id' => $versionId,
                        'product_id' => $item->product_id,
                        'quantity_per_square_meter' => $item->quantity_per_square_meter,
                        'per_floor' => $item->per_floor,
                        'rounding' => $item->rounding,
                        'source' => $item->source ?: 'Định mức minh họa; cần kỹ sư kiểm tra theo bản vẽ',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $this->activateWithinTransaction($code, (int) $versionId, $actorId, $now);
                $imported++;
            }

            return $imported;
        });
    }

    public function createAndActivate(array $data, int $actorId): int
    {
        $activeProductIds = DB::table('products')->where('active', true)->whereIn('id', collect($data['items'])->pluck('product_id'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $requestedIds = collect($data['items'])->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (count($activeProductIds) !== count($requestedIds)) {
            throw ValidationException::withMessages(['items' => 'Một hoặc nhiều vật liệu đã ngừng bán. Hãy tải lại trang rồi chọn vật liệu đang bán.']);
        }

        return DB::transaction(function () use ($data, $actorId): int {
            $currentVersion = DB::table('estimation_norm_versions')->where('building_type', $data['building_type'])->max('version');
            $version = (int) $currentVersion + 1;
            $now = now();
            $versionId = DB::table('estimation_norm_versions')->insertGetId([
                'building_type' => $data['building_type'],
                'version' => $version,
                'label' => trim($data['label']),
                'change_note' => trim($data['change_note']),
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($data['items'] as $item) {
                DB::table('estimation_norm_version_items')->insert([
                    'version_id' => $versionId,
                    'product_id' => $item['product_id'],
                    'quantity_per_square_meter' => $item['quantity_per_square_meter'],
                    'per_floor' => (bool) $item['per_floor'],
                    'rounding' => $item['rounding'],
                    'source' => trim($item['source']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->activateWithinTransaction($data['building_type'], (int) $versionId, $actorId, $now);

            return (int) $versionId;
        });
    }

    public function activateVersion(int $versionId, int $actorId): void
    {
        DB::transaction(function () use ($versionId, $actorId): void {
            $version = DB::table('estimation_norm_versions')->where('id', $versionId)->first();
            if (! $version) {
                abort(404);
            }
            $activeVersionId = DB::table('estimation_norm_active')->where('building_type', $version->building_type)->value('version_id');
            if ((int) $activeVersionId === $versionId) {
                return;
            }
            $this->activateWithinTransaction($version->building_type, $versionId, $actorId, now());
        });
    }

    private function activateWithinTransaction(string $buildingType, int $versionId, ?int $actorId, $activatedAt): void
    {
        $activeId = DB::table('estimation_norm_active')->where('building_type', $buildingType)->value('id');
        if ($activeId) {
            DB::table('estimation_norm_active')->where('id', $activeId)->update([
                'version_id' => $versionId,
                'activated_by' => $actorId,
                'activated_at' => $activatedAt,
                'updated_at' => $activatedAt,
            ]);
        } else {
            DB::table('estimation_norm_active')->insert([
                'building_type' => $buildingType,
                'version_id' => $versionId,
                'activated_by' => $actorId,
                'activated_at' => $activatedAt,
                'created_at' => $activatedAt,
                'updated_at' => $activatedAt,
            ]);
        }
        DB::table('estimation_norm_activation_events')->insert([
            'building_type' => $buildingType,
            'version_id' => $versionId,
            'activated_by' => $actorId,
            'activated_at' => $activatedAt,
            'created_at' => $activatedAt,
            'updated_at' => $activatedAt,
        ]);
    }

    private function legacyLabel(string $code): string
    {
        if (isset(self::LEGACY_LABELS[$code])) {
            return self::LEGACY_LABELS[$code];
        }

        return str_replace('_', ' ', $code);
    }
}
