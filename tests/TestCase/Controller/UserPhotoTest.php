<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Account\ActivityLog;
use App\Storage\Uploads;
use App\Test\TestCase\PermissionsTestTrait;
use App\Test\TestCase\UserFactoryTrait;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use CakeDC\Users\Model\Entity\User;
use FilesystemIterator;
use Laminas\Diactoros\UploadedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * User photo (josegonzalez/cakephp-upload): stored privately as `users/<id>/photo/<uuid>.webp`,
 * re-encoded by GD to a 256×256 WebP (no EXIF, no smuggled payloads), validated by its real content.
 * Own photo from the profile; admins change other users' (never a superadmin's, unless superadmin).
 * Served only to signed-in users.
 */
class UserPhotoTest extends TestCase
{
    use IntegrationTestTrait;
    use PermissionsTestTrait;
    use UserFactoryTrait;

    protected array $fixtures = [
        'app.Users', 'app.Roles', 'app.Permissions', 'app.RolesPermissions', 'app.AccountActivities',
    ];

    private string $root;

    /**
     * @var list<string>
     */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = TMP . 'tests' . DS . 'uploads-' . bin2hex(random_bytes(4)) . DS;
        Configure::write('Uploads.path', $this->root);
        $this->syncPermissions();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->root)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    /**
     * A real image file of the given type and size, as the browser would upload it.
     */
    private function upload(string $type = 'jpeg', int $width = 600, int $height = 400): UploadedFile
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'photo');
        $this->tmpFiles[] = $path;
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int)imagecolorallocate($image, 200, 30, 30));
        match ($type) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'gif' => imagegif($image, $path),
        };

        return new UploadedFile($path, (int)filesize($path), UPLOAD_ERR_OK, 'Mi foto DNI.' . $type, 'image/' . $type);
    }

    private function fileUpload(string $contents, string $clientName, string $mime): UploadedFile
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'photo');
        $this->tmpFiles[] = $path;
        file_put_contents($path, $contents);

        return new UploadedFile($path, strlen($contents), UPLOAD_ERR_OK, $clientName, $mime);
    }

    private function fresh(User $user): User
    {
        /** @var \CakeDC\Users\Model\Entity\User $fresh */
        $fresh = $this->fetchTable('Users')->get($user->id);

        return $fresh;
    }

    private function storedFile(User $user): string
    {
        $fresh = $this->fresh($user);

        return $this->root . $fresh->get('photo_dir') . $fresh->get('photo');
    }

    public function testOwnPhotoIsStoredAsAPrivateSquareWebpNamedByConvention(): void
    {
        $user = $this->createUser();
        $this->signIn($user);

        $this->post('/user-photo/update', ['photo' => $this->upload()]);

        $this->assertRedirectContains('/profile');
        $fresh = $this->fresh($user);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.webp$/', (string)$fresh->get('photo'), 'Random name, never the client one');
        $this->assertSame('users' . DS . $user->id . DS . 'photo' . DS, $fresh->get('photo_dir'), '<table>/<id>/<field>/');
        $file = $this->storedFile($user);
        $this->assertFileExists($file);
        $this->assertStringStartsNotWith(WWW_ROOT, $file, 'Outside webroot');
        $this->assertSame('image/webp', mime_content_type($file), 'Re-encoded');
        [$width, $height] = (array)getimagesize($file);
        $this->assertSame([256, 256], [$width, $height]);
        $this->assertSame(
            [ActivityLog::PHOTO_CHANGED],
            $this->fetchTable('AccountActivities')->find()->where(['user_id' => $user->id])->all()->extract('event')->toList(),
        );
    }

    public function testOnlyRealJpegPngOrWebpUpTo2MbAreAccepted(): void
    {
        $user = $this->createUser();
        $this->signIn($user);

        $this->post('/user-photo/update', ['photo' => $this->fileUpload('<?php echo "hi";', 'shell.jpg', 'image/jpeg')]);
        $this->assertFlashMessage('The photo must be a JPEG, PNG or WebP image.');
        $this->post('/user-photo/update', ['photo' => $this->upload('gif')]);
        $this->assertFlashMessage('The photo must be a JPEG, PNG or WebP image.');
        $big = $this->fileUpload(str_repeat('x', 2 * 1024 * 1024 + 1), 'big.jpg', 'image/jpeg');
        $this->post('/user-photo/update', ['photo' => $big]);
        $this->assertFlashMessage('The photo cannot be larger than 2 MB.');

        $this->assertNull($this->fresh($user)->get('photo'));
        $this->assertDirectoryDoesNotExist($this->root . 'users');

        $this->post('/user-photo/update', ['photo' => $this->upload('png', 300, 900)]);
        $this->assertFlashMessage('Photo updated.');
    }

    public function testReplacingDeletesThePreviousFileAndRemovingClearsIt(): void
    {
        $user = $this->createUser();
        $this->signIn($user);
        $this->post('/user-photo/update', ['photo' => $this->upload()]);
        $first = $this->storedFile($user);

        $this->post('/user-photo/update', ['photo' => $this->upload('png')]);
        $second = $this->storedFile($user);
        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist($first, 'Old photo deleted');
        $this->assertFileExists($second);

        $this->post('/user-photo/delete');
        $this->assertFileDoesNotExist($second);
        $this->assertNull($this->fresh($user)->get('photo'));
        $this->assertNull($this->fresh($user)->get('photo_dir'));
    }

    public function testAdminsChangeOtherUsersPhotosButNotASuperadminsAndUsersOnlyTheirOwn(): void
    {
        $target = $this->createUser();
        $super = $this->createUser(['role' => 'superadmin', 'is_superuser' => true]);
        $admin = $this->createUser(['role' => 'admin']);

        $this->signIn($admin);
        $this->post('/user-photo/update/' . $target->id, ['photo' => $this->upload()]);
        $this->assertRedirectContains('/users/edit/' . $target->id);
        $this->assertNotNull($this->fresh($target)->get('photo'));
        $activity = $this->fetchTable('AccountActivities')->find()->where(['user_id' => $target->id])->firstOrFail();
        $this->assertSame((string)$admin->id, $activity->get('actor_id'), 'Who changed it');

        $this->post('/user-photo/update/' . $super->id, ['photo' => $this->upload()]);
        $this->assertNull($this->fresh($super)->get('photo'), 'An admin never touches a superadmin');

        $this->signIn($this->createUser());
        $this->post('/user-photo/delete/' . $target->id);
        $this->assertNotNull($this->fresh($target)->get('photo'), 'A user only changes their own');
    }

    public function testPhotosAreServedOnlyToSignedInUsers(): void
    {
        $user = $this->createUser();
        $this->signIn($user);
        $this->post('/user-photo/update', ['photo' => $this->upload()]);

        $this->get('/user-photo/show/' . $user->id);
        $this->assertResponseOk();
        $this->assertContentType('image/webp');
        $this->assertHeaderContains('Cache-Control', 'private');
        $this->assertSame((string)file_get_contents($this->storedFile($user)), (string)$this->_response->getBody());

        $this->get('/profile');
        $this->assertResponseContains('/user-photo/show/' . $user->id . '?v=');

        $this->signIn($this->createUser());
        $this->get('/user-photo/show/' . $this->createUser()->id);
        $this->assertResponseCode(404, 'No photo');

        $this->_session = [];
        $this->get('/user-photo/show/' . $user->id);
        $this->assertRedirectContains('/login');
    }

    public function testDeletingTheUserDeletesItsFiles(): void
    {
        $user = $this->createUser();
        $this->signIn($user);
        $this->post('/user-photo/update', ['photo' => $this->upload()]);
        $file = $this->storedFile($user);

        $this->fetchTable('Users')->deleteOrFail($this->fresh($user));

        $this->assertFileDoesNotExist($file);
        $this->assertDirectoryDoesNotExist($this->root . 'users' . DS . $user->id, 'Whole record folder gone');
    }

    public function testTheNamingConventionIsGenericForAnyTableAndField(): void
    {
        $config = Uploads::imageField('logo');

        $this->assertSame('{table}{DS}{primaryKey}{DS}{field}{DS}', $config['logo']['path']);
        $this->assertFalse($config['logo']['keepFilesOnDelete']);
        $this->assertSame(Uploads::root(), $config['logo']['filesystem']['root']);
    }
}
