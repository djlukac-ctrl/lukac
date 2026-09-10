(() => {
  const FORMULAS = ['Essentiel', 'Ambiance', 'Expérience'];
  const OPTIONS = ['Pack Instant Magique', 'Pack Instant Magique Signature', 'Fumée lourde', 'Étincelles froides', 'Éclairage mural', 'Écran & projecteur'];

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

  function upgradeHomeForm() {
    const form = document.querySelector('.home-quote__form');
    if (!form) return;

    const budget = form.querySelector('select[name="budget"]');
    if (budget) budget.closest('.home-quote__field')?.remove();

    if (form.dataset.selectionGuardReady === '1') return;

    const existingGroup = [...form.querySelectorAll('.home-quote__group')].find(group =>
      group.querySelector('input[name="selections[]"]')
    );
    if (!existingGroup) return;

    const checked = new Set([...existingGroup.querySelectorAll('input[name="selections[]"]:checked')].map(input => input.value));
    const formulaGroup = buildGroup('Choisissez votre formule *', FORMULAS, 'home-quote__group--formulas');
    const optionGroup = buildGroup('Packs & options complémentaires', OPTIONS, 'home-quote__group--options');

    formulaGroup.querySelectorAll('input').forEach(input => { input.checked = checked.has(input.value); });
    optionGroup.querySelectorAll('input').forEach(input => { input.checked = checked.has(input.value); });

    existingGroup.replaceWith(formulaGroup, optionGroup);
    form.dataset.selectionGuardReady = '1';

    form.addEventListener('submit', (event) => {
      const formulaChecked = FORMULAS.some(value => form.querySelector(`input[name="selections[]"][value="${value}"]:checked`));
      if (formulaChecked) return;

      event.preventDefault();
      event.stopImmediatePropagation();
      const message = document.querySelector('.home-quote__message');
      if (message) {
        message.className = 'home-quote__message is-error';
        message.textContent = 'Merci de choisir une formule : Essentiel, Ambiance ou Expérience.';
        message.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      formulaGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, true);
  }

  const observer = new MutationObserver(upgradeHomeForm);
  observer.observe(document.documentElement, { childList: true, subtree: true });
  upgradeHomeForm();
})();