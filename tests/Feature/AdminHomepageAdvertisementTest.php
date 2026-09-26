<?php

namespace Tests\Feature;

use App\Http\Requests\StoreHomepageAdvertisementRequest;
use App\Models\CompanyProfile;
use App\Models\HomepageAdvertisement;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdminHomepageAdvertisementTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryUploads = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryUploads as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_only_an_exact_administrator_can_open_the_management_page(): void
    {
        $admin = $this->user('admin', 'advertisements-admin@example.com');
        $advertisement = $this->advertisement(
            'homepage-advertisements/existing.png',
            'إعلان قائم',
        );
        Storage::disk('public')->put($advertisement->image_path, 'image bytes');

        $this->actingAs($admin)
            ->get(route('admin.advertisements.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Advertisements')
                ->where('advertisements', [[
                    'id' => $advertisement->id,
                    'image_url' => Storage::disk('public')->url($advertisement->image_path),
                    'alt_text' => 'إعلان قائم',
                    'created_at' => $advertisement->created_at?->toISOString(),
                ]]));
    }

    public function test_a_guest_cannot_open_upload_to_or_delete_from_advertisement_management(): void
    {
        $advertisement = $this->advertisement('homepage-advertisements/guest-protected.png');
        Storage::disk('public')->put($advertisement->image_path, 'protected image');

        $this->get(route('admin.advertisements.index', absolute: false))
            ->assertRedirect(route('login', absolute: false));
        $this->post(route('admin.advertisements.store', absolute: false), [
            'image' => UploadedFile::fake()->create('blocked.png', 10, 'image/png'),
        ])->assertRedirect(route('login', absolute: false));
        $this->delete(route('admin.advertisements.destroy', $advertisement, absolute: false))
            ->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseCount('homepage_advertisements', 1);
        $this->assertDatabaseHas('homepage_advertisements', ['id' => $advertisement->id]);
        Storage::disk('public')->assertExists($advertisement->image_path);
        $this->assertSame([$advertisement->image_path], Storage::disk('public')->allFiles());
    }

    /** @return array<string, array{string, string|null}> */
    public static function nonAdministratorCases(): array
    {
        return [
            'job seeker' => ['job_seeker', null],
            'employer' => ['employer', null],
            'training company' => ['training_provider', 'company'],
            'individual trainer' => ['training_provider', 'trainer'],
            'near-miss administrator role' => ['administrator', null],
        ];
    }

    #[DataProvider('nonAdministratorCases')]
    public function test_every_non_administrator_role_is_blocked_from_all_management_endpoints(
        string $role,
        ?string $providerType,
    ): void {
        $actor = $this->user($role, str_replace('_', '-', $role).'-'.($providerType ?? 'user').'@example.com');

        if ($providerType !== null) {
            $this->provider($actor, $providerType);
        }

        $advertisement = $this->advertisement('homepage-advertisements/role-protected.png');
        Storage::disk('public')->put($advertisement->image_path, 'protected image');

        $this->actingAs($actor)
            ->get(route('admin.advertisements.index', absolute: false))
            ->assertForbidden();
        $this->actingAs($actor)
            ->post(route('admin.advertisements.store', absolute: false), [
                'image' => UploadedFile::fake()->create('blocked.png', 10, 'image/png'),
            ])
            ->assertForbidden();
        $this->actingAs($actor)
            ->delete(route('admin.advertisements.destroy', $advertisement, absolute: false))
            ->assertForbidden();

        $this->assertDatabaseCount('homepage_advertisements', 1);
        $this->assertDatabaseHas('homepage_advertisements', ['id' => $advertisement->id]);
        Storage::disk('public')->assertExists($advertisement->image_path);
        $this->assertSame([$advertisement->image_path], Storage::disk('public')->allFiles());
    }

    /** @return array<string, array{string}> */
    public static function validImageCases(): array
    {
        return [
            'JPG' => ['jpg'],
            'PNG' => ['png'],
            'WebP' => ['webp'],
        ];
    }

    #[DataProvider('validImageCases')]
    public function test_an_administrator_can_upload_each_supported_image_format(string $extension): void
    {
        $function = $extension === 'jpg' ? 'imagejpeg' : 'image'.$extension;

        if ($extension === 'webp' && (! function_exists('imagecreatetruecolor') || ! function_exists($function))) {
            $this->markTestSkipped("GD {$extension} support is not available in this test environment.");
        }

        $admin = $this->user('admin', "valid-{$extension}-admin@example.com");

        $response = $this->actingAs($admin)->post(
            route('admin.advertisements.store', absolute: false),
            [
                'image' => UploadedFile::fake()->image("campaign.{$extension}", 640, 480),
                'alt_text' => "وصف {$extension}",
            ],
        );

        $response
            ->assertRedirect(route('admin.advertisements.index', absolute: false))
            ->assertSessionHas('success', 'تمت إضافة الصورة الإعلانية بنجاح.')
            ->assertSessionMissing('error');

        $advertisement = HomepageAdvertisement::query()->sole();

        $this->assertSame("وصف {$extension}", $advertisement->alt_text);
        $this->assertTrue(HomepageAdvertisement::isManagedImagePath($advertisement->image_path));
        $this->assertSame($extension, strtolower(pathinfo($advertisement->image_path, PATHINFO_EXTENSION)));
        Storage::disk('public')->assertExists($advertisement->image_path);
        $this->assertDatabaseHas('homepage_advertisements', [
            'id' => $advertisement->id,
            'image_path' => $advertisement->image_path,
            'alt_text' => "وصف {$extension}",
        ]);
    }

    /** @return array<string, array{string, string}> */
    public static function rejectedFileCases(): array
    {
        return [
            'GIF image' => ['campaign.gif', 'image/gif'],
            'SVG image' => ['campaign.svg', 'image/svg+xml'],
            'HTML document' => ['campaign.html', 'text/html'],
            'PDF document' => ['campaign.pdf', 'application/pdf'],
            'PHP script' => ['campaign.php', 'image/jpeg'],
            'plain text' => ['campaign.txt', 'text/plain'],
        ];
    }

    #[DataProvider('rejectedFileCases')]
    public function test_disallowed_extensions_and_file_types_are_rejected_without_side_effects(
        string $name,
        string $mimeType,
    ): void {
        $admin = $this->user('admin', str_replace('.', '-', $name).'@example.com');

        $this->actingAs($admin)->post(
            route('admin.advertisements.store', absolute: false),
            ['image' => UploadedFile::fake()->create($name, 10, $mimeType)],
        )
            ->assertSessionHasErrors([
                'image' => StoreHomepageAdvertisementRequest::INVALID_IMAGE_MESSAGE,
            ])
            ->assertSessionHas('error', StoreHomepageAdvertisementRequest::INVALID_IMAGE_MESSAGE);

        $this->assertDatabaseCount('homepage_advertisements', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_non_image_renamed_with_an_allowed_extension_is_rejected_by_real_content_detection(): void
    {
        $admin = $this->user('admin', 'renamed-file-admin@example.com');
        $upload = $this->realUpload(
            'renamed.jpg',
            '<!doctype html><script>alert("not an image")</script>',
            'image/jpeg',
        );

        $this->actingAs($admin)->post(
            route('admin.advertisements.store', absolute: false),
            ['image' => $upload],
        )
            ->assertSessionHasErrors([
                'image' => StoreHomepageAdvertisementRequest::INVALID_IMAGE_MESSAGE,
            ])
            ->assertSessionHas('error', StoreHomepageAdvertisementRequest::INVALID_IMAGE_MESSAGE);

        $this->assertDatabaseCount('homepage_advertisements', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_oversized_image_is_rejected_without_storage_or_database_changes(): void
    {
        $admin = $this->user('admin', 'oversized-image-admin@example.com');

        $this->actingAs($admin)->post(
            route('admin.advertisements.store', absolute: false),
            ['image' => UploadedFile::fake()->image('oversized.png', 640, 480)->size(5121)],
        )
            ->assertSessionHasErrors([
                'image' => StoreHomepageAdvertisementRequest::INVALID_IMAGE_MESSAGE,
            ])
            ->assertSessionHas('error', StoreHomepageAdvertisementRequest::INVALID_IMAGE_MESSAGE);

        $this->assertDatabaseCount('homepage_advertisements', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_overlong_alternative_description_is_rejected_before_the_file_is_stored(): void
    {
        $admin = $this->user('admin', 'long-alt-admin@example.com');

        $this->actingAs($admin)->post(
            route('admin.advertisements.store', absolute: false),
            [
                'image' => UploadedFile::fake()->image('campaign.png', 640, 480),
                'alt_text' => str_repeat('أ', 161),
            ],
        )
            ->assertSessionHasErrors([
                'alt_text' => 'يجب ألا يتجاوز الوصف البديل للصورة 160 حرفًا.',
            ])
            ->assertSessionHas('error', 'يجب ألا يتجاوز الوصف البديل للصورة 160 حرفًا.');

        $this->assertDatabaseCount('homepage_advertisements', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_original_filenames_and_crafted_paths_are_ignored_and_generated_paths_are_unique(): void
    {
        $admin = $this->user('admin', 'safe-filename-admin@example.com');
        $outsidePath = 'outside/protected.png';
        Storage::disk('public')->put($outsidePath, 'must remain');

        foreach ([1, 2] as $number) {
            $this->actingAs($admin)->post(
                route('admin.advertisements.store', absolute: false),
                [
                    'image' => UploadedFile::fake()->image('../../same-name.png'),
                    'image_path' => $outsidePath,
                    'alt_text' => "إعلان {$number}",
                ],
            )->assertSessionHas('success', 'تمت إضافة الصورة الإعلانية بنجاح.');
        }

        $paths = HomepageAdvertisement::query()->orderBy('id')->pluck('image_path')->all();

        $this->assertCount(2, $paths);
        $this->assertNotSame($paths[0], $paths[1]);
        foreach ($paths as $path) {
            $this->assertTrue(HomepageAdvertisement::isManagedImagePath($path));
            $this->assertStringStartsWith('homepage-advertisements/', $path);
            $this->assertStringNotContainsString('..', $path);
            $this->assertStringNotContainsString('\\', $path);
            $this->assertStringNotContainsString('same-name', $path);
            Storage::disk('public')->assertExists($path);
        }
        Storage::disk('public')->assertExists($outsidePath);
        $this->assertDatabaseMissing('homepage_advertisements', ['image_path' => $outsidePath]);
    }

    public function test_a_database_creation_failure_removes_the_newly_stored_orphan(): void
    {
        $admin = $this->user('admin', 'orphan-cleanup-admin@example.com');
        $event = 'eloquent.creating: '.HomepageAdvertisement::class;

        Event::listen($event, function (HomepageAdvertisement $advertisement): void {
            throw new RuntimeException('Forced advertisement creation failure.');
        });

        try {
            $response = $this->actingAs($admin)->post(
                route('admin.advertisements.store', absolute: false),
                ['image' => UploadedFile::fake()->image('orphan.png')],
            );
        } finally {
            Event::forget($event);
        }

        $response
            ->assertRedirect(route('admin.advertisements.index', absolute: false))
            ->assertSessionHas('error', StoreHomepageAdvertisementRequest::INVALID_IMAGE_MESSAGE)
            ->assertSessionMissing('success');
        $this->assertDatabaseCount('homepage_advertisements', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_administrator_deletes_the_record_and_owned_file_but_not_unrelated_files(): void
    {
        $admin = $this->user('admin', 'delete-ad-admin@example.com');
        $advertisement = $this->advertisement('homepage-advertisements/delete-me.png');
        $unrelatedPath = 'homepage-advertisements/keep-me.png';
        $outsidePath = 'company-logos/keep-me.png';
        Storage::disk('public')->put($advertisement->image_path, 'owned');
        Storage::disk('public')->put($unrelatedPath, 'unrelated ad file');
        Storage::disk('public')->put($outsidePath, 'unrelated feature file');

        $this->actingAs($admin)
            ->delete(route('admin.advertisements.destroy', $advertisement, absolute: false))
            ->assertRedirect(route('admin.advertisements.index', absolute: false))
            ->assertSessionHas('success', 'تم حذف الصورة الإعلانية بنجاح.')
            ->assertSessionMissing('error');

        $this->assertDatabaseMissing('homepage_advertisements', ['id' => $advertisement->id]);
        Storage::disk('public')->assertMissing($advertisement->image_path);
        Storage::disk('public')->assertExists($unrelatedPath);
        Storage::disk('public')->assertExists($outsidePath);
    }

    public function test_a_file_shared_by_another_advertisement_is_deleted_only_after_the_last_reference(): void
    {
        $admin = $this->user('admin', 'shared-ad-admin@example.com');
        $sharedPath = 'homepage-advertisements/shared.png';
        $first = $this->advertisement($sharedPath, 'الأول');
        $second = $this->advertisement($sharedPath, 'الثاني');
        Storage::disk('public')->put($sharedPath, 'shared image');

        $this->actingAs($admin)
            ->delete(route('admin.advertisements.destroy', $first, absolute: false))
            ->assertSessionHas('success', 'تم حذف الصورة الإعلانية بنجاح.');

        $this->assertDatabaseMissing('homepage_advertisements', ['id' => $first->id]);
        $this->assertDatabaseHas('homepage_advertisements', ['id' => $second->id, 'image_path' => $sharedPath]);
        Storage::disk('public')->assertExists($sharedPath);

        $this->actingAs($admin)
            ->delete(route('admin.advertisements.destroy', $second, absolute: false))
            ->assertSessionHas('success', 'تم حذف الصورة الإعلانية بنجاح.');

        $this->assertDatabaseMissing('homepage_advertisements', ['id' => $second->id]);
        Storage::disk('public')->assertMissing($sharedPath);
    }

    public function test_files_shared_with_other_public_file_owners_are_preserved(): void
    {
        $admin = $this->user('admin', 'cross-feature-files-admin@example.com');
        $paths = [
            'company' => 'homepage-advertisements/shared-company.png',
            'provider_logo' => 'homepage-advertisements/shared-provider-logo.png',
            'provider_profile' => 'homepage-advertisements/shared-provider-profile.png',
            'course' => 'homepage-advertisements/shared-course.png',
            'cv' => 'homepage-advertisements/shared-cv.png',
        ];
        $advertisements = collect($paths)
            ->map(fn (string $path) => $this->advertisement($path));

        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'shared bytes');
        }

        $employer = $this->user('employer', 'shared-file-employer@example.com');
        CompanyProfile::create([
            'user_id' => $employer->id,
            'company_name' => 'شركة الملفات المشتركة',
            'logo_path' => $paths['company'],
        ]);

        $providerUser = $this->user('training_provider', 'shared-file-provider@example.com');
        $provider = $this->provider($providerUser, 'company', [
            'logo_path' => $paths['provider_logo'],
            'profile_image_path' => $paths['provider_profile'],
        ]);
        TrainingCourse::create([
            'training_provider_id' => $provider->id,
            'title' => 'دورة الملفات المشتركة',
            'slug' => 'shared-advertisement-file-course',
            'description' => 'وصف الدورة',
            'difficulty_level' => 'beginner',
            'delivery_method' => 'online',
            'cover_image_path' => $paths['course'],
        ]);

        $seeker = $this->user('job_seeker', 'shared-file-seeker@example.com');
        JobSeekerProfile::create([
            'user_id' => $seeker->id,
            'cv_path' => $paths['cv'],
        ]);

        foreach ($advertisements as $advertisement) {
            $this->actingAs($admin)
                ->delete(route('admin.advertisements.destroy', $advertisement, absolute: false))
                ->assertSessionHas('success', 'تم حذف الصورة الإعلانية بنجاح.');
        }

        $this->assertDatabaseCount('homepage_advertisements', 0);
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_a_missing_stored_file_does_not_prevent_database_deletion(): void
    {
        $admin = $this->user('admin', 'missing-file-admin@example.com');
        $advertisement = $this->advertisement('homepage-advertisements/already-missing.png');

        $this->actingAs($admin)
            ->delete(route('admin.advertisements.destroy', $advertisement, absolute: false))
            ->assertRedirect(route('admin.advertisements.index', absolute: false))
            ->assertSessionHas('success', 'تم حذف الصورة الإعلانية بنجاح.')
            ->assertSessionMissing('error');

        $this->assertDatabaseMissing('homepage_advertisements', ['id' => $advertisement->id]);
    }

    /** @return array<string, array{string, string}> */
    public static function unsafeStoredPathCases(): array
    {
        return [
            'parent traversal' => ['homepage-advertisements/../outside.png', 'outside.png'],
            'leading traversal' => ['../outside.png', 'outside.png'],
            'absolute slash' => ['/outside.png', 'outside.png'],
            'Windows traversal' => ['homepage-advertisements\\..\\outside.png', 'outside.png'],
            'prefix lookalike' => ['homepage-advertisements-evil/outside.png', 'homepage-advertisements-evil/outside.png'],
            'nested directory' => ['homepage-advertisements/nested/outside.png', 'homepage-advertisements/nested/outside.png'],
            'dot segment' => ['homepage-advertisements/./outside.png', 'homepage-advertisements/outside.png'],
            'double slash' => ['homepage-advertisements//outside.png', 'homepage-advertisements/outside.png'],
            'surrounding whitespace' => [' homepage-advertisements/outside.png', ' homepage-advertisements/outside.png'],
            'null byte' => ["homepage-advertisements/outside.png\0ignored.png", 'homepage-advertisements/outside.png'],
            'unsupported extension' => ['homepage-advertisements/outside.svg', 'homepage-advertisements/outside.svg'],
        ];
    }

    #[DataProvider('unsafeStoredPathCases')]
    public function test_unsafe_or_unrelated_stored_paths_are_never_deleted(
        string $unsafePath,
        string $protectedPath,
    ): void {
        $admin = $this->user('admin', 'unsafe-path-'.md5($unsafePath).'@example.com');
        $advertisement = $this->advertisement($unsafePath);
        Storage::disk('public')->put($protectedPath, 'must remain');

        $this->actingAs($admin)
            ->delete(route('admin.advertisements.destroy', $advertisement, absolute: false))
            ->assertRedirect(route('admin.advertisements.index', absolute: false))
            ->assertSessionHas('error', 'تم حذف الإعلان، ولكن تعذر تنظيف ملف الصورة من التخزين.')
            ->assertSessionMissing('success');

        $this->assertDatabaseMissing('homepage_advertisements', ['id' => $advertisement->id]);
        Storage::disk('public')->assertExists($protectedPath);
    }

    public function test_a_database_deletion_failure_preserves_both_the_record_and_file(): void
    {
        $admin = $this->user('admin', 'delete-rollback-admin@example.com');
        $advertisement = $this->advertisement('homepage-advertisements/rollback.png');
        $fakeDisk = Storage::disk('public');
        $fakeDisk->put($advertisement->image_path, 'must remain');
        $event = 'eloquent.deleting: '.HomepageAdvertisement::class;

        Event::listen($event, function (HomepageAdvertisement $deletingAdvertisement) use ($advertisement): void {
            if ($deletingAdvertisement->is($advertisement)) {
                throw new RuntimeException('Forced advertisement deletion failure.');
            }
        });

        try {
            $response = $this->actingAs($admin)
                ->delete(route('admin.advertisements.destroy', $advertisement, absolute: false));
        } finally {
            Event::forget($event);
        }

        $response
            ->assertRedirect(route('admin.advertisements.index', absolute: false))
            ->assertSessionHas('error', 'تعذر حذف الصورة الإعلانية. يرجى المحاولة مرة أخرى.')
            ->assertSessionMissing('success');
        $this->assertDatabaseHas('homepage_advertisements', ['id' => $advertisement->id]);
        $fakeDisk->assertExists($advertisement->image_path);
    }

    public function test_a_storage_cleanup_failure_is_logged_safely_after_the_record_is_deleted(): void
    {
        $admin = $this->user('admin', 'cleanup-failure-admin@example.com');
        $advertisement = $this->advertisement('homepage-advertisements/cleanup-failure.png');
        $disk = Mockery::mock(Filesystem::class);

        $disk->shouldReceive('exists')
            ->twice()
            ->with($advertisement->image_path)
            ->andReturnTrue();
        $disk->shouldReceive('delete')
            ->once()
            ->with($advertisement->image_path)
            ->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);
        Log::spy();

        $this->actingAs($admin)
            ->delete(route('admin.advertisements.destroy', $advertisement, absolute: false))
            ->assertRedirect(route('admin.advertisements.index', absolute: false))
            ->assertSessionHas('error', 'تم حذف الإعلان، ولكن تعذر تنظيف ملف الصورة من التخزين.')
            ->assertSessionMissing('success');

        $this->assertDatabaseMissing('homepage_advertisements', ['id' => $advertisement->id]);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Homepage advertisement image cleanup failed.',
                Mockery::on(function (array $context) use ($advertisement): bool {
                    return $context['advertisement_id'] === $advertisement->id
                        && $context['reason'] === 'delete_returned_false'
                        && $context['path_hash'] === hash('sha256', $advertisement->image_path)
                        && $context['exception_class'] === null
                        && ! array_key_exists('path', $context);
                }),
            );
    }

    public function test_a_duplicate_delete_request_cannot_delete_any_other_record_or_file(): void
    {
        $admin = $this->user('admin', 'duplicate-delete-admin@example.com');
        $deleted = $this->advertisement('homepage-advertisements/delete-once.png');
        $preserved = $this->advertisement('homepage-advertisements/preserve.png');
        Storage::disk('public')->put($deleted->image_path, 'delete once');
        Storage::disk('public')->put($preserved->image_path, 'preserve');

        $url = route('admin.advertisements.destroy', $deleted, absolute: false);
        $this->actingAs($admin)->delete($url)
            ->assertSessionHas('success', 'تم حذف الصورة الإعلانية بنجاح.');
        $this->actingAs($admin)->delete($url)->assertNotFound();

        $this->assertDatabaseMissing('homepage_advertisements', ['id' => $deleted->id]);
        $this->assertDatabaseHas('homepage_advertisements', ['id' => $preserved->id]);
        Storage::disk('public')->assertMissing($deleted->image_path);
        Storage::disk('public')->assertExists($preserved->image_path);
    }

    public function test_the_homepage_receives_only_safe_public_fields_in_id_order_and_keeps_featured_jobs(): void
    {
        $first = $this->advertisement('homepage-advertisements/first.png', 'الإعلان الأول');
        $second = $this->advertisement('homepage-advertisements/second.webp', null);
        $unsafe = $this->advertisement('../private/secret.png', 'يجب ألا يظهر');
        Storage::disk('public')->put($first->image_path, 'first');
        Storage::disk('public')->put($second->image_path, 'second');

        $employer = $this->user('employer', 'featured-job-employer@example.com');
        $company = CompanyProfile::create([
            'user_id' => $employer->id,
            'company_name' => 'شركة الوظيفة المميزة',
        ]);
        $published = Job::create([
            'company_profile_id' => $company->id,
            'title' => 'وظيفة منشورة',
            'slug' => 'published-homepage-job',
            'description' => 'وصف الوظيفة المنشورة',
            'status' => 'published',
        ]);
        $draft = Job::create([
            'company_profile_id' => $company->id,
            'title' => 'وظيفة مسودة',
            'slug' => 'draft-homepage-job',
            'description' => 'وصف الوظيفة المسودة',
            'status' => 'draft',
        ]);

        $this->get(route('home', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('advertisements', [
                    [
                        'id' => $first->id,
                        'image_url' => Storage::disk('public')->url($first->image_path),
                        'alt_text' => 'الإعلان الأول',
                    ],
                    [
                        'id' => $second->id,
                        'image_url' => Storage::disk('public')->url($second->image_path),
                        'alt_text' => null,
                    ],
                ])
                ->has('featuredJobs', 1)
                ->where('featuredJobs.0.id', $published->id));

        $this->assertLessThan($second->id, $first->id);
        $this->assertDatabaseHas('homepage_advertisements', ['id' => $unsafe->id]);
        $this->assertDatabaseHas('jobs', ['id' => $draft->id, 'status' => 'draft']);
    }

    public function test_the_homepage_renders_with_an_empty_advertisement_collection(): void
    {
        $this->get(route('home', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('advertisements', [])
                ->where('featuredJobs', []));
    }

    private function user(string $role, string $email): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => $email,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function provider(User $user, string $type, array $overrides = []): TrainingProviderProfile
    {
        return TrainingProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => $type,
            'display_name' => $type === 'trainer' ? 'مدرب اختبار' : 'شركة تدريب اختبار',
            'verification_status' => 'incomplete',
            ...$overrides,
        ]);
    }

    private function advertisement(string $path, ?string $altText = null): HomepageAdvertisement
    {
        return HomepageAdvertisement::create([
            'image_path' => $path,
            'alt_text' => $altText,
        ]);
    }

    private function realUpload(string $name, string $contents, string $clientMimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'madarat-ad-test-');

        $this->assertNotFalse($path);
        $this->assertNotFalse(file_put_contents($path, $contents));
        $this->temporaryUploads[] = $path;

        return new UploadedFile($path, $name, $clientMimeType, null, true);
    }
}
