<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class CloudinaryService
{
    private ?Cloudinary $client = null;

    private function client(): Cloudinary
    {
        if ($this->client === null) {
            $url = config('cloudinary.url');

            if (! $url) {
                throw new RuntimeException('CLOUDINARY_URL is not set in the .env file.');
            }

            $this->client = new Cloudinary($url);
        }

        return $this->client;
    }

    /**
     * Uploads a photo and returns its public URL and public id.
     * Large photos are shrunk to fit inside 1600 x 2400 px to save storage. That is still big
     * enough for the 1200 x 1600 zoom view on the gown page.
     *
     * @return array{url: string, public_id: string}
     */
    public function upload(UploadedFile $file, string $folder = 'gowns'): array
    {
        $result = $this->client()->uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder,
            'resource_type' => 'image',
            'transformation' => [
                ['width' => 1600, 'height' => 2400, 'crop' => 'limit', 'quality' => 'auto'],
            ],
        ]);

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    /**
     * Removes a photo from Cloudinary. A failure is logged but never stops the page.
     */
    public function delete(?string $publicId): bool
    {
        if (! $publicId) {
            return false;
        }

        try {
            $this->client()->uploadApi()->destroy($publicId, ['invalidate' => true]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
