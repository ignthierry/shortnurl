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
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> — URL Shortener</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            400: '#a78bfa',
                            500: '#8b5cf6',
                            600: '#7c3aed',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', system-ui, sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
        }
        .glass {
            background: rgba(30, 27, 75, 0.5);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(139, 92, 246, 0.15);
        }
        .glow {
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.15);
        }
        .input-focus:focus {
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.4);
        }
        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .copied-badge {
            animation: popIn 0.4s ease-out;
        }
        @keyframes popIn {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }
        .table-row:hover {
            background: rgba(139, 92, 246, 0.05);
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1e1b4b; }
        ::-webkit-scrollbar-thumb { background: #7c3aed; border-radius: 3px; }
    </style>
</head>
<body class="gradient-bg min-h-screen text-white antialiased">

    <div class="min-h-screen flex flex-col">

        <!-- Header -->
        <header class="py-6 px-4">
            <div class="max-w-4xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 flex items-center justify-center shadow-lg">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold tracking-tight"><?= htmlspecialchars($appName) ?></h1>
                </div>
                <span class="text-xs text-slate-500 hidden sm:block">Fast &middot; Simple &middot; Free</span>
            </div>
        </header>

        <!-- Hero + Form -->
        <main class="flex-1 px-4 pb-12">
            <div class="max-w-4xl mx-auto">

                <div class="text-center mb-10">
                    <h2 class="text-3xl sm:text-4xl font-bold mb-3 bg-gradient-to-r from-white via-brand-400 to-white bg-clip-text text-transparent">
                        Shorten Your Links
                    </h2>
                    <p class="text-slate-400 text-sm sm:text-base">Paste a long URL and get a short, shareable link in seconds.</p>
                </div>

                <!-- Shorten Form -->
                <div class="glass rounded-2xl p-6 glow mb-10">
                    <form id="shortenForm" class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1 relative">
                            <input
                                type="text"
                                id="urlInput"
                                placeholder="https://example.com/your-very-long-url-here"
                                required
                                class="w-full px-4 py-3.5 rounded-xl bg-slate-900/60 border border-slate-700/50 text-white placeholder-slate-500 text-sm focus:outline-none input-focus transition-all duration-200"
                            />
                            <div id="inputState" class="absolute right-3 top-1/2 -translate-y-1/2 hidden">
                                <svg id="inputValid" class="w-5 h-5 text-emerald-400 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <svg id="inputInvalid" class="w-5 h-5 text-red-400 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                        </div>
                        <button
                            type="submit"
                            id="submitBtn"
                            class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-brand-500 to-brand-600 hover:from-brand-600 hover:to-brand-500 text-white font-semibold text-sm transition-all duration-200 shadow-lg shadow-brand-600/25 hover:shadow-brand-500/40 active:scale-[0.98] whitespace-nowrap"
                        >
                            <span id="btnText">Shorten</span>
                            <svg id="btnSpinner" class="hidden animate-spin h-4 w-4 text-white inline ml-1" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </button>
                    </form>

                    <!-- Result -->
                    <div id="result" class="hidden mt-5 fade-in">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-emerald-400 mb-1 font-medium">Your shortened URL</p>
                                <p id="resultURL" class="text-emerald-300 font-mono text-sm truncate"></p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button
                                    onclick="copyToClipboard()"
                                    id="copyBtn"
                                    class="px-4 py-2 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 text-xs font-medium transition-all duration-200 flex items-center gap-1.5"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                    </svg>
                                    <span id="copyText">Copy</span>
                                </button>
                                <a id="resultLink" href="#" target="_blank" class="px-4 py-2 rounded-lg bg-brand-500/20 hover:bg-brand-500/30 text-brand-400 text-xs font-medium transition-all duration-200 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Open
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Error -->
                    <div id="error" class="hidden mt-5 fade-in">
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-red-500/10 border border-red-500/20">
                            <svg class="w-5 h-5 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p id="errorText" class="text-red-400 text-sm"></p>
                        </div>
                    </div>
                </div>

                <!-- Recent Links Table -->
                <?php if (!empty($recent)): ?>
                <div class="glass rounded-2xl overflow-hidden glow">
                    <div class="px-6 py-4 border-b border-slate-700/50">
                        <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
                            <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Recent Links
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500 text-xs uppercase tracking-wider">
                                    <th class="px-6 py-3">Short URL</th>
                                    <th class="px-6 py-3 hidden sm:table-cell">Original URL</th>
                                    <th class="px-6 py-3 text-center">Clicks</th>
                                    <th class="px-6 py-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach ($recent as $row): ?>
                                <tr class="table-row transition-colors duration-150">
                                    <td class="px-6 py-3.5">
                                        <a href="?c=<?= htmlspecialchars($row['short_code']) ?>" target="_blank" class="text-brand-400 hover:text-brand-300 font-mono text-xs font-medium transition-colors">
                                            <?= htmlspecialchars($baseURL . '/?c=' . $row['short_code']) ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-3.5 hidden sm:table-cell">
                                        <span class="text-slate-400 text-xs truncate block max-w-[280px]" title="<?= htmlspecialchars($row['original_url']) ?>">
                                            <?= htmlspecialchars($row['original_url']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 rounded-full bg-brand-500/15 text-brand-400 text-xs font-semibold">
                                            <?= (int) $row['click_count'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-center">
                                        <button
                                            onclick="copyLink('<?= htmlspecialchars($baseURL . '/?c=' . $row['short_code'], ENT_QUOTES) ?>', this)"
                                            class="px-3 py-1.5 rounded-lg bg-slate-700/50 hover:bg-slate-600/50 text-slate-400 hover:text-white text-xs font-medium transition-all duration-200 inline-flex items-center gap-1"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                            </svg>
                                            Copy
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Empty state -->
                <?php if (empty($recent)): ?>
                <div class="text-center py-12 text-slate-600">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <p class="text-sm">No links yet. Shorten your first URL above!</p>
                </div>
                <?php endif; ?>

            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6 px-4 text-center">
            <p class="text-xs text-slate-600">&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>. Built with PHP &amp; Tailwind CSS.</p>
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
            btnText.textContent = 'Shortening...';
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
                    errorDiv.classList.add('hidden');
                    urlInput.value = '';
                    inputValid.classList.add('hidden');
                    inputInvalid.classList.add('hidden');
                } else {
                    errorText.textContent = data.error || 'Something went wrong.';
                    errorDiv.classList.remove('hidden');
                    resultDiv.classList.add('hidden');
                }
            } catch (err) {
                errorText.textContent = 'Network error. Please try again.';
                errorDiv.classList.remove('hidden');
                resultDiv.classList.add('hidden');
            } finally {
                submitBtn.disabled = false;
                btnText.textContent = 'Shorten';
                btnSpinner.classList.add('hidden');
            }
        });

        function copyToClipboard() {
            const url = resultURL.textContent;
            navigator.clipboard.writeText(url).then(() => {
                const copyText = document.getElementById('copyText');
                copyText.textContent = 'Copied!';
                setTimeout(() => { copyText.textContent = 'Copy'; }, 2000);
            });
        }

        function copyLink(url, btn) {
            navigator.clipboard.writeText(url).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copied!';
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
