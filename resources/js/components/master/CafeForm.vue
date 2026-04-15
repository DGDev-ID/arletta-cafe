<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { MapPin, Phone, Search, Upload, X } from 'lucide-vue-next';
import { nextTick, onUnmounted, ref } from 'vue';

delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

interface CafeFormData {
    name: string;
    address: string;
    address_coordinate: string;
    description: string;
    image: File | null;
    phone_number: string;
    errors: Record<string, string>;
    processing: boolean;
}

const props = defineProps<{
    form: CafeFormData;
    existingImgUrl?: string | null;
    submitLabel?: string;
    backHref?: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const imagePreview = ref<string | null>(props.existingImgUrl ?? null);

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    props.form.image = file;
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        imagePreview.value = props.existingImgUrl ?? null;
    }
}

function removeImage() {
    props.form.image = null;
    imagePreview.value = null;
    const input = document.getElementById('cafe-img') as HTMLInputElement;
    if (input) input.value = '';
}

// ── Map ────────────────────────────────────────────────────────────────────
const showMap = ref(false);
const mapRef = ref<HTMLElement | null>(null);
const searchQuery = ref('');
const searchLoading = ref(false);
const searchResults = ref<{ display_name: string; lat: string; lon: string }[]>([]);

let map: L.Map | null = null;
let marker: L.Marker | null = null;

const bindDrag = () => {
    marker?.on('dragend', () => {
        const pos = marker!.getLatLng();
        props.form.address_coordinate = `${pos.lat.toFixed(7)}, ${pos.lng.toFixed(7)}`;
    });
};

const initMap = () => {
    if (!mapRef.value || map) return;

    let center: L.LatLngTuple = [-6.2088, 106.8456];
    if (props.form.address_coordinate) {
        const parts = props.form.address_coordinate.split(',').map((s) => parseFloat(s.trim()));
        if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
            center = [parts[0], parts[1]];
        }
    }

    map = L.map(mapRef.value).setView(center, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    if (props.form.address_coordinate) {
        marker = L.marker(center, { draggable: true }).addTo(map);
        bindDrag();
    }

    map.on('click', (e: L.LeafletMouseEvent) => {
        props.form.address_coordinate = `${e.latlng.lat.toFixed(7)}, ${e.latlng.lng.toFixed(7)}`;
        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map!);
            bindDrag();
        }
    });
};

const openMap = async () => {
    showMap.value = true;
    await nextTick();
    initMap();
    setTimeout(() => map?.invalidateSize(), 100);
};

const searchLocation = async () => {
    const q = searchQuery.value.trim();
    if (!q) return;
    searchLoading.value = true;
    searchResults.value = [];
    try {
        const res = await fetch(
            `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=5`,
            { headers: { 'Accept-Language': 'id,en' } },
        );
        searchResults.value = await res.json();
    } finally {
        searchLoading.value = false;
    }
};

const selectResult = (result: { display_name: string; lat: string; lon: string }) => {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);
    const latlng: L.LatLngTuple = [lat, lng];
    props.form.address_coordinate = `${lat.toFixed(7)}, ${lng.toFixed(7)}`;
    if (!props.form.address) props.form.address = result.display_name;
    map?.setView(latlng, 16);
    if (marker) {
        marker.setLatLng(latlng);
    } else {
        marker = L.marker(latlng, { draggable: true }).addTo(map!);
        bindDrag();
    }
    searchResults.value = [];
    searchQuery.value = result.display_name;
};

const clearCoordinate = () => {
    props.form.address_coordinate = '';
    if (marker && map) {
        map.removeLayer(marker);
        marker = null;
    }
};

onUnmounted(() => {
    map?.remove();
    map = null;
    marker = null;
});
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">

        <!-- Nama Cafe -->
        <div class="grid gap-2">
            <label for="cafe-name" class="text-sm font-medium leading-none">
                Nama Cafe <span class="text-red-500">*</span>
            </label>
            <input
                id="cafe-name"
                v-model="form.name"
                required
                placeholder="Contoh: Arletta Coffee Sudirman"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <InputError :message="form.errors.name" />
        </div>

        <!-- Alamat -->
        <div class="grid gap-2">
            <label for="cafe-address" class="text-sm font-medium leading-none">
                Alamat <span class="text-red-500">*</span>
            </label>
            <input
                id="cafe-address"
                v-model="form.address"
                required
                placeholder="Jl. Sudirman No.1, Jakarta Pusat"
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <InputError :message="form.errors.address" />
        </div>

        <!-- Koordinat Lokasi -->
        <div class="grid gap-2">
            <label for="cafe-coordinate" class="text-sm font-medium leading-none">
                Koordinat Lokasi
                <span class="text-muted-foreground text-xs font-normal ml-1">(opsional)</span>
            </label>

            <div class="flex gap-2">
                <input
                    id="cafe-coordinate"
                    v-model="form.address_coordinate"
                    readonly
                    placeholder="-6.2088000, 106.8456000"
                    class="flex-1 px-3 py-2 text-sm rounded-lg border bg-muted/40 focus:outline-none cursor-default"
                />
                <button
                    type="button"
                    @click="openMap"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 text-sm font-medium transition whitespace-nowrap"
                >
                    <MapPin :size="16" />
                    Pilih di Maps
                </button>
                <button
                    v-if="form.address_coordinate"
                    type="button"
                    @click="clearCoordinate"
                    class="inline-flex items-center px-3 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 border border-red-200 transition"
                    title="Hapus koordinat"
                >
                    <X :size="16" />
                </button>
            </div>

            <InputError :message="form.errors.address_coordinate" />

            <!-- Leaflet Map Picker -->
            <div v-if="showMap" class="mt-1 rounded-xl border overflow-hidden shadow-sm">
                <div class="p-3 bg-background border-b space-y-2">
                    <div class="flex gap-2 items-center">
                        <MapPin :size="16" class="text-blue-500 shrink-0" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari nama tempat atau alamat..."
                            class="flex-1 px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                            @keydown.enter.prevent="searchLocation"
                        />
                        <button
                            type="button"
                            @click="searchLocation"
                            :disabled="searchLoading"
                            class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 text-sm transition disabled:opacity-50"
                        >
                            <Search :size="14" />
                            {{ searchLoading ? '...' : 'Cari' }}
                        </button>
                        <button
                            type="button"
                            @click="showMap = false"
                            class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-muted hover:bg-muted/80 text-sm transition"
                        >
                            <X :size="14" />
                            Tutup
                        </button>
                    </div>
                    <ul v-if="searchResults.length" class="rounded-lg border divide-y text-sm max-h-40 overflow-y-auto">
                        <li
                            v-for="result in searchResults"
                            :key="result.lat + result.lon"
                            @click="selectResult(result)"
                            class="px-3 py-2 cursor-pointer hover:bg-muted/60 transition truncate"
                        >
                            {{ result.display_name }}
                        </li>
                    </ul>
                </div>
                <div ref="mapRef" class="w-full h-80"></div>
                <div class="px-4 py-2.5 bg-muted/40 text-xs text-muted-foreground text-center">
                    Cari lokasi, klik pada peta, atau geser marker untuk memilih koordinat. Peta menggunakan OpenStreetMap (gratis).
                </div>
            </div>

            <p v-if="form.address_coordinate" class="text-xs text-green-600 flex items-center gap-1">
                <MapPin :size="12" />
                Koordinat terpilih: {{ form.address_coordinate }}
            </p>
        </div>

        <!-- Deskripsi -->
        <div class="grid gap-2">
            <label for="cafe-description" class="text-sm font-medium leading-none">
                Deskripsi
                <span class="text-muted-foreground text-xs font-normal ml-1">(opsional)</span>
            </label>
            <textarea
                id="cafe-description"
                v-model="form.description"
                rows="4"
                placeholder="Deskripsi singkat tentang cafe ini..."
                class="w-full px-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring resize-none"
            ></textarea>
            <InputError :message="form.errors.description" />
        </div>

        <!-- Phone Number -->
        <div class="grid gap-2">
            <label for="cafe-phone" class="text-sm font-medium leading-none">
                Nomor Telepon
                <span class="text-muted-foreground text-xs font-normal ml-1">(opsional)</span>
            </label>
            <div class="relative">
                <Phone :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/60" />
                <input
                    id="cafe-phone"
                    v-model="form.phone_number"
                    type="text"
                    placeholder="Contoh: 081234567890"
                    class="w-full pl-10 pr-3 py-2 text-sm rounded-lg border bg-background focus:outline-none focus:ring-2 focus:ring-ring"
                />
            </div>
            <InputError :message="form.errors.phone_number" />
        </div>

        <!-- Image Upload -->
        <div class="grid gap-2">
            <label for="cafe-img" class="text-sm font-medium leading-none">
                Gambar Cafe
                <span class="text-muted-foreground text-xs font-normal ml-1">(opsional)</span>
            </label>
            <div class="space-y-3">
                <div v-if="imagePreview" class="relative inline-block">
                    <img :src="imagePreview" alt="Preview" class="h-48 w-auto rounded-xl border object-cover shadow-sm" />
                    <button type="button" @click="removeImage"
                        class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white shadow-sm hover:bg-red-600 transition">
                        <X :size="14" />
                    </button>
                </div>

                <label for="cafe-img"
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-muted-foreground/30 bg-muted/30 px-6 py-8 text-center transition hover:border-primary/50 hover:bg-muted/50">
                    <Upload :size="24" class="text-muted-foreground/60" />
                    <span class="text-sm text-muted-foreground">Klik untuk upload atau drag & drop</span>
                    <span class="text-xs text-muted-foreground/60">PNG, JPG, WEBP (max 5MB)</span>
                    <input id="cafe-img" type="file" accept="image/*" class="hidden" @change="onFileChange" />
                </label>
            </div>
            <InputError :message="form.errors.image" />
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-2 border-t">
            <a
                :href="backHref ?? '/master/cafe'"
                class="inline-flex items-center px-5 py-2.5 rounded-xl border text-sm font-medium text-muted-foreground hover:bg-muted transition"
            >
                Batal
            </a>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50"
            >
                {{ form.processing ? 'Menyimpan...' : (submitLabel ?? 'Simpan') }}
            </button>
        </div>

    </form>
</template>
