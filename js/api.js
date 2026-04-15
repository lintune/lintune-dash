async function apiFetch(path, options = {}) {
  const token = getAccessToken();
  const realm = getActiveRealm();

  const res = await fetch(`${CONFIG.apiBase}${path}`, {
    ...options,
    headers: {
      'Authorization': `Bearer ${token}`,
      'X-Realm': realm,
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
  });

  if (res.status === 401) {
    logout();
    return;
  }

  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}

async function getUsers() {
  return apiFetch('/users');
}

async function getDashboardStats() {
  return apiFetch('/dashboard/stats');
}
