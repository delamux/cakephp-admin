<?php
/**
 * Raw link for clients that block buttons.
 *
 * @var \App\View\AppView $this
 * @var string $url Absolute URL.
 */
?>
<p class="muted"><?= __('If the button does not work, copy and paste this address into your browser:') ?></p>
<div class="link-box"><a href="<?= h($url) ?>" target="_blank" rel="noopener" style="color: inherit;"><?= h($url) ?></a></div>
