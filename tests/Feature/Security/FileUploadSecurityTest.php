<?php

namespace Tests\Feature\Security;

use App\Models\Announcement;
use App\Models\Church;
use App\Models\File;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Upload hardening regression tests.
 *
 * The dangerous case these guard against: `mimetypes:` validates file *content*
 * via finfo, but says nothing about the *filename*. A polyglot file (valid image
 * magic bytes followed by PHP source) passes content validation while keeping an
 * executable extension. If that lands on the public disk, Nginx's
 * `location ~ \.php$` block hands it to PHP-FPM — remote code execution.
 */
class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    private function bootTenant(string $role = 'church_admin'): array
    {
        $church = Church::create(['name' => 'Test Church ' . uniqid(), 'is_active' => true]);
        $user   = User::factory()->create(['church_id' => $church->id]);
        $user->assignRole($role);

        app()->instance('church', $church);
        app()->instance('church.id', $church->id);
        $this->actingAs($user);

        $announcement = Announcement::create([
            'church_id'  => $church->id,
            'created_by' => $user->id,
            'title'      => 'Sunday Service',
            'body'       => 'Body text',
        ]);

        return [$church, $user, $announcement];
    }

    /** Raw bytes of a minimal but genuinely valid 1x1 GIF. finfo reports image/gif. */
    private const GIF_BYTES = "GIF89a\x01\x00\x01\x00\x00\xff\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x00;";

    /** A GIF-header polyglot carrying PHP source, under a caller-chosen filename. */
    private function polyglot(string $filename): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'poly');
        file_put_contents($path, self::GIF_BYTES . '<?php echo "pwned"; ?>');

        return new UploadedFile($path, $filename, 'image/gif', null, true);
    }

    /** A clean image upload. Built from raw bytes so the suite does not require ext-gd. */
    private function cleanImage(string $filename = 'photo.gif'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, self::GIF_BYTES);

        return new UploadedFile($path, $filename, 'image/gif', null, true);
    }

    public function test_executable_extension_is_never_persisted_on_upload(): void
    {
        [, , $announcement] = $this->bootTenant();

        $response = $this->postJson('/dashboard/files', [
            'file'            => $this->polyglot('payload.php'),
            'attachable_type' => 'announcement',
            'attachable_id'   => $announcement->id,
            'is_public'       => true,
        ]);

        // Either the request is rejected outright, or it is accepted but stored
        // under a neutralised name. What must NEVER happen is a stored .php path.
        if ($response->status() === 201) {
            $file = File::latest('id')->first();
            $this->assertNotNull($file);
            $this->assertStringEndsNotWith('.php', $file->path, 'A .php file was persisted to disk.');
            $this->assertStringEndsNotWith('.php', $file->name);
        } else {
            $response->assertStatus(422);
        }

        // Nothing ending in .php may exist on either disk.
        foreach (['public', 'local'] as $disk) {
            foreach (Storage::disk($disk)->allFiles() as $stored) {
                $this->assertStringEndsNotWith('.php', $stored, "Executable file written to [$disk] disk: $stored");
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dangerousExtensionProvider')]
    public function test_dangerous_extensions_are_blocked(string $filename): void
    {
        [, , $announcement] = $this->bootTenant();

        $response = $this->postJson('/dashboard/files', [
            'file'            => $this->polyglot($filename),
            'attachable_type' => 'announcement',
            'attachable_id'   => $announcement->id,
            'is_public'       => true,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, File::count(), "Upload of [$filename] should not have created a record.");
    }

    /**
     * The first four are additionally caught by Laravel's own
     * ValidatesAttributes::shouldBlockPhpUpload(). The rest are NOT in that
     * framework blocklist and were accepted before the explicit extension
     * whitelist was added — they are the reason it exists.
     */
    public static function dangerousExtensionProvider(): array
    {
        return [
            'php'      => ['shell.php'],
            'phtml'    => ['shell.phtml'],
            'php8'     => ['shell.php8'],
            'phar'     => ['shell.phar'],
            'phps'     => ['shell.phps'],
            'pht'      => ['shell.pht'],
            'shtml'    => ['shell.shtml'],
            'htaccess' => ['config.htaccess'],
            'html'     => ['page.html'],
            'no-ext'   => ['payload'],
        ];
    }

    public function test_svg_upload_is_rejected_to_prevent_stored_xss(): void
    {
        [, , $announcement] = $this->bootTenant();

        $path = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>');

        $response = $this->postJson('/dashboard/files', [
            'file'            => new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true),
            'attachable_type' => 'announcement',
            'attachable_id'   => $announcement->id,
            'is_public'       => true,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, File::count());
    }

    public function test_legitimate_image_upload_still_succeeds(): void
    {
        [, , $announcement] = $this->bootTenant();

        $this->postJson('/dashboard/files', [
            'file'            => $this->cleanImage(),
            'attachable_type' => 'announcement',
            'attachable_id'   => $announcement->id,
            'is_public'       => true,
        ])->assertStatus(201);

        $this->assertSame(1, File::count());
        $this->assertStringEndsWith('.gif', File::first()->path);
    }

    public function test_cross_tenant_upload_is_forbidden(): void
    {
        [, , $announcement] = $this->bootTenant();

        // A second church with its own announcement.
        $other = Church::create(['name' => 'Other Church', 'is_active' => true]);
        $otherAdmin = User::factory()->create(['church_id' => $other->id]);
        $otherAdmin->assignRole('church_admin');
        $foreign = Announcement::withoutGlobalScope('church')->create([
            'church_id'  => $other->id,
            'created_by' => $otherAdmin->id,
            'title'      => 'Foreign',
            'body'       => 'Body',
        ]);

        // Still acting as the FIRST church's admin, try to attach to the other church's record.
        $this->postJson('/dashboard/files', [
            'file'            => $this->cleanImage(),
            'attachable_type' => 'announcement',
            'attachable_id'   => $foreign->id,
            'is_public'       => false,
        ])->assertStatus(422); // scoped-out record resolves to null → 422

        $this->assertSame(0, File::count());
    }
}
