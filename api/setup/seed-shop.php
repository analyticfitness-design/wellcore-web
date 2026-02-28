<?php
// WellCore — Shop Database Seeder
// Access: /api/setup/seed-shop.php?secret=WELLCORE_SETUP_2026
// Idempotent: safe to run multiple times

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db      = getDB();
$results = [];
$errors  = [];

// ── 1. CREATE TABLES (if not exist) ─────────────────────────────────────────

$db->exec("
    CREATE TABLE IF NOT EXISTS shop_categories (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug        VARCHAR(80)  NOT NULL UNIQUE,
        name        VARCHAR(120) NOT NULL,
        icon        VARCHAR(50)  NULL,
        sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
        active      TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$results[] = 'Table shop_categories: OK';

$db->exec("
    CREATE TABLE IF NOT EXISTS shop_brands (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug        VARCHAR(80)  NOT NULL UNIQUE,
        name        VARCHAR(120) NOT NULL,
        logo_url    VARCHAR(500) NULL,
        active      TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$results[] = 'Table shop_brands: OK';

$db->exec("
    CREATE TABLE IF NOT EXISTS shop_products (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug            VARCHAR(120)     NOT NULL UNIQUE,
        name            VARCHAR(200)     NOT NULL,
        brand_id        INT UNSIGNED     NULL,
        category_id     INT UNSIGNED     NULL,
        description     TEXT             NULL,
        price_cop       INT UNSIGNED     NOT NULL,
        compare_price   INT UNSIGNED     NULL,
        image_url       VARCHAR(500)     NULL,
        image_alt       VARCHAR(200)     NULL,
        servings        VARCHAR(60)      NULL,
        weight          VARCHAR(60)      NULL,
        flavors         JSON             NULL,
        tags            JSON             NULL,
        stock_status    ENUM('in_stock','low_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
        featured        TINYINT(1)       NOT NULL DEFAULT 0,
        active          TINYINT(1)       NOT NULL DEFAULT 1,
        views           INT UNSIGNED     NOT NULL DEFAULT 0,
        created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_sp_brand    FOREIGN KEY (brand_id)    REFERENCES shop_brands(id)     ON DELETE SET NULL,
        CONSTRAINT fk_sp_category FOREIGN KEY (category_id) REFERENCES shop_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$results[] = 'Table shop_products: OK';

$db->exec("
    CREATE TABLE IF NOT EXISTS shop_orders (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_code      VARCHAR(20)  NOT NULL UNIQUE,
        guest_name      VARCHAR(150) NOT NULL,
        guest_email     VARCHAR(200) NOT NULL,
        guest_phone     VARCHAR(30)  NULL,
        guest_city      VARCHAR(100) NULL,
        guest_address   TEXT         NULL,
        guest_notes     TEXT         NULL,
        subtotal_cop    INT UNSIGNED NOT NULL DEFAULT 0,
        shipping_cop    INT UNSIGNED NOT NULL DEFAULT 0,
        total_cop       INT UNSIGNED NOT NULL DEFAULT 0,
        status          ENUM('pending','paid','processing','shipped','delivered','cancelled')
                        NOT NULL DEFAULT 'pending',
        tracking_code   VARCHAR(100) NULL,
        wompi_ref       VARCHAR(100) NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$results[] = 'Table shop_orders: OK';

$db->exec("
    CREATE TABLE IF NOT EXISTS shop_order_items (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id        INT UNSIGNED NOT NULL,
        product_id      INT UNSIGNED NULL,
        product_name    VARCHAR(200) NOT NULL,
        variant         VARCHAR(100) NULL,
        quantity        TINYINT UNSIGNED NOT NULL DEFAULT 1,
        unit_price      INT UNSIGNED NOT NULL,
        CONSTRAINT fk_soi_order   FOREIGN KEY (order_id)   REFERENCES shop_orders(id)   ON DELETE CASCADE,
        CONSTRAINT fk_soi_product FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$results[] = 'Table shop_order_items: OK';

// ── 2. CATEGORIES (INSERT IGNORE — idempotent) ───────────────────────────────

$categories = [
    ['proteinas',    'Proteinas',               1],
    ['creatinas',    'Creatinas',               2],
    ['aminoacidos',  'Aminoacidos',             3],
    ['pre-entrenos', 'Pre-Entrenos',            4],
    ['quemadores',   'Quemadores de Grasa',     5],
    ['vitaminas',    'Vitaminas y Salud',       6],
    ['naturales',    'Potenciadores Naturales', 7],
    ['combos',       'Combos y Packs',          8],
    ['accesorios',   'Accesorios',              9],
    ['digital',      'Productos Digitales',     10],
];

$catInserted = 0;
$stmtCat = $db->prepare(
    "INSERT IGNORE INTO shop_categories (slug, name, sort_order) VALUES (?, ?, ?)"
);
foreach ($categories as [$slug, $name, $order]) {
    try {
        $stmtCat->execute([$slug, $name, $order]);
        if ($stmtCat->rowCount() > 0) $catInserted++;
    } catch (PDOException $e) {
        $errors[] = "[category:$slug] " . $e->getMessage();
    }
}
$results[] = "Categories seeded: $catInserted new / " . count($categories) . ' total';

// ── 3. BRANDS (INSERT IGNORE — idempotent) ───────────────────────────────────

$brands = [
    ['hi-tech',        'HI-TECH PHARMA'],
    ['macroblends',    'MACROBLENDS'],
    ['hard-supps',     'HARD SUPPS'],
    ['nutramerican',   'NUTRAMERICAN PHARMA'],
    ['insane-labz',    'INSANE LABZ'],
    ['iron-nutrition', 'IRON NUTRITION'],
    ['smart-nutrition','SMART NUTRITION'],
    ['wellcore',       'WELLCORE FITNESS'],
];

$brandInserted = 0;
$stmtBrand = $db->prepare(
    "INSERT IGNORE INTO shop_brands (slug, name, logo_url) VALUES (?, ?, NULL)"
);
foreach ($brands as [$slug, $name]) {
    try {
        $stmtBrand->execute([$slug, $name]);
        if ($stmtBrand->rowCount() > 0) $brandInserted++;
    } catch (PDOException $e) {
        $errors[] = "[brand:$slug] " . $e->getMessage();
    }
}
$results[] = "Brands seeded: $brandInserted new / " . count($brands) . ' total';

// ── 4. PRODUCTS (ON DUPLICATE KEY UPDATE — fully idempotent) ─────────────────
// Columns: slug, name, brand_slug, category_slug, description, price_cop,
//          image_url, servings, weight, flavors_json, stock_qty, featured

$products = [

    // PROTEINAS
    ['whey-blend-4lb','Proteina Whey Blend 4 LB','macroblends','proteinas','Blend de proteina de suero con 60 servicios por envase. Combina whey concentrate e isolate para un perfil de aminoacidos completo con absorcion rapida y sostenida. Aporta entre 24-26g de proteina por servicio con bajo contenido de grasa y azucar. Ideal para post-entrenamiento o como complemento proteico diario. Disponible en 4 sabores con excelente solubilidad.',280000,'images/shop/whey-4lb.png','60 servicios','4 LB','["Vainilla","Caramelo","Chocolate","Cookies & Cream"]',15,true],
    ['whey-blend-2lb','Proteina Whey Blend 2 LB','macroblends','proteinas','Version de 2 libras del Whey Blend de Macroblends con 30 servicios. Misma formula de proteina de suero blend con absorcion rapida y perfil completo de aminoacidos. Presentacion ideal para quienes prueban la proteina por primera vez o buscan una opcion mas compacta. Aporta 24-26g de proteina por servicio.',180000,'images/shop/whey-2lb.png','30 servicios','2 LB','["Caramelo","Chocolate","Cookies & Cream","Vainilla"]',20,false],
    ['perfect-blend-2lb','Proteina Perfect Blend 2 LB','macroblends','proteinas','Formula de proteina multi-fuente que combina distintas velocidades de absorcion para un aporte proteico prolongado. Con 30 servicios por envase, es ideal tanto para post-entreno como para entre comidas. Contiene enzimas digestivas que mejoran la biodisponibilidad y reducen la hinchazon. Opcion solida para quienes buscan una proteina versatil.',165000,'images/shop/perfect-2lb.png','30 servicios','2 LB',null,10,false],
    ['xl-food-blend-3lb','XL Food Blend 3 LB','macroblends','proteinas','Gainer premium de 3 libras con ratio equilibrado de proteina y carbohidratos de calidad. Disenado para atletas en fase de volumen que necesitan un aporte calorico extra sin recurrir a azucares simples. Aporta entre 500-700 calorias por servicio dependiendo de la preparacion. Ideal para hardgainers y personas con metabolismo acelerado.',96000,'images/shop/xl-food-3lb.png','15 servicios','3 LB','["Vainilla"]',8,false],
    ['evolution-10lbs','Evolution Mass 10 LB','smart-nutrition','proteinas','Mass gainer de 10 libras con alto contenido calorico para ganancia muscular seria. Formulado con proteina de suero, carbohidratos complejos y MCTs para un aporte energetico sostenido. Con 40 servicios por envase, ofrece el mejor costo por servicio del mercado. Recomendado para fases de bulking y atletas que necesitan superar las 3000 calorias diarias.',250000,'images/shop/evo-10lb.png','40 servicios','10 LB',null,5,false],

    // CREATINAS
    ['cr2-creatina-60','CR2 Creatina 60 Servicios','macroblends','creatinas','Creatina monohidrato saborizada con 60 servicios por envase. La creatina es el suplemento mas investigado del mundo con evidencia solida en mejora de fuerza, potencia y rendimiento. La formula CR2 incluye sistemas de transporte que mejoran la absorcion. Disponible en 3 sabores para quienes prefieren evitar creatinas sin sabor. Dosis recomendada: 5g diarios.',110000,'images/shop/cr2-60.png','60 servicios','300g','["Pina Colada","Fresa","Tropical"]',25,true],
    ['cr2-creatina-30','CR2 Creatina 30 Servicios','macroblends','creatinas','Presentacion de 30 servicios de la creatina saborizada CR2 de Macroblends. Formato ideal para probar el producto o para ciclos de mantenimiento de un mes. Misma formula con sistema de absorcion mejorada. Perfecta como primera creatina o como opcion de viaje por su tamano compacto.',65000,'images/shop/cr2-30.png','30 servicios','150g','["Maracuya"]',15,false],
    ['cr-pure-creapure','CR-PURE Creatina Creapure','macroblends','creatinas','Creatina monohidrato con certificacion Creapure, el estandar de oro fabricado en Alemania con pureza superior al 99.95%. Sin sabor, sin aditivos, sin rellenos — solo creatina micronizada de grado farmaceutico. Estudios muestran que la suplementacion con creatina aumenta la fuerza maxima entre 5-10% y la masa magra en 1-2 kg en 4-12 semanas. La opcion para puristas.',120000,'images/shop/cr-pure.png','60 servicios','300g',null,10,false],
    ['crea-stack','Creatina Crea Stack','nutramerican','creatinas','Stack de creatinas que combina multiples formas del compuesto para maximizar la saturacion muscular. Incluye creatina monohidrato, HCL y complejo de transporte. Formulado para atletas avanzados que buscan el maximo rendimiento en fuerza y potencia. 60 servicios con dosificacion optimizada por cada toma.',160000,'images/shop/crea-stack.png','60 servicios','400g',null,8,false],
    ['creatina-iron','Creatina Monohidrato','iron-nutrition','creatinas','Creatina monohidrato micronizada sin sabor de grado farmaceutico. Particulas ultrafinas para mejor disolucion y absorcion. 66 servicios por envase — uno de los mejores rendimientos por gramo del mercado. Versatil: se puede mezclar con cualquier bebida sin alterar el sabor. Ideal para quienes prefieren creatina pura y economica.',120000,'images/shop/creatina-iron.png','66 servicios','400g',null,12,false],

    // AMINOACIDOS
    ['eaas-precision','EAAs Precision','hi-tech','aminoacidos','Formula completa de 9 aminoacidos esenciales en ratio cientificamente validado. Los EAAs son los bloques fundamentales para la sintesis de proteina muscular — el cuerpo no puede producirlos y deben obtenerse de la dieta o suplementacion. Superior a los BCAAs solos segun investigaciones recientes. Ideal intra-entreno o como complemento en dias de deficit calorico.',160000,'images/shop/eaas-precision.png','30 servicios','390g','["Fruit Punch","Blueberry"]',18,false],
    ['bcaa-supreme','BCAA Supreme','hi-tech','aminoacidos','Aminoacidos de cadena ramificada (leucina, isoleucina, valina) en ratio 2:1:1 optimizado para recuperacion muscular. Formulado con electrolitos y vitaminas B para hidratacion durante el entrenamiento. 30 servicios con sabor premium. Util como intra-entreno en sesiones largas o entrenamientos en ayunas.',160000,'images/shop/bcaa-supreme.png','30 servicios','390g','["Sandia","Blueberry"]',15,false],
    ['eaas-mix-30','EAAs Mix 30 Servicios','macroblends','aminoacidos','Mezcla de aminoacidos esenciales saborizada con 30 servicios y 4 sabores disponibles. Formula economica con todos los EAAs necesarios para activar la sintesis de proteina muscular. Baja en calorias, ideal para consumir durante el entrenamiento sin afectar el deficit calorico. Excelente opcion como primer suplemento de aminoacidos.',125000,'images/shop/eaas-mix.png','30 servicios','330g','["Pina Colada","Zarzamora","Limonada Cherry","Maracuya"]',20,true],

    // PRE-ENTRENOS
    ['mesomorph','Mesomorph Pre-Workout','hi-tech','pre-entrenos','Pre-entreno de alta estimulacion formulado para atletas avanzados que buscan energia maxima, foco mental intenso y pump vascular extremo. Uno de los pre-entrenos mas potentes disponibles en el mercado colombiano. Contiene DMAA, beta-alanina, citrulina y complejo neuroestimulante. No recomendado para principiantes ni personas sensibles a estimulantes. 25 servicios por envase.',170000,'images/shop/mesomorph.png','25 servicios','388g',null,10,true],
    ['rm-pre-entreno','RM Pre-Entreno 30 servs','macroblends','pre-entrenos','Pre-entreno con formula balanceada que ofrece energia sostenida sin el crash post-entrenamiento. Combina cafeina con L-teanina para un efecto estimulante limpio y enfocado. Incluye beta-alanina y citrulina para resistencia muscular y pump. 30 servicios — ideal para entrenamientos de fuerza e hipertrofia de alta intensidad.',155000,'images/shop/rm-pre.png','30 servicios','300g','["Cherry"]',8,false],
    ['neuro-freak','Neuro Freak','hard-supps','pre-entrenos','Nootropico pre-entrenamiento disenado para maximizar la conexion mente-musculo y el foco mental. Formula que prioriza la concentracion sobre la estimulacion pura — no es un pre-entreno tradicional de alta cafeina. Ideal para sesiones tecnicas donde la calidad de contraccion importa mas que la energia bruta. Buena opcion para entrenamientos nocturnos o personas sensibles a estimulantes.',140000,'images/shop/neuro-freak.png','30 servicios','250g',null,6,false],

    // QUEMADORES
    ['lipodrene','Lipodrene','hi-tech','quemadores','Termogenico de referencia en el mercado con formula original de Hi-Tech Pharma. Uno de los quemadores mas vendidos a nivel mundial con mas de 20 anos en el mercado. Actua sobre multiples vias metabolicas para aumentar el gasto calorico en reposo. 90 capsulas por envase para un ciclo completo de 30-45 dias. Usar con precaucion y no exceder la dosis recomendada.',165000,'images/shop/lipodrene.webp','90 capsulas','90 caps',null,10,false],
    ['lipodrene-xtreme','Lipodrene Xtreme','hi-tech','quemadores','Version de mayor potencia del Lipodrene clasico con ingredientes adicionales para un efecto termogenico mas agresivo. Formulado para usuarios experimentados que ya tienen tolerancia a termogenicos estandar. 90 capsulas por envase. Combinar con dieta hipocalorica y ejercicio para mejores resultados. No recomendado como primer termogenico.',165000,'images/shop/lipo-xtreme.webp','90 capsulas','90 caps',null,8,false],
    ['lipodrene-elite','Lipodrene Elite','hi-tech','quemadores','La formulacion premium de la linea Lipodrene con ingredientes exclusivos de Hi-Tech Pharma. Disenado para el control de peso avanzado con enfoque en preservacion muscular durante deficit calorico. 90 capsulas para ciclo completo. Ideal para fases de definicion donde se busca perder grasa sin sacrificar masa magra.',165000,'images/shop/lipo-elite.png','90 capsulas','90 caps',null,8,false],
    ['burner-stack','Burner Stack','nutramerican','quemadores','Stack quemador de grasa de Nutramerican con formula sinergica de multiples ingredientes termogenicos y lipoliticos. Actua sobre metabolismo basal, oxidacion de acidos grasos y control de apetito. 60 capsulas para un ciclo de 30 dias. Opcion accesible para quienes inician un protocolo de definicion con apoyo de suplementacion.',140000,'images/shop/burner-stack.png','60 capsulas','60 caps',null,10,false],

    // VITAMINAS
    ['musclevite','MuscleVite Multivitaminico','hi-tech','vitaminas','Multivitaminico de grado atletico con 120 tabletas formulado especificamente para deportistas de alto rendimiento. Cubre micronutrientes clave que se depletan con el ejercicio intenso: zinc, magnesio, vitaminas del complejo B y antioxidantes. A diferencia de multivitaminicos genericos, incluye dosis basadas en las necesidades de atletas, no de personas sedentarias. Un seguro nutricional para quienes entrenan 4+ dias por semana.',170000,'images/shop/musclevite.png','120 tabletas','120 tabs',null,12,false],
    ['multi-core','Multi Core Multivitaminico','hard-supps','vitaminas','Multivitaminico esencial con minerales clave para deportistas en presentacion de 60 capsulas. Cubre las necesidades basicas de micronutrientes sin sobredosificar. Formula directa y economica para quienes buscan un multivitaminico confiable sin pagar de mas. Ideal como base de suplementacion para cualquier persona activa.',95000,'images/shop/multi-core.png','60 capsulas','60 caps',null,15,false],
    ['omega-3','Omega 3','macroblends','vitaminas','Aceite de pescado con EPA y DHA de alta concentracion en 60 softgels. Los omega-3 tienen evidencia robusta en salud cardiovascular, reduccion de inflamacion sistemica y salud articular. Esencial para atletas que entrenan con alta frecuencia y volumen. Dosis recomendada: 2 capsulas diarias con comida para maximizar la absorcion.',70000,'images/shop/omega-3.png','60 softgels','60 caps',null,20,false],
    ['fish-oil','Fish Oil','hi-tech','vitaminas','Aceite de pescado premium de Hi-Tech Pharma con 90 softgels y ratio optimizado de acidos grasos omega 3-6-9. Soporta la recuperacion muscular, funcion cerebral y salud articular. Presentacion de 3 meses de duracion al tomar 1 capsula diaria. Procesado molecularmente para eliminar metales pesados y contaminantes.',90000,'images/shop/fish-oil.png','90 softgels','90 caps',null,10,false],
    ['greens-mix','Greens Mix','macroblends','vitaminas','Superalimentos verdes en polvo con 30 servicios. Concentrado de vegetales, frutas, enzimas digestivas y probioticos en un solo scoop. Disenado para complementar la ingesta de micronutrientes en dietas altas en proteina que pueden carecer de vegetales. No reemplaza el consumo de vegetales reales, pero ayuda a cubrir brechas nutricionales. Mezclar con agua o agregar al batido de proteina.',99000,'images/shop/greens-mix.png','30 servicios','250g',null,8,false],
    ['liver-rx','Liver-RX','hi-tech','vitaminas','Protector hepatico avanzado de Hi-Tech Pharma con silimarina, NAC y complejo hepatoprotector. Formulado para apoyar la funcion del higado en personas que usan suplementacion intensiva o dietas altas en proteina. La silimarina (extracto de cardo mariano) tiene evidencia en regeneracion de celulas hepaticas. 90 capsulas para un ciclo completo de 30 dias. Esencial en cualquier protocolo de salud integral.',110000,'images/shop/liver-rx.png','90 capsulas','90 caps',null,10,false],
    ['milk-thistle','Milk Thistle / Silimarina','hi-tech','vitaminas','Silimarina pura (extracto estandarizado de cardo mariano) en 90 capsulas. Uno de los protectores hepaticos mas estudiados con decadas de investigacion clinica. Actua como antioxidante hepatico y promueve la regeneracion celular del higado. Opcion economica y efectiva para proteccion hepatica basica. Recomendado como suplemento de mantenimiento a largo plazo.',100000,'images/shop/milk-thistle.png','90 capsulas','90 caps',null,15,false],

    // NATURALES
    ['turkesterone-650','Turkesterone 650','hi-tech','naturales','Ecdysterona de origen vegetal en capsulas de 650mg — uno de los potenciadores naturales mas investigados en los ultimos anos. Estudios preliminares sugieren mejoras en sintesis proteica y ganancia de masa magra sin afectar el eje hormonal. No es un esteroide ni tiene efectos secundarios hormonales. 60 capsulas por envase. Para atletas naturales que buscan optimizar sus resultados dentro del marco legal y seguro.',200000,'images/shop/turkesterone.webp','60 capsulas','60 caps',null,10,true],
    ['ashwagandha','Ashwagandha KSM-66','hi-tech','naturales','Extracto estandarizado KSM-66 de ashwagandha, el adaptogeno con mayor respaldo cientifico disponible. Estudios clinicos demuestran reduccion de cortisol del 27-30%, mejora en testosterona, calidad del sueno y recuperacion del entrenamiento. Patente KSM-66 garantiza concentracion y potencia del extracto. 60 capsulas para un ciclo de 2 meses. Recomendado especialmente para personas con alto estres y recuperacion comprometida.',160000,'images/shop/ashwagandha.png','60 capsulas','60 caps',null,12,false],
    ['tonkat-ali','Tonkat Ali 100:1','hi-tech','naturales','Extracto de Tongkat Ali (Eurycoma longifolia) en ratio de concentracion 100:1 — uno de los mas potentes del mercado. Investigaciones muestran mejoras en niveles de testosterona libre, composicion corporal y bienestar general en hombres. 60 capsulas para un ciclo de 2 meses. Sinergia comprobada con ashwagandha cuando se combinan. Para hombres mayores de 25 anos que buscan optimizacion hormonal natural.',150000,'images/shop/tonkat-ali.png','60 capsulas','60 caps',null,8,false],
    ['shilajit','Extracto de Shilajit','hi-tech','naturales','Shilajit purificado del Himalaya, rico en acido fulvico y mas de 80 minerales traza bioactivos. Investigaciones indican mejoras en niveles de testosterona, produccion de energia mitocondrial y absorcion de nutrientes. 60 capsulas purificadas libre de metales pesados. Suplemento ancestral con respaldo cientifico moderno para vitalidad general y rendimiento fisico.',130000,'images/shop/shilajit.png','60 capsulas','60 caps',null,10,false],
    ['berberine','Berberine','hi-tech','naturales','Berberina de alta pureza con 90 capsulas por envase. Compuesto con evidencia robusta en control glucemico, sensibilidad a la insulina y composicion corporal. Multiples meta-analisis la situan a la par de metformina en algunos parametros metabolicos. Util para fases de recomposicion corporal, manejo de la glucosa y salud metabolica general. Tomar con comidas para mejor absorcion.',130000,'images/shop/berberine.png','90 capsulas','90 caps',null,8,false],
    ['testo-rage','Testo Rage','hard-supps','naturales','Potenciador de testosterona de Hard Supps con formula herbal concentrada. Combina extractos estandarizados de plantas con evidencia en optimizacion hormonal masculina. Disenado para hombres activos que buscan soporte natural para energia, libido y rendimiento deportivo. 60 capsulas para un ciclo de 30 dias. Combinar con entrenamiento de fuerza y sueno adecuado para mejores resultados.',150000,'images/shop/testo-rage.png','60 capsulas','60 caps',null,6,false],
    ['zma','ZMA','hard-supps','naturales','Formula clasica ZMA: Zinc, Magnesio aspartato y Vitamina B6 en proporciones cientificamente validadas. Estudios muestran mejoras en calidad del sueno, recuperacion muscular y mantenimiento de niveles hormonales en atletas. 90 capsulas para 30 dias de uso. Tomar 30 minutos antes de dormir con el estomago vacio. Uno de los suplementos mas costo-efectivos para recuperacion deportiva.',95000,'images/shop/zma.png','90 capsulas','90 caps',null,15,false],
    ['hmb-1000','HMB 1000','hard-supps','naturales','Beta-Hidroxi-Beta-Metilbutirato (HMB) en dosis de 1000mg por capsula. Metabolito de la leucina con evidencia en prevencion del catabolismo muscular, especialmente util durante deficit calorico o periodos de entrenamiento intenso. 90 capsulas por envase. Particularmente beneficioso para atletas en fase de corte que quieren preservar masa muscular mientras pierden grasa.',90000,'images/shop/hmb-1000.png','90 capsulas','90 caps',null,10,false],
    ['l-arginina','L-Arginina','hard-supps','naturales','L-Arginina pura en capsulas, aminoacido precursor directo del oxido nitrico (NO). Promueve vasodilatacion para mejor flujo sanguineo, nutrientes y pump muscular durante el entrenamiento. 60 capsulas por envase. Puede tomarse como pre-entreno (30 min antes) o dividir la dosis entre manana y pre-entreno. Tambien apoya la salud cardiovascular a largo plazo.',80000,'images/shop/l-arginina.png','60 capsulas','60 caps',null,12,false],
    ['resveratrol','Resveratrol','hi-tech','naturales','Antioxidante polifenolico presente en uvas y vino tinto, concentrado en capsulas de alta potencia. Investigaciones asocian el resveratrol con propiedades anti-envejecimiento, salud cardiovascular y recuperacion del ejercicio. 60 capsulas por envase. Complemento de salud general recomendado para atletas que buscan longevidad y proteccion celular contra el estres oxidativo del entrenamiento intenso.',120000,'images/shop/resveratrol.png','60 capsulas','60 caps',null,10,false],

    // COMBOS
    ['combo-bulk','Combo Bulk','wellcore','combos','Pack de volumen WellCore que incluye Proteina + Creatina — los dos suplementos con mayor evidencia para ganancia muscular. Combinacion fundamental para cualquier fase de volumen seria. Ahorro significativo comparado con comprar cada producto por separado. Ideal para quienes inician su protocolo de suplementacion o buscan reponer ambos productos a la vez.',240000,'images/shop/combo-bulk.png',null,null,null,5,false],
    ['combo-pump','Combo Pump','wellcore','combos','Pack de pump WellCore que combina Pre-entreno + L-Arginina para la maxima vasodilatacion y congestion muscular. La arginina como precursora del oxido nitrico, combinada con los vasodilatadores del pre-entreno, genera un efecto sinergico en el pump. Ideal para sesiones de hipertrofia donde la congestion muscular es clave. Ahorro combinado versus compra individual.',235000,'images/shop/combo-pump.png',null,null,null,5,false],

    // NUTRAMERICAN — NUEVOS
    ['bipro-classic-09lb','Proteina Aislada BiPro Classic 0.9 LB','nutramerican','proteinas','Proteina isolate de alta pureza en presentacion compacta de 0.9 libras. Ideal para quienes buscan una proteina aislada sin lactosa, baja en grasa y carbohidratos. BiPro es la marca colombiana de referencia en proteina isolate. Perfecta para viaje o como primera experiencia con proteina aislada.',85000,'images/shop/bipro-09lb.png','12 servicios','0.9 LB',null,10,false],
    ['bipro-classic-2lb','Proteina BiPro Classic 2 LB','nutramerican','proteinas','Proteina aislada BiPro Classic en presentacion de 2 libras, la mas popular de la linea. Formula colombiana con proteina de suero isolate de alta biodisponibilidad y minimo contenido de lactosa. Rendimiento de aproximadamente 30 servicios. Excelente relacion calidad-precio en proteina isolate nacional.',165000,'images/shop/bipro-2lb.png','30 servicios','2 LB',null,12,false],
    ['bipro-capuccino-3lb','Proteina Aislada BiPro Capuccino 3 LB','nutramerican','proteinas','Proteina isolate BiPro en sabor capuccino con crema whisky, presentacion de 3 libras. Sabor premium con la misma calidad de proteina aislada que caracteriza la linea BiPro. Aproximadamente 45 servicios por envase. Ideal para quienes buscan variedad de sabor en su proteina diaria.',225000,'images/shop/bipro-capuccino-3lb.png','45 servicios','3 LB','["Capuccino Crema Whisky"]',8,false],
    ['bipro-pina-3lb','Proteina Aislada BiPro Pina Colada 3 LB','nutramerican','proteinas','Proteina isolate BiPro Classic sabor pina colada en presentacion de 3 libras. Misma formula de alta pureza con un sabor tropical refrescante. 45 servicios aproximados por envase. La presentacion mas rendidora de la linea BiPro.',225000,'images/shop/bipro-pina-3lb.png','45 servicios','3 LB','["Pina Colada"]',8,false],
    ['iso-clean-2lb','Proteina ISO Clean 2 LB','nutramerican','proteinas','Proteina isolate ISO Clean de 2 libras con proceso de filtracion avanzada para maxima pureza. Bajo contenido de grasa, carbohidratos y lactosa. Aproximadamente 30 servicios con alto porcentaje de proteina por scoop. Para atletas que exigen la mayor calidad en su proteina post-entreno.',180000,'images/shop/iso-clean-2lb.webp','30 servicios','2 LB',null,8,false],
    ['megaplex-10lb','Megaplex Creatine Power 10 LB','nutramerican','proteinas','Hipercalorico con creatina incorporada en presentacion de 10 libras. Formulado para fase de volumen con aporte calorico alto, proteina y creatina monohidrato en cada servicio. El mass gainer colombiano mas completo del mercado. Ideal para ectomorfos y atletas con requerimientos caloricos superiores a 3500 kcal diarias.',235000,'images/shop/megaplex-10lb.png','40 servicios','10 LB',null,5,false],
    ['megaplex-2lb','Megaplex Creatine Power 2 LB','nutramerican','proteinas','Version compacta del Megaplex Creatine Power en 2 libras. Hipercalorico con creatina incluida para ganancia de peso y masa muscular. Opcion economica para probar el producto o como complemento puntual en dias de mayor demanda calorica. Aproximadamente 8 servicios por envase.',65000,'images/shop/megaplex-2lb.png','8 servicios','2 LB',null,10,false],
    ['protein-pancake','Protein Pancake & Waffle','nutramerican','proteinas','Mezcla para pancakes y waffles con proteina de alta calidad. Preparacion rapida y facil — solo agregar agua. Opcion practica para desayunos o snacks altos en proteina sin la monotonia de los batidos. El suplemento mas accesible del catalogo a solo $44.000 COP.',44000,'images/shop/protein-pancake.png','10 servicios','500g',null,15,false],

    // HI-TECH PHARMA — NUEVOS
    ['nac','NAC / N-Acetilcisteina','hi-tech','vitaminas','N-Acetilcisteina, precursor del glutation — el antioxidante maestro del cuerpo. Evidencia solida en proteccion hepatica, soporte respiratorio y recuperacion del estres oxidativo del ejercicio intenso. Complemento esencial para protocolos de salud integral. Sinergia comprobada con Milk Thistle y Liver-RX.',100000,'images/shop/nac.png','90 capsulas','90 caps',null,12,false],
    ['vitamina-d3','Vitamina D3','hi-tech','vitaminas','Vitamina D3 de alta potencia de Hi-Tech Pharma. La vitamina D es deficiente en la mayoria de la poblacion y es critica para salud osea, funcion inmune y niveles hormonales optimos. Estudios la asocian con mejores niveles de testosterona y rendimiento deportivo. Suplemento basico que todo atleta deberia considerar.',75000,'images/shop/vitamina-d3.png','90 capsulas','90 caps',null,15,false],
    ['vitamina-c-1000','Vitamina C 1000 MG','hi-tech','vitaminas','Vitamina C en dosis de 1000mg por capsula para soporte inmunologico y antioxidante. El entrenamiento intenso puede deprimir temporalmente el sistema inmune — la vitamina C ayuda a contrarrestar este efecto. Tambien apoya la sintesis de colageno para salud articular. 90 capsulas para 3 meses de uso.',100000,'images/shop/vitamina-c-1000.png','90 capsulas','90 caps',null,15,false],
    ['l-glutamine','L-Glutamina','hi-tech','aminoacidos','Aminoacido mas abundante en el musculo esqueletico. La glutamina se depleta significativamente con el ejercicio intenso y el estres. Apoya la recuperacion muscular, salud intestinal y funcion inmune. Versatil: se puede agregar a cualquier batido sin alterar sabor. Util especialmente en fases de volumen alto de entrenamiento.',140000,'images/shop/l-glutamine.png','60 servicios','300g',null,10,false],
    ['yohimbine-hcl','Yohimbine HCL','hi-tech','quemadores','Yohimbina clorhidrato, antagonista de receptores alfa-2 adrenergicos con evidencia en movilizacion de grasa rebelde. Particularmente efectiva en zonas de dificil reduccion como abdomen bajo y espalda baja. Tomar en ayunas antes de cardio para maxima efectividad. No combinar con otros estimulantes. Solo para usuarios experimentados.',90000,'images/shop/yohimbine.png','90 capsulas','90 caps',null,8,false],
    ['magnesio-glicinato','Glicinato de Magnesio','hi-tech','vitaminas','Magnesio en forma de glicinato — la forma con mayor biodisponibilidad y mejor tolerancia digestiva. El magnesio participa en mas de 300 reacciones enzimaticas incluyendo contraccion muscular, sintesis proteica y calidad del sueno. La mayoria de atletas son deficientes. Tomar antes de dormir para mejor absorcion y calidad de sueno.',100000,'images/shop/magnesio-glicinato.png','90 capsulas','90 caps',null,12,false],
    ['colageno-peptidos','Peptidos de Colageno','hi-tech','vitaminas','Peptidos de colageno hidrolizado de Hi-Tech Pharma para salud articular, piel y tejido conectivo. El colageno representa el 30% de la proteina total del cuerpo y su produccion disminuye con la edad. Estudios muestran mejoras en dolor articular y elasticidad de la piel con suplementacion constante. Especialmente recomendado para atletas mayores de 30 anos.',190000,'images/shop/colageno-peptidos.png','30 servicios','300g',null,8,false],
    ['lipodrene-hardcore','Lipodrene Hardcore','hi-tech','quemadores','La version mas agresiva de la linea Lipodrene con efedra incluida. Formulacion hardcore para usuarios con alta tolerancia a estimulantes que buscan el maximo efecto termogenico. 90 capsulas por envase. Exclusivamente para atletas experimentados en uso de termogenicos. No usar como primer quemador.',165000,'images/shop/lipo-hardcore.png','90 capsulas','90 caps',null,6,false],
    ['combo-vital','Combo Vital Hi-Tech','hi-tech','combos','Pack de salud integral Hi-Tech con los suplementos esenciales para bienestar general y soporte del atleta. Ahorro especial vs compra individual. Incluye productos de la linea de salud mas vendidos de Hi-Tech Pharma. Ideal para armar tu base de micronutrientes y proteccion hepatica.',270000,'images/shop/combo-vital.png',null,null,null,5,false],

    // HARD SUPPS — NUEVO
    ['combo-preworkout-hs','Combo Pre-Workout Hard Supps','hard-supps','combos','Pack pre-entreno de Hard Supps con todo lo necesario para sesiones de maxima intensidad. Combina productos de la linea Hard Supps a precio especial. Ahorro vs compra individual. Para atletas que confian en Hard Supps como su marca de rendimiento.',160000,'images/shop/combo-preworkout-hs.png','1 pack',null,null,5,false],

    // ACCESORIOS
    ['bandas-resistencia-x5','Bandas de Resistencia Set x5','wellcore','accesorios','Set de 5 bandas elasticas de latex premium con 5 niveles de resistencia progresivos: extra ligera (5 lb), ligera (10 lb), media (15 lb), fuerte (25 lb) y extra fuerte (35 lb). Ideales para calentamiento, activacion glute, rehabilitacion, entrenamiento en casa o como complemento en gym. Incluyen bolsa de transporte. Material duradero con resistencia al desgarre. Codificadas por color para identificacion rapida del nivel.',44900,'images/shop/bandas-resistencia.png',null,'5 bandas',null,30,false],
    ['shaker-wellcore-700ml','Shaker WellCore 700ml','wellcore','accesorios','Mezclador de 700ml con compartimento inferior para guardar scoops de proteina o suplementos en capsulas. Tapa a prueba de fugas con sello de silicona y mecanismo de cierre seguro. Bola mezcladora de acero inoxidable para disolucion perfecta sin grumos. Libre de BPA, apto para lavavajillas. Marcas de medida en ml y oz impresas en el vaso. Diseno WellCore con acabado mate.',24900,'images/shop/shaker-wellcore.png',null,'700ml',null,50,false],
    ['guantes-entrenamiento','Guantes de Entrenamiento','wellcore','accesorios','Guantes de entrenamiento con palma de microfibra de agarre reforzado y respaldo de malla transpirable para ventilacion optima. Cierre de velcro ajustable en la muneca para soporte adicional. Costuras dobles en zonas de alto desgaste. Protegen contra callosidades sin perder sensibilidad en el agarre. Disponibles en tallas S, M, L y XL. Lavables a mano.',29900,'images/shop/guantes-entrenamiento.png',null,null,'["S","M","L","XL"]',25,false],
    ['foam-roller-45cm','Foam Roller 45cm','wellcore','accesorios','Rodillo de espuma de alta densidad de 45cm para liberacion miofascial, recuperacion muscular y movilidad articular. La textura con relieves de masaje facilita el trabajo sobre puntos gatillo y adhesiones fasciales. Ideal para usar como warm-up, cool-down o en dias de recuperacion activa. Soporta hasta 150kg de peso. Estudios muestran que el foam rolling reduce DOMS y mejora el rango de movimiento sin afectar el rendimiento posterior.',39900,'images/shop/foam-roller.png',null,'45cm',null,20,false],

    // DIGITAL
    ['guia-nutricion-wellcore','Guia Nutricion WellCore PDF','wellcore','digital','Guia digital de nutricion de 85 paginas con plan nutricional personalizable por fase (deficit, mantenimiento, superavit). Incluye calculadora de macros paso a paso, 4 plantillas de menu semanal por objetivo, lista de compras optimizada, tablas de equivalencias de alimentos y seccion de FAQ nutricional. Basada en la misma metodologia que usan los coaches WellCore con sus clientes. Formato PDF descargable, acceso inmediato tras la compra. Compatible con cualquier dispositivo.',19900,'images/shop/guia-nutricion.png',null,'PDF 85 pags',null,999,true],
    ['pack-recetas-fitness-50','Pack Recetas Fitness 50+','wellcore','digital','Compilacion de mas de 50 recetas altas en proteina disenadas por el equipo de nutricion WellCore. Cada receta incluye macros exactos por porcion (calorias, proteina, carbohidratos, grasas), tiempo de preparacion, ingredientes accesibles en Latinoamerica y foto de referencia. Categorias: desayunos, almuerzos, cenas, snacks y postres fitness. Todas las recetas se preparan en menos de 30 minutos. Formato PDF descargable, acceso inmediato.',14900,'images/shop/pack-recetas.png',null,'PDF 50+ recetas',null,999,false],
];

// Resolve foreign key lookup maps from what was just seeded/already exists
$brandMap = [];
foreach ($db->query("SELECT id, slug FROM shop_brands")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $brandMap[$row['slug']] = (int)$row['id'];
}

$categoryMap = [];
foreach ($db->query("SELECT id, slug FROM shop_categories")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $categoryMap[$row['slug']] = (int)$row['id'];
}

$prodInserted = 0;
$prodUpdated  = 0;

$stmtProd = $db->prepare("
    INSERT INTO shop_products
        (slug, name, brand_id, category_id, description, price_cop,
         image_url, servings, weight, flavors, stock_status, featured, active)
    VALUES
        (:slug, :name, :brand_id, :category_id, :description, :price_cop,
         :image_url, :servings, :weight, :flavors, :stock_status, :featured, 1)
    ON DUPLICATE KEY UPDATE
        name         = VALUES(name),
        brand_id     = VALUES(brand_id),
        category_id  = VALUES(category_id),
        description  = VALUES(description),
        price_cop    = VALUES(price_cop),
        image_url    = VALUES(image_url),
        servings     = VALUES(servings),
        weight       = VALUES(weight),
        flavors      = VALUES(flavors),
        stock_status = VALUES(stock_status),
        featured     = VALUES(featured),
        active       = 1
");

foreach ($products as [
    $slug, $name, $brandSlug, $categorySlug,
    $description, $priceCop,
    $imageUrl, $servings, $weight, $flavors,
    $stockQty, $featured,
]) {
    $brandId    = isset($brandSlug)    ? ($brandMap[$brandSlug]    ?? null) : null;
    $categoryId = isset($categorySlug) ? ($categoryMap[$categorySlug] ?? null) : null;

    // Derive stock_status from quantity hint
    if ($stockQty > 5) {
        $stockStatus = 'in_stock';
    } elseif ($stockQty > 0) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'out_of_stock';
    }

    try {
        $stmtProd->execute([
            ':slug'         => $slug,
            ':name'         => $name,
            ':brand_id'     => $brandId,
            ':category_id'  => $categoryId,
            ':description'  => $description,
            ':price_cop'    => $priceCop,
            ':image_url'    => $imageUrl,
            ':servings'     => $servings,
            ':weight'       => $weight,
            ':flavors'      => $flavors,
            ':stock_status' => $stockStatus,
            ':featured'     => $featured ? 1 : 0,
        ]);
        // rowCount: 1 = new INSERT, 2 = ON DUPLICATE KEY UPDATE hit, 0 = no change
        $rc = $stmtProd->rowCount();
        if ($rc === 1)     $prodInserted++;
        elseif ($rc >= 2)  $prodUpdated++;
    } catch (PDOException $e) {
        $errors[] = "[product:$slug] " . $e->getMessage();
    }
}

$results[] = "Products inserted: $prodInserted, updated: $prodUpdated, attempted: " . count($products);

// ── 5. FINAL COUNTS ─────────────────────────────────────────────────────────

$totalCats   = (int)$db->query("SELECT COUNT(*) FROM shop_categories")->fetchColumn();
$totalBrands = (int)$db->query("SELECT COUNT(*) FROM shop_brands")->fetchColumn();
$totalProds  = (int)$db->query("SELECT COUNT(*) FROM shop_products WHERE active = 1")->fetchColumn();
$featCount   = (int)$db->query("SELECT COUNT(*) FROM shop_products WHERE featured = 1 AND active = 1")->fetchColumn();

// ── OUTPUT ───────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WellCore — Shop Seed</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            background: #0a0a0a;
            color: #e0e0e0;
            padding: 40px 24px;
            min-height: 100vh;
        }
        .container { max-width: 820px; margin: 0 auto; }
        h1 {
            font-family: 'Bebas Neue', Impact, sans-serif;
            font-size: 2.4rem;
            letter-spacing: 0.08em;
            color: #fff;
            border-left: 4px solid #E31E24;
            padding-left: 16px;
            margin-bottom: 8px;
        }
        .subtitle { color: #888; font-size: 0.8rem; margin-bottom: 32px; padding-left: 20px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: #111113;
            border: 1px solid #222;
            border-top: 3px solid #E31E24;
            padding: 20px 16px;
        }
        .stat-value { font-size: 2rem; font-weight: 700; color: #E31E24; line-height: 1; }
        .stat-label { font-size: 0.7rem; color: #888; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.1em; }
        .section { margin-bottom: 24px; }
        .section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #E31E24;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #222;
        }
        .log-list { list-style: none; }
        .log-list li {
            font-size: 0.8rem;
            padding: 6px 10px;
            border-left: 2px solid #333;
            margin-bottom: 4px;
            color: #b0b0b0;
        }
        .log-list li::before { content: '// '; color: #00D9FF; }
        .error-list li { border-left-color: #E31E24; color: #ff8080; }
        .error-list li::before { content: 'ERR '; color: #E31E24; }
        .badge-ok  { display: inline-block; background: #00D9FF; color: #000; font-size: 0.65rem; font-weight: 700; padding: 2px 8px; margin-left: 8px; }
        .badge-err { display: inline-block; background: #E31E24; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 2px 8px; margin-left: 8px; }
    </style>
</head>
<body>
<div class="container">

    <h1>WELLCORE SHOP SEED</h1>
    <p class="subtitle"><?= date('Y-m-d H:i:s') ?> &mdash; Database: <?= DB_NAME ?></p>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $totalCats ?></div>
            <div class="stat-label">Categorias</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalBrands ?></div>
            <div class="stat-label">Marcas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalProds ?></div>
            <div class="stat-label">Productos Activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $featCount ?></div>
            <div class="stat-label">Destacados</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">
            Resultados
            <?php if (empty($errors)): ?>
                <span class="badge-ok">OK</span>
            <?php else: ?>
                <span class="badge-err"><?= count($errors) ?> ERRORES</span>
            <?php endif; ?>
        </div>
        <ul class="log-list">
            <?php foreach ($results as $line): ?>
                <li><?= htmlspecialchars($line) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="section">
        <div class="section-title">Errores</div>
        <ul class="log-list error-list">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
