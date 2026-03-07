<?php
/**
 * WellCore Fitness — Chat Profanity & Sales/Spam Filter
 *
 * Provides normalisation, bad-word detection, sales/spam detection,
 * phone-number detection and URL detection for community chat messages.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/chat-filter.php';
 *   $result = filterChatMessage($userText);
 *   // $result = ['clean' => '...', 'flagged' => true/false, 'reasons' => [...]]
 */

// ─────────────────────────────────────────────
// 1. Bad-word dictionary (200+ Spanish terms)
// ─────────────────────────────────────────────

function getChatBadWords(): array
{
    return [
        // — Insultos graves —
        'puta', 'puto', 'perra', 'perro', 'pendejo', 'pendeja',
        'idiota', 'imbecil', 'estupido', 'estupida',
        'tarado', 'tarada', 'baboso', 'babosa',
        'menso', 'mensa', 'tonto', 'tonta',
        'cabron', 'cabrona', 'culero', 'culera',
        'mamon', 'mamona', 'huevon', 'huevona', 'guevon', 'guevona',
        'marica', 'maricon', 'joto', 'jota', 'punal',
        'maldito', 'maldita', 'desgraciado', 'desgraciada',
        'hijueputa', 'hijueperra', 'hp', 'hdp', 'hpta',
        'malparido', 'malparida', 'gonorrea',
        'zorra', 'zorron', 'ramera', 'prostituta',
        'bastardo', 'bastarda', 'cornudo', 'cornuda',
        'soplapolla', 'soplagaitas', 'gilipollas', 'capullo',
        'cretino', 'cretina', 'inutil', 'animal',
        'cerdo', 'cerda', 'cochino', 'cochina',
        'asqueroso', 'asquerosa', 'repugnante',
        'miserable', 'rata', 'sabandija', 'alimaña',
        'engendro', 'adefesio', 'esperpento',
        'lameculos', 'arrastrado', 'arrastrada',
        'papanatas', 'pazguato', 'mequetrefe',
        'petardo', 'berzas', 'zoquete', 'zopenco',
        'memo', 'lelo', 'bobo', 'boba',

        // — Groserias sexuales —
        'verga', 'vergon', 'vergudo',
        'pinga', 'pingon',
        'pene', 'vagina', 'tetas', 'tetotas',
        'culo', 'nalgas', 'nalgon', 'nalgona',
        'coger', 'cojer', 'follar', 'tirar', 'culear',
        'chupar', 'mamar', 'tragar',
        'polla', 'pollón',
        'picha', 'pichula',
        'chimba', 'chocha', 'concha',
        'clitoris', 'orgasmo', 'masturbar',
        'pajero', 'pajera', 'pajote',
        'correrse', 'eyacular',
        'semen', 'esperma',
        'panocha', 'pepino', 'bicho',
        'huevos', 'cojones', 'bolas',
        'sodomizar', 'sodomia',
        'putear', 'puteria', 'putiza',
        'cogida', 'follada', 'mamada',
        'sexo oral', 'anal', 'pornografia', 'porno',

        // — Vulgaridades —
        'mierda', 'mierdero', 'caca', 'cacas',
        'chingar', 'chingada', 'chingado', 'chingadera', 'chingon',
        'joder', 'jodido', 'jodida', 'jodete',
        'carajo', 'carajos',
        'cono', 'coño',
        'diablos', 'demonios',
        'pinche', 'pinches',
        'pedo', 'pedorro', 'pedorra',
        'meada', 'meado', 'mear',
        'vomitar', 'vomitivo',
        'asco', 'asquiento',
        'porqueria', 'cochinada', 'guarrada', 'guarreria',
        'cagada', 'cagar', 'cagado', 'cagon',
        'pis', 'orinar',

        // — Discriminatorios —
        'naco', 'naca', 'nacos', 'nacas',
        'retrasado', 'retrasada',
        'mongoloide', 'mongolo',
        'subnormal', 'deficiente',
        'gordo', 'gorda', 'gordiflona',
        'enano', 'enana',
        'indio', 'india',
        'negro de mierda', 'negrata',
        'sidoso', 'sidosa',
        'invalido', 'lisiado',
        'leproso', 'apestoso', 'apestosa',
        'muerto de hambre',

        // — Agresiones —
        'te voy a matar', 'voy a matarte',
        'muerete', 'muerase',
        'basura', 'escoria', 'lacra', 'lacras',
        'parasito', 'parasita',
        'te voy a partir', 'te voy a romper',
        'te voy a dar', 'te voy a reventar',
        'ojala te mueras', 'ojala te pase',
        'callate', 'callese',
        'largate', 'pierdete',
        'tu madre', 'tu mama',
        'hijo de puta', 'hijo de perra',
        'hija de puta', 'hija de perra',
        'come mierda', 'traga mierda',
        'vete al diablo', 'vete al carajo',
        'vete a la mierda', 'vete a la verga',

        // — Drogas —
        'marihuana', 'marijuana', 'mariguana',
        'mota', 'hierba', 'yerba',
        'coca', 'cocaina',
        'crack', 'piedra',
        'meta', 'metanfetamina', 'cristal',
        'extasis', 'mdma',
        'perico', 'polvo',
        'heroina', 'opio',
        'lsd', 'acido',
        'hongos', 'psilocibina',
        'anfetamina', 'speed',
        'ketamina', 'ghb',
        'fentanilo', 'fentanyl',
        'narcotraficante', 'narco', 'narcos',
    ];
}

// ─────────────────────────────────────────────
// 2. Sales / spam word dictionary
// ─────────────────────────────────────────────

function getSalesWords(): array
{
    return [
        // Commerce
        'vendo', 'venta', 'ventas',
        'compra', 'comprar', 'compro',
        'precio', 'precios', 'costo', 'costos',
        'negocio', 'negocios',
        'oferta', 'ofertas',
        'descuento', 'descuentos',
        'promocion', 'promociones', 'promo',
        'ganga', 'barato', 'baratos', 'economico',
        'liquidacion', 'remate',

        // Messaging apps
        'whatsapp', 'whats', 'wpp',
        'telegram', 'snapchat', 'onlyfans',
        'signal', 'tiktok',

        // Payment
        'transferencia', 'transferencias',
        'paypal', 'nequi', 'daviplata',
        'zelle', 'venmo', 'mercadopago',
        'deposito', 'consignar',

        // Contact
        'llamame', 'llameme',
        'escribeme', 'escribame',
        'contactame', 'contacteme',
        'mandame mensaje', 'escribeme al',

        // Links
        'enlace', 'link', 'url',
        'http', 'https', 'www',
        'bit.ly', '.com', '.net', '.info',
        '.org', '.co', '.mx', '.es',

        // MLM / scam
        'multinivel', 'mlm',
        'herbalife', 'amway', 'omnilife',
        'gana dinero', 'ganar dinero',
        'ingresos extra', 'ingresos pasivos',
        'trabaja desde casa', 'trabajo desde casa',
        'negocio propio', 'se tu propio jefe',
        'libertad financiera', 'independencia financiera',
        'oportunidad de negocio', 'oportunidad unica',
        'esquema piramidal', 'piramidal',

        // Crypto / trading
        'crypto', 'criptomoneda', 'criptomonedas',
        'bitcoin', 'btc', 'ethereum', 'eth',
        'inversion', 'inversiones', 'invertir',
        'forex', 'trading', 'trader',
        'binance', 'coinbase',
        'nft', 'token', 'tokens',
    ];
}

// ─────────────────────────────────────────────
// 3. Text normalisation for matching
// ─────────────────────────────────────────────

function normalizeForFilter(string $text): string
{
    // Lowercase (multibyte-safe)
    $t = mb_strtolower($text, 'UTF-8');

    // Remove accents
    $accents = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ñ' => 'n', 'ü' => 'u',
    ];
    $t = strtr($t, $accents);

    // Number / symbol substitutions (leet-speak)
    $leet = [
        '0' => 'o',
        '1' => 'i',
        '3' => 'e',
        '4' => 'a',
        '5' => 's',
        '7' => 't',
        '@' => 'a',
    ];
    $t = strtr($t, $leet);

    // Collapse 3+ repeated characters to a single instance
    $t = preg_replace('/(.)\1{2,}/u', '$1', $t);

    // Remove spaces between single characters (e.g. "p u t a" → "puta")
    $t = preg_replace('/\b(\w)\s+(?=\w\b)/u', '$1', $t);

    return $t;
}

// ─────────────────────────────────────────────
// 4. Phone-number detection
// ─────────────────────────────────────────────

function containsPhoneNumber(string $text): bool
{
    // Strip common separators and whitespace
    $stripped = preg_replace('/[\s\-\.\(\)\+]/', '', $text);

    // Match 7 or more consecutive digits
    return (bool) preg_match('/\d{7,}/', $stripped);
}

// ─────────────────────────────────────────────
// 5. Main filter function
// ─────────────────────────────────────────────

function filterChatMessage(string $text): array
{
    $reasons = [];
    $clean   = $text;

    // Normalised version for matching
    $normalised = normalizeForFilter($text);

    // --- Profanity check ---
    $badWords = getChatBadWords();
    foreach ($badWords as $word) {
        $normWord = normalizeForFilter($word);
        // Use word-boundary matching on the normalised text
        $pattern = '/\b' . preg_quote($normWord, '/') . '\b/u';
        if (preg_match($pattern, $normalised)) {
            if (!in_array('profanity', $reasons, true)) {
                $reasons[] = 'profanity';
            }
            // Replace the match in the clean output (case-insensitive on original)
            $cleanPattern = '/' . preg_quote($word, '/') . '/iu';
            $clean = preg_replace($cleanPattern, '***', $clean);
        }
    }

    // --- Sales / spam check ---
    $salesWords = getSalesWords();
    foreach ($salesWords as $word) {
        $normWord = normalizeForFilter($word);
        // Some sales terms contain dots / special chars — quote them
        $pattern = '/' . preg_quote($normWord, '/') . '/u';
        if (preg_match($pattern, $normalised)) {
            if (!in_array('sales', $reasons, true)) {
                $reasons[] = 'sales';
            }
            $cleanPattern = '/' . preg_quote($word, '/') . '/iu';
            $clean = preg_replace($cleanPattern, '***', $clean);
        }
    }

    // --- Phone-number check ---
    if (containsPhoneNumber($text)) {
        $reasons[] = 'phone_number';
        // Replace sequences of 7+ digits (with optional separators) with ***
        $clean = preg_replace('/(\+?\d[\d\s\-\.\(\)]{6,}\d)/', '***', $clean);
    }

    // --- URL check ---
    $urlPattern = '/https?:\/\/[^\s]+|www\.[^\s]+|[a-z0-9\-]+\.(com|net|org|info|co|mx|es|ly|io|me|tv|app|dev|store|shop)(\/[^\s]*)?/iu';
    if (preg_match($urlPattern, $clean)) {
        $reasons[] = 'url';
        $clean = preg_replace($urlPattern, '***', $clean);
    }

    return [
        'clean'   => $clean,
        'flagged' => !empty($reasons),
        'reasons' => $reasons,
    ];
}
