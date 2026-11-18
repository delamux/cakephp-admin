<?php
/**
 * Header action button: icon + label on >= sm, icon-only on mobile
 * (BasecoatTheme ButtonHelper `collapse` option keeps the label for screen readers and as title).
 *
 * @var \App\View\AppView $this
 * @var string $text
 * @var array|string $url
 * @var string $icon
 * @var string|null $variant
 */
echo $this->Button->link($text, $url, [
    'icon' => $icon,
    'variant' => $variant ?? 'primary',
    'size' => 'sm',
    'collapse' => 'sm',
]);
