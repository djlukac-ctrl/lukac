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

  const legacyImagePositions = {
    center: [50, 50],
    top: [50, 0],
    bottom: [50, 100],
    left: [0, 50],
    right: [100, 50]
  };

  const lazyBackgrounds = new Map();
  const backgroundObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const config = lazyBackgrounds.get(entry.target);
          if (!config) return;
          entry.target.style.backgroundImage = `url("${config.path.replace(/"/g, '%22')}")`;
          entry.target.style.backgroundSize = 'cover';
          entry.target.style.backgroundPosition = config.position;
          entry.target.classList.add('has-cms-image');
          lazyBackgrounds.delete(entry.target);
          observer.unobserve(entry.target);
        });
      }, { rootMargin: '500px 0px' })
    : null;

  function normalize(value) {
    return value.trim().toLowerCase();
  }

  function clampPercent(value, fallback = 50) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return fallback;
    return Math.max(0, Math.min(100, parsed));
  }

  function queueBackgroundImage(el, path, position) {
    if (!backgroundObserver) {
      el.style.backgroundImage = `url("${path.replace(/"/g, '%22')}")`;
      el.style.backgroundSize = 'cover';
      el.style.backgroundPosition = position;
      el.classList.add('has-cms-image');
      return;
    }

    lazyBackgrounds.set(el, { path, position });
    backgroundObserver.observe(el);
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

      const legacy = key && content[`${key}.position`] ? String(content[`${key}.position`]).trim().toLowerCase() : 'center';
      const legacyPair = legacyImagePositions[legacy] || [50, 50];
      const x = clampPercent(content[`${key}.position_x`], legacyPair[0]);
      const y = clampPercent(content[`${key}.position_y`], legacyPair[1]);
      const position = `${x}% ${y}%`;

      if (el.tagName === 'IMG') {
        el.src = path;
        el.style.objectFit = 'cover';
        el.style.objectPosition = position;
      } else {
        queueBackgroundImage(el, path, position);
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

  function formatReviewDate(value) {
    if (!value) return '';
    const date = new Date(`${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
  }

  function applyReviews(reviews) {
    const grid = document.querySelector('.reviews-grid');
    if (!grid || !Array.isArray(reviews) || reviews.length === 0) return;

    const cards = reviews.slice(0, 3).map((review) => {
      const blockquote = document.createElement('blockquote');
      blockquote.className = 'review';

      const fullName = String(review.client_name || 'Client').trim();
      const firstName = fullName.split(/\s+/)[0] || 'Client';

      const recommendation = document.createElement('div');
      recommendation.className = 'review__recommendation';
      recommendation.innerHTML = `${firstName} recommande <span>Luka C.</span>`;

      const date = document.createElement('div');
      date.className = 'review__date';
      date.textContent = formatReviewDate(review.review_date);

      const text = document.createElement('p');
      text.textContent = review.review_text || '';

      blockquote.append(recommendation);
      if (date.textContent) blockquote.append(date);
      blockquote.append(text);
      return blockquote;
    });

    grid.replaceChildren(...cards);
  }

  fetch('api/site-data.php?t=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
    .then((response) => {
      if (!response.ok) throw new Error('CMS unavailable');
      return response.json();
    })
    .then((data) => {
      if (data && data.content) applyContent(data.content);
      if (data && data.availability) applyAvailability(data.availability);
      if (data && data.reviews) applyReviews(data.reviews);
    })
    .catch(() => {
      // Le site garde son contenu HTML par défaut si l'administration n'est pas disponible.
    });
})();