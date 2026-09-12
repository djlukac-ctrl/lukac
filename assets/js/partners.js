(() => {
  function parsePartners(content) {
    const raw = String(content?.['partners.dynamic'] || '').trim();
    if (!raw) return [];
    try {
      const partners = JSON.parse(raw);
      if (!Array.isArray(partners)) return [];
      return partners
        .filter((partner) => String(partner?.name || '').trim())
        .sort((a, b) => Number(a?.order || 999) - Number(b?.order || 999));
    } catch (_) {
      return [];
    }
  }

  function safeUrl(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    try {
      const url = new URL(raw, window.location.origin);
      return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
    } catch (_) {
      return '';
    }
  }

  function renderPartners(content) {
    document.querySelector('.home-partners')?.remove();
    const partners = parsePartners(content);
    if (!partners.length) return;

    const quote = document.querySelector('#devis');
    if (!quote) return;

    const section = document.createElement('section');
    section.className = 'home-partners';
    section.id = 'partenaires';
    section.style.order = '8';

    const head = document.createElement('div');
    head.className = 'home-partners__head';
    head.innerHTML = `
      <p class="home-partners__kicker">Mes partenaires</p>
      <h2>Des prestataires de confiance<br><em>pour compléter votre événement.</em></h2>
      <p>Des professionnels avec qui j’ai l’habitude de travailler et que je peux vous recommander pour vous accompagner dans l’organisation de votre événement.</p>
    `;

    const grid = document.createElement('div');
    grid.className = 'home-partners__grid';

    partners.forEach((partner) => {
      const card = document.createElement('article');
      card.className = 'home-partner-card';

      const image = String(partner?.image || '').trim();
      if (image) {
        const visual = document.createElement('div');
        visual.className = 'home-partner-card__visual';
        const img = document.createElement('img');
        img.src = image;
        img.alt = String(partner?.name || 'Prestataire partenaire');
        img.loading = 'lazy';
        img.decoding = 'async';
        visual.appendChild(img);
        card.appendChild(visual);
      }

      const body = document.createElement('div');
      body.className = 'home-partner-card__body';

      const activity = String(partner?.activity || '').trim();
      if (activity) {
        const meta = document.createElement('span');
        meta.className = 'home-partner-card__activity';
        meta.textContent = activity;
        body.appendChild(meta);
      }

      const title = document.createElement('h3');
      title.textContent = String(partner?.name || '').trim();
      body.appendChild(title);

      const desc = String(partner?.desc || '').trim();
      if (desc) {
        const text = document.createElement('p');
        text.textContent = desc;
        body.appendChild(text);
      }

      const url = safeUrl(partner?.url);
      if (url) {
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.innerHTML = 'Découvrir <span>→</span>';
        body.appendChild(link);
      }

      card.appendChild(body);
      grid.appendChild(card);
    });

    section.append(head, grid);
    quote.insertAdjacentElement('afterend', section);

    let style = document.getElementById('home-partners-styles');
    if (!style) {
      style = document.createElement('style');
      style.id = 'home-partners-styles';
      document.head.appendChild(style);
    }

    style.textContent = `
      .home-partners{max-width:1420px;width:100%;margin:0 auto;padding:16px 34px 110px;color:#181716}
      .home-partners__head{max-width:820px;margin-bottom:38px}
      .home-partners__kicker{margin:0 0 14px;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:#c93431;font-weight:800}
      .home-partners__head h2{font:600 clamp(38px,4.2vw,64px)/1.02 'Space Grotesk',sans-serif;letter-spacing:-.04em;margin:0 0 16px;color:#181716}
      .home-partners__head h2 em{font-style:normal;color:#c93431}
      .home-partners__head>p:last-child{max-width:720px;margin:0;color:#6f6862;font-size:13px;line-height:1.75}
      .home-partners__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
      .home-partner-card{overflow:hidden;border:1px solid rgba(24,23,22,.10);border-radius:20px;background:#fff;box-shadow:0 12px 32px rgba(50,38,28,.05)}
      .home-partner-card__visual{height:180px;display:grid;place-items:center;background:#faf8f5;padding:22px;border-bottom:1px solid rgba(24,23,22,.08)}
      .home-partner-card__visual img{width:100%;height:100%;object-fit:contain;display:block}
      .home-partner-card__body{padding:24px}
      .home-partner-card__activity{display:inline-block;margin-bottom:9px;color:#c93431;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
      .home-partner-card h3{margin:0 0 10px;font:600 23px/1.1 'Space Grotesk',sans-serif;letter-spacing:-.025em;color:#181716}
      .home-partner-card p{margin:0 0 18px;color:#6f6862;font-size:13px;line-height:1.65}
      .home-partner-card a{display:inline-flex;align-items:center;gap:8px;color:#181716;font-size:12px;font-weight:800;text-decoration:none}
      .home-partner-card a:hover{color:#c93431}
      @media(max-width:950px){.home-partners__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
      @media(max-width:650px){.home-partners{padding:14px 18px 80px}.home-partners__grid{grid-template-columns:1fr}.home-partner-card__visual{height:160px}.home-partner-card__body{padding:20px}}
    `;
  }

  fetch('api/site-data.php?partners=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
    .then((response) => response.ok ? response.json() : Promise.reject())
    .then((data) => renderPartners(data?.content || {}))
    .catch(() => {});
})();
