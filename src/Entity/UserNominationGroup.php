<?php

namespace App\Entity;

use App\Repository\UserNominationGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserNominationGroupRepository::class)]
#[ORM\Table(name: 'user_nomination_groups')]
#[ORM\UniqueConstraint(name: 'award_name', columns: ['award_id', 'name'])]
class UserNominationGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?bool $ignored = false;

    #[ORM\OneToOne(inversedBy: 'userNominationGroup', cascade: ['persist', 'remove'])]
    private ?Nominee $Nominee = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $mergedInto = null;

    #[ORM\ManyToOne(inversedBy: 'userNominationGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Award $award = null;

    /**
     * @var Collection<int, UserNomination>
     */
    #[ORM\OneToMany(mappedBy: 'nominationGroup', targetEntity: UserNomination::class, orphanRemoval: true)]
    private Collection $nominations;

    public function __construct()
    {
        $this->nominations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isIgnored(): ?bool
    {
        return $this->ignored;
    }

    public function setIgnored(bool $ignored): static
    {
        $this->ignored = $ignored;

        return $this;
    }

    public function getNominee(): ?Nominee
    {
        return $this->Nominee;
    }

    public function setNominee(?Nominee $Nominee): static
    {
        $this->Nominee = $Nominee;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMergedInto(): ?self
    {
        return $this->mergedInto;
    }

    public function setMergedInto(?self $mergedInto): static
    {
        $this->mergedInto = $mergedInto;

        return $this;
    }

    public function getAward(): ?Award
    {
        return $this->award;
    }

    public function setAward(?Award $award): static
    {
        $this->award = $award;

        return $this;
    }

    /**
     * @return Collection<int, UserNomination>
     */
    public function getNominations(): Collection
    {
        return $this->nominations;
    }

    public function addNomination(UserNomination $nomination): static
    {
        if (!$this->nominations->contains($nomination)) {
            $this->nominations->add($nomination);
            $nomination->setNominationGroup($this);
        }

        return $this;
    }

    public function removeNomination(UserNomination $nomination): static
    {
        if ($this->nominations->removeElement($nomination)) {
            // set the owning side to null (unless already changed)
            if ($nomination->getNominationGroup() === $this) {
                $nomination->setNominationGroup(null);
            }
        }

        return $this;
    }
}
