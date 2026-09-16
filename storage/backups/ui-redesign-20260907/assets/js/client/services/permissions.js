/**
 * SmartQMS browser adapter foundation and permission normalization.
 */
(function () {
  'use strict';

  const existing = window.SmartQmsData || {};
  let cachedConfig = null;

  function runtimeConfig() {
    if (cachedConfig) return cachedConfig;
    const source = document.getElementById('smartqms-runtime-config');
    try {
      cachedConfig = source ? JSON.parse(source.textContent || '{}') : {};
    } catch (error) {
      cachedConfig = {};
    }
    return cachedConfig;
  }

  function provider() {
    const value = String(runtimeConfig().provider || 'local').toLowerCase();
    return ['local', 'shadow', 'supabase'].includes(value) ? value : 'local';
  }

  function endpoint(name, fallback = '') {
    return String(runtimeConfig().endpoints?.[name] || fallback);
  }

  function normalizeRole(role) {
    const value = String(role || '').toLowerCase();
    if (value === 'client' || value === 'customer') return 'customer';
    if (['staff', 'admin', 'super_admin'].includes(value)) return value;
    return 'customer';
  }

  function currentRole() {
    return normalizeRole(document.body?.dataset.appRole || runtimeConfig().role || 'customer');
  }

  function can(action, role = currentRole()) {
    const normalized = normalizeRole(role);
    const grants = {
      read_catalog: ['customer', 'staff', 'admin', 'super_admin'],
      create_ticket: ['customer'],
      read_own_ticket: ['customer'],
      operate_queue: ['staff', 'admin', 'super_admin'],
      manage_catalog: ['admin', 'super_admin'],
      manage_roles: ['super_admin'],
    };
    return (grants[action] || []).includes(normalized);
  }

  function csrfToken() {
    return window.SmartQms?.csrfTokenFromPage?.()
      || document.querySelector('meta[name="csrf-token"]')?.content
      || '';
  }

  async function requestJson(url, options = {}) {
    if (!url) throw new Error('SmartQMS endpoint is not configured.');
    const headers = new Headers(options.headers || {});
    const token = csrfToken();
    if (token && String(options.method || 'GET').toUpperCase() !== 'GET' && !headers.has('X-CSRF-Token')) {
      headers.set('X-CSRF-Token', token);
    }
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers,
    });
    const contentType = response.headers?.get?.('content-type') || '';
    const payload = contentType.includes('application/json')
      ? await response.json()
      : { success: response.ok, redirect_url: response.url };
    if (!response.ok || payload.success === false) {
      const error = new Error(payload.error || payload.message || 'SmartQMS request failed.');
      error.status = response.status;
      error.payload = payload;
      throw error;
    }
    return payload;
  }

  async function supabaseJson(path, options = {}) {
    const config = runtimeConfig().supabase || {};
    if (!config.enabled || !config.url || !config.publishableKey) {
      throw new Error('Supabase browser access is not configured.');
    }
    const headers = new Headers(options.headers || {});
    headers.set('apikey', config.publishableKey);
    headers.set('Accept', 'application/json');
    if (options.accessToken) headers.set('Authorization', `Bearer ${options.accessToken}`);
    const response = await fetch(`${String(config.url).replace(/\/$/, '')}/${String(path).replace(/^\//, '')}`, {
      ...options,
      headers,
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok) {
      const error = new Error(payload?.message || payload?.error_description || 'Supabase request failed.');
      error.status = response.status;
      throw error;
    }
    return payload;
  }

  window.SmartQmsData = Object.assign(existing, {
    runtimeConfig,
    provider,
    endpoint,
    normalizeRole,
    currentRole,
    can,
    csrfToken,
    requestJson,
    supabaseJson,
  });
})();
