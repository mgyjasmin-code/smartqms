(() => {
  'use strict';

  const selector = '[data-public-datepicker]';
  const instances = new WeakMap();
  const filipinoLocale = {
    days: ['Linggo', 'Lunes', 'Martes', 'Miyerkules', 'Huwebes', 'Biyernes', 'Sabado'],
    daysShort: ['Lin', 'Lun', 'Mar', 'Miy', 'Huw', 'Biy', 'Sab'],
    daysMin: ['Li', 'Lu', 'Ma', 'Mi', 'Hu', 'Bi', 'Sa'],
    months: ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'],
    monthsShort: ['Ene', 'Peb', 'Mar', 'Abr', 'May', 'Hun', 'Hul', 'Ago', 'Set', 'Okt', 'Nob', 'Dis'],
    today: 'Ngayon',
    clear: 'I-clear',
    titleFormat: 'MM y',
    format: 'yyyy-mm-dd',
    weekStart: 0,
  };

  const language = () => document.documentElement.lang === 'fil' ? 'fil' : 'en';

  const destroy = (input) => {
    const picker = instances.get(input);
    if (picker) {
      try { picker.destroy(); } catch (_) {}
      instances.delete(input);
    }
  };

  const enhance = (input) => {
    if (!window.Datepicker) return false;
    destroy(input);
    const originalType = input.dataset.publicDatepickerNativeType || input.type || 'date';
    const currentValue = input.value;
    const minimumDate = input.getAttribute('min') || undefined;
    const maximumDate = input.getAttribute('max') || undefined;
    input.dataset.publicDatepickerNativeType = originalType;

    try {
      window.Datepicker.locales.fil = filipinoLocale;
      input.type = 'text';
      input.inputMode = 'numeric';
      input.placeholder = 'YYYY-MM-DD';
      const picker = new window.Datepicker(input, {
        autohide: true,
        buttonClass: 'btn',
        format: 'yyyy-mm-dd',
        language: language(),
        minDate: minimumDate,
        maxDate: maximumDate,
        todayBtn: true,
        todayBtnMode: 1,
        todayHighlight: true,
        weekStart: 0,
      });
      if (currentValue) picker.setDate(currentValue, { render: true });
      input.dataset.publicDatepickerEnhanced = 'true';
      instances.set(input, picker);
      return true;
    } catch (_) {
      destroy(input);
      input.type = originalType;
      input.value = currentValue;
      delete input.dataset.publicDatepickerEnhanced;
      return false;
    }
  };

  const initialize = () => document.querySelectorAll(selector).forEach((input) => enhance(input));

  const reinitializeForLanguage = () => {
    document.querySelectorAll(selector).forEach((input) => {
      const value = input.value;
      enhance(input);
      if (value) input.value = value;
    });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
  else initialize();
  window.addEventListener('smartqms:languagechange', reinitializeForLanguage);
})();
