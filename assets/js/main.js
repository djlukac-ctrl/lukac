const finalPolishStyles = document.createElement('link');
finalPolishStyles.rel = 'stylesheet';
finalPolishStyles.href = 'assets/css/final-polish.css?v=20260909-1';
document.head.appendChild(finalPolishStyles);

function ensureFormulesLink(container) {
  if (!container || container.querySelector('a[href="formules.html"]')) return;
  const link = document.createElement('a');
  link.href = 'formules.html';
  link.textContent = 'Formules';
  const prestationsLink = container.querySelector('a[href="prestations.html"]');
  if (prestationsLink && prestationsLink.nextSibling) {
    container.insertBefore(link, prestationsLink.nextSibling);
  } else if (prestationsLink) {
    prestationsLink.insertAdjacentElement('afterend', link);
  } else {
    container.prepend(link);
  }
}

ensureFormulesLink(document.querySelector('.desktop-nav'));
ensureFormulesLink(document.querySelector('.mobile-nav'));
ensureFormulesLink(document.querySelector('.site-footer__links'));

const homePrestations = document.querySelector('main > .home-prestations');
const availabilitySection = document.querySelector('main > #disponibilites');
if (homePrestations && availabilitySection) {
  availabilitySection.insertAdjacentElement('afterend', homePrestations);
}

const menuToggle = document.querySelector('.menu-toggle');
const mobileNav = document.querySelector('.mobile-nav');

if (menuToggle && mobileNav) {
  menuToggle.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!open));
    mobileNav.hidden = open;
  });

  mobileNav.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      menuToggle.setAttribute('aria-expanded', 'false');
      mobileNav.hidden = true;
    });
  });
}

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.12 }
);

document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
