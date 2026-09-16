/**
 * Camera adapter for Staff Arrival Check-In.
 * Instascan is primary, BarcodeDetector is a progressive fallback, and all
 * scanned content is parsed as data rather than navigated or rendered.
 */
(function (global) {
  'use strict';

  const TOKEN_PATTERN = /^(?:[a-f0-9]{32}|[a-f0-9]{64})$/i;
  const REFERENCE_PATTERN = /^[A-Z0-9][A-Z0-9_-]{2,39}$/;
  const REAR_CAMERA_PATTERN = /(?:back|rear|environment|world)/i;

  function normalizeReference(candidate) {
    const reference = String(candidate || '').trim().toUpperCase();
    return REFERENCE_PATTERN.test(reference) ? reference : '';
  }

  function isSmartQmsPath(pathname) {
    const normalized = String(pathname || '').replace(/\/{2,}/g, '/');
    return normalized.endsWith('/track/')
      || normalized.endsWith('/queue/ticket/')
      || normalized.endsWith('/views/client/ticket_lookup.php');
  }

  function parseValue(rawValue, expectedOrigin = global.location?.origin || '') {
    const value = String(rawValue || '').trim();
    if (TOKEN_PATTERN.test(value)) {
      return { lookup_type: 'token', lookup_value: value.toLowerCase() };
    }

    const directReference = normalizeReference(value);
    if (directReference) {
      return { lookup_type: 'reference', lookup_value: directReference };
    }

    try {
      const parsed = new URL(value, expectedOrigin);
      if (parsed.origin !== expectedOrigin || !isSmartQmsPath(parsed.pathname)) return null;

      const token = parsed.searchParams.get('token') || '';
      if (TOKEN_PATTERN.test(token)) {
        return { lookup_type: 'token', lookup_value: token.toLowerCase() };
      }

      const reference = normalizeReference(parsed.searchParams.get('reference') || parsed.searchParams.get('ref'));
      return reference ? { lookup_type: 'reference', lookup_value: reference } : null;
    } catch (error) {
      return null;
    }
  }

  function chooseCamera(cameras, requestedId = '') {
    if (!Array.isArray(cameras) || cameras.length === 0) return null;
    return cameras.find(camera => String(camera.id) === String(requestedId))
      || cameras.find(camera => REAR_CAMERA_PATTERN.test(String(camera.name || '')))
      || cameras[0];
  }

  function create(options = {}) {
    const video = options.video;
    const expectedOrigin = options.expectedOrigin || global.location?.origin || '';
    const scanInterval = Math.max(250, Number(options.scanInterval) || 500);
    let scanner = null;
    let nativeStream = null;
    let nativeTimer = null;
    let cameras = [];
    let engine = '';
    let locked = false;

    const reportCameras = (items, selectedId) => {
      options.onCameras?.(items.map(item => ({ id: String(item.id), name: String(item.name || 'Camera') })), String(selectedId || ''));
    };

    const stop = async () => {
      global.clearInterval(nativeTimer);
      nativeTimer = null;
      if (scanner) {
        const current = scanner;
        scanner = null;
        try {
          await current.stop();
        } catch (error) {
          // Camera tracks are still cleared below where the browser exposes them.
        }
      }
      if (nativeStream) {
        nativeStream.getTracks().forEach(track => track.stop());
        nativeStream = null;
      }
      if (video?.srcObject) {
        video.srcObject.getTracks?.().forEach(track => track.stop());
        video.srcObject = null;
      }
      engine = '';
    };

    const acceptScan = async rawValue => {
      if (locked) return;
      const parsed = parseValue(rawValue, expectedOrigin);
      if (!parsed) {
        options.onInvalid?.('This is not a valid SmartQMS appointment QR code. Try again or use Manual Input.');
        return;
      }
      locked = true;
      await stop();
      await options.onScan?.(parsed);
    };

    const startInstascan = async requestedId => {
      if (!global.Instascan?.Scanner || !global.Instascan?.Camera?.getCameras || !video) {
        throw new Error('Instascan is unavailable.');
      }
      const available = await global.Instascan.Camera.getCameras();
      cameras = available.map((camera, index) => ({
        id: String(camera.id ?? index),
        name: String(camera.name || `Camera ${index + 1}`),
        source: camera,
      }));
      const selected = chooseCamera(cameras, requestedId);
      if (!selected) throw new Error('No camera was found.');

      scanner = new global.Instascan.Scanner({
        video,
        continuous: true,
        mirror: !REAR_CAMERA_PATTERN.test(selected.name),
        captureImage: false,
        backgroundScan: false,
        refractoryPeriod: 5000,
        scanPeriod: 2,
      });
      scanner.addListener('scan', acceptScan);
      await scanner.start(selected.source);
      engine = 'instascan';
      reportCameras(cameras, selected.id);
      return { engine, cameras, selectedId: selected.id };
    };

    const startNative = async requestedId => {
      if (!global.navigator?.mediaDevices?.getUserMedia || typeof global.BarcodeDetector !== 'function' || !video) {
        throw new Error('Camera scanning is unavailable in this browser.');
      }

      const detector = new global.BarcodeDetector({ formats: ['qr_code'] });
      const videoConstraint = requestedId
        ? { deviceId: { exact: requestedId } }
        : { facingMode: { ideal: 'environment' } };
      nativeStream = await global.navigator.mediaDevices.getUserMedia({ video: videoConstraint, audio: false });
      video.srcObject = nativeStream;
      await video.play();

      const devices = typeof global.navigator.mediaDevices.enumerateDevices === 'function'
        ? await global.navigator.mediaDevices.enumerateDevices()
        : [];
      cameras = devices
        .filter(device => device.kind === 'videoinput')
        .map((device, index) => ({ id: device.deviceId, name: device.label || `Camera ${index + 1}` }));
      const selected = chooseCamera(cameras, requestedId);
      engine = 'native';
      reportCameras(cameras, selected?.id || requestedId);

      nativeTimer = global.setInterval(async () => {
        if (!nativeStream || global.document?.hidden || locked) return;
        try {
          const codes = await detector.detect(video);
          if (codes[0]?.rawValue) await acceptScan(codes[0].rawValue);
        } catch (error) {
          // A moving or unfocused frame can fail without ending the scan session.
        }
      }, scanInterval);
      return { engine, cameras, selectedId: selected?.id || requestedId || '' };
    };

    const start = async (requestedId = '') => {
      await stop();
      locked = false;
      try {
        return await startInstascan(requestedId);
      } catch (instascanError) {
        await stop();
        options.onFallback?.('Instascan was unavailable. SmartQMS is using the browser scanner instead.');
        return startNative(requestedId);
      }
    };

    const switchCamera = async cameraId => {
      const preferredEngine = engine;
      await stop();
      locked = false;
      if (preferredEngine === 'native') return startNative(cameraId);
      try {
        return await startInstascan(cameraId);
      } catch (error) {
        return startNative(cameraId);
      }
    };

    return {
      start,
      stop,
      switchCamera,
      getEngine: () => engine,
    };
  }

  global.SmartQmsArrivalScanner = Object.freeze({
    create,
    parseValue,
    chooseCamera,
    isSmartQmsPath,
  });
})(window);
