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
    grid.dataset.count = String(partners.length);

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
        link.className = 'home-partner-card__link';
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
      .home-partners{max-width:1420px;width:100%;margin:0 auto;padding:18px 34px 84px;color:#181716}
      .home-partners__head{max-width:820px;margin-bottom:32px}
      .home-partners__kicker{margin:0 0 14px;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:#c93431;font-weight:800}
      .home-partners__head h2{font:600 clamp(38px,4.2vw,64px)/1.02 'Space Grotesk',sans-serif;letter-spacing:-.04em;margin:0 0 16px;color:#181716}
      .home-partners__head h2 em{font-style:normal;color:#c93431}
      .home-partners__head>p:last-child{max-width:720px;margin:0;color:#6f6862;font-size:13px;line-height:1.75}
      .home-partners__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px;align-items:stretch}
      .home-partners__grid[data-count="1"]{grid-template-columns:minmax(0,380px)}
      .home-partners__grid[data-count="2"]{grid-template-columns:repeat(2,minmax(0,380px))}
      .home-partner-card{display:flex;flex-direction:column;min-width:0;overflow:hidden;border:1px solid rgba(24,23,22,.10);border-radius:22px;background:#fff;box-shadow:0 14px 36px rgba(50,38,28,.06);transition:transform .2s ease,box-shadow .2s ease}
      .home-partner-card:hover{transform:translateY(-3px);box-shadow:0 20px 44px rgba(50,38,28,.10)}
      .home-partner-card__visual{position:relative;flex:0 0 auto;height:210px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f7f4ef;padding:10px;border-bottom:1px solid rgba(24,23,22,.08)}
      .home-partner-card__visual img{display:block!important;position:static!important;max-width:94%!important;max-height:94%!important;width:auto!important;height:auto!important;object-fit:contain!important;margin:0 auto!important;transform:none!important}
      .home-partner-card__body{position:relative;z-index:1;display:flex;flex:1;flex-direction:column;align-items:flex-start;padding:24px 24px 25px;background:#fff;color:#181716}
      .home-partner-card__activity{display:inline-block;margin:0 0 9px;color:#c93431;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
      .home-partner-card h3{margin:0 0 10px;font:600 24px/1.08 'Space Grotesk',sans-serif;letter-spacing:-.025em;color:#181716}
      .home-partner-card p{margin:0 0 20px;color:#6f6862;font-size:13px;line-height:1.65}
      .home-partner-card__link{display:inline-flex;align-items:center;gap:9px;margin-top:auto;padding:11px 15px;border:1px solid rgba(24,23,22,.12);border-radius:999px;background:#181716;color:#fff!important;font-size:12px;font-weight:800;text-decoration:none;transition:.2s ease}
      .home-partner-card__link:hover{background:#c93431;border-color:#c93431;color:#fff!important;transform:translateY(-1px)}
      @media(max-width:950px){.home-partners__grid,.home-partners__grid[data-count="2"]{grid-template-columns:repeat(2,minmax(0,1fr))}.home-partners__grid[data-count="1"]{grid-template-columns:minmax(0,380px)}}
      @media(max-width:650px){.home-partners{padding:14px 18px 64px}.home-partners__head{margin-bottom:26px}.home-partners__grid,.home-partners__grid[data-count="1"],.home-partners__grid[data-count="2"]{grid-template-columns:1fr;gap:16px}.home-partner-card__visual{height:190px;padding:10px}.home-partner-card__body{padding:20px}.home-partner-card h3{font-size:22px}}
    `;
  }

  fetch('api/site-data.php?partners=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
    .then((response) => response.ok ? response.json() : Promise.reject())
    .then((data) => renderPartners(data?.content || {}))
    .catch(() => {});
})();
