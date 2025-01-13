<?php
namespace App\Service;

use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RandomLib\Factory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class FileService
{
    const FILESIZE_LIMIT = 1024 * 1024 * 10;

    const EXTENSION_MAPPING = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'audio/ogg' => 'ogg',
        'video/ogg' => 'ogg',
        'video/webm' => 'webm',
        'application/ogg' => 'ogg',
        'application/x-zip-compressed' => 'zip',
        'font/ttf' => 'ttf',
        'font/otf' => 'otf',
        'application/font-woff' => 'woff',
        'application/font-woff2' => 'woff2',
        'font/woff' => 'woff',
        'font/woff2' => 'woff2',
    ];

    /** @var string */
    private string $uploadDirectory;

    public function __construct(
        string $projectDir,
        private EntityManagerInterface $em,
        private AuthorizationCheckerInterface $authChecker,
    ) {
        $this->uploadDirectory = $projectDir . '/public/uploads/';
    }

    public function validateUploadedFile(?UploadedFile $file): void
    {
        if ($file === null) {
            throw new Exception('No file was uploaded');
        } elseif (!$file->isValid()) {
            throw new Exception($file->getErrorMessage());
        } elseif (!in_array($file->getClientMimeType(), array_keys(self::EXTENSION_MAPPING), true) && !$this->authChecker->isGranted('ROLE_BYPASS_MIME_CHECKS')) {
            throw new Exception('Invalid MIME type (' . $file->getClientMimeType() . ')');
        } elseif ($file->getSize() > self::FILESIZE_LIMIT) {
            throw new Exception('Filesize of ' . self::humanFilesize($file->getSize()) . ' exceeds limit of ' . self::humanFilesize(self::FILESIZE_LIMIT));
        }
    }

    /**
     * This function does not adhere to security best practices (maybe?)
     */
    public function handleUploadedFile(?UploadedFile $file, string $entityType, string $directory, ?string $filename): File
    {
        $this->validateUploadedFile($file);

        if (!file_exists($this->uploadDirectory . $directory)) {
            mkdir($this->uploadDirectory . $directory, 0777, true);
        }

        if ($filename === null) {
            $factory = new Factory;
            $generator = $factory->getLowStrengthGenerator();
            $token = hash('sha1', $generator->generate(64));
            $filename = substr($token, 0, 8);
        }

        $fileEntity = new File();
        $fileEntity->setSubdirectory($directory);
        $fileEntity->setFilename($filename . '-' . time());

        $extension = self::EXTENSION_MAPPING[$file->getClientMimeType()]
            ?? $file->getClientOriginalExtension();

        $fileEntity->setExtension($extension);
        $fileEntity->setEntity($entityType);

        $this->em->persist($fileEntity);
        $this->em->flush();

        $file->move($this->uploadDirectory . $directory . '/', $fileEntity->getFullFilename());

        return $fileEntity;
    }

    public function createFileFromString(string $contents, string $extension, string $entityType, string $directory, ?string $filename): File
    {
        if (!file_exists($this->uploadDirectory . $directory)) {
            mkdir($this->uploadDirectory . $directory, 0777, true);
        }

        if ($filename === null) {
            $factory = new Factory;
            $generator = $factory->getLowStrengthGenerator();
            $token = hash('sha1', $generator->generate(64));
            $filename = substr($token, 0, 8);
        }

        $fileEntity = new File();
        $fileEntity->setSubdirectory($directory);
        $fileEntity->setFilename($filename . '-' . time());
        $fileEntity->setExtension($extension);
        $fileEntity->setEntity($entityType);

        $this->em->persist($fileEntity);
        $this->em->flush();

        file_put_contents($this->uploadDirectory . $directory . '/' . $fileEntity->getFullFilename(), $contents);

        return $fileEntity;
    }

    public function deleteFile(File $file): void
    {
        unlink($this->uploadDirectory . $file->getRelativePath());
        $this->em->remove($file);
    }

    /**
     * Converts a number of bytes into a human-readable filesize.
     * This implementation is efficient, but will sometimes return a value that's less than one due
     * to the differences between 1000 and 1024 (for example, 0.98 GB)
     * @param int $bytes File size in bytes.
     * @return string     The human-readable string, to two decimal places.
     */
    public static function humanFilesize(int $bytes): string
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        // Determine the magnitude of the size from the length of the string.
        // Use the last element of the size array as the upper bound.
        $factor = min(floor((strlen($bytes) - 1) / 3), count($size) - 1);
        return sprintf("%.2f", $bytes / pow(1024, $factor)) . $size[$factor];
    }
}
