(() => {
  // Chargement léger des images.
  document.querySelectorAll('img').forEach((img) => {
    img.decoding = 'async';
    const isHero = img.matches('.hero-modern__frame img');
    const isHeaderLogo = img.matches('.site-header .brand__logo');

    if (isHero) {
      img.loading = 'eager';
      img.fetchPriority = 'high';
    } else if (!isHeaderLogo) {
      img.loading = 'lazy';
    }
  });

  // Les animations ont été supprimées : tout le contenu reste visible immédiatement.
  document.querySelectorAll('.reveal, .scroll-reveal').forEach((el) => {
    el.classList.add('is-visible');
  });

  // Restaure les images CMS des options/packs sans animation ni observation du scroll.
  const restorePackOptionImages = async () => {
    const targets = [...document.querySelectorAll('.signature-card__image[data-cms-image], .option-card__image[data-cms-image]')];
    if (!targets.length) return;

    try {
      const response = await fetch('api/site-data.php?t=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store'
      });
      if (!response.ok) return;

      const data = await response.json();
      const content = data && data.content ? data.content : {};

      targets.forEach((el) => {
        const key = el.dataset.cmsImage;
        const path = key && content[key] ? String(content[key]).trim() : '';
        if (!path) return;

        const x = Math.max(0, Math.min(100, Number(content[`${key}.position_x`] ?? 50) || 50));
        const y = Math.max(0, Math.min(100, Number(content[`${key}.position_y`] ?? 50) || 50));

        el.style.backgroundImage = `url("${path.replace(/"/g, '%22')}")`;
        el.style.backgroundSize = 'cover';
        el.style.backgroundPosition = `${x}% ${y}%`;
        el.classList.add('has-cms-image');
      });
    } catch (_) {
      // Le site reste utilisable même si l'API CMS est momentanément indisponible.
    }
  };

  restorePackOptionImages();
})();
