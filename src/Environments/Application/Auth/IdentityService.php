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
use SmartLicenseServer\Security\Context\AbstractIdentityProvider;
use SmartLicenseServer\Security\Context\Principal;

/**
 * Identity service provider.
 */
class IdentityService extends AbstractIdentityProvider {
    /**
     * The identity provider for either the web or CLI.
     */
    protected WebIdentityProvider|ConsoleIdentityProvider $provider;
    /**
     * Class constructor.
     * 
     * @param WebIdentityProvider|ConsoleIdentityProvider|null $provider
     */
    public function __construct(
        WebIdentityProvider|ConsoleIdentityProvider|null $provider = null
    ) {
        if ( null === $provider ) {
            $provider   = $this->auto_select_provider();
        }

        $this->provider = $provider;
    }

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
        return $this->provider->logon( $email, $pwd, $remember );
    }

    /**
     * {@inheritdoc}
     */
    public function signup( Request $request ): RequestException|Principal {
        return $this->provider->signup( $request );
    }

    /**
     * {@inheritdoc}
     */
    public function logout(): void {
        $this->provider->logout();
    }

    /**
     * {@inheritdoc}
     */
    public function reset_password(User $user, string $new_pwd): bool {
        return $this->provider->reset_password( $user, $new_pwd );
    }

    /*
    |-------------------
    | INTERNAL HELPERS
    |-------------------
    */

    protected function auto_select_provider() : WebIdentityProvider|ConsoleIdentityProvider {
        if ( in_array( \php_sapi_name(), [ 'cli', 'phpdbg' ], true ) ) {
            return new ConsoleIdentityProvider();
        }

        return new WebIdentityProvider();
    }
}