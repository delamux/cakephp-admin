<?php
/**
 * CakeDC/Users templates are plugin-prefixed, so CakePHP looks for the layout in the plugin first.
 * Reuse the app email layout (single source of truth).
 *
 * @var \App\View\AppView $this
 */
include ROOT . DS . 'templates' . DS . 'layout' . DS . 'email' . DS . 'html' . DS . 'default.php';
