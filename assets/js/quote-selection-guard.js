(() => {
  const FORMULAS = ['Essentiel', 'Ambiance', 'Expérience'];
  const OPTIONS = [
    'Pack Instant Magique',
    'Pack Instant Magique Signature',
    'Photobooth 150 tirages',
    'Photobooth 300 tirages',
    'Fumée lourde',
    'Étincelles froides',
    'Éclairage mural',
    'Écran & projecteur'
  ];

  function buildGroup(title, items, className) {
    const fieldset = document.createElement('fieldset');
    fieldset.className = `home-quote__group home-quote__group--full ${className}`;
    fieldset.innerHTML = `
      <legend>${title}</legend>
      <div class="home-quote__checks">
        ${items.map(item => `<label><input type="checkbox" name="selections[]" value="${item}"><span>${item}</span></label>`).join('')}
      </div>
    `;
    return fieldset;
  }

  function syncGroup(group, allowedValues) {
    if (!group) return;
    const checked = new Set(
      [...document.querySelectorAll('.home-quote__form input[name="selections[]"]:checked')]
        .map(input => input.value)
    );

    const checks = group.querySelector('.home-quote__checks');
    if (!checks) return;

    checks.replaceChildren(...allowedValues.map((value) => {
      const label = document.createElement('label');
      const input = document.createElement('input');
      const span = document.createElement('span');
      input.type = 'checkbox';
      input.name = 'selections[]';
      input.value = value;
      input.checked = checked.has(value);
      span.textContent = value;
      label.append(input, span);
      return label;
    }));
  }

  let syncing = false;

  function upgradeHomeForm() {
    if (syncing) return;
    const form = document.querySelector('.home-quote__form');
    if (!form) return;

    const budget = form.querySelector('select[name="budget"]');
    if (budget) budget.closest('.home-quote__field')?.remove();

    syncing = true;
    try {
      let formulaGroup = form.querySelector('.home-quote__group--formulas');
      let optionGroup = form.querySelector('.home-quote__group--options');

      if (!formulaGroup || !optionGroup) {
        const existingGroup = [...form.querySelectorAll('.home-quote__group')].find(group =>
          group.querySelector('input[name="selections[]"]')
        );
        if (!existingGroup) return;

        const checked = new Set([...existingGroup.querySelectorAll('input[name="selections[]"]:checked')].map(input => input.value));
        formulaGroup = buildGroup('Choisissez votre formule *', FORMULAS, 'home-quote__group--formulas');
        optionGroup = buildGroup('Packs & options complémentaires', OPTIONS, 'home-quote__group--options');
        formulaGroup.querySelectorAll('input').forEach(input => { input.checked = checked.has(input.value); });
        optionGroup.querySelectorAll('input').forEach(input => { input.checked = checked.has(input.value); });
        existingGroup.replaceWith(formulaGroup, optionGroup);
      } else {
        // Le CMS peut mettre à jour les options après coup : on rétablit toujours
        // une séparation stricte entre formules et packs/options.
        syncGroup(formulaGroup, FORMULAS);
        syncGroup(optionGroup, OPTIONS);
      }

      if (form.dataset.selectionGuardReady !== '1') {
        form.dataset.selectionGuardReady = '1';
        form.addEventListener('submit', (event) => {
          const formulaChecked = FORMULAS.some(value => form.querySelector(`.home-quote__group--formulas input[name="selections[]"][value="${value}"]:checked`));
          if (formulaChecked) return;

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
    } finally {
      syncing = false;
    }
  }

  const observer = new MutationObserver(() => {
    window.clearTimeout(observer._timer);
    observer._timer = window.setTimeout(upgradeHomeForm, 20);
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });
  upgradeHomeForm();
})();