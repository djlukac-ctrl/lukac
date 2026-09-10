(() => {
  const statusMeta = {
    open: { label: 'Ouvert', icon: '✓', className: 'availability__month--open' },
    limited: { label: 'Quelques disponibilités', icon: '◷', className: 'availability__month--limited' },
    closed: { label: 'Complet / Fermé', icon: '×', className: 'availability__month--closed' }
  };

  const monthNumbers = {
    janvier: 1, fevrier: 2, février: 2, mars: 3, avril: 4, mai: 5, juin: 6,
    juillet: 7, aout: 8, août: 8, septembre: 9, octobre: 10, novembre: 11, decembre: 12, décembre: 12
  };

  function normalize(value) {
    return value.trim().toLowerCase();
  }

  function applyContent(content) {
    document.querySelectorAll('[data-cms]').forEach((el) => {
      const key = el.dataset.cms;
      if (!key || !(key in content)) return;
      el.textContent = content[key];
    });

    document.querySelectorAll('[data-cms-list]').forEach((el) => {
      const key = el.dataset.cmsList;
      if (!key || !(key in content)) return;
      const items = String(content[key]).split(/\r?\n/).map((item) => item.trim()).filter(Boolean);
      el.replaceChildren(...items.map((item) => {
        const li = document.createElement('li');
        li.textContent = item;
        return li;
      }));
    });

    document.querySelectorAll('[data-cms-image]').forEach((el) => {
      const key = el.dataset.cmsImage;
      const path = key && content[key] ? String(content[key]).trim() : '';
      if (!path) return;
      if (el.tagName === 'IMG') {
        el.src = path;
      } else {
        el.style.backgroundImage = `url("${path.replace(/"/g, '%22')}")`;
        el.style.backgroundSize = 'cover';
        el.style.backgroundPosition = 'center';
        el.classList.add('has-cms-image');
      }
    });
  }

  function applyAvailability(availability) {
    document.querySelectorAll('.availability__year').forEach((yearBlock) => {
      const yearText = yearBlock.querySelector('.availability__year-number')?.textContent?.trim();
      if (!yearText || !availability[yearText]) return;

      yearBlock.querySelectorAll('.availability__month').forEach((row) => {
        const monthName = normalize(row.querySelector('.availability__month-name')?.textContent || '');
        const month = monthNumbers[monthName];
        if (!month) return;
        const status = availability[yearText][String(month)];
        const meta = statusMeta[status];
        if (!meta) return;

        row.classList.remove('availability__month--open', 'availability__month--limited', 'availability__month--closed');
        row.classList.add(meta.className);
        const icon = row.querySelector('.availability__icon');
        const label = row.querySelector('.availability__status');
        if (icon) icon.textContent = meta.icon;
        if (label) label.textContent = meta.label;
      });
    });
  }

  fetch('api/site-data.php?t=' + Date.now(), { credentials: 'same-origin' })
    .then((response) => {
      if (!response.ok) throw new Error('CMS unavailable');
      return response.json();
    })
    .then((data) => {
      if (data && data.content) applyContent(data.content);
      if (data && data.availability) applyAvailability(data.availability);
    })
    .catch(() => {
      // Le site garde son contenu HTML par défaut si l'administration n'est pas disponible.
    });
})();
