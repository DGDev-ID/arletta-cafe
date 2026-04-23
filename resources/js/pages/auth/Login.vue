<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { Coffee, LoaderCircle, Lock, Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="relative flex min-h-screen">
        <!-- Left Side - Cafe Photo Background -->
        <div class="relative hidden w-1/2 overflow-hidden lg:block">
            <!-- Cafe background image from Unsplash -->
            <img
                src="https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?w=1200&q=80&fit=crop"
                alt="Cafe background"
                class="absolute inset-0 h-full w-full object-cover"
            />

            <!-- Dark overlay for readability -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20" />

            <!-- Content overlay -->
            <div class="relative z-10 flex h-full flex-col justify-end px-12 pb-16 text-white">
                <!-- Logo badge -->
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-600/90 shadow-lg backdrop-blur-sm">
                        <Coffee class="h-5 w-5 text-white" :stroke-width="2" />
                    </div>
                    <span class="text-sm font-semibold uppercase tracking-widest text-amber-300/90">Arletta Cafe</span>
                </div>

                <h1 class="mb-3 text-4xl font-bold leading-tight tracking-tight drop-shadow-lg">
                    Dashboard<br />Management System
                </h1>
                <p class="max-w-sm text-sm leading-relaxed text-white/70">
                    Kelola operasional cafe dengan mudah dan efisien melalui dashboard yang terintegrasi.
                </p>

                <!-- Decorative divider -->
                <div class="mt-8 flex items-center gap-3">
                    <div class="h-px w-20 bg-amber-400/60" />
                    <div class="h-1 w-1 rounded-full bg-amber-400/60" />
                    <div class="h-px w-20 bg-amber-400/60" />
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form with dot pattern background -->
        <div class="relative flex w-full items-center justify-center px-6 py-12 lg:w-1/2">
            <!-- Dot pattern background -->
            <div
                class="absolute inset-0 bg-background"
                style="background-image: radial-gradient(circle, hsl(var(--muted-foreground) / 0.25) 1.5px, transparent 1.5px); background-size: 28px 28px;"
            />
            <!-- Subtle vignette so center content pops -->
            <div class="absolute inset-0 bg-gradient-to-br from-background/70 via-transparent to-background/70" />

            <div class="relative z-10 w-full max-w-md">
                <!-- Mobile Logo (visible on small screens only) -->
                <div class="mb-10 flex flex-col items-center lg:hidden">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-800 shadow-lg">
                        <Coffee class="h-8 w-8 text-amber-200" :stroke-width="1.5" />
                    </div>
                    <h1 class="text-2xl font-bold text-foreground">Arletta Cafe</h1>
                    <p class="text-sm text-muted-foreground">Dashboard Management System</p>
                </div>

                <!-- Form Card -->
                <div class="rounded-2xl border border-border/60 bg-card/90 p-8 shadow-2xl shadow-black/10 backdrop-blur-sm dark:shadow-black/40">
                    <!-- Header -->
                    <div class="mb-8 text-center">
                        <img src="/logo.png" alt="Arletta Cafe" class="w-14 h-14 mx-auto mb-3" />
                        <h2 class="text-2xl font-bold tracking-tight text-foreground">Selamat Datang</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Masukkan kredensial Anda untuk masuk</p>
                    </div>

                    <!-- Status message -->
                    <div
                        v-if="status"
                        class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400"
                    >
                        <div class="h-1.5 w-1.5 rounded-full bg-green-500" />
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Email Field -->
                        <div class="space-y-1.5">
                            <Label for="email" class="text-sm font-medium text-foreground">Alamat Email</Label>
                            <div class="relative">
                                <Mail class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground/70" />
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autofocus
                                    tabindex="1"
                                    autocomplete="email"
                                    v-model="form.email"
                                    placeholder="email@example.com"
                                    class="pl-10 transition-shadow focus:shadow-md"
                                />
                            </div>
                            <InputError :message="form.errors.email" />
                        </div>

                        <!-- Password Field -->
                        <div class="space-y-1.5">
                            <Label for="password" class="text-sm font-medium text-foreground">Password</Label>
                            <div class="relative">
                                <Lock class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground/70" />
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    tabindex="2"
                                    autocomplete="current-password"
                                    v-model="form.password"
                                    placeholder="••••••••"
                                    class="pl-10 transition-shadow focus:shadow-md"
                                />
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center" tabindex="3">
                            <Label for="remember" class="flex cursor-pointer items-center gap-2.5">
                                <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                                <span class="text-sm text-muted-foreground">Ingat saya</span>
                            </Label>
                        </div>

                        <!-- Submit Button -->
                        <Button
                            type="submit"
                            class="mt-2 w-full bg-amber-800 py-5 text-sm font-semibold text-white shadow-lg shadow-amber-900/20 transition-all hover:bg-amber-700 active:scale-[0.98] dark:bg-amber-700 dark:hover:bg-amber-600"
                            tabindex="5"
                            :disabled="form.processing"
                        >
                            <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            <span>{{ form.processing ? 'Memproses...' : 'Masuk ke Dashboard' }}</span>
                        </Button>
                    </form>
                </div>

                <!-- Footer -->
                <p class="mt-6 text-center text-xs text-muted-foreground/50">
                    &copy; {{ new Date().getFullYear() }} Arletta Cafe. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>
