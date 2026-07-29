(() => {
    'use strict';

    const video =
        document.getElementById('hpmcScannerVideo');

    const placeholder =
        document.getElementById(
            'hpmcScannerPlaceholder'
        );

    const startButton =
        document.getElementById(
            'hpmcScannerStart'
        );

    const stopButton =
        document.getElementById(
            'hpmcScannerStop'
        );

    const message =
        document.getElementById(
            'hpmcScannerMessage'
        );

    const manualForm =
        document.getElementById(
            'hpmcManualForm'
        );

    const manualCode =
        document.getElementById(
            'hpmcManualCode'
        );

    let stream = null;
    let detector = null;
    let zxingReader = null;
    let animationFrame = null;
    let running = false;
    let detecting = false;

    function setMessage(text, type = '') {
        message.textContent = text;
        message.className = 'hpmc-message';

        if (type) {
            message.classList.add(
                `hpmc-message--${type}`
            );
        }
    }

    function normalizeCode(rawValue) {
        const value =
            String(rawValue || '').trim();

        if (!value) {
            return '';
        }

        try {
            const url = new URL(
                value,
                window.location.origin
            );

            return (
                url.searchParams.get('id')
                || url.searchParams.get('code')
                || value
            ).trim();
        } catch (error) {
            return value;
        }
    }

    function openDetail(rawValue) {
        const code = normalizeCode(rawValue);

        if (!code) {
            setMessage(
                'QRコードから管理IDを取得できません。',
                'error'
            );

            return;
        }

        stopCamera();

        window.location.href =
            '/staff/property/detail/?id='
            + encodeURIComponent(code);
    }

    async function detectWithNativeApi() {
        if (
            !running
            || !detector
            || video.readyState < 2
        ) {
            animationFrame =
                requestAnimationFrame(
                    detectWithNativeApi
                );

            return;
        }

        if (detecting) {
            animationFrame =
                requestAnimationFrame(
                    detectWithNativeApi
                );

            return;
        }

        detecting = true;

        try {
            const results =
                await detector.detect(video);

            if (results.length > 0) {
                openDetail(results[0].rawValue);
                return;
            }
        } catch (error) {
            console.error(error);
        } finally {
            detecting = false;
        }

        animationFrame =
            requestAnimationFrame(
                detectWithNativeApi
            );
    }

    async function startNativeScanner() {
        const formats =
            await BarcodeDetector
                .getSupportedFormats();

        if (!formats.includes('qr_code')) {
            return false;
        }

        detector = new BarcodeDetector({
            formats: ['qr_code'],
        });

        stream =
            await navigator.mediaDevices
                .getUserMedia({
                    video: {
                        facingMode: {
                            ideal: 'environment',
                        },
                        width: {
                            ideal: 1920,
                        },
                        height: {
                            ideal: 1080,
                        },
                    },
                    audio: false,
                });

        video.srcObject = stream;

        await video.play();

        running = true;

        animationFrame =
            requestAnimationFrame(
                detectWithNativeApi
            );

        return true;
    }

    async function startZxingScanner() {
        if (
            typeof ZXing === 'undefined'
            || !ZXing.BrowserQRCodeReader
        ) {
            throw new Error(
                'QR読み取りライブラリを'
                + '読み込めませんでした。'
            );
        }

        zxingReader =
            new ZXing.BrowserQRCodeReader();

        running = true;

        await zxingReader.decodeFromVideoDevice(
            undefined,
            video,
            (result, error) => {
                if (!running) {
                    return;
                }

                if (result) {
                    openDetail(
                        result.getText()
                    );
                }

                if (
                    error
                    && !(
                        error instanceof
                        ZXing.NotFoundException
                    )
                ) {
                    console.error(error);
                }
            }
        );
    }

    async function startCamera() {
        if (running) {
            return;
        }

        if (
            !navigator.mediaDevices
            || !navigator.mediaDevices.getUserMedia
        ) {
            setMessage(
                'このページではカメラを利用できません。'
                + 'HTTPSまたはlocalhostで開いてください。',
                'error'
            );

            return;
        }

        startButton.disabled = true;

        setMessage(
            'カメラを起動しています。'
        );

        try {
            let nativeStarted = false;

            if ('BarcodeDetector' in window) {
                try {
                    nativeStarted =
                        await startNativeScanner();
                } catch (error) {
                    nativeStarted = false;
                }
            }

            if (!nativeStarted) {
                await startZxingScanner();
            }

            placeholder.hidden = true;
            stopButton.disabled = false;

            setMessage(
                'QRコードを枠内に映してください。',
                'success'
            );
        } catch (error) {
            stopCamera();

            let errorMessage =
                'カメラを起動できませんでした。';

            if (
                error
                && error.name === 'NotAllowedError'
            ) {
                errorMessage =
                    'カメラの使用が拒否されました。'
                    + 'ブラウザ設定からカメラを'
                    + '許可してください。';
            } else if (
                error
                && error.name === 'NotFoundError'
            ) {
                errorMessage =
                    '利用できるカメラが見つかりません。';
            } else if (
                error
                && error.message
            ) {
                errorMessage = error.message;
            }

            setMessage(
                errorMessage,
                'error'
            );
        } finally {
            if (!running) {
                startButton.disabled = false;
            }
        }
    }

    function stopCamera() {
        running = false;
        detecting = false;

        if (animationFrame !== null) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }

        if (zxingReader) {
            try {
                zxingReader.reset();
            } catch (error) {
                console.error(error);
            }

            zxingReader = null;
        }

        if (stream) {
            stream
                .getTracks()
                .forEach(
                    (track) => track.stop()
                );

            stream = null;
        }

        if (video.srcObject) {
            const tracks =
                video.srcObject.getTracks
                    ? video.srcObject.getTracks()
                    : [];

            tracks.forEach(
                (track) => track.stop()
            );
        }

        video.pause();
        video.srcObject = null;

        placeholder.hidden = false;
        startButton.disabled = false;
        stopButton.disabled = true;
    }

    startButton.addEventListener(
        'click',
        startCamera
    );

    stopButton.addEventListener(
        'click',
        () => {
            stopCamera();

            setMessage(
                'カメラを停止しました。'
            );
        }
    );

    manualForm.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();
            openDetail(manualCode.value);
        }
    );

    window.addEventListener(
        'pagehide',
        stopCamera
    );
})();
