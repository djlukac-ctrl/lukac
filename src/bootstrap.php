<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Paris');

const APP_ROOT = __DIR__ . '/..';
const STORAGE_DIR = APP_ROOT . '/storage';

if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0775, true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('lukac_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $info) {
        if (($info['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . STORAGE_DIR . '/lukac.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec('CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS quotes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        status TEXT NOT NULL DEFAULT "new",
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT,
        event_type TEXT NOT NULL,
        event_date TEXT,
        venue TEXT,
        guest_count INTEGER,
        budget TEXT,
        message TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    ensure_column($pdo, 'quotes', 'postal_address', 'TEXT');
    ensure_column($pdo, 'quotes', 'referral', 'TEXT');
    ensure_column($pdo, 'quotes', 'start_time', 'TEXT');
    ensure_column($pdo, 'quotes', 'end_time', 'TEXT');
    ensure_column($pdo, 'quotes', 'services', 'TEXT');
    ensure_column($pdo, 'quotes', 'selections', 'TEXT');

    $pdo->exec('CREATE TABLE IF NOT EXISTS availability (
        year INTEGER NOT NULL,
        month INTEGER NOT NULL,
        status TEXT NOT NULL,
        note TEXT,
        PRIMARY KEY (year, month)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS content (
        content_key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_name TEXT NOT NULL,
        review_text TEXT NOT NULL,
        rating INTEGER NOT NULL DEFAULT 5,
        review_date TEXT,
        published INTEGER NOT NULL DEFAULT 1,
        display_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS site_visitors (
        visit_date TEXT NOT NULL,
        visitor_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (visit_date, visitor_hash)
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_site_visitors_date ON site_visitors(visit_date)');

    seed_defaults($pdo);
    seed_reviews($pdo);
    return $pdo;
}

function default_content(): array
{
    return [
        'home.hero_line1' => 'Une soirée à votre image.',
        'home.hero_line2' => 'Une ambiance qui vous ressemble.',
        'home.hero_lead' => 'Mariages, anniversaires, soirées privées et événements professionnels : une prestation construite autour de vos invités, de votre lieu et de l’énergie du moment.',

        'prestations.hero_title' => 'Des prestations pensées',
        'prestations.hero_highlight' => 'pour votre événement.',
        'prestations.hero_intro' => 'Chaque soirée est unique. Je vous propose des solutions sur mesure pour créer une ambiance qui vous ressemble, du début à la fin.',
        'prestations.dj.title' => 'DJ',
        'prestations.dj.desc' => 'Une ambiance sur mesure du début à la fin. Je m’adapte à votre événement et à vos invités pour créer une soirée dynamique, fluide et inoubliable.',
        'prestations.dj.items' => "Mariages, anniversaires et soirées privées\nProgrammation musicale préparée avec vous\nAdaptation en direct selon l’ambiance",
        'prestations.animations.title' => 'Animations interactives',
        'prestations.animations.desc' => 'Faites participer vos invités et créez de vrais temps forts au cours de la soirée, sans casser le rythme ni surcharger l’animation.',
        'prestations.animations.items' => "Karaoké\nQuiz musical et blind test\nJeux et animations personnalisées selon vos envies",
        'prestations.cocktail.title' => 'Sonorisation de vin d’honneur',
        'prestations.cocktail.desc' => 'Je m’occupe de la diffusion musicale pour accompagner vos moments clés avec élégance, tout en gardant un volume adapté aux échanges entre invités.',
        'prestations.cocktail.items' => "Musique adaptée au moment\nVolume maîtrisé\nInstallation propre et professionnelle",

        'formules.hero_title' => 'Des formules sur mesure',
        'formules.hero_highlight' => 'pour votre événement.',
        'formules.hero_intro' => 'Que ce soit pour une petite soirée ou un événement plus important, je vous propose des solutions complètes et personnalisées.',
        'formules.essentiel.title' => 'Essentiel',
        'formules.essentiel.intro' => 'L’essentiel pour une soirée réussie.',
        'formules.essentiel.items' => "Sonorisation professionnelle adaptée\nEspace DJ élégant avec façade blanche\nÉclairage dynamique de la piste de danse\nMicros sans fil à disposition",
        'formules.ambiance.title' => 'Ambiance',
        'formules.ambiance.intro' => 'Plus de lumière, plus de dynamisme, plus d’ambiance.',
        'formules.ambiance.items' => "Sonorisation professionnelle\nEspace DJ élégant avec façade blanche\nMise en lumière renforcée de la piste de danse\nÉclairages mobiles et dynamiques au rythme de la musique\nTotems lumineux pour une installation plus immersive\nEffet de fumée pour sublimer les jeux de lumière\nMicros sans fil à disposition",
        'formules.experience.title' => 'Expérience',
        'formules.experience.intro' => 'Une mise en scène lumineuse complète pour une soirée inoubliable.',
        'formules.experience.items' => "Sonorisation professionnelle\nEspace DJ élégant avec façade blanche\nMise en lumière complète de la piste de danse\nÉclairages mobiles et effets dynamiques\nTotems lumineux pour une installation immersive\nEffets de fumée verticaux pour les moments forts\nEffets LED supplémentaires pour enrichir l’ambiance lumineuse\nMicros sans fil à disposition",
        'formules.signature1.title' => 'Pack Instant Magique',
        'formules.signature1.desc' => 'Sublimez votre ouverture de bal avec une mise en scène élégante et spectaculaire.',
        'formules.signature1.items' => "Fumée lourde au sol\n2 fontaines d’étincelles froides\nDéclenchement synchronisé au moment choisi",
        'formules.signature2.title' => 'Pack Instant Magique Signature',
        'formules.signature2.desc' => 'Pour celles et ceux qui veulent aller encore plus loin et créer un véritable moment “waouh”.',
        'formules.signature2.items' => "Fumée lourde au sol\n4 fontaines d’étincelles froides\nMise en scène renforcée autour de la piste\nDéclenchement synchronisé au moment choisi",
        'options.fumee.title' => 'Fumée lourde',
        'options.fumee.desc' => 'Un nuage au sol élégant pour sublimer votre ouverture de bal et créer un moment encore plus magique.',
        'options.etincelles.title' => 'Étincelles froides',
        'options.etincelles.desc' => 'Des gerbes d’étincelles spectaculaires pour accompagner votre entrée, votre ouverture de bal ou un temps fort.',
        'options.eclairage.title' => 'Éclairage mural',
        'options.eclairage.desc' => 'Donnez une nouvelle dimension à votre salle grâce à un éclairage d’ambiance réparti autour de l’espace.',
        'options.ecran.title' => 'Écran & projecteur',
        'options.ecran.desc' => 'Diffusez vos vidéos, diaporamas, surprises ou souvenirs directement pendant votre événement.',
    ];
}

function seed_defaults(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO content(content_key, value) VALUES(:k, :v)');
    foreach (default_content() as $key => $value) {
        $stmt->execute([':k' => $key, ':v' => $value]);
    }

    $defaults = [
        2026 => [7 => 'closed', 8 => 'closed', 9 => 'limited', 10 => 'limited', 11 => 'limited', 12 => 'open'],
        2027 => [1 => 'open', 2 => 'open', 3 => 'open', 4 => 'open', 5 => 'limited', 6 => 'closed', 7 => 'limited', 8 => 'limited', 9 => 'open', 10 => 'open', 11 => 'open', 12 => 'open'],
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO availability(year, month, status) VALUES(:y, :m, :s)');
    foreach ($defaults as $year => $months) {
        foreach ($months as $month => $status) {
            $stmt->execute([':y' => $year, ':m' => $month, ':s' => $status]);
        }
    }
}

function seed_reviews(PDO $pdo): void
{
    if ((int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn() > 0) {
        return;
    }

    $reviews = [
        ['Charlotte Targa', 'Une ambiance qui a mis tout le monde d’accord, beaucoup d’énergie et des transitions impeccables. Une soirée inoubliable, avec une recommandation à 200 %.', 5, '2025-09-22', 1],
        ['Laura Derancy', 'Une prestation de mariage dynamique, à l’écoute des demandes et avec une excellente animation. Une recommandation à 100 %.', 5, '2025-05-25', 2],
        ['Cyrille Lemarquis', 'Une très bonne soirée, parfaitement animée, avec une musique au top. Une prestation chaleureusement recommandée pour de futurs événements.', 5, '2025-03-10', 3],
    ];

    $stmt = $pdo->prepare('INSERT INTO reviews(client_name, review_text, rating, review_date, published, display_order) VALUES(:name,:text,:rating,:date,1,:sort)');
    foreach ($reviews as [$name, $text, $rating, $date, $sort]) {
        $stmt->execute([':name'=>$name, ':text'=>$text, ':rating'=>$rating, ':date'=>$date, ':sort'=>$sort]);
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): void
{
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page puis réessayez.');
    }
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function admin_exists(): bool
{
    return (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
}

function site_content(): array
{
    $rows = db()->query('SELECT content_key, value FROM content')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[$row['content_key']] = $row['value'];
    }
    return $out;
}

function quote_status_label(string $status): string
{
    return [
        'new' => 'Nouveau',
        'read' => 'Lu',
        'contacted' => 'Contacté',
        'booked' => 'Réservé',
        'archived' => 'Archivé',
    ][$status] ?? $status;
}
