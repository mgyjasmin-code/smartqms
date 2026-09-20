const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..', '..');

function classList(initial = []) {
  const values = new Set(initial);
  return {
    add: (...names) => names.forEach(name => values.add(name)),
    remove: (...names) => names.forEach(name => values.delete(name)),
    toggle: (name, force) => {
      if (force === undefined) {
        if (values.has(name)) values.delete(name); else values.add(name);
      } else if (force) {
        values.add(name);
      } else {
        values.delete(name);
      }
    },
    contains: name => values.has(name),
  };
}

function runtime(scriptName) {
  const documentListeners = {};
  const windowListeners = {};
  const documentElement = { dataset: {} };
  const state = {
    forms: [],
    validatedForms: [],
    passwordToggles: [],
    elementsById: {},
    adminRoot: null,
    adminModal: null,
    adminManagementModals: [],
    adminToasts: [],
    timers: 0,
    fetches: 0,
    assignments: 0,
    assignedUrl: '',
  };
  const document = {
    documentElement,
    hidden: false,
    readyState: 'loading',
    addEventListener(type, listener) {
      (documentListeners[type] ||= []).push(listener);
    },
    querySelector(selector) {
      if (selector === '[data-admin-root]') return state.adminRoot;
      if (selector === '[data-admin-management-confirm-modal]') return state.adminModal;
      if (selector === '[data-client-root]' || selector === '[data-staff-shell]'
          || selector === '[data-display-root]'
          || selector === '[data-logout-modal]') return null;
      return null;
    },
    querySelectorAll(selector) {
      if (selector === 'form[data-submitting="true"]') return state.forms;
      if (selector === '.js-auth-form, .js-validated-form') return state.validatedForms;
      if (selector === '[data-password-toggle]') return state.passwordToggles;
      if (selector === '[data-admin-management-modal]') return state.adminManagementModals;
      if (selector === '[data-admin-action-toast]') return state.adminToasts;
      return [];
    },
    getElementById(id) {
      return state.elementsById[id] || null;
    },
    createElement(tagName) {
      return {
        tagName: tagName.toUpperCase(),
        textContent: '',
      };
    },
    activeElement: null,
  };
  const window = {
    SmartQms: undefined,
    addEventListener(type, listener) {
      (windowListeners[type] ||= []).push(listener);
    },
    setInterval() {
      state.timers += 1;
      return state.timers;
    },
    clearInterval() {},
    matchMedia: () => ({ matches: false, addEventListener() {} }),
    localStorage: { getItem: () => null, setItem() {} },
    sessionStorage: { getItem: () => null, setItem() {}, removeItem() {} },
    location: {
      pathname: '/smartqms/views/admin/dashboard.php',
      reload() {},
      assign(url) {
        state.assignments += 1;
        state.assignedUrl = url;
      },
    },
    requestAnimationFrame(callback) { callback(); },
  };
  window.window = window;
  const context = vm.createContext({
    window,
    document,
    console,
    fetch: async () => {
      state.fetches += 1;
      return { ok: true, json: async () => ({ success: true, data: {} }) };
    },
    FormData: class {},
    URLSearchParams,
    AbortController,
    CustomEvent: class {
      constructor(type, options = {}) {
        this.type = type;
        this.bubbles = Boolean(options.bubbles);
        this.cancelable = Boolean(options.cancelable);
        this.detail = options.detail || {};
      }
    },
    Notification: undefined,
    getComputedStyle: () => ({ getPropertyValue: () => '' }),
    setTimeout,
    clearTimeout,
  });
  vm.runInContext(fs.readFileSync(path.join(root, 'assets', 'js', `${scriptName}.js`), 'utf8'), context);
  return { context, documentListeners, windowListeners, documentElement, state };
}

for (const script of ['main', 'shell', 'client', 'staff', 'admin', 'display']) {
  const loaded = runtime(script);
  assert.equal(loaded.documentListeners.DOMContentLoaded?.length, 1, `${script}: one DOM-ready listener`);
  loaded.documentListeners.DOMContentLoaded[0]();
  loaded.documentListeners.DOMContentLoaded[0]();
  if (script !== 'main') {
    assert.equal(loaded.state.timers, 0, `${script}: absent feature root creates no timer`);
    assert.equal(loaded.state.fetches, 0, `${script}: absent feature root creates no request`);
  }
}

const main = runtime('main');
main.documentListeners.DOMContentLoaded[0]();
assert.equal(main.context.window.SmartQms.escapeHtml('<script>"&\''), '&lt;script&gt;&quot;&amp;&#039;');
assert.equal(main.documentElement.dataset.smartqmsSharedInitialized, 'true');

const submit = {
  innerHTML: 'Please wait...',
  disabled: true,
  dataset: { originalText: 'Submit' },
  classList: classList(['is-loading']),
  removeAttribute() {},
};
const form = {
  dataset: { submitting: 'true' },
  querySelector: selector => selector === '[type="submit"]' ? submit : null,
};
main.state.forms = [form];
for (const listener of main.windowListeners.pageshow || []) listener({ persisted: false });
assert.equal(form.dataset.submitting, 'false');
assert.equal(submit.innerHTML, 'Submit');
assert.equal(submit.disabled, false);
assert.equal(submit.classList.contains('is-loading'), false);

const confirmationRuntime = runtime('main');
let confirmationSubmitHandler = null;
let confirmationEventCount = 0;
const confirmationSubmit = {
  innerHTML: '<span>Review</span>',
  disabled: false,
  dataset: { loadingText: 'Saving...' },
  classList: classList(),
  setAttribute() {},
  removeAttribute() {},
};
const confirmationForm = {
  dataset: {},
  classList: classList(['js-validated-form']),
  hasAttribute: name => name === 'data-admin-confirm-form',
  querySelectorAll: () => [],
  querySelector: selector => selector === '[type="submit"]' ? confirmationSubmit : null,
  addEventListener(type, listener) {
    if (type === 'submit') confirmationSubmitHandler = listener;
  },
  dispatchEvent(event) {
    if (event.type === 'smartqms:request-confirmation') {
      confirmationEventCount += 1;
      event.detail.handled = true;
    }
    return true;
  },
};
confirmationRuntime.state.validatedForms = [confirmationForm];
confirmationRuntime.documentListeners.DOMContentLoaded[0]();
let prevented = false;
confirmationSubmitHandler({
  preventDefault() { prevented = true; },
  submitter: confirmationSubmit,
});
assert.equal(confirmationEventCount, 1, 'validated admin form requests one confirmation');
assert.equal(prevented, true, 'handled confirmation pauses the first submit');
assert.equal(confirmationForm.dataset.submitting, undefined, 'first submit is not marked busy before confirmation');

confirmationForm.dataset.adminConfirmed = 'true';
prevented = false;
confirmationSubmitHandler({
  preventDefault() { prevented = true; },
  submitter: confirmationSubmit,
});
assert.equal(prevented, false, 'confirmed form continues to native submission');
assert.equal(confirmationForm.dataset.submitting, 'true', 'confirmed form receives the shared busy state');
assert.equal(confirmationSubmit.disabled, true, 'confirmed form prevents duplicate submission');

const directRuntime = runtime('main');
let directSubmitHandler = null;
const directSubmit = {
  innerHTML: '<span>Create</span>',
  disabled: false,
  dataset: { loadingText: 'Creating...' },
  classList: classList(),
  setAttribute() {},
  removeAttribute() {},
};
const directForm = {
  dataset: {},
  classList: classList(['js-validated-form']),
  hasAttribute: () => false,
  querySelectorAll: () => [],
  querySelector: selector => selector === '[type="submit"]' ? directSubmit : null,
  addEventListener(type, listener) {
    if (type === 'submit') directSubmitHandler = listener;
  },
};
directRuntime.state.validatedForms = [directForm];
directRuntime.documentListeners.DOMContentLoaded[0]();
let directPrevented = false;
directSubmitHandler({
  preventDefault() { directPrevented = true; },
  submitter: directSubmit,
});
assert.equal(directPrevented, false, 'direct management form continues to native submission');
assert.equal(directForm.dataset.submitting, 'true', 'direct management form receives the shared busy state');
assert.equal(directSubmit.disabled, true, 'direct management form prevents duplicate submission');

const nativeValidationRuntime = runtime('main');
const nativeValidationFormListeners = {};
const nativeFieldListeners = {};
const nativeFeedback = {
  textContent: 'This email is already registered.',
  classList: classList(['field-error']),
};
const nativeFieldAttributes = { 'aria-invalid': 'true' };
let nativeValidationForm = null;
const nativeField = {
  id: 'email',
  name: 'email',
  value: 'existing@example.test',
  dataset: {},
  classList: classList(['is-invalid']),
  nextElementSibling: null,
  parentElement: null,
  addEventListener(eventName, listener) { nativeFieldListeners[eventName] = listener; },
  closest(selector) { return selector === 'form' ? nativeValidationForm : null; },
  getAttribute(name) { return nativeFieldAttributes[name] || null; },
  removeAttribute(name) { delete nativeFieldAttributes[name]; },
};
nativeValidationForm = {
  dataset: {},
  classList: classList(['js-validated-form']),
  hasAttribute: () => false,
  checkValidity: () => true,
  querySelectorAll(selector) {
    if (selector === 'input:not([type="hidden"]), select, textarea') return [nativeField];
    if (selector === '[data-confirm-password-for]') return [];
    return [];
  },
  querySelector(selector) {
    if (selector === '[data-field-error-for="email"]') return nativeFeedback;
    if (selector === '[type="submit"]') return null;
    return null;
  },
  addEventListener(type, listener) { nativeValidationFormListeners[type] = listener; },
};
nativeValidationRuntime.state.validatedForms = [nativeValidationForm];
nativeValidationRuntime.documentListeners.DOMContentLoaded[0]();
assert.equal(nativeField.classList.contains('is-invalid'), true, 'server error remains until the field is edited');
nativeFieldListeners.input();
assert.equal(nativeField.classList.contains('is-invalid'), false, 'editing clears the server invalid state');
assert.equal(nativeField.classList.contains('is-valid'), false, 'editing never adds an application valid state');
assert.equal(nativeFieldAttributes['aria-invalid'], undefined, 'editing clears server aria-invalid metadata');
assert.equal(nativeFeedback.textContent, '', 'editing clears the matching server error message');

const passwordConfirmationRuntime = runtime('main');
const confirmationListeners = {};
const passwordListeners = {};
const passwordSource = {
  value: 'password-one',
  addEventListener(type, listener) { passwordListeners[type] = listener; },
};
const passwordConfirmation = {
  name: 'confirm_password',
  value: 'password-two',
  dataset: { confirmPasswordFor: 'password' },
  classList: classList(),
  nextElementSibling: null,
  parentElement: null,
  validityMessage: '',
  setCustomValidity(message) { this.validityMessage = message; },
  addEventListener(type, listener) { confirmationListeners[type] = listener; },
  closest(selector) { return selector === 'form' ? passwordConfirmationForm : null; },
  getAttribute() { return null; },
  removeAttribute() {},
};
const passwordConfirmationForm = {
  dataset: {},
  classList: classList(['js-auth-form']),
  elements: { namedItem: name => name === 'password' ? passwordSource : null },
  hasAttribute: () => false,
  checkValidity: () => passwordConfirmation.validityMessage === '',
  querySelectorAll(selector) {
    if (selector === '[data-confirm-password-for]') return [passwordConfirmation];
    if (selector === 'input:not([type="hidden"]), select, textarea') return [passwordConfirmation];
    return [];
  },
  querySelector: () => null,
  addEventListener() {},
};
passwordConfirmationRuntime.state.validatedForms = [passwordConfirmationForm];
passwordConfirmationRuntime.documentListeners.DOMContentLoaded[0]();
assert.equal(passwordConfirmation.validityMessage, 'Password entries do not match.', 'mismatched passwords use native custom validity');
passwordConfirmation.value = 'password-one';
passwordListeners.input();
assert.equal(passwordConfirmation.validityMessage, '', 'matching passwords clear native custom validity immediately');
passwordSource.value = 'changed-password';
passwordListeners.input();
assert.equal(passwordConfirmation.validityMessage, 'Password entries do not match.', 'editing the source password resynchronizes confirmation validity');

const invalidNativeRuntime = runtime('main');
let invalidSubmitHandler = null;
let invalidReportCount = 0;
let invalidBusyCount = 0;
let invalidConfirmationCount = 0;
const invalidNativeForm = {
  dataset: {},
  classList: classList(['js-validated-form']),
  hasAttribute: name => name === 'data-admin-confirm-form',
  checkValidity: () => false,
  reportValidity() { invalidReportCount += 1; },
  querySelectorAll: () => [],
  querySelector(selector) {
    if (selector === '[type="submit"]') {
      invalidBusyCount += 1;
      return null;
    }
    return null;
  },
  addEventListener(type, listener) {
    if (type === 'submit') invalidSubmitHandler = listener;
  },
  dispatchEvent() { invalidConfirmationCount += 1; },
};
invalidNativeRuntime.state.validatedForms = [invalidNativeForm];
invalidNativeRuntime.documentListeners.DOMContentLoaded[0]();
let invalidSubmitPrevented = false;
invalidSubmitHandler({ preventDefault() { invalidSubmitPrevented = true; } });
assert.equal(invalidSubmitPrevented, true, 'invalid native form is prevented');
assert.equal(invalidReportCount, 1, 'invalid native form reports the first invalid control');
assert.equal(invalidConfirmationCount, 0, 'invalid native form emits no confirmation event');
assert.equal(invalidNativeForm.dataset.submitting, undefined, 'invalid native form never enters the busy state');
assert.equal(invalidBusyCount, 0, 'invalid native form never resolves or mutates its submit button');

const passwordToggleRuntime = runtime('main');
const passwordToggleListeners = {};
const passwordToggleAttributes = {
  'aria-controls': 'staff-password',
  'aria-label': 'Show temporary password',
  'aria-pressed': 'false',
};
const passwordToggleInput = { type: 'password' };
const passwordShowIcon = { hidden: false };
const passwordHideIcon = { hidden: true };
const passwordToggleButton = {
  dataset: { passwordToggleLabel: 'temporary password' },
  title: 'Show temporary password',
  getAttribute(name) { return passwordToggleAttributes[name] || null; },
  setAttribute(name, value) { passwordToggleAttributes[name] = value; },
  querySelector(selector) {
    if (selector === '[data-password-show-icon]') return passwordShowIcon;
    if (selector === '[data-password-hide-icon]') return passwordHideIcon;
    return null;
  },
  addEventListener(type, listener) { passwordToggleListeners[type] = listener; },
};
passwordToggleRuntime.state.passwordToggles = [passwordToggleButton];
passwordToggleRuntime.state.elementsById['staff-password'] = passwordToggleInput;
passwordToggleRuntime.documentListeners.DOMContentLoaded[0]();
passwordToggleListeners.click();
assert.equal(passwordToggleInput.type, 'text', 'staff password toggle reveals the password');
assert.equal(passwordShowIcon.hidden, true);
assert.equal(passwordHideIcon.hidden, false);
assert.equal(passwordToggleAttributes['aria-label'], 'Hide temporary password');
assert.equal(passwordToggleAttributes['aria-pressed'], 'true');
passwordToggleListeners.click();
assert.equal(passwordToggleInput.type, 'password', 'staff password toggle hides the password again');
assert.equal(passwordShowIcon.hidden, false);
assert.equal(passwordHideIcon.hidden, true);
assert.equal(passwordToggleAttributes['aria-label'], 'Show temporary password');
assert.equal(passwordToggleAttributes['aria-pressed'], 'false');

const managementRuntime = runtime('admin');
const managementModalListeners = {};
const managementAutoModalListeners = {};
let managementResetCount = 0;
let managementFocusCount = 0;
let managementAutoShowCount = 0;
const managementInvalidField = {
  dataset: {},
  classList: classList(['is-invalid']),
  removeAttribute() {},
  focus() { managementFocusCount += 1; },
};
let managementCustomValidity = 'Password entries do not match.';
const managementConfirmationField = {
  setCustomValidity(message) { managementCustomValidity = message; },
};
const managementFeedback = { textContent: 'Required.' };
const managementForm = {
  dataset: { adminConfirmed: 'true' },
  classList: classList(['was-validated']),
  reset() { managementResetCount += 1; },
  querySelector(selector) {
    if (selector === '.is-invalid, [aria-invalid="true"]') return managementInvalidField;
    return null;
  },
  querySelectorAll(selector) {
    if (selector === '.is-invalid, .is-valid, [aria-invalid="true"]') return [managementInvalidField];
    if (selector === '[data-confirm-password-for]') return [managementConfirmationField];
    if (selector === '.field-error') return [managementFeedback];
    return [];
  },
};
const managementModal = {
  dataset: { adminCleanUrl: 'add_staff.php' },
  hasAttribute: () => false,
  querySelector: selector => selector === 'form' ? managementForm : null,
  addEventListener(type, listener) { managementModalListeners[type] = listener; },
};
const managementAutoForm = {
  dataset: {},
  classList: classList(),
  reset() {},
  querySelector: () => null,
  querySelectorAll: () => [],
};
const managementAutoModal = {
  dataset: { adminCleanUrl: 'services.php' },
  hasAttribute: name => name === 'data-admin-auto-open',
  querySelector: selector => selector === 'form' ? managementAutoForm : null,
  addEventListener(type, listener) { managementAutoModalListeners[type] = listener; },
};
managementRuntime.context.window.bootstrap = {
  Modal: {
    getOrCreateInstance(element) {
      return {
        show() {
          if (element === managementAutoModal) managementAutoShowCount += 1;
        },
      };
    },
  },
};
managementRuntime.state.adminRoot = { dataset: {} };
managementRuntime.state.adminManagementModals = [managementModal, managementAutoModal];
managementRuntime.documentListeners.DOMContentLoaded[0]();
managementRuntime.documentListeners.DOMContentLoaded[0]();
assert.equal(managementModal.dataset.adminModalInitialized, 'true', 'management modal initializes once');
assert.equal(managementAutoShowCount, 1, 'server edit/error state auto-opens once');
managementModalListeners['shown.bs.modal']();
assert.equal(managementFocusCount, 1, 'management modal focuses the first invalid field');
managementModalListeners['hidden.bs.modal']();
assert.equal(managementResetCount, 1, 'dismissed add modal resets once');
assert.equal(managementForm.classList.contains('was-validated'), false);
assert.equal(managementInvalidField.classList.contains('is-invalid'), false);
assert.equal(managementCustomValidity, '', 'dismissed add modal clears custom password validity');
assert.equal(managementFeedback.textContent, '');
managementAutoModalListeners['hidden.bs.modal']();
assert.equal(managementRuntime.state.assignments, 1, 'dismissed edit/error modal returns to one clean URL');
assert.equal(managementRuntime.state.assignedUrl, 'services.php');

const adminToastRuntime = runtime('admin');
let toastShowCount = 0;
const actionToast = { dataset: {} };
adminToastRuntime.context.window.bootstrap = {
  Toast: {
    getOrCreateInstance(element) {
      assert.equal(element, actionToast);
      return {
        show() { toastShowCount += 1; },
      };
    },
  },
};
adminToastRuntime.state.adminRoot = { dataset: {} };
adminToastRuntime.state.adminToasts = [actionToast];
adminToastRuntime.documentListeners.DOMContentLoaded[0]();
adminToastRuntime.documentListeners.DOMContentLoaded[0]();
assert.equal(actionToast.dataset.adminToastInitialized, 'true', 'admin action toast initializes once');
assert.equal(toastShowCount, 1, 'admin action toast is shown once');

const adminConfirmationRuntime = runtime('admin');
const adminModalListeners = {};
const adminConfirmButtonListeners = {};
const adminSummary = {
  children: [],
  hidden: false,
  get firstChild() { return this.children[0] || null; },
  appendChild(child) { this.children.push(child); },
  removeChild(child) { this.children.splice(this.children.indexOf(child), 1); },
};
const adminTitle = { textContent: '' };
const adminMessage = { textContent: '' };
const adminEyebrow = { textContent: '' };
const adminConfirmLabel = { textContent: '' };
const adminConfirmButton = {
  disabled: false,
  classList: classList(['is-primary']),
  addEventListener(type, listener) { adminConfirmButtonListeners[type] = listener; },
};
const adminModalElement = {
  querySelector(selector) {
    return {
      '[data-admin-confirm-title]': adminTitle,
      '[data-admin-confirm-message]': adminMessage,
      '[data-admin-confirm-eyebrow]': adminEyebrow,
      '[data-admin-confirm-summary]': adminSummary,
      '[data-admin-confirm-submit]': adminConfirmButton,
      '[data-admin-confirm-submit-label]': adminConfirmLabel,
    }[selector] || null;
  },
  addEventListener(type, listener) { adminModalListeners[type] = listener; },
};
let modalShowCount = 0;
let modalHideCount = 0;
adminConfirmationRuntime.context.window.bootstrap = {
  Modal: {
    getOrCreateInstance() {
      return {
        show() { modalShowCount += 1; },
        hide() { modalHideCount += 1; },
      };
    },
  },
};
adminConfirmationRuntime.state.adminRoot = { dataset: {} };
adminConfirmationRuntime.state.adminModal = adminModalElement;
adminConfirmationRuntime.documentListeners.DOMContentLoaded[0]();

let reviewedSubmitCount = 0;
const reviewSubmitter = { focus() {} };
const reviewFields = [
  {
    dataset: { adminConfirmLabel: 'Email' },
    name: 'email',
    type: 'email',
    tagName: 'INPUT',
    value: 'operator@example.test',
  },
  {
    dataset: { adminConfirmLabel: 'Temporary password', adminConfirmSensitive: 'true' },
    name: 'password',
    type: 'password',
    tagName: 'INPUT',
    value: 'secret-value',
  },
];
const reviewedForm = {
  dataset: {
    adminConfirmTitle: 'Create staff account?',
    adminConfirmMessage: 'Review staff.',
    adminConfirmSubmitLabel: 'Create staff',
  },
  matches: selector => selector === '[data-admin-confirm-form]',
  querySelectorAll: selector => selector === '[data-admin-confirm-field]' ? reviewFields : [],
  requestSubmit(submitter) {
    assert.equal(submitter, reviewSubmitter);
    reviewedSubmitCount += 1;
  },
};
const confirmationListener = adminConfirmationRuntime.documentListeners['smartqms:request-confirmation'][0];
const adminConfirmationEvent = {
  target: reviewedForm,
  detail: { handled: false, submitter: reviewSubmitter },
};
confirmationListener(adminConfirmationEvent);
assert.equal(adminConfirmationEvent.detail.handled, true, 'admin confirmation owns the validated form event');
assert.equal(modalShowCount, 1, 'admin confirmation opens one Bootstrap modal');
assert.equal(adminTitle.textContent, 'Create staff account?');
assert.equal(adminSummary.children[1].textContent, 'operator@example.test');
assert.equal(adminSummary.children[3].textContent, 'Provided (hidden)', 'password is masked in review output');

adminConfirmButtonListeners.click();
assert.equal(modalHideCount, 1, 'confirm closes the Bootstrap modal');
assert.equal(reviewedSubmitCount, 1, 'confirm requests exactly one real form submission');
assert.equal(reviewedForm.dataset.adminConfirmed, 'true');

const publicBookingSource = fs.readFileSync(path.join(root, 'assets', 'js', 'public_booking.js'), 'utf8');
for (const contract of [
  '[data-booking-service-input]',
  'data-booking-review',
  'smartqms:languagechange',
  "const stepNames = ['Choose Service', 'Appointment Details', 'Review']",
  'currentStep / steps.length',
  "form.dataset.submitting === 'true'",
  "form.setAttribute('aria-busy', 'true')",
  "submitButton.setAttribute('aria-busy', 'true')",
  'processingStatus.hidden = false',
  'errorSummary.focus()',
  "errorSummary?.addEventListener('click'",
  "target.querySelector('input:checked, input, select, textarea, button')",
  'invalid.reportValidity()',
]) {
  assert.equal(publicBookingSource.includes(contract), true, `public booking keeps ${contract}`);
}

const publicDatepickerSource = fs.readFileSync(path.join(root, 'assets', 'js', 'public_datepicker.js'), 'utf8');
for (const contract of [
  '[data-public-datepicker]',
  "format: 'yyyy-mm-dd'",
  'minDate: minimumDate',
  'maxDate: maximumDate',
  'window.Datepicker.locales.fil',
  "input.type = originalType",
  'smartqms:languagechange',
]) {
  assert.equal(publicDatepickerSource.includes(contract), true, `public datepicker keeps ${contract}`);
}

const staffActionSource = fs.readFileSync(path.join(root, 'assets', 'js', 'staff.js'), 'utf8');
for (const contract of [
  "activeButton.setAttribute('aria-busy', 'true')",
  "activeButton.classList.add('is-loading')",
  "activeButton.classList.remove('is-loading')",
]) {
  assert.equal(staffActionSource.includes(contract), true, `staff actions keep ${contract}`);
}
assert.equal(
  staffActionSource.includes('activeButton.textContent = activeButton.dataset.loadingText'),
  false,
  'staff action loading state must not replace visible button content',
);

const adminSource = fs.readFileSync(path.join(root, 'assets', 'js', 'admin.js'), 'utf8');
for (const contract of [
  'const semanticSeriesStyles = {',
  "predicted: { color: primaryColor, dash: [], pointStyle: 'circle' }",
  "actual: { color: amber, dash: [8, 5], pointStyle: 'triangle' }",
  'dataset.style_key',
  'function openAdminPrintDialog(button)',
  'function scheduleAdminPrintRestore(delay = 1200)',
  "window.matchMedia?.('print').addEventListener?.('change'",
  "window.addEventListener('focus', () => scheduleAdminPrintRestore(250))",
  "button.setAttribute('aria-busy', 'true')",
  "button.removeAttribute('aria-busy')",
]) {
  assert.equal(adminSource.includes(contract), true, `admin reports keep ${contract}`);
}

const publicDisplaySource = fs.readFileSync(path.join(root, 'assets', 'js', 'public_display.js'), 'utf8');
for (const contract of [
  'const visibleCapacity =',
  "servingRows.slice(1)",
  "waitingRows.slice(0, capacity)",
  "'public-display-next-label', 'Next'",
  'ResizeObserver',
  '+${hiddenCount} more waiting',
  "timeZone: 'Asia/Manila'",
  "setConnectionState('offline')",
  'window.setInterval(poll, 3000)',
]) {
  assert.equal(publicDisplaySource.includes(contract), true, `public display keeps ${contract}`);
}
for (const removed of [
  'AudioContext',
  'SpeechSynthesisUtterance',
  'speechSynthesis',
  'localStorage',
  'renderRecent',
  'recentRows',
  'waitingPageIndex',
  'payload.recent',
]) {
  assert.equal(publicDisplaySource.includes(removed), false, `public display removes ${removed}`);
}

async function runArrivalScannerContracts() {
  const source = fs.readFileSync(path.join(root, 'assets', 'js', 'staff_arrival_scanner.js'), 'utf8');
  let scanListener = null;
  let startedCamera = '';
  let instascanStops = 0;
  const scannerWindow = {
    location: { origin: 'https://qms.test' },
    document: { hidden: false },
    setInterval: () => 17,
    clearInterval: () => {},
    navigator: { mediaDevices: {} },
    Instascan: {
      Scanner: class {
        addListener(event, listener) { if (event === 'scan') scanListener = listener; }
        async start(camera) { startedCamera = String(camera.id); }
        async stop() { instascanStops += 1; }
      },
      Camera: {
        getCameras: async () => [
          { id: 'front', name: 'Front Camera' },
          { id: 'rear', name: 'Rear Camera' },
        ],
      },
    },
  };
  vm.runInNewContext(source, { window: scannerWindow, URL });
  const adapter = scannerWindow.SmartQmsArrivalScanner;
  assert.equal(typeof adapter.create, 'function', 'arrival scanner exposes its adapter');
  assert.equal(adapter.parseValue('A'.repeat(64)).lookup_type, 'token');
  assert.equal(adapter.parseValue('BHC-2026-0042').lookup_value, 'BHC-2026-0042');
  assert.equal(adapter.parseValue('2026092100000045').lookup_value, '2026092100000045');
  assert.equal(
    adapter.parseValue(`https://qms.test/smartqms/track/?token=${'b'.repeat(64)}`).lookup_value,
    'b'.repeat(64),
    'same-origin private tracker QR is accepted',
  );
  assert.equal(adapter.parseValue(`https://evil.test/track/?token=${'b'.repeat(64)}`), null, 'foreign QR URLs are rejected');
  assert.equal(adapter.parseValue(`https://qms.test/not-a-tracker/?token=${'b'.repeat(64)}`), null, 'unapproved local paths are rejected');

  const video = { srcObject: null, async play() {} };
  let validScans = 0;
  const controller = adapter.create({
    video,
    expectedOrigin: scannerWindow.location.origin,
    async onScan() { validScans += 1; },
  });
  const primary = await controller.start();
  assert.equal(primary.engine, 'instascan', 'Instascan is the primary scanner');
  assert.equal(primary.selectedId, 'rear', 'rear camera is preferred');
  assert.equal(startedCamera, 'rear');
  await Promise.all([
    scanListener(`https://qms.test/smartqms/track/?token=${'c'.repeat(64)}`),
    scanListener(`https://qms.test/smartqms/track/?token=${'c'.repeat(64)}`),
  ]);
  assert.equal(validScans, 1, 'one valid QR triggers one lookup');
  assert.equal(instascanStops > 0, true, 'Instascan stops after a valid scan');

  const switchController = adapter.create({ video, expectedOrigin: scannerWindow.location.origin });
  await switchController.start();
  await switchController.switchCamera('front');
  assert.equal(startedCamera, 'front', 'staff can switch to another enumerated camera');
  await switchController.stop();

  let nativeTrackStops = 0;
  let nativeConstraints = null;
  scannerWindow.Instascan.Camera.getCameras = async () => { throw new Error('Instascan unavailable'); };
  scannerWindow.BarcodeDetector = class { async detect() { return []; } };
  scannerWindow.navigator.mediaDevices = {
    async getUserMedia(constraints) {
      nativeConstraints = constraints;
      return { getTracks: () => [{ stop() { nativeTrackStops += 1; } }] };
    },
    async enumerateDevices() {
      return [{ kind: 'videoinput', deviceId: 'native-rear', label: 'Back Camera' }];
    },
  };
  const fallbackController = adapter.create({ video, expectedOrigin: scannerWindow.location.origin });
  const fallback = await fallbackController.start();
  assert.equal(fallback.engine, 'native', 'BarcodeDetector is used when Instascan fails');
  assert.equal(nativeConstraints.video.facingMode.ideal, 'environment');
  await fallbackController.stop();
  assert.equal(nativeTrackStops > 0, true, 'native fallback releases its media track');

  scannerWindow.Instascan = undefined;
  scannerWindow.BarcodeDetector = undefined;
  scannerWindow.navigator.mediaDevices = {};
  const manualController = adapter.create({ video, expectedOrigin: scannerWindow.location.origin });
  await assert.rejects(() => manualController.start(), /unavailable/i, 'unsupported browsers fall back to manual input');
}

runArrivalScannerContracts()
  .then(() => process.stdout.write('JavaScript runtime contracts: all passed\n'))
  .catch(error => {
    console.error(error);
    process.exitCode = 1;
  });
