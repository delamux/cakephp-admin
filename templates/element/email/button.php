<?php
/**
 * "Bulletproof" email button: table cell background (Outlook) + padded link (everyone else).
 *
 * @var \App\View\AppView $this
 * @var string $text
 * @var string $url Absolute URL.
 */
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 24px;">
    <tr>
        <td class="button-td" align="center" bgcolor="#18181b">
            <a class="button" href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h($text) ?></a>
        </td>
    </tr>
</table>
