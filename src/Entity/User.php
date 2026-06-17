<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]

#[ORM\Table(
    name: 'users',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', columns: ['email']),
        // ne rends pas "username" unique si ta base existante ne l'est pas
    ]
)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 85, nullable: true)]
    private ?string $fname = null;

    #[ORM\Column(length: 85, nullable: true)]
    private ?string $lname = null;

    #[ORM\Column(length: 85, nullable: true, unique: true)]
    private ?string $username = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;



    // Héritage du schéma – inutilisé par bcrypt/argon2id
    #[ORM\Column(length: 85, nullable: true)]
    private ?string $salt = null;

    #[ORM\Column(length: 85, nullable: true)]
    private ?string $organization = null;

    #[ORM\Column(length: 85, nullable: true)]
    private ?string $position = null;

    #[ORM\Column(length: 85, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 85)]
    private string $timezone = 'Europe/Paris';

    #[ORM\Column(type: 'string', length: 10)]
    private string $language = 'fr';

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 1])]
    private int $homepageid = 1;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $date_created;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $last_modified = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastlogin = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        // Si la date n'est pas déjà mise, on met la date actuelle
        if (!isset($this->date_created)) {
            $this->date_created = new \DateTime();
        }
    }


    // FK vers user_statuses.status_id (tinyint unsigned)
    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $status_id = 1;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $legacyid = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $legacypassword = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uid = null;



    /**
     * @var Collection<int, ResourceGroup>
     */
    #[ORM\ManyToMany(targetEntity: ResourceGroup::class, mappedBy: 'users')]
    private Collection $resourceGroups;

    public function __construct()
    {
        $this->resourceGroups = new ArrayCollection();
    }

    public function __toString(): string
    {
        $name = trim(($this->fname ?? '') . ' ' . ($this->lname ?? ''));
        return $name !== '' ? $name : ($this->email ?? '');
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
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

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Sérialisation pour la session PHP (CSRF uniquement en mode stateless SSO).
     * On ne hash plus le password ici car :
     * - Le firewall est en stateless: true (pas de token de sécurité en session)
     * - Le password est fixe ('!SSO_NO_PASSWORD!') pour tous les users SSO
     * - La vérification du password par Symfony est donc toujours stable
     */
    public function __serialize(): array
    {
        return (array) $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $u): self
    {
        $this->username = $u;
        return $this;
    }

    public function getFname(): ?string
    {
        return $this->fname;
    }

    public function setFname(?string $v): self
    {
        $this->fname = $v;
        return $this;
    }

    public function getLname(): ?string
    {
        return $this->lname;
    }

    public function setLname(?string $v): self
    {
        $this->lname = $v;
        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $tz): self
    {
        $this->timezone = $tz;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $lang): self
    {
        $this->language = $lang;
        return $this;
    }

    public function getHomepageid(): int
    {
        return $this->homepageid;
    }

    public function setHomepageid(int $v): self
    {
        $this->homepageid = $v;
        return $this;
    }

    public function getDateCreated(): \DateTimeInterface
    {
        return $this->date_created;
    }

    public function setDateCreated(\DateTimeInterface $d): self
    {
        $this->date_created = $d;
        return $this;
    }

    public function getLastModified(): ?\DateTimeInterface
    {
        return $this->last_modified;
    }

    public function setLastModified(?\DateTimeInterface $d): self
    {
        $this->last_modified = $d;
        return $this;
    }

    public function getLastlogin(): ?\DateTimeInterface
    {
        return $this->lastlogin;
    }

    public function setLastlogin(?\DateTimeInterface $d): self
    {
        $this->lastlogin = $d;
        return $this;
    }

    public function getStatusId(): int
    {
        return $this->status_id;
    }

    public function setStatusId(int $v): self
    {
        $this->status_id = $v;
        return $this;
    }

    public function getLegacyid(): ?string
    {
        return $this->legacyid;
    }

    public function setLegacyid(?string $v): self
    {
        $this->legacyid = $v;
        return $this;
    }

    public function getLegacypassword(): ?string
    {
        return $this->legacypassword;
    }

    public function setLegacypassword(?string $v): self
    {
        $this->legacypassword = $v;
        return $this;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function setSalt(?string $salt): static
    {
        $this->salt = $salt;

        return $this;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function setOrganization(?string $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }


    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(?string $uid): static
    {
        $this->uid = $uid;
        return $this;
    }



    /**
     * @return Collection<int, ResourceGroup>
     */
    public function getResourceGroups(): Collection
    {
        return $this->resourceGroups;
    }

    public function addResourceGroup(ResourceGroup $resourceGroup): static
    {
        if (!$this->resourceGroups->contains($resourceGroup)) {
            $this->resourceGroups->add($resourceGroup);
            $resourceGroup->addUser($this);
        }

        return $this;
    }

    public function removeResourceGroup(ResourceGroup $resourceGroup): static
    {
        if ($this->resourceGroups->removeElement($resourceGroup)) {
            $resourceGroup->removeUser($this);
        }

        return $this;
    }


}
