<?php
// public_html/includes/header.php
?>
<header>
    <div class="container header-inner">
        <a class="logo" href="/">
            <img class="logo-image" src="/icons/image-logo-512.png" alt="Diet Organizer">
            <span class="logo-text">Diet Organizer</span>
        </a>
        <nav class="nav">
            <button class="menu-toggle" type="button" aria-label="<?php echo h(t('Меню')); ?>">
                <hr><hr><hr>
            </button>
            <div class="nav-links">
                <button class="menu-close" type="button" aria-label="<?php echo h(t('Закрити')); ?>">&times;</button>
                <a href="/"><?php echo h(t('Головна')); ?></a>
                <?php if (is_logged_in()): ?>
                    <a href="/dashboard"><?php echo h(t('Кабінет')); ?></a>
                    <a href="/foods"><?php echo h(t('Продукти')); ?></a>
                    <a href="/meal_categories"><?php echo h(t('Категорії прийомів їжі')); ?></a>
                    <a href="/diary"><?php echo h(t('Щоденник')); ?></a>
                    <a href="/profile"><?php echo h(t('Профіль')); ?></a>
                    <a href="/logout" class="primary"><?php echo h(t('Вийти')); ?></a>
                <?php else: ?>
                    <a href="/login"><?php echo h(t('Увійти')); ?></a>
                    <a href="/register" class="primary"><?php echo h(t('Реєстрація')); ?></a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
