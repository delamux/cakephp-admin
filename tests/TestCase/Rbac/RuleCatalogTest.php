<?php
declare(strict_types=1);

namespace App\Test\TestCase\Rbac;

use App\Rbac\Rule\Owner;
use App\Rbac\RuleCatalog;
use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * The `owner` rule (the `allowed` of a CakeDC RBAC rule) and where it can be chosen.
 *
 * Uses two throwaway tables: sales_quote_headers (created_by) hasMany sales_quote_lines (no
 * created_by: the owner is the header's).
 */
class RuleCatalogTest extends TestCase
{
    private const ME = '11111111-1111-4111-8111-111111111111';
    private const OTHER = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        $db = ConnectionManager::get('test');
        $db->execute('DROP TABLE IF EXISTS sales_quote_lines');
        $db->execute('DROP TABLE IF EXISTS sales_quote_headers');
        $db->execute('CREATE TABLE sales_quote_headers (id serial PRIMARY KEY, title varchar(50), created_by uuid)');
        $db->execute('CREATE TABLE sales_quote_lines (id serial PRIMARY KEY, sales_quote_header_id integer REFERENCES sales_quote_headers(id), item varchar(50))');
        $db->execute("INSERT INTO sales_quote_headers (id, title, created_by) VALUES (1, 'mine', '" . self::ME . "'), (2, 'theirs', '" . self::OTHER . "')");
        $db->execute("INSERT INTO sales_quote_lines (id, sales_quote_header_id, item) VALUES (10, 1, 'a'), (20, 2, 'b')");

        $locator = TableRegistry::getTableLocator();
        $locator->get('SalesQuoteHeaders', ['className' => Table::class, 'table' => 'sales_quote_headers']);
        $locator->get('SalesQuoteLines', ['className' => Table::class, 'table' => 'sales_quote_lines'])->belongsTo('SalesQuoteHeaders');
    }

    protected function tearDown(): void
    {
        $db = ConnectionManager::get('test');
        $db->execute('DROP TABLE IF EXISTS sales_quote_lines');
        $db->execute('DROP TABLE IF EXISTS sales_quote_headers');
        parent::tearDown();
    }

    private function request(string $plugin, string $controller, string $action, ?string $id): ServerRequest
    {
        return (new ServerRequest())->withAttribute('params', [
            'plugin' => $plugin === '' ? null : $plugin, 'controller' => $controller, 'action' => $action,
            'pass' => $id === null ? [] : [$id],
        ]);
    }

    private function action(string $plugin, string $controller, string $action): array
    {
        return ['plugin' => $plugin, 'prefix' => '', 'controller' => $controller, 'action' => $action];
    }

    public function testOwnerOfAnAccountIsTheAccountItself(): void
    {
        $rule = new Owner();
        $me = ['id' => self::ME];

        $this->assertTrue($rule->allowed($me, 'editor', $this->request('CakeDC/Users', 'Users', 'edit', self::ME)));
        $this->assertFalse($rule->allowed($me, 'editor', $this->request('CakeDC/Users', 'Users', 'edit', self::OTHER)));
        $this->assertFalse($rule->allowed($me, 'editor', $this->request('CakeDC/Users', 'Users', 'edit', null)), 'No id: nothing to own');
        $this->assertFalse($rule->allowed([], 'editor', $this->request('CakeDC/Users', 'Users', 'edit', self::ME)));
    }

    public function testOwnerOfARecordIsItsCreator(): void
    {
        $rule = new Owner();

        $this->assertTrue($rule->allowed(['id' => self::ME], 'editor', $this->request('', 'SalesQuoteHeaders', 'edit', '1')));
        $this->assertFalse($rule->allowed(['id' => self::ME], 'editor', $this->request('', 'SalesQuoteHeaders', 'edit', '2')));
        $this->assertFalse($rule->allowed(['id' => self::ME], 'editor', $this->request('', 'SalesQuoteHeaders', 'edit', '999')), 'Missing record');
        $this->assertFalse($rule->allowed(['id' => self::ME], 'editor', $this->request('', 'SalesQuoteHeaders', 'edit', 'abc')), 'Invalid id, no database error');
    }

    public function testOwnerOfAChildRecordIsTheCreatorOfItsParent(): void
    {
        $rule = new Owner();

        $this->assertTrue($rule->allowed(['id' => self::ME], 'editor', $this->request('', 'SalesQuoteLines', 'edit', '10')));
        $this->assertFalse($rule->allowed(['id' => self::ME], 'editor', $this->request('', 'SalesQuoteLines', 'edit', '20')));
    }

    public function testOwnerIsOfferedOnlyWhereThereIsSomethingToOwn(): void
    {
        $catalog = RuleCatalog::fromConfig();

        $this->assertSame([RuleCatalog::OWNER], array_keys($catalog->applicable($this->action('CakeDC/Users', 'Users', 'edit'))));
        $this->assertSame([RuleCatalog::OWNER], array_keys($catalog->applicable($this->action('', 'UserRoles', 'edit'))));
        $this->assertSame([], $catalog->applicable($this->action('CakeDC/Users', 'Users', 'index')), 'A list has no record');
        $this->assertSame([], $catalog->applicable($this->action('', 'Dashboard', 'index')));
        $this->assertSame([RuleCatalog::OWNER], array_keys((new RuleCatalog([RuleCatalog::OWNER => new Owner(fn(): bool => true)]))->applicable($this->action('', 'SalesQuoteLines', 'edit'))));
    }

    public function testOwnerNeedsACreatorSomewhereUpTheParents(): void
    {
        $rule = new Owner();

        $this->assertSame(['created_by'], $rule->ownerPath(TableRegistry::getTableLocator()->get('SalesQuoteHeaders')));
        $this->assertSame(['SalesQuoteHeaders', 'created_by'], $rule->ownerPath(TableRegistry::getTableLocator()->get('SalesQuoteLines')));
        $this->assertNull($rule->ownerPath(TableRegistry::getTableLocator()->get('Roles')));
    }

    public function testValidChecksTheNameAndWhereItApplies(): void
    {
        $catalog = RuleCatalog::fromConfig();

        $this->assertTrue($catalog->isValid(RuleCatalog::OWNER, $this->action('CakeDC/Users', 'Users', 'edit')));
        $this->assertFalse($catalog->isValid('nope', $this->action('CakeDC/Users', 'Users', 'edit')));
        $this->assertFalse($catalog->isValid(RuleCatalog::OWNER, $this->action('', 'Dashboard', 'index')));
        $this->assertInstanceOf(Owner::class, $catalog->get(RuleCatalog::OWNER));
        $this->assertNull($catalog->get('itsMe'), 'Replaced by owner');
    }
}
