<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import {
  AlertCircle,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  History,
  LoaderCircle,
  RotateCcw,
  XCircle,
} from 'lucide-vue-next';
import { getApiError } from '../../services/api';
import { promoApi } from '../../services/promoApi';

const filters = [
  { label: 'All', value: '' },
  { label: 'Applied', value: 'applied' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Revoked', value: 'revoked' },
];
const items = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const activeFilter = ref('');
const page = ref(1);
const loading = ref(true);
const errorMessage = ref('');

const statusDetails = {
  applied: { label: 'Applied', icon: CheckCircle2, classes: 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/20' },
  rejected: { label: 'Rejected', icon: XCircle, classes: 'bg-rose-400/10 text-rose-300 ring-rose-400/20' },
  revoked: { label: 'Revoked', icon: RotateCcw, classes: 'bg-amber-400/10 text-amber-300 ring-amber-400/20' },
};
const hasPrevious = computed(() => meta.value.current_page > 1);
const hasNext = computed(() => meta.value.current_page < meta.value.last_page);

async function loadHistory() {
  loading.value = true;
  errorMessage.value = '';

  try {
    const result = await promoApi.history({ status: activeFilter.value, page: page.value });
    items.value = result.data;
    meta.value = result.meta;
  } catch (error) {
    errorMessage.value = getApiError(error, 'History could not be loaded.');
  } finally {
    loading.value = false;
  }
}

function selectFilter(value) {
  if (activeFilter.value === value) return;
  activeFilter.value = value;
  page.value = 1;
}

function changePage(nextPage) {
  if (nextPage < 1 || nextPage > meta.value.last_page) return;
  page.value = nextPage;
}

function formatDate(value) {
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

watch([activeFilter, page], loadHistory);
onMounted(loadHistory);
defineExpose({ refresh: loadHistory });
</script>

<template>
  <section class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.035]">
    <header class="flex flex-col gap-5 border-b border-white/10 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex items-center gap-3">
        <span class="grid size-9 place-items-center rounded-xl bg-sky-400/10 text-sky-300">
          <History :size="18" />
        </span>
        <div>
          <h2 class="font-semibold text-white">
            Promo history
          </h2>
          <p class="mt-0.5 text-xs text-slate-500">
            {{ meta.total }} recorded {{ meta.total === 1 ? 'event' : 'events' }}
          </p>
        </div>
      </div>

      <div
        class="flex w-full gap-1 overflow-x-auto rounded-xl bg-slate-950/50 p-1 lg:w-auto"
        role="group"
        aria-label="Filter promo history"
      >
        <button
          v-for="filter in filters"
          :key="filter.value"
          type="button"
          :aria-pressed="activeFilter === filter.value"
          :class="[
            'whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold transition',
            activeFilter === filter.value ? 'bg-white/10 text-white shadow-sm' : 'text-slate-500 hover:text-slate-300',
          ]"
          @click="selectFilter(filter.value)"
        >
          {{ filter.label }}
        </button>
      </div>
    </header>

    <div
      v-if="loading"
      class="grid min-h-64 place-items-center text-sm text-slate-500"
    >
      <span class="flex items-center gap-2"><LoaderCircle
        :size="17"
        class="animate-spin"
      /> Loading history…</span>
    </div>

    <div
      v-else-if="errorMessage"
      class="m-5 flex items-start gap-3 rounded-xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-200"
    >
      <AlertCircle
        :size="18"
        class="mt-0.5 shrink-0"
      />
      <div>
        <p>{{ errorMessage }}</p>
        <button
          type="button"
          class="mt-2 font-semibold underline underline-offset-4"
          @click="loadHistory"
        >
          Try again
        </button>
      </div>
    </div>

    <div
      v-else-if="!items.length"
      class="grid min-h-64 place-items-center px-6 py-12 text-center"
    >
      <div>
        <span class="mx-auto grid size-11 place-items-center rounded-full bg-white/5 text-slate-500"><History :size="20" /></span>
        <p class="mt-4 font-medium text-slate-300">
          No promo events here
        </p>
        <p class="mt-1 text-sm text-slate-600">
          Try another filter or claim a code above.
        </p>
      </div>
    </div>

    <template v-else>
      <div class="hidden overflow-x-auto md:block">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-white/10 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-600">
            <tr>
              <th class="px-6 py-4">
                Promo code
              </th>
              <th class="px-6 py-4">
                Date
              </th>
              <th class="px-6 py-4">
                Amount
              </th>
              <th class="px-6 py-4">
                Status
              </th>
              <th class="px-6 py-4">
                Details
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/[0.07]">
            <tr
              v-for="item in items"
              :key="item.id"
              class="transition hover:bg-white/[0.025]"
            >
              <td class="px-6 py-4 font-mono text-xs font-semibold tracking-[0.08em] text-slate-200">
                {{ item.code }}
              </td>
              <td class="whitespace-nowrap px-6 py-4 text-slate-500">
                {{ formatDate(item.created_at) }}
              </td>
              <td class="px-6 py-4 font-medium text-slate-300">
                {{ item.amount ? `$${item.amount}` : '—' }}
              </td>
              <td class="px-6 py-4">
                <span :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', statusDetails[item.status].classes]">
                  <component
                    :is="statusDetails[item.status].icon"
                    :size="13"
                  />
                  {{ statusDetails[item.status].label }}
                </span>
              </td>
              <td class="max-w-xs px-6 py-4 text-xs leading-5 text-slate-500">
                {{ item.rejection_reason || 'Balance updated successfully.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="divide-y divide-white/[0.07] md:hidden">
        <article
          v-for="item in items"
          :key="item.id"
          class="p-5"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-mono text-sm font-semibold tracking-[0.08em] text-slate-200">
                {{ item.code }}
              </p>
              <p class="mt-1 text-xs text-slate-600">
                {{ formatDate(item.created_at) }}
              </p>
            </div>
            <span :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', statusDetails[item.status].classes]">
              <component
                :is="statusDetails[item.status].icon"
                :size="13"
              />
              {{ statusDetails[item.status].label }}
            </span>
          </div>
          <div class="mt-4 flex items-end justify-between gap-4">
            <p class="text-xs leading-5 text-slate-500">
              {{ item.rejection_reason || 'Balance updated successfully.' }}
            </p>
            <p class="shrink-0 font-semibold text-slate-200">
              {{ item.amount ? `$${item.amount}` : '—' }}
            </p>
          </div>
        </article>
      </div>

      <footer class="flex items-center justify-between border-t border-white/10 px-5 py-4 sm:px-6">
        <p class="text-xs text-slate-600">
          Page {{ meta.current_page }} of {{ meta.last_page }}
        </p>
        <div class="flex gap-2">
          <button
            type="button"
            :disabled="!hasPrevious"
            aria-label="Previous page"
            class="grid size-9 place-items-center rounded-lg border border-white/10 text-slate-400 transition hover:border-white/20 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
            @click="changePage(page - 1)"
          >
            <ChevronLeft :size="17" />
          </button>
          <button
            type="button"
            :disabled="!hasNext"
            aria-label="Next page"
            class="grid size-9 place-items-center rounded-lg border border-white/10 text-slate-400 transition hover:border-white/20 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
            @click="changePage(page + 1)"
          >
            <ChevronRight :size="17" />
          </button>
        </div>
      </footer>
    </template>
  </section>
</template>
