<?php
// === SEO / i18n head block ===
// Le view possono impostare $SEO = ['title'=>..., 'desc'=>..., 'robots'=>..., 'canonical'=>..., 'jsonld'=>...]
// prima di includere questo file.
$SEO = $SEO ?? [];
$seoPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$seoTitle = $SEO['title'] ?? t('meta.defaultTitle');
$seoDesc = $SEO['desc'] ?? t('meta.defaultDesc');
$seoRobots = $SEO['robots'] ?? (str_starts_with($seoPath, '/admin') ? 'noindex,nofollow' : 'index,follow');

$seoScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '') === 'https') ? 'https' : 'http';
$seoHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$seoBase = URL_PATH !== '' ? URL_PATH : $seoScheme . '://' . $seoHost;

$seoQuery = $_GET;
unset($seoQuery['lang']);
$seoQueryStr = http_build_query($seoQuery);

$seoCanonical = $SEO['canonical']
    ?? $seoBase . ($SEO['canonicalPath'] ?? $seoPath) . (I18n::isDefault() ? '' : '?lang=' . I18n::lang());
$seoHrefIt = $seoBase . $seoPath . ($seoQueryStr ? '?' . $seoQueryStr : '');
$seoHrefEn = $seoBase . $seoPath . '?' . ($seoQueryStr ? $seoQueryStr . '&' : '') . 'lang=en';
$seoImage = $seoBase . '/pwa/web-app-manifest-512x512.png';
$seoLocale = I18n::lang() === 'en' ? 'en_US' : 'it_IT';
$seoLocaleAlt = I18n::lang() === 'en' ? 'it_IT' : 'en_US';
?>
	<title><?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?></title>
	<meta name="description" content="<?= htmlspecialchars($seoDesc, ENT_QUOTES, 'UTF-8') ?>">
	<meta name="robots" content="<?= htmlspecialchars($seoRobots, ENT_QUOTES, 'UTF-8') ?>">
	<link rel="canonical" href="<?= htmlspecialchars($seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
	<link rel="alternate" hreflang="it" href="<?= htmlspecialchars($seoHrefIt, ENT_QUOTES, 'UTF-8') ?>">
	<link rel="alternate" hreflang="en" href="<?= htmlspecialchars($seoHrefEn, ENT_QUOTES, 'UTF-8') ?>">
	<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($seoHrefIt, ENT_QUOTES, 'UTF-8') ?>">
	<meta name="theme-color" content="#009E61">
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="ACTV Live">
	<meta property="og:locale" content="<?= $seoLocale ?>">
	<meta property="og:locale:alternate" content="<?= $seoLocaleAlt ?>">
	<meta property="og:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>">
	<meta property="og:description" content="<?= htmlspecialchars($seoDesc, ENT_QUOTES, 'UTF-8') ?>">
	<meta property="og:url" content="<?= htmlspecialchars($seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
	<meta property="og:image" content="<?= htmlspecialchars($seoImage, ENT_QUOTES, 'UTF-8') ?>">
	<meta name="twitter:card" content="summary">
	<meta name="twitter:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>">
	<meta name="twitter:description" content="<?= htmlspecialchars($seoDesc, ENT_QUOTES, 'UTF-8') ?>">
	<meta name="twitter:image" content="<?= htmlspecialchars($seoImage, ENT_QUOTES, 'UTF-8') ?>">
<?php if (!empty($SEO['jsonld'])): ?>
	<script type="application/ld+json"><?= json_encode($SEO['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
	<script>window.ACTV_LANG = <?= json_encode(I18n::lang()) ?>;</script>
	<script src="/js/i18n-dict.js"></script>
	<script src="/js/i18n.js"></script>
	<script src="/js/lang-switcher.js" defer></script>

	<!-- Google tag (gtag.js) — caricato solo dopo consenso cookie -->
	<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	window.actvLoadAnalytics = function () {
		if (window.__gaLoaded) return;
		window.__gaLoaded = true;
		var s = document.createElement('script');
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=G-56Z18G0ZJT';
		document.head.appendChild(s);
		gtag('js', new Date());
		gtag('config', 'G-56Z18G0ZJT');
	};
	try {
		if (localStorage.getItem('cookie_consent_accepted') === 'true') window.actvLoadAnalytics();
	} catch (e) {}
	</script>
	<!-- <script src="/js/time-machine.js"></script> -->
	<link  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.min.js" integrity="sha384-RuyvpeZCxMJCqVUGFI0Do1mQrods/hhxYlcVfGPOfQtPJh0JCw12tUAZ/Mv10S7D" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

	<?php require_once BASE_PATH . '/app/views/common/icons.php'; ?>
	<link rel="stylesheet" href="<?=URL_PATH?>/css/structure/structure-style.css">
	<link rel="stylesheet" href="<?=URL_PATH?>/css/style.css">
	<link rel="stylesheet" href="<?=URL_PATH?>/css/app-shell.css">
	<link rel="stylesheet" href="<?=URL_PATH?>/css/tour.css">

	<!--PWA-->
	<link rel="icon" type="image/png" href="<?=URL_PATH?>/pwa/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="<?=URL_PATH?>/pwa/favicon.svg" />
	<link rel="shortcut icon" href="<?=URL_PATH?>/pwa/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="<?=URL_PATH?>/pwa/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="Actv Live" />
	<link rel="manifest" href="<?=URL_PATH?>/pwa/site.webmanifest" />

	<link rel="stylesheet" href="<?=URL_PATH?>/css/cookie-notice.css">
	<script src="/js/cookie-notice.js"></script>
	<script src="/js/theme.js"></script>
	<script src="/js/offline.js"></script>
	<script src="/js/ui-feedback.js"></script>
	<script src="/js/app-shell.js" defer></script>
	<script>
		window.ACTV_PERF_DIAGNOSTICS = <?= !empty(ENV['ACTV_PERF_DIAGNOSTICS']) && ENV['ACTV_PERF_DIAGNOSTICS'] === '1' ? 'true' : 'false' ?>;
	</script>
	<script src="/js/perfDiagnostics.js" defer></script>

<?php 
//Send globals to JS
if (strpos($_SERVER['REQUEST_URI'], '/api/') === false && strpos($_SERVER['REQUEST_URI'], '/gtfs-test') === false) {
	echo "
	<script>
		sessionStorage.setItem('url', '" . URL_PATH . "');
		sessionStorage.setItem('theme', '" . THEME . "');
	</script>";
}
?>
