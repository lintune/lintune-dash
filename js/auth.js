// PKCE helpers
async function generateCodeVerifier() {
  const array = new Uint8Array(32);
  crypto.getRandomValues(array);
  return btoa(String.fromCharCode(...array)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

async function generateCodeChallenge(verifier) {
  const encoder = new TextEncoder();
  const data = encoder.encode(verifier);
  const digest = await crypto.subtle.digest('SHA-256', data);
  return btoa(String.fromCharCode(...new Uint8Array(digest))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

// Realm lookup via Worker (D1 backed)
async function getRealmsByEmail(email) {
  const res = await fetch(`${CONFIG.apiBase}/auth/realms?email=${encodeURIComponent(email)}`);
  if (!res.ok) throw new Error('Could not look up realms for this email.');
  const data = await res.json();
  return data.realms; // string[]
}

// Start PKCE login for a given realm
async function startLogin(realm) {
  const verifier = await generateCodeVerifier();
  const challenge = await generateCodeChallenge(verifier);
  sessionStorage.setItem('pkce_verifier', verifier);
  sessionStorage.setItem('pkce_realm', realm);

  const params = new URLSearchParams({
    client_id: CONFIG.clientId,
    redirect_uri: CONFIG.redirectUri,
    response_type: 'code',
    scope: 'openid profile email',
    code_challenge: challenge,
    code_challenge_method: 'S256',
  });

  window.location.href = `${CONFIG.keycloakBase}/realms/${realm}/protocol/openid-connect/auth?${params}`;
}

// Exchange code for tokens (called on dashboard.html load)
async function handleCallback() {
  const params = new URLSearchParams(window.location.search);
  const code = params.get('code');
  if (!code) return;

  const realm = sessionStorage.getItem('pkce_realm');
  const verifier = sessionStorage.getItem('pkce_verifier');

  const body = new URLSearchParams({
    grant_type: 'authorization_code',
    client_id: CONFIG.clientId,
    redirect_uri: CONFIG.redirectUri,
    code,
    code_verifier: verifier,
  });

  const res = await fetch(`${CONFIG.keycloakBase}/realms/${realm}/protocol/openid-connect/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  });

  if (!res.ok) {
    window.location.href = '/index.html?error=token_exchange_failed';
    return;
  }

  const tokens = await res.json();
  sessionStorage.setItem('access_token', tokens.access_token);
  sessionStorage.setItem('refresh_token', tokens.refresh_token);
  sessionStorage.setItem('id_token', tokens.id_token);

  // Clean up URL
  window.history.replaceState({}, document.title, window.location.pathname);
}

function getAccessToken() {
  return sessionStorage.getItem('access_token');
}

function getActiveRealm() {
  return sessionStorage.getItem('pkce_realm');
}

function logout() {
  const realm = getActiveRealm();
  const idToken = sessionStorage.getItem('id_token');
  sessionStorage.clear();

  const params = new URLSearchParams({
    post_logout_redirect_uri: window.location.origin + '/index.html',
    id_token_hint: idToken,
  });

  window.location.href = `${CONFIG.keycloakBase}/realms/${realm}/protocol/openid-connect/logout?${params}`;
}

// Guard: redirect to login if no token
function requireAuth() {
  if (!getAccessToken()) {
    window.location.href = '/index.html';
  }
}
