/** SmartQMS service catalog adapter. */
(function () {
  'use strict';
  const data = window.SmartQmsData;
  if (!data) return;

  function normalize(row) {
    return {
      id: String(row.id || (row.legacy_id ? `local-service-${row.legacy_id}` : '')),
      legacyId: row.legacy_id == null ? null : Number(row.legacy_id),
      code: String(row.code || row.service_code || ''),
      name: String(row.name || row.service_name || ''),
      description: String(row.description || ''),
      priorityOnly: Boolean(row.priority_only),
      queueMode: String(row.queue_mode || 'central'),
      displayOrder: Number(row.display_order || 0),
      active: row.active === undefined ? Boolean(row.is_active) : Boolean(row.active),
      mlValue: Number(row.ml_value ?? row.service_encoded ?? 0),
    };
  }

  async function getServices() {
    if (!data.can('read_catalog')) throw new Error('Service catalog access is not permitted.');
    if (data.provider() === 'supabase' && data.runtimeConfig().supabase?.enabled) {
      const rows = await data.supabaseJson('/rest/v1/services?select=id,legacy_id,code,name,description,priority_only,queue_mode,display_order,active,ml_value&active=eq.true&order=display_order.asc,name.asc');
      return (Array.isArray(rows) ? rows : []).map(normalize);
    }
    const payload = await data.requestJson(data.endpoint('services'));
    return (payload.data || []).map(normalize);
  }

  data.services = Object.freeze({ getServices, normalize });
  data.getServices = getServices;
})();
