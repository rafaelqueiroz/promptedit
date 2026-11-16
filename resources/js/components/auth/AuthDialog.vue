<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as login } from '@/routes/login';
import { request as requestPasswordReset } from '@/routes/password';
import { store as register } from '@/routes/register';
import type { AuthMode } from '@/types';

defineProps<{
    canResetPassword: boolean;
    passwordRules: string;
    /** Shown instead of the default description, e.g. why signing in is needed right now. */
    reason?: string;
}>();

const open = defineModel<boolean>('open', { required: true });
const mode = defineModel<AuthMode>('mode', { default: 'login' });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-xl">
                    {{
                        mode === 'login'
                            ? 'Sign in to generate'
                            : 'Create your account'
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        reason ??
                        (mode === 'login'
                            ? 'Welcome back. Your generations and what they cost are where you left them.'
                            : 'An email and a password is all it takes. Your first image is seconds away.')
                    }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="mode === 'login'"
                v-bind="login.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="grid gap-5"
            >
                <div class="grid gap-2">
                    <Label for="auth-email">Email address</Label>
                    <Input
                        id="auth-email"
                        type="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="auth-password">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="requestPasswordReset()"
                            class="text-sm"
                        >
                            Forgot your password?
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="auth-password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <Label
                    for="auth-remember"
                    class="flex items-center gap-3 font-normal"
                >
                    <Checkbox id="auth-remember" name="remember" />
                    Remember me
                </Label>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    data-test="auth-login-button"
                >
                    <Spinner v-if="processing" />
                    Sign in
                </Button>

                <p class="text-center text-sm text-muted-foreground">
                    New here?
                    <button
                        type="button"
                        class="text-foreground underline underline-offset-4"
                        @click="mode = 'register'"
                    >
                        Create an account
                    </button>
                </p>
            </Form>

            <Form
                v-else
                v-bind="register.form()"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="grid gap-5"
            >
                <div class="grid gap-2">
                    <Label for="auth-name">Name</Label>
                    <Input
                        id="auth-name"
                        type="text"
                        name="name"
                        required
                        autocomplete="name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="auth-register-email">Email address</Label>
                    <Input
                        id="auth-register-email"
                        type="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="auth-register-password">Password</Label>
                    <PasswordInput
                        id="auth-register-password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Password"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="auth-password-confirmation">
                        Confirm password
                    </Label>
                    <PasswordInput
                        id="auth-password-confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    data-test="auth-register-button"
                >
                    <Spinner v-if="processing" />
                    Create account
                </Button>

                <p class="text-center text-sm text-muted-foreground">
                    Already have an account?
                    <button
                        type="button"
                        class="text-foreground underline underline-offset-4"
                        @click="mode = 'login'"
                    >
                        Sign in
                    </button>
                </p>
            </Form>
        </DialogContent>
    </Dialog>
</template>
