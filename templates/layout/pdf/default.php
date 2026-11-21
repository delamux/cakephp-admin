<?php
/**
 * PDF layout (Dompdf: CSS 2.1, no flexbox/grid → tables for layout).
 *
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;

$brand = (string)Configure::read('BasecoatTheme.brand.name', 'CakePHP Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= h($this->fetch('title')) ?></title>
    <style>
        @page { margin: 90px 40px 60px 40px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { color: #09090b; font-size: 10px; line-height: 1.45; }
        header { position: fixed; top: -65px; left: 0; right: 0; height: 45px; border-bottom: 1px solid #e4e4e7; }
        footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 20px; color: #71717a; font-size: 8px; border-top: 1px solid #e4e4e7; padding-top: 6px; }
        /* Dompdf supports counter(page) but not counter(pages) */
        footer .page:after { content: "<?= h(__('Page')) ?> " counter(page); }
        .brand { font-size: 14px; font-weight: bold; }
        .muted { color: #71717a; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.data th { background: #f4f4f5; color: #52525b; font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; text-align: left; padding: 6px 8px; border-bottom: 1px solid #e4e4e7; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #f4f4f5; vertical-align: top; }
        table.data tr:nth-child(even) td { background: #fafafa; }
        table.kpis { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 12px -8px 4px; }
        table.kpis td { border: 1px solid #e4e4e7; border-radius: 6px; padding: 10px 12px; width: 25%; }
        .kpi-label { color: #71717a; font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; }
        .kpi-value { font-size: 18px; font-weight: bold; margin-top: 2px; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; border: 1px solid #e4e4e7; font-size: 8px; }
        .badge-ok { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .badge-warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .badge-dark { background: #18181b; border-color: #18181b; color: #ffffff; }
    </style>
</head>
<body>
<header>
    <table width="100%"><tr>
        <td class="brand"><?= h($brand) ?></td>
        <td class="muted" style="text-align: right;"><?= h($this->fetch('title')) ?></td>
    </tr></table>
</header>
<footer>
    <table width="100%"><tr>
        <td><?= h($brand) ?> · <?= __('Confidential') ?></td>
        <td style="text-align: right;"><span class="page"></span></td>
    </tr></table>
</footer>
<main><?= $this->fetch('content') ?></main>
</body>
</html>
