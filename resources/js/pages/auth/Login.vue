<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { redirect as googleRedirect } from '@/routes/google';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Entrar a Centinela',
        description: 'Entrá con Google para ver tus proyectos',
    },
});

const props = defineProps<{
    status?: string;
    error?: string;
    googleHabilitado: boolean;
}>();
</script>

<template>
    <Head title="Entrar" />

    <div
        v-if="props.status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ props.status }}
    </div>

    <AlertError
        v-if="props.error"
        class="mb-6"
        title="No pudimos entrar"
        :errors="[props.error]"
    />

    <!--
        Un enlace y no un formulario: el redirect a Google es un GET, y con
        `external` Inertia hace una navegación completa en vez de un XHR, que es
        lo que necesita una redirección a otro dominio.
    -->
    <div v-if="props.googleHabilitado" class="flex flex-col gap-6">
        <a
            :href="googleRedirect().url"
            class="inline-flex h-10 w-full items-center justify-center gap-3 rounded-md border border-input bg-background text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
            data-test="google-login-button"
        >
            <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
                <path
                    fill="#4285F4"
                    d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47a5.54 5.54 0 0 1-2.4 3.63v3h3.86c2.26-2.09 3.56-5.17 3.56-8.87z"
                />
                <path
                    fill="#34A853"
                    d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A11.99 11.99 0 0 0 12 24z"
                />
                <path
                    fill="#FBBC05"
                    d="M5.27 14.29a7.2 7.2 0 0 1 0-4.58V6.62H1.29a11.99 11.99 0 0 0 0 10.76l3.98-3.09z"
                />
                <path
                    fill="#EA4335"
                    d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.69 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"
                />
            </svg>
            Continuar con Google
        </a>

        <div class="relative">
            <Separator />
            <span
                class="absolute inset-0 -top-2.5 mx-auto w-fit bg-background px-2 text-xs text-muted-foreground"
            >
                o con tu email
            </span>
        </div>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        :class="props.googleHabilitado ? 'mt-6' : ''"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@ejemplo.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Contraseña"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Mantener la sesión abierta</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Entrar
            </Button>
        </div>
    </Form>
</template>
