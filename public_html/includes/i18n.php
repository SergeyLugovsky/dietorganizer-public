<?php
// public_html/includes/i18n.php

if (!function_exists('t')) {
    function i18n_get_locale(): string
    {
        return 'uk';
    }

    function t(string $key, array $params = []): string
    {
        $value = $key;
        if ($params) {
            foreach ($params as $paramKey => $paramValue) {
                $value = str_replace(':' . $paramKey, (string)$paramValue, $value);
            }
        }
        return $value;
    }
}
