<?php
declare(strict_types=1);

namespace Electroplan\Auth;

/**
 * Provider-neutral Identity Profile representation for Electroplan.
 *
 * Encapsulates identity claims received from any identity provider
 * without vendor lock-in.
 */
class IdentityProfile
{
    private string $id;
    private ?string $email;
    private ?string $name;
    private ?string $username;
    private bool $active;
    private array $roles;
    private ?string $provider;
    private array $claims;

    /**
     * @param string $id Unique identifier from identity provider
     * @param string|null $email User email
     * @param string|null $name Full name
     * @param string|null $username Username or handle
     * @param bool $active Account active status (default: false, fail-closed)
     * @param list<string> $roles Roles assigned to user
     * @param string|null $provider Identity provider identifier
     * @param array<string, mixed> $claims Full raw claims map
     */
    public function __construct(
        string $id,
        ?string $email = null,
        ?string $name = null,
        ?string $username = null,
        bool $active = false,
        array $roles = [],
        ?string $provider = null,
        array $claims = []
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
        $this->username = $username;
        $this->active = $active;
        $this->roles = $roles;
        $this->provider = $provider;
        $this->claims = $claims;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClaims(): array
    {
        return $this->claims;
    }

    public function getClaim(string $key, mixed $default = null): mixed
    {
        return $this->claims[$key] ?? $default;
    }

    /**
     * Factory method creating an IdentityProfile from a claims array.
     *
     * @param array<string, mixed> $claims
     * @param string|null $provider
     * @return self
     * @throws \InvalidArgumentException if user identifier is missing
     */
    public static function fromClaims(array $claims, ?string $provider = null): self
    {
        $id = '';
        if (isset($claims['hub_user_id']) && is_scalar($claims['hub_user_id'])) {
            $id = trim((string)$claims['hub_user_id']);
        } elseif (isset($claims['sub']) && is_scalar($claims['sub'])) {
            $id = trim((string)$claims['sub']);
        } elseif (isset($claims['id']) && is_scalar($claims['id'])) {
            $id = trim((string)$claims['id']);
        } elseif (isset($claims['user_id']) && is_scalar($claims['user_id'])) {
            $id = trim((string)$claims['user_id']);
        }

        if ($id === '') {
            throw new \InvalidArgumentException('Identity profile requires a non-empty user identifier.');
        }

        $email = isset($claims['email']) && is_string($claims['email']) ? trim($claims['email']) : null;
        $name = isset($claims['name']) && is_string($claims['name']) ? trim($claims['name']) : null;
        $username = isset($claims['username']) && is_string($claims['username'])
            ? trim($claims['username'])
            : (isset($claims['preferred_username']) && is_string($claims['preferred_username']) ? trim($claims['preferred_username']) : null);

        $active = false;
        if (array_key_exists('active', $claims) && $claims['active'] !== null) {
            $rawActive = $claims['active'];
            if (is_bool($rawActive)) {
                $active = $rawActive;
            } elseif (is_int($rawActive)) {
                $active = ($rawActive === 1);
            } elseif (is_string($rawActive)) {
                $lower = strtolower(trim($rawActive));
                $active = in_array($lower, ['1', 'true', 'yes', 'on', 'active'], true);
            }
        }

        $roles = [];
        if (isset($claims['roles']) && is_array($claims['roles'])) {
            $roles = array_values(array_filter($claims['roles'], 'is_string'));
        }

        $providerName = $provider ?? (isset($claims['provider']) && is_string($claims['provider']) ? $claims['provider'] : null);

        return new self(
            $id,
            $email,
            $name,
            $username,
            $active,
            $roles,
            $providerName,
            $claims
        );
    }

    /**
     * Converts the profile to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'username' => $this->username,
            'active' => $this->active,
            'roles' => $this->roles,
            'provider' => $this->provider,
            'claims' => $this->claims,
        ];
    }
}

if (!class_exists('IdentityProfile', false)) {
    class_alias(\Electroplan\Auth\IdentityProfile::class, 'IdentityProfile');
}
