(() => {
  const FORMULAS = ['Essentiel', 'Ambiance', 'Expérience'];
  const OPTIONS = [
    'Pack Instant Magique',
    'Pack Instant Magique Signature',
    'Photobooth 150 tirages',
    'Photobooth 300 tirages',
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
      const optionGroup = buildGroup('Packs & options complémentaires', OPTIONS, 'home-quote__group--options', checkedValues);
      existingGroup.replaceWith(formulaGroup, optionGroup);
    }

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

  if (prepareHomeForm()) return;

  const observer = new MutationObserver(() => {
    if (!prepareHomeForm()) return;
    observer.disconnect();
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });
})();