<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Paris');

// Protections HTTP communes aux pages PHP.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');

const APP_ROOT = __DIR__ . '/..';
const STORAGE_DIR = APP_ROOT . '/storage';

if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0775, true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
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
    ensure_column($pdo, 'quotes', 'followed_up_at', 'TEXT');
    ensure_column($pdo, 'quotes', 'closure_reason', 'TEXT');
    ensure_column($pdo, 'quotes', 'closure_note', 'TEXT');

    $pdo->exec('CREATE TABLE IF NOT EXISTS prestations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source_quote_id INTEGER UNIQUE,
        client_name TEXT NOT NULL,
        client_email TEXT,
        client_phone TEXT,
        event_type TEXT,
        event_date TEXT,
        venue TEXT,
        start_time TEXT,
        end_time TEXT,
        services TEXT,
        selections TEXT,
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(source_quote_id) REFERENCES quotes(id) ON DELETE CASCADE
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_prestations_event_date ON prestations(event_date)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_prestations_source_quote ON prestations(source_quote_id)');
    ensure_column($pdo, 'prestations', 'total_amount_cents', 'INTEGER');
    ensure_column($pdo, 'prestations', 'deposit_amount_cents', 'INTEGER');
    ensure_column($pdo, 'prestations', 'contract_signed', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'deposit_received', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'contract_signed_at', 'TEXT');
    ensure_column($pdo, 'prestations', 'deposit_received_at', 'TEXT');
    ensure_column($pdo, 'prestations', 'schedule_confirmed', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'venue_confirmed', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'questionnaire_received', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'music_received', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'equipment_confirmed', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'final_contact_done', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'balance_received', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'prestations', 'client_meeting_status', "TEXT NOT NULL DEFAULT 'todo'");
    ensure_column($pdo, 'prestations', 'client_meeting_at', 'TEXT');
    ensure_column($pdo, 'prestations', 'client_meeting_notes', 'TEXT');

    // Les prestations peuvent désormais être ajoutées manuellement, sans demande de devis source.
    $prestationColumns = $pdo->query('PRAGMA table_info(prestations)')->fetchAll();
    foreach ($prestationColumns as $prestationColumn) {
        if (($prestationColumn['name'] ?? '') === 'source_quote_id' && (int)($prestationColumn['notnull'] ?? 0) === 1) {
            $pdo->beginTransaction();
            try {
                $pdo->exec('CREATE TABLE prestations_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    source_quote_id INTEGER UNIQUE,
                    client_name TEXT NOT NULL,
                    client_email TEXT,
                    client_phone TEXT,
                    event_type TEXT,
                    event_date TEXT,
                    venue TEXT,
                    start_time TEXT,
                    end_time TEXT,
                    services TEXT,
                    selections TEXT,
                    notes TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    total_amount_cents INTEGER,
                    deposit_amount_cents INTEGER,
                    contract_signed INTEGER NOT NULL DEFAULT 0,
                    deposit_received INTEGER NOT NULL DEFAULT 0,
                    contract_signed_at TEXT,
                    deposit_received_at TEXT,
                    schedule_confirmed INTEGER NOT NULL DEFAULT 0,
                    venue_confirmed INTEGER NOT NULL DEFAULT 0,
                    questionnaire_received INTEGER NOT NULL DEFAULT 0,
                    music_received INTEGER NOT NULL DEFAULT 0,
                    equipment_confirmed INTEGER NOT NULL DEFAULT 0,
                    final_contact_done INTEGER NOT NULL DEFAULT 0,
                    balance_received INTEGER NOT NULL DEFAULT 0,
                    client_meeting_status TEXT NOT NULL DEFAULT "todo",
                    client_meeting_at TEXT,
                    client_meeting_notes TEXT,
                    FOREIGN KEY(source_quote_id) REFERENCES quotes(id) ON DELETE CASCADE
                )');
                $pdo->exec('
                    INSERT INTO prestations_new (
                        id, source_quote_id, client_name, client_email, client_phone,
                        event_type, event_date, venue, start_time, end_time, services,
                        selections, notes, created_at, updated_at, total_amount_cents,
                        deposit_amount_cents, contract_signed, deposit_received,
                        contract_signed_at, deposit_received_at, schedule_confirmed,
                        venue_confirmed, questionnaire_received, music_received,
                        equipment_confirmed, final_contact_done, balance_received,
                        client_meeting_status, client_meeting_at, client_meeting_notes
                    )
                    SELECT
                        id, source_quote_id, client_name, client_email, client_phone,
                        event_type, event_date, venue, start_time, end_time, services,
                        selections, notes, created_at, updated_at, total_amount_cents,
                        deposit_amount_cents, contract_signed, deposit_received,
                        contract_signed_at, deposit_received_at, schedule_confirmed,
                        venue_confirmed, questionnaire_received, music_received,
                        equipment_confirmed, final_contact_done, balance_received,
                        client_meeting_status, client_meeting_at, client_meeting_notes
                    FROM prestations
                ');
                $pdo->exec('DROP TABLE prestations');
                $pdo->exec('ALTER TABLE prestations_new RENAME TO prestations');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_prestations_event_date ON prestations(event_date)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_prestations_source_quote ON prestations(source_quote_id)');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            break;
        }
    }

    $pdo->exec("
        INSERT OR IGNORE INTO prestations(
            source_quote_id, client_name, client_email, client_phone,
            event_type, event_date, venue, start_time, end_time,
            services, selections, created_at, updated_at
        )
        SELECT
            id, name, email, phone,
            event_type, event_date, venue, start_time, end_time,
            services, selections, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        FROM quotes
        WHERE status='accepted'
    ");

    $pdo->exec('CREATE TABLE IF NOT EXISTS availability (
        year INTEGER NOT NULL,
        month INTEGER NOT NULL,
        status TEXT NOT NULL,
        note TEXT,
        PRIMARY KEY (year, month)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS reserved_dates (
        event_date TEXT PRIMARY KEY,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS commercial_quotes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quote_number TEXT NOT NULL UNIQUE,
        source_quote_id INTEGER,
        status TEXT NOT NULL DEFAULT "draft",
        client_name TEXT NOT NULL,
        client_email TEXT,
        client_phone TEXT,
        client_address TEXT,
        event_type TEXT,
        event_date TEXT,
        venue TEXT,
        description TEXT,
        amount_cents INTEGER NOT NULL DEFAULT 0,
        deposit_percent INTEGER NOT NULL DEFAULT 30,
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(source_quote_id) REFERENCES quotes(id) ON DELETE SET NULL
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_commercial_quotes_source ON commercial_quotes(source_quote_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_commercial_quotes_status ON commercial_quotes(status)');

    ensure_column($pdo, 'commercial_quotes', 'travel_km', 'REAL NOT NULL DEFAULT 0');

    $pdo->exec('CREATE TABLE IF NOT EXISTS commercial_quote_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quote_id INTEGER NOT NULL,
        catalog_item_id INTEGER,
        category TEXT NOT NULL,
        item_name TEXT NOT NULL,
        description TEXT,
        quantity REAL NOT NULL DEFAULT 1,
        unit_price_cents INTEGER NOT NULL DEFAULT 0,
        line_total_cents INTEGER NOT NULL DEFAULT 0,
        display_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(quote_id) REFERENCES commercial_quotes(id) ON DELETE CASCADE,
        FOREIGN KEY(catalog_item_id) REFERENCES catalog_items(id) ON DELETE SET NULL
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_commercial_quote_items_quote ON commercial_quote_items(quote_id, display_order, id)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS catalog_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        price_cents INTEGER NOT NULL DEFAULT 0,
        price_mode TEXT NOT NULL DEFAULT "fixed",
        unit_label TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        display_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_catalog_items_category ON catalog_items(category, display_order, id)');
    $pdo->exec("DELETE FROM catalog_items WHERE category='formule' AND name IN ('Pack Instant Magique','Pack Instant Magique Signature')");

    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        key_hash TEXT PRIMARY KEY,
        attempts INTEGER NOT NULL DEFAULT 0,
        first_attempt_at INTEGER NOT NULL,
        locked_until INTEGER NOT NULL DEFAULT 0
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

    $stmt = $pdo->prepare("UPDATE content SET value=:new, updated_at=CURRENT_TIMESTAMP WHERE content_key=:key AND value=:old");
    $stmt->execute([
        ':new'=>'DJ & Animateur',
        ':key'=>'prestations.animations.title',
        ':old'=>'Animations interactives',
    ]);
    $stmt->execute([
        ':new'=>'Une prestation DJ complète, enrichie d’animations au micro et de moments participatifs pour faire vivre votre soirée et créer une ambiance conviviale avec vos invités.',
        ':key'=>'prestations.animations.desc',
        ':old'=>'Faites participer vos invités et créez de vrais temps forts au cours de la soirée, sans casser le rythme ni surcharger l’animation.',
    ]);
    $pdo->exec("UPDATE catalog_items SET name='DJ & Animateur', description='Prestation DJ avec animation au micro, jeux, quiz, blind test ou karaoké selon les envies.', updated_at=CURRENT_TIMESTAMP WHERE category='prestation' AND name='Animations interactives'");

    seed_reviews($pdo);
    seed_catalog($pdo);
    ensure_fee_catalog_items($pdo);
    ensure_quote_catalog_items($pdo);
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
        'prestations.animations.title' => 'DJ & Animateur',
        'prestations.animations.desc' => 'Une prestation DJ complète, enrichie d’animations au micro et de moments participatifs pour faire vivre votre soirée et créer une ambiance conviviale avec vos invités.',
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

function seed_catalog(PDO $pdo): void
{
    if ((int)$pdo->query('SELECT COUNT(*) FROM catalog_items')->fetchColumn() > 0) {
        return;
    }

    $items = [
        ['prestation','DJ','Une ambiance sur mesure du début à la fin.',0,'fixed','Par prestation',1],
        ['prestation','Animations interactives','Karaoké, quiz musical, blind test et animations personnalisées.',0,'fixed','Par prestation',2],
        ['prestation','Sonorisation de vin d’honneur','Musique d’ambiance et sonorisation adaptée au moment.',0,'fixed','Par prestation',3],

        ['formule','Essentiel','L’essentiel pour une soirée réussie.',0,'fixed','Par événement',10],
        ['formule','Ambiance','Plus de lumière, plus de dynamisme, plus d’ambiance.',0,'fixed','Par événement',11],
        ['formule','Expérience','Une mise en scène lumineuse complète pour une soirée inoubliable.',0,'fixed','Par événement',12],

        ['option','Fumée lourde','Nuage au sol pour ouverture de bal ou temps fort.',0,'fixed','Par utilisation',20],
        ['option','Étincelles froides','Gerbes d’étincelles pour entrée, ouverture de bal ou temps fort.',0,'fixed','Par prestation',21],
        ['option','Éclairage mural','Éclairage d’ambiance réparti autour de la salle.',0,'fixed','Par prestation',22],
        ['option','Écran & projecteur','Diffusion de vidéos, diaporamas, surprises ou souvenirs.',0,'fixed','Par prestation',23],
    ];

    $stmt = $pdo->prepare('INSERT INTO catalog_items(category,name,description,price_cents,price_mode,unit_label,active,display_order) VALUES(:category,:name,:description,:price,:mode,:unit,1,:sort)');
    foreach ($items as [$category,$name,$description,$price,$mode,$unit,$sort]) {
        $stmt->execute([
            ':category'=>$category,
            ':name'=>$name,
            ':description'=>$description,
            ':price'=>$price,
            ':mode'=>$mode,
            ':unit'=>$unit,
            ':sort'=>$sort,
        ]);
    }
}

function ensure_quote_catalog_items(PDO $pdo): void
{
    $check = $pdo->prepare("SELECT id FROM catalog_items WHERE category='option' AND name=:name LIMIT 1");
    $check->execute([':name'=>'Photobooth 300 tirages']);
    if (!$check->fetchColumn()) {
        $stmt = $pdo->prepare(
            "INSERT INTO catalog_items(category,name,description,price_cents,price_mode,unit_label,active,display_order)
             VALUES('option',:name,:description,0,'fixed','Par prestation',1,24)"
        );
        $stmt->execute([
            ':name'=>'Photobooth 300 tirages',
            ':description'=>'Photobooth avec forfait de 300 tirages.',
        ]);
    }
}

function ensure_fee_catalog_items(PDO $pdo): void
{
    $items = [
        ['Déplacement', 'Frais kilométriques calculés selon la distance facturée.', 100, 'unit', 'Par km', 90],
        ['Montage / démontage', 'Calcul automatique : 20 % du total de la formule et des options sélectionnées.', 0, 'formula', '20 % formule + options', 91],
    ];

    $check = $pdo->prepare("SELECT id FROM catalog_items WHERE category='frais' AND name=:name LIMIT 1");
    $insert = $pdo->prepare(
        "INSERT INTO catalog_items(category,name,description,price_cents,price_mode,unit_label,active,display_order)
         VALUES('frais',:name,:description,:price,:mode,:unit,1,:sort)"
    );

    foreach ($items as [$name,$description,$price,$mode,$unit,$sort]) {
        $check->execute([':name'=>$name]);
        $existingId = (int)($check->fetchColumn() ?: 0);
        if (!$existingId) {
            $insert->execute([
                ':name'=>$name,
                ':description'=>$description,
                ':price'=>$price,
                ':mode'=>$mode,
                ':unit'=>$unit,
                ':sort'=>$sort,
            ]);
        } elseif ($name === 'Montage / démontage') {
            $update = $pdo->prepare(
                "UPDATE catalog_items
                 SET description=:description, price_cents=0, price_mode='formula',
                     unit_label=:unit, updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id"
            );
            $update->execute([
                ':description'=>$description,
                ':unit'=>$unit,
                ':id'=>$existingId,
            ]);
        } elseif ($name === 'Déplacement') {
            $update = $pdo->prepare(
                "UPDATE catalog_items
                 SET price_cents=100, price_mode='unit', unit_label='Par km',
                     updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id"
            );
            $update->execute([':id'=>$existingId]);
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

    $now = time();
    $lastActivity = (int)($_SESSION['admin_last_activity'] ?? 0);

    // Déconnexion automatique après 2 h d'inactivité.
    if ($lastActivity > 0 && ($now - $lastActivity) > 7200) {
        unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_last_activity'], $_SESSION['admin_regenerated_at']);
        session_regenerate_id(true);
        header('Location: login.php?expired=1');
        exit;
    }

    $_SESSION['admin_last_activity'] = $now;

    // Renouvelle périodiquement l'identifiant de session.
    $regeneratedAt = (int)($_SESSION['admin_regenerated_at'] ?? 0);
    if ($regeneratedAt === 0 || ($now - $regeneratedAt) > 900) {
        session_regenerate_id(true);
        $_SESSION['admin_regenerated_at'] = $now;
    }
}

function login_rate_limit_key(string $email): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', strtolower(trim($email)) . '|' . $ip);
}

function login_rate_limit_status(string $email): int
{
    $key = login_rate_limit_key($email);
    $stmt = db()->prepare('SELECT attempts, first_attempt_at, locked_until FROM login_attempts WHERE key_hash = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();
    if (!$row) return 0;

    $now = time();
    $lockedUntil = (int)($row['locked_until'] ?? 0);
    if ($lockedUntil > $now) return $lockedUntil - $now;

    // Nettoie automatiquement une ancienne fenêtre de tentatives.
    if (($now - (int)($row['first_attempt_at'] ?? 0)) > 900) {
        $delete = db()->prepare('DELETE FROM login_attempts WHERE key_hash = :key');
        $delete->execute([':key' => $key]);
    }

    return 0;
}

function login_rate_limit_fail(string $email): void
{
    $pdo = db();
    $key = login_rate_limit_key($email);
    $now = time();

    $stmt = $pdo->prepare('SELECT attempts, first_attempt_at FROM login_attempts WHERE key_hash = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();

    $attempts = 1;
    $firstAttempt = $now;
    if ($row && ($now - (int)$row['first_attempt_at']) <= 900) {
        $attempts = (int)$row['attempts'] + 1;
        $firstAttempt = (int)$row['first_attempt_at'];
    }

    $lockedUntil = $attempts >= 5 ? $now + 900 : 0;
    $upsert = $pdo->prepare(
        'INSERT INTO login_attempts(key_hash, attempts, first_attempt_at, locked_until)
         VALUES(:key,:attempts,:first,:locked)
         ON CONFLICT(key_hash) DO UPDATE SET
           attempts=excluded.attempts,
           first_attempt_at=excluded.first_attempt_at,
           locked_until=excluded.locked_until'
    );
    $upsert->execute([
        ':key' => $key,
        ':attempts' => $attempts,
        ':first' => $firstAttempt,
        ':locked' => $lockedUntil,
    ]);
}

function login_rate_limit_clear(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE key_hash = :key');
    $stmt->execute([':key' => login_rate_limit_key($email)]);
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

function event_date_availability(string $date): array
{
    $date = trim($date);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$parsed ||
        ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) ||
        $parsed->format('Y-m-d') !== $date
    ) {
        return ['available' => false, 'reason' => 'invalid', 'message' => 'La date indiquée n’est pas valide.'];
    }

    if ($parsed < new DateTimeImmutable('today')) {
        return ['available' => false, 'reason' => 'past', 'message' => 'Cette date est déjà passée.'];
    }

    $stmt = db()->prepare('SELECT 1 FROM reserved_dates WHERE event_date = :date LIMIT 1');
    $stmt->execute([':date' => $date]);

    if ($stmt->fetchColumn()) {
        return [
            'available' => false,
            'reason' => 'reserved',
            'message' => 'Cette date est déjà réservée. Merci de choisir une autre date.',
        ];
    }

    return ['available' => true, 'reason' => 'available', 'message' => ''];
}


function calculate_setup_teardown_cents(int $formulaOptionsSubtotalCents): int
{
    return max(0, (int) round($formulaOptionsSubtotalCents * 0.20));
}

function calculate_rounded_deposit(int $totalCents): array
{
    $totalCents = max(0, $totalCents);
    if ($totalCents === 0) {
        return ['deposit_cents'=>0, 'balance_cents'=>0];
    }

    $minimumBalance = (int) ceil($totalCents * 0.70);
    $roundedBalance = (int) (ceil($minimumBalance / 1000) * 1000);

    if ($roundedBalance > $totalCents) {
        $roundedBalance = $totalCents;
    }

    $deposit = max(0, $totalCents - $roundedBalance);

    return [
        'deposit_cents'=>$deposit,
        'balance_cents'=>$roundedBalance,
    ];
}

function next_commercial_quote_number(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'DEV-' . $year . '-';
    $stmt = $pdo->prepare('SELECT quote_number FROM commercial_quotes WHERE quote_number LIKE :prefix ORDER BY quote_number DESC LIMIT 1');
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = (string)($stmt->fetchColumn() ?: '');
    $sequence = 1;
    if ($last && preg_match('/(\d{3,})$/', $last, $m)) {
        $sequence = ((int)$m[1]) + 1;
    }
    return $prefix . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
}

function commercial_quote_status_label(string $status): string
{
    return [
        'draft' => 'Brouillon',
        'sent' => 'Envoyé',
        'accepted' => 'Accepté',
        'refused' => 'Refusé',
        'expired' => 'Expiré',
    ][$status] ?? $status;
}

function quote_status_label(string $status): string
{
    return [
        'new' => 'Nouveau',
        'read' => 'Lu',
        'sent' => 'Envoyé',
        'accepted' => 'Accepté',
        'refused' => 'Refusé',
        // Compatibilité avec les anciens statuts déjà enregistrés.
        'contacted' => 'Contacté',
        'quote_sent' => 'Devis envoyé',
        'booked' => 'Réservé',
        'archived' => 'Archivé',
    ][$status] ?? $status;
}
