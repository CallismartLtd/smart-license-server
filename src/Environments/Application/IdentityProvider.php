<?php
/**
 * Identity provider class file
 * 
 * @author Callistus Nwachukwu
 * @since 0.3.0
 */

namespace SmartLicenseServer\Environments\Application;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\AbstractIdentityProvider;
use SmartLicenseServer\Security\Context\Principal;

/**
 * Identity and authentication provider.
 */
class IdentityProvider extends AbstractIdentityProvider {
    /**
     * {@inheritdoc}
     */
    public function authenticate(): ?Principal {
        throw new \Exception('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function logon(string $email, string $pwd, bool $remember = false): RequestException|Principal {
        throw new \Exception('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function signup(Request $request): RequestException|Principal {
        throw new \Exception('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function logout(): void {
        throw new \Exception('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function reset_password(User $user, string $new_pwd): bool {
        throw new \Exception('Not implemented');
    }
}