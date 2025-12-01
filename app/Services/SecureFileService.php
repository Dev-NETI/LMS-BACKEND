<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\UploadedFile;

class SecureFileService
{
    /**
     * Store an uploaded file securely with encryption
     */
    public function storeSecureFile(UploadedFile $file, string $directory = 'secure-training-materials'): array
    {
        // Generate unique filename
        $fileName = time() . '_' . $file->getClientOriginalName();
        $encryptedFileName = $this->generateSecureFileName($fileName);
        
        // Read file content and encrypt it
        $fileContent = file_get_contents($file->getRealPath());
        $encryptedContent = Crypt::encrypt($fileContent);
        
        // Store encrypted file in private storage
        $filePath = $directory . '/' . $encryptedFileName;
        Storage::disk('local')->put($filePath, $encryptedContent);
        
        return [
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $fileName,
            'encrypted_path' => $filePath,
            'size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType()
        ];
    }
    
    /**
     * Retrieve and decrypt a secure file
     */
    public function getSecureFile(string $encryptedPath): ?string
    {
        if (!Storage::disk('local')->exists($encryptedPath)) {
            return null;
        }
        
        $encryptedContent = Storage::disk('local')->get($encryptedPath);
        
        try {
            return Crypt::decrypt($encryptedContent);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt file: ' . $encryptedPath, ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Delete a secure file
     */
    public function deleteSecureFile(string $encryptedPath): bool
    {
        return Storage::disk('local')->delete($encryptedPath);
    }
    
    /**
     * Check if a secure file exists
     */
    public function secureFileExists(string $encryptedPath): bool
    {
        return Storage::disk('local')->exists($encryptedPath);
    }
    
    /**
     * Generate a secure filename that's not easily guessable
     */
    private function generateSecureFileName(string $originalName): string
    {
        $hash = hash('sha256', $originalName . time() . random_bytes(16));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return $hash . ($extension ? '.' . $extension : '') . '.enc';
    }
    
    /**
     * Get file info for secure file (without decrypting)
     */
    public function getSecureFileInfo(string $encryptedPath): array
    {
        if (!$this->secureFileExists($encryptedPath)) {
            return [];
        }
        
        $size = Storage::disk('local')->size($encryptedPath);
        $lastModified = Storage::disk('local')->lastModified($encryptedPath);
        
        return [
            'exists' => true,
            'size' => $size,
            'last_modified' => $lastModified
        ];
    }
}