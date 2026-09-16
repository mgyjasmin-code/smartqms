/**
 * Supabase Auth adapter. Existing PHP authentication stays authoritative
 * until the configured provider mode is explicitly changed.
 */
(function () {
  'use strict';

  const data = window.SmartQmsData;
  if (!data) return;
  const tokenKey = 'smartqms-supabase-access-token';
  let accessToken = '';
  try { accessToken = window.sessionStorage.getItem(tokenKey) || ''; } catch (error) { /* optional */ }
  if (!accessToken) accessToken = String(data.runtimeConfig().supabase?.accessToken || '');

  function rememberToken(token) {
    accessToken = String(token || '');
    try {
      if (accessToken) window.sessionStorage.setItem(tokenKey, accessToken);
      else window.sessionStorage.removeItem(tokenKey);
    } catch (error) { /* storage may be unavailable */ }
  }

  function session() {
    return {
      provider: data.provider(),
      role: data.currentRole(),
      accessToken,
    };
  }

  async function signIn(email, password) {
    if (data.provider() !== 'supabase') {
      throw new Error('Local sign-in continues through the SmartQMS login form.');
    }
    const payload = await data.supabaseJson('/auth/v1/token?grant_type=password', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
      headers: { 'Content-Type': 'application/json' },
    });
    rememberToken(payload?.access_token || '');
    return payload;
  }

  async function register(credentials) {
    if (data.provider() !== 'supabase') {
      throw new Error('Local registration continues through the SmartQMS registration form.');
    }
    return data.supabaseJson('/auth/v1/signup', {
      method: 'POST',
      body: JSON.stringify({
        email: credentials.email,
        password: credentials.password,
        data: credentials.profile || {},
      }),
      headers: { 'Content-Type': 'application/json' },
    });
  }

  async function getCurrentUser(token = accessToken) {
    if (!token) return null;
    return data.supabaseJson('/auth/v1/user', { accessToken: token });
  }

  async function signOut(token = accessToken) {
    if (data.provider() === 'supabase' && token) {
      await data.supabaseJson('/auth/v1/logout', { method: 'POST', accessToken: token });
    }
    rememberToken('');
  }

  data.auth = Object.freeze({ session, signIn, register, getCurrentUser, signOut });
})();
