<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Table(name: 'files')]
#[ORM\Entity]
class File implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $subdirectory;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $filename;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $extension;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $entity;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubdirectory(): ?string
    {
        return $this->subdirectory;
    }

    public function setSubdirectory(string $subdirectory): self
    {
        $this->subdirectory = $subdirectory;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getURL(): string
    {
        return '/uploads/' . $this->subdirectory . '/' . $this->filename . '.' . $this->extension;
    }

    public function getFullFilename(): string
    {
        return $this->getFilename() . '.' . $this->getExtension();
    }

    /**
     * @return string Returns the path of the file relative to the upload directory.
     */
    public function getRelativePath(): string
    {
        return $this->getSubdirectory() . '/' . $this->getFullFilename();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'fullFilename' => $this->getFullFilename(),
            'relativePath' => $this->getRelativePath(),
            'url' => $this->getURL()
        ];
    }
}
