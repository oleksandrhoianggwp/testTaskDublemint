<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { AlertTriangle, LoaderCircle, RotateCcw, X } from 'lucide-vue-next';

const props = defineProps({
  open: { type: Boolean, default: false },
  claim: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
});
const emit = defineEmits(['close', 'confirm']);
const confirmButton = ref(null);

function close() {
  if (!props.loading) emit('close');
}

function handleKeydown(event) {
  if (event.key === 'Escape' && props.open) close();
}

watch(
  () => props.open,
  async (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
    if (isOpen) {
      await nextTick();
      confirmButton.value?.focus();
    }
  },
);

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown);
  document.body.style.overflow = '';
});
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open && claim"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-950/80 px-5 py-8 backdrop-blur-sm"
        role="presentation"
        @mousedown.self="close"
      >
        <section
          role="dialog"
          aria-modal="true"
          aria-labelledby="revoke-title"
          aria-describedby="revoke-description"
          class="w-full max-w-md rounded-2xl border border-white/10 bg-[#101c2c] p-6 text-slate-100 shadow-2xl shadow-black/50"
        >
          <div class="flex items-start justify-between gap-5">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber-400/10 text-amber-300">
              <RotateCcw :size="20" />
            </span>
            <button
              type="button"
              :disabled="loading"
              aria-label="Close confirmation"
              class="grid size-8 place-items-center rounded-lg text-slate-500 transition hover:bg-white/5 hover:text-white disabled:opacity-40"
              @click="close"
            >
              <X :size="18" />
            </button>
          </div>

          <h2
            id="revoke-title"
            class="mt-5 text-xl font-semibold tracking-tight text-white"
          >
            Revoke this bonus?
          </h2>
          <p
            id="revoke-description"
            class="mt-2 text-sm leading-6 text-slate-400"
          >
            This will deduct the original bonus from the player wallet. The promo remains used and cannot be claimed again.
          </p>

          <dl class="mt-5 grid grid-cols-2 gap-3 rounded-xl border border-white/[0.07] bg-slate-950/40 p-4">
            <div>
              <dt class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-600">
                Promo
              </dt>
              <dd class="mt-1.5 font-mono text-sm font-semibold tracking-[0.08em] text-slate-200">
                {{ claim.code }}
              </dd>
            </div>
            <div class="text-right">
              <dt class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-600">
                Deduction
              </dt>
              <dd class="mt-1.5 text-sm font-semibold text-amber-300">
                −${{ claim.amount }}
              </dd>
            </div>
          </dl>

          <div
            v-if="error"
            role="alert"
            class="mt-4 flex items-start gap-2 rounded-xl border border-rose-400/20 bg-rose-400/10 p-3 text-sm leading-5 text-rose-200"
          >
            <AlertTriangle
              :size="17"
              class="mt-0.5 shrink-0"
            />
            {{ error }}
          </div>

          <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button
              type="button"
              :disabled="loading"
              class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:border-white/20 hover:bg-white/5 hover:text-white disabled:opacity-50"
              @click="close"
            >
              Keep bonus
            </button>
            <button
              ref="confirmButton"
              type="button"
              :disabled="loading"
              class="flex items-center justify-center gap-2 rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 focus:outline-none focus:ring-4 focus:ring-amber-300/20 disabled:cursor-not-allowed disabled:opacity-60"
              @click="emit('confirm')"
            >
              <LoaderCircle
                v-if="loading"
                :size="17"
                class="animate-spin"
              />
              {{ loading ? 'Revoking…' : 'Confirm revoke' }}
            </button>
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
