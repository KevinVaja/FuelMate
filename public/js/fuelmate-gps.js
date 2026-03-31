(() => {
    const HIGH_ACCURACY_OPTIONS = {
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: 10000,
    };

    const FALLBACK_OPTIONS = {
        enableHighAccuracy: false,
        maximumAge: 5000,
        timeout: 15000,
    };

    const ERROR_MESSAGES = {
        unavailable: "Unable to fetch precise location. Please enable GPS and location services.",
        insecure: "FuelMate location sharing requires HTTPS or localhost. Please open this page in a secure context.",
        unsupported: "This browser does not support GPS location access.",
        invalid: "Invalid GPS coordinates were received. Retrying for a better fix.",
        waiting: "Waiting for a more precise GPS fix.",
    };

    const isLoopbackHost = () => ["localhost", "127.0.0.1", "[::1]"].includes(window.location.hostname);
    const canUseGeolocation = () => window.isSecureContext || isLoopbackHost();

    const errorCodeLabel = (error) => {
        switch (error?.code) {
            case error?.PERMISSION_DENIED:
            case 1:
                return "permission_denied";
            case error?.POSITION_UNAVAILABLE:
            case 2:
                return "position_unavailable";
            case error?.TIMEOUT:
            case 3:
                return "timeout";
            default:
                return "unknown";
        }
    };

    const validateCoordinate = (value, min, max) =>
        typeof value === "number" &&
        Number.isFinite(value) &&
        value >= min &&
        value <= max;

    class FuelMateGpsTracker {
        constructor(options = {}) {
            this.options = {
                minimumAcceptedAccuracyMeters: 50,
                retryAccuracyThresholdMeters: 100,
                decimals: 8,
                debug: true,
                fallbackRetryIntervalMs: 10000,
                alertOnPermissionDenied: true,
                onAccepted: () => {},
                onStatus: () => {},
                onError: () => {},
                ...options,
            };

            this.watchId = null;
            this.lastAcceptedLocation = null;
            this.lastFallbackAttemptAt = 0;
            this.fallbackInFlight = false;
            this.permissionAlertShown = false;
        }

        start() {
            if (!("geolocation" in navigator)) {
                this.reportStatus(ERROR_MESSAGES.unsupported, { level: "error" });
                return false;
            }

            if (!canUseGeolocation()) {
                this.reportStatus(ERROR_MESSAGES.insecure, { level: "error" });
                return false;
            }

            this.reportStatus("Requesting precise GPS location...", { level: "info" });

            this.watchId = navigator.geolocation.watchPosition(
                (position) => this.handleHighAccuracySuccess(position),
                (error) => this.handleWatchError(error),
                HIGH_ACCURACY_OPTIONS,
            );

            return true;
        }

        stop() {
            if (this.watchId !== null) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
        }

        getLatestAcceptedLocation() {
            return this.lastAcceptedLocation;
        }

        handleHighAccuracySuccess(position) {
            const candidate = this.normalizePosition(position, "watch_high");

            if (!candidate) {
                return;
            }

            if (candidate.accuracyMeters === null) {
                this.reportStatus(`${ERROR_MESSAGES.waiting} Accuracy information is unavailable.`, {
                    level: "warning",
                });
                return;
            }

            if (candidate.accuracyMeters > this.options.retryAccuracyThresholdMeters) {
                this.reportStatus(
                    `GPS accuracy is weak (${Math.round(candidate.accuracyMeters)}m). Retrying for a stronger signal...`,
                    { level: "warning" },
                );
                this.requestFallbackPosition("weak_accuracy");
                return;
            }

            if (candidate.accuracyMeters > this.options.minimumAcceptedAccuracyMeters) {
                this.reportStatus(
                    `${ERROR_MESSAGES.waiting} Current accuracy is ${Math.round(candidate.accuracyMeters)}m.`,
                    { level: "warning" },
                );
                return;
            }

            this.acceptCandidate(candidate);
        }

        handleWatchError(error) {
            const errorType = errorCodeLabel(error);

            if (errorType === "permission_denied") {
                this.reportPermissionDenied();
                return;
            }

            if (errorType === "position_unavailable") {
                this.reportStatus("Precise GPS is temporarily unavailable. Retrying now...", {
                    level: "warning",
                });
                this.requestFallbackPosition(errorType);
                return;
            }

            if (errorType === "timeout") {
                this.reportStatus("Precise GPS timed out. Retrying with fallback location mode...", {
                    level: "warning",
                });
                this.requestFallbackPosition(errorType);
                return;
            }

            this.reportStatus(ERROR_MESSAGES.unavailable, { level: "error", error });
        }

        requestFallbackPosition(reason) {
            if (!("geolocation" in navigator) || this.fallbackInFlight) {
                return;
            }

            const now = Date.now();
            if (now - this.lastFallbackAttemptAt < this.options.fallbackRetryIntervalMs) {
                return;
            }

            this.lastFallbackAttemptAt = now;
            this.fallbackInFlight = true;

            navigator.geolocation.getCurrentPosition(
                (position) => this.handleFallbackSuccess(position, reason),
                (error) => this.handleFallbackError(error, reason),
                FALLBACK_OPTIONS,
            );
        }

        handleFallbackSuccess(position, reason) {
            this.fallbackInFlight = false;

            const candidate = this.normalizePosition(position, `fallback_${reason}`);

            if (!candidate) {
                return;
            }

            if (
                candidate.accuracyMeters === null ||
                candidate.accuracyMeters > this.options.minimumAcceptedAccuracyMeters
            ) {
                const accuracyText = candidate.accuracyMeters === null
                    ? "unknown"
                    : `${Math.round(candidate.accuracyMeters)}m`;

                this.reportStatus(
                    `Unable to fetch precise location. Current accuracy is ${accuracyText}. Please enable GPS and location services.`,
                    { level: "error" },
                );
                return;
            }

            this.acceptCandidate(candidate);
        }

        handleFallbackError(error) {
            this.fallbackInFlight = false;

            if (errorCodeLabel(error) === "permission_denied") {
                this.reportPermissionDenied();
                return;
            }

            this.reportStatus(ERROR_MESSAGES.unavailable, { level: "error", error });
        }

        reportPermissionDenied() {
            this.reportStatus(
                "Location permission is blocked. Please enable GPS and location services.",
                { level: "error" },
            );

            if (!this.permissionAlertShown && this.options.alertOnPermissionDenied) {
                this.permissionAlertShown = true;
                window.alert("Please enable location access, GPS, and location services for FuelMate.");
            }
        }

        acceptCandidate(candidate) {
            this.lastAcceptedLocation = candidate;
            this.reportStatus(
                `Precise location locked (${Math.round(candidate.accuracyMeters ?? 0)}m accuracy).`,
                { level: "success", candidate },
            );
            this.options.onAccepted(candidate);
        }

        normalizePosition(position, source) {
            const latitude = Number(position?.coords?.latitude);
            const longitude = Number(position?.coords?.longitude);
            const accuracy = Number(position?.coords?.accuracy);
            const roundedLatitude = Number(latitude.toFixed(this.options.decimals));
            const roundedLongitude = Number(longitude.toFixed(this.options.decimals));
            const accuracyMeters = Number.isFinite(accuracy) ? accuracy : null;

            if (this.options.debug) {
                console.log("Latitude:", roundedLatitude);
                console.log("Longitude:", roundedLongitude);
                console.log("Accuracy:", accuracyMeters);
            }

            if (
                !validateCoordinate(roundedLatitude, -90, 90) ||
                !validateCoordinate(roundedLongitude, -180, 180)
            ) {
                this.reportStatus(ERROR_MESSAGES.invalid, { level: "warning" });
                return null;
            }

            return {
                latitude: roundedLatitude,
                longitude: roundedLongitude,
                accuracyMeters,
                source,
                timestamp: Number.isFinite(position?.timestamp) ? position.timestamp : Date.now(),
            };
        }

        reportStatus(message, context = {}) {
            this.options.onStatus(message, context);

            if (context.error) {
                this.options.onError(context.error, message, context);
            }
        }
    }

    window.FuelMateGps = {
        HIGH_ACCURACY_OPTIONS,
        FALLBACK_OPTIONS,
        createTracker(options = {}) {
            return new FuelMateGpsTracker(options);
        },
    };
})();
