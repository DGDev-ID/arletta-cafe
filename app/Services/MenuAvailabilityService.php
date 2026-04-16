<?php

namespace App\Services;

use App\Models\MMaterial;
use App\Models\MMenu;
use App\Models\MenuMaterial;
use App\Models\UnitMaterialConverter;

class MenuAvailabilityService
{
    public function checkAvailableMenu(MMenu $menu, int $quantity): bool
    {
        $menuMaterials = MenuMaterial::where('menu_id', $menu->id)->get();

        foreach ($menuMaterials as $menuMaterial) {
            $material = MMaterial::find($menuMaterial->material_id);

            if (!$material) {
                return false;
            }

            if ($material->base_unit_id !== $menuMaterial->unit_id) {
                $converter = UnitMaterialConverter::where('material_id', $material->id)
                    ->where('from_unit_id', $menuMaterial->unit_id)
                    ->where('to_unit_id', $material->base_unit_id)
                    ->first();

                if (!$converter) {
                    return false;
                }

                $convertedMenuMaterialQuantity = (float) $menuMaterial->amount * (float) $converter->multiplier;
            } else {
                $convertedMenuMaterialQuantity = (float) $menuMaterial->amount;
            }

            $totalMaterialNeeded = $convertedMenuMaterialQuantity * $quantity;

            $isAvailable = (float) $material->stock >= $totalMaterialNeeded;

            if (!$isAvailable) {
                return false;
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

            $menuMaterials = MenuMaterial::where('menu_id', $menu->id)->get();

            foreach ($menuMaterials as $menuMaterial) {
                $material = MMaterial::find($menuMaterial->material_id);

                if (!$material) {
                    return [$menu->name];
                }

                if ($material->base_unit_id !== $menuMaterial->unit_id) {
                    $converter = UnitMaterialConverter::where('material_id', $material->id)
                        ->where('from_unit_id', $menuMaterial->unit_id)
                        ->where('to_unit_id', $material->base_unit_id)
                        ->first();

                    if (!$converter) {
                        return [$menu->name];
                    }

                    $convertedAmount = (float) $menuMaterial->amount * (float) $converter->multiplier;
                } else {
                    $convertedAmount = (float) $menuMaterial->amount;
                }

                $needed = $convertedAmount * $quantity;

                $aggregatedNeeds[$material->id] = ($aggregatedNeeds[$material->id] ?? 0) + $needed;
                $materialToMenuNames[$material->id][] = $menu->name;
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
}
