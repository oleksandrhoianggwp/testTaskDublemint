<script setup>
import { computed, ref } from 'vue';
import { CheckCircle2, LoaderCircle, Sparkles, XCircle } from 'lucide-vue-next';
import { getApiError } from '../../services/api';
import { promoApi } from '../../services/promoApi';

const emit = defineEmits(['claimed', 'history-changed']);
const code = ref('');
const submitting = ref(false);
const feedback = ref(null);
const normalizedCode = computed(() => code.value.trim().toUpperCase());

async function submit() {
  if (!/^[A-Za-z0-9]{6,12}$/.test(normalizedCode.value)) {
    feedback.value = {
      type: 'error',
      message: 'Use 6–12 Latin letters or numbers with no spaces or symbols.',
    };
    return;
  }

  submitting.value = true;
  feedback.value = null;

  try {
    const result = await promoApi.claim(normalizedCode.value);
    code.value = '';
    feedback.value = {
      type: 'success',
      message: `${result.bonus_amount} USD was added to your balance.`,
    };
    emit('claimed', result.balance);
    emit('history-changed');
  } catch (error) {
    feedback.value = { type: 'error', message: getApiError(error, 'The promo could not be claimed.') };
    if (error.response?.data?.code?.startsWith('PROMO_')) emit('history-changed');
  } finally {
    submitting.value = false;
  }
}

function clearFeedback() {
  feedback.value = null;
}

defineExpose({ clearFeedback });
</script>

<template>
  <section class="rounded-2xl border border-white/10 bg-white/[0.045] p-6">
    <div class="flex items-start gap-3">
      <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-400/10 text-violet-300">
        <Sparkles :size="19" />
      </span>
      <div>
        <h2 class="font-semibold text-white">
          Claim a promotion
        </h2>
        <p class="mt-1 text-sm leading-5 text-slate-500">
          Enter your 6–12 character reward code.
        </p>
      </div>
    </div>

    <form
      class="mt-6"
      @submit.prevent="submit"
    >
      <label
        for="promo-code"
        class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"
      >Promo code</label>
      <div class="mt-2 flex flex-col gap-3 sm:flex-row">
        <input
          id="promo-code"
          v-model="code"
          :disabled="submitting"
          maxlength="12"
          autocomplete="off"
          placeholder="WELCOME10"
          class="min-w-0 flex-1 rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 font-mono text-sm uppercase tracking-[0.14em] text-white outline-none transition placeholder:text-slate-700 focus:border-emerald-400/60 focus:ring-4 focus:ring-emerald-400/10 disabled:opacity-60"
        >
        <button
          type="submit"
          :disabled="submitting || !code.trim()"
          class="flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 focus:outline-none focus:ring-4 focus:ring-white/10 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <LoaderCircle
            v-if="submitting"
            :size="17"
            class="animate-spin"
          />
          {{ submitting ? 'Applying…' : 'Apply code' }}
        </button>
      </div>
    </form>

    <div
      v-if="feedback"
      role="status"
      :class="[
        'mt-4 flex items-start gap-2 rounded-xl border px-3.5 py-3 text-sm leading-5',
        feedback.type === 'success'
          ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200'
          : 'border-rose-400/20 bg-rose-400/10 text-rose-200',
      ]"
    >
      <CheckCircle2
        v-if="feedback.type === 'success'"
        :size="17"
        class="mt-0.5 shrink-0"
      />
      <XCircle
        v-else
        :size="17"
        class="mt-0.5 shrink-0"
      />
      {{ feedback.message }}
    </div>
  </section>
</template>
