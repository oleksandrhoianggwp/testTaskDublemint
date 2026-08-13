<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { LoaderCircle } from 'lucide-vue-next';
import LoginPage from './pages/LoginPage.vue';
import PromoDashboard from './pages/PromoDashboard.vue';
import { authApi } from './services/authApi';
import { clearAuthToken, getAuthToken, setAuthToken } from './services/api';

const user = ref(null);
const booting = ref(Boolean(getAuthToken()));

async function restoreSession() {
  if (!getAuthToken()) {
    booting.value = false;
    return;
  }

  try {
    user.value = await authApi.me();
  } catch {
    clearAuthToken();
  } finally {
    booting.value = false;
  }
}

function handleAuthenticated(session) {
  setAuthToken(session.token);
  user.value = session.user;
}

function handleBalanceChanged(balance) {
  user.value = { ...user.value, balance };
}

async function handleLogout() {
  try {
    await authApi.logout();
  } finally {
    clearAuthToken();
    user.value = null;
  }
}

function handleUnauthorized() {
  clearAuthToken();
  user.value = null;
  booting.value = false;
}

onMounted(() => {
  window.addEventListener('auth:unauthorized', handleUnauthorized);
  restoreSession();
});

onBeforeUnmount(() => window.removeEventListener('auth:unauthorized', handleUnauthorized));
</script>

<template>
  <div
    v-if="booting"
    class="grid min-h-screen place-items-center bg-[#07111f] text-slate-300"
  >
    <div class="flex items-center gap-3 text-sm font-medium">
      <LoaderCircle
        :size="20"
        class="animate-spin text-emerald-400"
      />
      Restoring your secure session…
    </div>
  </div>

  <LoginPage
    v-else-if="!user"
    @authenticated="handleAuthenticated"
  />

  <PromoDashboard
    v-else
    :user="user"
    @balance-changed="handleBalanceChanged"
    @logout="handleLogout"
  />
</template>
