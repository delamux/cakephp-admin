<?php
/**
 * Plain-text email layout (multipart alternative for clients without HTML).
 *
 * @var \Cake\View\View $this
 */
use Cake\Core\Configure;

$brand = (string)Configure::read('BasecoatTheme.brand.name', 'CakePHP Admin');

echo $brand . "\n";
echo str_repeat('=', mb_strlen($brand)) . "\n\n";
echo trim($this->fetch('content')) . "\n\n";
echo "--\n";
echo __('You received this email because an account action was requested on {0}.', $brand) . "\n";
echo __('If it was not you, you can safely ignore this message.') . "\n";
echo '© ' . date('Y') . ' ' . $brand . "\n";
