<?php

namespace Tests\Feature;

use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageDiskTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_migration_seeds_the_default_disk_as_disco_externo(): void
    {
        $this->assertSame(['Disco externo'], StorageDisk::pluck('name')->all());
    }

    public function test_creates_disk_and_shows_it_in_the_sidebar(): void
    {
        $dir = sys_get_temp_dir().'/disk-'.uniqid();
        mkdir($dir);

        $this->actingAs($this->user())
            ->post(route('storage-disks.store'), ['name' => 'Backups NAS', 'path' => $dir.'/'])
            ->assertRedirect();

        $disk = StorageDisk::where('name', 'Backups NAS')->firstOrFail();
        $this->assertSame($dir, $disk->path, 'quita la barra final');
        $this->actingAs($this->user())->get(route('files.show', $disk))->assertOk()->assertSee('Backups NAS');

        rmdir($dir);
    }

    public function test_accepts_a_path_that_is_not_mounted_yet(): void
    {
        $this->actingAs($this->user())
            ->post(route('storage-disks.store'), ['name' => 'Futuro', 'path' => '/mnt/no-existe-'.uniqid()])
            ->assertRedirect();

        $this->assertDatabaseHas('storage_disks', ['name' => 'Futuro']);
    }

    public function test_rejects_relative_root_app_and_ssh_paths(): void
    {
        $user = $this->actingAs($this->user());
        $user->post(route('storage-disks.store'), ['name' => 'x', 'path' => 'relativa'])->assertSessionHasErrors('path');
        $user->post(route('storage-disks.store'), ['name' => 'x', 'path' => '/'])->assertSessionHasErrors('path');
        $user->post(route('storage-disks.store'), ['name' => 'x', 'path' => base_path()])->assertSessionHasErrors('path');
        $user->post(route('storage-disks.store'), ['name' => 'x', 'path' => base_path('storage')])->assertSessionHasErrors('path');
    }

    public function test_a_stored_forbidden_path_is_never_browsed(): void
    {
        $disk = StorageDisk::create(['name' => 'Malo', 'path' => base_path()]);

        $this->actingAs($this->user())->get(route('files.show', $disk))->assertStatus(503);
    }

    public function test_updates_and_removes_disk_without_touching_files(): void
    {
        $dir = sys_get_temp_dir().'/disk-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/a.txt', 'a');
        $disk = StorageDisk::create(['name' => 'Viejo', 'path' => $dir]);
        $user = $this->actingAs($this->user());

        $user->put(route('storage-disks.update', $disk), ['name' => 'Nuevo', 'path' => $dir])->assertRedirect();
        $this->assertSame('Nuevo', $disk->fresh()->name);

        $user->delete(route('storage-disks.destroy', $disk))->assertRedirect();
        $this->assertModelMissing($disk);
        $this->assertFileExists($dir.'/a.txt');

        unlink($dir.'/a.txt');
        rmdir($dir);
    }
}
