<?php
// public_html/includes/client_bootstrap.php
?>
<script>
    (function () {
        if (!('serviceWorker' in navigator)) {
            return;
        }
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {
                // silently ignore registration errors
            });
        });
    })();
</script>
<?php if (is_logged_in()): ?>
<script>
    (function () {
        if (!window.Intl || !window.localStorage) {
            return;
        }
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!tz) {
            return;
        }
        const key = 'dietOrganizerTimezone';
        if (localStorage.getItem(key) === tz) {
            return;
        }
        fetch('/api/me/timezone.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ timezone: tz })
        }).then(function () {
            localStorage.setItem(key, tz);
        }).catch(function () {
            // ignore network errors
        });
    })();
</script>
<script>
    (function () {
        function postJson(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.json().catch(function () {
                    return { ok: false, error: 'Invalid JSON response' };
                });
            });
        }

        window.DietOrganizer = window.DietOrganizer || {};

        window.DietOrganizer.registerMobilePushToken = function (token, platform, deviceId, appVersion) {
            if (!token) {
                return Promise.resolve({ ok: false, error: 'Token is required' });
            }
            return postJson('/api/push/mobile_register.php', {
                provider: 'fcm',
                token: token,
                platform: platform || 'android',
                device_id: deviceId || null,
                app_version: appVersion || null
            });
        };

        window.DietOrganizer.unregisterMobilePushToken = function (token, deviceId, platform) {
            if (!token && !deviceId) {
                return Promise.resolve({ ok: false, error: 'Token or deviceId is required' });
            }
            return postJson('/api/push/mobile_unregister.php', {
                provider: 'fcm',
                token: token || null,
                device_id: deviceId || null,
                platform: platform || null
            });
        };

        window.DietOrganizer.openNativePushSettings = function () {
            if (!window.ReactNativeWebView || typeof window.ReactNativeWebView.postMessage !== 'function') {
                return false;
            }
            window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'open_push_settings' }));
            return true;
        };
    })();
</script>
<?php endif; ?>
