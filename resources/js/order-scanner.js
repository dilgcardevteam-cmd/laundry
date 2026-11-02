import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

const openButton = document.getElementById('open-order-qr-scanner');
const closeButton = document.getElementById('close-order-qr-scanner');
const flipButton = document.getElementById('flip-order-qr-camera');
const restartButton = document.getElementById('restart-order-qr-scanner');
const modal = document.getElementById('order-qr-modal');
const message = document.getElementById('order-qr-message');
const readerId = 'order-qr-reader';

if (openButton instanceof HTMLButtonElement
    && closeButton instanceof HTMLButtonElement
    && flipButton instanceof HTMLButtonElement
    && restartButton instanceof HTMLButtonElement
    && modal instanceof HTMLElement
    && message instanceof HTMLElement) {
    let scanner = null;
    let isProcessingCode = false;
    let isStartingCamera = false;
    let cameras = [];
    let activeCameraId = null;
    const scanConfig = { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1 };

    const showMessage = (text, isError = false) => {
        message.textContent = text;
        message.classList.toggle('text-red-700', isError);
        message.classList.toggle('text-[#002c76]', !isError);
    };

    const stopScanner = async () => {
        if (scanner?.isScanning) {
            await scanner.stop();
        }
    };

    const startScanner = async (cameraId = activeCameraId) => {
        if (isStartingCamera) {
            return;
        }

        isStartingCamera = true;
        restartButton.classList.add('hidden');
        showMessage('Starting camera…');

        try {
            if (!scanner) {
                scanner = new Html5Qrcode(readerId, {
                    formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                    verbose: false,
                });
            }

            await scanner.start(
                cameraId ?? { facingMode: 'environment' },
                scanConfig,
                handleDecodedQr,
                () => {},
            );
            cameras = await Html5Qrcode.getCameras();
            activeCameraId = scanner.getRunningTrackSettings().deviceId
                ?? (typeof cameraId === 'string' ? cameraId : null);
            flipButton.classList.toggle('hidden', cameras.length < 2);
            showMessage('Point the camera at the order QR code.');
        } catch (error) {
            const detail = error instanceof Error ? error.message : 'Camera access could not be started.';
            showMessage(detail, true);
            restartButton.classList.remove('hidden');
        } finally {
            isStartingCamera = false;
        }
    };

    const closeScanner = async () => {
        try {
            await stopScanner();
            scanner?.clear();
            scanner = null;
            cameras = [];
            activeCameraId = null;
            flipButton.classList.add('hidden');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        } catch (error) {
            const detail = error instanceof Error ? error.message : 'The camera could not be stopped.';
            showMessage(detail, true);
        }
    };

    const flipCamera = async () => {
        if (cameras.length < 2 || isStartingCamera || isProcessingCode) {
            return;
        }

        const activeCameraIndex = cameras.findIndex((camera) => camera.id === activeCameraId);
        const nextCameraIndex = activeCameraIndex < 0 ? 0 : (activeCameraIndex + 1) % cameras.length;
        const nextCameraId = cameras[nextCameraIndex].id;

        try {
            await stopScanner();
            activeCameraId = nextCameraId;
            await startScanner(nextCameraId);
        } catch (error) {
            const detail = error instanceof Error ? error.message : 'The camera could not be switched.';
            showMessage(detail, true);
            restartButton.classList.remove('hidden');
        }
    };

    const handleDecodedQr = async (qrCode) => {
        if (isProcessingCode) {
            return;
        }

        isProcessingCode = true;
        showMessage('QR code found. Updating the order…');

        try {
            await stopScanner();
            const response = await fetch(openButton.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ qr_code: qrCode }),
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(result.message ?? 'The order status could not be updated.');
            }

            const orderRow = document.querySelector(`[data-order-id="${result.order_id}"]`);
            const statusSelect = orderRow?.querySelector('select[name="laundry_status"]');
            if (statusSelect instanceof HTMLSelectElement) {
                statusSelect.value = result.laundry_status;
            }

            if (result.changed) {
                showMessage(`${result.order_code}: ${result.previous_status_label} → ${result.status_label}`);
            } else {
                showMessage(`${result.order_code} is already at ${result.status_label}.`);
            }
            restartButton.classList.remove('hidden');
        } catch (error) {
            const detail = error instanceof Error ? error.message : 'The order status could not be updated.';
            showMessage(detail, true);
            restartButton.classList.remove('hidden');
        } finally {
            isProcessingCode = false;
        }
    };

    openButton.addEventListener('click', () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        startScanner();
    });

    closeButton.addEventListener('click', closeScanner);
    flipButton.addEventListener('click', flipCamera);
    restartButton.addEventListener('click', startScanner);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeScanner();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeScanner();
        }
    });
}
