<?php

namespace App\Factory;

use App\Entity\KPIFile;
use App\Entity\KPIValue;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class KpiFileFactory
{
    public function __construct(
        private SluggerInterface $slugger,
        private string $uploadDir = 'uploads/',
    ) {
    }

    public function createFromUpload(UploadedFile $file, KPIValue $kpiValue): KPIFile
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $filename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        $uploadPath = __DIR__.'/../../public/'.rtrim($this->uploadDir, '/').'/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $file->move($uploadPath, $filename);

        $kpiFile = new KPIFile();
        $kpiFile->setFilename($filename);
        $kpiFile->setOriginalName($file->getClientOriginalName());
        $kpiFile->setMimeType($file->getMimeType());
        $kpiFile->setFileSize($file->getSize());
        $kpiFile->setKpiValue($kpiValue);

        return $kpiFile;
    }
}
