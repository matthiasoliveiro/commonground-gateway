<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity holds the information about a common ground gateway.
 *
 * @ApiResource(
 *     	normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     	denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *     collectionOperations={
 *          "post"={"path"="/admin/authentications"},
 *     		"get"={"path"="/admin/authentications"},
 *     },
 *      itemOperations={
 * 		    "get"={
 *              "read"=false,
 *              "validate"=false,
 *              "path"="/admin/authentications/{id}"
 *          },
 * 	        "put"={"path"="/admin/authentications/{id}"},
 * 	        "delete"={"path"="/admin/authentications/{id}"},
 *          "get_change_logs"={
 *              "path"="/authentications/{id}/change_log",
 *              "method"="get",
 *              "openapi_context" = {
 *                  "summary"="Changelogs",
 *                  "description"="Gets al the change logs for this resource"
 *              }
 *          },
 *          "get_audit_trail"={
 *              "path"="/authentications/{id}/audit_trail",
 *              "method"="get",
 *              "openapi_context" = {
 *                  "summary"="Audittrail",
 *                  "description"="Gets the audit trail for this resource"
 *              }
 *          },
 *     },
 * )
 * @ORM\Entity(repositoryClass="App\Repository\AuthenticationRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 * @ApiFilter(BooleanFilter::class)
 * @ApiFilter(OrderFilter::class)
 * @ApiFilter(DateFilter::class, strategy=DateFilter::EXCLUDE_NULL)
 * @ApiFilter(SearchFilter::class)
 * @UniqueEntity("name")
 */
class Authentication
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @Assert\Uuid
     * @Groups({"read","read_secure"})
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * @var string The Name of the Gateway which is used in the commonGround service
     *
     * @Assert\NotNull
     * @Assert\Length(
     *      max = 255
     * )
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="digispoof"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @var ?string The location where the authentication is hosted
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="https://test.nl/authenticate"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $authenticateUrl = null;

    /**
     * @var ?string The location where the token for the authentication is hosted
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="https://test.nl/token"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $tokenUrl = null;

    /**
     * @var ?string The secret used for authentication
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="secret"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $secret = null;

    /**
     * @var ?string The client id used for authentication
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="client id"
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $clientId = null;

    /**
     * @var ?array The scopes used for authentication
     *
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="array",
     *         }
     *     }
     * )
     * @Groups({"read","read_secure","write"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $scopes = [];

    /**
     * @var Datetime The moment this resource was created
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var Datetime The moment this resource was last Modified
     *
     * @Groups({"read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAuthenticateUrl(): ?string
    {
        return $this->authenticateUrl;
    }

    public function setAuthenticateUrl(string $authenticateUrl): self
    {
        $this->authenticateUrl = $authenticateUrl;

        return $this;
    }

    public function getTokenUrl(): ?string
    {
        return $this->tokenUrl;
    }

    public function setTokenUrl(string $tokenUrl): self
    {
        $this->tokenUrl = $tokenUrl;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getScopes(): ?array
    {
        return $this->scopes;
    }

    public function setScopes(?array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function getDateCreated(): ?DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    public function getDateModified(): ?DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }
}
