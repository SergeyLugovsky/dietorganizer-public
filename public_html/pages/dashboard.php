<?php
// public_html/pages/dashboard.php
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="<?php echo h(i18n_get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(t('Особистий кабінет')); ?></title>
    <link rel="stylesheet" href="/css/home.css?v=<?php echo filemtime(APP_PUBLIC . '/css/home.css'); ?>">
</head>
<body>
    <div class="wrapper">
        <?php require APP_PUBLIC . '/includes/header.php'; ?>

        <main>
            <div class="container page">
                <h1><?php echo h(t('Особистий кабінет')); ?></h1>
                <p><?php echo h(t('Вітаємо, :name.', ['name' => $user['name'] ?? t('Користувач')])); ?></p>
                <div class="menu-cards">
                    <a class="menu-card" href="/foods">
                        <div class="menu-card-title"><?php echo h(t('Продукти')); ?></div>
                        <div class="menu-card-text"><?php echo h(t('Список продуктів і керування ними.')); ?></div>
                    </a>
                    <a class="menu-card" href="/meal_categories">
                        <div class="menu-card-title"><?php echo h(t('Категорії прийомів їжі')); ?></div>
                        <div class="menu-card-text"><?php echo h(t('Створюйте та редагуйте категорії.')); ?></div>
                    </a>
                    <a class="menu-card" href="/diary">
                        <div class="menu-card-title"><?php echo h(t('Щоденник')); ?></div>
                        <div class="menu-card-text"><?php echo h(t('Записуйте прийоми їжі за днями.')); ?></div>
                    </a>
                    <a class="menu-card" href="/profile">
                        <div class="menu-card-title"><?php echo h(t('Профіль')); ?></div>
                        <div class="menu-card-text"><?php echo h(t('Дані акаунта та налаштування.')); ?></div>
                    </a>
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
    <?php require APP_PUBLIC . '/includes/client_bootstrap.php'; ?>
</body>
</html>
