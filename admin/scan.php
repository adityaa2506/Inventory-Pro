<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Scan Barcode';
$activePage = 'scan';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Barcode Scanner</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="restartScanner()">
                        <i class="fa-solid fa-arrows-rotate"></i> Reset
                    </button>
                </div>
                
                <div id="reader" style="width:100%; min-height: 300px; border-radius: 10px; overflow: hidden; background: #000;"></div>
                
                <div class="row g-2 mt-3">
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100" onclick="toggleTorch()">
                            <i class="fa-solid fa-lightbulb"></i> Flash/Torch
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-secondary w-100" id="cameraBtn" onclick="switchCamera()">
                            <i class="fa-solid fa-camera-rotate"></i> Front/Back
                        </button>
                    </div>
                </div>

                <div class="mt-4 border-top pt-3">
                    <label class="form-label fw-bold">Manual Entry</label>
                    <div class="input-group">
                        <input type="text" id="manualCode" class="form-control" placeholder="Type barcode...">
                        <button type="button" class="btn btn-is2" onclick="goManual()">Open</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
let locked = false;
let html5QrCode;
let currentCameraId;
let torchEnabled = false;

async function startScanner(cameraId = null) {
    if (html5QrCode) {
        await html5QrCode.stop().catch(() => {});
    }
    
    html5QrCode = new Html5Qrcode("reader");
    currentCameraId = cameraId;

    const config = { 
        fps: 20, 
        qrbox: { width: 280, height: 200 },
        aspectRatio: 1.777778 // 16:9 for landscape scan area
    };

    try {
        if (!cameraId) {
            // Default to back camera
            await html5QrCode.start(
                { facingMode: "environment" }, 
                config, 
                onScanSuccess
            );
        } else {
            await html5QrCode.start(
                cameraId, 
                config, 
                onScanSuccess
            );
        }
    } catch (err) {
        console.error("Scanner error:", err);
        // Fallback or show error
        document.getElementById('reader').innerHTML = `<div class="p-4 text-center text-white">Camera not found or permission denied.</div>`;
    }
}

function onScanSuccess(decodedText) {
    if (locked) return;
    locked = true;
    
    // Simple visual feedback
    const reader = document.getElementById('reader');
    reader.style.border = "5px solid #28a745";
    
    // Short delay to show success before redirect
    setTimeout(() => {
        window.location.href = '<?= BASE_URL ?>admin/scan_result.php?code=' + encodeURIComponent(decodedText);
    }, 400);
}

async function switchCamera() {
    const devices = await Html5Qrcode.getCameras();
    if (devices && devices.length > 1) {
        const currentIndex = devices.findIndex(d => d.id === currentCameraId);
        const nextIndex = (currentIndex + 1) % devices.length;
        startScanner(devices[nextIndex].id);
    } else {
        alert("Only one camera found.");
    }
}

async function toggleTorch() {
    if (!html5QrCode) return;
    torchEnabled = !torchEnabled;
    try {
        await html5QrCode.applyVideoConstraints({
            advanced: [{ torch: torchEnabled }]
        });
    } catch (err) {
        console.warn("Torch not supported on this device/browser.");
        torchEnabled = !torchEnabled; // revert state
    }
}

function restartScanner() {
    locked = false;
    document.getElementById('reader').style.border = "none";
    startScanner();
}

function goManual() {
    const v = document.getElementById('manualCode').value.trim();
    if (!v) return;
    window.location.href = '<?= BASE_URL ?>admin/scan_result.php?code=' + encodeURIComponent(v);
}

// Initial start
window.addEventListener('load', () => startScanner());
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
