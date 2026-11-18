<?php
declare(strict_types=1);

namespace App\Rbac;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

/**
 * Discovers controller actions for the permissions catalogue.
 *
 * An action is what CakePHP can dispatch (Controller::isAction()): a public, non-static method not
 * defined by Cake\Controller\Controller (trait methods included), minus `_`-prefixed methods and the
 * configured helpers (`App.permissions.ignoredActions`). AppController/ErrorController and abstract
 * classes are skipped. Sub-folders of a Controller folder are routing prefixes (Admin/…).
 */
class ActionDiscovery
{
    /**
     * @param list<array{plugin: string, namespace: string, path: string}> $sources Controller folders.
     * @param list<string> $ignoredActions Public methods that are not actions.
     */
    public function __construct(private array $sources, private array $ignoredActions = [])
    {
    }

    /**
     * App controllers + `App.permissions.plugins` (default: CakeDC/Users).
     *
     * @return self
     */
    public static function fromConfig(): self
    {
        $sources = [['plugin' => '', 'namespace' => 'App\\Controller', 'path' => APP . 'Controller' . DS]];
        foreach ((array)Configure::read('App.permissions.plugins', ['CakeDC/Users']) as $plugin) {
            if (!Plugin::isLoaded($plugin)) {
                continue;
            }
            $sources[] = [
                'plugin' => $plugin,
                'namespace' => str_replace('/', '\\', $plugin) . '\\Controller',
                'path' => Plugin::classPath($plugin) . 'Controller' . DS,
            ];
        }

        /** @var list<string> $ignored */
        $ignored = (array)Configure::read('App.permissions.ignoredActions', [
            'getUsersTable', 'setUsersTable', 'validateReCaptcha',
        ]);

        return new self($sources, $ignored);
    }

    /**
     * `Plugin.Prefix/Controller::action` (plugin and prefix omitted when empty).
     *
     * @param array<string, mixed> $action Action data.
     * @return string
     */
    public static function key(array $action): string
    {
        $plugin = (string)($action['plugin'] ?? '');
        $prefix = (string)($action['prefix'] ?? '');

        return ($plugin !== '' ? $plugin . '.' : '') . ($prefix !== '' ? $prefix . '/' : '')
            . $action['controller'] . '::' . $action['action'];
    }

    /**
     * @return list<array{plugin: string, prefix: string, controller: string, action: string}>
     */
    public function actions(): array
    {
        $actions = [];
        foreach ($this->sources as $source) {
            foreach ($this->controllerFiles($source['path']) as $relative) {
                $class = $source['namespace'] . '\\' . str_replace('/', '\\', substr($relative, 0, -4));
                $name = substr(basename($relative), 0, -strlen('Controller.php'));
                if (in_array($name, ['App', 'Error'], true) || !class_exists($class)) {
                    continue;
                }
                $reflection = new ReflectionClass($class);
                if ($reflection->isAbstract() || !$reflection->isSubclassOf(Controller::class)) {
                    continue;
                }
                $prefix = dirname($relative) === '.' ? '' : dirname($relative);
                foreach ($this->publicActions($reflection) as $method) {
                    $action = [
                        'plugin' => $source['plugin'],
                        'prefix' => $prefix,
                        'controller' => $name,
                        'action' => $method,
                    ];
                    $actions[self::key($action)] = $action;
                }
            }
        }
        ksort($actions, SORT_STRING);

        return array_values($actions);
    }

    /**
     * @param \ReflectionClass<\Cake\Controller\Controller> $controller Controller class.
     * @return list<string>
     */
    private function publicActions(ReflectionClass $controller): array
    {
        $methods = [];
        foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (
                $method->isStatic()
                || str_starts_with($name, '_')
                || method_exists(Controller::class, $name)
                || in_array($name, $this->ignoredActions, true)
            ) {
                continue;
            }
            $methods[] = $name;
        }

        return $methods;
    }

    /**
     * Controller files relative to `$path` (`Admin/ArticlesController.php`).
     *
     * @param string $path Folder.
     * @return list<string>
     */
    private function controllerFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Controller.php')) {
                $files[] = str_replace(DS, '/', substr($file->getPathname(), strlen($path)));
            }
        }
        sort($files);

        return $files;
    }
}
