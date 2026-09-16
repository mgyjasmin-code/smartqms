/** SmartQMS ticket and queue action adapter. */
(function () {
  'use strict';
  const data = window.SmartQmsData;
  if (!data) return;

  function formPayload(values = {}) {
    const form = new FormData();
    Object.entries(values).forEach(([key, value]) => {
      if (value !== undefined && value !== null) form.append(key, String(value));
    });
    const token = data.csrfToken();
    if (token && !form.has('csrf_token')) form.append('csrf_token', token);
    return form;
  }

  async function createTicket(payload) {
    if (!data.can('create_ticket')) throw new Error('Ticket creation is not permitted.');
    if (data.provider() === 'supabase' && data.runtimeConfig().supabase?.enabled) {
      const accessToken = payload.accessToken || data.auth?.session().accessToken || '';
      return data.requestJson(data.endpoint('createTicket'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(accessToken ? { Authorization: `Bearer ${accessToken}` } : {}),
        },
        body: JSON.stringify({
          service_id: payload.serviceId ?? payload.service_id,
          branch_id: payload.branchId ?? payload.branch_id,
          client_type: payload.clientType ?? payload.client_type ?? 'regular',
        }),
      });
    }
    return data.requestJson(data.endpoint('createTicket'), {
      method: 'POST',
      body: formPayload({
        service_id: payload.serviceId ?? payload.service_id,
        client_type: payload.clientType ?? payload.client_type ?? 'regular',
        branch_id: payload.branchId ?? payload.branch_id ?? '',
      }),
    });
  }

  async function getTicket(ticketId = '') {
    const suffix = ticketId ? `?ticket_id=${encodeURIComponent(ticketId)}` : '';
    const accessToken = data.auth?.session?.().accessToken || '';
    return data.requestJson(`${data.endpoint('ticket')}${suffix}`, {
      headers: accessToken ? { Authorization: `Bearer ${accessToken}` } : {},
    });
  }

  async function getLiveQueue(branchId = '') {
    if (data.provider() === 'supabase' && data.runtimeConfig().supabase?.enabled) {
      const snapshot = await data.supabaseJson('/rest/v1/rpc/get_live_queue_snapshot', {
        method: 'POST',
        accessToken: data.auth?.session?.().accessToken,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ p_branch_id: branchId || null }),
      });
      return { success: true, data: snapshot || {}, provider: 'supabase' };
    }
    const suffix = branchId ? `?branch_id=${encodeURIComponent(branchId)}` : '';
    return data.requestJson(`${data.endpoint('liveQueue')}${suffix}`);
  }

  async function staffAction(name, payload = {}) {
    if (!data.can('operate_queue')) throw new Error('Queue operation is not permitted.');
    return data.requestJson(data.endpoint(name), {
      method: 'POST',
      body: formPayload(payload),
    });
  }

  const callNextTicket = counterId => staffAction('callNext', { counter_id: counterId });
  const completeTicket = ticketId => staffAction('completeTicket', { ticket_id: ticketId });
  const skipTicket = ticketId => staffAction('skipTicket', { ticket_id: ticketId });
  const voidTicket = ticketId => staffAction('voidTicket', { ticket_id: ticketId });
  const setCounterStatus = status => staffAction('counterStatus', { status });

  data.queue = Object.freeze({
    createTicket,
    getTicket,
    getLiveQueue,
    callNextTicket,
    completeTicket,
    skipTicket,
    voidTicket,
    setCounterStatus,
  });
  Object.assign(data, {
    createTicket,
    getTicket,
    getLiveQueue,
    callNextTicket,
    completeTicket,
    skipTicket,
    voidTicket,
    setCounterStatus,
  });
})();
