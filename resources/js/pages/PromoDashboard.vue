<script setup>
import { ref } from 'vue';
import { LogOut, ShieldCheck, Sparkles } from 'lucide-vue-next';
import BalancePanel from '../components/promo/BalancePanel.vue';
import PromoClaimForm from '../components/promo/PromoClaimForm.vue';
import PromoHistory from '../components/promo/PromoHistory.vue';

defineProps({
  user: { type: Object, required: true },
});
const emit = defineEmits(['balance-changed', 'logout']);
const history = ref(null);

function initials(name) {
  return name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
}
</script>

<template>
  <main class="min-h-screen bg-[#07111f] text-slate-100">
    <header class="border-b border-white/[0.07] bg-[#07111f]/90 px-5 backdrop-blur sm:px-8">
      <div class="mx-auto flex h-18 max-w-7xl items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="grid size-9 place-items-center rounded-xl bg-emerald-400 text-slate-950"><Sparkles
            :size="18"
            :stroke-width="2.4"
          /></span>
          <div>
            <p class="text-sm font-semibold tracking-tight text-white">
              Dublemint
            </p>
            <p class="hidden text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-600 sm:block">
              Promo Desk
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div class="hidden text-right sm:block">
            <p class="text-sm font-medium text-slate-200">
              {{ user.name }}
            </p>
            <p class="text-xs text-slate-600">
              {{ user.email }}
            </p>
          </div>
          <span class="grid size-9 place-items-center rounded-full border border-white/10 bg-white/5 text-xs font-semibold text-slate-300">{{ initials(user.name) }}</span>
          <button
            type="button"
            aria-label="Sign out"
            class="grid size-9 place-items-center rounded-lg text-slate-500 transition hover:bg-white/5 hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-400/30"
            @click="emit('logout')"
          >
            <LogOut :size="18" />
          </button>
        </div>
      </div>
    </header>

    <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10">
      <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-400">
            <ShieldCheck :size="14" /> Secure player workspace
          </p>
          <h1 class="mt-3 text-3xl font-semibold tracking-[-0.035em] text-white sm:text-4xl">
            Rewards dashboard
          </h1>
          <p class="mt-2 text-sm text-slate-500">
            Claim bonuses and review every promo balance event.
          </p>
        </div>
        <p class="text-xs text-slate-600">
          All amounts shown in {{ user.currency }}
        </p>
      </div>

      <div class="grid gap-5 lg:grid-cols-[0.82fr_1.18fr]">
        <BalancePanel
          :balance="user.balance"
          :currency="user.currency"
        />
        <PromoClaimForm
          @claimed="emit('balance-changed', $event)"
          @history-changed="history?.refresh()"
        />
      </div>

      <div class="mt-5">
        <PromoHistory
          ref="history"
          @balance-changed="emit('balance-changed', $event)"
        />
      </div>
    </div>
  </main>
</template>
