(() => {
  if (!document.getElementById('quote-dependent-styles')) {
    const style = document.createElement('style');
    style.id = 'quote-dependent-styles';
    style.textContent = '.home-quote__dependent{grid-column:1/-1;display:flex;gap:10px;margin:-2px 0 4px 22px;padding:10px 12px;border-left:2px solid #c93431}.home-quote__dependent[hidden]{display:none}.home-quote__dependent label{display:flex;align-items:center;gap:8px;padding:9px 13px;border:1px solid rgba(24,23,22,.10);border-radius:10px;background:#fff;font-size:12px;cursor:pointer}.home-quote__dependent input{width:16px;height:16px;margin:0;accent-color:#c93431}@media(max-width:700px){.home-quote__dependent{margin-left:10px;flex-direction:column}}';
    document.head.appendChild(style);
  }

  const FORMULAS = ['Essentiel', 'Ambiance', 'Expérience'];
  const OPTIONS = [
    'Photobooth',
    "Livre d'or audio",
    'Fumée lourde',
    'Étincelles froides',
    'Éclairage mural',
    'Écran & projecteur'
  ];

  function createChoice(value, checked = false) {
    const label = document.createElement('label');
    const input = document.createElement('input');
    const span = document.createElement('span');
    input.type = 'checkbox';
    input.name = 'selections[]';
    input.value = value;
    input.checked = checked;
    span.textContent = value;
    label.append(input, span);
    return label;
  }

  function buildGroup(title, items, className, checkedValues) {
    const fieldset = document.createElement('fieldset');
    fieldset.className = `home-quote__group home-quote__group--full ${className}`;
    const legend = document.createElement('legend');
    legend.textContent = title;
    const checks = document.createElement('div');
    checks.className = 'home-quote__checks';
    items.forEach((item) => checks.appendChild(createChoice(item, checkedValues.has(item))));
    fieldset.append(legend, checks);
    return fieldset;
  }

  function moveMisplacedOptions(form) {
    const formulaChecks = form.querySelector('.home-quote__group--formulas .home-quote__checks');
    const optionChecks = form.querySelector('.home-quote__group--options .home-quote__checks');
    if (!formulaChecks || !optionChecks) return;

    [...formulaChecks.querySelectorAll('label')].forEach((label) => {
      const input = label.querySelector('input[name="selections[]"]');
      if (!input || FORMULAS.includes(input.value)) return;

      const duplicate = [...optionChecks.querySelectorAll('input[name="selections[]"]')]
        .find((existing) => existing.value === input.value);
      if (duplicate) {
        if (input.checked) duplicate.checked = true;
        label.remove();
      } else {
        optionChecks.appendChild(label);
      }
    });
  }

  function ensureDependentChoices(form) {
    const optionChecks = form.querySelector('.home-quote__group--options .home-quote__checks');
    if (!optionChecks) return;

    const configs = [
      {
        value: 'Étincelles froides',
        name: 'spark_option',
        choices: [
          ['Étincelles froides — 2 jets', '2 jets'],
          ['Étincelles froides — 4 jets', '4 jets']
        ]
      },
      {
        value: 'Photobooth',
        name: 'photobooth_option',
        choices: [
          ['Photobooth 150 tirages', '150 tirages'],
          ['Photobooth 300 tirages', '300 tirages']
        ]
      }
    ];

    configs.forEach((config) => {
      const parent = [...optionChecks.querySelectorAll('input[name="selections[]"]')]
        .find((input) => input.value === config.value);
      if (!parent) return;

      const parentLabel = parent.closest('label');
      let choices = optionChecks.querySelector('[data-dependent-for="' + config.name + '"]');
      if (!choices) {
        choices = document.createElement('div');
        choices.className = 'home-quote__dependent';
        choices.dataset.dependentFor = config.name;
        config.choices.forEach(([value, text]) => {
          const label = document.createElement('label');
          const input = document.createElement('input');
          const span = document.createElement('span');
          input.type = 'radio';
          input.name = config.name;
          input.value = value;
          span.textContent = text;
          label.append(input, span);
          choices.appendChild(label);
        });
        parentLabel.insertAdjacentElement('afterend', choices);
      }

      const sync = () => {
        choices.hidden = !parent.checked;
        if (!parent.checked) {
          choices.querySelectorAll('input[type="radio"]').forEach((input) => input.checked = false);
        }
      };
      if (parent.dataset.dependentReady !== '1') {
        parent.dataset.dependentReady = '1';
        parent.addEventListener('change', sync);
      }
      sync();
    });
  }

  function prepareHomeForm() {
    const form = document.querySelector('.home-quote__form');
    if (!form) return false;

    const budget = form.querySelector('select[name="budget"]');
    if (budget) budget.closest('.home-quote__field')?.remove();

    if (!form.querySelector('.home-quote__group--formulas') || !form.querySelector('.home-quote__group--options')) {
      const existingGroup = [...form.querySelectorAll('.home-quote__group')].find((group) =>
        group.querySelector('input[name="selections[]"]')
      );
      if (!existingGroup) return false;

      const checkedValues = new Set(
        [...existingGroup.querySelectorAll('input[name="selections[]"]:checked')].map((input) => input.value)
      );
      const formulaGroup = buildGroup('Choisissez votre formule *', FORMULAS, 'home-quote__group--formulas', checkedValues);
      const optionGroup = buildGroup('Options complémentaires', OPTIONS, 'home-quote__group--options', checkedValues);
      existingGroup.replaceWith(formulaGroup, optionGroup);
    }

    moveMisplacedOptions(form);
    ensureDependentChoices(form);

    if (form.dataset.selectionGuardReady !== '1') {
      form.dataset.selectionGuardReady = '1';
      form.addEventListener('submit', (event) => {
        const hasFormula = [...form.querySelectorAll('.home-quote__group--formulas input[name="selections[]"]:checked')]
          .some((input) => FORMULAS.includes(input.value));
        if (hasFormula) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        const message = document.querySelector('.home-quote__message');
        if (message) {
          message.className = 'home-quote__message is-error';
          message.textContent = 'Merci de choisir une formule : Essentiel, Ambiance ou Expérience.';
          message.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        form.querySelector('.home-quote__group--formulas')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, true);
    }

    return true;
  }

  const observer = new MutationObserver(() => {
    const form = document.querySelector('.home-quote__form');
    if (!form) {
      prepareHomeForm();
      return;
    }
    moveMisplacedOptions(form);
    ensureDependentChoices(form);
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
  prepareHomeForm();
})();