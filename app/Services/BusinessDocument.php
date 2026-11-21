<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The SSM registration certificate for a business. It identifies a business, so it is kept on the
 * private disk and only served to the account holder and to management accounts.
 */
class BusinessDocument
{
    public const DISK = 'local';

    public const DIRECTORY = 'business-documents';

    public function store(User $user, UploadedFile $file): void
    {
        $previousPath = $user->business_document_path;
        $extension = strtolower((string) $file->extension());

        $path = Storage::disk(self::DISK)->putFileAs(self::DIRECTORY, $file, 'user-'.$user->id.'.'.$extension);

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk(self::DISK)->delete($previousPath);
        }

        $user->forceFill([
            'business_document_path' => $path,
            'business_document_name' => mb_substr($file->getClientOriginalName(), 0, 190),
        ])->save();
    }

    /**
     * The document as a file download, or a 404 JSON response when none has been uploaded.
     */
    public function response(User $owner): Response
    {
        if ($owner->business_document_path === null) {
            return response()->json(['message' => __('app.profile.document_missing')], 404);
        }

        app(ActivityLogger::class)->record('business.document_viewed', null, null, $owner);

        return $this->download($owner);
    }

    private function download(User $user): Response
    {
        $extension = pathinfo((string) $user->business_document_path, PATHINFO_EXTENSION);

        return Storage::disk(self::DISK)->download(
            $user->business_document_path,
            'SSM-'.$user->id.'.'.$extension,
        );
    }
}
