// Populate realm switcher dropdown in the navbar
async function initRealmSwitcher() {
  const email = parseJwt(getAccessToken())?.email;
  if (!email) return;

  const realms = await getRealmsByEmail(email).catch(() => []);
  const activeRealm = getActiveRealm();

  const switcher = document.getElementById('realm-switcher');
  if (!switcher || realms.length <= 1) return;

  switcher.classList.remove('d-none');
  const menu = document.getElementById('realm-menu');

  realms.forEach(realm => {
    const li = document.createElement('li');
    li.innerHTML = `<a class="dropdown-item ${realm === activeRealm ? 'active' : ''}" href="#" data-realm="${realm}">${realm}</a>`;
    li.querySelector('a').addEventListener('click', (e) => {
      e.preventDefault();
      sessionStorage.setItem('pkce_realm', realm);
      startLogin(realm);
    });
    menu.appendChild(li);
  });
}

function parseJwt(token) {
  try {
    return JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
  } catch {
    return null;
  }
}

function setUserInfo() {
  const payload = parseJwt(getAccessToken());
  if (!payload) return;

  const nameEl = document.getElementById('user-display-name');
  const realmEl = document.getElementById('active-realm-label');

  if (nameEl) nameEl.textContent = payload.name || payload.preferred_username || payload.email;
  if (realmEl) realmEl.textContent = getActiveRealm();
}
