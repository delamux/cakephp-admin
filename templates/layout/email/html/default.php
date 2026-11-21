<?php
/**
 * HTML email layout — built for maximum client compatibility:
 * - table-based 600px layout (Outlook/Word rendering engine has no flex/grid/max-width),
 * - CSS written in <style> and inlined at send time (App\Mailer\CssInliner / Emogrifier),
 * @media rules (mobile + dark mode) stay in <head> for clients that support them,
 * - MSO conditionals for Outlook DPI scaling, hidden preheader text, no JS, no web fonts required,
 * - role="presentation" on layout tables for screen readers.
 *
 * Blocks: `title`, `preheader` (inbox preview text), `content`.
 *
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;

$brand = (string)Configure::read('BasecoatTheme.brand.name', 'CakePHP Admin');
$baseUrl = rtrim((string)Configure::read('App.fullBaseUrl', ''), '/');
$year = date('Y');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title><?= h($this->fetch('title') ?: $brand) ?></title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <style>table, td, h1, p, a { font-family: Arial, Helvetica, sans-serif !important; }</style>
    <![endif]-->
    <style>
        body { margin: 0; padding: 0; width: 100%; background-color: #f4f4f5; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        .wrapper { width: 100%; background-color: #f4f4f5; }
        .container { width: 600px; }
        .brand-cell { padding: 32px 0 20px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 700; color: #09090b; }
        .brand-mark { display: inline-block; width: 28px; height: 28px; line-height: 28px; border-radius: 6px; background-color: #18181b; color: #ffffff; font-size: 15px; text-align: center; vertical-align: middle; }
        .card { background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; }
        .card-cell { padding: 40px 40px 32px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 24px; color: #3f3f46; }
        h1 { margin: 0 0 16px; font-size: 22px; line-height: 30px; font-weight: 700; color: #09090b; }
        p { margin: 0 0 16px; }
        a { color: #18181b; text-decoration: underline; }
        .muted { color: #71717a; font-size: 13px; line-height: 20px; }
        .button-td { border-radius: 8px; background-color: #18181b; }
        .button { display: inline-block; padding: 12px 24px; border: 1px solid #18181b; border-radius: 8px; background-color: #18181b; color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600; line-height: 20px; text-decoration: none; mso-padding-alt: 0; }
        .code { display: inline-block; padding: 10px 16px; border-radius: 8px; background-color: #f4f4f5; border: 1px solid #e4e4e7; font-family: 'SFMono-Regular', Menlo, Consolas, monospace; font-size: 20px; letter-spacing: 4px; color: #09090b; }
        .link-box { padding: 12px 16px; border-radius: 8px; background-color: #fafafa; border: 1px solid #f4f4f5; font-size: 12px; line-height: 18px; color: #52525b; }
        .footer-cell { padding: 24px 24px 40px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; line-height: 18px; color: #a1a1aa; }
        .preheader { display: none; mso-hide: all; font-size: 1px; line-height: 1px; color: #f4f4f5; }

        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .card-cell { padding: 28px 20px 24px !important; }
            .outer-pad { padding-left: 12px !important; padding-right: 12px !important; }
            .button { display: block !important; text-align: center !important; }
        }
        @media (prefers-color-scheme: dark) {
            body, .wrapper { background-color: #09090b !important; }
            .card { background-color: #18181b !important; border-color: #27272a !important; }
            .card-cell { color: #d4d4d8 !important; }
            h1, .brand-cell { color: #fafafa !important; }
            a { color: #fafafa !important; }
            .brand-mark, .button-td, .button { background-color: #fafafa !important; border-color: #fafafa !important; color: #18181b !important; }
            .code, .link-box { background-color: #27272a !important; border-color: #3f3f46 !important; color: #e4e4e7 !important; }
        }
    </style>
</head>
<body>
<div class="preheader"><?= h($this->fetch('preheader')) ?>&#847; &zwnj; &nbsp; &#847; &zwnj; &nbsp; &#847; &zwnj; &nbsp;</div>
<table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center" class="outer-pad" style="padding: 0 24px;">
            <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->
            <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" align="center">
                <tr>
                    <td class="brand-cell">
                        <?php if ($baseUrl !== '') :
                            ?><a href="<?= h($baseUrl) ?>" style="color: inherit; text-decoration: none;"><?php
                        endif; ?>
                        <span class="brand-mark"><?= h(mb_strtoupper(mb_substr($brand, 0, 1))) ?></span>&nbsp;&nbsp;<?= h($brand) ?>
                        <?php if ($baseUrl !== '') :
                            ?></a><?php
                        endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table role="presentation" class="card" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td class="card-cell">
                                    <?= $this->fetch('content') ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="footer-cell">
                        <?= __('You received this email because an account action was requested on {0}.', h($brand)) ?><br />
                        <?= __('If it was not you, you can safely ignore this message.') ?><br /><br />
                        &copy; <?= $year ?> <?= h($brand) ?>
                    </td>
                </tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td>
    </tr>
</table>
</body>
</html>
