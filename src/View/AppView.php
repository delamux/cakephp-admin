<?php
declare(strict_types=1);

namespace App\View;

use BasecoatTheme\View\BasecoatHelpersTrait;
use Cake\Core\Configure;
use Cake\View\View;

/**
 * Application View: all Basecoat component helpers (Form included) + CakeDC/Users helpers.
 *
 * @property \BasecoatTheme\View\Helper\AccordionHelper $Accordion
 * @property \BasecoatTheme\View\Helper\AlertHelper $Alert
 * @property \BasecoatTheme\View\Helper\AvatarHelper $Avatar
 * @property \BasecoatTheme\View\Helper\BadgeHelper $Badge
 * @property \BasecoatTheme\View\Helper\BreadcrumbHelper $Breadcrumb
 * @property \BasecoatTheme\View\Helper\ButtonHelper $Button
 * @property \BasecoatTheme\View\Helper\ButtonGroupHelper $ButtonGroup
 * @property \BasecoatTheme\View\Helper\CardHelper $Card
 * @property \BasecoatTheme\View\Helper\ChartHelper $Chart
 * @property \BasecoatTheme\View\Helper\DialogHelper $Dialog
 * @property \BasecoatTheme\View\Helper\DropdownMenuHelper $DropdownMenu
 * @property \BasecoatTheme\View\Helper\EmptyStateHelper $EmptyState
 * @property \BasecoatTheme\View\Helper\FormHelper $Form
 * @property \BasecoatTheme\View\Helper\IconHelper $Icon
 * @property \BasecoatTheme\View\Helper\KbdHelper $Kbd
 * @property \BasecoatTheme\View\Helper\PaginationHelper $Pagination
 * @property \BasecoatTheme\View\Helper\ProgressHelper $Progress
 * @property \BasecoatTheme\View\Helper\SidebarHelper $Sidebar
 * @property \BasecoatTheme\View\Helper\SkeletonHelper $Skeleton
 * @property \BasecoatTheme\View\Helper\TableHelper $Table
 * @property \BasecoatTheme\View\Helper\TabsHelper $Tabs
 * @property \BasecoatTheme\View\Helper\ThemeHelper $Theme
 * @property \BasecoatTheme\View\Helper\ToastHelper $Toast
 * @property \BasecoatTheme\View\Helper\TooltipHelper $Tooltip
 * @property \CakeDC\Users\View\Helper\UserHelper $User
 * @property \CakeDC\Users\View\Helper\AuthLinkHelper $AuthLink
 * @property \App\View\Helper\UserPhotoHelper $UserPhoto
 */
class AppView extends View
{
    use BasecoatHelpersTrait;

    /**
     * @var array<string, string>
     */
    private const EXTRA_ICONS = [
        'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/>'
            . '<line x1="12" x2="12" y1="17" y2="21"/>',
        'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 '
            . '1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'
            . '<path d="m9 12 2 2 4-4"/>',
        'key-round' => '<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 '
            . '1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/>'
            . '<circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 '
            . '1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'log-in' => '<path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>'
            . '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>'
            . '<path d="M12 7v5l4 2"/>',
    ];

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadBasecoatHelpers([
            'Theme' => self::themeConfig(),
            // Lucide icons the theme subset lacks (2FA / passkeys)
            'Icon' => ['icons' => self::EXTRA_ICONS],
        ]);
        $this->addHelper('CakeDC/Users.User');
        // htmx (self-hosted with compiled assets, pinned CDN in dev) with a CSP-compatible config: no
        // eval, no injected <style>, scripts in swapped content get the page nonce.
        $request = $this->getRequest();
        $this->Html->meta('csrf-token', (string)$request->getAttribute('csrfToken'), ['block' => true]);
        $this->Html->meta('htmx-config', (string)json_encode([
            'allowEval' => false,
            'includeIndicatorStyles' => false,
            'inlineScriptNonce' => (string)$request->getAttribute('cspScriptNonce'),
            'inlineStyleNonce' => (string)$request->getAttribute('cspStyleNonce'),
            'historyCacheSize' => 0,
        ]), ['block' => true]);
        $this->Html->script(self::htmxUrl(), ['block' => true, 'defer' => true]);
        // App behaviours without inline JS (confirmations, link-submitted forms, htmx CSRF). With nonce.
        $this->Html->script('app', ['block' => true, 'defer' => true]);
        $this->addHelper('CakeDC/Users.AuthLink');
        // Private user photos (App\Controller\UserPhotoController)
        $this->addHelper('UserPhoto');
    }

    public const HTMX_VERSION = '2.0.11';

    /**
     * @return string htmx script (webroot/js/vendor in production, jsDelivr in dev).
     */
    public static function htmxUrl(): string
    {
        return Configure::read('BasecoatTheme.compiledAssets')
            ? '/js/vendor/htmx.min.js'
            : 'https://cdn.jsdelivr.net/npm/htmx.org@' . self::HTMX_VERSION . '/dist/htmx.min.js';
    }

    /**
     * Theme assets: CDN + Tailwind browser build (dev, zero build) or, with
     * `BasecoatTheme.compiledAssets`, everything self-hosted: compiled stylesheet with the Geist
     * font, Basecoat JS and Chart.js (no CDN requests).
     *
     * @return array<string, mixed>
     */
    public static function themeConfig(): array
    {
        if (!Configure::read('BasecoatTheme.compiledAssets')) {
            return [];
        }

        return [
            'css' => ['app'],
            'tailwindBrowser' => false,
            'js' => '/js/basecoat/all.min.js',
            'chartJsBasecoat' => '/js/basecoat/chart.min.js',
            'chartJs' => '/js/vendor/chart.umd.js',
            // Geist is declared in app.css (@font-face, webroot/font/geist): no Google Fonts request
            'font' => false,
        ];
    }
}
