<?php
/**
 * Authentication result DTO.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Authentication
 */

namespace SmartLicenseServer\Security\Authentication;

use SmartLicenseServer\Security\Actors\ActorInterface;

final readonly class AuthenticationResult {

    /*
    |------------------------------------------
    | RESULT STATES
    |------------------------------------------
    */

    /**
     * Authentication was successful.
     *
     * @var string
     */
    public const STATUS_AUTHENTICATED = 'authenticated';

    /**
     * Authentication credentials were invalid.
     *
     * @var string
     */
    public const STATUS_INVALID_CREDENTIALS = 'invalid_credentials';

    /**
     * Authentication credentials were not provided.
     *
     * @var string
     */
    public const STATUS_MISSING_CREDENTIALS = 'missing_credentials';

    /**
     * Authentication was not possible because the actor could not be found.
     *
     * @var string
     */
    public const STATUS_ACTOR_NOT_FOUND = 'actor_not_found';

    /**
     * Authentication failed because the actor is not allowed to authenticate.
     *
     * @var string
     */
    public const STATUS_UNAUTHORIZED = 'unauthorized';

    /**
     * Authentication failed because of an internal error.
     *
     * @var string
     */
    public const STATUS_ERROR = 'error';

    /*
    |------------------------------------------
    | PROPERTIES
    |------------------------------------------
    */

    /**
     * @param string              $status
     * @param ActorInterface|null $actor
     * @param string|null         $error_code
     * @param string|null         $message
     */
    public function __construct(
        public string $status,
        public ?ActorInterface $actor = null,
        public ?string $error_code = null,
        public ?string $message = null,
    ) {}

    /*
    |------------------------------------------
    | FACTORIES
    |------------------------------------------
    */

    /**
     * Create a successful authentication result.
     *
     * @param ActorInterface $actor
     * @param string|null $message
     * @return static
     */
    public static function authenticated(
        ActorInterface $actor,
        ?string $message = null
    ) : static {
        return new static(
            status: static::STATUS_AUTHENTICATED,
            actor: $actor,
            message: $message
        );
    }

    /**
     * Create a failed authentication result.
     *
     * @param string $status
     * @param string|null $error_code
     * @param string|null $message
     * @return static
     */
    public static function failure(
        string $status,
        ?string $error_code = null,
        ?string $message = null
    ) : static {
        return new static(
            status: $status,
            error_code: $error_code,
            message: $message
        );
    }

    /*
    |------------------------------------------
    | STATE
    |------------------------------------------
    */

    /**
     * Determine whether authentication succeeded.
     *
     * @return bool
     */
    public function is_authenticated() : bool {
        return static::STATUS_AUTHENTICATED === $this->status;
    }

    /**
     * Determine whether authentication failed.
     *
     * @return bool
     */
    public function is_failed() : bool {
        return ! $this->is_authenticated();
    }
}