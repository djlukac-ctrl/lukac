(() => {
  // Optimisation légère du chargement des images sans toucher aux fichiers sources.
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

  // Met en évidence le lien du menu correspondant à la zone visible.
  const desktopNav = document.querySelector('.desktop-nav');
  const navLinks = desktopNav ? [...desktopNav.querySelectorAll('a[href^="#"]')] : [];
  const trackedSections = navLinks.map((link) => {
    const href = link.getAttribute('href');
    const target = href === '#top' ? document.querySelector('.hero-modern') : document.querySelector(href);
    return target ? { link, target } : null;
  }).filter(Boolean);

  const updateActiveNav = () => {
    if (!trackedSections.length) return;
    const marker = Math.min(window.innerHeight * 0.35, 260);
    let active = trackedSections[0];

    trackedSections.forEach((item) => {
      if (item.target.getBoundingClientRect().top <= marker) active = item;
    });

    navLinks.forEach((link) => link.classList.toggle('is-active', link === active.link));
  };

  updateActiveNav();
  window.addEventListener('scroll', updateActiveNav, { passive: true });
  window.addEventListener('resize', updateActiveNav);

  // Petit bouton retour en haut, visible uniquement après avoir descendu la page.
  const backToTop = document.createElement('button');
  backToTop.type = 'button';
  backToTop.className = 'back-to-top';
  backToTop.setAttribute('aria-label', 'Retour en haut');
  backToTop.setAttribute('title', 'Retour en haut');
  backToTop.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>';
  document.body.appendChild(backToTop);

  const updateBackToTop = () => {
    backToTop.classList.toggle('is-visible', window.scrollY > 700);
  };

  updateBackToTop();
  window.addEventListener('scroll', updateBackToTop, { passive: true });
  backToTop.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();
