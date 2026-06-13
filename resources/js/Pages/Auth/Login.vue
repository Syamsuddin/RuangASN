<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const showMfa = ref(false);
const mfaToken = ref('');
const otpCode = ref('');

const submit = () => {
    form.post('/login', {
        onSuccess: (page) => {
            if (page.props.requires_mfa) {
                showMfa.value = true;
                mfaToken.value = page.props.mfa_token as string;
            }
        },
        onError: () => {},
    });
};

const submitMfa = () => {
    useForm({ mfa_token: mfaToken.value, otp_code: otpCode.value }).post('/login/mfa');
};
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-900 to-blue-700 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <span class="text-white text-2xl font-bold">R</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">RuangASN</h1>
                <p class="text-gray-500 text-sm mt-1">Platform Digital Workspace ASN</p>
            </div>

            <!-- MFA Form -->
            <form v-if="showMfa" @submit.prevent="submitMfa" class="space-y-4">
                <div class="text-center mb-4">
                    <p class="text-sm text-gray-600">Masukkan kode OTP dari aplikasi authenticator Anda</p>
                </div>
                <div>
                    <input
                        v-model="otpCode"
                        type="text"
                        placeholder="Kode OTP (6 digit)"
                        maxlength="8"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-center text-xl tracking-widest"
                        autofocus
                    />
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    Verifikasi OTP
                </button>
                <button type="button" @click="showMfa = false" class="w-full text-sm text-gray-500 hover:text-gray-700">
                    Kembali ke Login
                </button>
            </form>

            <!-- Login Form -->
            <form v-else @submit.prevent="submit" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIP atau Email</label>
                    <input
                        v-model="form.login"
                        type="text"
                        placeholder="NIP 18 digit atau email dinas"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        autofocus
                    />
                    <p v-if="form.errors.login" class="text-red-500 text-xs mt-1">{{ form.errors.login }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        placeholder="Password"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                </div>
                <div v-if="form.errors.login || form.errors.password" class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <p class="text-red-600 text-sm">{{ form.errors.login || form.errors.password }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-blue-600 text-white py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors disabled:opacity-50"
                >
                    <span v-if="form.processing">Memproses...</span>
                    <span v-else>Masuk</span>
                </button>
                <p class="text-center text-xs text-gray-500">
                    Sistem digunakan khusus untuk ASN yang terdaftar.<br>
                    Hubungi admin jika belum memiliki akses.
                </p>
            </form>
        </div>
    </div>
</template>
