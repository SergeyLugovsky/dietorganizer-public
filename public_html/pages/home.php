<?php
// public_html/pages/home.php
?>
<!DOCTYPE html>
<html lang="<?php echo h(i18n_get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(t('Diet Organizer — ваш щоденник харчування')); ?></title>
    <link rel="stylesheet" href="/css/home.css?v=<?php echo filemtime(APP_PUBLIC . '/css/home.css'); ?>">
</head>
<body>
    <div class="wrapper">
        <?php require APP_PUBLIC . '/includes/header.php'; ?>

        <main>
            <section class="hero">
                <div class="container hero-inner">
                    <div>
                        <h1><?php echo h(t('Особистий щоденник харчування та контроль калорій')); ?></h1>
                        <p class="lead"><?php echo h(t('Ведіть раціон, додавайте продукти, відстежуйте калорійність і макроси за днями. Усе просто: увійшли, записали прийом їжі, побачили статистику.')); ?></p>
                        <div class="cta">
                            <a class="btn primary" href="/register"><?php echo h(t('Почати безкоштовно')); ?></a>
                            <a class="btn secondary" href="/login"><?php echo h(t('У мене вже є акаунт')); ?></a>
                        </div>
                    </div>
                    <div class="badge-list">
                        <div class="badge"><strong><?php echo h(t('Швидкий старт')); ?></strong><span><?php echo h(t('Реєстрація за хвилину, без зайвих полів.')); ?></span></div>
                        <div class="badge"><strong><?php echo h(t('Свої продукти')); ?></strong><span><?php echo h(t('Додавайте позиції і БЖВ під ваш раціон.')); ?></span></div>
                        <div class="badge"><strong><?php echo h(t('Контроль за днями')); ?></strong><span><?php echo h(t('Дивіться калорії та макроси за будь-який день.')); ?></span></div>
                    </div>
                </div>
            </section>

            <section class="features">
                <div class="container features-grid">
                    <div class="card">
                        <h3><?php echo h(t('Категорії прийомів їжі')); ?></h3>
                        <p><?php echo h(t('Створюйте категорії (сніданок, обід, вечеря, перекус) і фіксуйте, що з’їли та скільки.')); ?></p>
                    </div>
                    <div class="card">
                        <h3><?php echo h(t('Власна база продуктів')); ?></h3>
                        <p><?php echo h(t('Додавайте продукти з калоріями та БЖВ на 100 г, щоб швидко вносити записи в щоденник.')); ?></p>
                    </div>
                    <div class="card">
                        <h3><?php echo h(t('Швидкі підсумки')); ?></h3>
                        <p><?php echo h(t('За датами видно, скільки калорій і макросів набрано. Розуміння раціону без зайвих таблиць.')); ?></p>
                    </div>
                </div>
            </section>
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
