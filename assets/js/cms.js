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

  function parseDynamicOptions(content) {
    const raw = String(content?.['options.dynamic'] || '').trim();
    if (!raw) return null;
    try {
      const options = JSON.parse(raw);
      return Array.isArray(options) ? options : null;
    } catch (_) {
      return null;
    }
  }

  function syncQuoteOptions(options) {
    const apply = () => {
      const form = document.querySelector('.home-quote__form');
      if (!form) return false;
      const inputs = [...form.querySelectorAll('input[name="selections[]"]')];
      if (!inputs.length) return false;

      const baseValues = new Set(['Essentiel', 'Ambiance', 'Expérience', 'Pack Instant Magique', 'Pack Instant Magique Signature']);
      const container = inputs[0].closest('.home-quote__checks');
      if (!container) return false;

      [...container.querySelectorAll('label')].forEach((label) => {
        const input = label.querySelector('input[name="selections[]"]');
        if (input && !baseValues.has(input.value)) label.remove();
      });

      options.forEach((option) => {
        const title = String(option?.title || '').trim();
        if (!title) return;
        const label = document.createElement('label');
        const input = document.createElement('input');
        const span = document.createElement('span');
        input.type = 'checkbox';
        input.name = 'selections[]';
        input.value = title;
        span.textContent = title;
        label.append(input, span);
        container.appendChild(label);
      });
      return true;
    };

    if (apply()) return;
    let attempts = 0;
    const timer = setInterval(() => {
      attempts += 1;
      if (apply() || attempts >= 20) clearInterval(timer);
    }, 100);
  }

  function renderDynamicOptions(content) {
    const options = parseDynamicOptions(content);
    if (!options) return;

    const grid = document.querySelector('.options-grid');
    if (grid) {
      const cards = options.map((option) => {
        const title = String(option?.title || '').trim();
        if (!title) return null;

        const card = document.createElement('article');
        card.className = 'option-card';

        const image = String(option?.image || '').trim();
        if (image) {
          const visual = document.createElement('div');
          visual.className = 'option-card__image has-cms-image';
          const x = clampPercent(option?.x, 50);
          const y = clampPercent(option?.y, 50);
          queueBackgroundImage(visual, image, `${x}% ${y}%`);
          card.appendChild(visual);
        }

        const heading = document.createElement('h3');
        heading.textContent = title;
        const text = document.createElement('p');
        text.textContent = String(option?.desc || '').trim();
        card.append(heading, text);
        return card;
      }).filter(Boolean);

      grid.replaceChildren(...cards);
    }

    syncQuoteOptions(options);
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function escapeAttribute(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function renderDeal(content) {
    document.querySelector('.home-deal')?.remove();
    if (!content || String(content['deal.enabled'] || '0') !== '1') return;

    const availability = document.querySelector('#disponibilites');
    if (!availability) return;

    const dateText = String(content['deal.date'] || '').trim();
    const section = document.createElement('section');
    section.className = 'home-deal';
    section.id = 'bon-plan';
    section.innerHTML = `
      <div class="home-deal__inner">
        <div class="home-deal__content">
          <div class="home-deal__badge">${escapeHtml(content['deal.badge'] || 'Bon plan — dernière minute')}</div>
          <div class="home-deal__availability-line">Je suis disponible le</div>
          ${dateText ? `<div class="home-deal__date">${escapeHtml(dateText)}</div>` : ''}
          <h2>${escapeHtml(content['deal.title'] || 'Une date vient de se libérer.')}</h2>
          <p>${escapeHtml(content['deal.text'] || '')}</p>
          <a href="#devis" class="home-deal__cta">Profiter de cette disponibilité <span>→</span></a>
        </div>
        ${content['deal.image'] ? `<div class="home-deal__visual"><img src="${escapeAttribute(content['deal.image'])}" alt="Bon plan Luka C" loading="lazy" decoding="async"></div>` : ''}
      </div>`;

    availability.insertAdjacentElement('afterend', section);

    let style = document.getElementById('home-deal-styles');
    if (!style) {
      style = document.createElement('style');
      style.id = 'home-deal-styles';
      document.head.appendChild(style);
    }
    style.textContent = `
      .home-deal{order:4!important;max-width:1420px;width:100%;margin:0 auto;padding:34px 34px 84px;scroll-margin-top:96px}
      .home-deal__inner{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr);overflow:hidden;border-radius:26px;background:#181716;color:#fff;box-shadow:0 20px 55px rgba(24,23,22,.14)}
      .home-deal__content{padding:46px 48px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;min-height:360px}
      .home-deal__badge{display:inline-flex;padding:7px 10px;border-radius:999px;background:#c93431;color:#fff;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;margin-bottom:26px}
      .home-deal__availability-line{font:600 18px/1.2 'Space Grotesk',sans-serif;color:#f3efeb;margin:0 0 6px}
      .home-deal__date{font:700 clamp(38px,5vw,70px)/.95 'Space Grotesk',sans-serif;letter-spacing:-.045em;color:#fff;margin:0 0 24px;text-transform:uppercase}
      .home-deal h2{font:600 clamp(24px,2.6vw,38px)/1.08 'Space Grotesk',sans-serif;letter-spacing:-.03em;margin:0 0 14px;max-width:700px;color:#fff}
      .home-deal p{margin:0 0 26px;max-width:660px;color:#c6c0ba;font-size:14px;line-height:1.7}
      .home-deal__cta{display:inline-flex;align-items:center;gap:12px;padding:14px 19px;border-radius:999px;background:#fff;color:#181716;font-size:12px;font-weight:800;transition:.2s}
      .home-deal__cta:hover{background:#c93431;color:#fff;transform:translateY(-2px)}
      .home-deal__visual{min-height:360px;background:#2a2826}
      .home-deal__visual img{width:100%;height:100%;min-height:360px;object-fit:cover;display:block}
      .home-prestations{order:5!important}.home-formules{order:6!important}.home-quote{order:7!important}
      @media(max-width:820px){.home-deal{padding:24px 18px 64px}.home-deal__inner{grid-template-columns:1fr}.home-deal__content{padding:32px 26px;min-height:auto}.home-deal__badge{margin-bottom:20px}.home-deal__availability-line{font-size:16px}.home-deal__date{font-size:clamp(38px,11vw,56px);margin-bottom:20px}.home-deal__visual,.home-deal__visual img{min-height:260px;max-height:360px}.home-deal__visual{order:-1}}
    `;
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
      if (data && data.content) {
        applyContent(data.content);
        renderDynamicOptions(data.content);
        renderDeal(data.content);
      }
      if (data && data.availability) applyAvailability(data.availability);
      if (data && data.reviews) applyReviews(data.reviews);
    })
    .catch(() => {
      // Le site garde son contenu HTML par défaut si l'administration n'est pas disponible.
    });

  // Statistiques anonymes : une même personne n'est comptée qu'une fois par jour.
  fetch('api/visit.php', {
    method: 'POST',
    credentials: 'same-origin',
    cache: 'no-store',
    keepalive:true
  }).catch(() => {});
})();
