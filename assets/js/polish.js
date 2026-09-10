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

  // Apparitions discrètes au scroll, une seule fois par élément.
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealSelectors = [
    '.section-heading',
    '.review',
    '.availability__year',
    '.prestations-section__head',
    '.prestation-card',
    '.formules-section__head',
    '.formule-card',
    '.signature-card',
    '.option-card',
    '.home-quote__intro',
    '.home-quote__form'
  ].join(',');

  const prepareRevealElements = () => {
    const elements = [...document.querySelectorAll(revealSelectors)]
      .filter((el) => !el.dataset.scrollRevealReady);

    elements.forEach((el, index) => {
      el.dataset.scrollRevealReady = '1';
      el.classList.add('scroll-reveal');
      el.classList.add(`scroll-reveal--delay-${(index % 4) + 1}`);

      if (reduceMotion) {
        el.classList.add('is-visible');
      }
    });

    return elements;
  };

  const initialHeroReveals = [...document.querySelectorAll('.hero-modern .reveal')];
  if (reduceMotion) {
    initialHeroReveals.forEach((el) => el.classList.add('is-visible'));
  } else {
    requestAnimationFrame(() => {
      initialHeroReveals.forEach((el) => el.classList.add('is-visible'));
    });
  }

  if (!reduceMotion && 'IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, {
      threshold: 0.12,
      rootMargin: '0px 0px -7% 0px'
    });

    const observePrepared = () => {
      prepareRevealElements().forEach((el) => revealObserver.observe(el));
    };

    observePrepared();

    // Certaines zones (formules/devis) sont injectées dynamiquement par main.js.
    const mutationObserver = new MutationObserver(() => observePrepared());
    mutationObserver.observe(document.body, { childList: true, subtree: true });
  } else {
    prepareRevealElements().forEach((el) => el.classList.add('is-visible'));
  }

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

  // Le formulaire de devis de l'accueil est injecté par main.js : charge le correctif après lui.
  const quoteGuard = document.createElement('script');
  quoteGuard.src = 'assets/js/quote-selection-guard.js?v=20260910-1';
  quoteGuard.defer = true;
  document.body.appendChild(quoteGuard);
})();
