<?php
/**
 * Identity provider class file
 *
 * @author Callistus Nwachukwu
 * @since 0.3.0
 */

namespace SmartLicenseServer\Environments\Application\Auth;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\IdentityProviders\AbstractIdentityProvider;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;

/**
 * Identity service provider.
 *
 * This class performs handles identitity provision regardless of PHP SAPI.
 *
 * The password methods need the web provider; with the console provider
 * (which authenticates by API key and has no sessions) they report that
 * password sign-in is not available instead of failing.
 */
class IdentityService extends AbstractIdentityProvider implements PasswordIdentityProviderInterface {
    /**
     * Class constructor.
     *
     * @param WebIdentityProvider|ConsoleIdentityProvider|null $provider
     */
    public function __construct(
        protected Guard $guard,
        protected WebIdentityProvider|ConsoleIdentityProvider $provider
    ) {}

    /**
     * {@inheritdoc}
     */
    public function authenticate(): ?Principal {
        return $this->provider->authenticate();
    }

    /**
     * {@inheritdoc}
     */
    public function logon(string $email, string $pwd, bool $remember = false): RequestException|Principal {
        if ( ! $this->provider instanceof PasswordIdentityProviderInterface ) {
            return $this->unsupported();
        }

        return $this->provider->logon( $email, $pwd, $remember );
    }

    /**
     * {@inheritdoc}
     */
    public function signup( Request $request ): RequestException|Principal {
        if ( ! $this->provider instanceof PasswordIdentityProviderInterface ) {
            return $this->unsupported();
        }

        return $this->provider->signup( $request );
    }

    /**
     * {@inheritdoc}
     */
    public function logout(): void {
        if ( $this->provider instanceof PasswordIdentityProviderInterface ) {
            $this->provider->logout();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function logout_everywhere(): void {
        if ( $this->provider instanceof PasswordIdentityProviderInterface ) {
            $this->provider->logout_everywhere();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset_password(User $user, string $new_pwd): bool {
        if ( ! $this->provider instanceof PasswordIdentityProviderInterface ) {
            return false;
        }

        return $this->provider->reset_password( $user, $new_pwd );
    }

    /**
     * The error returned by password methods when the provider has no password sign-in.
     *
     * @return RequestException
     */
    private function unsupported() : RequestException {
        return new RequestException(
            'password_auth_unavailable',
            'Password sign-in is not available here.'
        );
    }
}