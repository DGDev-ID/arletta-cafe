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
        if ($menu->is_combo && $menu->start_time && $menu->end_time) {
            $currentTime = now()->format('H:i:s');
            if ($currentTime < $menu->start_time || $currentTime > $menu->end_time) {
                return false;
            }
        }

        if ($menu->is_combo) {
            foreach ($menu->menuCombos as $combo) {
                if (!$this->checkAvailableMenu($combo->childMenu, $quantity * $combo->amount, $selectedVariants)) {
                    return false;
                }
            }
            return true;
        }

        // --- Cek MenuMaterial (raw material langsung) ---
        $menuMaterials = MenuMaterial::where('menu_id', $menu->id)->get();

        foreach ($menuMaterials as $menuMaterial) {
            $material = MMaterial::find($menuMaterial->material_id);

            if (!$material) {
                return false;
            }

            $convertedMenuMaterialQuantity = $this->convertToBase($material, $menuMaterial->amount, $menuMaterial->unit_id);

            if ($convertedMenuMaterialQuantity === null) {
                return false;
            }

            $totalMaterialNeeded = $convertedMenuMaterialQuantity * $quantity;

            if ($material->type === 'selectable') {
                // If selected variant provided for this material, check variant stock
                if (!empty($selectedVariants)) {
                    $variantEntry = collect($selectedVariants)->first(fn($v) => $v['material_id'] == $material->id);
                    if (!$variantEntry) {
                        return false;
                    }
                    $variant = \App\Models\MaterialVariant::find($variantEntry['variant_id']);
                    if (!$variant) return false;
                    if ((float) $variant->stock < $totalMaterialNeeded) return false;
                } else {
                    // For listing (empty selectedVariants), check if AT LEAST ONE variant has enough stock
                    $hasAvailableVariant = false;
                    foreach ($material->variants as $variant) {
                        if ((float) $variant->stock >= $totalMaterialNeeded) {
                            $hasAvailableVariant = true;
                            break;
                        }
                    }
                    if (!$hasAvailableVariant) return false;
                }
            } else {
                if ((float) $material->stock < $totalMaterialNeeded) return false;
            }
        }

        // --- Cek MenuSemiFinishedMaterial (expand SFM ke raw material) ---
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

                // if ($material->base_unit_id !== $detail->unit_id) {
                //     $converter = UnitMaterialConverter::where('material_id', $material->id)
                //         ->where('from_unit_id', $detail->unit_id)
                //         ->where('to_unit_id', $material->base_unit_id)
                //         ->first();

                //     if (!$converter) {
                //         return false;
                //     }

                //     $convertedAmount = (float) $detail->amount * (float) $converter->multiplier;
                // } else {
                //     $convertedAmount = (float) $detail->amount;
                // }

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
     * Material yang sama dari menu yang berbeda di-aggregate terlebih dahulu
     * sebelum dibandingkan dengan stok, untuk menghindari false-positive.
     *
     * @param  array<int, array{menu: MMenu, quantity: int}>  $items
     * @return array<string>  Nama menu yang tidak bisa dipenuhi (kosong = semua tersedia)
     */
    public function checkAvailableMenus(array $items): array
    {
        // Agregasi total kebutuhan per material_id (dalam base unit)
        // Format: [material_id => total_needed_in_base_unit]
        $aggregatedNeeds = [];
        $variantNeeds = [];

        // Simpan mapping material_id -> nama menu yang membutuhkan (untuk pesan error)
        $materialToMenuNames = [];

        foreach ($items as $item) {
            /** @var MMenu $menu */
            $menu     = $item['menu'];
            $quantity = $item['quantity'];

            if ($menu->is_combo && $menu->start_time && $menu->end_time) {
                $currentTime = now()->format('H:i:s');
                if ($currentTime < $menu->start_time || $currentTime > $menu->end_time) {
                    return [$menu->name];
                }
            }

            $menusToProcess = [];
            if ($menu->is_combo) {
                foreach ($menu->menuCombos as $combo) {
                    $menusToProcess[] = [
                        'menu' => $combo->childMenu,
                        'multiplier' => $quantity * $combo->amount
                    ];
                }
            } else {
                $menusToProcess[] = [
                    'menu' => $menu,
                    'multiplier' => $quantity
                ];
            }

            foreach ($menusToProcess as $menuData) {
                $currentMenu = $menuData['menu'];
                $currentQuantity = $menuData['multiplier'];

                // --- Aggregate dari MenuMaterial ---
                $menuMaterials = MenuMaterial::where('menu_id', $currentMenu->id)->get();

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
                        return false;
                    }

                    $needed = $convertedAmount * $currentQuantity;

                    if ($material->type === 'selectable') {
                        // Aggregate per-variant if selection provided
                        $selectedVariants = $item['selected_variants'] ?? [];
                        $variantEntry = collect($selectedVariants)->first(fn($v) => $v['material_id'] == $material->id);
                        if (!$variantEntry) {
                            // No selected variant — mark unavailable
                            $materialToMenuNames[$material->id][] = $menu->name;
                            $aggregatedNeeds[$material->id] = ($aggregatedNeeds[$material->id] ?? 0) + $needed; // fallback to parent check later
                        } else {
                            $variantId = $variantEntry['variant_id'];
                            $variantNeeds[$variantId] = ($variantNeeds[$variantId] ?? 0) + $needed;
                            $materialToMenuNames[$material->id][] = $menu->name;
                        }
                    } else {
                        $aggregatedNeeds[$material->id] = ($aggregatedNeeds[$material->id] ?? 0) + $needed;
                        $materialToMenuNames[$material->id][] = $menu->name;
                    }
                }

                // --- Aggregate dari MenuSemiFinishedMaterial ---
                $menuSfms = MenuSemiFinishedMaterial::where('menu_id', $currentMenu->id)
                    ->with('semiFinishedMaterial.details')
                    ->get();

                foreach ($menuSfms as $menuSfm) {
                    $multiplier = (float) $menuSfm->multiplier;

                    foreach ($menuSfm->semiFinishedMaterial->details as $detail) {
                        $material = MMaterial::find($detail->material_id);

                        if (!$material) {
                            return [$menu->name];
                        }

                        if ($material->base_unit_id !== $detail->unit_id) {
                            $converter = UnitMaterialConverter::where('material_id', $material->id)
                                ->where('from_unit_id', $detail->unit_id)
                                ->where('to_unit_id', $material->base_unit_id)
                                ->first();

                            if (!$converter) {
                                return [$menu->name];
                            }

                            $convertedAmount = (float) $detail->amount * (float) $converter->multiplier;
                        } else {
                            $convertedAmount = (float) $detail->amount;
                        }

                        $needed = $convertedAmount * $multiplier * $currentQuantity;

                        $aggregatedNeeds[$material->id] = ($aggregatedNeeds[$material->id] ?? 0) + $needed;
                        $materialToMenuNames[$material->id][] = $menu->name;
                    }
                }
            }
        }

        // Cek stok setelah semua kebutuhan diagregasi
        $unavailableMenuNames = [];

        // Check parent materials
        foreach ($aggregatedNeeds as $materialId => $totalNeeded) {
            $material = MMaterial::find($materialId);

            if (!$material || (float) $material->stock < $totalNeeded) {
                foreach ($materialToMenuNames[$materialId] as $menuName) {
                    $unavailableMenuNames[] = $menuName;
                }
            }
        }

        // Check variant needs
        foreach ($variantNeeds as $variantId => $totalNeeded) {
            $variant = \App\Models\MaterialVariant::find($variantId);
            if (!$variant || (float) $variant->stock < $totalNeeded) {
                // find parent material id for mapping
                $parentId = $variant?->material_id ?? null;
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
