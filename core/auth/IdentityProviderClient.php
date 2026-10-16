<?php
declare(strict_types=1);

namespace Electroplan\Auth;

/**
 * Provider-neutral identity client contract for Electroplan.
 *
 * Defines the contract for initiating identity flows, exchanging authorization
 * codes (with PKCE), and fetching normalized identity profiles.
 */
interface IdentityProviderClient
{
    /**
     * Builds the authorization redirect URL.
     *
     * @param array<string, mixed> $options
     * @return string
     */
    public function getAuthorizationUrl(array $options = []): string;

    /**
     * Authenticates using the authorization code and PKCE code verifier,
     * returning the normalized provider-neutral IdentityProfile.
     *
     * @param string $code Authorization code
     * @param string $codeVerifier PKCE code verifier
     * @return IdentityProfile
     */
    public function authenticate(string $code, string $codeVerifier): IdentityProfile;

    /**
     * Retrieves the identity profile using authorization code and PKCE code verifier.
     *
     * @param string $code Authorization code
     * @param string $codeVerifier PKCE code verifier
     * @return IdentityProfile
     */
    public function fetchProfile(string $code, string $codeVerifier): IdentityProfile;
}

if (!interface_exists('IdentityProviderClient', false)) {
    class_alias(\Electroplan\Auth\IdentityProviderClient::class, 'IdentityProviderClient');
}
