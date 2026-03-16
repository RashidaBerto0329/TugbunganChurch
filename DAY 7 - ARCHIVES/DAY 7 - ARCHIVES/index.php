<?php
// church/index.php — Landing Page (Enhanced v2)
if (session_status() === PHP_SESSION_NONE) session_start();
// AFTER — only redirect staff, let parishioners through
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] !== 'parishioner') {
    header('Location: /church/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Lady of Peace and Good Voyage Parish</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Cinzel:wght@400;500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/church/assets/css/style.css">

    <style>
        /* ── NAV QUICK LINKS ───────────────────────────────────── */
        .nav-links {
            display: flex;
            align-items: center;
            gap: 2px;
            flex: 1;
            justify-content: center;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 13px;
            border-radius: 6px;
            font-size: 0.77rem;
            color: rgba(255,255,255,0.65);
            font-family: var(--font-body);
            font-weight: 400;
            letter-spacing: 0.02em;
            transition: color 0.18s, background 0.18s;
            white-space: nowrap;
        }
        .nav-link i { font-size: 0.7rem; opacity: 0.8; }
        .nav-link:hover {
            color: var(--gold-light);
            background: rgba(255,255,255,0.06);
        }
        .nav-link.active { color: var(--gold-light); }
        @media (max-width: 900px) { .nav-links { display: none; } }

        /* ── MASS SCHEDULE SECTION ─────────────────────────────── */
        .mass-section {
            position: relative;
            z-index: 1;
            background: var(--cream);
            padding: 80px 24px 88px;
        }
        .mass-inner {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .section-badge {
            width: 52px; height: 52px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 18px;
        }
        .badge-gold-bg {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #fff;
            box-shadow: 0 4px 20px rgba(201,162,39,0.35);
        }
        .badge-navy-bg {
            background: linear-gradient(135deg, #0f2044, #1a3a70);
            color: var(--gold-light);
            box-shadow: 0 4px 20px rgba(15,32,68,0.3);
        }
        .section-title-dark {
            font-family: var(--font-display);
            font-size: clamp(1.3rem, 3.5vw, 2rem);
            color: #0f2044;
            letter-spacing: 0.04em;
            text-align: center;
            margin-bottom: 12px;
        }
        .section-subtitle-dark {
            font-family: var(--font-serif);
            font-size: clamp(0.95rem, 2vw, 1.08rem);
            color: var(--text-mid);
            text-align: center;
            max-width: 560px;
            line-height: 1.8;
            margin-bottom: 52px;
        }

        /* Sunday Mass grid */
        .mass-grid {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 48px;
        }
        .mass-card {
            background: #fff;
            border: 1.5px solid var(--parchment);
            border-radius: 14px;
            padding: 24px 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.22s var(--ease-out), border-color 0.22s, box-shadow 0.22s;
        }
        .mass-card:hover {
            transform: translateY(-4px);
            border-color: rgba(201,162,39,0.45);
            box-shadow: 0 10px 28px rgba(0,0,0,0.09);
        }
        .mass-num {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #fff;
            font-family: var(--font-display);
            font-size: 0.78rem;
            font-weight: 600;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 10px rgba(201,162,39,0.3);
            flex-shrink: 0;
        }
        .mass-time {
            font-family: var(--font-display);
            font-size: 1.15rem;
            font-weight: 600;
            color: #0f2044;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }
        .mass-label {
            font-size: 0.7rem;
            color: var(--text-soft);
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        /* Office hours + contact */
        .info-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            width: 100%;
            max-width: 760px;
        }
        @media (max-width: 600px) { .info-two-col { grid-template-columns: 1fr; } }

        .info-panel {
            background: #fff;
            border: 1px solid var(--parchment);
            border-radius: 14px;
            padding: 24px 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .info-panel-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .info-panel-icon {
            width: 36px; height: 36px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .icon-gold { background: #fef9c3; color: var(--gold-dark); }
        .icon-teal { background: #ccfbf1; color: #0d6e6e; }
        .icon-blue { background: #dbeafe; color: #1d4ed8; }

        .info-panel-title {
            font-family: var(--font-display);
            font-size: 0.82rem;
            letter-spacing: 0.07em;
            color: #0f2044;
            text-transform: uppercase;
        }
        .info-panel-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 6px 0;
            border-bottom: 1px solid var(--parchment);
            gap: 12px;
        }
        .info-panel-row:last-child { border-bottom: none; }
        .info-panel-row .key {
            font-size: 0.78rem;
            color: var(--text-soft);
            flex-shrink: 0;
        }
        .info-panel-row .val {
            font-size: 0.82rem;
            color: var(--text-dark);
            font-weight: 500;
            text-align: right;
        }
        .closed-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 99px;
            font-size: 0.68rem;
            background: #fee2e2;
            color: #dc2626;
            font-weight: 600;
        }

        /* ── FEES SECTION ───────────────────────────────────────── */
        .fees-section {
            position: relative;
            z-index: 1;
            background: #fff;
            padding: 80px 24px 88px;
            border-top: 1px solid var(--parchment);
            border-bottom: 1px solid var(--parchment);
        }
        .fees-inner {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .fees-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            width: 100%;
        }
        @media (max-width: 900px) {
            .fees-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 580px) {
            .fees-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .fee-card {
            border: 1.5px solid var(--parchment);
            border-radius: 14px;
            padding: 22px 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: transform 0.22s var(--ease-out), border-color 0.22s, box-shadow 0.22s;
            background: var(--cream);
        }
        .fee-card:hover {
            transform: translateY(-3px);
            border-color: rgba(201,162,39,0.4);
            box-shadow: 0 8px 24px rgba(0,0,0,0.07);
        }
        .fee-card-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
            margin-bottom: 6px;
        }
        .fee-card-name {
            font-family: var(--font-display);
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            color: #0f2044;
        }
        .fee-card-amount {
            font-family: var(--font-serif);
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gold-dark);
            line-height: 1;
        }
        .fee-card-note {
            font-size: 0.72rem;
            color: var(--text-soft);
            line-height: 1.5;
        }

        /* ── REQUIREMENTS SECTION ───────────────────────────────── */
        .requirements-section {
            position: relative;
            z-index: 1;
            background: linear-gradient(160deg, #0d2255 0%, #0f2a6b 40%, #153070 70%, #0e2558 100%);
            padding: 80px 24px 88px;
        }
        .req-inner {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ── REQUIREMENTS CAROUSEL ──────────────────────────────── */
        .req-carousel {
            width: 100%;
        }
        .req-dots {
            display: flex;
            gap: 8px;
            margin-bottom: 28px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 6px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .req-dot {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-family: var(--font-display);
            letter-spacing: 0.05em;
            color: rgba(255,255,255,0.55);
            cursor: pointer;
            border: none;
            background: transparent;
            transition: all 0.2s ease;
        }
        .req-dot.active {
            background: rgba(201,162,39,0.18);
            border: 1px solid rgba(201,162,39,0.35);
            color: var(--gold-light);
        }
        .req-dot:hover:not(.active) {
            color: rgba(255,255,255,0.8);
            background: rgba(255,255,255,0.06);
        }

        /* Track */
        .req-track-wrap {
            overflow: hidden;
            border-radius: 14px;
        }
        .req-track {
            display: flex;
            transition: transform 0.55s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform;
        }
        .req-slide {
            min-width: 100%;
            padding: 2px; /* prevent card shadow clip */
        }
        .req-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 22px;
        }
        .req-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 24px 22px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .req-card-title {
            font-family: var(--font-display);
            font-size: 0.78rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--gold-light);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .req-card-title i { font-size: 0.85rem; }
        .req-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .req-list li {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.7);
            line-height: 1.5;
        }
        .req-list li i {
            color: rgba(201,162,39,0.7);
            font-size: 0.65rem;
            margin-top: 4px;
            flex-shrink: 0;
        }
        .req-note {
            margin-top: 18px;
            padding: 10px 14px;
            background: rgba(201,162,39,0.08);
            border-left: 3px solid rgba(201,162,39,0.4);
            border-radius: 0 8px 8px 0;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.55);
            line-height: 1.6;
        }
        .req-note i { color: var(--gold-light); margin-right: 5px; }

        /* Arrows + progress */
        .req-arrows {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-top: 22px;
        }
        .req-arrow {
            width: 38px; height: 38px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.65);
            font-size: 0.78rem;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.18s, border-color 0.18s, color 0.18s, transform 0.18s;
        }
        .req-arrow:hover {
            background: rgba(201,162,39,0.18);
            border-color: rgba(201,162,39,0.4);
            color: var(--gold-light);
            transform: scale(1.08);
        }
        .req-progress {
            width: 160px;
            height: 3px;
            background: rgba(255,255,255,0.1);
            border-radius: 99px;
            overflow: hidden;
        }
        .req-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            border-radius: 99px;
            transition: width 0.55s cubic-bezier(0.16, 1, 0.3, 1);
            width: 25%;
        }

        /* Lead time chips */
        .lead-time-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 36px;
        }
        .lead-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 99px;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.65);
        }
        .lead-chip-icon {
            width: 26px; height: 26px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.68rem;
            flex-shrink: 0;
        }
        .lead-chip strong { color: var(--gold-light); font-weight: 600; }

        /* ── CERT CLAIM NOTICE ──────────────────────────────────── */
        .cert-notice-section {
            background: var(--cream);
            padding: 64px 24px;
            border-top: 1px solid var(--parchment);
        }
        .cert-notice-inner {
            max-width: 740px;
            margin: 0 auto;
            background: #fff;
            border: 1.5px solid rgba(201,162,39,0.3);
            border-radius: 16px;
            padding: 32px 36px;
            display: flex;
            gap: 22px;
            align-items: flex-start;
            box-shadow: 0 4px 20px rgba(201,162,39,0.08);
        }
        .cert-notice-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #fff;
            font-size: 1.1rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 3px 14px rgba(201,162,39,0.35);
        }
        .cert-notice-body h3 {
            font-family: var(--font-display);
            font-size: 0.9rem;
            letter-spacing: 0.06em;
            color: #0f2044;
            margin-bottom: 10px;
        }
        .cert-notice-body p {
            font-size: 0.85rem;
            color: var(--text-mid);
            line-height: 1.75;
        }
        .cert-notice-body p strong {
            color: var(--gold-dark);
        }
        @media (max-width: 560px) {
            .cert-notice-inner { flex-direction: column; padding: 24px 20px; }
        }

        /* ── FAQ ACCORDION ──────────────────────────────────────── */
        .faq-section {
            background: #fff;
            padding: 80px 24px 88px;
            border-top: 1px solid var(--parchment);
        }
        .faq-inner {
            max-width: 720px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .faq-list {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 8px;
        }
        .faq-item {
            border: 1.5px solid var(--parchment);
            border-radius: 12px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .faq-item.open { border-color: rgba(201,162,39,0.4); }
        .faq-q {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            cursor: pointer;
            gap: 14px;
            background: var(--cream);
            transition: background 0.18s;
        }
        .faq-item.open .faq-q { background: #fff; }
        .faq-q-text {
            font-family: var(--font-display);
            font-size: 0.86rem;
            letter-spacing: 0.03em;
            color: #0f2044;
        }
        .faq-chevron {
            color: var(--gold-dark);
            font-size: 0.75rem;
            transition: transform 0.25s var(--ease-out);
            flex-shrink: 0;
        }
        .faq-item.open .faq-chevron { transform: rotate(180deg); }
        .faq-a {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s var(--ease-out), padding 0.25s;
            padding: 0 20px;
            font-size: 0.85rem;
            color: var(--text-mid);
            line-height: 1.75;
        }
        .faq-item.open .faq-a {
            max-height: 300px;
            padding: 0 20px 18px;
        }

        /* ── DIVIDER ornament used between sections ─────────────── */
        .ornament-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 0 24px;
            background: inherit;
        }
        .ornament-divider .dl { flex: 1; max-width: 200px; height: 1px; background: linear-gradient(to right, transparent, var(--parchment)); }
        .ornament-divider .dl.r { background: linear-gradient(to left, transparent, var(--parchment)); }
        .ornament-divider .dc { color: var(--gold); font-size: 0.65rem; opacity: 0.7; }

        /* ── CONTACT FOOTER STRIP ───────────────────────────────── */
        .contact-strip {
            background: #07173a;
            padding: 64px 24px 56px;
            border-top: 1px solid rgba(201,162,39,0.12);
        }
        .contact-strip-inner {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 48px;
        }

        /* Top ornament */
        .contact-ornament {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            max-width: 400px;
        }
        .contact-ornament-line {
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(201,162,39,0.35));
        }
        .contact-ornament-line.r {
            background: linear-gradient(to left, transparent, rgba(201,162,39,0.35));
        }
        .contact-ornament-icon {
            color: var(--gold);
            font-size: 0.75rem;
            opacity: 0.7;
        }

        /* 4-item row */
        .contact-row {
            display: flex;
            align-items: stretch;
            justify-content: center;
            gap: 0;
            width: 100%;
            flex-wrap: wrap;
        }
        .contact-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 0 48px;
            text-align: center;
            flex: 1;
            min-width: 160px;
            max-width: 240px;
        }
        .contact-item-icon {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: rgba(201,162,39,0.10);
            border: 1.5px solid rgba(201,162,39,0.28);
            color: var(--gold-light);
            font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 18px rgba(201,162,39,0.10);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .contact-item:hover .contact-item-icon {
            background: rgba(201,162,39,0.18);
            box-shadow: 0 0 26px rgba(201,162,39,0.22);
        }
        .contact-item-label {
            font-family: var(--font-display);
            font-size: 0.6rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
        }
        .contact-item-value {
            font-family: var(--font-serif);
            font-size: 0.97rem;
            color: rgba(255,255,255,0.82);
            font-weight: 500;
            line-height: 1.5;
        }
        .contact-sep {
            width: 1px;
            background: rgba(255,255,255,0.07);
            align-self: stretch;
            min-height: 60px;
            flex-shrink: 0;
        }
        @media (max-width: 700px) {
            .contact-sep { display: none; }
            .contact-item { padding: 16px 24px; min-width: 140px; border-bottom: 1px solid rgba(255,255,255,0.06); }
            .contact-item:last-child { border-bottom: none; }
            .contact-row { flex-direction: column; align-items: center; gap: 0; }
        }

        /* ── USER DROPDOWN ─────────────────────────────────────────── */
.nav-user-dropdown {
    position: relative;
}
.nav-user-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    border: none;
    font-family: var(--font-body);
    font-size: 0.82rem;
    white-space: nowrap;
}
.nav-user-name {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.nav-user-chevron {
    font-size: 0.65rem;
    transition: transform 0.2s;
}
.nav-user-dropdown.open .nav-user-chevron {
    transform: rotate(180deg);
}
.nav-dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    background: #fff;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 12px;
    min-width: 180px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    overflow: hidden;
    z-index: 999;
    animation: dropdownIn 0.18s ease;
}
@keyframes dropdownIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.nav-user-dropdown.open .nav-dropdown-menu {
    display: block;
}
.nav-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 16px;
    font-size: 0.83rem;
    color: #1e293b;
    text-decoration: none;
    transition: background 0.15s;
}
.nav-dropdown-item i {
    width: 16px;
    text-align: center;
    color: #64748b;
    font-size: 0.8rem;
}
.nav-dropdown-item:hover {
    background: #f8fafc;
}
.nav-dropdown-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 2px 0;
}
.nav-dropdown-logout {
    color: #dc2626;
}
.nav-dropdown-logout i {
    color: #dc2626;
}
.nav-dropdown-logout:hover {
    background: #fff5f5;
}

/* Mobile: dropdown goes full-width */
@media (max-width: 600px) {
    .nav-user-name { display: none; }
    .nav-dropdown-menu {
        right: 0;
        min-width: 160px;
    }
}
    </style>
</head>
<body>

<canvas id="starfield"></canvas>

<!-- ═══ NAV ═══════════════════════════════════════════════════ -->
<nav id="topnav">
    <div class="nav-inner">
        <div class="nav-brand">
            <img src="/church/assets/img/church_logo.png" alt="Parish Seal" class="nav-logo">
            <div class="nav-brand-text">
                <span class="nav-title">Our Lady of Peace</span>
                <span class="nav-sub">& Good Voyage Parish</span>
            </div>
        </div>

        <div class="nav-links">
            <a href="#about" class="nav-link"><i class="fas fa-church"></i> About</a>
            <a href="#mass-schedule" class="nav-link"><i class="fas fa-bell"></i> Mass Schedule</a>
            <a href="#services" class="nav-link"><i class="fas fa-calendar-check"></i> Book</a>
            <a href="#fees" class="nav-link"><i class="fas fa-coins"></i> Fees</a>
            <a href="#requirements" class="nav-link"><i class="fas fa-clipboard-list"></i> Requirements</a>
            <a href="#faq" class="nav-link"><i class="fas fa-circle-question"></i> FAQ</a>
        </div>

<div class="nav-actions">
    <a href="#services" class="nav-btn-ghost">
        <i class="fas fa-calendar-check"></i>
        Book a Service
    </a>
    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="nav-user-dropdown" id="navUserDropdown">
<button class="nav-btn nav-user-btn" onclick="toggleUserDropdown(event)">
    <i class="fas fa-circle-user"></i>
</button>
        <div class="nav-dropdown-menu" id="navDropdownMenu">
            <a href="/church/portal/index.php" class="nav-dropdown-item">
                <i class="fas fa-gauge"></i>
                <span>My Portal</span>
            </a>
            <div class="nav-dropdown-divider"></div>
            <a href="/church/logout.php" class="nav-dropdown-item nav-dropdown-logout">
                <i class="fas fa-arrow-right-from-bracket"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>
    <?php else: ?>
    <a href="/church/login.php" class="nav-btn">
        <i class="fas fa-arrow-right-to-bracket"></i>
        Login
    </a>
    <?php endif; ?>
</div>
    </div>
</nav>

<!-- ═══ HERO ══════════════════════════════════════════════════ -->
<section class="hero">
    <div class="hero-bg">
        <div class="hero-bg-sky"></div>
        <div class="hero-bg-waves"></div>
        <div class="hero-bg-overlay"></div>
    </div>

    <div class="hero-ornament-top">
        <div class="ornament-line"></div>
        <div class="ornament-cross">✦</div>
        <div class="ornament-line"></div>
    </div>

    <div class="hero-content">
        <div class="hero-seal reveal-up" style="--delay: 0.1s">
            <div class="seal-glow"></div>
            <img src="/church/assets/img/church_logo.png" alt="Parish Seal" class="seal-img">
        </div>

        <p class="hero-diocese reveal-up" style="--delay: 0.3s">Roman Catholic Diocese of Zamboanga · Est. 1979</p>

        <h1 class="hero-title reveal-up" style="--delay: 0.45s">
            Our Lady of Peace<br>
            <em>and Good Voyage</em>
        </h1>

        <p class="hero-parish reveal-up" style="--delay: 0.55s">Parish · Tugbungan, Zamboanga City</p>

        <div class="hero-divider reveal-up" style="--delay: 0.65s">
            <span class="divider-line"></span>
            <span class="divider-icon"><i class="fas fa-anchor"></i></span>
            <span class="divider-line"></span>
        </div>

        <p class="hero-system-label reveal-up" style="--delay: 0.75s">Welcome to Our Parish Family</p>

        <div class="hero-features reveal-up" style="--delay: 0.9s">
            <span class="feature-pill"><i class="fas fa-droplet"></i> Baptism</span>
            <span class="feature-pill"><i class="fas fa-ring"></i> Wedding</span>
            <span class="feature-pill"><i class="fas fa-cross"></i> Funeral Mass</span>
            <span class="feature-pill"><i class="fas fa-scroll"></i> Certificates</span>
            <span class="feature-pill"><i class="fas fa-calendar-check"></i> Online Booking</span>
        </div>

<div class="hero-cta reveal-up" style="--delay: 1.05s">
    <a href="#services" class="btn-primary">
        <i class="fas fa-calendar-check"></i>
        Book a Service
    </a>
    <?php if (empty($_SESSION['user_id'])): ?>
    <a href="/church/login.php" class="btn-secondary">
        <i class="fas fa-arrow-right-to-bracket"></i>
        Login
    </a>
    <?php endif; ?>
</div>
    </div>

    <div class="hero-ornament-bottom">
        <div class="ornament-line"></div>
        <span class="ornament-text">Pax et Bonum</span>
        <div class="ornament-line"></div>
    </div>

    <div class="hero-wave">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none">
            <path d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z" fill="#f5f0e8"/>
        </svg>
    </div>
</section>

<!-- ═══ ABOUT STRIP ═══════════════════════════════════════════ -->
<section class="about-strip" id="about">
    <div class="about-inner">
        <div class="about-badge reveal-fade">
            <i class="fas fa-church"></i>
        </div>
        <h2 class="about-title reveal-fade" style="--delay:0.1s">A Home in Faith Since 1979</h2>
        <p class="about-text reveal-fade" style="--delay:0.2s">
            For over 47 years, Our Lady of Peace and Good Voyage Parish has been a place of prayer,
            community, and sacramental life for the people of Tugbungan and beyond. Whether you're
            here to celebrate a Baptism, plan a Wedding, or find information about our parish —
            you're always welcome here.
        </p>
        <div class="info-cards">
            <div class="info-card reveal-up" style="--delay:0.1s">
                <div class="info-card-icon" style="--ic: #3b82f6; --ib: #eff6ff;">
                    <i class="fas fa-scroll"></i>
                </div>
                <h3>Sacramental Records</h3>
                <p>Request copies of your Baptism, Confirmation, or Wedding certificates — all parish records are carefully kept and available upon request.</p>
            </div>
            <div class="info-card reveal-up" style="--delay:0.22s">
                <div class="info-card-icon" style="--ic: #b8933a; --ib: #fdf8ec;">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Parish Community</h3>
                <p>Be part of our growing community. Connect with parish activities, ministries, and volunteer opportunities open to all parishioners.</p>
            </div>
            <div class="info-card reveal-up" style="--delay:0.34s">
                <div class="info-card-icon" style="--ic: #0d9488; --ib: #f0fdfa;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Online Appointments</h3>
                <p>Book a Baptism, Wedding, or Funeral Mass right from this page. Submit your request online and our parish staff will confirm your schedule.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══ MASS SCHEDULE ══════════════════════════════════════════ -->
<section class="mass-section" id="mass-schedule">
    <div class="mass-inner">
        <div class="section-badge badge-gold-bg reveal-fade">
            <i class="fas fa-bell"></i>
        </div>
        <h2 class="section-title-dark reveal-fade" style="--delay:0.1s">Sunday Mass Schedule</h2>
        <p class="section-subtitle-dark reveal-fade" style="--delay:0.2s">
            Five Sunday masses are celebrated at our parish. All are welcome.
        </p>

        <div class="mass-grid">
            <?php
            $masses = ['5:30 AM','7:15 AM','9:00 AM','4:00 PM','5:30 PM'];
            foreach ($masses as $i => $t): ?>
            <div class="mass-card reveal-up" style="--delay:<?= 0.05 * ($i+1) ?>s">
                <div class="mass-num"><?= $i+1 ?></div>
                <div class="mass-time"><?= $t ?></div>
                <div class="mass-label">Sunday Mass</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="info-two-col">
            <!-- Office Hours -->
            <div class="info-panel reveal-up" style="--delay:0.1s">
                <div class="info-panel-header">
                    <div class="info-panel-icon icon-teal"><i class="fas fa-clock"></i></div>
                    <span class="info-panel-title">Office Hours</span>
                </div>
                <div class="info-panel-row">
                    <span class="key">Monday</span>
                    <span class="val"><span class="closed-badge">CLOSED</span></span>
                </div>
                <div class="info-panel-row">
                    <span class="key">Tue – Sun</span>
                    <span class="val">9:00 AM – 12:00 NN</span>
                </div>
                <div class="info-panel-row">
                    <span class="key">&nbsp;</span>
                    <span class="val">1:00 PM – 5:00 PM</span>
                </div>
            </div>
            <!-- Contact Info -->
            <div class="info-panel reveal-up" style="--delay:0.2s">
                <div class="info-panel-header">
                    <div class="info-panel-icon icon-blue"><i class="fas fa-phone"></i></div>
                    <span class="info-panel-title">Contact & Location</span>
                </div>
                <div class="info-panel-row">
                    <span class="key">Telephone</span>
                    <span class="val">062 308-4171</span>
                </div>
                <div class="info-panel-row">
                    <span class="key">Address</span>
                    <span class="val">Tugbungan, Zamboanga City</span>
                </div>
                <div class="info-panel-row">
                    <span class="key">Diocese</span>
                    <span class="val">Roman Catholic Diocese of Zamboanga</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ BOOKING SERVICES ════════════════════════════════════════ -->
<section class="services-section" id="services">
    <div class="services-wave-top">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none">
            <path d="M0,40 C360,0 1080,80 1440,40 L1440,0 L0,0 Z" fill="#f5f0e8"/>
        </svg>
    </div>

    <div class="services-inner">
        <div class="services-header">
            <div class="services-badge reveal-fade">
                <i class="fas fa-hands-praying"></i>
            </div>
            <h2 class="services-title reveal-fade" style="--delay:0.1s">Online Booking for Parishioners</h2>
            <p class="services-subtitle reveal-fade" style="--delay:0.2s">
                Can't visit us in person yet? You can now request a sacramental service online.
                Fill out a short form, and our parish staff will reach out to confirm your appointment.
            </p>
        </div>

        <div class="service-cards">
            <div class="service-card reveal-up" style="--delay:0.1s">
                <div class="service-card-icon baptism-icon"><i class="fas fa-droplet"></i></div>
                <div class="service-card-body">
                    <h3>Baptism</h3>
                    <p>Schedule the sacrament of Baptism for your child. Submit the child's details and your preferred date for the ceremony.</p>
                    <ul class="service-requirements">
                        <li><i class="fas fa-check-circle"></i> Child's registered birth certificate</li>
                        <li><i class="fas fa-check-circle"></i> Parents' marriage contract (if married)</li>
                        <li><i class="fas fa-check-circle"></i> Photocopy of sponsors' baptism certificates</li>
                        <li><i class="fas fa-check-circle"></i> Pre-Jorda Seminar (₱1,500)</li>
                    </ul>
                </div>
                <a href="/church/portal/book_baptism.php" class="service-card-btn">
                    Book Baptism <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="service-card reveal-up" style="--delay:0.22s">
                <div class="service-card-icon wedding-icon"><i class="fas fa-ring"></i></div>
                <div class="service-card-body">
                    <h3>Wedding</h3>
                    <p>Begin your journey toward a Church wedding. Request a schedule for your pre-nuptial interview and wedding ceremony.</p>
                    <ul class="service-requirements">
                        <li><i class="fas fa-check-circle"></i> Baptism & Confirmation certificates (both)</li>
                        <li><i class="fas fa-check-circle"></i> Marriage License & CENOMAR</li>
                        <li><i class="fas fa-check-circle"></i> Pre-Cana Seminar (₱1,500)</li>
                        <li><i class="fas fa-check-circle"></i> Recent 2×2 ID photos (both parties)</li>
                    </ul>
                </div>
                <a href="/church/portal/book_wedding.php" class="service-card-btn">
                    Book Wedding <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="service-card reveal-up" style="--delay:0.34s">
                <div class="service-card-icon funeral-icon"><i class="fas fa-cross"></i></div>
                <div class="service-card-body">
                    <h3>Funeral Mass</h3>
                    <p>Arrange a funeral Mass and burial blessing for your departed loved one. Our staff will guide you through the process.</p>
                    <ul class="service-requirements">
                        <li><i class="fas fa-check-circle"></i> Death certificate with Registry Number</li>
                        <li><i class="fas fa-check-circle"></i> Burial permit (photocopy)</li>
                        <li><i class="fas fa-check-circle"></i> Next of kin contact details</li>
                    </ul>
                </div>
                <a href="/church/portal/book_funeral.php" class="service-card-btn">
                    Book Funeral Mass <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- How it works -->
        <div class="how-it-works reveal-fade" style="--delay:0.2s">
            <p class="hiw-label">How it works</p>
            <div class="hiw-steps">
                <div class="hiw-step">
                    <div class="hiw-num">1</div>
                    <p>Fill out the<br>booking form</p>
                </div>
                <div class="hiw-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="hiw-step">
                    <div class="hiw-num">2</div>
                    <p>Parish staff<br>reviews request</p>
                </div>
                <div class="hiw-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="hiw-step">
                    <div class="hiw-num">3</div>
                    <p>You receive a<br>confirmation</p>
                </div>
                <div class="hiw-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="hiw-step">
                    <div class="hiw-num">4</div>
                    <p>Pay fees at<br>the parish office</p>
                </div>
                <div class="hiw-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="hiw-step">
                    <div class="hiw-num">5</div>
                    <p>Attend your<br>appointment</p>
                </div>
            </div>
        </div>

        <!-- Portal CTA -->
        <div class="portal-cta reveal-up" style="--delay:0.1s">
            <div class="portal-cta-inner">
                <div class="portal-cta-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="portal-cta-text">
                    <h3>Have a booking request already?</h3>
                    <p>Log in to check the status of your appointment or submit a new request.</p>
                </div>
<?php if (empty($_SESSION['user_id'])): ?>
<div class="portal-cta-actions">
    <a href="/church/login.php" class="btn-portal-login">
        <i class="fas fa-arrow-right-to-bracket"></i> Log In
    </a>
    <a href="/church/portal/register.php" class="btn-portal-register">
        <i class="fas fa-user-plus"></i> Register
    </a>
</div>
<?php else: ?>
<div class="portal-cta-actions">
    <a href="/church/portal/index.php" class="btn-portal-login">
        <i class="fas fa-gauge"></i> Go to My Portal
    </a>
</div>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="services-wave-bottom">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none">
            <path d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z" fill="#fff"/>
        </svg>
    </div>
</section>

<!-- ═══ FEES ════════════════════════════════════════════════════ -->
<section class="fees-section" id="fees">
    <div class="fees-inner">
        <div class="section-badge badge-navy-bg reveal-fade">
            <i class="fas fa-coins"></i>
        </div>
        <h2 class="section-title-dark reveal-fade" style="--delay:0.1s">Sacramental Fees</h2>
        <p class="section-subtitle-dark reveal-fade" style="--delay:0.2s">
            Below are the standard fees for sacramental services. All payments are settled in person at the parish office — no online payments are accepted.
        </p>

        <div class="fees-grid">
            <div class="fee-card reveal-up" style="--delay:0.05s">
                <div class="fee-card-icon" style="background:#eff6ff; color:#3b82f6;"><i class="fas fa-droplet"></i></div>
                <div class="fee-card-name">Pre-Jorda Seminar</div>
                <div class="fee-card-amount">₱1,500</div>
                <div class="fee-card-note">Required for Baptism</div>
            </div>
            <div class="fee-card reveal-up" style="--delay:0.1s">
                <div class="fee-card-icon" style="background:#fef9c3; color:#854d0e;"><i class="fas fa-droplet"></i></div>
                <div class="fee-card-name">Mass Baptism</div>
                <div class="fee-card-amount">₱500</div>
                <div class="fee-card-note">Group baptism fee</div>
            </div>
            <div class="fee-card reveal-up" style="--delay:0.15s">
                <div class="fee-card-icon" style="background:#fef9c3; color:#b8933a;"><i class="fas fa-ring"></i></div>
                <div class="fee-card-name">Pre-Cana Seminar</div>
                <div class="fee-card-amount">₱1,500</div>
                <div class="fee-card-note">Required for Wedding</div>
            </div>
            <div class="fee-card reveal-up" style="--delay:0.2s">
                <div class="fee-card-icon" style="background:#ede9fe; color:#7c3aed;"><i class="fas fa-cross"></i></div>
                <div class="fee-card-name">Funeral Mass</div>
                <div class="fee-card-amount">₱2,500</div>
                <div class="fee-card-note">Standard rate</div>
            </div>
            <div class="fee-card reveal-up" style="--delay:0.25s">
                <div class="fee-card-icon" style="background:#f0fdfa; color:#0d6e6e;"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="fee-card-name">Funeral (Indigent)</div>
                <div class="fee-card-amount">₱1,000</div>
                <div class="fee-card-note">Requires indigency certificate</div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ REQUIREMENTS ════════════════════════════════════════════ -->
<section class="requirements-section" id="requirements">
    <div class="req-inner">
        <div class="services-badge reveal-fade" style="margin-bottom:18px;">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <h2 class="services-title reveal-fade" style="--delay:0.1s">Requirements per Sacrament</h2>
        <p class="services-subtitle reveal-fade" style="--delay:0.2s">
            Please prepare these documents before visiting the parish office or submitting a booking.
        </p>

        <!-- Carousel -->
        <div class="req-carousel reveal-up" style="--delay:0.3s">
            <!-- Nav dots -->
            <div class="req-dots">
                <button class="req-dot active" data-slide="0"><i class="fas fa-droplet"></i> Baptism</button>
                <button class="req-dot" data-slide="1"><i class="fas fa-ring"></i> Wedding</button>
                <button class="req-dot" data-slide="2"><i class="fas fa-dove"></i> Confirmation</button>
                <button class="req-dot" data-slide="3"><i class="fas fa-cross"></i> Funeral</button>
            </div>

            <!-- Slide track -->
            <div class="req-track-wrap">
                <div class="req-track">

                    <!-- SLIDE 0: BAPTISM -->
                    <div class="req-slide">
                        <div class="req-panel active" id="req-baptism">
                            <div class="req-card">
                                <div class="req-card-title"><i class="fas fa-file-alt"></i> Documents Required</div>
                                <ul class="req-list">
                                    <li><i class="fas fa-circle"></i> Photocopy of registered Birth Certificate</li>
                                    <li><i class="fas fa-circle"></i> Marriage Contract of parents (if married)</li>
                                    <li><i class="fas fa-circle"></i> Certificate of Permission from your home parish (if not residing within the parish)</li>
                                    <li><i class="fas fa-circle"></i> 3 Certifications of No Record from 3 churches (if child is 2 years old and above)</li>
                                    <li><i class="fas fa-circle"></i> Photocopy of Baptism Certificate of each Sponsor</li>
                                </ul>
                            </div>
                            <div class="req-card">
                                <div class="req-card-title"><i class="fas fa-info-circle"></i> Additional Notes</div>
                                <ul class="req-list">
                                    <li><i class="fas fa-circle"></i> Pre-Jorda Seminar attendance is required (₱1,500)</li>
                                    <li><i class="fas fa-circle"></i> Baptism can be scheduled on weekdays (Tue–Sat) or during Sunday Mass</li>
                                    <li><i class="fas fa-circle"></i> Lead time: at least <strong style="color:var(--gold-light)">2 days</strong> in advance</li>
                                    <li><i class="fas fa-circle"></i> Duration: approximately 1 hour</li>
                                </ul>
                                <div class="req-note"><i class="fas fa-info-circle"></i> Communion is NOT bookable through the portal. It is organized by the Katalista.</div>
                            </div>
                        </div>
                    </div>

                    <!-- SLIDE 1: WEDDING -->
                    <div class="req-slide">
                        <div class="req-panel active" id="req-wedding">
                            <div class="req-card">
                                <div class="req-card-title"><i class="fas fa-file-alt"></i> Documents Required</div>
                                <ul class="req-list">
                                    <li><i class="fas fa-circle"></i> Marriage License & CENOMAR</li>
                                    <li><i class="fas fa-circle"></i> Baptism Certificate with "Marriage Purpose" annotation (both parties)</li>
                                    <li><i class="fas fa-circle"></i> Confirmation Certificate with "Marriage Purpose" annotation (both parties)</li>
                                    <li><i class="fas fa-circle"></i> Recent 2×2 ID photo (both parties)</li>
                                </ul>
                            </div>
                            <div class="req-card">
                                <div class="req-card-title"><i class="fas fa-tasks"></i> Process Requirements</div>
                                <ul class="req-list">
                                    <li><i class="fas fa-circle"></i> Pre-Cana Seminar completion (₱1,500)</li>
                                    <li><i class="fas fa-circle"></i> Canonical Interview (Compisal / Pre-Nuptial Interview)</li>
                                    <li><i class="fas fa-circle"></i> Marriage Banns announced on 3 consecutive Sundays</li>
                                    <li><i class="fas fa-circle"></i> Both parties must be baptized and confirmed Catholics</li>
                                </ul>
                                <div class="req-note"><i class="fas fa-clock"></i> Lead time: at least <strong style="color:var(--gold-light)">2 months</strong> before the wedding date.</div>
                            </div>
                        </div>
                    </div>

                    <!-- SLIDE 2: CONFIRMATION -->
                    <div class="req-slide">
                        <div class="req-panel active" id="req-confirmation">
                            <div class="req-card">
                                <div class="req-card-title"><i class="fas fa-file-alt"></i> Documents Required</div>
                                <ul class="req-list">
                                    <li><i class="fas fa-circle"></i> Birth Certificate (photocopy)</li>
                                    <li><i class="fas fa-circle"></i> Baptismal Certificate (photocopy)</li>
                                    <li><i class="fas fa-circle"></i> First Communion Certificate (photocopy)</li>
                                </ul>
                                <div class="req-note"><i class="fas fa-info-circle"></i> Confirmation schedules are announced by the parish. Contact the parish office for information on upcoming confirmation ceremonies.</div>
                            </div>
                            <div class="req-card">
                                <div class="req-card-title"><i class="fas fa-certificate"></i> Certificates Issued</div>
                                <ul class="req-list">
                                    <li><i class="fas fa-circle"></i> Baptism Certificate</li>
                                    <li><i class="fas fa-circle"></i> Communion Certificate (First Communion only)</li>
                                </ul>
                                <div class="req-note"><i class="fas fa-info-circle"></i> Certificates can only be claimed by the requestor. A representative must present a notarized Authorization Letter.</div>
                            </div>
                        </div>
                    </div>

                    <!-- SLIDE 3: FUNERAL -->
                    <div class="req-slide">
                        <div class="req-panel active" id="req-funeral">
                            <div class="req-card">
                                <div class="req-card-title"><i class="fas fa-file-alt"></i> Documents Required</div>
                                <ul class="req-list">
                                    <li><i class="fas fa-circle"></i> Death Certificate with Registry Number</li>
                                    <li><i class="fas fa-circle"></i> Burial Permit (photocopy)</li>
                                    <li><i class="fas fa-circle"></i> Next of kin contact details</li>
                                </ul>
                            </div>
                            <div class="req-card">
                                <div class="req-card-title"><i class="fas fa-info-circle"></i> Additional Notes</div>
                                <ul class="req-list">
                                    <li><i class="fas fa-circle"></i> The deceased may remain in the church for up to 7 days</li>
                                    <li><i class="fas fa-circle"></i> Lead time: at least <strong style="color:var(--gold-light)">1 day</strong> in advance</li>
                                    <li><i class="fas fa-circle"></i> Indigency certificate required for reduced rate (₱1,000)</li>
                                    <li><i class="fas fa-circle"></i> Standard rate: ₱2,500</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div><!-- .req-track -->
            </div><!-- .req-track-wrap -->

            <!-- Prev / Next arrows -->
            <div class="req-arrows">
                <button class="req-arrow" id="req-prev"><i class="fas fa-chevron-left"></i></button>
                <div class="req-progress">
                    <div class="req-progress-bar" id="req-progress-bar"></div>
                </div>
                <button class="req-arrow" id="req-next"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div><!-- .req-carousel -->

        <!-- Lead time chips -->
        <div class="lead-time-bar reveal-fade" style="--delay:0.4s">
            <div class="lead-chip">
                <div class="lead-chip-icon" style="background:rgba(59,130,246,0.2); color:#93c5fd;"><i class="fas fa-droplet"></i></div>
                Baptism — <strong>2 days</strong> minimum
            </div>
            <div class="lead-chip">
                <div class="lead-chip-icon" style="background:rgba(201,162,39,0.2); color:var(--gold-light);"><i class="fas fa-ring"></i></div>
                Wedding — <strong>2 months</strong> minimum
            </div>
            <div class="lead-chip">
                <div class="lead-chip-icon" style="background:rgba(139,92,246,0.2); color:#c4b5fd;"><i class="fas fa-cross"></i></div>
                Funeral — <strong>1 day</strong> minimum
            </div>
        </div>
    </div>
</section>

<!-- ═══ CERTIFICATE CLAIM NOTICE ════════════════════════════════ -->
<section class="cert-notice-section">
    <div class="cert-notice-inner reveal-up" style="--delay:0.1s">
        <div class="cert-notice-icon"><i class="fas fa-certificate"></i></div>
        <div class="cert-notice-body">
            <h3>Certificate Claim Policy</h3>
            <p>
                Certificates can only be claimed by the <strong>person who requested them</strong>.
                If the requestor is unavailable, the representative must present an
                <strong>Authorization Letter with notarization from an attorney</strong>.
                This policy applies to Baptism Certificates, Communion Certificates, and all
                other sacramental records issued by the parish.
            </p>
        </div>
    </div>
</section>

<!-- ═══ FAQ ════════════════════════════════════════════════════ -->
<section class="faq-section" id="faq">
    <div class="faq-inner">
        <div class="section-badge badge-gold-bg reveal-fade">
            <i class="fas fa-circle-question"></i>
        </div>
        <h2 class="section-title-dark reveal-fade" style="--delay:0.1s">Frequently Asked Questions</h2>
        <p class="section-subtitle-dark reveal-fade" style="--delay:0.2s">
            Common questions about booking, requirements, and parish services.
        </p>

        <div class="faq-list reveal-up" style="--delay:0.3s">
            <div class="faq-item">
                <div class="faq-q">
                    <span class="faq-q-text">How do I book a sacramental service online?</span>
                    <i class="fas fa-chevron-down faq-chevron"></i>
                </div>
                <div class="faq-a">
                    Create a parishioner account by clicking "Register" above. Once logged in, go to the booking portal and choose your desired service (Baptism, Wedding, or Funeral Mass). Fill out the form with all required details, acknowledge the lead time requirement, and submit. Parish staff will review and confirm your appointment.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q">
                    <span class="faq-q-text">Are payments accepted online?</span>
                    <i class="fas fa-chevron-down faq-chevron"></i>
                </div>
                <div class="faq-a">
                    No. This system is for appointment scheduling only. All fees and payments are settled in person at the parish office. Please bring the exact amount when visiting.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q">
                    <span class="faq-q-text">Can I book a Communion ceremony through this portal?</span>
                    <i class="fas fa-chevron-down faq-chevron"></i>
                </div>
                <div class="faq-a">
                    No. First Communion is organized by the Katalista and is not available through the online booking portal. Please contact the parish office directly for Communion-related concerns.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q">
                    <span class="faq-q-text">How far in advance must I book a wedding?</span>
                    <i class="fas fa-chevron-down faq-chevron"></i>
                </div>
                <div class="faq-a">
                    Wedding bookings require a minimum lead time of 2 months. This is to allow time for the Pre-Cana Seminar, Canonical Interview, and the 3-Sunday reading of Marriage Banns. Early booking is strongly encouraged.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q">
                    <span class="faq-q-text">Who can claim a sacramental certificate?</span>
                    <i class="fas fa-chevron-down faq-chevron"></i>
                </div>
                <div class="faq-a">
                    Only the person who requested the certificate may claim it. If they cannot attend, the representative must present a notarized Authorization Letter from an attorney. This policy protects the privacy and integrity of parish records.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q">
                    <span class="faq-q-text">What is the funeral service policy for the deceased remaining in the church?</span>
                    <i class="fas fa-chevron-down faq-chevron"></i>
                </div>
                <div class="faq-a">
                    The body of the deceased may remain in the church for up to 7 days. Please coordinate with the parish office upon booking for the specific schedule and arrangements.
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ CONTACT STRIP ═══════════════════════════════════════════ -->
<section class="contact-strip">
    <div class="contact-strip-inner">

        <!-- Top ornament -->
        <div class="contact-ornament">
            <span class="contact-ornament-line"></span>
            <span class="contact-ornament-icon"><i class="fas fa-anchor"></i></span>
            <span class="contact-ornament-line r"></span>
        </div>

        <!-- 4-item row -->
        <div class="contact-row">
            <div class="contact-item">
                <div class="contact-item-icon"><i class="fas fa-location-dot"></i></div>
                <div class="contact-item-label">Location</div>
                <div class="contact-item-value">Tugbungan,<br>Zamboanga City</div>
            </div>

            <div class="contact-sep"></div>

            <div class="contact-item">
                <div class="contact-item-icon"><i class="fas fa-phone"></i></div>
                <div class="contact-item-label">Telephone</div>
                <div class="contact-item-value">062 308-4171</div>
            </div>

            <div class="contact-sep"></div>

            <div class="contact-item">
                <div class="contact-item-icon"><i class="fas fa-clock"></i></div>
                <div class="contact-item-label">Office Hours</div>
                <div class="contact-item-value">Tue – Sun<br>9AM–12NN · 1PM–5PM</div>
            </div>

            <div class="contact-sep"></div>

            <div class="contact-item">
                <div class="contact-item-icon"><i class="fas fa-church"></i></div>
                <div class="contact-item-label">Diocese</div>
                <div class="contact-item-value">Roman Catholic Diocese<br>of Zamboanga</div>
            </div>
        </div>

    </div>
</section>

<!-- ═══ FOOTER ══════════════════════════════════════════════════ -->
<footer class="site-footer">
    <div class="footer-ornament">
        <span class="ornament-line-gold"></span>
        <span class="footer-cross">✦</span>
        <span class="ornament-line-gold"></span>
    </div>
    <img src="/church/assets/img/church_logo.png" alt="Parish Seal" class="footer-seal">
    <p class="footer-name">Our Lady of Peace and Good Voyage Parish</p>
    <p class="footer-address">Tugbungan, Zamboanga City · Roman Catholic Diocese of Zamboanga</p>
    <p class="footer-copy">&copy; <?= date('Y') ?> Our Lady of Peace and Good Voyage Parish · All Rights Reserved</p>
</footer>

<script src="/church/assets/js/main.js"></script>
<script>
// ── Requirements Carousel ───────────────────────────────────────
(function() {
    const track    = document.querySelector('.req-track');
    const slides   = document.querySelectorAll('.req-slide');
    const dots     = document.querySelectorAll('.req-dot');
    const prevBtn  = document.getElementById('req-prev');
    const nextBtn  = document.getElementById('req-next');
    const progBar  = document.getElementById('req-progress-bar');
    const total    = slides.length;
    let current    = 0;
    let autoTimer  = null;

    function goTo(idx) {
        current = (idx + total) % total;
        track.style.transform = `translateX(-${current * 100}%)`;
        dots.forEach((d, i) => d.classList.toggle('active', i === current));
        progBar.style.width = `${((current + 1) / total) * 100}%`;
    }

    function startAuto() {
        stopAuto();
        autoTimer = setInterval(() => goTo(current + 1), 5000);
    }
    function stopAuto() {
        if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
    }

    dots.forEach((d, i) => {
        d.addEventListener('click', () => { goTo(i); startAuto(); });
    });
    prevBtn.addEventListener('click', () => { goTo(current - 1); startAuto(); });
    nextBtn.addEventListener('click', () => { goTo(current + 1); startAuto(); });

    // Pause on hover
    document.querySelector('.req-carousel').addEventListener('mouseenter', stopAuto);
    document.querySelector('.req-carousel').addEventListener('mouseleave', startAuto);

    // Touch swipe
    let touchStartX = 0;
    document.querySelector('.req-track-wrap').addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    document.querySelector('.req-track-wrap').addEventListener('touchend', e => {
        const diff = touchStartX - e.changedTouches[0].screenX;
        if (Math.abs(diff) > 40) { goTo(diff > 0 ? current + 1 : current - 1); startAuto(); }
    }, { passive: true });

    goTo(0);
    startAuto();
})();

// ── FAQ Accordion ───────────────────────────────────────────────
document.querySelectorAll('.faq-q').forEach(q => {
    q.addEventListener('click', () => {
        const item = q.parentElement;
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
    });
});

// ── Smooth scroll for anchor nav links ─────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if (target) {
            e.preventDefault();
            const offset = 72; // nav height
            const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    });
});

// ── Active nav link on scroll ───────────────────────────────────
(function() {
    const sections = ['about','mass-schedule','services','fees','requirements','faq'];
    const links    = document.querySelectorAll('.nav-link');

    function onScroll() {
        const scrollY = window.pageYOffset + 100;
        let active = '';
        sections.forEach(id => {
            const el = document.getElementById(id);
            if (el && el.offsetTop <= scrollY) active = id;
        });
        links.forEach(l => {
            const href = l.getAttribute('href').replace('#', '');
            l.classList.toggle('active', href === active);
        });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();


// ── User dropdown ───────────────────────────────────────────
function toggleUserDropdown(e) {
    e.stopPropagation();
    document.getElementById('navUserDropdown').classList.toggle('open');
}
document.addEventListener('click', () => {
    const d = document.getElementById('navUserDropdown');
    if (d) d.classList.remove('open');
});
</script>
</body>
</html>