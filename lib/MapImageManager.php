<?php

class MapImageManager {
    private $mapsDirectory;
    private $localImagesDirectory;

    public function __construct($mapsDirectory, $localImagesDirectory) {
        if (!extension_loaded('imagick')) {
            throw new RuntimeException("L'extension PHP Imagick est requise");
        }
        
        $this->mapsDirectory = $mapsDirectory;
        $this->localImagesDirectory = $localImagesDirectory;
        
        if (!is_dir($this->localImagesDirectory)) {
            mkdir($this->localImagesDirectory, 0777, true);
        }
    }

    public function getMapImage($mapName) {
        try {
            $localPath = $this->localImagesDirectory . DIRECTORY_SEPARATOR . $mapName . '.jpg';
            
            if (file_exists($localPath)) {
                return 'images/maps/' . $mapName . '.jpg';
            }

            if (!is_dir($this->mapsDirectory)) {
                error_log("Dossier maps non trouvé: " . $this->mapsDirectory);
                return 'images/maps/default.jpg';
            }

            $pk3Files = glob($this->mapsDirectory . DIRECTORY_SEPARATOR . '*.pk3');
            foreach ($pk3Files as $pk3File) {
                $zip = new ZipArchive();
                if ($zip->open($pk3File) === true) {
                    $levelshotJpg = 'levelshots/' . $mapName . '.jpg';
                    // Créer un tableau avec les deux possibilités de casse pour .tga
                    $levelshotTgaVariants = [
                        'levelshots/' . $mapName . '.tga',
                        'levelshots/' . $mapName . '.TGA'
                    ];
                    
                    // D'abord essayer le JPG
                    if ($zip->locateName($levelshotJpg) !== false) {
                        if ($this->extractAndSaveImage($zip, $levelshotJpg, $localPath)) {
                            $zip->close();
                            return 'images/maps/' . $mapName . '.jpg';
                        }
                    }
                    
                    // Ensuite essayer les deux variantes de TGA
                    foreach ($levelshotTgaVariants as $levelshotTga) {
                        if ($zip->locateName($levelshotTga) !== false) {
                            $tempDir = $this->localImagesDirectory . DIRECTORY_SEPARATOR . 'temp';
                            if (!is_dir($tempDir)) {
                                mkdir($tempDir, 0777, true);
                            }
                            
                            $tempTgaPath = $tempDir . DIRECTORY_SEPARATOR . $levelshotTga;
                            if ($zip->extractTo($tempDir, $levelshotTga) && file_exists($tempTgaPath)) {
                                if ($this->convertTgaToJpg($tempTgaPath, $localPath)) {
                                    $zip->close();
                                    $this->cleanupTempDirectories($tempDir);
                                    return 'images/maps/' . $mapName . '.jpg';
                                }
                            }
                            $this->cleanupTempDirectories($tempDir);
                        }
                    }
                    
                    $zip->close();
                }
            }
        } catch (Exception $e) {
            error_log("Erreur lors du traitement de l'image: " . $e->getMessage());
        }

        return 'images/maps/default.jpg';
    }

    private function convertTgaToJpg($tgaPath, $jpgPath) {
        try {
            if (!extension_loaded('imagick')) {
                error_log("Extension Imagick non disponible");
                return false;
            }

            // Créer une nouvelle instance Imagick
            $image = new Imagick();
            
            // Lire l'image TGA
            $image->readImage($tgaPath);
            
            // Retourner l'image verticalement
            $image->flipImage();
            
            // Convertir en JPG
            $image->setImageFormat('jpeg');
            $image->setImageCompression(Imagick::COMPRESSION_JPEG);
            $image->setImageCompressionQuality(95);
            
            // Sauvegarder l'image
            $success = $image->writeImage($jpgPath);
            
            // Libérer la mémoire
            $image->clear();
            $image->destroy();
            
            return $success;

        } catch (ImagickException $e) {
            error_log("Erreur Imagick: " . $e->getMessage());
            if (isset($image)) {
                $image->clear();
                $image->destroy();
            }
            return false;
        }
    }

    private function extractAndSaveImage($zip, $sourcePath, $destPath) {
        $tempDir = $this->localImagesDirectory . DIRECTORY_SEPARATOR . 'temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        if ($zip->extractTo($tempDir, $sourcePath)) {
            $extractedPath = $tempDir . DIRECTORY_SEPARATOR . $sourcePath;
            if (file_exists($extractedPath)) {
                $result = rename($extractedPath, $destPath);
                $this->cleanupTempDirectories($tempDir);
                return $result;
            }
        }
        
        $this->cleanupTempDirectories($tempDir);
        return false;
    }

    private function cleanupTempDirectories($tempDir) {
        if (!is_dir($tempDir)) return;
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($tempDir);
    }
}