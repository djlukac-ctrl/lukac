const finalPolishStyles = document.createElement('link');
finalPolishStyles.rel = 'stylesheet';
finalPolishStyles.href = 'assets/css/final-polish.css?v=20260909-1';
document.head.appendChild(finalPolishStyles);

const reviewStyles = document.createElement('style');
reviewStyles.textContent = `
  .review__recommendation{font:600 18px 'Space Grotesk',sans-serif;color:#181716;margin-bottom:7px}
  .review__recommendation span{color:#c93431}
  .review__date{font-size:10px;color:#8b847d;margin-bottom:22px}
  .review>p{margin-top:0}
`;
document.head.appendChild(reviewStyles);

document.querySelectorAll('.review').forEach((review) => {
  const stars = review.querySelector('.stars');
  const footer = review.querySelector('footer');
  const text = review.querySelector(':scope > p');
  if (!footer || !text) return;

  const fullName = footer.querySelector('strong')?.textContent?.trim() || 'Un client';
  const firstName = fullName.split(/\s+/)[0];
  const footerText = footer.textContent.replace(fullName, '').trim();

  const recommendation = document.createElement('div');
  recommendation.className = 'review__recommendation';
  recommendation.innerHTML = `${firstName} recommande <span>Luka C.</span>`;

  const date = document.createElement('div');
  date.className = 'review__date';
  date.textContent = footerText;

  if (stars) stars.remove();
  footer.remove();
  review.insertBefore(recommendation, text);
  review.insertBefore(date, text);
});

const homeMain = document.querySelector('body > main');
const homeHero = document.querySelector('main > .hero-modern');
const reviewsSection = document.querySelector('main > #avis');
const homePrestations = document.querySelector('main > .home-prestations');
const homeFormules = document.querySelector('main > .home-formules');

if (homeMain && homeHero && reviewsSection && homePrestations) {
  homeMain.style.display = 'flex';
  homeMain.style.flexDirection = 'column';
  homeHero.style.order = '1';
  reviewsSection.style.order = '2';
  homePrestations.style.order = '3';
  homePrestations.style.width = '100%';

  if (homeFormules) {
    homeFormules.style.order = '4';
    homeFormules.style.width = '100%';
  }

  const quoteSection = document.createElement('section');
  quoteSection.id = 'devis';
  quoteSection.className = 'home-quote reveal';
  quoteSection.style.order = '5';
  quoteSection.innerHTML = `
    <div class="home-quote__head">
      <p class="home-quote__kicker">Demande de devis</p>
      <h2>Créons une soirée <em>qui vous ressemble.</em></h2>
      <p>Parlez-moi de votre date, de votre lieu et de vos envies. Je vous proposerai une prestation pensée pour votre événement et l’ambiance que vous souhaitez créer.</p>
    </div>

    <div class="home-quote__message" aria-live="polite"></div>

    <form class="home-quote__form" method="post" action="devis.php">
      <input type="hidden" name="csrf" value="">
      <div class="home-quote__hp"><label>Entreprise<input name="company" tabindex="-1" autocomplete="off"></label></div>

      <div class="home-quote__grid">
        <div class="home-quote__field"><label>Nom et prénom *</label><input name="name" required autocomplete="name"></div>
        <div class="home-quote__field"><label>E-mail *</label><input name="email" type="email" required autocomplete="email"></div>
        <div class="home-quote__field"><label>Téléphone *</label><input name="phone" type="tel" required autocomplete="tel"></div>
        <div class="home-quote__field"><label>Quelqu’un vous a parlé de moi ?</label><input name="referral"></div>

        <fieldset class="home-quote__group home-quote__group--full">
          <legend>Adresse postale *</legend>
          <div class="home-quote__address">
            <div class="home-quote__field"><label>N° *</label><input name="address_number" required></div>
            <div class="home-quote__field"><label>Rue *</label><input name="address_street" required></div>
            <div class="home-quote__field"><label>Ville *</label><input name="address_city" required></div>
            <div class="home-quote__field"><label>Code postal *</label><input name="address_postcode" required inputmode="numeric" maxlength="5" pattern="[0-9]{5}"></div>
          </div>
        </fieldset>

        <div class="home-quote__field"><label>Type d’événement *</label><select name="event_type" required><option value=""></option><option>Mariage</option><option>Anniversaire</option><option>Baptême</option><option>Retraite</option><option>Autre</option></select></div>
        <div class="home-quote__field"><label>Date de l’événement *</label><input name="event_date" type="date" required></div>
        <div class="home-quote__field"><label>Lieu de réception / commune *</label><input name="venue" required></div>
        <div class="home-quote__field"><label>Nombre d’invités</label><input name="guest_count" type="number" min="1" max="5000"></div>
        <div class="home-quote__field"><label>Arrivée des invités *</label><input name="start_time" type="time" required></div>
        <div class="home-quote__field"><label>Fin de soirée *</label><input name="end_time" type="time" required></div>

        <fieldset class="home-quote__group home-quote__group--full">
          <legend>Quelle(s) prestation(s) souhaitez-vous ? *</legend>
          <div class="home-quote__checks">
            <label><input type="checkbox" name="services[]" value="DJ"><span>DJ</span></label>
            <label><input type="checkbox" name="services[]" value="Animations"><span>Animations</span></label>
            <label><input type="checkbox" name="services[]" value="Karaoké"><span>Karaoké</span></label>
            <label><input type="checkbox" name="services[]" value="Sonorisation de vin d’honneur et cérémonie laïque"><span>Sonorisation de vin d’honneur et cérémonie laïque</span></label>
          </div>
        </fieldset>

        <fieldset class="home-quote__group home-quote__group--full home-quote__group--formulas">
          <legend>Choisissez votre formule *</legend>
          <div class="home-quote__checks">
            ${['Essentiel','Ambiance','Expérience'].map(item => `<label><input type="checkbox" name="selections[]" value="${item}"><span>${item}</span></label>`).join('')}
          </div>
        </fieldset>

        <fieldset class="home-quote__group home-quote__group--full home-quote__group--options">
          <legend>Options complémentaires</legend>
          <div class="home-quote__checks">
            <label><input type="checkbox" name="selections[]" value="Livre d'or audio"><span>Livre d'or audio</span></label>
            <label><input type="checkbox" name="selections[]" value="Fumée lourde"><span>Fumée lourde</span></label>
            <label><input type="checkbox" name="selections[]" value="Étincelles froides — 2 jets"><span>Étincelles froides — 2 jets</span></label>
            <label><input type="checkbox" name="selections[]" value="Étincelles froides — 4 jets"><span>Étincelles froides — 4 jets</span></label>
            <label><input type="checkbox" name="selections[]" value="Éclairage mural"><span>Éclairage mural</span></label>
            <label><input type="checkbox" name="selections[]" value="Écran & projecteur"><span>Écran & projecteur</span></label>
          </div>
        </fieldset>

        <div class="home-quote__field home-quote__field--full"><label>Budget indicatif</label><select name="budget"><option value=""></option><option>Moins de 800 €</option><option>800 à 1 200 €</option><option>1 200 à 1 800 €</option><option>Plus de 1 800 €</option></select></div>
        <div class="home-quote__field home-quote__field--full"><label>Parlez-moi de votre projet</label><textarea name="message"></textarea></div>
      </div>

      <div class="home-quote__actions">
        <p>* Champs obligatoires afin que je puisse étudier votre demande dans les meilleures conditions.</p>
        <button type="submit">Envoyer ma demande <span>→</span></button>
      </div>
    </form>`;

  homeMain.appendChild(quoteSection);

  const quoteStyles = document.createElement('style');
  quoteStyles.textContent = `
    .home-quote{max-width:var(--max);width:100%;margin:0 auto;padding:88px 34px 110px;color:#181716}
    .home-quote__head{max-width:820px;margin-bottom:38px}.home-quote__kicker{margin:0 0 14px;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:#c93431;font-weight:700}
    .home-quote__head h2{font:600 clamp(42px,4.5vw,68px)/1.02 'Space Grotesk',sans-serif;letter-spacing:-.04em;margin:0 0 16px}.home-quote__head h2 em{font-style:normal;color:#c93431}.home-quote__head>p:last-child{max-width:690px;margin:0;color:#6f6862;font-size:13px;line-height:1.75}
    .home-quote__form{background:#fff;border:1px solid rgba(24,23,22,.10);border-radius:22px;padding:28px;box-shadow:0 16px 40px rgba(50,38,28,.06)}
    .home-quote__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.home-quote__field{display:grid;gap:7px}.home-quote__field--full,.home-quote__group--full{grid-column:1/-1}
    .home-quote__field label,.home-quote__group legend{font-size:11px;color:#6f6862}.home-quote__field input,.home-quote__field select,.home-quote__field textarea{width:100%;border:1px solid rgba(24,23,22,.12);border-radius:12px;background:#faf8f5;color:#181716;padding:13px 14px;outline:none;font:inherit}.home-quote__field textarea{min-height:140px;resize:vertical}
    .home-quote__field input:focus,.home-quote__field select:focus,.home-quote__field textarea:focus{border-color:#a99f95;box-shadow:0 0 0 3px rgba(201,52,49,.06)}
    .home-quote__group{border:1px solid rgba(24,23,22,.10);border-radius:16px;padding:16px;margin:0;background:#fbf9f6}.home-quote__group legend{padding:0 7px}.home-quote__address{display:grid;grid-template-columns:120px 1.5fr 1fr 150px;gap:12px}.home-quote__checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 14px}.home-quote__checks label{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid rgba(24,23,22,.10);border-radius:11px;background:#fff;color:#393531;font-size:12px;cursor:pointer}.home-quote__checks input{width:16px;height:16px;margin:1px 0 0;accent-color:#c93431}
    .home-quote__actions{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:20px}.home-quote__actions p{margin:0;color:#817a73;font-size:10px}.home-quote__actions button{border:0;border-radius:999px;background:#181716;color:#fff;padding:14px 20px;font-weight:700;cursor:pointer}.home-quote__actions button:hover{background:#c93431}.home-quote__actions button:disabled{opacity:.55;cursor:wait}
    .home-quote__message{display:none;margin-bottom:16px;padding:16px 18px;border-radius:14px;font-size:13px}.home-quote__message.is-success{display:block;background:#f2f8ee;color:#27451f;border:1px solid rgba(96,128,75,.22)}.home-quote__message.is-error{display:block;background:#fff1f0;color:#9a413d;border:1px solid rgba(168,76,71,.20)}
    .home-quote__hp{position:absolute;left:-9999px;opacity:0;pointer-events:none}
    @media(max-width:850px){.home-quote__address{grid-template-columns:1fr 1fr}}
    @media(max-width:700px){.home-quote{padding:64px 18px 80px}.home-quote__grid{grid-template-columns:1fr}.home-quote__field--full,.home-quote__group--full{grid-column:auto}.home-quote__address,.home-quote__checks{grid-template-columns:1fr}.home-quote__form{padding:20px}.home-quote__actions{align-items:stretch;flex-direction:column}.home-quote__actions button{width:100%}}
  `;
  document.head.appendChild(quoteStyles);

  document.querySelectorAll('a[href="devis.php"]').forEach(link => {
    link.setAttribute('href', '#devis');
  });

  const quoteForm = quoteSection.querySelector('.home-quote__form');
  const csrfInput = quoteForm.querySelector('input[name="csrf"]');
  const quoteMessage = quoteSection.querySelector('.home-quote__message');
  const quoteButton = quoteForm.querySelector('button[type="submit"]');

  async function loadCsrfToken() {
    try {
      const response = await fetch('devis.php', { credentials: 'same-origin' });
      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const token = doc.querySelector('input[name="csrf"]')?.value || '';
      csrfInput.value = token;
      return token;
    } catch (error) {
      return '';
    }
  }

  loadCsrfToken();

  quoteForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    quoteMessage.className = 'home-quote__message';
    quoteMessage.textContent = '';

    const servicesChecked = quoteForm.querySelectorAll('input[name="services[]"]:checked').length;
    const selectionsChecked = quoteForm.querySelectorAll('input[name="selections[]"]:checked').length;
    if (!servicesChecked || !selectionsChecked) {
      quoteMessage.className = 'home-quote__message is-error';
      quoteMessage.textContent = 'Merci de sélectionner au moins une prestation et une formule.';
      quoteMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    if (!csrfInput.value && !(await loadCsrfToken())) {
      quoteMessage.className = 'home-quote__message is-error';
      quoteMessage.textContent = 'Impossible de préparer le formulaire pour le moment. Merci de réessayer.';
      return;
    }

    quoteButton.disabled = true;
    quoteButton.textContent = 'Envoi en cours…';

    try {
      const response = await fetch('devis.php', {
        method: 'POST',
        body: new FormData(quoteForm),
        credentials: 'same-origin'
      });
      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const success = doc.querySelector('.quote-success');
      const error = doc.querySelector('.quote-error');

      if (success) {
        quoteMessage.className = 'home-quote__message is-success';
        quoteMessage.textContent = 'Merci ! Votre demande de devis a bien été envoyée. Je reviendrai vers vous dès que possible.';
        quoteForm.reset();
        await loadCsrfToken();
      } else {
        quoteMessage.className = 'home-quote__message is-error';
        quoteMessage.textContent = error?.textContent?.trim() || 'Une erreur est survenue pendant l’envoi. Merci de vérifier les informations saisies.';
      }
    } catch (error) {
      quoteMessage.className = 'home-quote__message is-error';
      quoteMessage.textContent = 'Une erreur réseau est survenue pendant l’envoi. Merci de réessayer.';
    } finally {
      quoteButton.disabled = false;
      quoteButton.innerHTML = 'Envoyer ma demande <span>→</span>';
      quoteMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
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

// Navigation dans l'ordre réel des blocs de la page.
const navItems = [
  ['Accueil', '#top'],
  ['Avis clients', '#avis'],
  ['Prestations', '#prestations'],
  ['Formules', '#formules']
];

const desktopNav = document.querySelector('.desktop-nav');
if (desktopNav) {
  desktopNav.innerHTML = navItems.map(([label, href]) => `<a href="${href}">${label}</a>`).join('');
}

if (mobileNav) {
  mobileNav.innerHTML = navItems.map(([label, href]) => `<a href="${href}">${label}</a>`).join('') + '<a class="mobile-nav__cta" href="#devis">Demander un devis</a>';
  mobileNav.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
      mobileNav.hidden = true;
    });
  });
}

// Réseaux sociaux Luka C dans le pied de page.
const footer = document.querySelector('.site-footer');
if (footer && !footer.querySelector('.site-footer__socials')) {
  const socials = document.createElement('div');
  socials.className = 'site-footer__socials';
  socials.setAttribute('aria-label', 'Réseaux sociaux');
  socials.innerHTML = `
    <a href="https://www.facebook.com/profile.php?id=100093212362664" target="_blank" rel="noopener noreferrer">Facebook</a>
    <a href="https://www.instagram.com/djlukac" target="_blank" rel="noopener noreferrer">Instagram</a>
    <a href="https://www.tiktok.com/@djluka.c" target="_blank" rel="noopener noreferrer">TikTok</a>
  `;
  const footerSmall = footer.querySelector('small');
  if (footerSmall) footer.insertBefore(socials, footerSmall);
  else footer.appendChild(socials);
}

// Réseaux sociaux dans le header, sous forme d'icônes discrètes.
const siteHeader = document.querySelector('.site-header');
if (siteHeader && !siteHeader.querySelector('.header-socials')) {
  const headerSocials = document.createElement('div');
  headerSocials.className = 'header-socials';
  headerSocials.setAttribute('aria-label', 'Réseaux sociaux');
  headerSocials.innerHTML = `
    <a href="https://www.facebook.com/profile.php?id=100093212362664" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 22v-9h3l.45-3.5H13.5V7.25c0-1.01.28-1.7 1.73-1.7H17V2.42c-.31-.04-1.38-.13-2.62-.13-2.6 0-4.38 1.59-4.38 4.5V9.5H7v3.5h3v9h3.5z"/></svg>
    </a>
    <a href="https://www.instagram.com/djlukac" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7zm5 3.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 0 1 12 7.5zm0 2A2.5 2.5 0 1 0 14.5 12 2.5 2.5 0 0 0 12 9.5zm5.25-3.2a1.05 1.05 0 1 1-1.05 1.05 1.05 1.05 0 0 1 1.05-1.05z"/></svg>
    </a>
    <a href="https://www.tiktok.com/@djluka.c" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.2 2h3.1c.2 1.6 1.1 2.9 2.7 3.7v3.1a7.8 7.8 0 0 1-2.8-.8v6.4a6.4 6.4 0 1 1-5.5-6.3v3.2a3.3 3.3 0 1 0 2.5 3.1V2z"/></svg>
    </a>
  `;
  const headerCta = siteHeader.querySelector('.header-cta');
  if (headerCta) siteHeader.insertBefore(headerSocials, headerCta);
  else siteHeader.appendChild(headerSocials);
}

const socialStyles = document.createElement('style');
socialStyles.textContent = `
  .site-footer__socials{display:flex;flex-wrap:wrap;gap:10px 18px;margin:18px 0 22px}
  .site-footer__socials a{font-size:12px;font-weight:600;color:#d4cec7!important}
  .site-footer__socials a:hover{color:#fff!important}
  .header-socials{display:flex;align-items:center;gap:6px;margin-left:auto;margin-right:10px}
  .header-socials a{display:grid;place-items:center;width:34px;height:34px;border:1px solid rgba(24,23,22,.12);border-radius:50%;background:rgba(255,255,255,.58);color:#181716;transition:background .18s ease,color .18s ease,border-color .18s ease,transform .18s ease}
  .header-socials a:hover{background:#c93431;color:#fff;border-color:#c93431;transform:translateY(-1px)}
  .header-socials svg{width:16px;height:16px;fill:currentColor}
  @media(max-width:1180px){.header-socials{display:none}}
`;
document.head.appendChild(socialStyles);

/* Calendrier de devis relié aux dates réservées */
(() => {
  const dateInputs = Array.from(document.querySelectorAll('input[name="event_date"]'));
  if (!dateInputs.length) return;

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  const weekdayNames = ['L','M','M','J','V','S','D'];

  const style = document.createElement('style');
  style.textContent = `
    .lukac-date-picker{position:relative}
    .lukac-date-trigger{width:100%;min-height:48px;display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(24,23,22,.12);border-radius:12px;background:#faf8f5;color:#181716;padding:13px 14px;font:inherit;text-align:left;cursor:pointer;transition:border-color .18s ease,box-shadow .18s ease,background .18s ease}
    .lukac-date-trigger:hover,.lukac-date-trigger[aria-expanded="true"]{border-color:#a99f95;box-shadow:0 0 0 3px rgba(201,52,49,.06);background:#fff}
    .lukac-date-trigger__icon{font-size:18px;line-height:1;color:#c93431}.lukac-date-trigger__placeholder{color:#8a837c}
    .lukac-calendar{position:absolute;left:0;top:calc(100% + 8px);z-index:80;width:min(360px,calc(100vw - 44px));padding:18px;border:1px solid rgba(24,23,22,.12);border-radius:18px;background:#fff;color:#181716;box-shadow:0 24px 60px rgba(43,34,28,.18)}
    .lukac-calendar[hidden]{display:none!important}.lukac-calendar__head{display:grid;grid-template-columns:38px 1fr 38px;gap:8px;align-items:center;margin-bottom:14px}
    .lukac-calendar__title{text-align:center;font:600 17px 'Space Grotesk',sans-serif}.lukac-calendar__nav{width:38px;height:38px;display:grid;place-items:center;border:1px solid rgba(24,23,22,.10);border-radius:50%;background:#faf8f5;color:#181716;cursor:pointer;font-size:18px}
    .lukac-calendar__nav:hover:not(:disabled){background:#181716;color:#fff}.lukac-calendar__nav:disabled{opacity:.25;cursor:not-allowed}
    .lukac-calendar__status{margin:-4px 0 13px;text-align:center;font-size:10px;font-weight:700;letter-spacing:.05em;color:#817a73}
    .lukac-calendar__weekdays,.lukac-calendar__days{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}.lukac-calendar__weekdays span{display:grid;place-items:center;height:26px;font-size:9px;font-weight:800;color:#9a938c}
    .lukac-calendar__empty{height:39px}.lukac-calendar__day{height:39px;border:0;border-radius:10px;background:#f8f5f1;color:#26221f;font-size:12px;font-weight:700;cursor:pointer;transition:transform .15s ease,background .15s ease,color .15s ease}
    .lukac-calendar__day:hover{background:#181716;color:#fff;transform:translateY(-1px)}.lukac-calendar__day.is-selected{background:#c93431;color:#fff;box-shadow:0 5px 14px rgba(201,52,49,.22)}
    .lukac-calendar__day--unavailable{height:39px;display:grid;place-items:center;border-radius:10px;background:#f5f2ee;color:#c1bbb5;font-size:12px;font-weight:600;text-decoration:line-through;cursor:not-allowed}
    .lukac-calendar__day--reserved{background:#f6e9e8;color:#bf7772}.lukac-calendar__day--today{outline:1px solid rgba(201,52,49,.45);outline-offset:-2px}
    .lukac-calendar__legend{display:flex;gap:15px;flex-wrap:wrap;margin-top:14px;padding-top:13px;border-top:1px solid rgba(24,23,22,.08);font-size:10px;color:#817a73}
    .lukac-calendar__legend span{display:inline-flex;align-items:center;gap:6px}.lukac-calendar__legend i{width:8px;height:8px;border-radius:50%;background:#181716}.lukac-calendar__legend .off i{background:#d7a09c}
    .lukac-date-feedback{min-height:18px;margin-top:5px;font-size:11px;line-height:1.45;color:#817a73}.lukac-date-feedback.is-ok{color:#557347;font-weight:600}.lukac-date-feedback.is-error{color:#b24540;font-weight:600}
    @media(max-width:620px){.lukac-calendar{position:fixed;left:14px;right:14px;top:50%;width:auto;max-width:390px;margin:auto;transform:translateY(-50%);padding:16px}.lukac-calendar__day,.lukac-calendar__day--unavailable{height:42px}}
  `;
  document.head.appendChild(style);

  const toIso = (year, month, day) => `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
  const parseIso = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
    return match ? {year:Number(match[1]),month:Number(match[2]),day:Number(match[3])} : null;
  };
  const formatFr = (value) => {
    const parsed = parseIso(value);
    if (!parsed) return '';
    return new Intl.DateTimeFormat('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(parsed.year,parsed.month-1,parsed.day));
  };
  const isPast = (year,month,day) => {
    const d = new Date(year,month-1,day);
    d.setHours(0,0,0,0);
    return d < today;
  };
  const monthIndex = (year,month) => year * 12 + (month - 1);

  function setupPicker(input, reservedDates) {
    const reserved = new Set((reservedDates || []).map(String));
    const originalValue = input.value;
    const selected = parseIso(originalValue);
    let viewYear = selected?.year || today.getFullYear();
    let viewMonth = selected?.month || (today.getMonth() + 1);
    const minIndex = monthIndex(today.getFullYear(), today.getMonth() + 1);

    input.type = 'hidden';
    input.removeAttribute('required');

    const picker = document.createElement('div');
    picker.className = 'lukac-date-picker';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'lukac-date-trigger';
    trigger.setAttribute('aria-expanded','false');
    const feedback = document.createElement('div');
    feedback.className = 'lukac-date-feedback';
    feedback.setAttribute('aria-live','polite');
    const calendar = document.createElement('div');
    calendar.className = 'lukac-calendar';
    calendar.hidden = true;
    picker.append(trigger,calendar,feedback);
    input.insertAdjacentElement('afterend',picker);

    const updateTrigger = () => {
      trigger.innerHTML = input.value
        ? `<span>${formatFr(input.value)}</span><span class="lukac-date-trigger__icon">▣</span>`
        : '<span class="lukac-date-trigger__placeholder">Choisir une date disponible</span><span class="lukac-date-trigger__icon">▣</span>';
    };

    function monthReservedCount() {
      const prefix = `${viewYear}-${String(viewMonth).padStart(2,'0')}-`;
      return Array.from(reserved).filter(date => date.startsWith(prefix)).length;
    }

    function render() {
      const firstDay = new Date(viewYear,viewMonth-1,1);
      const daysInMonth = new Date(viewYear,viewMonth,0).getDate();
      const mondayOffset = (firstDay.getDay()+6)%7;
      const selectedDate = parseIso(input.value);
      const reservedCount = monthReservedCount();

      calendar.innerHTML = `
        <div class="lukac-calendar__head">
          <button type="button" class="lukac-calendar__nav" data-cal-prev aria-label="Mois précédent">‹</button>
          <div class="lukac-calendar__title">${monthNames[viewMonth-1]} ${viewYear}</div>
          <button type="button" class="lukac-calendar__nav" data-cal-next aria-label="Mois suivant">›</button>
        </div>
        <div class="lukac-calendar__status">${reservedCount ? `${reservedCount} date${reservedCount > 1 ? 's' : ''} déjà réservée${reservedCount > 1 ? 's' : ''} ce mois-ci` : 'Toutes les dates futures sont disponibles'}</div>
        <div class="lukac-calendar__weekdays">${weekdayNames.map(d=>`<span>${d}</span>`).join('')}</div>
        <div class="lukac-calendar__days"></div>
        <div class="lukac-calendar__legend"><span><i></i> Disponible</span><span class="off"><i></i> Réservée</span></div>
      `;

      const days = calendar.querySelector('.lukac-calendar__days');
      for(let i=0;i<mondayOffset;i++){
        const empty=document.createElement('span');
        empty.className='lukac-calendar__empty';
        days.appendChild(empty);
      }

      for(let day=1;day<=daysInMonth;day++){
        const iso=toIso(viewYear,viewMonth,day);
        const past=isPast(viewYear,viewMonth,day);
        const isReserved=reserved.has(iso);
        const available=!past && !isReserved;
        const isSelected=selectedDate && selectedDate.year===viewYear && selectedDate.month===viewMonth && selectedDate.day===day;
        const isToday=today.getFullYear()===viewYear && today.getMonth()+1===viewMonth && today.getDate()===day;

        if(available){
          const button=document.createElement('button');
          button.type='button';
          button.className='lukac-calendar__day';
          if(isSelected) button.classList.add('is-selected');
          if(isToday) button.classList.add('lukac-calendar__day--today');
          button.textContent=String(day);
          button.setAttribute('aria-label',formatFr(iso));
          button.addEventListener('click',()=>{
            input.value=iso;
            updateTrigger();
            feedback.className='lukac-date-feedback is-ok';
            feedback.textContent='✓ Cette date est disponible.';
            calendar.hidden=true;
            trigger.setAttribute('aria-expanded','false');
            input.dispatchEvent(new Event('change',{bubbles:true}));
          });
          days.appendChild(button);
        } else {
          const off=document.createElement('span');
          off.className='lukac-calendar__day--unavailable';
          if(isReserved) off.classList.add('lukac-calendar__day--reserved');
          if(isToday) off.classList.add('lukac-calendar__day--today');
          off.textContent=String(day);
          off.title=past ? 'Date passée' : 'Déjà réservée';
          days.appendChild(off);
        }
      }

      const prev=calendar.querySelector('[data-cal-prev]');
      const next=calendar.querySelector('[data-cal-next]');
      prev.disabled=monthIndex(viewYear,viewMonth)<=minIndex;

      prev.addEventListener('click',(event)=>{
        event.preventDefault(); event.stopPropagation();
        if(viewMonth===1){viewMonth=12;viewYear--;}else viewMonth--;
        render();
      });
      next.addEventListener('click',(event)=>{
        event.preventDefault(); event.stopPropagation();
        if(viewMonth===12){viewMonth=1;viewYear++;}else viewMonth++;
        render();
      });
    }

    trigger.addEventListener('click',()=>{
      const opening=calendar.hidden;
      document.querySelectorAll('.lukac-calendar').forEach(other=>{if(other!==calendar) other.hidden=true;});
      calendar.hidden=!opening;
      trigger.setAttribute('aria-expanded',String(opening));
      if(opening) render();
    });

    document.addEventListener('click',(event)=>{
      if(!picker.contains(event.target)){
        calendar.hidden=true;
        trigger.setAttribute('aria-expanded','false');
      }
    });
    document.addEventListener('keydown',(event)=>{
      if(event.key==='Escape'){
        calendar.hidden=true;
        trigger.setAttribute('aria-expanded','false');
      }
    });

    const form=input.closest('form');
    form?.addEventListener('submit',(event)=>{
      if(!input.value){
        event.preventDefault();
        event.stopImmediatePropagation();
        feedback.className='lukac-date-feedback is-error';
        feedback.textContent='Merci de choisir une date disponible dans le calendrier.';
        picker.scrollIntoView({behavior:'smooth',block:'center'});
        trigger.focus();
      }
    },true);

    if(originalValue){
      const parsed=parseIso(originalValue);
      if(parsed && !isPast(parsed.year,parsed.month,parsed.day) && !reserved.has(originalValue)){
        feedback.className='lukac-date-feedback is-ok';
        feedback.textContent='✓ Cette date est disponible.';
      }else{
        input.value='';
        feedback.className='lukac-date-feedback is-error';
        feedback.textContent='La date précédemment choisie n’est plus disponible.';
      }
    }
    updateTrigger();
  }

  fetch('api/site-data.php?reserved_calendar='+Date.now(),{credentials:'same-origin',cache:'no-store'})
    .then(response=>response.ok ? response.json() : Promise.reject())
    .then(data=>dateInputs.forEach(input=>setupPicker(input,data?.reserved_dates || [])))
    .catch(()=>{});
})();
