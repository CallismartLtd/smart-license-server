<?php
/**
 * Web identity provider class file.
 * 
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Auth;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Context\IdentityProviderInterface;
use SmartLicenseServer\Security\Context\Principal;
use SmartLicenseServer\Security\Actors\User;

class WebIdentityProvider implements IdentityProviderInterface {
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
    public function reset_password( User $user, string $new_pwd): bool {
        throw new \Exception('Not implemented');
    }
}