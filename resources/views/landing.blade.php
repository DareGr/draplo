<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Draplo — Your next SaaS, architected by AI</title>
    <meta name="description" content="Describe your SaaS idea, AI generates a complete Laravel scaffold optimized for AI coding agents. Open source, self-hostable.">
    <meta property="og:title" content="Draplo — Draft it. Deploy it.">
    <meta property="og:description" content="AI-generated Laravel project scaffolds for developers.">
    <meta property="og:image" content="/images/og-image.png">
    <meta property="og:url" content="https://draplo.com">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="https://draplo.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .glass-panel {
            background: rgba(31, 31, 35, 0.6);
            backdrop-filter: blur(20px);
        }
        .terminal-window {
            background-color: #08090C;
            border: 1px solid rgba(192, 193, 255, 0.15);
        }
        #hero-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-background text-on-background font-body antialiased">

    {{-- ================================================================== --}}
    {{-- NAVIGATION --}}
    {{-- ================================================================== --}}
    <nav class="fixed top-0 w-full z-50 bg-background/80 backdrop-blur-xl border-b border-outline-variant/15">
        <div class="flex justify-between items-center px-8 h-16 max-w-7xl mx-auto">
            <div class="flex items-center gap-8">
                <a href="/" class="text-xl font-black tracking-tighter text-white uppercase">Draplo</a>
                <div class="hidden md:flex gap-6">
                    <a class="text-primary font-bold border-b-2 border-primary pb-1 text-sm" href="/templates">Templates</a>
                    <a class="text-on-surface-variant hover:text-white transition-colors text-sm" href="#community">Community</a>
                    <a class="text-on-surface-variant hover:text-white transition-colors text-sm" href="https://github.com/draplo/draplo" target="_blank" rel="noopener">GitHub</a>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="/auth/github" class="text-on-surface-variant hover:text-white transition-colors px-4 py-2 text-sm font-medium">Sign In</a>
                <a href="/templates" class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-5 py-2 rounded-md font-bold text-sm hover:opacity-90 transition-opacity">Get Started</a>
            </div>
        </div>
    </nav>

    <main>

        {{-- ================================================================== --}}
        {{-- 1. HERO SECTION --}}
        {{-- ================================================================== --}}
        <section class="relative min-h-screen flex flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-background via-surface-container-low to-background">
            <canvas id="hero-canvas" class="absolute inset-0 w-full h-full" data-enabled="{{ config('app.flags.threejs_hero', true) ? 'true' : 'false' }}"></canvas>

            <div class="relative z-10 max-w-7xl mx-auto px-8 text-center">
                {{-- Status badge --}}
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-surface-container-highest border border-outline-variant/15 mb-8">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    <span class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant">From idea to deployed Laravel SaaS in 10 minutes</span>
                </div>

                <h1 class="text-6xl md:text-7xl lg:text-8xl font-extrabold tracking-tight text-white leading-none">
                    Your next SaaS,<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-primary-container">architected by AI.</span>
                </h1>

                <p class="text-xl text-on-surface-variant mt-6 max-w-2xl mx-auto font-light">
                    Pick a template. Customize in the wizard. AI generates your Laravel scaffold — database, API, docs — optimized for Claude Code and Cursor. Deploy to your own server.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 mt-10 justify-center">
                    <a href="/templates" class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-8 py-3 rounded-md font-bold hover:translate-y-[-2px] transition-transform inline-block">
                        Browse Templates
                    </a>
                    <a href="https://github.com/draplo/draplo" target="_blank" rel="noopener" class="border border-outline-variant/15 text-on-surface px-8 py-3 rounded-md font-medium flex items-center justify-center gap-2 hover:bg-surface-container-high transition-colors">
                        <span class="material-symbols-outlined text-xl">star</span>
                        View on GitHub
                    </a>
                </div>

                {{-- Stats row --}}
                <div class="flex flex-wrap justify-center gap-8 md:gap-16 mt-16 font-mono text-xs uppercase tracking-[0.2em] text-outline">
                    <div class="flex flex-col items-center gap-1">
                        <span class="text-white font-bold text-lg">25</span>
                        <span>SaaS Templates</span>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <span class="text-white font-bold text-lg">95%</span>
                        <span>Ideas Covered</span>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <span class="text-white font-bold text-lg">3 min</span>
                        <span>To Full Scaffold</span>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <span class="text-white font-bold text-lg">$0</span>
                        <span>Self-Host Forever</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================== --}}
        {{-- 2. TECH STACK BAR --}}
        {{-- ================================================================== --}}
        <section class="py-12 bg-surface-container-low border-y border-outline-variant/5">
            <div class="max-w-7xl mx-auto px-8 flex flex-wrap justify-center items-center gap-12 md:gap-16">
                <div class="flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-2xl text-on-surface-variant">deployed_code</span>
                    <span class="font-mono text-xs text-outline">Laravel</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-2xl text-on-surface-variant">database</span>
                    <span class="font-mono text-xs text-outline">PostgreSQL</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-2xl text-on-surface-variant">code</span>
                    <span class="font-mono text-xs text-outline">React</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-2xl text-on-surface-variant">palette</span>
                    <span class="font-mono text-xs text-outline">Tailwind</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-2xl text-on-surface-variant">smart_toy</span>
                    <span class="font-mono text-xs text-outline">Claude</span>
                </div>
                <div class="flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-2xl text-on-surface-variant">cloud</span>
                    <span class="font-mono text-xs text-outline">Coolify</span>
                </div>
            </div>
        </section>

        {{-- ================================================================== --}}
        {{-- 3. HOW IT WORKS --}}
        {{-- ================================================================== --}}
        <section class="py-24 px-8">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row justify-between items-start gap-12">
                    <div class="md:w-1/3">
                        <span class="text-primary font-mono text-xs tracking-widest uppercase">How It Works</span>
                        <h2 class="text-4xl font-extrabold text-white mt-2">Four steps from idea to deployment.</h2>
                    </div>
                    <div class="md:w-2/3 grid grid-cols-1 md:grid-cols-2 gap-12">
                        <div class="group">
                            <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                                <span class="material-symbols-outlined text-2xl text-primary">edit_note</span>
                            </div>
                            <span class="font-mono text-4xl text-outline-variant mb-2 block group-hover:text-primary transition-colors">01</span>
                            <h3 class="text-lg font-bold text-white mt-4">Describe</h3>
                            <p class="text-on-surface-variant text-sm mt-2">Tell the wizard about your SaaS idea. Pick a template, name your models, define your roles.</p>
                        </div>
                        <div class="group">
                            <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                                <span class="material-symbols-outlined text-2xl text-primary">smart_toy</span>
                            </div>
                            <span class="font-mono text-4xl text-outline-variant mb-2 block group-hover:text-primary transition-colors">02</span>
                            <h3 class="text-lg font-bold text-white mt-4">Generate</h3>
                            <p class="text-on-surface-variant text-sm mt-2">AI architects your complete scaffold — migrations, models, API routes, agent docs, and realistic seeders.</p>
                        </div>
                        <div class="group">
                            <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                                <span class="material-symbols-outlined text-2xl text-primary">code</span>
                            </div>
                            <span class="font-mono text-4xl text-outline-variant mb-2 block group-hover:text-primary transition-colors">03</span>
                            <h3 class="text-lg font-bold text-white mt-4">Preview</h3>
                            <p class="text-on-surface-variant text-sm mt-2">Review and edit every generated file. See the full project structure before you commit to it.</p>
                        </div>
                        <div class="group">
                            <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                                <span class="material-symbols-outlined text-2xl text-primary">rocket_launch</span>
                            </div>
                            <span class="font-mono text-4xl text-outline-variant mb-2 block group-hover:text-primary transition-colors">04</span>
                            <h3 class="text-lg font-bold text-white mt-4">Deploy</h3>
                            <p class="text-on-surface-variant text-sm mt-2">Push to GitHub, deploy to your own server. Bring Your Own Server — Hetzner, DigitalOcean, Linode, or Vultr.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================== --}}
        {{-- 4. FEATURED BLUEPRINTS --}}
        {{-- ================================================================== --}}
        <section class="py-24 px-8 bg-surface-container-lowest">
            <div class="max-w-7xl mx-auto">
                <div class="mb-12 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
                    <div>
                        <span class="text-primary font-mono text-xs tracking-widest uppercase">Templates</span>
                        <h2 class="text-4xl font-extrabold text-white mt-2">Production Blueprints</h2>
                        <p class="text-on-surface-variant mt-2">25 industry-specific scaffolds. Pick one, customize it, ship it.</p>
                    </div>
                    <a href="/templates" class="text-primary font-mono text-sm border-b border-primary/30 pb-1 hover:border-primary transition-colors">See All 25 Templates &rarr;</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {{-- Booking Platform --}}
                    <div class="bg-surface-container p-6 rounded-xl hover:bg-surface-container-high transition-colors border border-outline-variant/10">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-2xl text-primary">calendar_month</span>
                        </div>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant">Operations</span>
                        <h3 class="text-lg font-bold text-white mt-1">Booking Platform</h3>
                        <p class="text-on-surface-variant text-sm mt-2 mb-4">Availability calendars, time-slot management, and Stripe Connect for marketplaces.</p>
                        <div class="flex items-center justify-between">
                            <div class="flex gap-2">
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Laravel</span>
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Redis</span>
                            </div>
                            <span class="text-[10px] font-mono text-outline uppercase">8 Models &middot; Medium</span>
                        </div>
                    </div>

                    {{-- CRM --}}
                    <div class="bg-surface-container p-6 rounded-xl hover:bg-surface-container-high transition-colors border border-outline-variant/10">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-2xl text-primary">bolt</span>
                        </div>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant">Sales</span>
                        <h3 class="text-lg font-bold text-white mt-1">CRM</h3>
                        <p class="text-on-surface-variant text-sm mt-2 mb-4">Lead tracking, pipeline stages, and Filament-powered admin dashboard.</p>
                        <div class="flex items-center justify-between">
                            <div class="flex gap-2">
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Filament</span>
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Breeze</span>
                            </div>
                            <span class="text-[10px] font-mono text-outline uppercase">10 Models &middot; Medium-High</span>
                        </div>
                    </div>

                    {{-- Invoice & Billing --}}
                    <div class="bg-surface-container p-6 rounded-xl hover:bg-surface-container-high transition-colors border border-outline-variant/10">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-2xl text-primary">receipt_long</span>
                        </div>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant">Sales</span>
                        <h3 class="text-lg font-bold text-white mt-1">Invoice & Billing</h3>
                        <p class="text-on-surface-variant text-sm mt-2 mb-4">PDF generation, recurring subscriptions, and tax calculation logic.</p>
                        <div class="flex items-center justify-between">
                            <div class="flex gap-2">
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Cashier</span>
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">PDF</span>
                            </div>
                            <span class="text-[10px] font-mono text-outline uppercase">10 Models &middot; Medium</span>
                        </div>
                    </div>

                    {{-- Restaurant --}}
                    <div class="bg-surface-container p-6 rounded-xl hover:bg-surface-container-high transition-colors border border-outline-variant/10">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-2xl text-primary">restaurant</span>
                        </div>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant">Hospitality</span>
                        <h3 class="text-lg font-bold text-white mt-1">Restaurant</h3>
                        <p class="text-on-surface-variant text-sm mt-2 mb-4">Table reservations, menu management, QR ordering, and kitchen display system.</p>
                        <div class="flex items-center justify-between">
                            <div class="flex gap-2">
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Laravel</span>
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Reverb</span>
                            </div>
                            <span class="text-[10px] font-mono text-outline uppercase">11 Models &middot; Medium</span>
                        </div>
                    </div>

                    {{-- Project Management --}}
                    <div class="bg-surface-container p-6 rounded-xl hover:bg-surface-container-high transition-colors border border-outline-variant/10">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-2xl text-primary">task_alt</span>
                        </div>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant">Operations</span>
                        <h3 class="text-lg font-bold text-white mt-1">Project Management</h3>
                        <p class="text-on-surface-variant text-sm mt-2 mb-4">Kanban boards, sprint planning, time tracking, and team collaboration tools.</p>
                        <div class="flex items-center justify-between">
                            <div class="flex gap-2">
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Reverb</span>
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Redis</span>
                            </div>
                            <span class="text-[10px] font-mono text-outline uppercase">10 Models &middot; Medium-High</span>
                        </div>
                    </div>

                    {{-- E-commerce --}}
                    <div class="bg-surface-container p-6 rounded-xl hover:bg-surface-container-high transition-colors border border-outline-variant/10">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-2xl text-primary">shopping_cart</span>
                        </div>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-on-surface-variant">Sales</span>
                        <h3 class="text-lg font-bold text-white mt-1">E-commerce</h3>
                        <p class="text-on-surface-variant text-sm mt-2 mb-4">Product variants, cart persistence, and headless inventory API.</p>
                        <div class="flex items-center justify-between">
                            <div class="flex gap-2">
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Cashier</span>
                                <span class="bg-surface-container-lowest text-[10px] font-mono px-2 py-1 rounded text-primary border border-primary/20 uppercase">Elastic</span>
                            </div>
                            <span class="text-[10px] font-mono text-outline uppercase">13 Models &middot; High</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================== --}}
        {{-- 5. AGENT-READY --}}
        {{-- ================================================================== --}}
        <section class="py-24 px-8">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <span class="text-primary font-mono text-xs tracking-widest uppercase">AI-First Output</span>
                        <h2 class="text-4xl font-extrabold text-white mt-2">Built for AI Coding Agents</h2>
                        <p class="text-on-surface-variant mt-4 text-lg">
                            Every generated scaffold includes comprehensive context files that AI coding agents understand natively. Claude Code, Cursor, Aider — they all read your scaffold and know exactly what to build next.
                        </p>
                        <ul class="mt-8 space-y-4">
                            <li class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary mt-0.5">check_circle</span>
                                <div>
                                    <span class="text-white font-medium">CLAUDE.md</span>
                                    <span class="text-on-surface-variant text-sm"> — Project context, conventions, and architecture decisions</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary mt-0.5">check_circle</span>
                                <div>
                                    <span class="text-white font-medium">todo.md</span>
                                    <span class="text-on-surface-variant text-sm"> — Phased implementation plan your agent can follow</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary mt-0.5">check_circle</span>
                                <div>
                                    <span class="text-white font-medium">.claude-reference/</span>
                                    <span class="text-on-surface-variant text-sm"> — Architecture, patterns, constants, and DB schema</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-primary mt-0.5">check_circle</span>
                                <div>
                                    <span class="text-white font-medium">Migrations + Seeders</span>
                                    <span class="text-on-surface-variant text-sm"> — Production-ready database schema with realistic data</span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="terminal-window rounded-xl p-6 font-mono text-xs leading-relaxed overflow-x-auto">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="w-3 h-3 rounded-full bg-tertiary-container/60"></span>
                            <span class="w-3 h-3 rounded-full bg-yellow-500/60"></span>
                            <span class="w-3 h-3 rounded-full bg-green-500/60"></span>
                            <span class="ml-3 text-outline font-mono text-[10px] uppercase tracking-widest">Project Structure</span>
                        </div>
                        <pre class="text-on-surface-variant"><span class="text-primary">project/</span>
<span class="text-outline">&boxv;&boxh;&boxh;</span> <span class="text-white">CLAUDE.md</span>
<span class="text-outline">&boxv;&boxh;&boxh;</span> <span class="text-white">PROJECT.md</span>
<span class="text-outline">&boxv;&boxh;&boxh;</span> <span class="text-white">todo.md</span>
<span class="text-outline">&boxv;&boxh;&boxh;</span> <span class="text-primary">.claude-reference/</span>
<span class="text-outline">&boxv;   &boxv;&boxh;&boxh;</span> architecture.md
<span class="text-outline">&boxv;   &boxv;&boxh;&boxh;</span> constants.md
<span class="text-outline">&boxv;   &boxv;&boxh;&boxh;</span> patterns.md
<span class="text-outline">&boxv;   &boxur;&boxh;&boxh;</span> decisions.md
<span class="text-outline">&boxv;&boxh;&boxh;</span> <span class="text-primary">database/migrations/</span>
<span class="text-outline">&boxv;   &boxv;&boxh;&boxh;</span> create_users_table.php
<span class="text-outline">&boxv;   &boxv;&boxh;&boxh;</span> create_projects_table.php
<span class="text-outline">&boxv;   &boxur;&boxh;&boxh;</span> create_tasks_table.php
<span class="text-outline">&boxv;&boxh;&boxh;</span> <span class="text-primary">app/Models/</span>
<span class="text-outline">&boxv;   &boxv;&boxh;&boxh;</span> User.php
<span class="text-outline">&boxv;   &boxur;&boxh;&boxh;</span> Project.php
<span class="text-outline">&boxur;&boxh;&boxh;</span> <span class="text-primary">routes/</span>
    <span class="text-outline">&boxur;&boxh;&boxh;</span> api.php</pre>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================== --}}
        {{-- 6. PRICING --}}
        {{-- ================================================================== --}}
        <section id="community" class="py-24 px-8 bg-surface-container-lowest">
            <div class="max-w-3xl mx-auto text-center">
                <span class="text-primary font-mono text-xs tracking-widest uppercase">Open Source</span>
                <h2 class="text-4xl font-extrabold text-white mt-2">Completely Free. Forever.</h2>
                <p class="text-on-surface-variant mt-4 max-w-xl mx-auto text-lg">
                    No premium tiers. No paywalls. No limits.<br>
                    Every feature &mdash; all 25 templates, GitHub export, BYOS deploy &mdash; is yours.
                </p>

                <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="bg-surface-container rounded-xl p-6">
                        <span class="material-symbols-outlined text-primary text-3xl">smart_toy</span>
                        <h3 class="text-white font-bold mt-3">AI Generation</h3>
                        <p class="text-on-surface-variant text-sm mt-1">All 25 templates, unlimited generations</p>
                    </div>
                    <div class="bg-surface-container rounded-xl p-6">
                        <span class="material-symbols-outlined text-primary text-3xl">upload</span>
                        <h3 class="text-white font-bold mt-3">Full Export</h3>
                        <p class="text-on-surface-variant text-sm mt-1">GitHub push + ZIP download, no limits</p>
                    </div>
                    <div class="bg-surface-container rounded-xl p-6">
                        <span class="material-symbols-outlined text-primary text-3xl">dns</span>
                        <h3 class="text-white font-bold mt-3">BYOS Deploy</h3>
                        <p class="text-on-surface-variant text-sm mt-1">Hetzner, DO, Linode, Vultr &mdash; your server</p>
                    </div>
                </div>

                <p class="text-on-surface-variant mt-12 text-sm">
                    If Draplo saves you time, consider supporting development.
                </p>
                <div class="mt-4 flex justify-center gap-3">
                    <a href="{{ config('app.donate_url') }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-bold text-sm hover:opacity-90 transition-opacity">
                        <span class="material-symbols-outlined text-lg">coffee</span>
                        Buy Me a Coffee
                    </a>
                    <a href="{{ config('app.github_repo_url') }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 px-5 py-2.5 border border-outline-variant/15 text-on-surface rounded-md font-medium text-sm hover:bg-surface-container-high transition-colors">
                        <span class="material-symbols-outlined text-lg">star</span>
                        Star on GitHub
                    </a>
                </div>
            </div>
        </section>

        {{-- ================================================================== --}}
        {{-- 7. OPEN SOURCE --}}
        {{-- ================================================================== --}}
        <section class="py-24 px-8 text-center">
            <div class="max-w-3xl mx-auto">
                <span class="text-primary font-mono text-xs tracking-widest uppercase">Open Source</span>
                <h2 class="text-4xl md:text-5xl font-extrabold text-white mt-2">Fully open source. AGPL-3.0.</h2>
                <p class="text-on-surface-variant text-lg mt-4">Self-host for free. Forever. All features included. Your data stays on your server.</p>
                <a href="https://github.com/draplo/draplo" target="_blank" rel="noopener" class="mt-10 inline-flex items-center gap-2 border border-outline-variant/15 text-on-surface px-8 py-3 rounded-md font-medium hover:bg-surface-container-high transition-colors">
                    <span class="material-symbols-outlined">code</span>
                    View Source on GitHub
                </a>
            </div>
        </section>

    </main>

    {{-- ================================================================== --}}
    {{-- 8. FOOTER --}}
    {{-- ================================================================== --}}
    <footer class="py-12 bg-surface-container-lowest border-t border-outline-variant/5">
        <div class="max-w-7xl mx-auto px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-4">
                <span class="text-lg font-black tracking-tighter text-white uppercase">Draplo</span>
                <span class="font-mono text-xs text-outline">&copy; {{ date('Y') }} Draplo. AGPL-3.0 License.</span>
            </div>
            <nav class="flex gap-6">
                <a href="{{ config('app.donate_url') }}" target="_blank" rel="noopener" class="font-mono text-xs text-outline hover:text-on-surface-variant transition-colors">Donate</a>
                <a href="#" class="font-mono text-xs text-outline hover:text-on-surface-variant transition-colors">Privacy</a>
                <a href="#" class="font-mono text-xs text-outline hover:text-on-surface-variant transition-colors">Terms</a>
                <a href="#" class="font-mono text-xs text-outline hover:text-on-surface-variant transition-colors">Security</a>
                <a href="#" class="font-mono text-xs text-outline hover:text-on-surface-variant transition-colors">Changelog</a>
            </nav>
        </div>
    </footer>

    @vite(['resources/js/threejs-hero.js'])
</body>
</html>
