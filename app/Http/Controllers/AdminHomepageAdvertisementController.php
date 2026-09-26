<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHomepageAdvertisementRequest;
use App\Models\CompanyProfile;
use App\Models\HomepageAdvertisement;
use App\Models\JobSeekerProfile;
use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class AdminHomepageAdvertisementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAdministrator($request);
        $disk = Storage::disk('public');

        $advertisements = HomepageAdvertisement::query()
            ->orderBy('id')
            ->get(['id', 'image_path', 'alt_text', 'created_at'])
            ->map(fn (HomepageAdvertisement $advertisement): array => [
                'id' => $advertisement->id,
                'image_url' => HomepageAdvertisement::isManagedImagePath($advertisement->image_path)
                    ? $disk->url($advertisement->image_path)
                    : null,
                'alt_text' => $advertisement->alt_text,
                'created_at' => $advertisement->created_at?->toISOString(),
            ])
            ->values();

        return Inertia::render('Admin/Advertisements', [
            'advertisements' => $advertisements,
        ]);
    }

    public function store(StoreHomepageAdvertisementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('image');
        $extension = strtolower($file->extension());
        $storedPath = null;

        try {
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                throw new RuntimeException('The validated advertisement image has an unsupported extension.');
            }

            $path = $file->storeAs(
                HomepageAdvertisement::STORAGE_DIRECTORY,
                Str::uuid()->toString().'.'.$extension,
                'public',
            );

            if (! is_string($path) || ! HomepageAdvertisement::isManagedImagePath($path)) {
                throw new RuntimeException('The advertisement image could not be stored safely.');
            }

            $storedPath = $path;

            DB::transaction(fn () => HomepageAdvertisement::query()->create([
                'image_path' => $storedPath,
                'alt_text' => $data['alt_text'] ?? null,
            ]));
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $this->removeOrphanedUpload($storedPath);
            }

            Log::error('Homepage advertisement creation failed.', [
                'exception_class' => $exception::class,
            ]);

            return redirect()->route('admin.advertisements.index')->with(
                'error',
                StoreHomepageAdvertisementRequest::INVALID_IMAGE_MESSAGE,
            );
        }

        return redirect()->route('admin.advertisements.index')->with(
            'success',
            'تمت إضافة الصورة الإعلانية بنجاح.',
        );
    }

    public function destroy(Request $request, HomepageAdvertisement $advertisement): RedirectResponse
    {
        $this->ensureAdministrator($request);
        $advertisementId = $advertisement->id;
        $imagePath = $advertisement->image_path;

        try {
            DB::transaction(function () use ($advertisement): void {
                if (! $advertisement->delete()) {
                    throw new RuntimeException('The homepage advertisement record could not be deleted.');
                }
            });
        } catch (Throwable $exception) {
            Log::error('Homepage advertisement database deletion failed.', [
                'advertisement_id' => $advertisementId,
                'exception_class' => $exception::class,
            ]);

            return redirect()->route('admin.advertisements.index')->with(
                'error',
                'تعذر حذف الصورة الإعلانية. يرجى المحاولة مرة أخرى.',
            );
        }

        if (! $this->removeAdvertisementImage($advertisementId, $imagePath)) {
            return redirect()->route('admin.advertisements.index')->with(
                'error',
                'تم حذف الإعلان، ولكن تعذر تنظيف ملف الصورة من التخزين.',
            );
        }

        return redirect()->route('admin.advertisements.index')->with(
            'success',
            'تم حذف الصورة الإعلانية بنجاح.',
        );
    }

    private function ensureAdministrator(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    private function removeAdvertisementImage(int $advertisementId, mixed $imagePath): bool
    {
        if (! HomepageAdvertisement::isManagedImagePath($imagePath)) {
            $this->logCleanupFailure($advertisementId, 'unsafe_path');

            return false;
        }

        try {
            if ($this->imagePathIsReferencedElsewhere($imagePath)) {
                return true;
            }

            $disk = Storage::disk('public');

            if (! $disk->exists($imagePath)) {
                return true;
            }

            if ($disk->delete($imagePath) || ! $disk->exists($imagePath)) {
                return true;
            }

            $this->logCleanupFailure($advertisementId, 'delete_returned_false', $imagePath);
        } catch (Throwable $exception) {
            $this->logCleanupFailure(
                $advertisementId,
                'cleanup_exception',
                $imagePath,
                $exception,
            );
        }

        return false;
    }

    private function imagePathIsReferencedElsewhere(string $path): bool
    {
        return HomepageAdvertisement::query()->where('image_path', $path)->exists()
            || CompanyProfile::query()->where('logo_path', $path)->exists()
            || TrainingProviderProfile::query()
                ->where(fn (Builder $query) => $query
                    ->where('logo_path', $path)
                    ->orWhere('profile_image_path', $path))
                ->exists()
            || TrainingCourse::query()->where('cover_image_path', $path)->exists()
            || JobSeekerProfile::query()->where('cv_path', $path)->exists();
    }

    private function removeOrphanedUpload(string $path): void
    {
        if (! HomepageAdvertisement::isManagedImagePath($path)) {
            return;
        }

        try {
            $disk = Storage::disk('public');

            if ($disk->exists($path) && ! $disk->delete($path) && $disk->exists($path)) {
                $this->logCleanupFailure(null, 'orphan_delete_returned_false', $path);
            }
        } catch (Throwable $exception) {
            $this->logCleanupFailure(null, 'orphan_storage_exception', $path, $exception);
        }
    }

    private function logCleanupFailure(
        ?int $advertisementId,
        string $reason,
        mixed $path = null,
        ?Throwable $exception = null,
    ): void {
        Log::warning('Homepage advertisement image cleanup failed.', [
            'advertisement_id' => $advertisementId,
            'reason' => $reason,
            'path_hash' => is_string($path) ? hash('sha256', $path) : null,
            'exception_class' => $exception !== null ? $exception::class : null,
        ]);
    }
}
