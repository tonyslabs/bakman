<?php

namespace Tests\Feature;

use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FileBrowserTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private StorageDisk $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/filebrowser-'.uniqid();
        File::ensureDirectoryExists($this->root.'/backups/vps/dvprod');
        file_put_contents($this->root.'/backups/vps/dvprod/dvprod_2026-09-26_210000.sql.gz', 'dump');
        file_put_contents($this->root.'/README.txt', 'hola');
        file_put_contents(dirname($this->root).'/fuera-'.basename($this->root).'.txt', 'secreto');
        symlink(dirname($this->root), $this->root.'/escape');

        $this->disk = StorageDisk::query()->firstOrFail();
        $this->disk->update(['path' => $this->root]);
    }

    protected function tearDown(): void
    {
        @unlink($this->root.'/escape');
        @unlink(dirname($this->root).'/fuera-'.basename($this->root).'.txt');
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('files.index'))->assertRedirect(route('login'));
        $this->get(route('files.show', $this->disk))->assertRedirect(route('login'));
    }

    public function test_lists_directories_first_and_files(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('files.show', $this->disk))
            ->assertOk()
            ->assertSeeInOrder(['backups/', 'README.txt']);
    }

    public function test_navigates_into_nested_directory(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('files.show', ['disk' => $this->disk, 'path' => 'backups/vps/dvprod']))
            ->assertOk()
            ->assertSee('dvprod_2026-09-26_210000.sql.gz');
    }

    public function test_downloads_file(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('files.download', ['disk' => $this->disk, 'path' => 'README.txt']))
            ->assertOk()
            ->assertDownload('README.txt');
    }

    public function test_rejects_path_traversal(): void
    {
        $user = User::factory()->create();
        $outside = '../fuera-'.basename($this->root).'.txt';

        $this->actingAs($user)->get(route('files.show', ['disk' => $this->disk, 'path' => '..']))->assertNotFound();
        $this->actingAs($user)->get(route('files.download', ['disk' => $this->disk, 'path' => $outside]))->assertNotFound();
        $this->actingAs($user)->get(route('files.download', ['disk' => $this->disk, 'path' => 'escape/fuera-'.basename($this->root).'.txt']))->assertNotFound();
    }

    public function test_index_redirects_to_first_disk_or_to_the_create_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('files.index'))->assertRedirect(route('files.show', $this->disk));

        $this->disk->delete();
        $this->actingAs($user)->get(route('files.index'))->assertRedirect(route('storage-disks.create'));
    }

    public function test_each_disk_browses_its_own_root(): void
    {
        $other = sys_get_temp_dir().'/filebrowser-otro-'.uniqid();
        File::ensureDirectoryExists($other);
        file_put_contents($other.'/solo-en-el-otro.txt', 'x');
        $second = StorageDisk::create(['name' => 'Segundo', 'path' => $other]);

        $this->actingAs(User::factory()->create())
            ->get(route('files.show', $second))->assertOk()->assertSee('solo-en-el-otro.txt')->assertDontSee('README.txt');

        File::deleteDirectory($other);
    }
}
