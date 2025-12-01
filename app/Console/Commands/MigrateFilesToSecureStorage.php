<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\TrainingMaterial;
use App\Services\SecureFileService;

class MigrateFilesToSecureStorage extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'files:migrate-to-secure-storage';

    /**
     * The console command description.
     */
    protected $description = 'Migrate existing training material files from public storage to encrypted secure storage';

    /**
     * Execute the console command.
     */
    public function handle(SecureFileService $secureFileService)
    {
        $this->info('Starting migration of files to secure storage...');
        
        $materials = TrainingMaterial::all();
        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($materials as $material) {
            $this->info("Processing: {$material->file_name}");
            
            // Check if file exists in public storage
            $publicPath = storage_path('app/public/' . $material->file_path);
            
            if (!file_exists($publicPath)) {
                $this->warn("File not found: {$publicPath}");
                $skipped++;
                continue;
            }
            
            try {
                // Read the existing file
                $fileContent = file_get_contents($publicPath);
                
                // Create a temporary uploaded file for the secure service
                $tempFile = tmpfile();
                fwrite($tempFile, $fileContent);
                $tempPath = stream_get_meta_data($tempFile)['uri'];
                
                // Create a fake uploaded file object
                $uploadedFile = new \Illuminate\Http\UploadedFile(
                    $tempPath,
                    $material->file_name,
                    $material->file_type,
                    null,
                    true
                );
                
                // Store securely
                $secureFileData = $secureFileService->storeSecureFile($uploadedFile);
                
                // Update the material record
                $material->update([
                    'file_path' => $secureFileData['encrypted_path']
                ]);
                
                // Delete the old public file
                unlink($publicPath);
                
                // Clean up temp file
                fclose($tempFile);
                
                $this->info("✓ Migrated: {$material->file_name}");
                $migrated++;
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to migrate {$material->file_name}: " . $e->getMessage());
                $errors++;
                
                // Clean up temp file if it exists
                if (isset($tempFile)) {
                    fclose($tempFile);
                }
            }
        }
        
        $this->info("\nMigration completed!");
        $this->info("Migrated: {$migrated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");
        
        return 0;
    }
}