<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 60)]
    private ?string $displayName = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    /** Whether an admin has approved this account (registration moderation). */
    #[ORM\Column]
    private bool $approved = false;

    /** Whether the account is active; false = banned/disabled (cannot log in). */
    #[ORM\Column]
    private bool $active = true;

    /** Authelia OIDC subject id, set when the account is linked to the zebbox SSO. */
    #[ORM\Column(length: 100, nullable: true, unique: true)]
    private ?string $autheliaId = null;

    /** Random token enabling a public read-only "missing & duplicates" page. */
    #[ORM\Column(length: 32, nullable: true, unique: true)]
    private ?string $shareToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, UserAlbum> */
    #[ORM\OneToMany(targetEntity: UserAlbum::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userAlbums;

    /** @var Collection<int, UserSticker> */
    #[ORM\OneToMany(targetEntity: UserSticker::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userStickers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->userAlbums = new ArrayCollection();
        $this->userStickers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        $this->approved = $approved;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getAutheliaId(): ?string
    {
        return $this->autheliaId;
    }

    public function setAutheliaId(?string $autheliaId): static
    {
        $this->autheliaId = $autheliaId;

        return $this;
    }

    public function getShareToken(): ?string
    {
        return $this->shareToken;
    }

    public function setShareToken(?string $shareToken): static
    {
        $this->shareToken = $shareToken;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here.
    }

    /**
     * @return Collection<int, UserAlbum>
     */
    public function getUserAlbums(): Collection
    {
        return $this->userAlbums;
    }

    /**
     * @return Collection<int, UserSticker>
     */
    public function getUserStickers(): Collection
    {
        return $this->userStickers;
    }
}
