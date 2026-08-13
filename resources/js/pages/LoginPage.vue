<script setup>
import { ref } from 'vue';
import { ArrowRight, LoaderCircle, LockKeyhole, ShieldCheck, Sparkles } from 'lucide-vue-next';
import { authApi } from '../services/authApi';
import { getApiError } from '../services/api';

const emit = defineEmits(['authenticated']);
const email = ref('demo@example.com');
const password = ref('password');
const submitting = ref(false);
const errorMessage = ref('');

async function submit() {
  submitting.value = true;
  errorMessage.value = '';

  try {
    const session = await authApi.login({ email: email.value, password: password.value });
    emit('authenticated', session);
  } catch (error) {
    errorMessage.value = getApiError(error, 'We could not sign you in.');
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <main class="min-h-screen bg-[#07111f] px-5 py-8 text-slate-100 sm:px-8">
    <div class="mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl items-center gap-10 lg:grid-cols-[1.15fr_0.85fr]">
      <section class="hidden max-w-xl lg:block">
        <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">
          <Sparkles :size="14" />
          Player rewards workspace
        </div>
        <h1 class="text-5xl font-semibold leading-[1.08] tracking-[-0.04em] text-white">
          Bonus operations,<br>
          built for confidence.
        </h1>
        <p class="mt-6 max-w-lg text-lg leading-8 text-slate-400">
          Claim verified promotions, follow every balance movement, and keep a complete audit trail from one secure workspace.
        </p>
        <div class="mt-10 flex gap-8 border-t border-white/10 pt-7 text-sm text-slate-400">
          <span class="flex items-center gap-2"><ShieldCheck
            :size="18"
            class="text-emerald-400"
          /> Token protected</span>
          <span class="flex items-center gap-2"><LockKeyhole
            :size="18"
            class="text-emerald-400"
          /> Exact-value ledger</span>
        </div>
      </section>

      <section class="mx-auto w-full max-w-md rounded-3xl border border-white/10 bg-white/[0.055] p-6 shadow-2xl shadow-black/30 backdrop-blur sm:p-8">
        <div class="mb-8">
          <div class="mb-6 flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-emerald-400 text-slate-950">
              <Sparkles
                :size="20"
                :stroke-width="2.4"
              />
            </span>
            <div>
              <p class="font-semibold tracking-tight text-white">
                Dublemint
              </p>
              <p class="text-xs text-slate-500">
                Promo Desk
              </p>
            </div>
          </div>
          <h2 class="text-2xl font-semibold tracking-tight text-white">
            Welcome back
          </h2>
          <p class="mt-2 text-sm leading-6 text-slate-400">
            Use the seeded demo player to review both ticket flows.
          </p>
        </div>

        <form
          class="space-y-5"
          @submit.prevent="submit"
        >
          <label class="block text-sm font-medium text-slate-300">
            Email address
            <input
              v-model="email"
              type="email"
              autocomplete="email"
              required
              class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-white outline-none transition placeholder:text-slate-600 focus:border-emerald-400/60 focus:ring-4 focus:ring-emerald-400/10"
            >
          </label>

          <label class="block text-sm font-medium text-slate-300">
            Password
            <input
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-white outline-none transition focus:border-emerald-400/60 focus:ring-4 focus:ring-emerald-400/10"
            >
          </label>

          <div
            v-if="errorMessage"
            role="alert"
            class="rounded-xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm leading-5 text-rose-200"
          >
            {{ errorMessage }}
          </div>

          <button
            type="submit"
            :disabled="submitting"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-400 px-4 py-3 font-semibold text-slate-950 transition hover:bg-emerald-300 focus:outline-none focus:ring-4 focus:ring-emerald-300/20 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <LoaderCircle
              v-if="submitting"
              :size="18"
              class="animate-spin"
            />
            <template v-else>
              Open dashboard
              <ArrowRight :size="18" />
            </template>
          </button>
        </form>

        <div class="mt-6 rounded-xl border border-dashed border-white/10 px-4 py-3 text-xs leading-5 text-slate-500">
          Demo access: <span class="font-medium text-slate-300">demo@example.com</span> / <span class="font-medium text-slate-300">password</span>
        </div>
      </section>
    </div>
  </main>
</template>
