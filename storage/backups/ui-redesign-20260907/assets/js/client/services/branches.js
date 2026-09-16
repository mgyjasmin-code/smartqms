/** SmartQMS branch/location adapter. */
(function () {
  'use strict';
  const data = window.SmartQmsData;
  if (!data) return;

  function normalize(row) {
    return {
      id: String(row.id || ''),
      legacyId: row.legacy_id == null ? null : Number(row.legacy_id),
      name: String(row.name || 'Barangay Health Center'),
      address: String(row.address || ''),
      active: row.active !== false,
    };
  }

  async function getBranches() {
    if (!data.can('read_catalog')) throw new Error('Branch access is not permitted.');
    if (data.provider() === 'supabase' && data.runtimeConfig().supabase?.enabled) {
      const rows = await data.supabaseJson('/rest/v1/branches?select=id,legacy_id,name,address,active&active=eq.true&order=name.asc');
      return (Array.isArray(rows) ? rows : []).map(normalize);
    }
    const payload = await data.requestJson(data.endpoint('branches'));
    return (payload.data || []).map(normalize);
  }

  data.branches = Object.freeze({ getBranches, normalize });
  data.getBranches = getBranches;
})();
