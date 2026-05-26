<?php

namespace App\Services;

use App\Models\MMaterial;
use App\Models\MMenu;
use App\Models\MenuMaterial;
use App\Models\MenuSemiFinishedMaterial;
use App\Models\UnitMaterialConverter;

class MenuAvailabilityService
{
    public function checkAvailableMenu(MMenu $menu, int $quantity, array $selectedVariants = []): bool
    {
        $menuMaterials = MenuMaterial::where('menu_id', $menu->id)->get();

        foreach ($menuMaterials as $menuMaterial) {
            $material = MMaterial::find($menuMaterial->material_id);

            if (!$material) {
                return false;
            }

            $convertedAmount = $this->convertToBase($material, $menuMaterial->amount, $menuMaterial->unit_id);

            if ($convertedAmount === null) {
                return false;
            }

            $totalNeeded = $convertedAmount * $quantity;

            if ($material->type === 'selectable') {
                // Jika ada selected variant (dari checkout/check), validasi variant spesifik
                $variantEntry = collect($selectedVariants)
                    ->first(fn($v) => $v['material_id'] == $material->id);

                if ($variantEntry) {
                    // Cek stok variant yang dipilih
                    $variant = \App\Models\MaterialVariant::find($variantEntry['variant_id']);
                    if (!$variant || (float) $variant->stock < $totalNeeded) {
                        return false;
                    }
                } else {
                    // Tidak ada selection (filter awal untuk tampilkan menu):
                    // Cukup pastikan ada minimal 1 variant yang stoknya cukup
                    $hasAvailableVariant = $material->variants()
                        ->where('stock', '>=', $totalNeeded)
                        ->exists();

                    if (!$hasAvailableVariant) {
                        return false;
                    }
                }
            } else {
                if ((float) $material->stock < $totalNeeded) {
                    return false;
                }
            }
        }

        // --- Cek MenuSemiFinishedMaterial ---
        $menuSfms = MenuSemiFinishedMaterial::where('menu_id', $menu->id)
            ->with('semiFinishedMaterial.details')
            ->get();

        foreach ($menuSfms as $menuSfm) {
            $multiplier = (float) $menuSfm->multiplier;

            foreach ($menuSfm->semiFinishedMaterial->details as $detail) {
                $material = MMaterial::find($detail->material_id);

                if (!$material) {
                    return false;
                }

                $convertedAmount = $this->convertToBase($material, $detail->amount, $detail->unit_id);

                if ($convertedAmount === null) {
                    return false;
                }

                $totalNeeded = $convertedAmount * $multiplier * $quantity;

                if ((float) $material->stock < $totalNeeded) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Cek ketersediaan material untuk banyak menu sekaligus.
     */
    public function checkAvailableMenus(array $items): array
    {
        $aggregatedNeeds  = [];
        $variantNeeds     = [];
        $materialToMenuNames = [];

        foreach ($items as $item) {
            /** @var MMenu $menu */
            $menu     = $item['menu'];
            $quantity = $item['quantity'];
            $selectedVariants = $item['selected_variants'] ?? [];

            // --- Aggregate dari MenuMaterial ---
            $menuMaterials = MenuMaterial::where('menu_id', $menu->id)->get();

            foreach ($menuMaterials as $menuMaterial) {
                $material = MMaterial::find($menuMaterial->material_id);

                if (!$material) {
                    return [$menu->name];
                }

                $convertedAmount = $this->convertToBase(
                    $material,
                    $menuMaterial->amount,
                    $menuMaterial->unit_id
                );

                if ($convertedAmount === null) {
                    return [$menu->name];
                }

                $needed = $convertedAmount * $quantity;
                $materialToMenuNames[$material->id][] = $menu->name;

                if ($material->type === 'selectable') {
                    $variantEntry = collect($selectedVariants)
                        ->first(fn($v) => $v['material_id'] == $material->id);

                    if (!$variantEntry) {
                        // Tidak ada variant dipilih → tandai tidak tersedia
                        $aggregatedNeeds["missing_variant_{$material->id}"] = 1;
                        $materialToMenuNames["missing_variant_{$material->id}"][] = $menu->name;
                    } else {
                        $variantId = $variantEntry['variant_id'];
                        $variantNeeds[$variantId] = ($variantNeeds[$variantId] ?? 0) + $needed;
                    }
                } else {
                    $aggregatedNeeds[$material->id] = ($aggregatedNeeds[$material->id] ?? 0) + $needed;
                }
            }

            // --- Aggregate dari MenuSemiFinishedMaterial ---
            $menuSfms = MenuSemiFinishedMaterial::where('menu_id', $menu->id)
                ->with('semiFinishedMaterial.details')
                ->get();

            foreach ($menuSfms as $menuSfm) {
                $multiplier = (float) $menuSfm->multiplier;

                foreach ($menuSfm->semiFinishedMaterial->details as $detail) {
                    $material = MMaterial::find($detail->material_id);

                    if (!$material) {
                        return [$menu->name];
                    }

                    $convertedAmount = $this->convertToBase(
                        $material,
                        $detail->amount,
                        $detail->unit_id
                    );

                    if ($convertedAmount === null) {
                        return [$menu->name];
                    }

                    $needed = $convertedAmount * $multiplier * $quantity;
                    $aggregatedNeeds[$material->id] = ($aggregatedNeeds[$material->id] ?? 0) + $needed;
                    $materialToMenuNames[$material->id][] = $menu->name;
                }
            }
        }

        $unavailableMenuNames = [];

        // Cek stok material normal
        foreach ($aggregatedNeeds as $key => $totalNeeded) {
            // Key "missing_variant_X" → langsung tandai unavailable
            if (str_starts_with((string) $key, 'missing_variant_')) {
                foreach (($materialToMenuNames[$key] ?? []) as $menuName) {
                    $unavailableMenuNames[] = $menuName;
                }
                continue;
            }

            $material = MMaterial::find($key);
            if (!$material || (float) $material->stock < $totalNeeded) {
                foreach (($materialToMenuNames[$key] ?? []) as $menuName) {
                    $unavailableMenuNames[] = $menuName;
                }
            }
        }

        // Cek stok variant
        foreach ($variantNeeds as $variantId => $totalNeeded) {
            $variant = \App\Models\MaterialVariant::find($variantId);
            if (!$variant || (float) $variant->stock < $totalNeeded) {
                $parentId = $variant?->material_id;
                if ($parentId && isset($materialToMenuNames[$parentId])) {
                    foreach ($materialToMenuNames[$parentId] as $menuName) {
                        $unavailableMenuNames[] = $menuName;
                    }
                }
            }
        }

        return array_values(array_unique($unavailableMenuNames));
    }

    private function convertToBase($material, $amount, $unitId)
    {
        if ($material->base_unit_id == $unitId) {
            return (float) $amount;
        }

        $converter = UnitMaterialConverter::where('material_id', $material->id)
            ->where('from_unit_id', $unitId)
            ->where('to_unit_id', $material->base_unit_id)
            ->first();

        if ($converter) {
            return (float) $amount * (float) $converter->multiplier;
        }

        $reverse = UnitMaterialConverter::where('material_id', $material->id)
            ->where('from_unit_id', $material->base_unit_id)
            ->where('to_unit_id', $unitId)
            ->first();

        if ($reverse) {
            return (float) $amount / (float) $reverse->multiplier;
        }

        return null;
    }
}