<?php
/**
 * Seed: Instagram Reels — Tips de Coach (Silvia + Dann)
 * ======================================================
 * 1. Desactiva todos los coach_video_tips actuales (YouTube)
 * 2. Inserta 29 reels de Instagram como nuevos tips
 *
 * EJECUCIÓN: php /code/api/setup/seed-instagram-reels.php
 *            o via Bearer token admin en HTTP
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
header('Content-Type: text/plain; charset=utf-8');

$coachId = '3'; // coachsilvia
$now     = date('Y-m-d H:i:s');

// 1. Desactivar todos los tips actuales
$deact = $db->prepare("UPDATE coach_video_tips SET is_active = 0 WHERE is_active = 1");
$deact->execute();
echo "Desactivados: " . $deact->rowCount() . " tips anteriores\n\n";

// Formato: [titulo, instagram_url, thumbnail_url, sort_order]
$reels = [

    // ─── COACH SILVIA (14 reels) ──────────────────────────────────────────
    [
        'Tip de Entrenamiento — Coach Silvia #1',
        'https://www.instagram.com/reel/DTv5xIcEV5y/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/619481110_18550161655002464_8274308748407843533_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=108&ccb=7-5&_nc_sid=18de74&oh=00_AfzyNL0KYqzfsF0gDv4PRHGOUNIsWJciVC638X93yrtMaA&oe=69B4C2BA',
        10,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #2',
        'https://www.instagram.com/reel/DVbTGLUBLzY/',
        'https://scontent.cdninstagram.com/v/t51.71878-15/639781462_1628066558204987_7172796194456507003_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=100&ccb=7-5&_nc_sid=18de74&oh=00_AfxrUyXWN9hYn2YKHU5NVXdP_saJYb-14JiAB24nV-YXZQ&oe=69B49ED6',
        11,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #3',
        'https://www.instagram.com/reel/DU35IntD5PA/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/630125169_18558123076002464_7500484159874451771_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=109&ccb=7-5&_nc_sid=18de74&oh=00_Afxxiciy1F96qzb8D9H9GKdtJechLwhqmupfnq8PIZieMg&oe=69B4AE4C',
        12,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #4',
        'https://www.instagram.com/reel/DULeeWxlTJA/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/625948638_18551978422002464_1200191900736513243_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=110&ccb=7-5&_nc_sid=18de74&oh=00_AfxRuI3JCOklI1OfYQ4TjtfuosrnU_FkpVkJpiX5oLjGMg&oe=69B49F53',
        13,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #5',
        'https://www.instagram.com/reel/DTgfRyeEdkR/',
        'https://scontent.cdninstagram.com/v/t51.71878-15/615805871_25522629734067345_5800165157402100387_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=100&ccb=7-5&_nc_sid=18de74&oh=00_AfyIaSg0sy95-Tvnt26dCorvs194Q91HED4f-Bs_E1ZpSA&oe=69B4B638',
        14,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #6',
        'https://www.instagram.com/reel/DTJeWlsD1pS/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/610627015_18547545769002464_7595026755897097573_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=100&ccb=7-5&_nc_sid=18de74&oh=00_AfyB2CoNcc76bRUkVfpPFPrXvn1XnN2jAmcVJH_RD2htPw&oe=69B4C4C9',
        15,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #7',
        'https://www.instagram.com/reel/DTDfBv2ES3P/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/610560221_18547139872002464_4737073830098613885_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=107&ccb=7-5&_nc_sid=18de74&oh=00_Afw0YEQ-fDvGOSTRvQUFz1jtpKkdR-cwgKe7-lwTNeeOaQ&oe=69B4C549',
        16,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #8',
        'https://www.instagram.com/reel/DS3i6e8DA7r/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/607517470_18546238435002464_2588613254905942427_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=100&ccb=7-5&_nc_sid=18de74&oh=00_AfzomQpGnHszRRHEV9SMf59jcqULA8qtiKqGQrk4Xpbtkw&oe=69B4AAA9',
        17,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #9',
        'https://www.instagram.com/reel/DSvh6OND0bT/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/607012762_18545706007002464_6116219494106890514_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=100&ccb=7-5&_nc_sid=18de74&oh=00_AfwSN9u65RntD37IEHNnFbwTH9Ak1-ZxyJWgbpV-H0Aq6w&oe=69B4A186',
        18,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #10',
        'https://www.instagram.com/reel/DSlWF9zETpU/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/605102011_18545001430002464_5974744007448956197_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=102&ccb=7-5&_nc_sid=18de74&oh=00_AfxfkYusZK6bjdUC9CzVeh7mDaKzIhVCLnlB3rLRTQ8NBA&oe=69B4C2A4',
        19,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #11',
        'https://www.instagram.com/reel/DSSjCn3EWzj/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/590419326_18543764413002464_4040856176794240208_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=100&ccb=7-5&_nc_sid=18de74&oh=00_AfwCychRlv5WZk6XcEDGqUucILDyUdAo1Xq1d7iSpZVE3Q&oe=69B4BCBA',
        20,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #12',
        'https://www.instagram.com/reel/DSBnLHDDN0y/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/589163090_18542563339002464_902593216753471492_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=111&ccb=7-5&_nc_sid=18de74&oh=00_Afypwpjxh-STSMNfFg-CjLTZETiu_Ym8cxQjC6odoSTNwQ&oe=69B49CC6',
        21,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #13',
        'https://www.instagram.com/reel/DR5dinFkQ3R/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/591149325_18542009050002464_2278856598769535421_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=102&ccb=7-5&_nc_sid=18de74&oh=00_AfxWcKx9acNAGCExcSzTWtfZhhHFyfTJjFs3yiG0eXVMKQ&oe=69B49AD9',
        22,
    ],
    [
        'Tip de Entrenamiento — Coach Silvia #14',
        'https://www.instagram.com/reel/DRlRYTiDKU_/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/589068059_18540697456002464_8205003014441286680_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=105&ccb=7-5&_nc_sid=18de74&oh=00_AfyIiezJ0wXUl_Tng6ROBYSdxNSskHd_lPpmSoo92KYWXg&oe=69B4CA0C',
        23,
    ],

    // ─── COACH DANN (13 reels) ────────────────────────────────────────────
    [
        'Tip de Entrenamiento — Coach Dann #1',
        'https://www.instagram.com/reel/DS8VLUlDxDg/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/609172254_18080154740224918_4958873340284156019_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=100&ccb=7-5&_nc_sid=18de74&oh=00_AfyV9HI0Pt-wxMxApqErWIaQanY-qiDaYkfODWpnGvqVug&oe=69B4B9CF',
        30,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #2',
        'https://www.instagram.com/reel/DS5_0xRD3VT/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/609171161_18080043122224918_7643990340474213373_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=104&ccb=7-5&_nc_sid=18de74&oh=00_AfxlvZ0aEHukpmih5RQd1XDm7oGtfswtN_oYjimVjkCYYw&oe=69B4C1DB',
        31,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #3',
        'https://www.instagram.com/reel/DR3C7uxD4OR/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/589178205_18077676473224918_8280838167770261135_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=109&ccb=7-5&_nc_sid=18de74&oh=00_Afzt0iiD8SGYwjhz2dnmH9ubdFaSDmQ7JFDHhvI6nZ_i4Q&oe=69B4ADE3',
        32,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #4',
        'https://www.instagram.com/reel/DUrP9v-j_ob/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/630153968_18084241868224918_1939378481161533767_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=106&ccb=7-5&_nc_sid=18de74&oh=00_Afzr2sSFt9j0PLbRFSkqXsrFhgo36PbZPAogpE6IBjPVYg&oe=69B4C112',
        33,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #5',
        'https://www.instagram.com/reel/DUQsPSHjSBF/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/627361746_18083365700224918_6052483366594566026_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=111&ccb=7-5&_nc_sid=18de74&oh=00_AfwYAWqiewg9NmGeUZZDXptLeCBLlTqPfhZLQriuBn9oSg&oe=69B49F4F',
        34,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #6',
        'https://www.instagram.com/reel/DT_q8_MD2mf/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/623155708_18082747292224918_2360235018815645044_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=111&ccb=7-5&_nc_sid=18de74&oh=00_AfxWj28t35_Vzpy5OWjYzeL2eZXrJ1ruPy-EK-XlVQ3Gvg&oe=69B4CD79',
        35,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #7',
        'https://www.instagram.com/reel/DTu78ekjSp4/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/619789195_18081935654224918_8587635829918843503_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=101&ccb=7-5&_nc_sid=18de74&oh=00_AfzahxN6HacaFRbAKRiP9AN3Vbmsh_I01DZbaANm-HMlmw&oe=69B4C66B',
        36,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #8',
        'https://www.instagram.com/reel/DTNnb8kDfgL/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/611777139_18080775884224918_1390323794403273464_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=109&ccb=7-5&_nc_sid=18de74&oh=00_Afyd8rHKI49-kRt6cKyDjYt9UBcJJidvKls0FkotF_5Chg&oe=69B4C0A2',
        37,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #9',
        'https://www.instagram.com/reel/DTJfoGED9B9/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/610620410_18080635535224918_4855708260229645182_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=105&ccb=7-5&_nc_sid=18de74&oh=00_AfzKJO49tni27mJpqrmISIL3yKzek6kt1exfYcpBc4QvxA&oe=69B4CB24',
        38,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #10',
        'https://www.instagram.com/reel/DTEIO3tjwiP/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/610775704_18080458079224918_3267421862802112748_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=105&ccb=7-5&_nc_sid=18de74&oh=00_AfzY9LljTuToTKsJ0jfgAzVam8spmYAv-Sf77Fd-Rb2aMg&oe=69B4A3BF',
        39,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #11',
        'https://www.instagram.com/reel/DSVzMoXD-0m/',
        'https://scontent.cdninstagram.com/v/t51.71878-15/587809806_1519836379094289_7122899645816183170_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=105&ccb=7-5&_nc_sid=18de74&oh=00_AfwcEpk0E5LA-Gq0-UsL48fhXpf79LOh1PkbnAWSbWg5CA&oe=69B4ACB5',
        40,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #12',
        'https://www.instagram.com/reel/DSODr5ij42D/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/598349983_18078485966224918_2508650974962626023_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=107&ccb=7-5&_nc_sid=18de74&oh=00_Afy9dBrugMN2i4OfOfEJPniJXD8w7A-fPq96iGU0b-atlA&oe=69B4A367',
        41,
    ],
    [
        'Tip de Entrenamiento — Coach Dann #13',
        'https://www.instagram.com/reel/DSFpOZhjQ60/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/598366863_18078192377224918_9176791089886185381_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=111&ccb=7-5&_nc_sid=18de74&oh=00_AfwictCHP5BfIj9_7QSfi5gcv7BXSuz3ElWFi4Dhh4iQMQ&oe=69B4C466',
        42,
    ],

    // ─── SILVIA & DANN JUNTOS (2) ─────────────────────────────────────────
    [
        'Entrena con tu Coach — Silvia & Dann',
        'https://www.instagram.com/reel/DSurw6mjQDa/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/606489579_18079639250224918_7031098512485384662_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=101&ccb=7-5&_nc_sid=18de74&oh=00_Afy2fioKfKWpk3G2lzqOdCAwRfVrGpn1CCeuET6IgBzkIw&oe=69B49D72',
        50,
    ],
    [
        'WellCore en Acción — Coach Silvia & Dann',
        'https://www.instagram.com/p/DNttMQOXvLe/',
        'https://scontent.cdninstagram.com/v/t51.82787-15/539101195_18066644315224918_4695748119641952898_n.jpg?stp=cmp1_dst-jpg_e35_s640x640_tt6&_nc_cat=106&ccb=7-5&_nc_sid=18de74&oh=00_AfzVLd208L5GP-5kNZ5fcrE8kNVxvujUnNFbzeO79rKNpw&oe=69B4CECD',
        51,
    ],
];

echo "Reels a insertar: " . count($reels) . "\n\n";

$stmt = $db->prepare("
    INSERT INTO coach_video_tips
        (coach_id, title, video_url, thumbnail_url, duration_sec, sort_order, is_active, created_at)
    VALUES (?, ?, ?, ?, 0, ?, 1, ?)
");

$db->beginTransaction();
$inserted = 0;
$errors   = [];

foreach ($reels as [$title, $videoUrl, $thumbUrl, $sortOrder]) {
    try {
        $stmt->execute([$coachId, $title, $videoUrl, $thumbUrl, $sortOrder, $now]);
        $inserted++;
        echo "  OK  sort={$sortOrder}  {$title}\n";
    } catch (\PDOException $e) {
        $errors[] = "{$title}: " . $e->getMessage();
        echo "  ERR  {$title}: " . $e->getMessage() . "\n";
    }
}

$db->commit();

echo "\n=== Resultado ===\n";
echo "Insertados: $inserted\n";
echo "Errores: " . count($errors) . "\n";
echo "Total activos en DB: " . $db->query("SELECT COUNT(*) FROM coach_video_tips WHERE is_active=1")->fetchColumn() . "\n";
