<?php
// public_html/pages/profile.php
require_login();
require APP_PUBLIC . '/includes/db.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="<?php echo h(i18n_get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(t('Профіль')); ?></title>
    <link rel="stylesheet" href="/css/home.css?v=<?php echo filemtime(APP_PUBLIC . '/css/home.css'); ?>">
</head>
<body>
    <div class="wrapper">
        <?php require APP_PUBLIC . '/includes/header.php'; ?>

        <main>
            <div class="container page">
                <h1><?php echo h(t('Профіль')); ?></h1>
                <p><?php echo h(t('Імʼя:')); ?> <?php echo h($user['name'] ?? ''); ?></p>
                <p><?php echo h(t('Email')); ?>: <?php echo h($user['email'] ?? ''); ?></p>
                <p><?php echo h(t('Редагування профілю буде доступне пізніше.')); ?></p>

                <div id="push-settings">
                    <h2><?php echo h(t('Сповіщення')); ?></h2>
                    <p><?php echo h(t('Сповіщення для нагадувань про їжу доступні лише в мобільному застосунку.')); ?></p>
                    <div>
                        <button class="btn primary" type="button" id="open-native-push-settings">
                            <?php echo h(t('Налаштувати сповіщення в застосунку')); ?>
                        </button>
                    </div>
                    <p id="push-status"></p>
                </div>
            </div>
        </main>

        <footer>
            <div class="container">
                <?php echo h(t('Diet Organizer • простий спосіб вести щоденник харчування і тримати форму')); ?>
            </div>
        </footer>
    </div>

    <script>
        (function () {
            const btn = document.querySelector('.menu-toggle');
            const nav = document.querySelector('.nav-links');
            const closeBtn = document.querySelector('.menu-close');
            if (!btn || !nav) return;

            btn.addEventListener('click', function () {
                btn.classList.toggle('open');
                nav.classList.toggle('open');
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    nav.classList.remove('open');
                    btn.classList.remove('open');
                });
            }

            nav.addEventListener('click', function (e) {
                if (e.target.tagName === 'A' && nav.classList.contains('open')) {
                    nav.classList.remove('open');
                    btn.classList.remove('open');
                }
            });
        })();
    </script>

    <script>
        (function () {
            const texts = <?php echo json_encode([
                'appOnly' => t('Сповіщення доступні лише в мобільному застосунку.'),
                'openNativePushSettings' => t('Відкрийте налаштування сповіщень у мобільному застосунку.'),
                'nativeBridgeUnavailable' => t('Не вдалося відкрити налаштування. Оновіть застосунок до останньої версії.'),
            ]); ?>;

            const pushSettings = document.querySelector('#push-settings');
            if (!pushSettings) return;

            const openBtn = document.querySelector('#open-native-push-settings');
            const statusEl = document.querySelector('#push-status');
            const isNativeAppWebView = !!window.ReactNativeWebView;

            function setStatus(text) {
                if (statusEl) {
                    statusEl.textContent = text;
                }
            }

            if (!isNativeAppWebView) {
                if (openBtn) {
                    openBtn.style.display = 'none';
                }
                setStatus(texts.appOnly);
                return;
            }

            setStatus(texts.openNativePushSettings);

            if (!openBtn) {
                return;
            }

            openBtn.disabled = false;
            openBtn.addEventListener('click', function () {
                if (!window.DietOrganizer || typeof window.DietOrganizer.openNativePushSettings !== 'function') {
                    setStatus(texts.nativeBridgeUnavailable);
                    return;
                }

                const opened = window.DietOrganizer.openNativePushSettings();
                setStatus(opened ? texts.openNativePushSettings : texts.nativeBridgeUnavailable);
            });
        })();
    </script>

    <?php require APP_PUBLIC . '/includes/client_bootstrap.php'; ?>
</body>
</html>
