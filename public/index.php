<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Environment.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Shortener.php';

Environment::load();

$baseURL = rtrim(Environment::get('APP_URL', (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']), '/');

$code = $_GET['c'] ?? null;
if ($code && preg_match('/^[a-zA-Z0-9]{6}$/', $code)) {
    Shortener::redirect($code);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? $_POST['url'] ?? '';

    $result = Shortener::shorten($url);

    if ($result['success']) {
        $result['short_url'] = $baseURL . '/?c=' . $result['short_code'];
    }

    echo json_encode($result);
    exit;
}

$recent = [];
try {
    $recent = Shortener::recent(20);
} catch (PDOException $e) {
    // DB might not be set up yet
}

$appName = Environment::get('APP_NAME', 'ShortnURL');
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> - URL Shortener</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Space Grotesk"', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        base: '#0a0c10',
                        surface: '#11141a',
                        accent: {
                            DEFAULT: '#22d3ee',
                            dim: '#0e7490',
                        },
                    },
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── Base ─────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            background: #0a0c10;
            color: #e2e8f0;
            min-height: 100dvh;
            overflow-x: hidden;
        }
        ::selection { background: rgba(34, 211, 238, 0.25); color: #fff; }

        /* ── Ambient background ───────────────── */
        .bg-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(rgba(34, 211, 238, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(34, 211, 238, 0.035) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 30%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 30%, transparent 75%);
            pointer-events: none;
        }
        .bg-glow {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 60% 40% at 50% -10%, rgba(34, 211, 238, 0.12), transparent 60%),
                radial-gradient(ellipse 40% 30% at 90% 110%, rgba(14, 116, 144, 0.10), transparent 60%);
            pointer-events: none;
        }

        /* ── Glass panels ─────────────────────── */
        .glass {
            position: relative;
            background: linear-gradient(160deg, rgba(255,255,255,0.055), rgba(255,255,255,0.02));
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            backdrop-filter: blur(20px) saturate(140%);
            -webkit-backdrop-filter: blur(20px) saturate(140%);
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.06),
                0 20px 60px rgba(0,0,0,0.45);
        }
        .glass::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: radial-gradient(120% 60% at 15% 0%, rgba(34,211,238,0.06), transparent 50%);
            pointer-events: none;
        }

        /* ── Input ────────────────────────────── */
        .url-input {
            background: rgba(10, 12, 16, 0.7);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'JetBrains Mono', monospace;
        }
        .url-input:focus {
            outline: none;
            border-color: rgba(34, 211, 238, 0.5);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.12), 0 0 30px rgba(34, 211, 238, 0.08);
            background: rgba(10, 12, 16, 0.9);
        }

        /* ── Primary button ───────────────────── */
        .btn-primary {
            position: relative;
            background: linear-gradient(135deg, #22d3ee, #0e7490);
            color: #041014;
            font-weight: 700;
            letter-spacing: -0.01em;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s, filter 0.3s;
            box-shadow: 0 8px 30px rgba(34, 211, 238, 0.25);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            filter: brightness(1.08);
            box-shadow: 0 12px 40px rgba(34, 211, 238, 0.35);
        }
        .btn-primary:active {
            transform: translateY(0) scale(0.98);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Secondary button ─────────────────── */
        .btn-ghost {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            color: #94a3b8;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,0.08);
            color: #e2e8f0;
            border-color: rgba(255,255,255,0.2);
        }
        .btn-ghost:active {
            transform: scale(0.97);
        }

        /* ── Animations ───────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
            0% { transform: scale(0.92); opacity: 0; }
            60% { transform: scale(1.02); }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.4); }
            50% { opacity: 0.7; box-shadow: 0 0 0 4px rgba(52, 211, 153, 0); }
        }
        @keyframes shimmer {
            0% { background-position: -400px 0; }
            100% { background-position: 400px 0; }
        }
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .fade-up { animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .fade-up-1 { animation-delay: 0.08s; }
        .fade-up-2 { animation-delay: 0.16s; }
        .fade-up-3 { animation-delay: 0.24s; }
        .fade-up-4 { animation-delay: 0.32s; }
        .pop-in { animation: popIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .live-dot { animation: pulseDot 2s ease-in-out infinite; }
        .float-slow { animation: floatSlow 6s ease-in-out infinite; }
        .row-fade { animation: fadeUp 0.4s ease-out both; }

        /* ── Table rows ───────────────────────── */
        .data-row {
            transition: background 0.2s, transform 0.2s;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .data-row:hover {
            background: rgba(34, 211, 238, 0.03);
        }

        /* ── Scrollbar ────────────────────────── */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0a0c10; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }

        /* ── Reduced motion ───────────────────── */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="antialiased">

    <!-- Ambient background layers -->
    <div class="bg-grid"></div>
    <div class="bg-glow"></div>

    <div class="relative z-10 min-h-[100dvh] flex flex-col">

        <!-- ── Header ─────────────────────────────── -->
        <header class="fade-up pt-6 sm:pt-8 px-4 sm:px-6">
            <div class="max-w-5xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-accent to-accent-dim flex items-center justify-center float-slow">
                            <svg class="w-5 h-5 text-[#041014]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                        </div>
                        <span class="absolute -inset-1 rounded-xl bg-accent/20 blur-lg -z-10 float-slow"></span>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold tracking-tight text-white leading-none"><?= htmlspecialchars($appName) ?></h1>
                        <p class="text-[11px] text-slate-500 mt-0.5 font-mono">URL SHORTENER // SELF-HOSTED</p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-2 text-xs font-mono text-slate-500">
                    <span class="w-1.5 h-1.5 rounded-full live-dot bg-emerald-400"></span>
                    <span>SYSTEM ONLINE</span>
                </div>
            </div>
        </header>

        <!-- ── Main ───────────────────────────────── -->
        <main class="flex-1 px-4 sm:px-6 pb-16 pt-10 sm:pt-16">
            <div class="max-w-5xl mx-auto">

                <!-- Hero -->
                <div class="mb-12 sm:mb-14 max-w-3xl">
                    <p class="fade-up fade-up-1 font-mono text-[11px] tracking-[0.25em] text-accent/80 uppercase mb-4">
                        Perpendek. Bagikan. Lacak.
                    </p>
                    <h2 class="fade-up fade-up-2 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tighter text-white leading-[1.05]">
                        Tautan panjang,<br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-accent via-cyan-300 to-sky-500">satu ketukan pendek.</span>
                    </h2>
                    <p class="fade-up fade-up-3 mt-5 text-slate-400 text-base sm:text-lg leading-relaxed max-w-xl">
                        Tempel URL panjang, dapatkan tautan pendek dalam hitungan detik. Cepat, sederhana, tanpa ribet.
                    </p>
                </div>

                <!-- Shorten Card -->
                <div class="glass fade-up fade-up-4 p-5 sm:p-8 mb-12">
                    <form id="shortenForm" class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1 relative">
                            <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-600 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            <input
                                type="text"
                                id="urlInput"
                                placeholder="https://contoh.com/tautan-sangat-panjang-disini"
                                required
                                class="url-input w-full pl-11 pr-10 py-4 rounded-[14px] text-sm text-slate-200 placeholder-slate-600"
                            />
                            <div id="inputState" class="absolute right-3.5 top-1/2 -translate-y-1/2 hidden">
                                <svg id="inputValid" class="w-5 h-5 text-emerald-400 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <svg id="inputInvalid" class="w-5 h-5 text-red-400 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                        </div>
                        <button
                            type="submit"
                            id="submitBtn"
                            class="btn-primary px-8 py-4 text-sm whitespace-nowrap inline-flex items-center justify-center gap-2"
                        >
                            <span id="btnText">Perpendek</span>
                            <svg id="btnSpinner" class="hidden animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </button>
                    </form>

                    <!-- Result -->
                    <div id="result" class="hidden mt-5 pop-in">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 p-4 rounded-xl bg-emerald-500/[0.06] border border-emerald-500/20">
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] text-emerald-400 mb-1 font-mono tracking-wider uppercase">Tautan pendek siap</p>
                                <p id="resultURL" class="text-emerald-300 font-mono text-sm truncate"></p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button onclick="copyToClipboard()" id="copyBtn" class="btn-ghost px-4 py-2.5 text-xs flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                    </svg>
                                    <span id="copyText">Salin</span>
                                </button>
                                <a id="resultLink" href="#" target="_blank" class="btn-ghost px-4 py-2.5 text-xs flex items-center gap-1.5 no-underline">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Buka
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Error -->
                    <div id="error" class="hidden mt-5 pop-in">
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-red-500/[0.06] border border-red-500/20">
                            <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p id="errorText" class="text-red-300 text-sm"></p>
                        </div>
                    </div>
                </div>

                <!-- Recent Links -->
                <?php if (!empty($recent)): ?>
                <div class="fade-up fade-up-4">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-white font-semibold tracking-tight flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Tautan terbaru
                        </h3>
                        <span class="font-mono text-[11px] text-slate-600"><?= count($recent) ?> ENTRI</span>
                    </div>

                    <div class="glass overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left font-mono text-[10px] text-slate-600 uppercase tracking-[0.2em]">
                                        <th class="px-6 py-4 font-medium">Tautan pendek</th>
                                        <th class="px-6 py-4 hidden sm:table-cell font-medium">URL asli</th>
                                        <th class="px-6 py-4 text-center font-medium">Klik</th>
                                        <th class="px-6 py-4 text-center font-medium">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $i => $row): ?>
                                    <tr class="data-row row-fade" style="animation-delay: <?= min($i * 40, 400) ?>ms">
                                        <td class="px-6 py-4">
                                            <a href="?c=<?= htmlspecialchars($row['short_code']) ?>" target="_blank" class="text-accent hover:text-cyan-300 font-mono text-xs font-medium transition-colors no-underline">
                                                <?= htmlspecialchars($baseURL . '/?c=' . $row['short_code']) ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 hidden sm:table-cell">
                                            <span class="text-slate-500 text-xs truncate block max-w-[300px]" title="<?= htmlspecialchars($row['original_url']) ?>">
                                                <?= htmlspecialchars($row['original_url']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center justify-center min-w-[32px] px-2 py-1 rounded-full bg-accent/10 border border-accent/20 text-accent font-mono text-xs font-semibold">
                                                <?= (int) $row['click_count'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button
                                                onclick="copyLink('<?= htmlspecialchars($baseURL . '/?c=' . $row['short_code'], ENT_QUOTES) ?>', this)"
                                                class="btn-ghost px-3 py-1.5 text-xs inline-flex items-center gap-1.5"
                                            >
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                                </svg>
                                                Salin
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Empty state -->
                <?php if (empty($recent)): ?>
                <div class="glass fade-up fade-up-4 text-center py-16 px-6">
                    <svg class="w-10 h-10 mx-auto mb-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <p class="text-slate-500 text-sm">Belum ada tautan. Perpendek URL pertama Anda di atas!</p>
                </div>
                <?php endif; ?>

                <!-- Feature strip -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-14 fade-up">
                    <div class="p-4 flex items-start gap-3 rounded-2xl border border-white/5 bg-white/[0.02]">
                        <span class="w-8 h-8 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Sangat cepat</p>
                            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Tautan langsung aktif dalam hitungan detik.</p>
                        </div>
                    </div>
                    <div class="p-4 flex items-start gap-3 rounded-2xl border border-white/5 bg-white/[0.02]">
                        <span class="w-8 h-8 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Lacak klik</p>
                            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Pantau performa setiap tautan yang dibagikan.</p>
                        </div>
                    </div>
                    <div class="p-4 flex items-start gap-3 rounded-2xl border border-white/5 bg-white/[0.02]">
                        <span class="w-8 h-8 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-200">Self-hosted</p>
                            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Data Anda aman di server sendiri.</p>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <!-- Footer -->
        <footer class="py-8 px-4 text-center border-t border-white/5">
            <p class="text-xs text-slate-600 font-mono">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?> <span class="mx-2 text-slate-700">//</span> PHP + Tailwind
            </p>
        </footer>
    </div>

    <script>
        const form = document.getElementById('shortenForm');
        const urlInput = document.getElementById('urlInput');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        const resultDiv = document.getElementById('result');
        const resultURL = document.getElementById('resultURL');
        const resultLink = document.getElementById('resultLink');
        const errorDiv = document.getElementById('error');
        const errorText = document.getElementById('errorText');
        const inputValid = document.getElementById('inputValid');
        const inputInvalid = document.getElementById('inputInvalid');

        function isValidURL(str) {
            try {
                const u = new URL(str);
                return u.protocol === 'http:' || u.protocol === 'https:';
            } catch {
                if (/^[a-zA-Z0-9][a-zA-Z0-9-]*\.[a-zA-Z]{2,}/.test(str)) {
                    return true;
                }
                return false;
            }
        }

        urlInput.addEventListener('input', () => {
            const v = urlInput.value.trim();
            if (v === '') {
                inputValid.classList.add('hidden');
                inputInvalid.classList.add('hidden');
                return;
            }
            if (isValidURL(v)) {
                inputValid.classList.remove('hidden');
                inputInvalid.classList.add('hidden');
            } else {
                inputInvalid.classList.remove('hidden');
                inputValid.classList.add('hidden');
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const url = urlInput.value.trim();
            if (!url) return;

            submitBtn.disabled = true;
            btnText.textContent = 'Memperpendek...';
            btnSpinner.classList.remove('hidden');
            resultDiv.classList.add('hidden');
            errorDiv.classList.add('hidden');

            try {
                const resp = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url })
                });
                const data = await resp.json();

                if (data.success) {
                    resultURL.textContent = data.short_url;
                    resultLink.href = data.short_url;
                    resultDiv.classList.remove('hidden');
                    resultDiv.classList.remove('pop-in');
                    void resultDiv.offsetWidth;
                    resultDiv.classList.add('pop-in');
                    errorDiv.classList.add('hidden');
                    urlInput.value = '';
                    inputValid.classList.add('hidden');
                    inputInvalid.classList.add('hidden');
                } else {
                    errorText.textContent = data.error || 'Terjadi kesalahan.';
                    errorDiv.classList.remove('hidden');
                    resultDiv.classList.add('hidden');
                }
            } catch (err) {
                errorText.textContent = 'Error jaringan. Silakan coba lagi.';
                errorDiv.classList.remove('hidden');
                resultDiv.classList.add('hidden');
            } finally {
                submitBtn.disabled = false;
                btnText.textContent = 'Perpendek';
                btnSpinner.classList.add('hidden');
            }
        });

        function copyToClipboard() {
            const url = resultURL.textContent;
            navigator.clipboard.writeText(url).then(() => {
                const copyText = document.getElementById('copyText');
                copyText.textContent = 'Tersalin!';
                setTimeout(() => { copyText.textContent = 'Salin'; }, 2000);
            });
        }

        function copyLink(url, btn) {
            navigator.clipboard.writeText(url).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Tersalin!';
                btn.classList.add('text-emerald-400');
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.classList.remove('text-emerald-400');
                }, 2000);
            });
        }
    </script>

</body>
</html>
