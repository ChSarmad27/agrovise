<?php
/*
   ANIMATION SCOPE: PUBLIC PAGE ONLY
   Post-login pages: NOT MODIFIED ✓
   PHP/session logic: NOT MODIFIED ✓
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/tracking.php';

// Get featured products for each category
$featuredProducts = getFeaturedProducts();

// Dynamic Counters
$conn = getDBConnection();

// Record the visit (skipped for bots and logged-in staff)
trackPageView($conn, 'home');
// optimized COUNT queries
$totalProducts = $conn->query("SELECT COUNT(*) FROM products WHERE is_published = TRUE")->fetchColumn();
$totalCategories = $conn->query("SELECT COUNT(DISTINCT category) FROM products WHERE is_published = TRUE")->fetchColumn();

// check if clients table exists
try {
    $happyFarmers = $conn->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    // Default marketing offset if counts are low
    if ($happyFarmers < 1000) {
        $happyFarmers = 1000 + $happyFarmers;
    }
} catch (PDOException $e) {
    $happyFarmers = 1000;
}

// Category chapters for the editorial collection section
$categoryChapters = [
    'insecticides'   => ['no' => '01', 'title' => 'Insecticides',    'line' => 'Precision protection against the pests that threaten a season\'s work.'],
    'weedicides'     => ['no' => '02', 'title' => 'Weedicides',      'line' => 'Selective and total weed control, so only the crop remains.'],
    'fungicides'     => ['no' => '03', 'title' => 'Fungicides',      'line' => 'Defence against blight, rust and rot — from soil to leaf.'],
    'granulars'      => ['no' => '04', 'title' => 'Granulars',       'line' => 'Slow-release nutrition and soil conditioning, measured by the acre.'],
    'micronutrients' => ['no' => '05', 'title' => 'Micronutrients',  'line' => 'Zinc, iron, boron — the trace elements behind every full harvest.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGROVISE — Plow, Grow, Glow | Premium Agriculture Products</title>
    <meta name="description" content="AGROVISE - Your trusted partner for agriculture products including insecticides, weedicides, fungicides, granulars, and micronutrients.">

    <!-- Fonts: display serif + tracked geometric sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ============================================================
           AGROVISE — Harvest Editorial
           Chaptered, cinematic scroll design. Self-contained styles.
           ============================================================ */
        :root {
            --ink: #0f1a0e;            /* deep forest ink */
            --ink-soft: #162414;
            --ivory: #f2efe6;          /* warm gallery ivory */
            --ivory-deep: #e9e4d6;
            --gold: #c2a04f;           /* wheat gold accent */
            --sage: #8a9a82;
            --text-dark: #23291f;
            --text-dark-muted: #5c6355;
            --text-light: #f2efe6;
            --text-light-muted: rgba(242, 239, 230, 0.62);
            --hairline-dark: rgba(15, 26, 14, 0.16);
            --hairline-light: rgba(242, 239, 230, 0.18);
            --serif: 'Cormorant Garamond', Georgia, serif;
            --sans: 'Jost', 'Segoe UI', sans-serif;
            --ease: cubic-bezier(0.22, 1, 0.36, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--sans);
            font-weight: 300;
            background: var(--ivory);
            color: var(--text-dark);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; text-decoration: none; }
        ::selection { background: var(--gold); color: var(--ink); }

        /* ---------- shared editorial atoms ---------- */
        .wrap { width: min(1240px, calc(100% - 48px)); margin-inline: auto; }

        .micro {
            font-family: var(--sans);
            font-size: 0.68rem;
            font-weight: 500;
            letter-spacing: 0.34em;
            text-transform: uppercase;
        }

        .chapter-no {
            font-family: var(--serif);
            font-style: italic;
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.08em;
        }

        .display {
            font-family: var(--serif);
            font-weight: 500;
            line-height: 1.04;
            letter-spacing: -0.015em;
        }

        .btn-line {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            padding: 16px 34px;
            border: 1px solid currentColor;
            font-family: var(--sans);
            font-size: 0.7rem;
            font-weight: 500;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            transition: background 0.45s var(--ease), color 0.45s var(--ease), border-color 0.45s var(--ease);
        }
        .btn-line i { font-size: 0.65rem; transition: transform 0.45s var(--ease); }
        .btn-line:hover i { transform: translateX(6px); }
        .on-dark .btn-line:hover, .btn-line.dark-hover:hover { background: var(--ivory); color: var(--ink); border-color: var(--ivory); }
        .on-light .btn-line:hover { background: var(--ink); color: var(--ivory); border-color: var(--ink); }

        .link-line {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 0.68rem;
            font-weight: 500;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            position: relative;
            padding-bottom: 6px;
        }
        .link-line::after {
            content: '';
            position: absolute;
            left: 0; bottom: 0;
            width: 100%; height: 1px;
            background: currentColor;
            transform: scaleX(0.35);
            transform-origin: left;
            transition: transform 0.5s var(--ease);
        }
        .link-line:hover::after { transform: scaleX(1); }
        .link-line i { font-size: 0.6rem; }

        /* ---------- reveal-on-scroll ---------- */
        .rv { opacity: 0; transform: translateY(34px); transition: opacity 0.9s var(--ease), transform 0.9s var(--ease); }
        .rv.in { opacity: 1; transform: none; }
        .rv-img { overflow: hidden; }
        .rv-img img { transform: scale(1.12); transition: transform 1.4s var(--ease); }
        .rv-img.in img { transform: scale(1); }

        /* ============================================================
           NAVIGATION — minimal hairline bar
           ============================================================ */
        .nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 22px 0;
            transition: background 0.5s var(--ease), padding 0.5s var(--ease), box-shadow 0.5s ease;
            color: var(--ivory);
        }
        .nav .wrap { display: flex; align-items: center; justify-content: space-between; }
        .nav.solid {
            background: rgba(242, 239, 230, 0.92);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            color: var(--ink);
            padding: 14px 0;
            box-shadow: 0 1px 0 var(--hairline-dark);
        }
        .nav-word {
            font-family: var(--sans);
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.42em;
            text-transform: uppercase;
        }
        .nav-word span { color: var(--gold); }
        .nav-links { display: flex; gap: 42px; list-style: none; align-items: center; }
        .nav-links a {
            font-size: 0.66rem;
            font-weight: 500;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            opacity: 0.85;
            transition: opacity 0.3s ease;
            position: relative;
            padding-bottom: 4px;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            left: 0; bottom: 0;
            width: 100%; height: 1px;
            background: currentColor;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s var(--ease);
        }
        .nav-links a:hover { opacity: 1; }
        .nav-links a:hover::after { transform: scaleX(1); }
        .nav-burger {
            display: none;
            background: none; border: 0; color: inherit;
            font-size: 1.1rem; cursor: pointer;
            padding: 6px;
        }
        @media (max-width: 900px) {
            .nav-links {
                position: fixed;
                inset: 0;
                background: var(--ink);
                color: var(--ivory);
                flex-direction: column;
                justify-content: center;
                gap: 34px;
                transform: translateY(-100%);
                transition: transform 0.6s var(--ease);
            }
            .nav-links.open { transform: none; }
            .nav-links a { font-size: 0.85rem; }
            .nav-burger { display: block; z-index: 110; }
        }

        /* ============================================================
           CHAPTER I — HERO
           ============================================================ */
        .hero {
            position: relative;
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            color: var(--text-light);
            background: var(--ink);
            overflow: hidden;
            perspective: 1100px;
        }
        .hero-media, .hero-media::after {
            position: absolute; inset: -4%;
        }
        .hero-media {
            background: url('https://images.unsplash.com/photo-1500382017468-9049fed747ef?q=80&w=2000&auto=format&fit=crop') center 65% / cover no-repeat;
            transform: translate(var(--mx, 0), var(--my, 0)) scale(1.06);
            transition: transform 0.35s ease-out;
            will-change: transform;
        }
        .hero-media::after {
            content: '';
            inset: 0;
            position: absolute;
            background:
                linear-gradient(180deg, rgba(15,26,14,0.55) 0%, rgba(15,26,14,0.18) 40%, rgba(15,26,14,0.82) 100%),
                radial-gradient(120% 60% at 50% 100%, rgba(15,26,14,0.55), transparent 60%);
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            padding: 0 0 9vh;
            transform-style: preserve-3d;
        }
        .hero-eyebrow {
            display: flex; align-items: center; gap: 22px;
            color: var(--gold);
            margin-bottom: 4vh;
        }
        .hero-eyebrow .rule { width: 72px; height: 1px; background: var(--gold); opacity: 0.7; }
        .hero h1 {
            font-size: clamp(3.2rem, 9.5vw, 8.2rem);
            max-width: 12ch;
        }
        .hero h1 em {
            font-style: italic;
            font-weight: 500;
            color: var(--gold);
        }
        .hero-sub {
            max-width: 44ch;
            margin-top: 3.2vh;
            font-size: 0.95rem;
            font-weight: 300;
            letter-spacing: 0.04em;
            color: var(--text-light-muted);
        }
        .hero-actions { display: flex; gap: 26px; align-items: center; margin-top: 4.5vh; flex-wrap: wrap; }

        /* stats — hairline editorial strip, properly centered columns */
        .hero-stats {
            position: relative;
            z-index: 2;
            border-top: 1px solid var(--hairline-light);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            background: rgba(15, 26, 14, 0.35);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        .stat-item {
            text-align: center;
            padding: 26px 12px;
        }
        .stat-item + .stat-item { border-left: 1px solid var(--hairline-light); }
        .stat-number {
            font-family: var(--serif);
            font-weight: 500;
            font-size: clamp(1.9rem, 3.4vw, 2.8rem);
            color: var(--ivory);
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
        }
        .stat-number sup { font-size: 0.5em; color: var(--gold); }
        .stat-label {
            margin-top: 6px;
            font-size: 0.62rem;
            font-weight: 500;
            letter-spacing: 0.32em;
            text-transform: uppercase;
            color: var(--text-light-muted);
        }

        .hero-scrollcue {
            position: absolute;
            right: 38px; bottom: 34vh;
            z-index: 3;
            writing-mode: vertical-rl;
            font-size: 0.6rem;
            letter-spacing: 0.4em;
            text-transform: uppercase;
            color: var(--text-light-muted);
            display: flex; align-items: center; gap: 16px;
        }
        .hero-scrollcue::after {
            content: '';
            width: 1px; height: 64px;
            background: linear-gradient(var(--gold), transparent);
            animation: cue 2.2s var(--ease) infinite;
        }
        @keyframes cue { 0% { transform: scaleY(0); transform-origin: top; } 55% { transform: scaleY(1); transform-origin: top; } 56% { transform-origin: bottom; } 100% { transform: scaleY(0); transform-origin: bottom; } }
        @media (max-width: 900px) { .hero-scrollcue { display: none; } }

        /* ============================================================
           RIBBON — scrolling category marquee
           ============================================================ */
        .ribbon {
            background: var(--ink);
            color: var(--ivory);
            border-block: 1px solid var(--hairline-light);
            overflow: hidden;
            padding: 18px 0;
        }
        .ribbon-track {
            display: flex;
            gap: 0;
            width: max-content;
            animation: ribbon 36s linear infinite;
        }
        .ribbon:hover .ribbon-track { animation-play-state: paused; }
        .ribbon-track span {
            font-family: var(--serif);
            font-style: italic;
            font-size: 1.15rem;
            white-space: nowrap;
            padding: 0 28px;
            color: var(--text-light-muted);
        }
        .ribbon-track span i { color: var(--gold); font-size: 0.5rem; vertical-align: middle; margin-left: 52px; }
        @keyframes ribbon { to { transform: translateX(-50%); } }

        /* ============================================================
           CHAPTER II — THE COLLECTION (editorial plates)
           ============================================================ */
        .collection { background: var(--ivory); padding: clamp(5rem, 12vh, 9rem) 0 clamp(4rem, 9vh, 7rem); }
        .chapter-head { display: flex; align-items: baseline; justify-content: space-between; gap: 24px; margin-bottom: clamp(3rem, 7vh, 5rem); flex-wrap: wrap; }
        .chapter-head .kicker { display: flex; align-items: center; gap: 18px; color: var(--text-dark-muted); }
        .chapter-head .kicker .rule { width: 56px; height: 1px; background: var(--gold); }
        .chapter-head h2 { font-size: clamp(2.4rem, 5.4vw, 4.6rem); margin-top: 14px; }
        .chapter-head h2 em { font-style: italic; color: var(--gold); }
        .chapter-head p { max-width: 34ch; color: var(--text-dark-muted); font-size: 0.9rem; letter-spacing: 0.03em; }

        .plate {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: clamp(2rem, 5vw, 5rem);
            align-items: center;
            padding: clamp(2.6rem, 6vh, 4.4rem) 0;
            border-top: 1px solid var(--hairline-dark);
        }
        .plate:last-of-type { border-bottom: 1px solid var(--hairline-dark); }
        .plate:nth-child(even) .plate-media { order: 2; }
        .plate-media {
            position: relative;
            overflow: hidden;
            aspect-ratio: 4 / 3;
            background: var(--ivory-deep);
        }
        .plate-media img {
            width: 100%; height: 100%;
            object-fit: cover;
            transform: scale(1.01);
            transition: transform 1.6s var(--ease), filter 1.6s var(--ease);
            filter: saturate(0.88);
        }
        .plate:hover .plate-media img { transform: scale(1.08); filter: saturate(1); }
        .plate-media .plate-no {
            position: absolute;
            top: 18px; left: 22px;
            color: var(--ivory);
            font-family: var(--serif);
            font-style: italic;
            font-size: 1rem;
            letter-spacing: 0.1em;
            z-index: 2;
            text-shadow: 0 1px 12px rgba(15,26,14,0.5);
        }
        .plate-media::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(200deg, rgba(15,26,14,0.32), transparent 45%);
        }
        .plate-body .micro { color: var(--gold); }
        .plate-body h3 {
            font-family: var(--serif);
            font-weight: 500;
            font-size: clamp(2rem, 3.6vw, 3.1rem);
            line-height: 1.05;
            margin: 14px 0 16px;
        }
        .plate-body > p { color: var(--text-dark-muted); font-size: 0.92rem; max-width: 42ch; }
        .plate-list { list-style: none; margin: 26px 0 30px; }
        .plate-list li {
            display: flex; align-items: center; gap: 14px;
            padding: 11px 0;
            border-bottom: 1px solid var(--hairline-dark);
            font-size: 0.82rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 400;
        }
        .plate-list li:first-child { border-top: 1px solid var(--hairline-dark); }
        .plate-list li i { font-size: 0.42rem; color: var(--gold); }
        @media (max-width: 900px) {
            .plate { grid-template-columns: 1fr; gap: 1.6rem; }
            .plate:nth-child(even) .plate-media { order: 0; }
        }

        /* ============================================================
           CHAPTER III — MAISON / ABOUT (dark editorial split)
           ============================================================ */
        .maison {
            background: var(--ink);
            color: var(--text-light);
            padding: clamp(5rem, 13vh, 9.5rem) 0;
            position: relative;
            overflow: hidden;
        }
        .maison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: clamp(2.5rem, 6vw, 6rem);
            align-items: center;
        }
        .maison-media { position: relative; }
        .maison-media .frame {
            overflow: hidden;
            aspect-ratio: 3 / 4;
        }
        .maison-media .frame img { width: 100%; height: 100%; object-fit: cover; filter: saturate(0.9); }
        .maison-media .caption {
            margin-top: 14px;
            font-family: var(--serif);
            font-style: italic;
            font-size: 0.92rem;
            color: var(--text-light-muted);
        }
        .maison-media::before {
            content: '';
            position: absolute;
            inset: 26px -26px -26px 26px;
            border: 1px solid var(--hairline-light);
            pointer-events: none;
        }
        .maison-body .micro { color: var(--gold); }
        .maison-body h2 { font-size: clamp(2.3rem, 4.6vw, 3.9rem); margin: 18px 0 26px; }
        .maison-body h2 em { font-style: italic; color: var(--gold); }
        .maison-body p { color: var(--text-light-muted); font-size: 0.94rem; max-width: 50ch; margin-bottom: 18px; }
        .maison-quote {
            font-family: var(--serif);
            font-style: italic;
            font-weight: 500;
            font-size: clamp(1.25rem, 2vw, 1.6rem);
            color: var(--ivory);
            border-left: 1px solid var(--gold);
            padding-left: 26px;
            margin: 34px 0;
            max-width: 40ch;
            line-height: 1.4;
        }
        @media (max-width: 900px) { .maison-grid { grid-template-columns: 1fr; } .maison-media::before { display: none; } }

        /* ============================================================
           CHAPTER IV — PILLARS (hairline value grid)
           ============================================================ */
        .pillars { background: var(--ivory); padding: clamp(5rem, 12vh, 9rem) 0; }
        .pillars-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border: 1px solid var(--hairline-dark);
            border-right: 0;
        }
        .pillar {
            border-right: 1px solid var(--hairline-dark);
            padding: clamp(1.8rem, 3vw, 3rem);
            min-height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 30px;
            transition: background 0.5s var(--ease), color 0.5s var(--ease);
        }
        .pillar:hover { background: var(--ink); color: var(--ivory); }
        .pillar .chapter-no { color: var(--gold); }
        .pillar h3 { font-family: var(--serif); font-weight: 500; font-size: 1.55rem; margin-bottom: 10px; }
        .pillar p { font-size: 0.84rem; color: var(--text-dark-muted); transition: color 0.5s var(--ease); }
        .pillar:hover p { color: var(--text-light-muted); }
        @media (max-width: 900px) { .pillars-grid { grid-template-columns: 1fr 1fr; border-right: 1px solid var(--hairline-dark); } .pillar { border-bottom: 1px solid var(--hairline-dark); border-right: 0; min-height: 0; } }

        /* ============================================================
           CTA STRIP
           ============================================================ */
        .cta-strip {
            position: relative;
            color: var(--ivory);
            text-align: center;
            padding: clamp(6rem, 16vh, 11rem) 0;
            background: url('https://images.unsplash.com/photo-1625246333195-78d9c38ad449?q=80&w=2000&auto=format&fit=crop') center / cover fixed no-repeat;
        }
        @supports (-webkit-touch-callout: none) { .cta-strip { background-attachment: scroll; } }
        .cta-strip::before {
            content: '';
            position: absolute; inset: 0;
            background: rgba(15, 26, 14, 0.72);
        }
        .cta-strip .wrap { position: relative; z-index: 2; }
        .cta-strip .micro { color: var(--gold); }
        .cta-strip h2 { font-size: clamp(2.4rem, 5.5vw, 4.8rem); margin: 20px auto 34px; max-width: 18ch; }
        .cta-strip h2 em { font-style: italic; color: var(--gold); }

        /* ============================================================
           FOOTER
           ============================================================ */
        .footer { background: var(--ink); color: var(--text-light-muted); padding: clamp(4rem, 9vh, 6.5rem) 0 0; }
        .footer-word {
            font-family: var(--serif);
            font-weight: 500;
            font-size: clamp(3rem, 10vw, 7.5rem);
            color: var(--ivory);
            letter-spacing: 0.08em;
            line-height: 1;
            border-bottom: 1px solid var(--hairline-light);
            padding-bottom: clamp(2rem, 5vh, 3.5rem);
            margin-bottom: clamp(2rem, 5vh, 3.5rem);
        }
        .footer-word span { color: var(--gold); font-style: italic; }
        .footer-cols {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1.2fr;
            gap: clamp(2rem, 4vw, 4rem);
            padding-bottom: clamp(2.5rem, 6vh, 4rem);
        }
        .footer h4 { color: var(--ivory); font-size: 0.66rem; font-weight: 500; letter-spacing: 0.32em; text-transform: uppercase; margin-bottom: 22px; }
        .footer ul { list-style: none; }
        .footer ul li { margin-bottom: 12px; }
        .footer ul a, .footer-contact li {
            font-size: 0.84rem; font-weight: 300;
            transition: color 0.3s ease;
        }
        .footer ul a:hover { color: var(--gold); }
        .footer-brand p { font-size: 0.88rem; max-width: 34ch; margin-bottom: 24px; }
        .footer-social { display: flex; gap: 14px; }
        .footer-social a {
            width: 38px; height: 38px;
            border: 1px solid var(--hairline-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.78rem;
            transition: background 0.4s var(--ease), color 0.4s var(--ease), border-color 0.4s var(--ease);
        }
        .footer-social a:hover { background: var(--gold); border-color: var(--gold); color: var(--ink); }
        .footer-contact { list-style: none; }
        .footer-contact li { display: flex; gap: 14px; margin-bottom: 14px; align-items: baseline; }
        .footer-contact i { color: var(--gold); font-size: 0.75rem; }
        .footer-base {
            border-top: 1px solid var(--hairline-light);
            padding: 26px 0;
            display: flex; justify-content: space-between; align-items: center; gap: 18px; flex-wrap: wrap;
            font-size: 0.68rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .footer-base a:hover { color: var(--gold); }
        @media (max-width: 900px) { .footer-cols { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 560px) { .footer-cols { grid-template-columns: 1fr; } }

        /* ---------- cursor dot (fine pointers only) ---------- */
        .cursor-dot, .cursor-ring {
            position: fixed;
            top: 0; left: 0;
            border-radius: 50%;
            pointer-events: none;
            z-index: 999;
            transform: translate(-50%, -50%);
        }
        .cursor-dot { width: 5px; height: 5px; background: var(--gold); }
        .cursor-ring {
            width: 34px; height: 34px;
            border: 1px solid rgba(194, 160, 79, 0.55);
            transition: width 0.3s var(--ease), height 0.3s var(--ease), border-color 0.3s ease;
        }
        .cursor-ring.grow { width: 58px; height: 58px; border-color: var(--gold); }
        @media (pointer: coarse) { .cursor-dot, .cursor-ring { display: none; } }

        /* ---------- reduced motion ---------- */
        @media (prefers-reduced-motion: reduce) {
            *, ::before, ::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
            .rv, .rv-img img { opacity: 1 !important; transform: none !important; }
            .cursor-dot, .cursor-ring { display: none; }
            .cta-strip { background-attachment: scroll; }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="nav" id="nav">
        <div class="wrap">
            <a href="index.php" class="nav-word">AGRO<span>VISE</span></a>
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php">Home</a></li>
                <li><a href="#collection">The Collection</a></li>
                <li><a href="#maison">Our Story</a></li>
                <li><a href="#pillars">Craft</a></li>
                <li><a href="admin/login.php"><i class="fas fa-lock" style="font-size:0.55rem; margin-right:8px;" aria-hidden="true"></i>Admin</a></li>
            </ul>
            <button class="nav-burger" id="navBurger" aria-label="Menu" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
        </div>
    </nav>

    <!-- CHAPTER I · HERO -->
    <header class="hero on-dark" id="hero">
        <div class="hero-media" id="heroMedia" role="img" aria-label="Sunlit crop field at golden hour"></div>

        <div class="hero-scrollcue">Scroll</div>

        <div class="wrap hero-inner" id="heroInner">
            <div class="hero-eyebrow rv">
                <span class="rule"></span>
                <span class="micro">Agrovise &middot; Premium Agriculture &middot; Chapter Nº 01</span>
            </div>
            <h1 class="display rv" style="transition-delay:.08s;">Plow. Grow.<br><em>Glow.</em></h1>
            <p class="hero-sub rv" style="transition-delay:.16s;">
                A considered collection of crop protection and plant nutrition —
                insecticides to micronutrients, selected for the fields that feed us.
            </p>
            <div class="hero-actions rv" style="transition-delay:.24s;">
                <a href="#collection" class="btn-line">Explore the Collection <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                <a href="#maison" class="link-line">Our Story</a>
            </div>
        </div>

        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-number" data-target="<?php echo (int)$totalProducts; ?>">0<sup>+</sup></div>
                <div class="stat-label">Products</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" data-target="<?php echo (int)$totalCategories; ?>">0<sup>+</sup></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" data-target="<?php echo (int)$happyFarmers; ?>">0<sup>+</sup></div>
                <div class="stat-label">Happy Farmers</div>
            </div>
        </div>
    </header>

    <!-- RIBBON -->
    <div class="ribbon" aria-hidden="true">
        <div class="ribbon-track" id="ribbonTrack">
            <span>Insecticides <i class="fas fa-circle"></i></span>
            <span>Weedicides <i class="fas fa-circle"></i></span>
            <span>Fungicides <i class="fas fa-circle"></i></span>
            <span>Granulars <i class="fas fa-circle"></i></span>
            <span>Micronutrients &amp; Fertilizers <i class="fas fa-circle"></i></span>
            <span>Est. Quality — Field Tested <i class="fas fa-circle"></i></span>
        </div>
    </div>

    <!-- CHAPTER II · THE COLLECTION -->
    <section class="collection on-light" id="collection">
        <div class="wrap">
            <div class="chapter-head">
                <div>
                    <div class="kicker rv">
                        <span class="rule"></span>
                        <span class="micro">Chapter Nº 02 — The Collection</span>
                    </div>
                    <h2 class="display rv" style="transition-delay:.08s;">Five families,<br>one <em>harvest</em>.</h2>
                </div>
                <p class="rv" style="transition-delay:.16s;">
                    Every product in our range earns its place — protection and nutrition
                    curated for each stage of cultivation.
                </p>
            </div>

            <?php foreach ($categoryChapters as $slug => $ch): ?>
            <article class="plate">
                <div class="plate-media rv-img">
                    <span class="plate-no">Nº <?php echo $ch['no']; ?></span>
                    <img src="assets/images/categories/<?php echo $slug; ?>.jpg" alt="<?php echo sanitize($ch['title']); ?>" loading="lazy">
                </div>
                <div class="plate-body">
                    <span class="micro rv">The Collection — <?php echo $ch['no']; ?> / 05</span>
                    <h3 class="rv" style="transition-delay:.06s;"><?php echo sanitize(getCategoryName($slug)); ?></h3>
                    <p class="rv" style="transition-delay:.12s;"><?php echo sanitize($ch['line']); ?></p>

                    <ul class="plate-list rv" style="transition-delay:.18s;">
                        <?php if (!empty($featuredProducts[$slug])): ?>
                            <?php foreach (array_slice($featuredProducts[$slug], 0, 3) as $product): ?>
                            <li><i class="fas fa-circle" aria-hidden="true"></i> <?php echo sanitize($product['name']); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><i class="fas fa-circle" aria-hidden="true"></i> New selections arriving</li>
                        <?php endif; ?>
                    </ul>

                    <a href="categories/<?php echo $slug; ?>.php" class="link-line rv" style="transition-delay:.24s;">
                        Discover <?php echo sanitize($ch['title']); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- CHAPTER III · MAISON -->
    <section class="maison on-dark" id="maison">
        <div class="wrap maison-grid">
            <div class="maison-media rv-img">
                <div class="frame">
                    <img src="https://images.unsplash.com/photo-1592982537447-7440770cbfc9?q=80&w=1400&auto=format&fit=crop" alt="Farmer inspecting young crops by hand" loading="lazy">
                </div>
                <p class="caption">In the field — where every AGROVISE product is proven.</p>
            </div>
            <div class="maison-body">
                <span class="micro rv">Chapter Nº 03 — Our Story</span>
                <h2 class="display rv" style="transition-delay:.08s;">A house built<br>on <em>good ground</em>.</h2>
                <p class="rv" style="transition-delay:.14s;">
                    AGROVISE is a trusted name in agriculture, dedicated to supplying farmers with
                    products that enhance crop productivity and stand guard against pests and disease.
                </p>
                <p class="rv" style="transition-delay:.2s;">
                    From soil preparation to harvest, we accompany every stage of cultivation —
                    with formulations chosen for efficacy, and a range that respects the land it serves.
                </p>
                <blockquote class="maison-quote rv" style="transition-delay:.26s;">
                    &ldquo;Plow, Grow, Glow — three words, one promise to the harvest.&rdquo;
                </blockquote>
                <a href="#collection" class="btn-line rv" style="transition-delay:.32s;">Browse Products <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </section>

    <!-- CHAPTER IV · PILLARS -->
    <section class="pillars on-light" id="pillars">
        <div class="wrap">
            <div class="chapter-head">
                <div>
                    <div class="kicker rv">
                        <span class="rule"></span>
                        <span class="micro">Chapter Nº 04 — The Craft</span>
                    </div>
                    <h2 class="display rv" style="transition-delay:.08s;">What we <em>stand</em> by.</h2>
                </div>
            </div>
            <div class="pillars-grid">
                <div class="pillar rv">
                    <span class="chapter-no">Nº 01</span>
                    <div>
                        <h3>Premium Quality</h3>
                        <p>Every product undergoes rigorous quality testing for maximum effectiveness and safety.</p>
                    </div>
                </div>
                <div class="pillar rv" style="transition-delay:.08s;">
                    <span class="chapter-no">Nº 02</span>
                    <div>
                        <h3>Eco-Conscious</h3>
                        <p>High yields balanced with environmental responsibility, for farming that lasts.</p>
                    </div>
                </div>
                <div class="pillar rv" style="transition-delay:.16s;">
                    <span class="chapter-no">Nº 03</span>
                    <div>
                        <h3>Expert Support</h3>
                        <p>Agriculture specialists on hand with guidance and recommendations, season round.</p>
                    </div>
                </div>
                <div class="pillar rv" style="transition-delay:.24s;">
                    <span class="chapter-no">Nº 04</span>
                    <div>
                        <h3>Reliable Delivery</h3>
                        <p>A distribution network that brings the product to the field exactly when it is needed.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-strip on-dark">
        <div class="wrap">
            <span class="micro rv">Begin the Season</span>
            <h2 class="display rv" style="transition-delay:.08s;">The next harvest<br>starts <em>here</em>.</h2>
            <a href="#collection" class="btn-line rv" style="transition-delay:.16s;">Explore the Collection <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="wrap">
            <div class="footer-word rv">AGRO<span>VISE</span></div>
            <div class="footer-cols">
                <div class="footer-brand">
                    <h4>The House</h4>
                    <p>Your trusted partner for premium agriculture products. Plow, Grow, Glow with AGROVISE.</p>
                    <div class="footer-social">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f" aria-hidden="true"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter" aria-hidden="true"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in" aria-hidden="true"></i></a>
                    </div>
                </div>
                <div>
                    <h4>Navigate</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#collection">The Collection</a></li>
                        <li><a href="#maison">Our Story</a></li>
                        <li><a href="#pillars">Craft</a></li>
                        <li><a href="admin/login.php">Admin Login</a></li>
                    </ul>
                </div>
                <div>
                    <h4>The Collection</h4>
                    <ul>
                        <li><a href="categories/insecticides.php">Insecticides</a></li>
                        <li><a href="categories/weedicides.php">Weedicides</a></li>
                        <li><a href="categories/fungicides.php">Fungicides</a></li>
                        <li><a href="categories/granulars.php">Granulars</a></li>
                        <li><a href="categories/micronutrients.php">Micronutrients</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Correspondence</h4>
                    <ul class="footer-contact">
                        <li><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>123 Agriculture Road, Farming District, Country</span></li>
                        <li><i class="fas fa-phone" aria-hidden="true"></i><span>+1 234 567 8900</span></li>
                        <li><i class="fas fa-envelope" aria-hidden="true"></i><span>info@agrovise.com</span></li>
                    </ul>
                </div>
            </div>
            <div class="footer-base">
                <p>&copy; <?php echo date('Y'); ?> AGROVISE &middot; All rights reserved</p>
                <div style="display:flex; gap:28px;">
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const finePointer = window.matchMedia('(pointer: fine)').matches;

        /* ---- Nav: solid on scroll ---- */
        const nav = document.getElementById('nav');
        function onScroll() { nav.classList.toggle('solid', window.scrollY > 40); }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        /* ---- Mobile menu ---- */
        const burger = document.getElementById('navBurger');
        const links = document.getElementById('navLinks');
        burger.addEventListener('click', () => {
            const open = links.classList.toggle('open');
            burger.setAttribute('aria-expanded', open);
            burger.innerHTML = open ? '<i class="fas fa-xmark" aria-hidden="true"></i>' : '<i class="fas fa-bars" aria-hidden="true"></i>';
        });
        links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
            links.classList.remove('open');
            burger.setAttribute('aria-expanded', 'false');
            burger.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
        }));

        /* ---- Ribbon: duplicate track for seamless loop ---- */
        const track = document.getElementById('ribbonTrack');
        if (track) track.innerHTML += track.innerHTML;

        /* ---- Reveal on scroll ---- */
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
            });
        }, { threshold: 0.15 });
        document.querySelectorAll('.rv, .rv-img').forEach(el => io.observe(el));

        /* ---- Stat counters ---- */
        function countUp(el, target) {
            const dur = 1600, t0 = performance.now();
            function tick(t) {
                const p = Math.min((t - t0) / dur, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                el.firstChild.textContent = Math.round(target * eased).toLocaleString();
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }
        const statObs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (!e.isIntersecting) return;
                document.querySelectorAll('.stat-number').forEach(el => {
                    const target = parseInt(el.getAttribute('data-target')) || 0;
                    if (reduceMotion) { el.firstChild.textContent = target.toLocaleString(); }
                    else { countUp(el, target); }
                });
                statObs.disconnect();
            });
        }, { threshold: 0.3 });
        const statsBar = document.querySelector('.hero-stats');
        if (statsBar) statObs.observe(statsBar);

        /* ---- Hero cursor parallax (fine pointers, motion allowed) ---- */
        if (finePointer && !reduceMotion) {
            const hero = document.getElementById('hero');
            const media = document.getElementById('heroMedia');
            const inner = document.getElementById('heroInner');
            let raf = null;
            hero.addEventListener('mousemove', (e) => {
                if (raf) return;
                raf = requestAnimationFrame(() => {
                    const r = hero.getBoundingClientRect();
                    const x = (e.clientX - r.left) / r.width - 0.5;
                    const y = (e.clientY - r.top) / r.height - 0.5;
                    media.style.setProperty('--mx', (x * -18) + 'px');
                    media.style.setProperty('--my', (y * -12) + 'px');
                    inner.style.transform = 'rotateY(' + (x * 2.5) + 'deg) rotateX(' + (-y * 1.8) + 'deg)';
                    raf = null;
                });
            });
            hero.addEventListener('mouseleave', () => {
                media.style.removeProperty('--mx');
                media.style.removeProperty('--my');
                inner.style.transform = '';
            });

            /* ---- Cursor dot + ring ---- */
            const dot = document.createElement('div'); dot.className = 'cursor-dot';
            const ring = document.createElement('div'); ring.className = 'cursor-ring';
            document.body.append(dot, ring);
            let rx = innerWidth / 2, ry = innerHeight / 2, tx = rx, ty = ry;
            window.addEventListener('mousemove', (e) => {
                tx = e.clientX; ty = e.clientY;
                dot.style.left = tx + 'px'; dot.style.top = ty + 'px';
            }, { passive: true });
            (function follow() {
                rx += (tx - rx) * 0.16; ry += (ty - ry) * 0.16;
                ring.style.left = rx + 'px'; ring.style.top = ry + 'px';
                requestAnimationFrame(follow);
            })();
            document.querySelectorAll('a, button').forEach(el => {
                el.addEventListener('mouseenter', () => ring.classList.add('grow'));
                el.addEventListener('mouseleave', () => ring.classList.remove('grow'));
            });
        }

        /* ---- Smooth anchor offset ---- */
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    const top = target.getBoundingClientRect().top + window.scrollY - 70;
                    window.scrollTo({ top, behavior: reduceMotion ? 'auto' : 'smooth' });
                }
            });
        });
    });
    </script>
</body>
</html>
