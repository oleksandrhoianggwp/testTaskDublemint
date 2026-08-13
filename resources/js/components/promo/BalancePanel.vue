<script setup>
import { ArrowUpRight, WalletCards } from 'lucide-vue-next';

defineProps({
  balance: { type: String, required: true },
  currency: { type: String, default: 'USD' },
});
</script>

<template>
  <section class="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-slate-800 to-slate-900 p-6 shadow-xl shadow-black/10">
    <div class="absolute -right-16 -top-16 size-48 rounded-full bg-emerald-400/10 blur-2xl" />
    <div class="relative flex items-start justify-between gap-6">
      <div>
        <p class="flex items-center gap-2 text-sm font-medium text-slate-400">
          <WalletCards :size="17" />
          Available balance
        </p>
        <p class="mt-4 text-4xl font-semibold tracking-[-0.04em] text-white sm:text-5xl">
          <span class="mr-1 text-2xl text-slate-500">$</span>{{ balance }}
        </p>
        <p class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
          {{ currency }} wallet
        </p>
      </div>
      <span class="grid size-10 place-items-center rounded-full bg-emerald-400/10 text-emerald-300">
        <ArrowUpRight :size="19" />
      </span>
    </div>
  </section>
</template>
