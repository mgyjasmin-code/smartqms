/** Supabase Postgres-change subscription with a safe polling fallback. */
(function () {
  'use strict';
  const data = window.SmartQmsData;
  if (!data) return;

  const TABLES = Object.freeze(['tickets', 'counters', 'queue_events']);

  function realtimeUrl(config) {
    const url = new URL(config.url);
    url.protocol = url.protocol === 'https:' ? 'wss:' : 'ws:';
    url.pathname = `${url.pathname.replace(/\/$/, '')}/realtime/v1/websocket`;
    url.search = new URLSearchParams({ apikey: config.publishableKey, vsn: '1.0.0' }).toString();
    return url.toString();
  }

  function subscribeToQueue(branchId, callback, options = {}) {
    if (typeof callback !== 'function') throw new TypeError('Queue subscription callback is required.');
    const interval = Math.max(5000, Number(options.interval || 10000));
    const config = data.runtimeConfig().supabase || {};
    const useRealtime = data.provider() === 'supabase'
      && config.enabled && config.url && config.publishableKey && 'WebSocket' in window;
    let active = true;
    let inFlight = false;
    let socket = null;
    let pollTimer = null;
    let heartbeatTimer = null;
    let safetyPollTimer = null;
    let reconnectTimer = null;
    let refreshTimer = null;
    let reference = 0;
    let reconnectAttempt = 0;

    const update = async source => {
      if (!active || inFlight || document.hidden) return;
      inFlight = true;
      try {
        callback(await data.getLiveQueue(branchId), null, { source });
      } catch (error) {
        callback(null, error, { source });
      } finally {
        inFlight = false;
      }
    };

    const scheduleUpdate = source => {
      window.clearTimeout(refreshTimer);
      refreshTimer = window.setTimeout(() => update(source), 120);
    };
    const startPolling = () => {
      if (pollTimer !== null) return;
      pollTimer = window.setInterval(() => update('polling'), interval);
    };
    const stopPolling = () => {
      if (pollTimer === null) return;
      window.clearInterval(pollTimer);
      pollTimer = null;
    };
    const startSafetyPolling = () => {
      if (safetyPollTimer !== null) return;
      safetyPollTimer = window.setInterval(
        () => update('safety-polling'),
        Math.max(30000, interval * 6)
      );
    };
    const stopSafetyPolling = () => {
      if (safetyPollTimer === null) return;
      window.clearInterval(safetyPollTimer);
      safetyPollTimer = null;
    };

    const joinRealtime = () => {
      if (!active || !useRealtime || document.hidden || socket) return;
      try {
        socket = new window.WebSocket(realtimeUrl(config));
      } catch (error) {
        socket = null;
        startPolling();
        return;
      }
      socket.addEventListener('open', () => {
        reconnectAttempt = 0;
        stopPolling();
        startSafetyPolling();
        const changes = TABLES.map(table => ({
          event: '*', schema: 'public', table,
          ...(branchId ? { filter: `branch_id=eq.${branchId}` } : {}),
        }));
        socket.send(JSON.stringify({
          topic: `realtime:smartqms-queue-${branchId || 'all'}`,
          event: 'phx_join',
          payload: {
            config: {
              broadcast: { self: false }, presence: { key: '' },
              postgres_changes: changes,
            },
            access_token: data.auth?.session?.().accessToken || config.publishableKey,
          },
          ref: String(++reference),
        }));
        heartbeatTimer = window.setInterval(() => {
          if (socket?.readyState === window.WebSocket.OPEN) {
            socket.send(JSON.stringify({
              topic: 'phoenix', event: 'heartbeat', payload: {}, ref: String(++reference),
            }));
          }
        }, 25000);
      });
      socket.addEventListener('message', event => {
        let message;
        try { message = JSON.parse(event.data); } catch (error) { return; }
        if (message?.event === 'phx_reply' && message?.payload?.status === 'error') {
          startPolling();
          return;
        }
        if (message?.event === 'postgres_changes') scheduleUpdate('realtime');
      });
      socket.addEventListener('error', () => startPolling());
      socket.addEventListener('close', () => {
        socket = null;
        window.clearInterval(heartbeatTimer);
        heartbeatTimer = null;
        stopSafetyPolling();
        if (!active) return;
        startPolling();
        reconnectTimer = window.setTimeout(
          joinRealtime,
          Math.min(30000, 1000 * (2 ** reconnectAttempt++))
        );
      });
    };

    const onVisibility = () => {
      if (document.hidden) {
        stopPolling();
        stopSafetyPolling();
        if (socket) socket.close(1000, 'Page hidden');
      } else {
        update('visibility');
        if (useRealtime) joinRealtime(); else startPolling();
      }
    };
    document.addEventListener('visibilitychange', onVisibility);
    update('initial');
    if (useRealtime) joinRealtime(); else startPolling();

    return function unsubscribe() {
      if (!active) return;
      active = false;
      stopPolling();
      stopSafetyPolling();
      window.clearInterval(heartbeatTimer);
      window.clearTimeout(reconnectTimer);
      window.clearTimeout(refreshTimer);
      if (socket) socket.close(1000, 'Unsubscribed');
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }

  data.realtime = Object.freeze({ subscribeToQueue, tables: TABLES });
  data.subscribeToQueue = subscribeToQueue;
})();
