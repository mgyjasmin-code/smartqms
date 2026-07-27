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
  querySelectorAll: selector => selector === '[data-validate]' ? [] : [],
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
  querySelectorAll: selector => selector === '[data-validate]' ? [] : [],
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

const neutralValidationRuntime = runtime('main');
const neutralValidationFormListeners = {};
const neutralFeedback = {
  id: '',
  textContent: '',
  classList: classList(['field-error']),
};
const optionalPasswordFeedback = {
  id: '',
  textContent: '',
  classList: classList(['field-error']),
};
let neutralValidationForm = null;
function validationInput({ name, value, required, validate, type = 'text', feedback }) {
  const listeners = {};
  const attributes = {};
  return {
    id: name,
    name,
    value,
    required,
    type,
    dataset: { validate },
    classList: classList(),
    nextElementSibling: null,
    parentElement: null,
    addEventListener(eventName, listener) { listeners[eventName] = listener; },
    closest(selector) { return selector === 'form' ? neutralValidationForm : null; },
    getAttribute(nameToRead) { return attributes[nameToRead] || ''; },
    setAttribute(nameToSet, valueToSet) { attributes[nameToSet] = valueToSet; },
    removeAttribute(nameToRemove) { delete attributes[nameToRemove]; },
    listeners,
    feedback,
  };
}
const untouchedRequired = validationInput({
  name: 'first_name',
  value: 'John',
  required: true,
  validate: 'required',
  feedback: neutralFeedback,
});
const optionalPassword = validationInput({
  name: 'password',
  value: '',
  required: false,
  validate: 'password',
  type: 'password',
  feedback: optionalPasswordFeedback,
});
const neutralValidationFields = [untouchedRequired, optionalPassword];
neutralValidationForm = {
  dataset: {},
  classList: classList(['js-validated-form']),
  hasAttribute: name => name === 'data-validation-errors-only',
  querySelectorAll: selector => selector === '[data-validate]' ? neutralValidationFields : [],
  querySelector(selector) {
    const feedbackMatch = selector.match(/^\[data-field-error-for="(.+)"\]$/);
    if (feedbackMatch) {
      return neutralValidationFields.find(field => field.name === feedbackMatch[1])?.feedback || null;
    }
    if (selector === '[type="submit"]' || selector === '.is-invalid') return null;
    return null;
  },
  addEventListener(type, listener) { neutralValidationFormListeners[type] = listener; },
};
neutralValidationRuntime.state.validatedForms = [neutralValidationForm];
neutralValidationRuntime.documentListeners.DOMContentLoaded[0]();

untouchedRequired.listeners.blur();
assert.equal(untouchedRequired.classList.contains('is-valid'), false, 'untouched edit values remain neutral');
assert.equal(untouchedRequired.classList.contains('is-invalid'), false, 'untouched edit values are not invalid');
optionalPassword.listeners.blur();
assert.equal(optionalPassword.classList.contains('is-valid'), false, 'blank optional password remains neutral');
assert.equal(optionalPassword.classList.contains('is-invalid'), false, 'blank optional password is not invalid');
assert.equal(optionalPasswordFeedback.textContent, '', 'blank optional password has no error message');

untouchedRequired.value = '';
untouchedRequired.listeners.input();
untouchedRequired.listeners.blur();
assert.equal(untouchedRequired.classList.contains('is-invalid'), true, 'cleared required field becomes invalid after blur');
assert.equal(neutralFeedback.textContent, 'This field is required.');

untouchedRequired.value = 'Jane';
untouchedRequired.listeners.input();
assert.equal(untouchedRequired.classList.contains('is-invalid'), false, 'corrected required field clears its error');
assert.equal(untouchedRequired.classList.contains('is-valid'), false, 'errors-only form keeps corrected fields neutral');

optionalPassword.value = 'short';
optionalPassword.listeners.input();
optionalPassword.listeners.blur();
assert.equal(optionalPassword.classList.contains('is-invalid'), true, 'entered short optional password is invalid');
optionalPassword.value = '';
optionalPassword.listeners.input();
assert.equal(optionalPassword.classList.contains('is-invalid'), false, 'cleared optional password returns to neutral');
assert.equal(optionalPasswordFeedback.textContent, '');

let neutralSubmitPrevented = false;
neutralValidationFormListeners.submit({ preventDefault() { neutralSubmitPrevented = true; } });
assert.equal(neutralSubmitPrevented, false, 'valid errors-only form can submit');
assert.equal(neutralValidationForm.classList.contains('was-validated'), false, 'errors-only form does not enable Bootstrap valid-state styling');

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
  dataset: { validationDirty: 'true' },
  classList: classList(['is-invalid']),
  removeAttribute() {},
  focus() { managementFocusCount += 1; },
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
    if (selector === '[data-validate]') return [managementInvalidField];
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
assert.equal(managementInvalidField.dataset.validationDirty, 'false');
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

process.stdout.write('JavaScript runtime contracts: all passed\n');
