(() => {
  'use strict';

  const form = document.querySelector('[data-public-booking]');
  if (!form || form.dataset.initialized === 'true') return;
  form.dataset.initialized = 'true';

  const steps = [...form.querySelectorAll('[data-booking-step]')];
  const nextButton = form.querySelector('[data-booking-next]');
  const backButton = form.querySelector('[data-booking-back]');
  const submitButton = form.querySelector('[data-booking-submit]');
  const progress = document.querySelector('[role="progressbar"][aria-label="Booking progress"]');
  const progressBar = document.querySelector('[data-booking-progress-bar]');
  const progressLabel = document.querySelector('[data-booking-progress-label]');
  const progressName = document.querySelector('[data-booking-progress-name]');
  const errorSummary = document.querySelector('[data-booking-error-summary]');
  const stepperItems = [...document.querySelectorAll('.public-booking-stepper li')];
  const processingStatus = form.querySelector('[data-booking-processing]');
  const submitSpinner = submitButton?.querySelector('[data-booking-spinner]');
  const submitLabel = submitButton?.querySelector('[data-booking-submit-label]');
  const submitIcon = submitButton?.querySelector('[data-booking-submit-icon]');
  const stepNames = ['Choose Service', 'Appointment Details', 'Review'];
  let currentStep = Math.min(3, Math.max(1, Number.parseInt(form.dataset.initialStep || '1', 10)));

  const field = (name) => form.elements.namedItem(name);

  const updateReview = () => {
    const service = field('service_id');
    const selectedServiceInput = typeof RadioNodeList !== 'undefined' && service instanceof RadioNodeList
      ? [...form.querySelectorAll('[data-booking-service-input]')].find((input) => input.checked)
      : service;
    const selectedService = selectedServiceInput?.dataset?.serviceName || '—';
    const visitDate = field('visit_date')?.value || '';
    const parsedDate = visitDate ? new Date(`${visitDate}T00:00:00`) : null;
    const dateLabel = parsedDate && !Number.isNaN(parsedDate.getTime())
      ? new Intl.DateTimeFormat(document.documentElement.lang === 'fil' ? 'fil-PH' : 'en-PH', { dateStyle: 'long' }).format(parsedDate)
      : '—';
    const firstName = field('first_name')?.value?.trim() || '';
    const lastName = field('last_name')?.value?.trim() || '';
    const phone = field('phone_number')?.value?.trim() || '—';
    const values = {
      service: selectedService,
      date: dateLabel,
      name: `${firstName} ${lastName}`.trim() || '—',
      phone,
    };
    Object.entries(values).forEach(([key, value]) => {
      const output = form.querySelector(`[data-booking-review="${key}"]`);
      if (output) output.textContent = value;
    });
  };

  const render = (focusHeading = false) => {
    form.classList.add('is-enhanced');
    steps.forEach((step) => { step.hidden = Number(step.dataset.bookingStep) !== currentStep; });
    stepperItems.forEach((item, index) => {
      if (index + 1 === currentStep) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
      item.classList.toggle('is-complete', index + 1 < currentStep);
    });
    if (progress) progress.setAttribute('aria-valuenow', String(currentStep));
    if (progressBar) progressBar.style.width = `${(currentStep / steps.length) * 100}%`;
    if (progressLabel) progressLabel.textContent = `Step ${currentStep} of ${steps.length}`;
    if (progressName) progressName.textContent = stepNames[currentStep - 1];
    if (backButton) backButton.hidden = currentStep === 1;
    if (nextButton) nextButton.hidden = currentStep === steps.length;
    if (submitButton) submitButton.hidden = currentStep !== steps.length;
    if (currentStep === steps.length) updateReview();
    if (focusHeading) {
      const legend = steps[currentStep - 1]?.querySelector('legend');
      if (legend) {
        legend.tabIndex = -1;
        legend.focus();
      }
    }
  };

  const validateCurrentStep = () => {
    const controls = [...(steps[currentStep - 1]?.querySelectorAll('input, select, textarea') || [])];
    const invalid = controls.find((control) => !control.checkValidity());
    if (!invalid) return true;
    invalid.reportValidity();
    invalid.focus();
    return false;
  };

  nextButton?.addEventListener('click', () => {
    if (!validateCurrentStep()) return;
    currentStep = Math.min(steps.length, currentStep + 1);
    render(true);
  });

  backButton?.addEventListener('click', () => {
    currentStep = Math.max(1, currentStep - 1);
    render(true);
  });

  form.addEventListener('change', (event) => {
    if (event.target.name === 'service_id') {
      form.querySelectorAll('[data-booking-service-card]').forEach((card) => {
        card.classList.toggle('is-selected', card.dataset.serviceId === event.target.value);
      });
    }
  });

  errorSummary?.addEventListener('click', (event) => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;
    const target = document.getElementById(link.hash.slice(1));
    const targetStep = target?.closest('[data-booking-step]');
    if (!target || !targetStep) return;
    event.preventDefault();
    currentStep = Number.parseInt(targetStep.dataset.bookingStep || '1', 10);
    render();
    window.requestAnimationFrame(() => {
      const focusTarget = target.matches('input, select, textarea, button')
        ? target
        : target.querySelector('input:checked, input, select, textarea, button');
      focusTarget?.focus();
    });
  });

  form.addEventListener('submit', (event) => {
    if (form.dataset.submitting === 'true') {
      event.preventDefault();
      return;
    }
    for (let index = 1; index < steps.length; index += 1) {
      const invalid = [...steps[index - 1].querySelectorAll('input, select, textarea')].find((control) => !control.checkValidity());
      if (!invalid) continue;
      event.preventDefault();
      currentStep = index;
      render();
      invalid.reportValidity();
      invalid.focus();
      return;
    }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    if (submitButton) {
      const bounds = typeof submitButton.getBoundingClientRect === 'function'
        ? submitButton.getBoundingClientRect()
        : null;
      if (bounds?.width) submitButton.style.minWidth = `${Math.ceil(bounds.width)}px`;
      if (bounds?.height) submitButton.style.minHeight = `${Math.ceil(bounds.height)}px`;
      submitButton.disabled = true;
      submitButton.setAttribute('aria-busy', 'true');
      if (submitSpinner) submitSpinner.hidden = false;
      if (submitLabel) submitLabel.textContent = submitButton.dataset.loadingText || 'Creating appointment…';
      if (submitIcon) submitIcon.hidden = true;
    }
    if (processingStatus) processingStatus.hidden = false;
  });

  window.addEventListener('smartqms:languagechange', () => { if (currentStep === steps.length) updateReview(); });
  nextButton.hidden = false;
  render();
  if (errorSummary) {
    window.requestAnimationFrame(() => errorSummary.focus());
  }
})();
