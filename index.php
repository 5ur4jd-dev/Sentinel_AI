<?php

declare(strict_types=1);

$dashboardUrl = 'public/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sentinel AI turns real business websites into qualified sales opportunities and actionable outreach.">
    <title>Sentinel AI — Real prospecting, less guesswork</title>
    <style>
        :root {
            --ink: #101828;
            --muted: #667085;
            --line: #e4e7ec;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --soft-blue: #eff6ff;
            --green: #12b76a;
            --white: #ffffff;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: #fbfdff;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        a { color: inherit; text-decoration: none; }
        .page { min-height: 100vh; overflow: hidden; }
        .nav, .hero, .section, .footer { width: min(1160px, calc(100% - 40px)); margin: 0 auto; }
        .nav {
            height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 800; letter-spacing: -.03em; font-size: 1.2rem; }
        .brand-mark {
            width: 38px; height: 38px; display: grid; place-items: center;
            color: var(--white); background: var(--ink); border-radius: 12px; font-size: 1.1rem;
        }
        .nav-links { display: flex; align-items: center; gap: 26px; color: var(--muted); font-size: .92rem; }
        .nav-links a:hover { color: var(--ink); }
        .button {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 13px 18px; border-radius: 10px; font-weight: 700; font-size: .92rem;
            border: 1px solid transparent; transition: transform .2s, background .2s;
        }
        .button:hover { transform: translateY(-2px); }
        .button-primary { color: var(--white); background: var(--blue); box-shadow: 0 10px 22px rgba(37, 99, 235, .2); }
        .button-primary:hover { background: var(--blue-dark); }
        .button-light { color: var(--ink); border-color: var(--line); background: var(--white); }
        .hero { padding: 84px 0 72px; display: grid; grid-template-columns: 1.05fr .95fr; gap: 72px; align-items: center; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; color: var(--blue-dark); background: var(--soft-blue); border-radius: 999px; padding: 8px 12px; font-size: .78rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .eyebrow span { width: 7px; height: 7px; border-radius: 50%; background: var(--green); }
        h1 { margin: 20px 0 18px; max-width: 680px; font-size: clamp(3rem, 6vw, 5.5rem); line-height: .98; letter-spacing: -.075em; }
        .hero-copy { max-width: 590px; color: var(--muted); font-size: 1.12rem; line-height: 1.7; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
        .proof { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 28px; color: var(--muted); font-size: .86rem; }
        .proof strong { color: var(--ink); }
        .dashboard-card { position: relative; padding: 18px; border: 1px solid #dbe5f3; border-radius: 24px; background: var(--white); box-shadow: 0 25px 70px rgba(16, 24, 40, .12); transform: rotate(1.5deg); }
        .window-bar { display: flex; gap: 6px; padding: 5px 0 16px; }
        .window-bar i { width: 9px; height: 9px; border-radius: 50%; background: #d0d5dd; }
        .window-bar i:first-child { background: #f97066; } .window-bar i:nth-child(2) { background: #fdb022; } .window-bar i:nth-child(3) { background: #32d583; }
        .mini-header { display: flex; justify-content: space-between; align-items: center; padding: 14px; background: #f8fafc; border-radius: 15px; }
        .mini-logo { font-weight: 800; } .mini-status { color: var(--green); font-size: .75rem; font-weight: 700; }
        .metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }
        .metric { padding: 14px; border: 1px solid var(--line); border-radius: 14px; } .metric small { color: var(--muted); display: block; font-size: .68rem; } .metric strong { display: block; margin-top: 7px; font-size: 1.5rem; }
        .pipeline { margin-top: 12px; padding: 16px; border: 1px solid var(--line); border-radius: 14px; }
        .pipeline-title { display: flex; justify-content: space-between; margin-bottom: 15px; font-weight: 800; font-size: .86rem; }
        .lead { display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; padding: 12px 0; border-top: 1px solid #f2f4f7; font-size: .78rem; } .lead span { color: var(--muted); } .score { color: var(--green); font-weight: 800; }
        .section { padding: 60px 0 90px; }
        .section-heading { max-width: 620px; margin-bottom: 30px; } .section-heading h2 { margin: 12px 0; font-size: clamp(2rem, 4vw, 3.2rem); letter-spacing: -.055em; } .section-heading p { color: var(--muted); line-height: 1.65; }
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .card { padding: 25px; border: 1px solid var(--line); border-radius: 18px; background: var(--white); } .card-icon { font-size: 1.6rem; } .card h3 { margin: 20px 0 8px; } .card p { color: var(--muted); line-height: 1.6; font-size: .94rem; }
        .cta { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding: 34px; border-radius: 22px; background: var(--ink); color: var(--white); } .cta h2 { margin: 0 0 8px; letter-spacing: -.04em; } .cta p { margin: 0; color: #c8d0dc; line-height: 1.5; } .cta .button { background: var(--white); color: var(--ink); }
        .footer { display: flex; justify-content: space-between; gap: 20px; padding: 26px 0 34px; color: var(--muted); font-size: .84rem; border-top: 1px solid var(--line); }
        @media (max-width: 820px) { .nav-links a:not(.button) { display: none; } .hero { grid-template-columns: 1fr; gap: 40px; padding-top: 55px; } .dashboard-card { transform: none; } .cards { grid-template-columns: 1fr; } .cta { flex-direction: column; align-items: flex-start; } }
        @media (max-width: 520px) { .nav, .hero, .section, .footer { width: min(100% - 28px, 1160px); } h1 { font-size: 3.2rem; } .metrics { grid-template-columns: 1fr; } .footer { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="page">
        <nav class="nav">
            <a class="brand" href="index.php"><span class="brand-mark">S</span><span>Sentinel AI</span></a>
            <div class="nav-links">
                <a href="#workflow">How it works</a>
                <a href="#features">Capabilities</a>
                <a class="button button-primary" href="<?= htmlspecialchars($dashboardUrl) ?>">Open dashboard</a>
            </div>
        </nav>

        <main>
            <section class="hero">
                <div>
                    <div class="eyebrow"><span></span> Real prospect intelligence</div>
                    <h1>Find the right leads. Know why they matter.</h1>
                    <p class="hero-copy">Sentinel AI researches real business websites, surfaces evidence-backed opportunities, qualifies prospects, and prepares outreach you can actually send.</p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="<?= htmlspecialchars($dashboardUrl) ?>">Start researching <span>→</span></a>
                        <a class="button button-light" href="#workflow">See the workflow</a>
                    </div>
                    <div class="proof"><span><strong>Real URLs</strong> verified before saving</span><span><strong>Human review</strong> before sending</span></div>
                </div>
                <div class="dashboard-card" aria-label="Sentinel AI dashboard preview">
                    <div class="window-bar"><i></i><i></i><i></i></div>
                    <div class="mini-header"><span class="mini-logo">Sentinel AI</span><span class="mini-status">● Research active</span></div>
                    <div class="metrics"><div class="metric"><small>Leads found</small><strong>24</strong></div><div class="metric"><small>Audited</small><strong>18</strong></div><div class="metric"><small>Qualified</small><strong>07</strong></div></div>
                    <div class="pipeline"><div class="pipeline-title"><span>Priority prospects</span><span style="color:#667085">Today</span></div><div class="lead"><div><strong>Northstar Dental</strong><br><span>Website opportunity detected</span></div><div class="score">92</div><div><strong>Harbor & Co.</strong><br><span>Contact path needs work</span></div><div class="score">84</div><div><strong>Atlas Fitness</strong><br><span>Local SEO opportunity</span></div><div class="score">78</div></div></div>
                </div>
            </section>

            <section class="section" id="workflow">
                <div class="section-heading"><div class="eyebrow">A practical workflow</div><h2>From public signal to useful conversation.</h2><p>Every step is tied to an actual lead record, fetched website evidence, and a clear next action.</p></div>
                <div class="cards">
                    <article class="card"><div class="card-icon">⌕</div><h3>1. Research</h3><p>Find target businesses and verify that their websites resolve before they enter your pipeline.</p></article>
                    <article class="card"><div class="card-icon">◈</div><h3>2. Understand</h3><p>Audit page structure, SEO signals, mobile readiness, and conversion paths using the live website.</p></article>
                    <article class="card"><div class="card-icon">↗</div><h3>3. Act</h3><p>Review personalized outreach, send it through your SMTP account, and track real replies.</p></article>
                </div>
            </section>

            <section class="section" id="features">
                <div class="cta"><div><h2>Ready to work with real leads?</h2><p>Open the working dashboard and run your first research cycle.</p></div><a class="button" href="<?= htmlspecialchars($dashboardUrl) ?>">Open Sentinel AI →</a></div>
            </section>
        </main>

        <footer class="footer"><span>© <?= date('Y') ?> Sentinel AI</span><span>Research clearly. Reach out responsibly.</span></footer>
    </div>
</body>
</html>
