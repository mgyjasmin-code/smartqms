// Behavioral regressions for private tracking and non-disruptive staff refreshes.
// No browser, network, credentials, or database is used by this test harness.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const rootPath = path.resolve(__dirname, '../..');
const source = name => fs.readFileSync(path.join(rootPath, 'assets/js', name + '.js'), 'utf8');
const settle = () => new Promise(resolve => setImmediate(resolve));
function deferred() { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no; }); return { promise, resolve, reject }; }
class Element {
  constructor(text = '') { this.textContent = text; this.dataset = {}; this.hidden = false; this.disabled = false; this.listeners = {}; this.children = []; this.attributes = {}; this.classes = new Set(); this.classList = { add: (...v) => v.forEach(x => this.classes.add(x)), remove: (...v) => v.forEach(x => this.classes.delete(x)), contains: x => this.classes.has(x), toggle: (x, on) => on ? this.classes.add(x) : this.classes.delete(x) }; }
  addEventListener(name, callback) { (this.listeners[name] ||= new Set()).add(callback); }
  removeEventListener(name, callback) { this.listeners[name]?.delete(callback); }
  emit(name, event = {}) { this.listeners[name]?.forEach(callback => callback(event)); }
  querySelector() { return null; }
  querySelectorAll() { return []; }
  contains(el) { return this.children.includes(el); }
  hasAttribute(name) { return name in this.attributes; }
  setAttribute(name, value) { this.attributes[name] = value; }
  getAttribute(name) { return this.attributes[name]; }
  removeAttribute(name) { delete this.attributes[name]; }
  focus() {}
}
async function testTracker() {
  const nodes = {};
  for (const name of ['status-panel', 'ticket-metadata', 'feedback-gate', 'feedback-complete', 'update-error', 'status-label', 'status-message', 'ticket-number', 'queue-number', 'status-detail', 'service-name', 'people-ahead', 'wait-display', 'wait-minutes', 'counter-label', 'last-updated']) nodes[name] = new Element();
  nodes['status-panel'].dataset.status = 'waiting';
  nodes['last-updated'].textContent = 'Initial server timestamp';
  nodes['wait-minutes'].textContent = '0';
  nodes['feedback-complete'].hidden = true;
  const tracker = new Element(); tracker.dataset = { token: 'review-token', statusUrl: '/status' };
  tracker.querySelector = selector => nodes[selector.slice(6, -1)] || null;
  const pending = [];
  let scheduled;
  const document = new Element(); document.body = new Element(); document.hidden = false;
  document.querySelector = () => tracker;
  const window = { clearTimeout() {}, setTimeout(callback) { scheduled = callback; return 1; } };
  vm.runInNewContext(source('public_queue'), { document, window, fetch: () => { const request = deferred(); pending.push(request); return request.promise; }, Intl, Date, FormData: class {} });
  assert.equal(nodes['wait-display'].textContent, '0.0 min', 'zero-minute initial estimate is valid');
  assert.equal(nodes['last-updated'].textContent, 'Initial server timestamp', 'initial render must retain server timestamp');
  async function respond(value, status = 'waiting', options = {}) {
    if (!pending.length) scheduled();
    pending.shift().resolve({ ok: options.ok !== false, json: async () => {
      if (options.malformed) throw new Error('bad JSON');
      return { success: options.success !== false, data: { status, ticket_number: 'A-024', predicted_wait_minutes: value, people_ahead: 3, service_name: 'Consultation', has_feedback: false } };
    } });
    await settle();
  }
  await respond(0);
  assert.equal(nodes['wait-display'].textContent, '0.0 min');
  const lastSuccess = nodes['last-updated'].dateTime;
  for (const value of [null, undefined, '', 'NaN', -1, Infinity, false, '12oops']) {
    await respond(value); assert.equal(nodes['wait-display'].textContent, 'Temporarily unavailable', `${String(value)} must not become zero`);
  }
  await respond('12.5'); assert.equal(nodes['wait-display'].textContent, '12.5 min');
  for (const failure of [{ok: false}, {success: false}, {malformed: true}]) {
    const time = nodes['last-updated'].dateTime;
    await respond(999, 'calling', failure);
    assert.equal(nodes['wait-display'].textContent, '12.5 min', 'failed update retains last valid estimate');
    assert.equal(nodes['last-updated'].dateTime, time, 'failed update retains successful timestamp');
    assert.equal(nodes['update-error'].hidden, false);
    assert.equal(nodes['status-panel'].dataset.status, 'waiting');
  }
  assert.ok(lastSuccess);
  await respond(0, 'scheduled'); assert.equal(nodes['wait-display'].textContent, 'After check-in');
  assert.equal(nodes['update-error'].hidden, true, 'recovery clears the error');
  await respond(0, 'completed'); assert.equal(nodes['feedback-gate'].hidden, false);
  assert.equal(nodes['ticket-metadata'].hidden, true);
  console.log('PASS private tracker: zero/invalid estimates, failure retention, recovery, and feedback state');
}
async function testStaff() {
  const current = new Element(); let replacements = 0; let content = 'original';
  Object.defineProperty(current, 'innerHTML', { get: () => content, set: value => { replacements++; content = value; } });
  const status = new Element('Last updated 9:00 AM.');
  const shell = new Element(); const button = new Element('Start');
  button.dataset = { actionUrl: '/start', ticketId: '24', providerAction: 'startTest' };
  current.children = [button];
  let editing = false, modal = null;
  current.querySelector = () => editing ? new Element() : null;
  shell.querySelectorAll = () => [button];
  const document = new Element(); document.hidden = false; document.activeElement = null;
  document.querySelector = selector => ({ '#staff-main': current, '[data-staff-refresh-status]': status, '.modal.show, .offcanvas.show': modal }[selector] || null);
  const requests = [], action = deferred(); let actions = 0;
  const window = { location: {href: '/staff'}, sessionStorage: { getItem() {}, removeItem() {} }, setTimeout() { return 1; }, clearTimeout() {}, clearInterval() {}, addEventListener() {}, SmartQmsData: { startTest: () => { actions++; return action.promise; } } };
  class DOMParser { parseFromString(html) { return { querySelector: () => ({innerHTML: html}) }; } }
  const context = {window, document, DOMParser, HTMLElement: Element, Intl, Date, FormData: class {}, fetch: () => { const request = deferred(); requests.push(request); return request.promise; }};
  vm.runInNewContext(source('staff').replace(/\}\)\(\);\s*$/, 'window.testHooks = {refreshStaffContent, initStaffActions};})();'), context);
  const { refreshStaffContent, initStaffActions } = window.testHooks;
  async function reply(html = 'fresh', ok = true) { requests.shift().resolve({ok, text: async () => html}); await settle(); }
  for (const mode of ['focus', 'form', 'modal']) {
    document.activeElement = mode === 'focus' ? button : null; editing = mode === 'form'; modal = mode === 'modal' ? new Element() : null;
    await refreshStaffContent(shell); assert.equal(requests.length, 0, `${mode} blocks background replacement`);
  }
  document.activeElement = null; editing = false; modal = null;
  editing = true;
  await refreshStaffContent(shell, true);
  assert.equal(requests.length, 0, 'even a completed action cannot discard another edited form');
  editing = false;
  const lateEdit = refreshStaffContent(shell); editing = true; await reply('discard'); await lateEdit;
  assert.equal(replacements, 0, 'interaction starting during fetch preserves content'); editing = false;
  const failure = refreshStaffContent(shell); const rejected = assert.rejects(failure); await reply('', false); await rejected;
  assert.equal(content, 'original'); assert.match(status.textContent, /9:00 AM/); assert.ok(status.classList.contains('is-stale'));
  initStaffActions(shell);
  const background = refreshStaffContent(shell);
  button.emit('click'); button.emit('click');
  assert.equal(actions, 1, 'duplicate action submission is prevented');
  action.resolve({success: true}); await settle();
  await reply('stale before action'); await background;
  assert.equal(replacements, 0, 'pre-action response cannot overwrite the completed action');
  assert.equal(requests.length, 1, 'a completed action requests a fresh snapshot after an in-flight fetch');
  await reply('updated after action');
  assert.equal(content, 'updated after action'); assert.equal(replacements, 1);
  assert.equal(status.classList.contains('is-stale'), false);
  console.log('PASS staff: focus/forms/dialogs, mid-request edits, failure timestamp, duplicate submission, and action-refresh race');
}
async function testAdmin() {
  const document = new Element(); const current = new Element(); const status = new Element('Snapshot loaded 9:00 AM');
  let open = true, replacements = 0;
  current.querySelector = () => open ? new Element() : null;
  Object.defineProperty(current, 'innerHTML', { set() { replacements++; } });
  document.querySelector = selector => ({'#admin-main': current, '[data-admin-refresh-status]': status}[selector] || null);
  const requests = [];
  const window = {addEventListener() {}, location: {href:'/admin'}, matchMedia: () => ({addEventListener(){}})};
  class DOMParser { parseFromString() { return {querySelector: () => ({innerHTML:'fresh'})}; } }
  vm.runInNewContext(source('admin').replace(/\}\)\(\);\s*$/, 'window.testRefresh = refreshAdminDashboard;})();'), {document, window, DOMParser, fetch: () => {const request = deferred(); requests.push(request); return request.promise;}});
  await window.testRefresh(); assert.equal(requests.length, 0, 'expanded chart values are preserved');
  open = false;
  const failed = window.testRefresh(); requests.shift().resolve({ok:false}); await failed;
  assert.equal(replacements, 0); assert.match(status.textContent, /9:00 AM/); assert.ok(status.classList.contains('is-stale'));
  const interrupted = window.testRefresh(); open = true; requests.shift().resolve({ok:true,text:async()=>''}); await interrupted;
  assert.equal(replacements, 0, 'chart values opened during fetch remain open');
  open = false;
  const success = window.testRefresh(); requests.shift().resolve({ok:true,text:async()=>''}); await success;
  assert.equal(replacements, 1);
  console.log('PASS administrator: expanded values, mid-request interaction, failure timestamp, and recovery');
}
(async () => { await testTracker(); await testStaff(); await testAdmin(); })().catch(error => { console.error(error); process.exitCode = 1; });
