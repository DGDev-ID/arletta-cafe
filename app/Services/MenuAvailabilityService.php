<?php

namespace App\Services;

use App\Models\MMaterial;
use App\Models\MMenu;
use App\Models\MenuMaterial;
use App\Models\MenuSemiFinishedMaterial;
use App\Models\UnitMaterialConverter;

class MenuAvailabilityService
{
    public function checkAvailableMenu(MMenu $menu, int $quantity): bool
    {
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

            // if ($material->base_unit_id !== $menuMaterial->unit_id) {
            //     $converter = UnitMaterialConverter::where('material_id', $material->id)
            //         ->where('from_unit_id', $menuMaterial->unit_id)
            //         ->where('to_unit_id', $material->base_unit_id)
            //         ->first();

            //     if (!$converter) {
            //         return false;
            //     }

            //     $convertedMenuMaterialQuantity = (float) $menuMaterial->amount * (float) $converter->multiplier;
            // } else {
            //     $convertedMenuMaterialQuantity = (float) $menuMaterial->amount;
            // }

            $totalMaterialNeeded = $convertedMenuMaterialQuantity * $quantity;

            $isAvailable = (float) $material->stock >= $totalMaterialNeeded;

            if (!$isAvailable) {
                return false;
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

        // Simpan mapping material_id -> nama menu yang membutuhkan (untuk pesan error)
        $materialToMenuNames = [];

        foreach ($items as $item) {
            /** @var MMenu $menu */
            $menu     = $item['menu'];
            $quantity = $item['quantity'];

            // --- Aggregate dari MenuMaterial ---
            $menuMaterials = MenuMaterial::where('menu_id', $menu->id)->get();

            foreach ($menuMaterials as $menuMaterial) {
                $material = MMaterial::find($menuMaterial->material_id);

                if (!$material) {
                    return [$menu->name];
                }

                // if ($material->base_unit_id !== $menuMaterial->unit_id) {
                //     $converter = UnitMaterialConverter::where('material_id', $material->id)
                //         ->where('from_unit_id', $menuMaterial->unit_id)
                //         ->where('to_unit_id', $material->base_unit_id)
                //         ->first();

                //     if (!$converter) {
                //         return [$menu->name];
                //     }

                //     $convertedAmount = (float) $menuMaterial->amount * (float) $converter->multiplier;
                // } else {
                //     $convertedAmount = (float) $menuMaterial->amount;
                // }
                $convertedAmount = $this->convertToBase($material, $detail->amount, $detail->unit_id);

                if ($convertedAmount === null) {
                    return false;
                }

                $needed = $convertedAmount * $quantity;

                $aggregatedNeeds[$material->id] = ($aggregatedNeeds[$material->id] ?? 0) + $needed;
                $materialToMenuNames[$material->id][] = $menu->name;
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

                    $needed = $convertedAmount * $multiplier * $quantity;

                    $aggregatedNeeds[$material->id] = ($aggregatedNeeds[$material->id] ?? 0) + $needed;
                    $materialToMenuNames[$material->id][] = $menu->name;
                }
            }
        }

        // Cek stok setelah semua kebutuhan diagregasi
        $unavailableMenuNames = [];

        foreach ($aggregatedNeeds as $materialId => $totalNeeded) {
            $material = MMaterial::find($materialId);

            if (!$material || (float) $material->stock < $totalNeeded) {
                // Semua menu yang memakai material ini ditandai tidak tersedia
                foreach ($materialToMenuNames[$materialId] as $menuName) {
                    $unavailableMenuNames[] = $menuName;
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
