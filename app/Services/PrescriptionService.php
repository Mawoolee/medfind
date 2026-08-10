<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles secure storage and retrieval of prescription images.
 *
 * All files are stored in the private `prescriptions` disk (outside
 * public/) and their binary content is encrypted with Laravel's built-in
 * AES-256-CBC encrypter (keyed by APP_KEY) before writing to disk.
 *
 * The stored filename is a random UUID with a .enc extension so it
 * cannot be guessed or served directly by the web server.
 */
class PrescriptionService
{
    protected string $disk = 'prescriptions';

    /**
     * Encrypt and store an uploaded prescription image.
     *
     * @return string  The opaque filename to persist in the database.
     */
    public function store(UploadedFile $file): string
    {
        // Read raw bytes from the upload
        $plainBytes = file_get_contents($file->getRealPath());

        // Encrypt with AES-256-CBC via Laravel's Crypt facade
        $encrypted = Crypt::encryptString(base64_encode($plainBytes));

        $filename = Str::uuid() . '.enc';

        Storage::disk($this->disk)->put($filename, $encrypted);

        return $filename;
    }

    /**
     * Retrieve, decrypt, and return the raw image bytes of a stored prescription.
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException if tampered.
     */
    public function retrieve(string $filename): string
    {
        if (!Storage::disk($this->disk)->exists($filename)) {
            abort(404, 'Prescription file not found.');
        }

        $encrypted = Storage::disk($this->disk)->get($filename);
        $base64    = Crypt::decryptString($encrypted);

        return base64_decode($base64);
    }

    /**
     * Guess the MIME type from the decrypted bytes (first 12 bytes magic).
     */
    public function mimeType(string $rawBytes): string
    {
        $hex = bin2hex(substr($rawBytes, 0, 12));

        // JPEG: ff d8 ff
        if (str_starts_with($hex, 'ffd8ff')) {
            return 'image/jpeg';
        }

        // PNG: 89 50 4e 47
        if (str_starts_with($hex, '89504e47')) {
            return 'image/png';
        }

        // GIF: 47 49 46 38
        if (str_starts_with($hex, '47494638')) {
            return 'image/gif';
        }

        // WebP: 52 49 46 46 ... 57 45 42 50
        if (str_starts_with($hex, '52494646') && str_contains($hex, '57454250')) {
            return 'image/webp';
        }

        // PDF: 25 50 44 46
        if (str_starts_with($hex, '25504446')) {
            return 'application/pdf';
        }

        return 'application/octet-stream';
    }

    /**
     * Delete a stored prescription file.
     */
    public function delete(string $filename): void
    {
        Storage::disk($this->disk)->delete($filename);
    }
}
