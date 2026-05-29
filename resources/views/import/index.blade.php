<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Appika ERP — Bulk Data Import</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* ── smooth step connector ───────────────────────────────────── */
        .step-connector { height: 3px; flex: 1; transition: background .4s; }

        /* ── progress bar fill ───────────────────────────────────────── */
        #progress-fill { transition: width .6s ease; }

        /* ── log area ────────────────────────────────────────────────── */
        #log-box { font-family: 'Courier New', monospace; font-size: .8rem; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

{{-- ══════════════════════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════════════════════ --}}
<header class="bg-indigo-700 shadow-lg">
    <div class="max-w-5xl mx-auto px-6 py-4 flex items-center gap-4">
        <div class="bg-indigo-500 rounded-xl p-2">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
        </div>
        <div>
            <h1 class="text-white text-xl font-bold tracking-tight">Appika ERP</h1>
            <p class="text-indigo-200 text-sm">Asynchronous Bulk Data Import System</p>
        </div>
        <div class="ml-auto">
            <a href="{{ route('import.sample') }}"
               class="flex items-center gap-2 bg-indigo-500 hover:bg-indigo-400 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Download Sample CSV
            </a>
        </div>
    </div>
</header>

{{-- ══════════════════════════════════════════════════════════════════════
     MAIN CONTENT
═══════════════════════════════════════════════════════════════════════ --}}
<main class="max-w-5xl mx-auto px-6 py-8 space-y-8">

    {{-- ── STEP INDICATORS ─────────────────────────────────────────── --}}
    <div class="flex items-center gap-0">
        @foreach ([
            ['num'=>1,'label'=>'Upload File'],
            ['num'=>2,'label'=>'Validate'],
            ['num'=>3,'label'=>'Execute Import'],
            ['num'=>4,'label'=>'Monitor Progress'],
        ] as $i => $step)
            <div class="flex flex-col items-center">
                <div id="step-bubble-{{ $step['num'] }}"
                     class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all duration-300
                     {{ $i === 0 ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-slate-300 text-slate-400' }}">
                    {{ $step['num'] }}
                </div>
                <span id="step-label-{{ $step['num'] }}"
                      class="text-xs mt-1 font-medium {{ $i === 0 ? 'text-indigo-600' : 'text-slate-400' }}">
                    {{ $step['label'] }}
                </span>
            </div>
            @if ($i < 3)
                <div id="step-connector-{{ $step['num'] }}"
                     class="step-connector mx-1 bg-slate-300 mb-5 rounded"></div>
            @endif
        @endforeach
    </div>

    {{-- ── CARD WRAPPER ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

        {{-- ════════════════════════════════════════════════════════════
             STEP 1 — UPLOAD
        ════════════════════════════════════════════════════════════ --}}
        <div id="panel-step-1" class="p-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-1">Step 1 — Choose Your CSV File</h2>
            <p class="text-slate-500 text-sm mb-6">
                Upload a CSV file containing customer data.
                Required columns: <code class="bg-slate-100 px-1 rounded">name</code>,
                <code class="bg-slate-100 px-1 rounded">email</code>.
                Max 100 MB.
            </p>

            <div id="drop-zone"
                 class="border-2 border-dashed border-indigo-300 rounded-xl p-10 text-center cursor-pointer hover:border-indigo-500 hover:bg-indigo-50 transition-all duration-200">
                <svg class="w-12 h-12 text-indigo-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-slate-600 font-medium mb-1">Drag & drop your CSV file here</p>
                <p class="text-slate-400 text-sm mb-4">or click to browse</p>
                <input type="file" id="csv-file" accept=".csv,.txt" class="hidden">
                <button onclick="document.getElementById('csv-file').click()"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
                    Browse File
                </button>
            </div>

            <div id="file-info" class="hidden mt-4 flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-xl">
                <svg class="w-8 h-8 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <p id="file-name-display" class="font-medium text-slate-800 truncate"></p>
                    <p id="file-size-display" class="text-slate-500 text-sm"></p>
                </div>
                <button onclick="resetUpload()"
                        class="text-slate-400 hover:text-red-500 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mt-6 flex justify-end">
                <button id="btn-upload"
                        onclick="doUpload()"
                        disabled
                        class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-lg font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Upload File
                </button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             STEP 2 — VALIDATE
        ════════════════════════════════════════════════════════════ --}}
        <div id="panel-step-2" class="hidden p-8 border-t border-slate-100">
            <h2 class="text-lg font-semibold text-slate-800 mb-1">Step 2 — Validate File Structure</h2>
            <p class="text-slate-500 text-sm mb-6">
                We'll verify that your file has the correct columns before starting the import.
            </p>

            <div id="validate-result" class="hidden"></div>

            <div class="flex justify-between mt-6">
                <button onclick="goToStep(1)" class="text-slate-500 hover:text-slate-700 text-sm font-medium">← Back</button>
                <button id="btn-validate"
                        onclick="doValidate()"
                        class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Validate Headers
                </button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             STEP 3 — EXECUTE
        ════════════════════════════════════════════════════════════ --}}
        <div id="panel-step-3" class="hidden p-8 border-t border-slate-100">
            <h2 class="text-lg font-semibold text-slate-800 mb-1">Step 3 — Start Import</h2>
            <p class="text-slate-500 text-sm mb-6">
                Laravel will split your file into <strong>1,000-row chunks</strong>, dispatch each as a
                background job, and process them in parallel. The request returns immediately — no timeout.
            </p>

            <div id="execute-summary" class="p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800 mb-6"></div>

            <div class="flex justify-between">
                <button onclick="goToStep(2)" class="text-slate-500 hover:text-slate-700 text-sm font-medium">← Back</button>
                <button id="btn-execute"
                        onclick="doExecute()"
                        class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Start Import Now
                </button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════
             STEP 4 — PROGRESS MONITOR
        ════════════════════════════════════════════════════════════ --}}
        <div id="panel-step-4" class="hidden p-8 border-t border-slate-100">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800 mb-1">Step 4 — Live Progress Monitor</h2>
                    <p class="text-slate-500 text-sm">Polling every 2 seconds until all chunks finish.</p>
                </div>
                <span id="status-badge"
                      class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                    Processing…
                </span>
            </div>

            {{-- Progress bar --}}
            <div class="mb-2 flex justify-between text-sm font-medium text-slate-600">
                <span>Overall Progress</span>
                <span id="progress-pct">0%</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-5 overflow-hidden mb-6">
                <div id="progress-fill"
                     style="width:0%"
                     class="h-full bg-gradient-to-r from-indigo-500 to-indigo-400 rounded-full flex items-center justify-end pr-2">
                </div>
            </div>

            {{-- Stats grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="bg-slate-50 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-indigo-600" id="stat-total-jobs">—</p>
                    <p class="text-xs text-slate-500 mt-1">Total Chunks</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-amber-500" id="stat-pending-jobs">—</p>
                    <p class="text-xs text-slate-500 mt-1">Pending</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-red-500" id="stat-failed-jobs">—</p>
                    <p class="text-xs text-slate-500 mt-1">Failed</p>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-green-600" id="stat-items-added">—</p>
                    <p class="text-xs text-slate-500 mt-1">Rows Inserted</p>
                </div>
            </div>

            {{-- Batch ID + activity log --}}
            <div class="mb-3">
                <p class="text-xs text-slate-400">Batch UUID</p>
                <code id="batch-id-display" class="text-xs text-slate-600 break-all"></code>
            </div>

            <div id="log-box"
                 class="bg-slate-900 text-green-400 rounded-xl p-4 h-44 overflow-y-auto space-y-0.5 text-xs">
                <p class="opacity-50">— Waiting for activity log —</p>
            </div>

            <div class="flex justify-between mt-6">
                <button onclick="startNewImport()"
                        class="text-slate-500 hover:text-slate-700 text-sm font-medium">
                    ← Start a New Import
                </button>
                <button id="btn-cancel-polling" onclick="stopPolling()"
                        class="text-red-500 hover:text-red-700 text-sm font-medium">
                    Stop Polling
                </button>
            </div>
        </div>

    </div>{{-- /card --}}

    {{-- ── RECENT IMPORTS TABLE ─────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Recent Import History</h2>
            <button onclick="window.location.reload()"
                    class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Refresh</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">Batch ID</th>
                        <th class="px-6 py-3 text-left">File Name</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3 text-right">Rows Added</th>
                        <th class="px-6 py-3 text-left">Date</th>
                        <th class="px-6 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="history-tbody" class="divide-y divide-slate-100">
                    @forelse ($recentImports as $import)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <code class="text-xs text-slate-500">{{ substr($import->id, 0, 8) }}…</code>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-700">{{ $import->file_name }}</td>
                        <td class="px-6 py-4">
                            @php
                                $badge = match($import->status) {
                                    'Finished'   => 'bg-green-100 text-green-700',
                                    'Failed'     => 'bg-red-100 text-red-700',
                                    default      => 'bg-yellow-100 text-yellow-700',
                                };
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">
                                {{ $import->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-slate-700">
                            {{ number_format($import->items_added) }}
                        </td>
                        <td class="px-6 py-4 text-slate-500">
                            {{ $import->created_at->diffForHumans() }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button onclick="pollSingleBatch('{{ $import->id }}')"
                                    class="text-indigo-600 hover:underline text-xs">
                                View Status
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-slate-400">
                            No imports yet. Upload a CSV above to get started.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</main>

{{-- ══════════════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════════════ --}}
<script>
// ─────────────────────────────────────────────────────────────────────
//  State
// ─────────────────────────────────────────────────────────────────────
const state = {
    file:       null,   // File object
    filePath:   null,   // Server path returned after upload
    fileName:   null,   // Original file name
    totalRows:  0,
    batchId:    null,
    pollTimer:  null,
    currentStep: 1,
};

// ─────────────────────────────────────────────────────────────────────
//  CSRF helper
// ─────────────────────────────────────────────────────────────────────
const csrfToken = () => document.querySelector('meta[name="csrf-token"]').content;

async function apiFetch(url, options = {}) {
    const { headers: extraHeaders = {}, ...restOptions } = options;
    const res = await fetch(url, {
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
            ...extraHeaders,
        },
        ...restOptions,
    });
    const data = await res.json();
    if (!res.ok) throw { status: res.status, data };
    return data;
}

// ─────────────────────────────────────────────────────────────────────
//  Step management
// ─────────────────────────────────────────────────────────────────────
function goToStep(n) {
    state.currentStep = n;
    [1,2,3,4].forEach(i => {
        const panel   = document.getElementById(`panel-step-${i}`);
        const bubble  = document.getElementById(`step-bubble-${i}`);
        const label   = document.getElementById(`step-label-${i}`);
        const conn    = document.getElementById(`step-connector-${i}`);

        if (i === n) {
            panel .classList.remove('hidden');
            bubble.classList.replace('bg-white','bg-indigo-600');
            bubble.classList.replace('border-slate-300','border-indigo-600');
            bubble.classList.replace('text-slate-400','text-white');
            label .classList.replace('text-slate-400','text-indigo-600');
        } else {
            panel .classList.add('hidden');
        }

        if (i < n) {
            bubble.classList.remove('bg-white','border-slate-300','text-slate-400');
            bubble.classList.add   ('bg-indigo-600','border-indigo-600','text-white');
            label .classList.replace('text-slate-400','text-indigo-600');
            if (conn) conn.style.background = '#4f46e5';
        } else if (i > n) {
            bubble.classList.remove('bg-indigo-600','border-indigo-600','text-white');
            bubble.classList.add   ('bg-white','border-slate-300','text-slate-400');
            label .classList.replace('text-indigo-600','text-slate-400');
            if (conn) conn.style.background = '';
        }
    });
}

// ─────────────────────────────────────────────────────────────────────
//  File input wiring
// ─────────────────────────────────────────────────────────────────────
document.getElementById('csv-file').addEventListener('change', function () {
    if (this.files.length) selectFile(this.files[0]);
});

const dropZone = document.getElementById('drop-zone');
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('bg-indigo-50'); });
dropZone.addEventListener('dragleave', e => { dropZone.classList.remove('bg-indigo-50'); });
dropZone.addEventListener('drop',      e => {
    e.preventDefault();
    dropZone.classList.remove('bg-indigo-50');
    if (e.dataTransfer.files.length) selectFile(e.dataTransfer.files[0]);
});

function selectFile(file) {
    state.file = file;
    document.getElementById('file-name-display').textContent = file.name;
    document.getElementById('file-size-display').textContent =
        (file.size / 1024).toFixed(1) + ' KB  ·  ' + file.type;
    document.getElementById('file-info').classList.remove('hidden');
    document.getElementById('drop-zone').classList.add('hidden');
    document.getElementById('btn-upload').disabled = false;
}

function resetUpload() {
    state.file = null;
    document.getElementById('csv-file').value = '';
    document.getElementById('file-info').classList.add('hidden');
    document.getElementById('drop-zone').classList.remove('hidden');
    document.getElementById('btn-upload').disabled = true;
}

// ─────────────────────────────────────────────────────────────────────
//  STEP 1 — Upload
// ─────────────────────────────────────────────────────────────────────
async function doUpload() {
    if (!state.file) return;

    const btn = document.getElementById('btn-upload');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Uploading…';

    try {
        const fd = new FormData();
        fd.append('file', state.file);
        const data = await apiFetch('/import/upload', { method: 'POST', body: fd });

        state.filePath = data.file_path;
        state.fileName = data.file_name;

        showToast(`✓ "${data.file_name}" uploaded (${data.size_kb} KB)`, 'success');
        goToStep(2);
    } catch (err) {
        showToast('Upload failed: ' + (err.data?.message || JSON.stringify(err.data?.errors)), 'error');
        btn.disabled = false;
        btn.innerHTML = 'Upload File';
    }
}

// ─────────────────────────────────────────────────────────────────────
//  STEP 2 — Validate
// ─────────────────────────────────────────────────────────────────────
async function doValidate() {
    const btn = document.getElementById('btn-validate');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Validating…';

    const resultBox = document.getElementById('validate-result');

    try {
        const data = await apiFetch('/import/validate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_path: state.filePath }),
        });

        state.totalRows = data.total_rows;

        resultBox.className = 'p-4 bg-green-50 border border-green-200 rounded-xl mb-4';
        resultBox.innerHTML = `
            <p class="font-semibold text-green-700 mb-2">✓ ${data.message}</p>
            <div class="grid grid-cols-2 gap-2 text-sm text-green-700">
                <span>Detected columns: <code class="bg-green-100 px-1 rounded">${data.headers.join(', ')}</code></span>
                <span>Data rows found: <strong>${data.total_rows.toLocaleString()}</strong></span>
            </div>`;
        resultBox.classList.remove('hidden');

        // Pre-fill execute summary
        const chunks = Math.ceil(data.total_rows / 1000);
        document.getElementById('execute-summary').innerHTML = `
            <ul class="space-y-1">
                <li>📄 File: <strong>${state.fileName}</strong></li>
                <li>📊 Total rows: <strong>${data.total_rows.toLocaleString()}</strong></li>
                <li>⚙️  Background jobs (chunks): <strong>${chunks}</strong> × 1,000 rows each</li>
                <li>🚀 The HTTP request will return immediately with a Batch UUID.</li>
            </ul>`;

        showToast(`✓ Validation passed — ${data.total_rows.toLocaleString()} rows ready`, 'success');

        setTimeout(() => goToStep(3), 800);
    } catch (err) {
        const msg = err.data?.message || 'Validation failed';
        resultBox.className = 'p-4 bg-red-50 border border-red-200 rounded-xl mb-4';
        resultBox.innerHTML = `<p class="font-semibold text-red-700">✗ ${msg}</p>`;
        resultBox.classList.remove('hidden');
        showToast(msg, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Validate Headers';
    }
}

// ─────────────────────────────────────────────────────────────────────
//  STEP 3 — Execute
// ─────────────────────────────────────────────────────────────────────
async function doExecute() {
    const btn = document.getElementById('btn-execute');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Dispatching…';

    try {
        const data = await apiFetch('/import/execute', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_path: state.filePath, file_name: state.fileName }),
        });

        state.batchId = data.batch_id;

        document.getElementById('batch-id-display').textContent = data.batch_id;
        logLine(`⚡ Batch dispatched — ${data.total_jobs} chunk job(s) queued.`);
        logLine(`   Batch UUID: ${data.batch_id}`);

        goToStep(4);
        startPolling();
        showToast('🚀 Import queued! Watching progress…', 'success');
    } catch (err) {
        showToast('Dispatch failed: ' + (err.data?.message || 'Unknown error'), 'error');
        btn.disabled = false;
        btn.innerHTML = 'Start Import Now';
    }
}

// ─────────────────────────────────────────────────────────────────────
//  STEP 4 — Polling
// ─────────────────────────────────────────────────────────────────────
function startPolling() {
    if (state.pollTimer) clearInterval(state.pollTimer);
    state.pollTimer = setInterval(pollStatus, 2000);
    pollStatus(); // immediate first call
}

function stopPolling() {
    clearInterval(state.pollTimer);
    state.pollTimer = null;
    logLine('⏹ Polling stopped manually.');
    document.getElementById('btn-cancel-polling').textContent = 'Polling Stopped';
    document.getElementById('btn-cancel-polling').disabled = true;
}

async function pollStatus() {
    if (!state.batchId) return;
    try {
        const d = await apiFetch(`/import/status/${state.batchId}`);
        updateProgressUI(d);

        if (d.is_finished || d.is_cancelled) {
            stopPolling();
            showToast(
                d.status === 'Finished'
                    ? `✅ Import finished! ${d.items_added.toLocaleString()} rows added.`
                    : `⚠ Import finished with status: ${d.status}`,
                d.status === 'Finished' ? 'success' : 'warning'
            );
            // Refresh the history table after a short delay
            setTimeout(() => window.location.reload(), 3000);
        }
    } catch (e) {
        logLine('⚠ Poll error: ' + JSON.stringify(e));
    }
}

// Called from "View Status" links in the history table
async function pollSingleBatch(batchId) {
    state.batchId = batchId;
    document.getElementById('batch-id-display').textContent = batchId;
    goToStep(4);
    startPolling();
}

function updateProgressUI(d) {
    const pct = d.progress ?? 0;

    document.getElementById('progress-fill').style.width = pct + '%';
    document.getElementById('progress-pct').textContent  = pct + '%';
    document.getElementById('stat-total-jobs').textContent   = d.total_jobs  ?? '—';
    document.getElementById('stat-pending-jobs').textContent = d.pending_jobs ?? '—';
    document.getElementById('stat-failed-jobs').textContent  = d.failed_jobs  ?? '—';
    document.getElementById('stat-items-added').textContent  = (d.items_added ?? 0).toLocaleString();

    const badge = document.getElementById('status-badge');
    const statusStyles = {
        'Processing': 'bg-yellow-100 text-yellow-700',
        'Finished':   'bg-green-100  text-green-700',
        'Failed':     'bg-red-100    text-red-700',
    };
    badge.className = 'px-3 py-1 rounded-full text-xs font-semibold ' +
        (statusStyles[d.status] ?? 'bg-slate-100 text-slate-600');
    badge.textContent = d.status;

    const done = d.total_jobs - (d.pending_jobs ?? 0);
    logLine(`[${new Date().toLocaleTimeString()}] ${pct}% — ${done}/${d.total_jobs} chunks done, ${(d.items_added ?? 0).toLocaleString()} rows inserted`);
}

// ─────────────────────────────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────────────────────────────
function logLine(text) {
    const box = document.getElementById('log-box');
    if (box.querySelector('.opacity-50')) {
        box.innerHTML = '';
    }
    const p = document.createElement('p');
    p.textContent = text;
    box.appendChild(p);
    box.scrollTop = box.scrollHeight;
}

function showToast(msg, type = 'info') {
    const colors = {
        success: 'bg-green-600',
        error:   'bg-red-600',
        warning: 'bg-amber-500',
        info:    'bg-indigo-600',
    };
    const toast = document.createElement('div');
    toast.className = `fixed bottom-6 right-6 z-50 ${colors[type]} text-white text-sm font-medium px-5 py-3 rounded-xl shadow-xl transition-all duration-300 max-w-sm`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
}

function startNewImport() {
    stopPolling();
    state.file = state.filePath = state.fileName = state.batchId = null;
    state.totalRows = 0;
    resetUpload();
    document.getElementById('validate-result').classList.add('hidden');
    document.getElementById('log-box').innerHTML = '<p class="opacity-50">— Waiting for activity log —</p>';
    document.getElementById('btn-cancel-polling').textContent = 'Stop Polling';
    document.getElementById('btn-cancel-polling').disabled = false;
    goToStep(1);
}
</script>
</body>
</html>
